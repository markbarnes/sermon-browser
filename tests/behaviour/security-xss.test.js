/**
 * Cross-site scripting, and PHP in templates.
 *
 * The seed stores script payloads in every field of sermon 4 and its preacher, series,
 * service, tag, file name, URL and embed code, as an attacker could have through the
 * unauthenticated AJAX handler before 0.46.0. Every view must show them as text.
 */
import { before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, php, phpString, seed } from './lib/wp.js';
import { Client } from './lib/client.js';
import { assertNoLiveScript, element } from './lib/html.js';

let fixtures;
let admin;
const visitor = new Client();

before( async () => {
	fixtures = await seed();
	admin = await Client.as( fixtures.users.admin );
} );

const sermonBrowser = ( html ) => element( html, '<div class="sermon-browser' );

describe( 'stored payloads on the front end', () => {
	const views = {
		'the sermon list': '',
		'the hostile preacher': 'preacher=3',
		'the hostile series': 'series=3',
		'the hostile service': 'service=3',
		'the hostile tag': `stag=${ encodeURIComponent( '<img src=x onerror=alert("tag")>' ) }`,
		'a Bible book': 'book=John',
		'sermons sorted by title': 'sortby=m.title&dir=asc',
	};
	for ( const [ name, query ] of Object.entries( views ) ) {
		it( `shows them as text on ${ name }`, async () => {
			const html = await visitor.html( `/sermons/?${ query }` );
			// The check is only meaningful if the hostile sermon is on the page.
			assert.match( html, /sermon_id=4/, `the hostile sermon should be shown on ${ name }` );
			assertNoLiveScript( html, name );
		} );
	}

	it( 'shows them as text on the hostile sermon page, where they are displayed', async () => {
		const html = sermonBrowser( await visitor.html( '/sermons/?sermon_id=4' ) );
		assertNoLiveScript( html, 'the hostile sermon page' );
		assert.match( html, /&lt;script&gt;alert\(&quot;xss&quot;\)&lt;\/script&gt;/, 'the title should be visible as text' );
		assert.match( html, /&lt;img src=x onerror=alert\(&quot;tag&quot;\)&gt;/, 'the tag should be visible as text' );
	} );

	it( 'shows them as text with the drop-down filter', async () => {
		await php( "sb_update_option( 'filter_type', 'dropdown' ); return true;" );
		try {
			assertNoLiveScript( await visitor.html( '/sermons/' ), 'the drop-down filter' );
		} finally {
			await php( "sb_update_option( 'filter_type', 'oneclick' ); return true;" );
		}
	} );

	it( 'keeps genuine embeds working', async () => {
		const html = sermonBrowser( await visitor.html( '/sermons/?sermon_id=1' ) );
		assert.match( html, /<iframe width="560" height="315" src="https:\/\/www\.youtube-nocookie\.com\/embed\/abc123" allowfullscreen><\/iframe>/ );
	} );

	it( 'strips srcdoc, event handlers and scripts from stored embeds', async () => {
		const html = sermonBrowser( await visitor.html( '/sermons/?sermon_id=4' ) );
		assert.match( html, /<iframe src="https:\/\/example\.org\/"><\/iframe>/ );
	} );
} );

describe( 'reflected payloads on the front end', () => {
	const payload = '"><script>alert("xss")</script>';
	for ( const parameter of [ 'title', 'date', 'enddate', 'book', 'stag', 'sortby', 'dir', 'preacher', 'pagenum' ] ) {
		it( `escapes ${ parameter }`, async () => {
			for ( const filter of [ 'oneclick', 'dropdown' ] ) {
				await php( `sb_update_option( 'filter_type', '${ filter }' ); return true;` );
				const html = await visitor.html( `/sermons/?${ parameter }=${ encodeURIComponent( payload ) }` );
				assertNoLiveScript( html, `${ parameter } with the ${ filter } filter` );
			}
			await php( "sb_update_option( 'filter_type', 'oneclick' ); return true;" );
		} );
	}
} );

