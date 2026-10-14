/**
 * Copying sermons from the plugin's tables to posts with WP-CLI (wp sermon-browser migrate and
 * verify): a dry run writes nothing, the copy matches the tables, running again updates it, verify
 * finds differences, and the site keeps showing sermons from the tables as before.
 *
 * Removes what it copied afterwards (the seed doesn't know about posts).
 */
import { after, before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, one, php, seed, wp } from './lib/wp.js';
import { Client } from './lib/client.js';
import { element } from './lib/html.js';

const visitor = new Client();
const pages = [ '/sermons/', '/sermons/?sermon_id=1', '/sermons/?preacher=1', '/sermons/?sortby=title&dir=asc' ];
const sermonBrowser = async ( path ) => element( await visitor.html( path ), '<div class="sermon-browser' );
const migrated = () => php( "return (int) ( new WP_Query( array( 'post_type' => 'sb_sermon', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ) ) )->found_posts;" );

/**
 * Runs WP-CLI and returns its output, also when the command fails.
 *
 * @param {string[]} args
 * @return {{ok: boolean, output: string}}
 */
function cli( args ) {
	try {
		return { ok: true, output: wp( args ) };
	} catch ( error ) {
		return { ok: false, output: `${ error.stdout }\n${ error.stderr }` };
	}
}

const removeCopies = () => php( 'SermonBrowser\\Post_Types::delete_all(); return true;' );

let before_migration;
let sermons;

before( async () => {
	await seed();
	await removeCopies();
	sermons = Number( ( await one( 'SELECT COUNT(*) AS n FROM {prefix}sb_sermons' ) ).n );
	before_migration = await Promise.all( pages.map( sermonBrowser ) );
} );

after( removeCopies );

describe( 'wp sermon-browser migrate', () => {
	it( 'says what it would copy in a dry run, and writes nothing', async () => {
		const { ok, output } = cli( [ 'sermon-browser', 'migrate', '--dry-run' ] );

		assert.ok( ok, output );
		assert.match( output, /What copying would do:/ );
		assert.match( output, new RegExp( `- sermons: ${ sermons } new, 0 updated, 0 deleted` ) );
		assert.match( output, /Success: Dry run: nothing was written\./ );
		assert.equal( await migrated(), 0 );
	} );

	it( 'copies every sermon and checks the copy against the tables', async () => {
		const { ok, output } = cli( [ 'sermon-browser', 'migrate' ] );

		assert.ok( ok, output );
		assert.match( output, new RegExp( `- sermons: ${ sermons } new, 0 updated, 0 deleted` ) );
		assert.match( output, /Checked against the tables: no differences\./ );
		assert.match( output, /Success: Copied\./ );
		assert.equal( await migrated(), sermons );
	} );

	it( 'updates the copy when run again, without duplicating it', async () => {
		const { ok, output } = cli( [ 'sermon-browser', 'migrate' ] );

		assert.ok( ok, output );
		assert.match( output, new RegExp( `- sermons: 0 new, ${ sermons } updated, 0 deleted` ) );
		assert.equal( await migrated(), sermons );
	} );

	it( 'leaves the site showing sermons from the tables as before', async () => {
		assert.deepEqual( await Promise.all( pages.map( sermonBrowser ) ), before_migration );
	} );
} );

describe( 'wp sermon-browser verify', () => {
	it( 'says the copy matches the tables', () => {
		const { ok, output } = cli( [ 'sermon-browser', 'verify' ] );

		assert.ok( ok, output );
		assert.match( output, /No differences\./ );
		assert.match( output, /Success: The copy matches the tables\./ );
	} );

	it( 'fails, listing what differs, when the copy has changed', async () => {
		const id = await php( "global $wpdb; $id = (int) $wpdb->get_var( \"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'sb_legacy_id' AND meta_value = '1' AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'sb_sermon')\" ); wp_update_post( array( 'ID' => $id, 'post_title' => 'Changed' ) ); return $id;" );

		const { ok, output } = cli( [ 'sermon-browser', 'verify' ] );

		assert.equal( ok, false );
		assert.match( output, new RegExp( `- Sermon 1 differs from post ${ id } in its title\\.` ) );
		assert.match( output, /Error: The copy differs from the tables\./ );
	} );
} );

describe( 'the debug log', () => {
	it( 'has no PHP errors, warnings or notices from these commands', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
