/**
 * What visitors see with the live site's settings rather than the defaults: the sermon
 * list hides sermons without files or embeds, lists files with download links and has a
 * one-line podcast link; the templates keep the credit link from older versions; the
 * stylesheet has an extra rule; the recent sermons and popular widgets are set up.
 *
 * The templates are built from the defaults with the live site's changes (recorded from
 * its settings in October 2026), and each change is checked to apply, so a change to the
 * defaults can't silently stop this file testing the live templates.
 *
 * Snapshots are in tests/snapshots/live/.
 */
import { before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, defaultTemplates, seed, setOptions, useTemplates } from './lib/wp.js';
import { Client } from './lib/client.js';
import { element, matchesSnapshot } from './lib/html.js';

const visitor = new Client();
let css;

/**
 * Replaces text that must be in the template exactly once.
 *
 * @param {string} template
 * @param {string|RegExp} find
 * @param {string} replacement
 * @return {string}
 */
function change( template, find, replacement ) {
	const matches = template.match( find instanceof RegExp ? new RegExp( find.source, 'g' ) : new RegExp( find.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ), 'g' ) );
	assert.equal( matches && matches.length, 1, `The default template no longer contains ${ find } once` );
	return template.replace( find, replacement );
}

before( async () => {
	await seed();
	const defaults = await defaultTemplates();
	let search = change( defaults.search, /<div class="sb-clear">\s*<h4>Subscribe to Podcast<\/h4>[\s\S]*?<\/table>\s*<\/div>/, '<div class="sb-clear">Subscribe to podcast (<a href="[itunes_podcast]">iTunes</a>, or <a href="[podcast]">other players</a>)</div>\r\n\t<hr/>' );
	search = change( search, '[files_loop][file][/files_loop]', '[files_loop][file_with_download][/files_loop]' );
	search = change( search, /\s*<\/div>\s*$/, '\r\n   \t[creditlink]\r\n</div>' );
	const single = change( defaults.single, /\s*<\/div>\s*$/, '\r\n   \t[creditlink]\r\n</div>' );
	css = defaults.css + '\r\n\r\np.audioplayer_container {\r\n\tdisplay:inline !important;\r\n}';
	await useTemplates( { search, single } );
	await setOptions( {
		css_style: css,
		hide_no_attachments: true,
		filter_type: 'oneclick',
		filter_hide: false,
		sermons_per_page: 10,
		mp3_shortcode: '[audio mp3="%SERMONURL%"]',
		sermons_widget_options: { 2: { limit: '5', preacherz: 0, book: 0, preacher: 0, service: 0, series: 0, title: 'Recent sermons', date: 1, player: 0 } },
		popular_widget_options: { title: '', limit: 5, display_sermons: true, display_series: true, display_preachers: true },
	} );
	// Different play counts, so the popular widget's order doesn't depend on how the database breaks ties.
	for ( const [ file, plays ] of [ [ '2026-03-08-peace-with-god.mp3', 3 ], [ '2026-03-01-faith-and-works.mp3', 2 ] ] ) {
		for ( let i = 0; i < plays; i++ ) {
			await visitor.request( `/sermons/?show&file_name=${ file }`, { redirect: 'manual' } );
		}
	}
} );

