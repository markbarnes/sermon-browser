/**
 * SQL injection.
 *
 * Before 0.46.0 several queries interpolated values instead of preparing them. None of
 * these tests' payloads got through in 0.45.22, because the values happened to be
 * slashed (WordPress adds slashes to request data), cast to integers, or HTML-encoded
 * (getID3 encodes quotes in ID3 tags). The queries now use prepared statements, and these
 * tests check that hostile values stay data whatever happens to the code around them.
 */
import { before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, one, php, query, seed } from './lib/wp.js';
import { Client } from './lib/client.js';

let fixtures;
let admin;

before( async () => {
	fixtures = await seed();
	admin = await Client.as( fixtures.users.admin );
} );

/**
 * Builds an MP3 with an ID3v2.3 tag holding the given text frames (e.g. {TALB: 'album'}).
 */
function mp3WithTags( frames ) {
	const frameBuffers = Object.entries( frames ).map( ( [ id, text ] ) => {
		const content = Buffer.concat( [ Buffer.from( [ 0 ] ), Buffer.from( text, 'latin1' ) ] );
		const header = Buffer.alloc( 10 );
		header.write( id, 0, 'latin1' );
		header.writeUInt32BE( content.length, 4 );
		return Buffer.concat( [ header, content ] );
	} );
	const tag = Buffer.concat( frameBuffers );
	const size = tag.length;
	const header = Buffer.from( [ 0x49, 0x44, 0x33, 3, 0, 0, ( size >> 21 ) & 0x7f, ( size >> 14 ) & 0x7f, ( size >> 7 ) & 0x7f, size & 0x7f ] );
	const frame = Buffer.alloc( 417 );
	frame.set( [ 0xff, 0xfb, 0x90, 0x64 ] );
	return Buffer.concat( [ header, tag, ...Array( 20 ).fill( frame ) ] );
}

const databaseErrors = async () => ( await php( "return (string) @file_get_contents( WP_CONTENT_DIR . '/debug.log' );" ) ).split( '\n' ).filter( ( line ) => line.includes( 'WordPress database error' ) );

describe( 'importing ID3 tags', () => {
	it( 'treats an album tag as text, not SQL', async () => {
		await php( "sb_update_option( 'import_album', true ); sb_update_option( 'import_title', true ); return true;" );
		// If the tag reached SQL unprepared, this would match series 3 instead of creating a new series.
		const album = "No such series' OR id = '3";
		const form = new FormData();
		form.append( 'sb_upload_nonce', await admin.nonce( 'files.php', 'sb_upload_nonce' ) );
		form.append( 'save', 'Upload' );
		form.append( 'upload', new File( [ mp3WithTags( { TIT2: 'Tagged sermon', TALB: album } ) ], 'tagged.mp3' ) );
		await admin.request( '/wp-admin/admin.php?page=sermon-browser/files.php', { multipart: form } );
		const file = await one( "SELECT id FROM {prefix}sb_stuff WHERE name = 'tagged.mp3'" );
		assert.ok( file, 'the tagged MP3 should upload' );

		const page = await admin.adminPage( `new_sermon.php&getid3=${ file.id }` );
		assert.match( page, /value="Tagged sermon"/, 'the title tag should be read' );
		const series = await query( "SELECT id, name FROM {prefix}sb_series WHERE name LIKE 'No such series%'" );
		assert.equal( series.length, 1, 'a series named after the album should be created' );
		// getID3 HTML-encodes tag text, and the plugin stores it as it comes. getID3 uses htmlentities()'s
		// default flags, which encode single quotes only from PHP 8.1.
		assert.ok( [ album, album.replaceAll( "'", '&#039;' ) ].includes( series[ 0 ].name ), series[ 0 ].name );
		assert.match( page, new RegExp( `<option value="${ series[ 0 ].id }" selected="selected">` ) );
		assert.deepEqual( await databaseErrors(), [] );
	} );
} );

describe( 'saving a sermon', () => {
	it( 'stores a time containing quotes as text', async () => {
		const author = await Client.as( fixtures.users.author );
		const nonce = await author.nonce( 'new_sermon.php&mid=3', 'sermon_browser_save_nonce' );
		await author.request( '/wp-admin/admin.php?page=sermon-browser/new_sermon.php&mid=3', {
			form: { sermon_browser_save_nonce: nonce, save: 'Save', title: 'Romans 5: peace with God', preacher: '1', service: '1', series: '1', date: '2026-03-08', time: "x', title='hacked", override: 'on', tags: 'grace', 'file[]': '0' },
		} );
		const sermon = await one( 'SELECT title, time FROM {prefix}sb_sermons WHERE id = 3' );
		assert.equal( sermon.title, 'Romans 5: peace with God' );
		assert.deepEqual( await databaseErrors(), [] );
	} );
} );

describe( 'front-end filters', () => {
	const visitor = new Client();
	const cases = {
		title: "' OR 1=1 -- ",
		date: '2026-01-01" OR "1"="1',
		enddate: "2026-12-31' OR '1'='1",
		book: "Romans' OR '1'='1",
		stag: "grace' OR '1'='1",
		sortby: 'm.id; DROP TABLE wp_sb_sermons',
		dir: 'asc, (SELECT SLEEP(5))',
		preacher: '1 OR 1=1',
		pagenum: '1; DROP TABLE wp_sb_sermons',
	};

	for ( const [ parameter, value ] of Object.entries( cases ) ) {
		it( `treats ${ parameter } as data`, async () => {
			const started = Date.now();
			const response = await visitor.request( `/sermons/?${ parameter }=${ encodeURIComponent( value ) }` );
			assert.equal( response.status, 200 );
			assert.ok( Date.now() - started < 4000, 'the query should not have been delayed' );
			const match = response.text.match( /Sermons \((\d+)\)/ );
			assert.ok( match, 'the sermon list should render' );
			if ( [ 'title', 'book', 'stag' ].includes( parameter ) ) {
				assert.equal( match[ 1 ], '0', 'the injected condition should not match every sermon' );
			}
			if ( [ 'date', 'enddate' ].includes( parameter ) ) {
				// MySQL reads a date followed by other text as the date, so expect the same result as the clean date.
				const clean = await visitor.request( `/sermons/?${ parameter }=${ value.slice( 0, 10 ) }` );
				assert.equal( match[ 1 ], clean.text.match( /Sermons \((\d+)\)/ )[ 1 ] );
			}
		} );
	}

	it( 'leaves the tables intact and logs no database errors', async () => {
		assert.equal( ( await one( 'SELECT COUNT(*) n FROM {prefix}sb_sermons' ) ).n, '5' );
		assert.deepEqual( await databaseErrors(), [] );
	} );
} );

describe( 'the podcast feed', () => {
	it( 'treats filter values as data', async () => {
		const response = await new Client().request( `/sermons/?podcast&title=${ encodeURIComponent( "' OR 1=1 -- " ) }` );
		assert.equal( response.status, 200 );
		assert.doesNotMatch( response.text, /<item>/ );
		assert.deepEqual( await databaseErrors(), [] );
	} );
} );

describe( 'the debug log', () => {
	it( 'has no PHP errors from these requests', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
