/**
 * Download and redirect links: ?download&file_name=, ?show&file_name=, ?download&url= and ?show&url=.
 *
 * Before 0.46.0, ?download&url= fetched any URL and streamed it back (an open proxy) and
 * ?show&url= redirected anywhere (an open redirect).
 */
import { before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { one, php, seed } from './lib/wp.js';
import { Client } from './lib/client.js';

let fixtures;
const visitor = new Client();

before( async () => {
	fixtures = await seed();
} );

const get = ( query ) => visitor.request( `/sermons/?${ query }`, { redirect: 'manual' } );
const count = async ( name ) => Number( ( await one( `SELECT count FROM {prefix}sb_stuff WHERE name = '${ name }'` ) ).count );
const requestsLogged = async () => php( "return get_option( 'sb_test_http_log', array() );" );

describe( 'local files', () => {
	it( 'downloads a stored file as an attachment', async () => {
		const response = await get( 'download&file_name=2026-03-01-faith-and-works.mp3' );
		assert.equal( response.status, 200 );
		assert.equal( response.headers.get( 'content-type' ), 'application/octet-stream' );
		assert.equal( response.headers.get( 'content-disposition' ), 'attachment; filename="2026-03-01-faith-and-works.mp3"' );
		assert.equal( response.headers.get( 'x-content-type-options' ), 'nosniff' );
		assert.equal( Number( response.headers.get( 'content-length' ) ), 10 + 20 * 417 );
		assert.equal( await count( '2026-03-01-faith-and-works.mp3' ), 1 );
	} );

	it( 'redirects to a stored file', async () => {
		const response = await get( 'show&file_name=2026-03-08-peace-with-god.mp3' );
		assert.equal( response.status, 302 );
		assert.equal( response.headers.get( 'location' ), `${ fixtures.page_url.replace( /sermons\/$/, '' ) }${ fixtures.upload_dir }2026-03-08-peace-with-god.mp3` );
		assert.equal( await count( '2026-03-08-peace-with-god.mp3' ), 1 );
	} );

	it( 'does not count downloads by editors', async () => {
		const editor = await Client.as( fixtures.users.editor );
		await editor.request( '/sermons/?show&file_name=2026-03-08-peace-with-god.mp3', { redirect: 'manual' } );
		assert.equal( await count( '2026-03-08-peace-with-god.mp3' ), 1 );
	} );

	for ( const name of [ 'not-a-sermon.mp3', '../../../wp-config.php', '..%2F..%2F..%2Fwp-config.php', '/etc/passwd', 'unlinked-sermon.mp3%00.php' ] ) {
		it( `returns 404 for ${ name }`, async () => {
			for ( const action of [ 'download', 'show' ] ) {
				const response = await get( `${ action }&file_name=${ encodeURIComponent( name ) }` );
				assert.equal( response.status, 404, action );
				assert.equal( response.headers.get( 'location' ), null );
				assert.doesNotMatch( response.text, /DB_PASSWORD|root:x:0/ );
			}
		} );
	}

	it( 'keeps a hostile stored file name out of the response headers', async () => {
		const response = await get( `download&file_name=${ encodeURIComponent( 'x"><img src=x onerror=alert("file")>.mp3' ) }` );
		const disposition = response.headers.get( 'content-disposition' );
		assert.match( disposition, /^attachment; filename="[^"\\\r\n]*"$/ );
	} );
} );

describe( 'remote URLs', () => {
	const stored = 'https://example.org/sermons/faith-and-works-remote.mp3';

	it( 'redirects to a URL attached to a sermon', async () => {
		const response = await get( `show&url=${ encodeURIComponent( stored ) }` );
		assert.equal( response.status, 302 );
		assert.equal( response.headers.get( 'location' ), stored );
		assert.equal( await count( stored ), 1 );
	} );

	it( 'downloads a URL attached to a sermon as an attachment', async () => {
		const response = await get( `download&url=${ encodeURIComponent( stored ) }` );
		assert.equal( response.status, 200 );
		assert.equal( response.headers.get( 'content-type' ), 'application/octet-stream' );
		assert.equal( response.headers.get( 'content-disposition' ), 'attachment; filename="faith-and-works-remote.mp3"' );
		assert.equal( response.headers.get( 'x-content-type-options' ), 'nosniff' );
		assert.equal( response.text.length > 0, true );
	} );

	it( 'refuses to redirect to a URL that is not attached to a sermon (open redirect)', async () => {
		const response = await get( `show&url=${ encodeURIComponent( 'https://evil.example/phish' ) }` );
		assert.equal( response.status, 404 );
		assert.equal( response.headers.get( 'location' ), null );
	} );

	it( 'refuses to redirect to a stored javascript: URL', async () => {
		const response = await get( `show&url=${ encodeURIComponent( 'javascript:alert("url")' ) }` );
		assert.equal( response.status, 404 );
		assert.equal( response.headers.get( 'location' ), null );
	} );

	it( 'refuses to fetch a URL that is not attached to a sermon (open proxy)', async () => {
		await php( "delete_option( 'sb_test_http_log' ); return true;" );
		for ( const url of [ 'https://example.org/sermons/not-attached.mp3', 'http://169.254.169.254/latest/meta-data/', 'file:///etc/passwd' ] ) {
			const response = await get( `download&url=${ encodeURIComponent( url ) }` );
			assert.equal( response.status, 404, url );
		}
		assert.deepEqual( await requestsLogged(), [], 'no request should leave the server' );
	} );
} );
