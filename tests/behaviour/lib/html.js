/**
 * Helpers for checking HTML: extracting the plugin's output, snapshots, and live script payloads.
 */
import assert from 'node:assert/strict';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { SITE } from './wp.js';

const snapshotDir = fileURLToPath( new URL( '../../snapshots/', import.meta.url ) );

/**
 * Returns the first element with the given opening tag (e.g. '<div class="sermon-browser">'), including its children.
 *
 * @param {string} html
 * @param {string} openingTag
 * @return {string}
 */
export function element( html, openingTag ) {
	const start = html.indexOf( openingTag );
	assert.notEqual( start, -1, `${ openingTag } not found` );
	const tagName = openingTag.match( /^<(\w+)/ )[ 1 ];
	const tags = new RegExp( `<(/?)${ tagName }\\b[^>]*>`, 'g' );
	tags.lastIndex = start;
	let depth = 0;
	for ( const match of html.matchAll( tags ) ) {
		if ( match.index < start ) {
			continue;
		}
		depth += match[ 1 ] ? -1 : 1;
		if ( depth === 0 ) {
			return html.slice( start, match.index + match[ 0 ].length );
		}
	}
	throw new Error( `${ openingTag } is not closed` );
}

/**
 * Replaces the parts of output that change between runs or sites.
 *
 * @param {string} text
 * @return {string}
 */
export function normalize( text ) {
	return text
		.replaceAll( SITE, '{SITE}' )
		// JSON-encoded URLs, as in the popular widget's inline script.
		.replaceAll( SITE.replaceAll( '/', '\\/' ), '{SITE}' )
		.replaceAll( SITE.replace( /^https?:/, 'itpc:' ), '{SITE_ITPC}' )
		.replace( /(nonce=)[0-9a-f]{10}/g, '$1{NONCE}' )
		// WordPress's audio player ids include the page's post id, which changes when the seed recreates the page.
		.replace( /\b(audio|mejs)-\d+-(\d+)/g, '$1-{POST}-$2' )
		.replace( /(Sermon Browser plugin )[\d.]+/g, '$1{VERSION}' )
		.replace( /\r\n?/g, '\n' );
}

/**
 * Compares text with a stored snapshot in tests/snapshots/. The snapshot is written if it
 * doesn't exist yet, or when UPDATE_SNAPSHOTS=1 is set, so changes to output are reviewed in git.
 *
 * @param {string} text
 * @param {string} name File name in tests/snapshots/
 */
export function matchesSnapshot( text, name ) {
	const file = snapshotDir + name;
	const actual = normalize( text );
	if ( process.env.UPDATE_SNAPSHOTS === '1' || ! existsSync( file ) ) {
		mkdirSync( dirname( file ), { recursive: true } );
		writeFileSync( file, actual );
		return;
	}
	assert.equal( actual, readFileSync( file, 'utf8' ), `Output differs from tests/snapshots/${ name } (run with UPDATE_SNAPSHOTS=1 if the change is intended)` );
}

/**
 * Fails if the HTML contains one of the seed's script payloads as live markup.
 *
 * The checks look for the exact payloads stored by tests/fixtures/seed.php, so escaped
 * copies (as text, in attributes, or JSON-encoded in scripts) don't count, and neither
 * do the admin pages' own onclick and javascript: links.
 *
 * @param {string} html
 * @param {string} where Description for the failure message.
 */
export function assertNoLiveScript( html, where ) {
	const checks = [
		[ /<script>alert\("(?:xss|embed)"\)<\/script>/, 'a <script> element' ],
		[ /<img src=x onerror=alert\("(?:preacher|tag|file)"\)>/, 'an <img onerror> element' ],
		[ /<iframe\b[^>]*\s(?:srcdoc|onload)\s*=/i, 'an iframe with srcdoc or onload' ],
		[ /\s(?:href|src)\s*=\s*["']?\s*javascript:alert\((?:"|&quot;|&#0?34;)url/i, 'a javascript: URL' ],
	];
	for ( const [ pattern, description ] of checks ) {
		const match = html.match( pattern );
		assert.equal( match, null, `${ where } contains ${ description }: ${ match && html.slice( Math.max( 0, match.index - 80 ), match.index + 120 ) }` );
	}
}
