/**
 * The sermon list's filters in the cases the seed alone doesn't reach: a filter line
 * long enough to fold the rest behind "more", dates within one year (listed by month),
 * several filters at once, the drop-down filter's year and how its menus narrow, pinned
 * preachers, and what the filters add to the page's head (the filtered feed's link).
 *
 * Adds seven preachers with a sermon each in April and May 2026, and removes the 2025
 * sermon, so every sermon is in 2026.
 */
import { before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { debugLog, php, query, seed } from './lib/wp.js';
import { Client } from './lib/client.js';
import { element, matchesSnapshot } from './lib/html.js';

const visitor = new Client();

before( async () => {
	await seed();
	await query( 'DELETE FROM {prefix}sb_sermons WHERE id = 5' );
	for ( let id = 4; id <= 10; id++ ) {
		const sermonId = id + 2;
		const date = id <= 7 ? `2026-04-0${ id - 3 } 10:30:00` : `2026-05-0${ id - 7 } 10:30:00`;
		await query( `INSERT INTO {prefix}sb_preachers (id, name, description, image) VALUES (${ id }, 'Preacher ${ id }', '', '')` );
		await query( `INSERT INTO {prefix}sb_sermons (id, title, preacher_id, datetime, service_id, series_id, start, end, description, time, override, page_id) VALUES (${ sermonId }, 'Sermon ${ sermonId }', ${ id }, '${ date }', 1, 1, 'a:0:{}', 'a:0:{}', '', '', 0, 0)` );
	}
} );

const page = ( query = '' ) => visitor.html( `/sermons/${ query ? '?' + query : '' }` );
const sermonBrowser = async ( query = '' ) => element( await page( query ), '<div class="sermon-browser' );
const filters = async ( query = '' ) => element( await sermonBrowser( query ), '<div id="mainfilter">' );

describe( 'the one-click filter', () => {
	it( 'folds a long filter line behind "more"', async () => {
		const html = await sermonBrowser();
		matchesSnapshot( element( html, '<div id="mainfilter">' ), 'filters-oneclick-more.html' );
		const preachers = element( html, '<div id = "preacher"' );
		// The six with most sermons (then the most recent) show; the other four are folded away.
		assert.deepEqual( [ ...preachers.split( '<span id="preacher-more">' )[ 0 ].matchAll( /preacher=(\d+)/g ) ].map( ( match ) => Number( match[ 1 ] ) ), [ 1, 10, 9, 8, 7, 6 ] );
		assert.equal( [ ...element( preachers, '<span id="preacher-more">' ).matchAll( /<a href=/g ) ].length, 4 );
		assert.match( preachers, /<span id="preacher-more-link" style="display:none">&hellip; \(<a  id="preacher-toggle" href="#"><strong>4 more<\/strong><\/a>\)<\/span>/ );
		assert.match( html, /jQuery\('#preacher-more'\)\.hide\(\);\r\t\tjQuery\('#preacher-more-link'\)\.show\(\);/ );
		assert.doesNotMatch( html, /id="series-more"/ );
	} );

	it( 'lists dates by month when every sermon is in the same year', async () => {
		const dates = element( await filters(), '<div id = "dates"' );
		// Months have no leading zero, and every month ends on the 31st.
		assert.match( dates, /date=2026-3-01&#038;enddate=2026-3-31">March<\/a> \(4\), <a href="[^"]*date=2026-4-01&#038;enddate=2026-4-31">April<\/a> \(4\), <a href="[^"]*date=2026-5-01&#038;enddate=2026-5-31">May<\/a> \(3\)<\/div>/ );
	} );

	it( 'shows several active filters, each with a link that removes only that one', async () => {
		const html = await sermonBrowser( 'preacher=1&series=1' );
		matchesSnapshot( element( html, '<div id="mainfilter">' ), 'filters-oneclick-two-active.html' );
		assert.match( html, /<strong>Preacher<\/strong>:&nbsp;Dylan Jones&nbsp;\(<a href="[^"]*\/sermons\/\?series=1">x<\/a>\)\r, <strong>Series<\/strong>:&nbsp;Romans&nbsp;\(<a href="[^"]*\/sermons\/\?preacher=1">x<\/a>\)/ );
	} );

	it( 'shows a date filter as its year', async () => {
		const html = await sermonBrowser( 'date=2026-04-01&enddate=2026-04-31' );
		assert.match( html, /<strong>Date<\/strong>:&nbsp;2026&nbsp;\(<a href="[^"]*\/sermons\/">x<\/a>\)/ );
	} );
} );

describe( 'the drop-down filter', () => {
	const dropdown = async ( callback, hide = true ) => {
		await php( `sb_update_option( 'filter_type', 'dropdown' ); sb_update_option( 'filter_hide', ${ hide ? "'hide'" : 'false' } ); return true;` );
		try {
			await callback();
		} finally {
			await php( "sb_update_option( 'filter_type', 'oneclick' ); sb_update_option( 'filter_hide', 'hide' ); return true;" );
		}
	};

	it( 'is a GET form with a label for every field', () => dropdown( async () => {
		const form = element( await filters(), '<form method="get" id="sermon-filter"' );
		for ( const field of [ 'preacher', 'service', 'book', 'series', 'sermon_year', 'title', 'sortby', 'dir' ] ) {
			const css = field.replace( 'sermon_', '' );
			assert.match( form, new RegExp( `<td class="fieldname[^"]* sb-filter-${ css }"><label for="${ field }">` ) );
			assert.match( form, new RegExp( `<td class="field sb-filter-${ css }"[^>]*>(<select|<input)[^>]* id="${ field }"` ) );
		}
		// WordPress reads a "page" field as its own page number and answers "Page not found".
		assert.doesNotMatch( form, /name="page"/ );
	} ) );

	it( 'shows the filtered list when its form is submitted', () => dropdown( async () => {
		const form = element( await page(), '<form method="get" id="sermon-filter"' );
		const action = form.match( /action="([^"]+)"/ )[ 1 ];
		// Every field the browser sends: inputs as they are, selects with their selected option.
		const fields = new URLSearchParams();
		for ( const [ , attributes ] of form.matchAll( /<input([^>]*)>/g ) ) {
			const name = attributes.match( /name="([^"]+)"/ );
			if ( name ) {
				fields.set( name[ 1 ], attributes.match( /value="([^"]*)"/ )[ 1 ] );
			}
		}
		for ( const [ , name, options ] of form.matchAll( /<select name="([^"]+)"[^>]*>([\s\S]*?)<\/select>/g ) ) {
			const selected = options.match( /<option value="([^"]*)"[^>]*selected/ ) || options.match( /<option value="([^"]*)"/ );
			fields.set( name, selected[ 1 ] );
		}
		fields.set( 'preacher', '4' );

		const response = await visitor.request( `${ action }${ action.includes( '?' ) ? '&' : '?' }${ fields }` );
		assert.equal( response.status, 200 );
		const list = element( response.text, '<div class="sermon-browser' );
		assert.match( list, /Sermon 6/ );
		assert.doesNotMatch( list, /Sermon [7-9]|Sermon 1[0-2]/ );
	} ) );

	it( 'finds sermons from the address its fields make, keeping the choices', () => dropdown( async () => {
		const response = await visitor.request( '/sermons/?preacher=1&service=0&book=&series=0&date=&enddate=&title=&sortby=m.datetime&dir=desc' );
		assert.equal( response.status, 200 );
		assert.match( response.text, /<option value="1" selected="selected">Dylan Jones/ );
	} ) );

	it( 'offers each year with sermons, newest first, instead of date fields', () => dropdown( async () => {
		await query( "INSERT INTO {prefix}sb_sermons (id, title, preacher_id, datetime, service_id, series_id, start, end, description, time, override, page_id) VALUES (90, 'Sermon 90', 2, '2024-06-02 10:30:00', 1, 1, 'a:0:{}', 'a:0:{}', '', '', 0, 0)" );
		try {
			const form = element( await filters(), '<form method="get" id="sermon-filter"' );
			const count = Number( await php( "global $wpdb; return (int) $wpdb->get_var( \"SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons WHERE datetime LIKE '2026%'\" );" ) );
			assert.deepEqual( [ ...element( form, '<select name="sermon_year"' ).matchAll( /<option value="(\d*)"[^>]*>([^<]*)</g ) ].map( ( match ) => [ match[ 1 ], match[ 2 ] ] ), [ [ '', '[All]' ], [ '2026', `2026 (${ count })` ], [ '2024', '2024 (1)' ] ] );
			assert.doesNotMatch( form, /type="date"/ );

			const html = await sermonBrowser( 'preacher=0&sermon_year=2024' );
			assert.match( html, /<option value="2024" selected="selected">/ );
			assert.deepEqual( [ ...html.matchAll( /sermon_id=(\d+)/g ) ].map( ( match ) => Number( match[ 1 ] ) ).filter( ( id, i, ids ) => ids.indexOf( id ) === i ), [ 90 ] );
		} finally {
			await query( 'DELETE FROM {prefix}sb_sermons WHERE id = 90' );
		}
	} ) );

	it( 'narrows each menu to the sermons the other menus choose, keeping its own alternatives', () => dropdown( async () => {
		const options = ( form, name ) => [ ...element( form, `<select name="${ name }"` ).matchAll( /<option value="([^"]*)"[^>]*>([^<]*)</g ) ].map( ( match ) => [ match[ 1 ], match[ 2 ] ] );
		const all = element( await filters(), '<form method="get" id="sermon-filter"' );
		const form = element( await filters( 'preacher=4' ), '<form method="get" id="sermon-filter"' );
		// Preacher 4's one sermon is in 2026, in the first series and service.
		assert.deepEqual( options( form, 'sermon_year' ), [ [ '', '[All]' ], [ '2026', '2026 (1)' ] ] );
		assert.equal( options( form, 'series' ).length, 2 );
		assert.equal( options( form, 'service' ).length, 2 );
		// The preacher menu counts without its own choice, so it still offers every preacher.
		assert.deepEqual( options( form, 'preacher' ), options( all, 'preacher' ) );
		assert.match( form, /<option value="4" selected="selected">Preacher 4 \(1\)/ );
	} ) );

	it( 'keeps a choice that no sermon matches alongside the others, counting 0', () => dropdown( async () => {
		const form = element( await filters( 'preacher=4&sermon_year=2024' ), '<form method="get" id="sermon-filter"' );
		assert.match( form, /<option value="4" selected="selected">Preacher 4 \(0\)/ );
		assert.match( form, /<option value="2024" selected="selected">2024 \(0\)/ );
	} ) );

	it( 'has a "Show filter" link only when the filter starts hidden', async () => {
		await dropdown( async () => assert.match( await sermonBrowser(), /id="show_hide_filter"/ ) );
		await dropdown( async () => assert.doesNotMatch( await sermonBrowser(), /id="show_hide_filter"/ ), false );
	} );
} );

