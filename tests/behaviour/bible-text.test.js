/**
 * Bible text on sermon pages: [esvtext] (api.esv.org, with the site's key).
 *
 * tests/mu-plugins/sb-test-http.php answers the two APIs like the real ones, with a script
 * tag in the text, and logs every request.
 */
import { before, beforeEach, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, php, phpString, seed } from './lib/wp.js';
import { Client } from './lib/client.js';
import { assertNoLiveScript } from './lib/html.js';

const visitor = new Client();

before( async () => {
	await seed();
} );

beforeEach( async () => {
	await php( "delete_option( 'sb_test_http_log' ); sb_update_option( 'esv_api_key', '' ); return true;" );
} );

const useSingleTemplate = ( template ) =>
	php( `require_once SB_INCLUDES_DIR . '/dictionary.php'; sb_update_option( 'single_template', ${ phpString( template ) } ); sb_update_option( 'single_output', sb_compile_template( ${ phpString( template ) }, sb_sermon_page_dictionary() ) ); return true;` );
const requests = async () => ( await php( "return get_option( 'sb_test_http_log', array() );" ) ).map( ( request ) => request.url );
const sermonPage = async ( id ) => visitor.html( `/sermons/?sermon_id=${ id }` );

describe( 'ESV text', () => {
	before( () => useSingleTemplate( '<div class="bible-text">[esvtext]</div>' ) );

	it( 'is fetched with the site’s API key and shown, filtered', async () => {
		await php( "sb_update_option( 'esv_api_key', 'test-esv-key' ); return true;" );
		const html = await sermonPage( 1 );
		assert.deepEqual( await requests(), [ 'https://api.esv.org/v3/passage/html/?q=Romans%203%3A21-31&include-headings=false&include-footnotes=false' ] );
		assert.match( html, /<b class="verse-num" id="v45003021-1">21&nbsp;<\/b>But now the righteousness of God has been manifested apart from the law/ );
		assert.match( html, /class="copyright">ESV<\/a>/ );
		assert.doesNotMatch( html, /<script>alert\("esv"\)/ );
	} );

	it( 'fetches each passage of a sermon', async () => {
		await php( "sb_update_option( 'esv_api_key', 'test-esv-key' ); return true;" );
		await sermonPage( 3 );
		assert.deepEqual( ( await requests() ).map( ( url ) => new URL( url ).searchParams.get( 'q' ) ), [ 'Romans 5:1-11', 'Isaiah 53:4-6' ] );
	} );
} );

describe( 'the debug log', () => {
	it( 'has no PHP errors from these requests', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
