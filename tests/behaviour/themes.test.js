/**
 * Block themes and classic themes: the plugin's output is the same in both, and the page
 * title is only changed by sb_page_title(), through wp_title(), which only older classic
 * themes call.
 *
 * The test site's block theme is Twenty Twenty-Five; the classic theme is tests/themes/sb-classic,
 * which calls wp_title() itself unless the sb_test_title_tag option gives it title-tag support.
 * The other test files use the block theme.
 */
import { after, before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, php, phpString, seed } from './lib/wp.js';
import { Client } from './lib/client.js';
import { element, matchesSnapshot } from './lib/html.js';

const visitor = new Client();
const widgetIds = [ 'sermon-2', 'sermon-browser-tags', 'sermon-browser-popular' ];

let blockTheme;
let blockWidgets;

const title = ( html ) => html.match( /<title>([\s\S]*?)<\/title>/ )[ 1 ];
const sermonBrowser = ( html ) => element( html, '<div class="sermon-browser' );
const widgets = ( html ) => widgetIds.map( ( id ) => element( html, `<section id="${ id }"` ) );
const useTheme = ( stylesheet ) => php( `switch_theme( ${ phpString( stylesheet ) } ); return true;` );

before( async () => {
	await seed();
	await php( `sb_update_option( 'sermons_widget_options', array( 2 => array( 'limit' => '3', 'preacherz' => 1, 'book' => 1, 'preacher' => 0, 'service' => 0, 'series' => 0, 'title' => 'Recent', 'date' => 1, 'player' => 0 ) ) ); return true;` );
	blockTheme = await php( 'return get_stylesheet();' );
	// The block theme shows the widgets with Legacy Widget blocks on this page.
	blockWidgets = widgets( await visitor.html( '/sermon-widgets/' ) );
} );

after( async () => {
	await useTheme( blockTheme );
	await php( "delete_option( 'sb_test_title_tag' ); return true;" );
} );

describe( 'in a block theme', () => {
	it( 'leaves the page title alone on sermon pages (block themes never call wp_title())', async () => {
		const list = await visitor.html( '/sermons/' );
		const sermon = await visitor.html( '/sermons/?sermon_id=1' );
		assert.equal( title( sermon ), title( list ) );
	} );
} );

describe( 'in a classic theme that calls wp_title()', () => {
	before( () => useTheme( 'sb-classic' ) );

	// Block themes run wptexturize() over the whole page, the plugin's output included, so quotes
	// in the list's embed code are curly there (list.html) and straight here.
	it( 'shows the sermon list', async () => {
		matchesSnapshot( sermonBrowser( await visitor.html( '/sermons/' ) ), 'classic/list.html' );
	} );

	it( 'shows a sermon page as it does in a block theme', async () => {
		matchesSnapshot( sermonBrowser( await visitor.html( '/sermons/?sermon_id=1' ) ), 'sermon-1.html' );
	} );

	it( 'shows the widgets as they are in a block theme', async () => {
		assert.deepEqual( widgets( await visitor.html( '/sermons/' ) ), blockWidgets );
	} );

	it( 'adds the podcast link and stylesheet to the head of the sermons page, and loads jQuery', async () => {
		const html = await visitor.html( '/sermons/' );
		const head = html.split( '</head>' )[ 0 ];
		assert.match( head, /<link rel="alternate" type="application\/rss\+xml" title="Sermon podcast" href="[^"]*\/sermons\/\?podcast" \/>/ );
		assert.match( head, /<link rel='stylesheet' id='sb_style-css' href='[^']*\/sb-style\.css\?ver=\d+' media='all' \/>/ );
		assert.match( html, /<script id="jquery-core-js"/ );
	} );

	it( 'adds the sermon and its preacher to the page title', async () => {
		const list = title( await visitor.html( '/sermons/' ) );
		assert.equal( title( await visitor.html( '/sermons/?sermon_id=1' ) ), `${ list } (Paul&#039;s letter: Faith &amp; Works - Dylan Jones)` );
	} );

	it( 'says in the page title when the sermon does not exist', async () => {
		const list = title( await visitor.html( '/sermons/' ) );
		assert.equal( title( await visitor.html( '/sermons/?sermon_id=999' ) ), `${ list } (No sermons found.)` );
	} );

	it( 'shows titles and names with script in them as text in the page title', async () => {
		const html = await visitor.html( '/sermons/?sermon_id=4' );
		assert.match( title( html ), / \(&lt;script&gt;alert\(&quot;xss&quot;\)&lt;\/script&gt; - &lt;script&gt;alert\(&quot;xss&quot;\)&lt;\/script&gt;\)$/ );
	} );
} );

describe( 'in a classic theme with title-tag support', () => {
	before( async () => {
		await useTheme( 'sb-classic' );
		await php( "update_option( 'sb_test_title_tag', 1 ); return true;" );
	} );

	it( 'leaves the page title alone on sermon pages (WordPress builds the title without wp_title())', async () => {
		const list = await visitor.html( '/sermons/' );
		const sermon = await visitor.html( '/sermons/?sermon_id=1' );
		assert.equal( title( sermon ), title( list ) );
	} );

	it( 'shows a sermon page as it does in a block theme', async () => {
		matchesSnapshot( sermonBrowser( await visitor.html( '/sermons/?sermon_id=1' ) ), 'sermon-1.html' );
	} );
} );

describe( 'the debug log', () => {
	it( 'logs no PHP errors, warnings or notices', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