describe( 'a year', () => {
	it( 'lists that year\'s sermons and keeps the year in the list\'s links', async () => {
		const html = await page( 'sermon_year=2026' );
		assert.match( html, /title="Custom sermon podcast" href="[^"]*\/sermons\/\?sermon_year=2026&#038;podcast=1/ );
		assert.doesNotMatch( await sermonBrowser( 'sermon_year=2025' ), /sermon_id=/ );
	} );

	it( 'is ignored unless it is four digits, and dates in the address win', async () => {
		const ids = async ( query ) => [ ...( await sermonBrowser( query ) ).matchAll( /sermon_id=(\d+)/g ) ].map( ( match ) => match[ 1 ] ).join();
		assert.equal( await ids( 'sermon_year=26' ), await ids() );
		assert.match( await sermonBrowser( 'sermon_year=2025&date=2026-01-01&enddate=2026-12-31' ), /sermon_id=/ );
	} );
} );

describe( 'pinned preachers', () => {
	const pinned = async ( ids, callback ) => {
		await php( `sb_update_option( 'pinned_preachers', ${ JSON.stringify( ids ).replace( '[', 'array(' ).replace( ']', ')' ) } ); return true;` );
		try {
			await callback();
		} finally {
			await php( "sb_update_option( 'pinned_preachers', array() ); return true;" );
		}
	};

	it( 'come first in the one-click filter', () => pinned( [ 7 ], async () => {
		const preachers = element( await sermonBrowser(), '<div id = "preacher"' );
		assert.deepEqual( [ ...preachers.split( '<span id="preacher-more">' )[ 0 ].matchAll( /preacher=(\d+)/g ) ].map( ( match ) => Number( match[ 1 ] ) ), [ 7, 1, 10, 9, 8, 6 ] );
	} ) );

	it( 'come first in the drop-down filter, in their usual order', () => pinned( [ 9, 4 ], async () => {
		await php( "sb_update_option( 'filter_type', 'dropdown' ); return true;" );
		try {
			const select = element( await filters(), '<select name="preacher"' );
			assert.deepEqual( [ ...select.matchAll( /<option value="(\d+)"/g ) ].map( ( match ) => Number( match[ 1 ] ) ).slice( 0, 4 ), [ 0, 9, 4, 1 ] );
		} finally {
			await php( "sb_update_option( 'filter_type', 'oneclick' ); return true;" );
		}
	} ) );
} );

