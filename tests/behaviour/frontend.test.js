/**
 * What visitors see: the sermon list, filters, sermon pages, widgets, the podcast feed and
 * the stylesheet.
 *
 * Snapshots in tests/snapshots/ record the plugin's current output, so restructuring that
 * changes it fails until the change is reviewed (npm run test:update-snapshots). The other
 * assertions state the behaviour that must survive any change of output.
 */
import { before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, one, php, phpString, seed, SITE } from './lib/wp.js';
import { Client } from './lib/client.js';
import { element, matchesSnapshot } from './lib/html.js';

const visitor = new Client();

before( async () => {
	await seed();
} );

const sermonBrowser = async ( query = '' ) => element( await visitor.html( `/sermons/${ query ? '?' + query : '' }` ), '<div class="sermon-browser' );
const listedTitles = ( html ) => [ ...html.matchAll( /<a href="[^"]*sermon_id=(\d+)">/g ) ].map( ( match ) => Number( match[ 1 ] ) ).filter( ( id, index, ids ) => ids.indexOf( id ) === index );
const sermonCount = ( html ) => Number( html.match( /Sermons \((\d+)\)/ )[ 1 ] );

describe( 'the sermon list', () => {
	it( 'lists every sermon, newest first', async () => {
		const html = await sermonBrowser();
		assert.equal( sermonCount( html ), 5 );
		assert.deepEqual( listedTitles( element( html, '<table class="sermons">' ) ), [ 4, 3, 2, 1, 5 ] );
		matchesSnapshot( html, 'list.html' );
	} );

	it( 'shows titles, preachers and passages as visitors expect', async () => {
		const html = await sermonBrowser();
		assert.match( html, /Paul&#039;s letter: Faith &amp; Works/ );
		assert.match( html, /Gŵyl Dewi — the Lord’s my shepherd…/ );
		assert.match( html, /Rhiannon Llŷr/ );
		assert.match( html, /Romans<\/a> 3:21-31/ );
		assert.match( html, /Romans<\/a> 5:1-11/ );
	} );

	it( 'renders the drop-down filter', async () => {
		await php( "sb_update_option( 'filter_type', 'dropdown' ); return true;" );
		try {
			const html = await sermonBrowser();
			assert.match( html, /<select name="preacher" id="preacher">/ );
			matchesSnapshot( html, 'list-dropdown-filter.html' );
		} finally {
			await php( "sb_update_option( 'filter_type', 'oneclick' ); return true;" );
		}
	} );

	it( 'pages through sermons', async () => {
		await php( "sb_update_option( 'sermons_per_page', 2 ); return true;" );
		try {
			const first = await sermonBrowser();
			assert.equal( listedTitles( element( first, '<table class="sermons">' ) ).length, 2 );
			assert.match( first, /pagenum=2[^"]*">Next page/ );
			const third = await sermonBrowser( 'pagenum=3' );
			assert.deepEqual( listedTitles( element( third, '<table class="sermons">' ) ), [ 5 ] );
			assert.match( third, /pagenum=2[^"]*">&laquo; Previous page/ );
			assert.doesNotMatch( third, /Next page/ );
		} finally {
			await php( "sb_update_option( 'sermons_per_page', 10 ); return true;" );
		}
	} );
} );

describe( 'filters', () => {
	const filters = {
		'preacher=1': [ 3, 1, 5 ],
		'preacher=2': [ 2 ],
		'series=1': [ 3, 1 ],
		'service=4': [ 5 ],
		'book=Romans': [ 3, 1 ],
		'book=Psalm': [ 2 ],
		'stag=grace': [ 3, 1 ],
		'title=shepherd': [ 2 ],
		'title=Paul%27s': [ 1 ], // stored with the apostrophe escaped: Paul\'s
		'date=2026-03-01&enddate=2026-03-02': [ 2, 1 ],
		'date=2025-01-01&enddate=2025-12-31': [ 5 ],
	};
	for ( const [ query, ids ] of Object.entries( filters ) ) {
		it( `?${ query } lists sermons ${ ids.join( ', ' ) }`, async () => {
			const html = await sermonBrowser( query );
			assert.equal( sermonCount( html ), ids.length );
			assert.deepEqual( listedTitles( element( html, '<table class="sermons">' ) ), ids );
		} );
	}

	it( 'shows the active filter and links to remove it', async () => {
		const html = await sermonBrowser( 'preacher=1' );
		matchesSnapshot( html, 'list-filtered-by-preacher.html' );
		assert.match( html, /Active filter: <strong>Preacher<\/strong>:&nbsp;Dylan Jones&nbsp;\(<a href="[^"]*\/sermons\/">x<\/a>\)/ );
	} );

	it( 'sorts by title', async () => {
		await php( "sb_update_option( 'filter_type', 'dropdown' ); return true;" );
		try {
			const html = await sermonBrowser( 'sortby=m.title&dir=asc' );
			assert.deepEqual( listedTitles( element( html, '<table class="sermons">' ) ), [ 4, 5, 2, 1, 3 ] );
		} finally {
			await php( "sb_update_option( 'filter_type', 'oneclick' ); return true;" );
		}
	} );
} );

describe( 'sermon pages', () => {
	it( 'shows a sermon with its passage, preacher, series, service, tags, files and embed', async () => {
		const html = await sermonBrowser( 'sermon_id=1' );
		matchesSnapshot( html, 'sermon-1.html' );
		assert.match( html, /<h2>Paul&#039;s letter: Faith &amp; Works <span class="scripture">\(<a href="[^"]*book=Romans">Romans<\/a> 3:21-31\)<\/span>/ );
		assert.match( html, /preacher=1">Dylan Jones<\/a>, 1 March 2026/ );
		assert.match( html, /series=1">Romans<\/a> series, preached at a <a href="[^"]*service=1">Sunday Morning<\/a> service/ );
		assert.match( html, /<div class="sermon-description"><p>Justified by grace <em>alone<\/em>, through faith.<\/p>/ );
		assert.match( html, /stag=faith">faith<\/a>, <a href="[^"]*stag=grace">grace<\/a>/ );
		assert.match( html, /\?show&#038;file_name=2026-03-01-faith-and-works\.mp3/ );
		assert.match( html, /download&#038;file_name=2026-03-01-faith-and-works\.mp3">Download<\/a>/ );
		assert.match( html, /\?show&#038;url=https%3A%2F%2Fexample\.org%2Fsermons%2Ffaith-and-works-remote\.mp3/ );
		assert.match( html, /<iframe width="560" height="315" src="https:\/\/www\.youtube-nocookie\.com\/embed\/abc123" allowfullscreen><\/iframe>/ );
		assert.match( html, /About Dylan Jones: <\/span><span class='description'>Pastor of Bethel since 2015\.<\/span>/ );
		assert.match( html, /<img alt='Dylan Jones' class='preacher' src='[^']*\/sermons\/images\/profile\.jpg'>/ );
	} );

	it( 'links to sermons preached earlier, on the same day and later', async () => {
		const html = await sermonBrowser( 'sermon_id=1' );
		assert.match( html, /<td class="earlier"><a href="[^"]*sermon_id=5">&laquo; Christmas morning/ );
		assert.match( html, /<td><a href="[^"]*sermon_id=2">Gŵyl Dewi/ );
		assert.match( html, /<td class="later"><a href="[^"]*sermon_id=3">Romans 5: peace with God &raquo;/ );
	} );

	it( 'shows a sermon with Welsh characters', async () => {
		const html = await sermonBrowser( 'sermon_id=2' );
		matchesSnapshot( html, 'sermon-2.html' );
		assert.match( html, /Gŵyl Dewi — the Lord’s my shepherd…/ );
		assert.match( html, /The Psalms &#8211; a book of mixed emotions/ );
	} );

	it( 'shows a sermon with no Bible passage', async () => {
		const html = await sermonBrowser( 'sermon_id=5' );
		matchesSnapshot( html, 'sermon-5.html' );
		assert.match( html, /<h2>Christmas morning: Unto us a child is born <span class="scripture">\(\)<\/span>/ );
	} );

	it( 'says when a sermon does not exist', async () => {
		const html = await visitor.html( '/sermons/?sermon_id=999' );
		assert.match( html, /No sermons found\./ );
	} );
} );

describe( 'sb_display_sermons(), which the readme tells theme authors to call', () => {
	const display = ( options ) => php( `ob_start(); sb_display_sermons( json_decode( ${ phpString( JSON.stringify( options ) ) }, true ) ); return ob_get_clean();` );
	const ids = ( html ) => [ ...html.matchAll( /sermon_id=(\d+)/g ) ].map( ( match ) => Number( match[ 1 ] ) );

	it( 'lists recent sermons with passage, preacher and date', async () => {
		const html = await display( { limit: 3 } );
		matchesSnapshot( html, 'widget-sermons.html' );
		assert.equal( [ ...html.matchAll( /<li>/g ) ].length, 3 );
	} );

	it( 'leaves out what it is told to, and ignores display_player, which was never implemented', async () => {
		const html = await display( { display_preacher: 0, display_passage: 0, display_date: 0, display_player: 1 } );
		assert.deepEqual( ids( html ), [ 4, 3, 2, 1, 5 ] );
		assert.doesNotMatch( html, /sermon-preacher|sermon-passage|sermon-date|<audio/ );
	} );

	it( 'filters by preacher, series and service', async () => {
		assert.deepEqual( ids( await display( { preacher: 1 } ) ), [ 3, 1, 5 ] );
		assert.deepEqual( ids( await display( { series: 2 } ) ), [ 2, 5 ] );
		assert.deepEqual( ids( await display( { service: 1 } ) ), [ 3, 1 ] );
	} );

	it( 'prints only the URL of the latest sermon with url_only', async () => {
		assert.match( await display( { url_only: 1 } ), /^http:\/\/[^"<>\s]*\/sermons\/\?sermon_id=4$/ );
		assert.match( await display( { url_only: 1, preacher: 2 } ), /\?sermon_id=2$/ );
	} );
} );

describe( 'widgets', () => {
	// The seed puts the widgets on this page, in a widget area of the test site's own.
	const widget = async ( id ) => {
		const html = await visitor.html( '/sermon-widgets/' );
		return html.includes( `<section id="${ id }"` ) ? element( html, `<section id="${ id }"` ) : '';
	};
	const setSermonsWidget = ( options ) => php( `sb_update_option( 'sermons_widget_options', array( 2 => json_decode( ${ phpString( JSON.stringify( { limit: '', preacherz: 0, book: 0, preacher: 0, service: 0, series: 0, title: '', date: 0, player: 0, ...options } ) ) }, true ) ) ); return true;` );

	it( 'has no sermons widget until one is set up', async () => {
		assert.equal( await widget( 'sermon-2' ), '' );
	} );

	it( 'lists recent sermons with the parts chosen in the widget settings', async () => {
		await setSermonsWidget( { limit: '3', preacherz: 1, book: 1, title: 'Latest <sermons>' } );
		const html = await widget( 'sermon-2' );
		matchesSnapshot( html, 'widget-sermons-area.html' );
		assert.match( html, /^<section id="sermon-2" class="widget sermon"><h2 class="widget-title">Latest &lt;sermons&gt;<\/h2><ul class="sermon-widget">/ );
		assert.equal( [ ...html.matchAll( /<li>/g ) ].length, 3 );
		assert.match( html, /sermon_id=3">Romans 5: peace with God<\/a><\/span> <span class="sermon-passage">\(<a href="[^"]*book=Romans">Romans<\/a> 5:1-11\)<\/span> <span class="sermon-preacher">by <a href="[^"]*preacher=1">Dylan Jones<\/a><\/span>\.<\/li>/ );
		assert.doesNotMatch( html, /sermon-date/ );
	} );

	it( 'filters the sermons widget by preacher, series or service', async () => {
		const ids = async () => [ ...( await widget( 'sermon-2' ) ).matchAll( /sermon_id=(\d+)/g ) ].map( ( match ) => Number( match[ 1 ] ) );
		await setSermonsWidget( { limit: '10', preacher: 1 } );
		assert.deepEqual( await ids(), [ 3, 1, 5 ] );
		await setSermonsWidget( { limit: '10', series: 2 } );
		assert.deepEqual( await ids(), [ 2, 5 ] );
		await setSermonsWidget( { limit: '10', service: 4 } );
		assert.deepEqual( await ids(), [ 5 ] );
	} );

	it( 'shows a tag cloud', async () => {
		const html = await widget( 'sermon-browser-tags' );
		assert.match( html, /<h2 class="widget-title">Sermon Browser tags<\/h2>/ );
		assert.match( html, /<a style="font-size:150%" href="[^"]*stag=grace">grace<\/a>/ );
		assert.match( html, /<a style="font-size:80%" href="[^"]*stag=psalms">psalms<\/a>/ );
	} );

	it( 'shows the most popular sermons, series and preachers', async () => {
		await new Client().request( '/sermons/?show&file_name=2026-03-08-peace-with-god.mp3', { redirect: 'manual' } );
		const html = await widget( 'sermon-browser-popular' );
		assert.match( html, /<div id="sb_popular_wrapper_w"><div class="popular-sermons_w"><ul><li><a href="[^"]*sermon_id=3">Romans 5: peace with God<\/a>/ );
		assert.match( html, /popular_series_trigger_w/ );
		assert.match( html, /popular_preachers_trigger_w/ );
		// The seed has a file attached to no sermon; it mustn't show up as an empty entry.
		assert.doesNotMatch( html, /<li><a href=\\?"[^"\\]*\\?"><\/a><\/li>/ );
	} );

	it( 'shows only the parts of the popular widget chosen in its settings', async () => {
		await php( "sb_update_option( 'popular_widget_options', array( 'title' => 'Popular', 'limit' => 1, 'display_sermons' => false, 'display_series' => true, 'display_preachers' => false ) ); return true;" );
		const html = ( await widget( 'sermon-browser-popular' ) ).split( '<script' )[ 0 ];
		assert.match( html, /<h2 class="widget-title">Popular<\/h2><p><a id="popular_series_trigger_w" href="#">Series<\/a><\/p>/ );
		assert.match( html, /<div class="popular-series_w"><ul><li><a href="[^"]*series=1">Romans<\/a><\/li><\/ul><\/div>/ );
		assert.doesNotMatch( html, /popular-sermons_w|popular-preachers_w|popular_sermons_trigger_w|popular_preachers_trigger_w/ );
	} );
} );

describe( 'the podcast feed', () => {
	it( 'lists each audio file and audio URL as an episode, newest first', async () => {
		const response = await visitor.request( '/sermons/?podcast' );
		assert.equal( response.headers.get( 'content-type' ), 'application/rss+xml' );
		matchesSnapshot( response.text, 'podcast.xml' );
		const items = [ ...response.text.matchAll( /<item>[\s\S]*?<\/item>/g ) ].map( ( match ) => match[ 0 ] );
		assert.equal( items.length, 4 );
		assert.match( items[ 0 ], /<title>&lt;script&gt;alert\(&quot;xss&quot;\)&lt;\/script&gt;<\/title>/ );
		assert.match( items[ 1 ], /<title>Romans 5: peace with God<\/title>/ );
		assert.match( items[ 2 ], /<title>Paul&apos;s letter: Faith &amp; Works<\/title>/ );
		assert.match( items[ 3 ], /<enclosure url="[^"]*show&amp;url=https%3A%2F%2Fexample\.org%2Fsermons%2Ffaith-and-works-remote\.mp3"/ );
		assert.match( items[ 2 ], /<enclosure url="[^"]*show&amp;file_name=2026-03-01-faith-and-works\.mp3" length="8350" type="audio\/mpeg" \/>/ );
		assert.match( items[ 2 ], /<description><!\[CDATA\[<p>Justified by grace <em>alone<\/em>, through faith\.<\/p>\]\]><\/description>/ );
	} );

	it( 'gives iTunes direct file links, so plays are not counted twice', async () => {
		const response = await fetch( `${ SITE }/sermons/?podcast`, { headers: { 'User-Agent': 'iTunes/12.0' } } );
		const xml = await response.text();
		assert.match( xml, /<enclosure url="[^"]*\/wp-content\/uploads\/sermons\/2026-03-08-peace-with-god\.mp3"/ );
	} );

	it( 'filters the feed', async () => {
		const xml = ( await visitor.request( '/sermons/?podcast&preacher=2' ) ).text;
		assert.doesNotMatch( xml, /<item>/ );
		const romans = ( await visitor.request( '/sermons/?podcast&book=Romans' ) ).text;
		assert.equal( [ ...romans.matchAll( /<item>/g ) ].length, 3 );
	} );
} );

describe( 'the stylesheet', () => {
	it( 'is served as CSS', async () => {
		const response = await visitor.request( '/sb-style.css' );
		assert.equal( response.status, 200 );
		assert.match( response.headers.get( 'content-type' ), /^text\/css\b/ );
		assert.match( response.text, /\.sermon-browser/ );
	} );

	it( 'answers 304 Not Modified when the browser has the current version', async () => {
		const cached = await fetch( `${ SITE }/sb-style.css`, { headers: { 'If-Modified-Since': 'Wed, 01 Jan 2099 00:00:00 GMT' } } );
		assert.equal( cached.status, 304 );
		const stale = await fetch( `${ SITE }/sb-style.css`, { headers: { 'If-Modified-Since': 'Thu, 01 Jan 2015 00:00:00 GMT' } } );
		assert.equal( stale.status, 200 );
		assert.match( await stale.text(), /\.sermon-browser/ );
	} );
} );

describe( 'download counts', () => {
	it( 'counts a visitor playing a sermon', async () => {
		const before = Number( ( await one( "SELECT count FROM {prefix}sb_stuff WHERE name = '2026-03-01-faith-and-works.mp3'" ) ).count );
		await visitor.request( '/sermons/?show&file_name=2026-03-01-faith-and-works.mp3', { redirect: 'manual' } );
		const after = Number( ( await one( "SELECT count FROM {prefix}sb_stuff WHERE name = '2026-03-01-faith-and-works.mp3'" ) ).count );
		assert.equal( after, before + 1 );
	} );
} );

describe( 'the debug log', () => {
	it( 'has no PHP errors, warnings or notices from any of these views', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
