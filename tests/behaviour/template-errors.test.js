/**
 * Templates with tags where they can't work: outside their loop, after their loop has
 * ended, or in loops that aren't closed or were never opened. Visitors see the template as
 * if the faulty tag weren't there; those who can edit the templates see an error in its
 * place, linking to the Help page; and the Templates page warns about them when saving.
 *
 * [file], [file_with_download] and [embed] in a sermon but outside their loops aren't
 * faults: they show the sermon's first file or embed, as the readme has always said.
 *
 * The compiled templates (up to 0.48) printed warnings, wrong values or a critical error for these.
 */
import { before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, php, seed, useTemplates } from './lib/wp.js';
import { Client } from './lib/client.js';

const todo = 'until the Templates page checks templates when saving them';
const visitor = new Client();
let admin;
let editor;

before( async () => {
	const fixtures = await seed();
	admin = await Client.as( fixtures.users.admin );
	editor = await Client.as( fixtures.users.editor );
} );

const START = '<div id="template-test">';
const END = '<span id="template-test-end"></span>';
const marked = ( template ) => START + template + END + '</div>';

/**
 * Returns the template's output from a page, between the markers.
 *
 * @param {Response & {text: string}} response
 * @return {string}
 */
function output( response ) {
	assert.equal( response.status, 200 );
	const start = response.text.indexOf( START );
	assert.notEqual( start, -1, 'The template\'s output is not in the page' );
	const end = response.text.indexOf( END, start );
	assert.notEqual( end, -1, 'The template\'s output is not complete' );
	return response.text.slice( start + START.length, end );
}

const render = async ( query = '', client = visitor ) => output( await client.request( `/sermons/${ query ? '?' + query : '' }` ) );

// Audio players are numbered in the order they appear on the page.
const withoutPlayerIds = ( html ) => html.replace( /\b(audio|mejs)-\d+-\d+/g, '$1-N' ).replace( /([?&;]_=)\d+/g, '$1N' );

const ERROR = /<(div|p|span) class="sb-template-error"[^>]*>(.*?)<\/\1>/gs;
const errors = ( html ) => [ ...html.matchAll( ERROR ) ].map( ( match ) => match[ 2 ] );

/**
 * Checks that a template with a fault shows visitors and editors what the template would
 * without the fault, and shows administrators an error for each faulty tag, linked to the Help page.
 *
 * @param {{search?: string, single?: string}} templates The faulty templates.
 * @param {{search?: string, single?: string}} withoutFaults What visitors should see: the same templates without the faulty tags.
 * @param {string} query
 * @param {string[]} faultyTags
 */
async function assertFault( templates, withoutFaults, query, faultyTags ) {
	await useTemplates( Object.fromEntries( Object.entries( withoutFaults ).map( ( [ name, template ] ) => [ name, marked( template ) ] ) ) );
	const expected = await render( query );
	await useTemplates( Object.fromEntries( Object.entries( templates ).map( ( [ name, template ] ) => [ name, marked( template ) ] ) ) );
	assert.equal( await render( query ), expected, 'Visitors see the template without the faulty tags' );
	assert.equal( await render( query, editor ), expected, 'Editors, who can\'t change the templates, see what visitors see' );
	const adminView = await render( query, admin );
	const found = errors( adminView );
	assert.equal( found.length, faultyTags.length, `One error for each faulty tag: ${ found.join( ' | ' ) }` );
	faultyTags.forEach( ( tag, index ) => {
		assert.ok( found[ index ].includes( tag ), `The error names ${ tag }: ${ found[ index ] }` );
		assert.match( found[ index ], /<a href="[^"]*\/wp-admin\/admin\.php\?page=sermon-browser\/help\.php[^"]*">/ );
	} );
}

describe( 'a tag outside its loop', () => {
	it( 'is left out of the sermon list, with an error for administrators', () => assertFault(
		{ search: 'a[sermon_title]b[date]c' },
		{ search: 'abc' },
		'',
		[ '[sermon_title]', '[date]' ]
	) );

	it( 'is left out when it is a file or embed outside the sermons loop', () => assertFault(
		{ search: 'a[file]b[files_loop]f[/files_loop]c[embed_loop]e[/embed_loop]d' },
		{ search: 'abcd' },
		'',
		[ '[file]', '[files_loop]', '[embed_loop]' ]
	) );

	it( 'is left out of a sermon page when it is a passage outside the passages loop', () => assertFault(
		{ single: 'a[passage]b' },
		{ single: 'ab' },
		'sermon_id=3',
		[ '[passage]' ]
	) );
} );