describe( 'the page head', () => {
	it( 'links to the podcast and the stylesheet on the sermons page', async () => {
		const html = await page();
		assert.match( html, /<link rel="alternate" type="application\/rss\+xml" title="Sermon podcast" href="[^"]*\/sermons\/\?podcast" \/>/ );
		assert.match( html, /<link rel='stylesheet' id='sb_style-css' href='[^']*\/sb-style\.css\?ver=\d+' media='all' \/>/ );
		assert.match( html, /<script id="jquery-core-js"/ );
		assert.doesNotMatch( html, /Custom sermon podcast/ );
	} );

	it( 'links to the filtered podcast when the list is filtered', async () => {
		const html = await page( 'preacher=1' );
		assert.match( html, /<link rel="alternate" type="application\/rss\+xml" title="Custom sermon podcast" href="[^"]*\/sermons\/\?preacher=1&#038;podcast=1&#038;dir=desc&#038;sortby=m\.datetime" \/>/ );
	} );

	it( 'links to the podcast on every other page too, but adds nothing else', async () => {
		const html = await visitor.html( '/sermon-widgets/?preacher=1' );
		assert.match( html, /title="Sermon podcast"/ );
		assert.doesNotMatch( html, /Custom sermon podcast|sb_datepicker/ );
	} );
} );

describe( 'the debug log', () => {
	it( 'has no PHP errors, warnings or notices from these views', async () => {
		assert.deepEqual( await debugLog(), [] );
	} );
} );