const sermonBrowser = async ( query = '' ) => element( await visitor.html( `/sermons/${ query ? '?' + query : '' }` ), '<div class="sermon-browser' );
const listedIds = ( html ) => [ ...element( html, '<table class="sermons">' ).matchAll( /<a href="[^"]*sermon_id=(\d+)">/g ) ].map( ( match ) => Number( match[ 1 ] ) ).filter( ( id, index, ids ) => ids.indexOf( id ) === index );
const sermonCount = ( html ) => Number( html.match( /Sermons \((\d+)\)/ )[ 1 ] );

describe( 'the sermon list with the live settings', () => {
	it( 'lists only sermons with files or embeds', async () => {
		const html = await sermonBrowser();
		matchesSnapshot( html, 'live/list.html' );
		assert.equal( sermonCount( html ), 3 );
		assert.deepEqual( listedIds( html ), [ 4, 3, 1 ] );
	} );

	it( 'gives each file and audio URL a player and a download link', async () => {
		const files = element( ( await sermonBrowser() ).split( 'sermon_id=1"' )[ 1 ], '<td class="files">' );
		assert.equal( [ ...files.matchAll( /<audio class="wp-audio-shortcode"/g ) ].length, 2 );
		assert.match( files, /<source type="audio\/mpeg" src="[^"]*\/sermons\/\?show&#038;file_name=2026-03-01-faith-and-works\.mp3&#038;_=\d+" \/>/ );
		assert.match( files, /<\/audio> <a href="[^"]*\/sermons\/\?download&#038;file_name=2026-03-01-faith-and-works\.mp3">Download<\/a>/ );
		assert.match( files, /<\/audio> <a href="[^"]*\/sermons\/\?download&#038;url=https%3A%2F%2Fexample\.org%2Fsermons%2Ffaith-and-works-remote\.mp3">Download<\/a>/ );
	} );

	it( 'links to the podcast in one line, and to Sermon Browser', async () => {
		const html = await sermonBrowser();
		assert.match( html, /<div class="sb-clear">Subscribe to podcast \(<a href="itpc:\/\/[^"]*\/sermons\/\?podcast">iTunes<\/a>, or <a href="http:\/\/[^"]*\/sermons\/\?podcast">other players<\/a>\)<\/div>/ );
		assert.doesNotMatch( html, /Subscribe to Podcast/ );
		assert.match( html, /<div id="poweredbysermonbrowser">Powered by <a href="http:\/\/www\.sermonbrowser\.com\/">Sermon Browser<\/a><\/div>/ );
	} );

	it( 'hides sermons without attachments from filtered lists too', async () => {
		assert.deepEqual( listedIds( await sermonBrowser( 'preacher=1' ) ), [ 3, 1 ] );
		assert.equal( sermonCount( await sermonBrowser( 'series=2' ) ), 0 );
	} );
} );

describe( 'sermon pages with the live settings', () => {
	it( 'shows a sermon with files and an embed', async () => {
		matchesSnapshot( await sermonBrowser( 'sermon_id=1' ), 'live/sermon-1.html' );
	} );

	it( 'still shows a sermon without attachments when asked for it', async () => {
		matchesSnapshot( await sermonBrowser( 'sermon_id=2' ), 'live/sermon-2.html' );
	} );
} );

describe( 'the podcast feed with the live settings', () => {
	it( 'is the same as with the default settings', async () => {
		// Sermons without audio have no episode either way, so hide_no_attachments changes nothing.
		matchesSnapshot( ( await visitor.request( '/sermons/?podcast' ) ).text, 'podcast.xml' );
	} );
} );

describe( 'widgets with the live settings', () => {
	const widgets = async () => {
		const html = await visitor.html( '/sermon-widgets/' );
		return html.slice( html.indexOf( '<section id="sermon-2"' ), html.lastIndexOf( '</section>' ) + '</section>'.length );
	};

	it( 'shows recent sermons, tags and the most popular sermons in a widget area', async () => {
		matchesSnapshot( await widgets(), 'live/widgets.html' );
	} );

	it( 'lists the five most recent sermons with dates, including those without attachments', async () => {
		const html = element( await widgets(), '<section id="sermon-2"' );
		assert.match( html, /<h2 class="widget-title">Recent sermons<\/h2>/ );
		assert.deepEqual( [ ...html.matchAll( /sermon_id=(\d+)/g ) ].map( ( match ) => Number( match[ 1 ] ) ), [ 4, 3, 2, 1, 5 ] );
		assert.match( html, /sermon_id=3">Romans 5: peace with God<\/a><\/span> <span class="sermon-date"> on 8 March 2026<\/span>\.<\/li>/ );
		assert.doesNotMatch( html, /sermon-preacher|sermon-passage/ );
	} );

	it( 'shows the most played sermons first', async () => {
		const html = element( await widgets(), '<div id="sb_popular_wrapper_w">' );
		assert.deepEqual( [ ...html.matchAll( /sermon_id=(\d+)/g ) ].map( ( match ) => Number( match[ 1 ] ) ), [ 3, 1, 4 ] );
	} );
} );

describe( 'the stylesheet with the live settings', () => {
	it( 'is the stylesheet as saved', async () => {
		assert.equal( ( await visitor.request( '/sb-style.css' ) ).text, css );
	} );
} );

describe( 'the debug log', () => {
	it( 'has no PHP errors, warnings or notices from any of these views', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
