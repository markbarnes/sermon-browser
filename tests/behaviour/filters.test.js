/**
 * The sermon list's filters in the cases the seed alone doesn't reach: a filter line
 * long enough to fold the rest behind "more", dates within one year (listed by month),
 * several filters at once, and what the filters add to the page's head (the filtered
 * feed's link, the drop-down filter's date picker).
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

	it( 'loads the date picker for the drop-down filter', async () => {
		await php( "sb_update_option( 'filter_type', 'dropdown' ); return true;" );
		try {
			const html = await page();
			assert.match( html, /<script id="sb_datepicker-js" src="[^"]*\/sb-includes\/datePicker\.js\?ver=[^"]*"><\/script>/ );
			assert.match( html, /<link rel='stylesheet' id='sb_datepicker-css' href='[^']*\/sb-includes\/datepicker\.css\?ver=[^']*' media='all' \/>/ );
		} finally {
			await php( "sb_update_option( 'filter_type', 'oneclick' ); return true;" );
		}
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
