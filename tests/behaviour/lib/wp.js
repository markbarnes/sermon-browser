/**
 * Talks to the wp-env test site: seeding, database queries and PHP, through the
 * test-only endpoint in tests/mu-plugins/sb-test-api.php, and WP-CLI when needed.
 */
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath( new URL( '../../..', import.meta.url ) );
export const SITE = 'http://localhost:8891';

/**
 * Runs a WP-CLI command on the test site and returns its output.
 *
 * @param {string[]} args
 * @return {string}
 */
export function wp( args ) {
	return execFileSync( 'npx', [ 'wp-env', 'run', '--config=.wp-env.tests.json', 'cli', 'wp', ...args ], {
		cwd: root,
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
	} ).trim();
}

/**
 * Fetches, retrying once if the server closed an idle keep-alive connection.
 *
 * @param {string} url
 * @param {object} options
 * @return {Promise<Response>}
 */
export async function fetchWithRetry( url, options ) {
	try {
		return await fetch( url, options );
	} catch ( error ) {
		if ( error.cause && error.cause.code === 'UND_ERR_SOCKET' ) {
			return fetch( url, options );
		}
		throw error;
	}
}

/**
 * Calls the test-only endpoint.
 *
 * @param {object} request
 * @return {Promise<any>}
 */
async function api( request ) {
	const response = await fetchWithRetry( SITE + '/', {
		method: 'POST',
		headers: { 'X-SB-Test': 'run', 'Content-Type': 'application/json' },
		body: JSON.stringify( request ),
	} );
	const text = await response.text();
	if ( ! response.ok ) {
		throw new Error( `Test API ${ request.action } failed (${ response.status }): ${ text.slice( 0, 500 ) }` );
	}
	try {
		return JSON.parse( text );
	} catch {
		throw new Error( `Test API ${ request.action } returned something other than JSON: ${ text.slice( 0, 500 ) }` );
	}
}

/**
 * Runs PHP on the test site, with WordPress and the plugin loaded.
 * Returns the code's return value, or what it echoes if it returns nothing.
 *
 * @param {string} code
 * @return {Promise<any>}
 */
export function php( code ) {
	return api( { action: 'php', code } );
}

/**
 * Returns the rows of an SQL query as objects with string values. {prefix} is replaced with the table prefix.
 *
 * @param {string} sql
 * @return {Promise<object[]>}
 */
export async function query( sql ) {
	const rows = await api( { action: 'query', sql } );
	if ( rows && rows.error ) {
		throw new Error( `SQL error: ${ rows.error }\n${ sql }` );
	}
	return rows;
}

/**
 * Returns the first row of an SQL query.
 *
 * @param {string} sql
 * @return {Promise<object|undefined>}
 */
export async function one( sql ) {
	return ( await query( sql ) )[ 0 ];
}

/**
 * Resets the test site to the fixtures in tests/fixtures/seed.php.
 *
 * @return {Promise<{page_url: string, upload_dir: string, users: Object<string, {login: string, password: string, id: number}>}>}
 */
export function seed() {
	return api( { action: 'seed' } );
}

/**
 * Returns the PHP errors, warnings, notices and deprecations logged since the last seed.
 *
 * @return {Promise<string[]>}
 */
export async function debugLog() {
	const log = await php( "return (string) @file_get_contents( WP_CONTENT_DIR . '/debug.log' );" );
	return log.split( '\n' ).filter( ( line ) => /PHP (Fatal|Warning|Notice|Deprecated|Parse)/.test( line ) );
}

/**
 * Quotes a string as a PHP single-quoted literal.
 *
 * @param {string} value
 * @return {string}
 */
export function phpString( value ) {
	return "'" + String( value ).replaceAll( '\\', '\\\\' ).replaceAll( "'", "\\'" ) + "'";
}

/**
 * Returns true if a file exists in the sermon upload folder.
 *
 * @param {string} path Relative to the upload folder.
 * @return {Promise<boolean>}
 */
export function uploadExists( path ) {
	return php( `return file_exists( SB_ABSPATH . sb_get_option( 'upload_dir' ) . ${ phpString( path ) } );` );
}

/**
 * Sets the plugin's options (as the Options and Templates pages would save them).
 *
 * @param {object} options Option names and values; arrays and objects are stored as PHP arrays.
 * @return {Promise<true>}
 */
export function setOptions( options ) {
	return php( `foreach ( json_decode( ${ phpString( JSON.stringify( options ) ) }, true ) as $name => $value ) { sb_update_option( $name, $value ); } return true;` );
}

/**
 * Sets the search results and single sermon templates and compiles them, as saving the Templates page does.
 *
 * @param {{search?: string, single?: string}} templates
 * @return {Promise<true>}
 */
export function useTemplates( { search, single } ) {
	const code = [ "require_once SB_INCLUDES_DIR . '/dictionary.php';" ];
	if ( search !== undefined ) {
		code.push( `sb_update_option( 'search_template', ${ phpString( search ) } ); sb_update_option( 'search_output', sb_compile_template( ${ phpString( search ) }, sb_search_results_dictionary() ) );` );
	}
	if ( single !== undefined ) {
		code.push( `sb_update_option( 'single_template', ${ phpString( single ) } ); sb_update_option( 'single_output', sb_compile_template( ${ phpString( single ) }, sb_sermon_page_dictionary() ) );` );
	}
	return php( code.join( ' ' ) + ' return true;' );
}

/**
 * Returns the plugin's default templates and stylesheet, as a fresh install stores them.
 *
 * @return {Promise<{search: string, single: string, css: string}>}
 */
export function defaultTemplates() {
	return php( "require_once SB_INCLUDES_DIR . '/sb-install.php'; return array( 'search' => sb_default_multi_template(), 'single' => sb_default_single_template(), 'css' => sb_default_css() );" );
}
