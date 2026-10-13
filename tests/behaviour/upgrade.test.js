/**
 * Updating from 0.45.21, as sites still on it will: its database is already at version 1.7,
 * so only the code upgrade runs, on the first request of any kind after the update. It
 * removes the templates the old version stored compiled to PHP, protects the upload folder,
 * removes other options left by the old version and records the new version.
 *
 * The site is put into the state 0.45.21 left it in, with customised settings (lib/customised.js),
 * stored the way the old version stored them (templates with escaped quotes, output
 * compiled by the old dictionary), using WordPress's functions rather than the plugin's.
 * Then a visitor's request must show exactly what a fresh install with the same settings
 * shows (the snapshots of customised-settings.test.js).
 */
import { before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, defaultTemplates, php, phpString, seed, uploadExists } from './lib/wp.js';
import { customisedOptions, customisedTemplates } from './lib/customised.js';
import { Client } from './lib/client.js';
import { element, matchesSnapshot } from './lib/html.js';

const visitor = new Client();
const staleOutput = '<p>Compiled by 0.45.21</p>';

/**
 * Stores options as 0.45.21 left them, in one request (any later request runs the upgrade).
 *
 * @param {object} options Merged into the plugin's options.
 * @param {{search: string, single: string, css: string}} templates
 */
function storeAsOldVersion( options, templates ) {
	return php( `
		$options = unserialize( base64_decode( get_option( 'sermonbrowser_options' ) ) );
		$options = array_merge( $options, json_decode( ${ phpString( JSON.stringify( options ) ) }, true ) );
		update_option( 'sermonbrowser_options', base64_encode( serialize( $options ) ) );
		$templates = json_decode( ${ phpString( JSON.stringify( templates ) ) }, true );
		update_option( 'sermonbrowser_search_template', base64_encode( addslashes( $templates['search'] ) ) );
		update_option( 'sermonbrowser_single_template', base64_encode( addslashes( $templates['single'] ) ) );
		update_option( 'sermonbrowser_css_style', base64_encode( addslashes( $templates['css'] ) ) );
		update_option( 'sermonbrowser_search_output', base64_encode( ${ phpString( staleOutput ) } ) );
		update_option( 'sermonbrowser_single_output', base64_encode( ${ phpString( staleOutput ) } ) );
		update_option( 'show_donate_reminder', 'off' );
		update_option( 'sb_show_donate_reminder', 'off' );
		@unlink( SB_ABSPATH . $options['upload_dir'] . '.htaccess' );
		return true;
	` );
}

describe( 'the first request after updating from 0.45.21', () => {
	let templates;
	let list;

	before( async () => {
		await seed();
		templates = await customisedTemplates();
		await storeAsOldVersion( { ...customisedOptions, code_version: '0.45.21', db_version: '1.7' }, templates );
		list = await visitor.html( '/sermons/' );
	} );

	it( 'shows the sermon list as a fresh install with the same settings does', () => {
		assert.doesNotMatch( list, /Compiled by 0\.45\.21/, 'the old version\'s compiled template ran' );
		matchesSnapshot( element( list, '<div class="sermon-browser' ), 'customised/list.html' );
	} );

	it( 'shows sermon pages as a fresh install does', async () => {
		matchesSnapshot( element( await visitor.html( '/sermons/?sermon_id=1' ), '<div class="sermon-browser' ), 'customised/sermon-1.html' );
	} );

	it( 'serves the stylesheet as it was saved', async () => {
		assert.equal( ( await visitor.request( '/sb-style.css' ) ).text, templates.css );
	} );

	it( 'records the new version and keeps the settings', async () => {
		const state = await php( "return array( 'code' => sb_get_option( 'code_version' ), 'current' => SB_CURRENT_VERSION, 'db' => sb_get_option( 'db_version' ), 'filter' => sb_get_option( 'filter_type' ), 'hide' => sb_get_option( 'hide_no_attachments' ), 'widgets' => sb_get_option( 'sermons_widget_options' ) );" );
		assert.equal( state.code, state.current );
		assert.equal( state.db, '1.7' );
		assert.equal( state.filter, 'oneclick' );
		assert.equal( state.hide, true );
		assert.deepEqual( state.widgets, customisedOptions.sermons_widget_options );
	} );

	it( 'leaves the templates as they were stored', async () => {
		const stored = await php( "return array( base64_decode( get_option( 'sermonbrowser_search_template' ) ), base64_decode( get_option( 'sermonbrowser_single_template' ) ) );" );
		assert.deepEqual( stored, [ templates.search, templates.single ].map( ( template ) => template.replace( /['"\\]/g, '\\$&' ) ) );
	} );

	it( 'stops scripts running from the upload folder', async () => {
		assert.ok( await uploadExists( '.htaccess' ) );
		const rules = await php( "return file_get_contents( SB_ABSPATH . sb_get_option( 'upload_dir' ) . '.htaccess' );" );
		assert.match( rules, /<FilesMatch "\\\.\(\?i:php\[0-9\]\?\|phtml\|phar\|pht\|shtml\|cgi\|pl\|py\|sh\)\$">\n\tRequire all denied\n<\/FilesMatch>/ );
	} );

	it( 'removes the templates the old version stored compiled to PHP', async () => {
		assert.deepEqual( await php( "return array( get_option( 'sermonbrowser_search_output' ), get_option( 'sermonbrowser_single_output' ) );" ), [ false, false ] );
	} );

	it( 'removes the options for the donation notice that has been removed', async () => {
		assert.deepEqual( await php( "return array( get_option( 'show_donate_reminder' ), get_option( 'sb_show_donate_reminder' ) );" ), [ false, false ] );
	} );

	// Each seed empties the debug log, so each scenario checks its own requests.
	it( 'logs no PHP errors, warnings or notices', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );

describe( 'updating from a version with no filter type saved', () => {
	it( 'uses the drop-down filter', async () => {
		await seed();
		await storeAsOldVersion( { code_version: '0.45.21', filter_type: '' }, await defaultTemplates() );
		const html = await visitor.html( '/sermons/' );
		assert.match( html, /<select name="preacher" id="preacher">/ );
		assert.equal( await php( "return sb_get_option( 'filter_type' );" ), 'dropdown' );
	} );

	// Each seed empties the debug log, so each scenario checks its own requests.
	it( 'logs no PHP errors, warnings or notices', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );

describe( 'updating the database from version 1.6', () => {
	it( 'adds the MP3 shortcode option when an administrator next opens the admin', async () => {
		const { users } = await seed();
		await php( "$options = unserialize( base64_decode( get_option( 'sermonbrowser_options' ) ) ); $options['db_version'] = '1.6'; unset( $options['mp3_shortcode'] ); update_option( 'sermonbrowser_options', base64_encode( serialize( $options ) ) ); return true;" );
		await visitor.html( '/sermons/' );
		assert.equal( await php( "return sb_get_option( 'db_version' );" ), '1.6', 'visitors do not run database upgrades' );
		const admin = await Client.as( users.admin );
		await admin.html( '/wp-admin/' );
		assert.deepEqual( await php( "return array( sb_get_option( 'db_version' ), sb_get_option( 'mp3_shortcode' ) );" ), [ '1.7', '[audio mp3="%SERMONURL%"]' ] );
	} );

	// Each seed empties the debug log, so each scenario checks its own requests.
	it( 'logs no PHP errors, warnings or notices', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
