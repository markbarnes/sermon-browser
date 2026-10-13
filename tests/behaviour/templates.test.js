/**
 * How the search results and sermon page templates are read: each template tag on its own
 * in the context it belongs to, the text around and between tags, loops, and saving or
 * resetting the templates on the Templates page.
 *
 * The default and customised templates are covered by frontend.test.js and
 * customised-settings.test.js, and the Bible text tags by bible-text.test.js.
 *
 * Snapshots are in tests/snapshots/templates/.
 */
import { before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, php, seed, setOptions, useTemplates } from './lib/wp.js';
import { Client } from './lib/client.js';
import { assertNoLiveScript, element, matchesSnapshot } from './lib/html.js';

const visitor = new Client();
let fixtures;
let editor;

before( async () => {
	fixtures = await seed();
	editor = await Client.as( fixtures.users.editor );
	// [most_popular] uses the popular widget's settings; different play counts give it a fixed order.
	await setOptions( { popular_widget_options: { title: '', limit: 5, display_sermons: true, display_series: true, display_preachers: true } } );
	for ( const [ file, plays ] of [ [ '2026-03-08-peace-with-god.mp3', 2 ], [ '2026-03-01-faith-and-works.mp3', 1 ] ] ) {
		for ( let i = 0; i < plays; i++ ) {
			await visitor.request( `/sermons/?show&file_name=${ file }`, { redirect: 'manual' } );
		}
	}
} );

const START = '<div id="template-test">';
const END = '<span id="template-test-end"></span>';

/**
 * Wraps a template in markers, so its output can be found in the page.
 *
 * @param {string} template
 * @return {string}
 */
const marked = ( template ) => START + template + END + '</div>';

/**
 * Returns the template's output from a page, between the markers.
 *
 * @param {string} html
 * @return {string}
 */
function output( html ) {
	const start = html.indexOf( START );
	assert.notEqual( start, -1, 'The template\'s output is not in the page' );
	const end = html.indexOf( END, start );
	assert.notEqual( end, -1, 'The template\'s output is not complete' );
	return html.slice( start + START.length, end );
}

const render = async ( query = '', client = visitor ) => output( await client.html( `/sermons/${ query ? '?' + query : '' }` ) );
const listItems = ( html ) => [ ...html.matchAll( /<li>(.*?)<\/li>/gs ) ].map( ( match ) => match[ 1 ] );