describe( 'stored payloads in widgets and the feed', () => {
	it( 'shows them as text in the popular sermons widget', async () => {
		const html = await php( "ob_start(); sb_print_most_popular(); return ob_get_clean();" );
		assertNoLiveScript( html, 'the popular sermons widget' );
	} );

	it( 'shows them as text in the sermons widget', async () => {
		const html = await php( "ob_start(); sb_display_sermons( array( 'limit' => 10 ) ); return ob_get_clean();" );
		assertNoLiveScript( html, 'the sermons widget' );
		assert.match( html, /&lt;script&gt;alert/ );
	} );

	it( 'shows them as text in the tag cloud', async () => {
		const html = await php( "ob_start(); sb_print_tag_clouds(); return ob_get_clean();" );
		assertNoLiveScript( html, 'the tag cloud' );
	} );

	it( 'keeps the podcast feed well-formed', async () => {
		const xml = ( await visitor.request( '/sermons/?podcast' ) ).text;
		assert.match( xml, /^<\?xml/ );
		assert.equal( await php( `$d = new DOMDocument(); return @$d->loadXML( ${ phpString( xml ) } ) && $d->getElementsByTagName( 'script' )->length === 0;` ), true );
	} );
} );

describe( 'stored payloads in the admin pages', () => {
	const pages = [
		'sermon.php',
		'new_sermon.php&mid=4',
		'new_sermon.php&getid3=1',
		'files.php',
		'preachers.php',
		'preachers.php&act=edit&pid=3',
		'manage.php',
		'options.php',
		'templates.php',
	];
	for ( const page of pages ) {
		it( `shows them as text on ${ page }`, async () => {
			const html = await admin.adminPage( page );
			assert.doesNotMatch( html, /critical error|You do not have/ );
			assertNoLiveScript( html, page );
		} );
	}

	it( 'shows them as text in the sermons widget settings', async () => {
		const html = await php( "require_once SB_INCLUDES_DIR . '/admin.php'; ob_start(); sb_widget_sermon_control( 1 ); return ob_get_clean();" );
		assertNoLiveScript( html, 'the sermons widget settings' );
	} );

	it( 'shows them as text in AJAX file search results', async () => {
		const response = await admin.ajax( { search: 'mp3' }, await admin.ajaxNonce() );
		assertNoLiveScript( response.text, 'file search results' );
		assert.match( response.text, /sbConfirmDelete/ );
	} );
} );

describe( 'PHP in templates', () => {
	const marker = '<?php echo "ran-" . "template"; ?><?= "ran-" . "short" ?>';

	it( 'is not run when saved on the Templates page', async () => {
		const nonce = await admin.nonce( 'templates.php', 'sermon_template_edit_nonce' );
		const templates = await php( "return array( 'multi' => sb_get_option( 'search_template' ), 'single' => sb_get_option( 'single_template' ), 'style' => sb_get_option( 'css_style' ) );" );
		await admin.request( '/wp-admin/admin.php?page=sermon-browser/templates.php', {
			form: { sermon_template_edit_nonce: nonce, save: 'Save', multi: marker + templates.multi, single: marker + templates.single, style: templates.style },
		} );
		for ( const query of [ '', 'sermon_id=1' ] ) {
			const html = await visitor.html( `/sermons/?${ query }` );
			assert.doesNotMatch( html, /ran-template|ran-short/ );
		}
	} );

	it( 'is not run from a template stored before 0.46.0, once recompiled', async () => {
		await php( `sb_update_option( 'search_template', ${ phpString( marker ) } . sb_get_option( 'search_template' ) ); require_once SB_INCLUDES_DIR . '/upgrade.php'; sb_version_upgrade( '0.45.22', SB_CURRENT_VERSION ); return true;` );
		const html = await visitor.html( '/sermons/' );
		assert.doesNotMatch( html, /ran-template|ran-short/ );
		assert.match( html, /class="sermon-browser/ );
	} );
} );

describe( 'the debug log', () => {
	it( 'has no PHP errors from these requests', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
