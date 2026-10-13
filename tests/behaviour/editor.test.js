/**
 * The sermons page in the block editor.
 *
 * The editor screen preloads the page's REST data while the admin page loads, which
 * renders the content (and so the shortcode) with is_admin() true, where the front-end
 * code isn't loaded by default.
 */
import { before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, one, seed } from './lib/wp.js';
import { Client } from './lib/client.js';

let admin;
let pageId;

before( async () => {
	const site = await seed();
	admin = await Client.as( site.users.admin );
	pageId = ( await one( "SELECT ID FROM {prefix}posts WHERE post_type = 'page' AND post_name = 'sermons' AND post_status = 'publish'" ) ).ID;
} );

describe( 'the block editor', () => {
	it( 'opens the sermons page, rendering the shortcode in the preloaded data', async () => {
		const response = await admin.request( `/wp-admin/post.php?post=${ pageId }&action=edit` );
		assert.equal( response.status, 200 );
		assert.doesNotMatch( response.text, /critical error|Fatal error/ );
		// The preloaded REST response has the rendered content, JSON-encoded.
		assert.match( response.text, /class=\\"sermon-browser/ );
	} );
} );

describe( 'the debug log', () => {
	it( 'has no PHP errors, warnings or notices from opening the editor', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