describe( 'a tag after its loop has ended', () => {
	it( 'is left out, rather than showing the loop\'s last sermon', () => assertFault(
		{ search: '[sermons_loop]<i>[sermon_title]</i>[/sermons_loop]a[sermon_title]b' },
		{ search: '[sermons_loop]<i>[sermon_title]</i>[/sermons_loop]ab' },
		'',
		[ '[sermon_title]' ]
	) );

	it( 'is left out when it is a passage after the passages loop', () => assertFault(
		{ single: '[passages_loop][passage][/passages_loop]a[passage]b' },
		{ single: '[passages_loop][passage][/passages_loop]ab' },
		'sermon_id=3',
		[ '[passage]' ]
	) );
} );

describe( 'loops that are not closed, or not opened', () => {
	it( 'leave out a loop that is never closed, and the tags that needed it', () => assertFault(
		{ search: 'x[sermons_loop]<i>[sermon_title]</i>y' },
		{ search: 'x<i></i>y' },
		'',
		[ '[sermons_loop]', '[sermon_title]' ]
	) );

	it( 'leave out a closing tag with no loop to close', () => assertFault(
		{ search: 'x[sermons_loop]a[/sermons_loop][/sermons_loop]y[/files_loop]z' },
		{ search: 'x[sermons_loop]a[/sermons_loop]yz' },
		'',
		[ '[/sermons_loop]', '[/files_loop]' ]
	) );

	it( 'leave out a closing tag on a sermon page', () => assertFault(
		{ single: 'x[/embed_loop]y[/passages_loop]z' },
		{ single: 'xyz' },
		'sermon_id=1',
		[ '[/embed_loop]', '[/passages_loop]' ]
	) );

	it( 'never stop the page with an error', async () => {
		await useTemplates( { search: marked( '[sermons_loop][files_loop][sermon_title]' ), single: marked( '[/embed_loop][/embed_loop][passages_loop]' ) } );
		for ( const query of [ '', 'sermon_id=1' ] ) {
			const response = await visitor.request( `/sermons/${ query ? '?' + query : '' }` );
			assert.equal( response.status, 200 );
			assert.doesNotMatch( response.text, /critical error/ );
		}
	} );
} );

