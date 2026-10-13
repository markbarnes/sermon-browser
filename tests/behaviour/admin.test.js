/**
 * The admin pages' forms that the security tests don't reach: saving a preacher, adding
 * and deleting a sermon, the redirects to the Add Sermon page after an upload or import,
 * and the widget settings forms.
 */
import { before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, one, php, query, seed } from './lib/wp.js';
import { Client } from './lib/client.js';

const MP3 = Buffer.concat( [ Buffer.from( 'ID3\x03\x00\x00\x00\x00\x00\x00', 'latin1' ), Buffer.alloc( 20 * 417 ).fill( 0 ) ] );

let admin;

before( async () => {
	const site = await seed();
	admin = await Client.as( site.users.admin );
} );

// The redirect is printed as a script: document.location = "…"; (or '…')
const redirect = ( html ) => {
	const match = html.match( /document\.location = (?:"([^"]+)"|'([^']+)');/ );
	assert.ok( match, 'no redirect in the response' );
	return match[ 1 ] ? JSON.parse( `"${ match[ 1 ] }"` ) : match[ 2 ];
};

describe( 'the Files page', () => {
	it( 'sends an uploaded MP3 to the Add Sermon page when import options are set', async () => {
		await php( "sb_update_option( 'import_title', true ); return true;" );
		const form = new FormData();
		form.append( 'save', 'Upload' );
		form.append( 'sb_upload_nonce', await admin.nonce( 'files.php', 'sb_upload_nonce' ) );
		form.append( 'upload', new File( [ MP3 ], 'to-import.mp3' ) );
		const response = await admin.request( '/wp-admin/admin.php?page=sermon-browser/files.php', { multipart: form } );
		const { id } = await one( "SELECT id FROM {prefix}sb_stuff WHERE name = 'to-import.mp3'" );
		assert.match( redirect( response.text ), new RegExp( `/wp-admin/admin\\.php\\?page=sermon-browser/new_sermon\\.php&getid3=${ id }$` ) );
	} );

	it( 'sends a linked URL to the Add Sermon page', async () => {
		const response = await admin.request( '/wp-admin/admin.php?page=sermon-browser/files.php', {
			form: { import_url: 'Import', url: 'https://example.org/linked.mp3', import_type: 'remote', sb_upload_nonce: await admin.nonce( 'files.php', 'sb_upload_nonce' ) },
		} );
		const { id } = await one( "SELECT id FROM {prefix}sb_stuff WHERE name = 'https://example.org/linked.mp3'" );
		assert.match( redirect( response.text ), new RegExp( `new_sermon\\.php&getid3=${ id }$` ) );
	} );
} );

