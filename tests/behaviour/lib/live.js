/**
 * The live site's settings (recorded from its settings in October 2026), for tests that
 * check what its visitors see.
 *
 * The templates and stylesheet are built from the plugin's defaults with the live site's
 * changes, and each change is checked to apply, so a change to the defaults can't silently
 * stop the tests using the live templates.
 */
import assert from 'node:assert/strict';
import { defaultTemplates } from './wp.js';

/**
 * Replaces text that must be in the template exactly once.
 *
 * @param {string} template
 * @param {string|RegExp} find
 * @param {string} replacement
 * @return {string}
 */
function change( template, find, replacement ) {
	const matches = template.match( find instanceof RegExp ? new RegExp( find.source, 'g' ) : new RegExp( find.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ), 'g' ) );
	assert.equal( matches && matches.length, 1, `The default template no longer contains ${ find } once` );
	return template.replace( find, replacement );
}

/**
 * Returns the live site's templates and stylesheet.
 *
 * @return {Promise<{search: string, single: string, css: string}>}
 */
export async function liveTemplates() {
	const defaults = await defaultTemplates();
	let search = change( defaults.search, /<div class="sb-clear">\s*<h4>Subscribe to Podcast<\/h4>[\s\S]*?<\/table>\s*<\/div>/, '<div class="sb-clear">Subscribe to podcast (<a href="[itunes_podcast]">iTunes</a>, or <a href="[podcast]">other players</a>)</div>\r\n\t<hr/>' );
	search = change( search, '[files_loop][file][/files_loop]', '[files_loop][file_with_download][/files_loop]' );
	// Older versions' default templates ended with the credit link.
	search = change( search, /\s*<\/div>\s*$/, '\r\n   \t[creditlink]\r\n</div>' );
	const single = change( defaults.single, /\s*<\/div>\s*$/, '\r\n   \t[creditlink]\r\n</div>' );
	const css = defaults.css + '\r\n\r\np.audioplayer_container {\r\n\tdisplay:inline !important;\r\n}';
	return { search, single, css };
}

/**
 * The live site's options, other than the templates and stylesheet.
 */
export const liveOptions = {
	hide_no_attachments: true,
	filter_type: 'oneclick',
	filter_hide: false,
	sermons_per_page: 10,
	mp3_shortcode: '[audio mp3="%SERMONURL%"]',
	sermons_widget_options: { 2: { limit: '5', preacherz: 0, book: 0, preacher: 0, service: 0, series: 0, title: 'Recent sermons', date: 1, player: 0 } },
	popular_widget_options: { title: '', limit: 5, display_sermons: true, display_series: true, display_preachers: true },
};