describe( 'a file or embed tag outside its loop, in a sermon', () => {
	/**
	 * Returns the first <p> of the template's output.
	 *
	 * @param {string} html
	 * @return {string}
	 */
	const first = ( html ) => ( html.match( /<p>.*?<\/p>/s ) || [ '' ] )[ 0 ];

	for ( const [ tag, loop ] of [ [ 'file', 'files_loop' ], [ 'file_with_download', 'files_loop' ], [ 'embed', 'embed_loop' ] ] ) {
		it( `shows the sermon's first ${ tag === 'embed' ? 'embed' : 'file' } for [${ tag }], with no error`, async () => {
			await useTemplates( { single: marked( `[${ loop }]<p>[${ tag }]</p>[/${ loop }]` ) } );
			const firstInLoop = withoutPlayerIds( first( await render( 'sermon_id=1' ) ) );
			assert.notEqual( firstInLoop, '', 'Sermon 1 has one' );
			await useTemplates( { single: marked( `<p>[${ tag }]</p>` ) } );
			assert.equal( withoutPlayerIds( await render( 'sermon_id=1' ) ), firstInLoop );
			assert.deepEqual( errors( await render( 'sermon_id=1', admin ) ), [] );
		} );
	}

	it( 'shows the first file, not the last, after the files loop', async () => {
		await useTemplates( { single: marked( '[files_loop]<p>[file]</p>[/files_loop]' ) } );
		const loop = withoutPlayerIds( await render( 'sermon_id=1' ) );
		const files = loop.match( /<p>.*?<\/p>/gs );
		assert.ok( files.length > 1, 'Sermon 1 has more than one file' );
		await useTemplates( { single: marked( '[files_loop]<p>[file]</p>[/files_loop]<p>[file]</p>' ) } );
		assert.equal( withoutPlayerIds( await render( 'sermon_id=1' ) ), loop + files[ 0 ] );
	} );

	it( 'shows each sermon\'s first file in the sermon list', async () => {
		await useTemplates( { search: marked( '[sermons_loop]<li>[files_loop]<p>[file]</p>[/files_loop]</li>[/sermons_loop]' ) } );
		const firsts = [ ...withoutPlayerIds( await render() ).matchAll( /<li>(.*?)<\/li>/gs ) ].map( ( match ) => ( match[ 1 ].match( /<p>.*?<\/p>/s ) || [ '' ] )[ 0 ] );
		assert.ok( firsts.includes( '' ) && firsts.some( ( html ) => html !== '' ), 'Some sermons have files and some don\'t' );
		await useTemplates( { search: marked( '[sermons_loop]<li><p>[file]</p></li>[/sermons_loop]' ) } );
		const lone = [ ...withoutPlayerIds( await render() ).matchAll( /<li>(.*?)<\/li>/gs ) ].map( ( match ) => match[ 1 ] );
		assert.deepEqual( lone, firsts.map( ( html ) => html || '<p></p>' ) );
		assert.deepEqual( errors( await render( '', admin ) ), [] );
	} );

	it( 'shows nothing, with no error, for a sermon without files or embeds', async () => {
		await useTemplates( { single: marked( '[file]|[file_with_download]|[embed]' ) } );
		const without = await php( "global $wpdb; return (int) $wpdb->get_var( \"SELECT s.id FROM {$wpdb->prefix}sb_sermons s LEFT JOIN {$wpdb->prefix}sb_stuff f ON f.sermon_id = s.id WHERE f.id IS NULL ORDER BY s.id LIMIT 1\" );" );
		assert.ok( without, 'The seed has a sermon without files or embeds' );
		assert.equal( await render( `sermon_id=${ without }`, admin ), '||' );
	} );
} );

describe( 'saving templates with faults on the Templates page', () => {
	const save = async ( multi, single ) => {
		const nonce = await admin.nonce( 'templates.php', 'sermon_template_edit_nonce' );
		const style = await php( "return sb_get_option( 'css_style' );" );
		return ( await admin.request( '/wp-admin/admin.php?page=sermon-browser/templates.php', { form: { sermon_template_edit_nonce: nonce, save: 'Save', multi, single, style } } ) ).text;
	};

	it( 'saves them, and warns about each fault', { todo }, async () => {
		const multi = '<div class="sermon-browser">[sermon_title][sermons_loop][date]</div>';
		const single = '<div class="sermon-browser">[/files_loop][passage]</div>';
		const html = await save( multi, single );
		assert.match( html, /Templates saved successfully\./ );
		assert.deepEqual( await php( "return array( sb_get_option( 'search_template' ), sb_get_option( 'single_template' ) );" ), [ multi, single ] );
		const warning = ( html.match( /<div[^>]*class="[^"]*notice-warning[^"]*"[^>]*>([\s\S]*?)<\/div>/ ) || [] )[ 1 ];
		assert.ok( warning, 'A warning is shown' );
		// [date] needs the loop that isn't closed, so it is reported too, as on the page.
		for ( const tag of [ '[sermon_title]', '[sermons_loop]', '[date]', '[/files_loop]', '[passage]' ] ) {
			assert.ok( warning.includes( tag ), `The warning names ${ tag }` );
		}
		assert.match( warning, /href="[^"]*\/wp-admin\/admin\.php\?page=sermon-browser\/help\.php/ );
	} );

	it( 'gives no warning for the default templates, or for a lone [file] or [embed] in a sermon', async () => {
		const defaults = await php( "require_once SB_INCLUDES_DIR . '/sb-install.php'; return array( sb_default_multi_template(), sb_default_single_template() );" );
		assert.doesNotMatch( await save( defaults[ 0 ], defaults[ 1 ] ), /notice-warning/ );
		assert.doesNotMatch( await save( '[sermons_loop][file][embed][/sermons_loop]', '[file][file_with_download][embed]' ), /notice-warning/ );
	} );
} );

describe( 'the debug log', () => {
	it( 'has no PHP errors, warnings or notices from any of these views', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
