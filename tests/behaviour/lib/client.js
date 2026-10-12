/**
 * An HTTP client for the test site that keeps a cookie jar, so it can act as a logged-in user.
 */
import { SITE, fetchWithRetry } from './wp.js';

export class Client {
	constructor() {
		this.cookies = new Map();
	}

	/**
	 * Logs in as a user from the seed and returns a client with their cookies.
	 *
	 * @param {{login: string, password: string}} user
	 * @return {Promise<Client>}
	 */
	static async as( user ) {
		const client = new Client();
		await client.request( '/wp-login.php' );
		const response = await client.request( '/wp-login.php', {
			form: { log: user.login, pwd: user.password, 'wp-submit': 'Log In', testcookie: '1' },
			redirect: 'manual',
		} );
		if ( response.status !== 302 ) {
			throw new Error( `Logging in as ${ user.login } failed with status ${ response.status }` );
		}
		return client;
	}

	/**
	 * Makes a request. form sends URL-encoded fields (arrays as repeated fields),
	 * multipart sends a FormData.
	 *
	 * @param {string} path
	 * @param {{method?: string, form?: object, multipart?: FormData, redirect?: string}} options
	 * @return {Promise<Response & {text: string}>}
	 */
	async request( path, { method, form, multipart, redirect = 'follow' } = {} ) {
		const headers = { cookie: [ ...this.cookies ].map( ( [ name, value ] ) => `${ name }=${ value }` ).join( '; ' ) };
		let body;
		if ( form ) {
			const params = new URLSearchParams();
			for ( const [ name, value ] of Object.entries( form ) ) {
				for ( const item of [].concat( value ) ) {
					params.append( name, item );
				}
			}
			body = params;
		} else if ( multipart ) {
			body = multipart;
		}
		const response = await fetchWithRetry( path.startsWith( 'http' ) ? path : SITE + path, {
			method: method || ( body ? 'POST' : 'GET' ),
			headers,
			body,
			redirect,
		} );
		for ( const cookie of response.headers.getSetCookie() ) {
			const [ pair ] = cookie.split( ';' );
			const [ name, ...value ] = pair.split( '=' );
			if ( /expires=Thu, 01[ -]Jan[ -]1970/i.test( cookie ) ) {
				this.cookies.delete( name );
			} else {
				this.cookies.set( name, value.join( '=' ) );
			}
		}
		response.text = await response.text();
		return response;
	}

	/**
	 * Returns the page's HTML.
	 *
	 * @param {string} path
	 * @return {Promise<string>}
	 */
	async html( path ) {
		return ( await this.request( path ) ).text;
	}

	/**
	 * Loads an admin page of the plugin and returns its HTML.
	 *
	 * @param {string} page e.g. 'files.php' or 'new_sermon.php&mid=1'
	 * @return {Promise<string>}
	 */
	async adminPage( page ) {
		return this.html( `/wp-admin/admin.php?page=sermon-browser/${ page }` );
	}

	/**
	 * Returns the value of a hidden nonce field on an admin page.
	 *
	 * @param {string} page
	 * @param {string} field
	 * @return {Promise<string>}
	 */
	async nonce( page, field ) {
		const html = await this.adminPage( page );
		const match = html.match( new RegExp( `name="${ field }" value="([^"]+)"` ) );
		if ( ! match ) {
			throw new Error( `No ${ field } on ${ page }` );
		}
		return match[ 1 ];
	}

	/**
	 * Returns the nonce the admin pages use for AJAX requests.
	 *
	 * @return {Promise<string>}
	 */
	async ajaxNonce() {
		const match = ( await this.adminPage( 'sermon.php' ) ).match( /sbAjaxNonce = "([^"]+)"/ );
		if ( ! match ) {
			throw new Error( 'No sbAjaxNonce on the Sermons page' );
		}
		return match[ 1 ];
	}

	/**
	 * Sends an AJAX request the way the admin pages do (POST with sermon=1).
	 *
	 * @param {object} fields
	 * @param {string|null} nonce
	 * @return {Promise<Response & {text: string}>}
	 */
	async ajax( fields, nonce ) {
		const form = { sermon: '1', ...fields };
		if ( nonce !== null ) {
			form._sbnonce = nonce;
		}
		return this.request( '/wp-admin/admin.php?page=sermon-browser/sermon.php', { form } );
	}
}