describe( 'each search results tag on its own', () => {
	const pageTags = [ 'filters_form', 'most_popular', 'tag_cloud', 'sermons_count', 'next_page', 'previous_page', 'podcast', 'podcast_for_search', 'itunes_podcast', 'itunes_podcast_for_search', 'podcasticon', 'podcasticon_for_search' ];
	const sermonTags = [ 'sermon_title', 'preacher_link', 'series_link', 'service_link', 'date', 'first_passage', 'editlink' ];
	const template = '\n<dl>\n'
		+ pageTags.map( ( tag ) => `<dt>${ tag }</dt><dd>[${ tag }]</dd>\n` ).join( '' )
		+ '</dl>\n[sermons_loop]<dl class="sermon">\n'
		+ sermonTags.map( ( tag ) => `<dt>${ tag }</dt><dd>[${ tag }]</dd>\n` ).join( '' )
		+ '<dt>file</dt><dd>[files_loop]<p>[file]</p>[/files_loop]</dd>\n'
		+ '<dt>file_with_download</dt><dd>[files_loop]<p>[file_with_download]</p>[/files_loop]</dd>\n'
		+ '<dt>embed</dt><dd>[embed_loop]<p>[embed]</p>[/embed_loop]</dd>\n'
		+ '</dl>[/sermons_loop]\n';

	before( () => useTemplates( { search: marked( template ) } ) );

	it( 'prints each tag the same as before', async () => {
		const html = await render();
		matchesSnapshot( html, 'templates/search-tags.html' );
		assertNoLiveScript( html, 'the search results tags' );
	} );

	it( 'prints each tag for a filtered list', async () => {
		matchesSnapshot( await render( 'preacher=1&sortby=title&dir=asc' ), 'templates/search-tags-filtered.html' );
	} );

	it( 'links to the next and previous pages when there are more sermons than fit on one', async () => {
		await setOptions( { sermons_per_page: 2 } );
		try {
			matchesSnapshot( await render( 'pagenum=2' ), 'templates/search-tags-page-2.html' );
		} finally {
			await setOptions( { sermons_per_page: 10 } );
		}
	} );

	it( 'shows the edit link only to those who can edit sermons', async () => {
		const editLinks = ( html ) => [ ...html.matchAll( /<dt>editlink<\/dt><dd>(.*?)<\/dd>/g ) ].map( ( match ) => match[ 1 ] );
		assert.deepEqual( editLinks( await render() ), [ '', '', '', '', '' ] );
		const links = editLinks( await render( '', editor ) );
		assert.equal( links.length, 5 );
		assert.match( links[ 0 ], /\/wp-admin\/admin\.php\?page=sermon-browser\/new_sermon\.php&(amp;|#038;)?mid=4/ );
	} );
} );

describe( 'each sermon page tag on its own', () => {
	const tags = [ 'sermon_title', 'sermon_description', 'preacher_link', 'preacher_description', 'preacher_image', 'series_link', 'service_link', 'date', 'next_sermon', 'prev_sermon', 'sameday_sermon', 'tags', 'biblepassage', 'editlink', 'creditlink' ];
	const template = '\n<dl>\n'
		+ tags.map( ( tag ) => `<dt>${ tag }</dt><dd>[${ tag }]</dd>\n` ).join( '' )
		+ '<dt>passage</dt><dd>[passages_loop][passage][/passages_loop]</dd>\n'
		+ '<dt>file</dt><dd>[files_loop]<p>[file]</p>[/files_loop]</dd>\n'
		+ '<dt>file_with_download</dt><dd>[files_loop]<p>[file_with_download]</p>[/files_loop]</dd>\n'
		+ '<dt>embed</dt><dd>[embed_loop]<p>[embed]</p>[/embed_loop]</dd>\n'
		+ '</dl>\n';

	before( () => useTemplates( { single: marked( template ) } ) );

	for ( const id of [ 1, 2, 3, 4, 5 ] ) {
		it( `prints each tag for sermon ${ id } the same as before`, async () => {
			const html = await render( `sermon_id=${ id }` );
			matchesSnapshot( html, `templates/sermon-tags-${ id }.html` );
			assertNoLiveScript( html, `the sermon page tags for sermon ${ id }` );
		} );
	}

	it( 'shows the edit link only to those who can edit sermons', async () => {
		const editLink = ( html ) => html.match( /<dt>editlink<\/dt><dd>(.*?)<\/dd>/ )[ 1 ];
		assert.equal( editLink( await render( 'sermon_id=1' ) ), '' );
		assert.match( editLink( await render( 'sermon_id=1', editor ) ), /\/wp-admin\/admin\.php\?page=sermon-browser\/new_sermon\.php&(amp;|#038;)?mid=1/ );
	} );
} );

describe( 'the text around tags', () => {
	it( 'is printed exactly as written, line endings and whitespace included', async () => {
		await useTemplates( { search: marked( 'a\r\n\t[sermons_count]  b \n<p class="x">c</p>\t' ) } );
		assert.equal( await render(), 'a\r\n\t5  b \n<p class="x">c</p>\t' );
	} );

	it( 'leaves unknown tags, the other template\'s tags and tags in another case as text', async () => {
		await useTemplates( {
			search: marked( '[sermon_description]|[next_sermon]|[Sermon_Title]|[SERMONS_COUNT]|[ sermons_count ]|[sermons_count ]|[unknown]|[sermons_count|sermons_count]|[[sermons_count]]' ),
			single: marked( '[sermons_count]|[filters_form]|[sermons_loop][sermon_title][/sermons_loop]|[first_passage]|[Date]|[[date]]' ),
		} );
		assert.equal( await render(), '[sermon_description]|[next_sermon]|[Sermon_Title]|[SERMONS_COUNT]|[ sermons_count ]|[sermons_count ]|[unknown]|[sermons_count|sermons_count]|[5]' );
		assert.equal( await render( 'sermon_id=1' ), '[sermons_count]|[filters_form]|[sermons_loop]Paul&#039;s letter: Faith &amp; Works[/sermons_loop]|[first_passage]|[Date]|[1 March 2026]' );
	} );

	it( 'shows PHP as text, without running it', async () => {
		await useTemplates( {
			search: marked( '<?php echo strrev( 1234 ); ?>|<?= 1 + 1 ?>|<? echo 2; ?>' ),
			single: marked( '<?php echo strrev( 5678 ); ?>' ),
		} );
		assert.equal( await render(), '&lt;?php echo strrev( 1234 ); ?&gt;|&lt;?= 1 + 1 ?&gt;|&lt;? echo 2; ?&gt;' );
		assert.equal( await render( 'sermon_id=1' ), '&lt;?php echo strrev( 5678 ); ?&gt;' );
	} );

	it( 'prints nothing at all for an empty template', async () => {
		await useTemplates( { search: '', single: '' } );
		assert.equal( await php( "return do_shortcode( '[sermons]' );" ), '' );
		assert.equal( await php( "return do_shortcode( '[sermons id=\"1\"]' );" ), '' );
	} );
} );

describe( 'tags used more than once', () => {
	it( 'prints a tag each time it is used', async () => {
		await useTemplates( {
			search: marked( '[sermons_count]/[sermons_count]' ),
			single: marked( '[date]/[date]' ),
		} );
		assert.equal( await render(), '5/5' );
		assert.equal( await render( 'sermon_id=1' ), '1 March 2026/1 March 2026' );
	} );

	it( 'repeats a loop each time it is used', async () => {
		await useTemplates( {
			search: marked( '<ol>[sermons_loop]<li>[sermon_title]</li>[/sermons_loop]</ol><ol>[sermons_loop]<li>[date]</li>[/sermons_loop]</ol>' ),
			single: marked( '<ol>[files_loop]<li>a</li>[/files_loop]</ol><ol>[files_loop]<li>b</li>[/files_loop]</ol><ol>[embed_loop]<li>c</li>[/embed_loop]</ol><ol>[embed_loop]<li>d</li>[/embed_loop]</ol>' ),
		} );
		const items = listItems( await render() );
		assert.equal( items.length, 10 );
		assert.match( items[ 3 ], /sermon_id=1">Paul&#039;s letter: Faith &amp; Works<\/a>/ );
		assert.deepEqual( items.slice( 5 ), [ '15 March 2026', '8 March 2026', '1 March 2026', '1 March 2026', '25 December 2025' ] );
		assert.equal( await render( 'sermon_id=1' ), '<ol><li>a</li><li>a</li></ol><ol><li>b</li><li>b</li></ol><ol><li>c</li></ol><ol><li>d</li></ol>' );
	} );
} );

describe( 'loops inside the sermons loop', () => {
	it( 'repeat for each of that sermon\'s files and embeds', async () => {
		await useTemplates( { search: marked( '[sermons_loop]<li>[files_loop]f[/files_loop]/[embed_loop]e[/embed_loop]</li>[/sermons_loop]' ) } );
		const counts = await php( `return array_map( function ( $id ) {
			global $wpdb;
			return str_repeat( 'f', (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = %d AND type IN ( 'file', 'url' )", $id ) ) )
				. '/' . str_repeat( 'e', (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = %d AND type = 'code'", $id ) ) );
		}, array( 4, 3, 2, 1, 5 ) );` );
		assert.deepEqual( listItems( await render() ), counts );
		// The seed has sermons with several files, with embeds, and with neither.
		assert.ok( counts.some( ( item ) => /ff/.test( item ) ) && counts.some( ( item ) => /e/.test( item ) ) && counts.includes( '/' ) );
	} );
} );

describe( 'the passages loop', () => {
	it( 'prints the passages together, separated by commas, where the loop ends', async () => {
		await useTemplates( { single: marked( '<p>[passages_loop][passage][/passages_loop]</p>' ) } );
		assert.match( await render( 'sermon_id=3' ), /^<p><a href="[^"]*book=Romans">Romans<\/a> 5:1-11, <a href="[^"]*book=Isaiah">Isaiah<\/a> 53:4-6<\/p>$/ );
	} );

	it( 'prints the rest of the loop\'s text once for each passage, before the passages', async () => {
		await useTemplates( { single: marked( '[passages_loop]<b>x</b>[passage]<i>y</i>[/passages_loop]' ) } );
		assert.match( await render( 'sermon_id=3' ), /^<b>x<\/b><i>y<\/i><b>x<\/b><i>y<\/i><a href="[^"]*book=Romans">Romans<\/a> 5:1-11, <a href="[^"]*book=Isaiah">Isaiah<\/a> 53:4-6$/ );
	} );

	it( 'prints nothing for a sermon without a passage', async () => {
		await useTemplates( { single: marked( '[passages_loop]<b>x</b>[passage][/passages_loop]' ) } );
		const noPassage = await php( "global $wpdb; return (int) $wpdb->get_var( \"SELECT s.id FROM {$wpdb->prefix}sb_sermons s LEFT JOIN {$wpdb->prefix}sb_books_sermons bs ON bs.sermon_id = s.id WHERE bs.id IS NULL ORDER BY s.id LIMIT 1\" );" );
		assert.ok( noPassage, 'The seed has a sermon without a passage' );
		assert.equal( await render( `sermon_id=${ noPassage }` ), '' );
	} );
} );

describe( 'the Templates page', () => {
	let admin;

	before( async () => {
		admin = await Client.as( fixtures.users.admin );
	} );

	const save = async ( fields ) => {
		const nonce = await admin.nonce( 'templates.php', 'sermon_template_edit_nonce' );
		const response = await admin.request( '/wp-admin/admin.php?page=sermon-browser/templates.php', { form: { sermon_template_edit_nonce: nonce, ...fields } } );
		assert.match( response.text, /Templates saved successfully\./ );
	};

	it( 'saves templates that the sermon list and sermon pages then use, quotes included', async () => {
		const style = await php( "return sb_get_option( 'css_style' );" );
		await save( {
			save: 'Save',
			multi: '<div class="sermon-browser saved-list" data-note=\'single\'>[sermons_count] sermons, the "latest" first: [sermons_loop]<span>[sermon_title]</span>[/sermons_loop]<script>alert("saved")</script></div>',
			single: '<div class="sermon-browser saved-single">[sermon_title] \\ [date]</div>',
			style,
		} );
		const list = element( await visitor.html( '/sermons/' ), '<div class="sermon-browser saved-list"' );
		// kses writes attributes with double quotes. Block themes curl the double quotes in the text.
		assert.match( list, /^<div class="sermon-browser saved-list" data-note="single">5 sermons, the &#8220;latest&#8221; first: <span><a href="[^"]*sermon_id=4">/ );
		// The Templates page filters what it saves with wp_kses_post(), even for administrators.
		assert.doesNotMatch( list, /<script>/ );
		assert.match( element( await visitor.html( '/sermons/?sermon_id=1' ), '<div class="sermon-browser saved-single"' ), /^<div class="sermon-browser saved-single">Paul&#039;s letter: Faith &amp; Works \\ 1 March 2026<\/div>$/ );
	} );

	it( 'resets the templates to the defaults', async () => {
		await save( { resetdefault: 'Reset to defaults' } );
		const defaults = await php( "require_once SB_INCLUDES_DIR . '/sb-install.php'; return sb_get_option( 'search_template' ) === sb_default_multi_template() && sb_get_option( 'single_template' ) === sb_default_single_template();" );
		assert.equal( defaults, true );
		matchesSnapshot( element( await visitor.html( '/sermons/?sermon_id=1' ), '<div class="sermon-browser' ), 'sermon-1.html' );
	} );
} );

describe( 'the debug log', () => {
	it( 'has no PHP errors, warnings or notices from any of these views', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
