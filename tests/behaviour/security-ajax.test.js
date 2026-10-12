/**
 * Admin AJAX requests (POST with sermon=1, handled by ajax.php).
 *
 * Before 0.46.0 they had no authentication: anyone could change or delete
 * preachers, series, services and files.
 */
import { before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { one, query, seed, uploadExists } from './lib/wp.js';
import { Client } from './lib/client.js';

let fixtures;
const clients = {};
const nonces = {};

before( async () => {
	fixtures = await seed();
	clients.anonymous = new Client();
	for ( const role of [ 'subscriber', 'author', 'editor', 'admin' ] ) {
		clients[ role ] = await Client.as( fixtures.users[ role ] );
		// Subscribers can't open the plugin's pages, so they have no nonce of their own.
		nonces[ role ] = role === 'subscriber' ? 'not-a-nonce' : await clients[ role ].ajaxNonce();
	}
	nonces.anonymous = 'not-a-nonce';
} );

/**
 * Asserts that a request was refused with 403 and left the database alone.
 */
function assertRefused( response ) {
	assert.equal( response.status, 403 );
	assert.equal( response.text, '-1' );
}

describe( 'AJAX requests need a valid nonce', () => {
	it( 'refuses an admin with no nonce', async () => {
		assertRefused( await clients.admin.ajax( { ssname: 'No nonce' }, null ) );
		assert.equal( ( await one( "SELECT COUNT(*) n FROM {prefix}sb_series WHERE name = 'No nonce'" ) ).n, '0' );
	} );

	it( 'refuses an admin with a wrong nonce', async () => {
		assertRefused( await clients.admin.ajax( { ssname: 'Wrong nonce' }, 'abc1234567' ) );
		assert.equal( ( await one( "SELECT COUNT(*) n FROM {prefix}sb_series WHERE name = 'Wrong nonce'" ) ).n, '0' );
	} );

	it( "refuses an author using an admin's nonce", async () => {
		assertRefused( await clients.author.ajax( { ssname: 'Borrowed nonce' }, nonces.admin ) );
		assert.equal( ( await one( "SELECT COUNT(*) n FROM {prefix}sb_series WHERE name = 'Borrowed nonce'" ) ).n, '0' );
	} );

	it( 'refuses an anonymous visitor', async () => {
		assertRefused( await clients.anonymous.ajax( { ssname: 'x', ssid: '1', del: '1' }, null ) );
		assert.equal( ( await one( 'SELECT COUNT(*) n FROM {prefix}sb_series WHERE id = 1' ) ).n, '1' );
	} );
} );

describe( 'creating preachers, series and services needs publish_posts', () => {
	for ( const [ role, allowed ] of [ [ 'anonymous', false ], [ 'subscriber', false ], [ 'author', true ], [ 'editor', true ], [ 'admin', true ] ] ) {
		it( `${ allowed ? 'lets' : 'refuses' } ${ role }`, async () => {
			const preacher = await clients[ role ].ajax( { pname: `Preacher by ${ role }` }, nonces[ role ] );
			const series = await clients[ role ].ajax( { ssname: `Series by ${ role }` }, nonces[ role ] );
			const service = await clients[ role ].ajax( { sname: `Service by ${ role } @ 11:00` }, nonces[ role ] );
			const created = await one( `SELECT
				(SELECT COUNT(*) FROM {prefix}sb_preachers WHERE name = 'Preacher by ${ role }') preachers,
				(SELECT COUNT(*) FROM {prefix}sb_series WHERE name = 'Series by ${ role }') series,
				(SELECT COUNT(*) FROM {prefix}sb_services WHERE name = 'Service by ${ role }' AND time = '11:00') services` );
			if ( allowed ) {
				for ( const response of [ preacher, series, service ] ) {
					assert.equal( response.status, 200 );
					assert.match( response.text, /^\d+$/ );
				}
				assert.deepEqual( created, { preachers: '1', series: '1', services: '1' } );
			} else {
				[ preacher, series, service ].forEach( assertRefused );
				assert.deepEqual( created, { preachers: '0', series: '0', services: '0' } );
			}
		} );
	}
} );

describe( 'renaming and deleting preachers, series and services needs manage_categories', () => {
	for ( const [ role, allowed ] of [ [ 'anonymous', false ], [ 'subscriber', false ], [ 'author', false ], [ 'editor', true ], [ 'admin', true ] ] ) {
		it( `${ allowed ? 'lets' : 'refuses' } ${ role }`, async () => {
			await query( `INSERT INTO {prefix}sb_series (name, page_id) VALUES ('Doomed series ${ role }', 0)` );
			const doomed = ( await one( `SELECT id FROM {prefix}sb_series WHERE name = 'Doomed series ${ role }'` ) ).id;
			const responses = [
				await clients[ role ].ajax( { pname: `Renamed by ${ role }`, pid: '1' }, nonces[ role ] ),
				await clients[ role ].ajax( { ssname: `Renamed by ${ role }`, ssid: '1' }, nonces[ role ] ),
				await clients[ role ].ajax( { sname: `Renamed by ${ role } @ 10:30`, sid: '1' }, nonces[ role ] ),
				await clients[ role ].ajax( { ssname: 'x', ssid: doomed, del: '1' }, nonces[ role ] ),
			];
			const state = await one( `SELECT
				(SELECT name FROM {prefix}sb_preachers WHERE id = 1) preacher,
				(SELECT name FROM {prefix}sb_series WHERE id = 1) series,
				(SELECT name FROM {prefix}sb_services WHERE id = 1) service,
				(SELECT COUNT(*) FROM {prefix}sb_series WHERE id = ${ doomed }) doomed` );
			if ( allowed ) {
				responses.forEach( ( response ) => assert.equal( response.status, 200 ) );
				assert.deepEqual( state, { preacher: `Renamed by ${ role }`, series: `Renamed by ${ role }`, service: `Renamed by ${ role }`, doomed: '0' } );
			} else {
				responses.forEach( assertRefused );
				assert.notEqual( state.preacher, `Renamed by ${ role }` );
				assert.notEqual( state.series, `Renamed by ${ role }` );
				assert.notEqual( state.service, `Renamed by ${ role }` );
				assert.equal( state.doomed, '1' );
			}
		} );
	}
} );

describe( 'renaming and deleting files needs upload_files', () => {
	const unlinked = () => one( "SELECT id, name FROM {prefix}sb_stuff WHERE sermon_id = 0 AND type = 'file'" );

	for ( const [ role, allowed ] of [ [ 'anonymous', false ], [ 'subscriber', false ], [ 'author', true ], [ 'editor', true ], [ 'admin', true ] ] ) {
		it( `${ allowed ? 'lets' : 'refuses' } ${ role } rename a file`, async () => {
			const before = ( await unlinked() );
			const response = await clients[ role ].ajax( { fid: before.id, fname: `renamed-by-${ role }.mp3` }, nonces[ role ] );
			const after = ( await unlinked() );
			if ( allowed ) {
				assert.equal( response.text, 'renamed' );
				assert.equal( after.name, `renamed-by-${ role }.mp3` );
				assert.ok( await uploadExists( after.name ) );
				assert.ok( ! ( await uploadExists( before.name ) ) );
			} else {
				assertRefused( response );
				assert.equal( after.name, before.name );
			}
		} );
	}

	it( 'refuses an anonymous visitor deleting a file', async () => {
		const file = ( await unlinked() );
		assertRefused( await clients.anonymous.ajax( { fid: file.id, fname: file.name, del: '1' }, null ) );
		assert.ok( await uploadExists( file.name ) );
	} );

	it( 'refuses a subscriber deleting a file', async () => {
		const file = ( await unlinked() );
		assertRefused( await clients.subscriber.ajax( { fid: file.id, fname: file.name, del: '1' }, nonces.subscriber ) );
		assert.ok( await uploadExists( file.name ) );
	} );

	it( 'lets an author delete a file', async () => {
		const file = ( await unlinked() );
		const response = await clients.author.ajax( { fid: file.id, fname: file.name, del: '1' }, nonces.author );
		assert.equal( response.text, 'deleted' );
		assert.ok( ! ( await uploadExists( file.name ) ) );
		assert.equal( ( await one( `SELECT COUNT(*) n FROM {prefix}sb_stuff WHERE id = ${ file.id }` ) ).n, '0' );
	} );
} );

describe( 'file renames', () => {
	const file = () => one( "SELECT id, name FROM {prefix}sb_stuff WHERE name = '2026-03-08-peace-with-god.mp3'" );

	it( 'refuses a name WordPress would not allow anyone to upload', async () => {
		for ( const name of [ 'peace.php', 'peace.phtml', 'peace.php5', 'peace.phar', '.htaccess', 'peace' ] ) {
			const response = await clients.admin.ajax( { fid: ( await file() ).id, fname: name }, nonces.admin );
			assert.equal( response.text, 'forbidden', name );
		}
		assert.ok( await uploadExists( '2026-03-08-peace-with-god.mp3' ) );
	} );

	it( 'refuses HTML for users without unfiltered_html, as WordPress does', async () => {
		const response = await clients.author.ajax( { fid: ( await file() ).id, fname: 'peace.html' }, nonces.author );
		assert.equal( response.text, 'forbidden' );
		assert.ok( await uploadExists( '2026-03-08-peace-with-god.mp3' ) );
	} );

	it( 'keeps a renamed file in the upload folder', async () => {
		const { id } = await file();
		const response = await clients.admin.ajax( { fid: id, fname: '../../escaped.mp3' }, nonces.admin );
		assert.equal( response.text, 'renamed' );
		assert.equal( ( await one( `SELECT name FROM {prefix}sb_stuff WHERE id = ${ id }` ) ).name, 'escaped.mp3' );
		assert.ok( await uploadExists( 'escaped.mp3' ) );
		await clients.admin.ajax( { fid: id, fname: '2026-03-08-peace-with-god.mp3' }, nonces.admin );
	} );

	it( 'uses the file named by id, ignoring a posted old name', async () => {
		const response = await clients.admin.ajax( { fid: ( await file() ).id, oname: '../../../wp-config.php', fname: 'config-copy.mp3' }, nonces.admin );
		assert.equal( response.text, 'renamed' );
		assert.ok( await uploadExists( 'config-copy.mp3' ) );
		assert.equal( ( await query( "SELECT name FROM {prefix}sb_stuff WHERE name = 'config-copy.mp3'" ) ).length, 1 );
	} );
} );

describe( 'listing sermons and files', () => {
	it( 'refuses a subscriber listing sermons', async () => {
		assertRefused( await clients.subscriber.ajax( { fetch: '1' }, nonces.subscriber ) );
	} );

	it( 'lists sermons for an author, with titles escaped', async () => {
		const response = await clients.author.ajax( { fetch: '1', title: '', preacher: '0', series: '0' }, nonces.author );
		assert.equal( response.status, 200 );
		assert.match( response.text, /Paul(?:&#039;|')s letter: Faith &amp; Works/ );
		assert.match( response.text, /&lt;script&gt;alert\(&quot;xss&quot;\)&lt;\/script&gt;/ );
		assert.doesNotMatch( response.text, /<script>alert\(/ );
	} );

	it( 'treats a quote in the title search as text', async () => {
		const response = await clients.author.ajax( { fetch: '1', title: "Paul's", preacher: '0', series: '0' }, nonces.author );
		assert.match( response.text, /Paul(?:&#039;|')s letter/ );
		assert.doesNotMatch( response.text, /Romans 5/ );
	} );

	it( 'refuses a subscriber searching files', async () => {
		assertRefused( await clients.subscriber.ajax( { search: 'mp3' }, nonces.subscriber ) );
	} );
} );