describe( 'the Preachers page', () => {
	it( 'saves a new preacher, slashed as the plugin stores text', async () => {
		const response = await admin.request( '/wp-admin/admin.php?page=sermon-browser/preachers.php&act=new', {
			form: { save: 'Save', pid: '0', name: "Dafydd O'Brien", description: 'Visiting from Swansea.', sermon_manage_preachers_nonce: await admin.nonce( 'preachers.php&act=new', 'sermon_manage_preachers_nonce' ) },
		} );
		assert.match( redirect( response.text ), /preachers\.php&saved=true$/ );
		const preacher = await one( "SELECT name, description FROM {prefix}sb_preachers WHERE name LIKE 'Dafydd%'" );
		assert.deepEqual( { ...preacher }, { name: "Dafydd O\\'Brien", description: 'Visiting from Swansea.' } );
		assert.match( await admin.adminPage( 'preachers.php' ), /<td>Dafydd O&#039;Brien<\/td>/ );
	} );
} );

describe( 'the Add Sermon page', () => {
	let id;

	it( 'saves a new sermon with a passage, tags, a URL and an embed', async () => {
		const response = await admin.request( '/wp-admin/admin.php?page=sermon-browser/new_sermon.php', {
			form: {
				save: 'Save',
				sermon_browser_save_nonce: await admin.nonce( 'new_sermon.php', 'sermon_browser_save_nonce' ),
				title: 'In the beginning',
				preacher: '2',
				service: '2',
				series: '1',
				date: '2026-05-03',
				'start[book][]': 'Genesis',
				'start[chapter][]': '1',
				'start[verse][]': '1',
				'end[book][]': 'Genesis',
				'end[chapter][]': '1',
				'end[verse][]': '5',
				tags: 'creation, light',
				'url[]': 'https://example.org/notes.pdf',
				'code[]': '<iframe src="https://player.vimeo.com/video/1"></iframe>',
				description: 'Let there be light.',
			},
		} );
		assert.match( redirect( response.text ), /sermon\.php&saved=true$/ );
		const sermon = await one( "SELECT id, datetime, preacher_id, service_id, series_id, description FROM {prefix}sb_sermons WHERE title = 'In the beginning'" );
		id = sermon.id;
		// Service 2's default time is 18:00.
		assert.equal( sermon.datetime, '2026-05-03 18:00:00' );
		assert.deepEqual( [ sermon.preacher_id, sermon.service_id, sermon.series_id, sermon.description ], [ '2', '2', '1', 'Let there be light.' ] );
		assert.deepEqual( ( await query( `SELECT book_name, chapter, verse, type FROM {prefix}sb_books_sermons WHERE sermon_id = ${ id } ORDER BY type DESC` ) ).map( ( row ) => Object.values( row ).join( ' ' ) ), [ 'Genesis 1 1 start', 'Genesis 1 5 end' ] );
		assert.deepEqual( ( await query( `SELECT t.name FROM {prefix}sb_sermons_tags st JOIN {prefix}sb_tags t ON t.id = st.tag_id WHERE st.sermon_id = ${ id } ORDER BY t.name` ) ).map( ( row ) => row.name ), [ 'creation', 'light' ] );
		assert.deepEqual( ( await query( `SELECT type FROM {prefix}sb_stuff WHERE sermon_id = ${ id } ORDER BY type` ) ).map( ( row ) => row.type ), [ 'code', 'url' ] );
	} );

	it( 'deletes it from the Sermons page', async () => {
		const list = await admin.adminPage( 'sermon.php' );
		const link = list.match( new RegExp( `href="([^"]*page=sermon-browser%2Fsermon\\.php&(?:#038|amp);mid=${ id }&(?:#038|amp);sermon_manage_sermons_nonce=[^"]+)"` ) );
		assert.ok( link, 'no delete link' );
		const response = await admin.request( link[ 1 ].replaceAll( /&(#038|amp);/g, '&' ) );
		assert.match( response.text, /Sermon removed from database\./ );
		assert.equal( ( await one( `SELECT COUNT(*) n FROM {prefix}sb_sermons WHERE id = ${ id }` ) ).n, '0' );
		assert.equal( ( await one( `SELECT COUNT(*) n FROM {prefix}sb_books_sermons WHERE sermon_id = ${ id }` ) ).n, '0' );
		assert.equal( ( await one( `SELECT COUNT(*) n FROM {prefix}sb_stuff WHERE sermon_id = ${ id }` ) ).n, '0' );
		assert.equal( ( await one( "SELECT COUNT(*) n FROM {prefix}sb_tags WHERE name IN ('creation', 'light')" ) ).n, '0' );
	} );
} );

describe( 'the widget settings forms', () => {
	const control = ( call, post ) => php( `require_once SB_INCLUDES_DIR . '/admin.php'; $_POST = ${ post }; ob_start(); ${ call }; $html = ob_get_clean(); $_POST = array(); return $html;` );

	it( 'saves the popular widget', async () => {
		const html = await control( 'sb_widget_popular_control()', "array( 'widget-popular-sermons-submit' => '1', 'widget-popular-title' => 'Most <b>popular</b>', 'widget-popular-limit' => '7', 'widget-popular-display-series' => '1' )" );
		assert.deepEqual( await php( "return sb_get_option( 'popular_widget_options' );" ), { title: 'Most popular', limit: 7, display_sermons: false, display_series: true, display_preachers: false } );
		assert.match( html, /name="widget-popular-title" type="text" value="Most popular"/ );
		assert.match( html, /<option value="7" selected="yes">7<\/option>/ );
	} );

	it( 'saves the sermons widget', async () => {
		const html = await control( "sb_widget_sermon_control( array( 'number' => 4 ) )", "array( 'sidebar' => 'none', 'widget-sermon' => array( 4 => array( 'limit' => '3', 'title' => 'Latest \"sermons\"', 'preacher' => '1', 'service' => '0', 'series' => '0', 'book' => '1', 'preacherz' => '1', 'date' => '0' ) ) )" );
		const options = await php( "$o = sb_get_option( 'sermons_widget_options' ); return $o[4];" );
		assert.deepEqual( options, { limit: 3, preacherz: 1, book: 1, preacher: 1, service: 0, series: 0, title: 'Latest "sermons"', date: 0 } );
		assert.match( html, /name="widget-sermon\[4\]\[title\]" type="text" value="Latest &quot;sermons&quot;"/ );
		assert.match( html, /<option value="1" selected="selected">Dylan Jones<\/option>/ );
	} );
} );

describe( 'the debug log', () => {
	it( 'has no PHP errors from these requests', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
