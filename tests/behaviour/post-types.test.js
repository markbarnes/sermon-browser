/**
 * The sermon post type and its taxonomies, which sermons will be stored in, are registered but
 * hidden until the plugin reads from them: a sermon stored there has no page, feed, REST route,
 * search result or admin screen, and visitors and editors see nothing new.
 *
 * Adds a published sermon post with a preacher, removed afterwards (the seed doesn't know about it).
 */
import { after, before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, php, seed } from './lib/wp.js';
import { Client } from './lib/client.js';

const visitor = new Client();
let admin;
let sermon;

before( async () => {
	const site = await seed();
	sermon = await php( `
		$id = wp_insert_post( array( 'post_type' => 'sb_sermon', 'post_status' => 'publish', 'post_title' => 'Hidden sermon zebra', 'post_name' => 'hidden-sermon-zebra' ) );
		wp_set_object_terms( $id, 'Hidden preacher', 'sb_preacher' );
		return $id;
	` );
	admin = await Client.as( site.users.admin );
} );

after( async () => {
	await php( `
		wp_delete_post( ${ Number( sermon ) }, true );
		foreach ( get_terms( array( 'taxonomy' => 'sb_preacher', 'hide_empty' => false, 'fields' => 'ids' ) ) as $term ) {
			wp_delete_term( $term, 'sb_preacher' );
		}
		return true;
	` );
} );

describe( 'a sermon stored in the post type', () => {
	it( 'has no page, by id or by name', async () => {
		for ( const path of [ `/?p=${ sermon }`, `/?post_type=sb_sermon&p=${ sermon }`, '/?sb_sermon=hidden-sermon-zebra', '/hidden-sermon-zebra/', '/sb_sermon/hidden-sermon-zebra/' ] ) {
			const response = await visitor.request( path );
			assert.doesNotMatch( response.text, /Hidden sermon zebra/, path );
		}
	} );

	it( 'is not in search results or feeds', async () => {
		for ( const path of [ '/?s=zebra', '/?s=zebra&post_type=sb_sermon', '/?feed=rss2', '/?feed=rss2&post_type=sb_sermon' ] ) {
			assert.doesNotMatch( await visitor.html( path ), /Hidden sermon zebra/, path );
		}
	} );

	it( 'has no REST route, even for an administrator', async () => {
		for ( const client of [ visitor, admin ] ) {
			const types = await client.request( '/?rest_route=/wp/v2/types' );
			assert.doesNotMatch( types.text, /sb_sermon/ );
			const taxonomies = await client.request( '/?rest_route=/wp/v2/taxonomies' );
			assert.doesNotMatch( taxonomies.text, /sb_preacher|sb_series|sb_service|sb_sermon_tag|sb_bible_book/ );
			const search = await client.request( '/?rest_route=/wp/v2/search&search=zebra' );
			assert.doesNotMatch( search.text, /Hidden sermon zebra/ );
		}
	} );

	it( 'has no taxonomy archive', async () => {
		for ( const path of [ '/?sb_preacher=hidden-preacher', '/?taxonomy=sb_preacher&term=hidden-preacher' ] ) {
			assert.doesNotMatch( await visitor.html( path ), /Hidden sermon zebra/, path );
		}
	} );

	it( 'has no admin screens or menu items', async () => {
		const dashboard = await admin.html( '/wp-admin/' );
		assert.doesNotMatch( dashboard, /post_type=sb_sermon|taxonomy=sb_/ );
		const list = await admin.request( '/wp-admin/edit.php?post_type=sb_sermon' );
		assert.doesNotMatch( list.text, /Hidden sermon zebra/ );
		const terms = await admin.request( '/wp-admin/edit-tags.php?taxonomy=sb_preacher&post_type=sb_sermon' );
		assert.doesNotMatch( terms.text, /Hidden preacher/ );
	} );
} );

describe( 'the debug log', () => {
	it( 'has no PHP errors, warnings or notices from these views', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
