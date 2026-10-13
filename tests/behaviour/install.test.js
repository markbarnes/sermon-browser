/**
 * Uninstalling and installing: the Uninstall page removes the plugin's tables, options and
 * (if asked) uploaded files and deactivates it; once reactivated, an administrator's next
 * visit to the admin installs it afresh.
 *
 * The tests run in order and leave a fresh install behind (the next test file seeds anyway).
 * While the plugin is deactivated, state is read with WordPress's functions and SQL.
 */
import { after, before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, php, query, seed } from './lib/wp.js';
import { Client } from './lib/client.js';
import { element, matchesSnapshot } from './lib/html.js';

const tables = [ 'sb_books', 'sb_books_sermons', 'sb_preachers', 'sb_series', 'sb_sermons', 'sb_sermons_tags', 'sb_services', 'sb_stuff', 'sb_tags' ];
const existingTables = async () => ( await query( "SHOW TABLES LIKE '{prefix}sb\\_%'" ) ).map( ( row ) => Object.values( row )[ 0 ].replace( /^.*?(sb_)/, '$1' ) ).sort();
const uploadFolder = ( path = '' ) => php( `return file_exists( ABSPATH . 'wp-content/uploads/sermons/' . ${ JSON.stringify( path ) } );` );
const pluginActive = () => php( "return in_array( 'sermon-browser/sermon.php', (array) get_option( 'active_plugins' ), true );" );

let users;
let admin;

before( async () => {
	( { users } = await seed() );
	admin = await Client.as( users.admin );
} );

after( async () => {
	// Whatever happened, leave the plugin active for the other test files.
	await php( "require_once ABSPATH . 'wp-admin/includes/plugin.php'; activate_plugin( 'sermon-browser/sermon.php' ); return true;" );
} );

describe( 'uninstalling', () => {
	const uninstall = async ( client, fields ) => client.request( '/wp-admin/admin.php?page=sermon-browser/uninstall.php', { form: { uninstall: 'Uninstall', ...fields } } );

	it( 'is refused to editors, who cannot edit plugins', async () => {
		const editor = await Client.as( users.editor );
		const response = await uninstall( editor, { sermon_browser_uninstall_nonce: await editor.nonce( 'uninstall.php', 'sermon_browser_uninstall_nonce' ), wipe: '1' } );
		assert.notEqual( response.status, 200 );
		assert.deepEqual( await existingTables(), tables );
	} );

	it( 'is refused without the form\'s nonce', async () => {
		const response = await uninstall( admin, { wipe: '1' } );
		assert.match( response.text, /You do not have the correct permissions to Uninstall SermonBrowser/ );
		assert.deepEqual( await existingTables(), tables );
		assert.ok( await uploadFolder( '2026-03-01-faith-and-works.mp3' ) );
	} );

	it( 'removes the tables, options and uploaded files, and deactivates the plugin', async () => {
		const nonce = await admin.nonce( 'uninstall.php', 'sermon_browser_uninstall_nonce' );
		const response = await uninstall( admin, { sermon_browser_uninstall_nonce: nonce, wipe: '1' } );
		assert.match( response.text, /Uninstall completed\. The SermonBrowser plugin has been deactivated\./ );
		assert.deepEqual( await existingTables(), [] );
		assert.deepEqual( await query( "SELECT option_name FROM {prefix}options WHERE option_name LIKE 'sermonbrowser\\_%'" ), [] );
		assert.equal( await pluginActive(), false );
		assert.equal( await uploadFolder( '2026-03-01-faith-and-works.mp3' ), false );
		assert.equal( await uploadFolder( '.htaccess' ), false );
		// Only files at the top of the folder are deleted; the images folder and what's in it stay.
		assert.ok( await uploadFolder( 'images/profile.jpg' ) );
	} );
} );

describe( 'installing', () => {
	before( async () => {
		// Start without an upload folder, as on a site that never had the plugin.
		await php( "$dir = ABSPATH . 'wp-content/uploads/sermons/'; foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $file ) { $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); } rmdir( $dir ); return true;" );
		assert.equal( await uploadFolder(), false );
		await php( "require_once ABSPATH . 'wp-admin/includes/plugin.php'; activate_plugin( 'sermon-browser/sermon.php' ); return true;" );
		await admin.html( '/wp-admin/' );
	} );

	it( 'creates the tables with sample preachers, series and services and the books of the Bible', async () => {
		assert.deepEqual( await existingTables(), tables );
		assert.deepEqual( ( await query( 'SELECT name FROM {prefix}sb_preachers ORDER BY id' ) ).map( ( row ) => row.name ), [ 'C H Spurgeon', 'Martyn Lloyd-Jones' ] );
		assert.deepEqual( ( await query( 'SELECT name FROM {prefix}sb_series ORDER BY id' ) ).map( ( row ) => row.name ), [ 'Exposition of the Psalms', 'Exposition of Romans' ] );
		assert.deepEqual( ( await query( 'SELECT name, time FROM {prefix}sb_services ORDER BY id' ) ).map( ( row ) => `${ row.name } ${ row.time }` ), [ 'Sunday Morning 10:30', 'Sunday Evening 18:00', 'Midweek Meeting 19:00', 'Special event 20:00' ] );
		const books = ( await query( 'SELECT name FROM {prefix}sb_books ORDER BY id' ) ).map( ( row ) => row.name );
		assert.equal( books.length, 66 );
		assert.deepEqual( [ books[ 0 ], books[ 18 ], books[ 39 ], books[ 65 ] ], [ 'Genesis', 'Psalm', 'Matthew', 'Revelation' ] );
		assert.deepEqual( await query( 'SELECT id FROM {prefix}sb_sermons' ), [] );
	} );

	it( 'stores the default settings and the current versions', async () => {
		const options = await php( "return array( 'code_version' => sb_get_option( 'code_version' ), 'current' => SB_CURRENT_VERSION, 'db_version' => sb_get_option( 'db_version' ), 'upload_dir' => sb_get_option( 'upload_dir' ), 'podcast_url' => sb_get_option( 'podcast_url' ), 'site' => site_url(), 'filter_type' => sb_get_option( 'filter_type' ), 'filter_hide' => sb_get_option( 'filter_hide' ), 'hide_no_attachments' => sb_get_option( 'hide_no_attachments' ), 'sermons_per_page' => sb_get_option( 'sermons_per_page' ), 'mp3_shortcode' => sb_get_option( 'mp3_shortcode' ), 'import_filename' => sb_get_option( 'import_filename' ) );" );
		assert.equal( options.code_version, options.current );
		assert.deepEqual( { ...options, code_version: undefined, current: undefined, site: undefined }, {
			code_version: undefined,
			current: undefined,
			site: undefined,
			db_version: '1.7',
			upload_dir: 'wp-content/uploads/sermons/',
			podcast_url: `${ options.site }?podcast`,
			filter_type: 'oneclick',
			filter_hide: 'hide',
			hide_no_attachments: false,
			sermons_per_page: '10',
			mp3_shortcode: '[audio mp3="%SERMONURL%"]',
			import_filename: 'none',
		} );
	} );

	it( 'creates the upload folder, protected from running scripts, with a folder for images', async () => {
		assert.ok( await uploadFolder( '.htaccess' ) );
		assert.ok( await uploadFolder( 'images/' ) );
	} );

	it( 'shows an empty sermon list with the default templates', async () => {
		const html = element( await new Client().html( '/sermons/' ), '<div class="sermon-browser' );
		matchesSnapshot( html, 'install-empty-list.html' );
		assert.match( html, /Sermons \(0\)/ );
	} );

	it( 'logs no PHP errors, warnings or notices', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
