<?php
/*
Plugin Name: Sermon Browser
Plugin URI: http://www.sermonbrowser.com/
Description: Upload sermons to your website, where they can be searched, listened to, and downloaded. Easy to use with comprehensive help and tutorials.
Author: Mark Barnes
Text Domain: sermon-browser
Version: 0.46.0
Author URI: https://www.markbarnes.net/
Requires at least: 5.8
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Copyright (c) 2008-2026 Mark Barnes

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

The structure of this plugin is as follows:
===========================================
MAIN FILES
----------
sermon.php     - This file. Contains common functions and initialisation routines.
admin.php      - Functions required in the admin pages.
frontend.php   - Functions required in the frontend (non-admin) pages.

OTHER FILES
-----------
ajax.php       - Handles AJAX returns.
dictionary.php - Translates the template tags into php code. Used only when saving a template.
filetypes.php  - User-editable file, which returns the correct mime-type for common file-extensions.
podcast.php    - Handles the podcast feed
sb-install.php - Used only when installing the plugin for the first time
style.php      - Outputs the custom stylesheet
uninstall.php  - Removes the plugin and its databases tables and rows
upgrade.php    - Runs only when upgrading from earlier versions of SermonBrowser

If you want to follow the logic of the code, start with sb_sermon_init, and trace the Wordpress actions and filters.
The frontend output is inserted by sb_shortcode

*/
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the sermon list, its filters, feed and downloads are read-only GET requests

if (!defined('ABSPATH'))
	exit;

/**
* Initialisation
*
* Sets version constants and basic Wordpress hooks.
* @package common_functions
*/
define('SB_CURRENT_VERSION', '0.46.0');
define('SB_DATABASE_VERSION', '1.7');
sb_define_constants();
add_action ('init', 'sb_hijack', 0);
add_action ('init', 'sb_sermon_init');
add_action ('widgets_init', 'sb_widget_sermon_init');

/**
* Display podcast, or download linked files
*
* Runs at the start of init (translations can't be loaded earlier). Checks whether the following are required before the page is built:
* Ajax data, stylesheet, file download
*/
function sb_hijack() {

	global $filetypes, $wpdb;

	wp_timezone_override_offset();

	if (isset($_POST['sermon']) && (int) $_POST['sermon'] == 1) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ajax.php checks the nonce
		require(SB_INCLUDES_DIR.'/ajax.php');
	if ((isset($_SERVER['REQUEST_URI']) && stripos(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])), 'sb-style.css') !== FALSE) || isset($_GET['sb-style']))
		require(SB_INCLUDES_DIR.'/style.php');

	if (!isset($_GET['download']) && !isset($_GET['show']))
		return;

	// Local files: only names listed in the database are served, and only from the upload folder
	if (isset($_GET['file_name'])) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared with the names stored in sb_stuff
		$file_name = sb_get_attachment_name(rawurldecode(wp_unslash($_GET['file_name'])), 'file');
		if ($file_name === null || !sb_is_plain_file_name($file_name))
			wp_die(esc_html(rawurldecode(sanitize_text_field(wp_unslash($_GET['file_name'])))).' '.esc_html__('not found', 'sermon-browser'), esc_html__('File not found', 'sermon-browser'), array('response' => 404));
		sb_increase_download_count ($file_name);
		if (isset($_GET['download'])) {
			//Forces sermon download of local file
			$file_path = SB_ABSPATH.sb_get_option('upload_dir').$file_name;
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.sb_header_file_name($file_name).'"');
			header('X-Content-Type-Options: nosniff');
			$filesize = @filesize($file_path);
			if ($filesize)
				header('Content-Length: '.$filesize);
			sb_output_file($file_path);
		} else {
			//Returns local file (doesn't force download)
			wp_redirect(sb_get_option('upload_url').rawurlencode($file_name)); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the upload folder, which may be on another host (a CDN)
		}
		die();
	}

	// External URLs: only URLs attached to a sermon, so the site can't be used as an open redirect or proxy
	if (isset($_GET['url'])) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared with the URLs stored in sb_stuff
		$stored_url = sb_get_attachment_name(rawurldecode(wp_unslash($_GET['url'])), 'url');
		$url = is_null($stored_url) ? '' : esc_url_raw(wp_specialchars_decode($stored_url, ENT_QUOTES), array('http', 'https'));
		if ($url == '')
			wp_die(esc_html(rawurldecode(sanitize_text_field(wp_unslash($_GET['url'])))).' '.esc_html__('not found', 'sermon-browser'), esc_html__('URL not found', 'sermon-browser'), array('response' => 404));
		if (isset($_GET['download'])) {
			//Forces sermon download of external URL
			require_once (ABSPATH.'wp-admin/includes/file.php');
			$downloaded_file = download_url($url);
			if (is_wp_error ($downloaded_file))
				wp_die(esc_html($url).' '.esc_html__('not found', 'sermon-browser'), esc_html__('URL not found', 'sermon-browser'), array('response' => 404));
			sb_increase_download_count($stored_url);
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.sb_header_file_name(basename(wp_parse_url($url, PHP_URL_PATH))).'"');
			header('X-Content-Type-Options: nosniff');
			header('Content-Length: '.filesize($downloaded_file));
			sb_output_file($downloaded_file);
			wp_delete_file($downloaded_file);
		} else {
			//Returns contents of external URL (doesn't force download)
			sb_increase_download_count ($stored_url);
			wp_redirect($url); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- only URLs attached to a sermon (checked above), which are usually on other hosts
		}
		die();
	}
}

/**
* Returns the name of an attachment exactly as stored in the database, or null if there is none
*
* @param string $name
* @param string $type ('file' or 'url')
* @return string|null
*/
function sb_get_attachment_name($name, $type) {
	global $wpdb;
	return $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE name = %s AND type = %s LIMIT 1", $name, $type));
}

/**
* Makes a file name safe to use in a quoted Content-Disposition header
*
* @param string $file_name
* @return string
*/
function sb_header_file_name($file_name) {
	return str_replace(array('"', '\\', "\r", "\n"), '', (string) $file_name);
}

/**
* Main initialisation function
*
* Sets up most Wordpress hooks and filters, depending on whether request is for front or back end.
*/
function sb_sermon_init () {
	global $wpdb, $defaultMultiForm, $defaultSingleForm, $defaultStyle;
	// Translations bundled in sb-includes; installed as a must-use plugin, the plugin's files are directly in mu-plugins
	if (basename(dirname(__FILE__)) == 'mu-plugins')
		load_muplugin_textdomain('sermon-browser', 'sb-includes');
	else
		load_plugin_textdomain('sermon-browser', false, dirname(plugin_basename(__FILE__)).'/sb-includes'); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- the translations bundled in sb-includes

    if(defined('WPLANG')){
		if (WPLANG != '')
			setlocale(LC_ALL, WPLANG.'.UTF-8');
    }

	//Display the podcast if that's what's requested
	if (isset($_GET['podcast']))
		require(SB_INCLUDES_DIR.'/podcast.php');

	// Register custom CSS and javascript files
	wp_register_script('sb_64', SB_PLUGIN_URL.'/sb-includes/64.js', false, SB_CURRENT_VERSION, false);
	wp_register_script('sb_datepicker', SB_PLUGIN_URL.'/sb-includes/datePicker.js', array('jquery'), SB_CURRENT_VERSION, false);
	wp_register_style('sb_datepicker', SB_PLUGIN_URL.'/sb-includes/datepicker.css', false, SB_CURRENT_VERSION);
	if (get_option('permalink_structure') == '') {
		wp_register_style('sb_style', trailingslashit(home_url()).'?sb-style&', false, sb_get_option('style_date_modified'));
	} else {
		wp_register_style('sb_style', trailingslashit(home_url()).'sb-style.css', false, sb_get_option('style_date_modified'));
    }

	// Register [sermon] shortcode handler
	add_shortcode('sermons', 'sb_shortcode');
	add_shortcode('sermon', 'sb_shortcode');

	// Check whether upgrade required
	if (current_user_can('manage_options') && is_admin()) {
		if (get_option('sb_sermon_db_version'))
			$db_version = get_option('sb_sermon_db_version');
		else
			$db_version = sb_get_option('db_version');
		if ($db_version && $db_version != SB_DATABASE_VERSION) {
			require_once (SB_INCLUDES_DIR.'/upgrade.php');
			sb_database_upgrade ($db_version);
		} elseif (!$db_version) {
			require_once(SB_INCLUDES_DIR.'/sb-install.php');
			sb_install();
		}
	}
	// Recompile templates after a code update, on any request, so fixes to the template tags apply straight away
	$sb_version = sb_get_option('code_version');
	if ($sb_version !== FALSE && $sb_version != SB_CURRENT_VERSION) {
		require_once (SB_INCLUDES_DIR.'/upgrade.php');
		sb_version_upgrade ($sb_version, SB_CURRENT_VERSION);
	}

	// Load shared (admin/frontend) features
	add_action ('save_post', 'sb_update_podcast_url');

	// Check to see what functions are required, and only load what is needed
	if (!is_admin()) {
		require_once (SB_INCLUDES_DIR.'/frontend.php');
		add_action('wp_head', 'sb_add_headers', 0);
		add_action('wp_head', 'wp_print_styles', 9);
		add_action('admin_bar_menu', 'sb_admin_bar_menu', 45);
		add_filter('wp_title', 'sb_page_title');
	} else {
		require_once (SB_INCLUDES_DIR.'/admin.php');
		add_action ('admin_menu', 'sb_add_pages');
		add_action ('admin_head', 'sb_print_ajax_nonce');
		add_action ('rightnow_end', 'sb_rightnow');
		add_action('admin_init', 'sb_add_admin_headers');
		add_action('current_screen', 'sb_add_help_tab');
	}
}

/**
* Add Sermons menu and sub-menus in admin
*/
function sb_add_pages() {
	add_menu_page(__('Sermons', 'sermon-browser'), __('Sermons', 'sermon-browser'), 'publish_posts', 'sermon-browser/sermon.php', 'sb_manage_sermons', SB_PLUGIN_URL.'/sb-includes/sb-icon.png');
	add_submenu_page('sermon-browser/sermon.php', __('Sermons', 'sermon-browser'), __('Sermons', 'sermon-browser'), 'publish_posts', 'sermon-browser/sermon.php', 'sb_manage_sermons');
	if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'sermon-browser/new_sermon.php' && isset($_REQUEST['mid'])) {
		add_submenu_page('sermon-browser/sermon.php', __('Edit Sermon', 'sermon-browser'), __('Edit Sermon', 'sermon-browser'), 'publish_posts', 'sermon-browser/new_sermon.php', 'sb_new_sermon');
	} else {
		add_submenu_page('sermon-browser/sermon.php', __('Add Sermon', 'sermon-browser'), __('Add Sermon', 'sermon-browser'), 'publish_posts', 'sermon-browser/new_sermon.php', 'sb_new_sermon');
	}
	add_submenu_page('sermon-browser/sermon.php', __('Files', 'sermon-browser'), __('Files', 'sermon-browser'), 'upload_files', 'sermon-browser/files.php', 'sb_files');
	add_submenu_page('sermon-browser/sermon.php', __('Preachers', 'sermon-browser'), __('Preachers', 'sermon-browser'), 'manage_categories', 'sermon-browser/preachers.php', 'sb_manage_preachers');
	add_submenu_page('sermon-browser/sermon.php', __('Series &amp; Services', 'sermon-browser'), __('Series &amp; Services', 'sermon-browser'), 'manage_categories', 'sermon-browser/manage.php', 'sb_manage_everything');
	add_submenu_page('sermon-browser/sermon.php', __('Options', 'sermon-browser'), __('Options', 'sermon-browser'), 'manage_options', 'sermon-browser/options.php', 'sb_options');
	add_submenu_page('sermon-browser/sermon.php', __('Templates', 'sermon-browser'), __('Templates', 'sermon-browser'), 'manage_options', 'sermon-browser/templates.php', 'sb_templates');
	add_submenu_page('sermon-browser/sermon.php', __('Uninstall', 'sermon-browser'), __('Uninstall', 'sermon-browser'), 'edit_plugins', 'sermon-browser/uninstall.php', 'sb_uninstall');
	add_submenu_page('sermon-browser/sermon.php', __('Help', 'sermon-browser'), __('Help', 'sermon-browser'), 'publish_posts', 'sermon-browser/help.php', 'sb_help');
}

/**
* Converts php.ini mega- or giga-byte numbers into kilobytes
*
* @param string $val
* @return integer
*/
function sb_return_kbytes($val) {
	$val = trim((string) $val);
	if ($val === '')
		return 0;
	$last = strtolower($val[strlen($val)-1]);
	$number = (int) $val;
	switch($last) {
		case 'g':
			$number *= 1024;
		case 'm':
			$number *= 1024;
	}
	return $number;
}

/**
* Count download stats for sermon
*
* Returns the number of plays for a particular file
*
* @param integer $sermonid
* @return integer
*/
function sb_sermon_stats($sermonid) {
	global $wpdb;
	$stats = $wpdb->get_var($wpdb->prepare("SELECT SUM(count) FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = %d", $sermonid));
	if ($stats > 0)
		return $stats;
}

/**
* Updates podcast URL in wp_options
*
* Function required if permalinks changed or [sermons] added to a different page
*/
 function sb_update_podcast_url () {
	global $wp_rewrite;
	$existing_url = sb_get_option('podcast_url');
	if (substr($existing_url, 0, strlen(site_url())) == site_url()) {
		if (sb_display_url(TRUE)=="") {
			sb_update_option('podcast_url', site_url().sb_query_char(false).'podcast');
		} else {
			sb_update_option('podcast_url', sb_display_url().sb_query_char(false).'podcast');
		}
	}
}

/**
* Returns occassionally requested default values
*
* Not defined as constants to save memory
* @param string $default_type
* @return mixed
*/
function sb_get_default ($default_type) {
	switch ($default_type) {
		case 'sermon_path':
			$upload_path = wp_upload_dir();
			$upload_path = $upload_path['basedir'];
			if (substr($upload_path, 0, strlen(ABSPATH)) == ABSPATH)
				$upload_path = substr($upload_path, strlen(ABSPATH));
			return trailingslashit($upload_path).'sermons/';
		case 'attachment_url':
			$upload_dir = wp_upload_dir();
			$upload_dir = $upload_dir['baseurl'];
			return trailingslashit($upload_dir).'sermons/';
		case 'bible_books':
			return array(__('Genesis', 'sermon-browser'), __('Exodus', 'sermon-browser'), __('Leviticus', 'sermon-browser'), __('Numbers', 'sermon-browser'), __('Deuteronomy', 'sermon-browser'), __('Joshua', 'sermon-browser'), __('Judges', 'sermon-browser'), __('Ruth', 'sermon-browser'), __('1 Samuel', 'sermon-browser'), __('2 Samuel', 'sermon-browser'), __('1 Kings', 'sermon-browser'), __('2 Kings', 'sermon-browser'), __('1 Chronicles', 'sermon-browser'), __('2 Chronicles','sermon-browser'), __('Ezra', 'sermon-browser'), __('Nehemiah', 'sermon-browser'), __('Esther', 'sermon-browser'), __('Job', 'sermon-browser'), __('Psalm', 'sermon-browser'), __('Proverbs', 'sermon-browser'), __('Ecclesiastes', 'sermon-browser'), __('Song of Solomon', 'sermon-browser'), __('Isaiah', 'sermon-browser'), __('Jeremiah', 'sermon-browser'), __('Lamentations', 'sermon-browser'), __('Ezekiel', 'sermon-browser'), __('Daniel', 'sermon-browser'), __('Hosea', 'sermon-browser'), __('Joel', 'sermon-browser'), __('Amos', 'sermon-browser'), __('Obadiah', 'sermon-browser'), __('Jonah', 'sermon-browser'), __('Micah', 'sermon-browser'), __('Nahum', 'sermon-browser'), __('Habakkuk', 'sermon-browser'), __('Zephaniah', 'sermon-browser'), __('Haggai', 'sermon-browser'), __('Zechariah', 'sermon-browser'), __('Malachi', 'sermon-browser'), __('Matthew', 'sermon-browser'), __('Mark', 'sermon-browser'), __('Luke', 'sermon-browser'), __('John', 'sermon-browser'), __('Acts', 'sermon-browser'), __('Romans', 'sermon-browser'), __('1 Corinthians', 'sermon-browser'), __('2 Corinthians', 'sermon-browser'), __('Galatians', 'sermon-browser'), __('Ephesians', 'sermon-browser'), __('Philippians', 'sermon-browser'), __('Colossians', 'sermon-browser'), __('1 Thessalonians', 'sermon-browser'), __('2 Thessalonians', 'sermon-browser'), __('1 Timothy', 'sermon-browser'), __('2 Timothy', 'sermon-browser'), __('Titus', 'sermon-browser'), __('Philemon', 'sermon-browser'), __('Hebrews', 'sermon-browser'), __('James', 'sermon-browser'), __('1 Peter', 'sermon-browser'), __('2 Peter', 'sermon-browser'), __('1 John', 'sermon-browser'), __('2 John', 'sermon-browser'), __('3 John', 'sermon-browser'), __('Jude', 'sermon-browser'), __('Revelation', 'sermon-browser'));
		case 'eng_bible_books':
			return array('Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy', 'Joshua', 'Judges', 'Ruth', '1 Samuel', '2 Samuel', '1 Kings', '2 Kings', '1 Chronicles', '2 Chronicles', 'Ezra', 'Nehemiah', 'Esther', 'Job', 'Psalm', 'Proverbs', 'Ecclesiastes', 'Song of Solomon', 'Isaiah', 'Jeremiah', 'Lamentations', 'Ezekiel', 'Daniel', 'Hosea', 'Joel', 'Amos', 'Obadiah', 'Jonah', 'Micah', 'Nahum', 'Habakkuk', 'Zephaniah', 'Haggai', 'Zechariah', 'Malachi', 'Matthew', 'Mark', 'Luke', 'John', 'Acts', 'Romans', '1 Corinthians', '2 Corinthians', 'Galatians', 'Ephesians', 'Philippians', 'Colossians', '1 Thessalonians', '2 Thessalonians', '1 Timothy', '2 Timothy', 'Titus', 'Philemon', 'Hebrews', 'James', '1 Peter', '2 Peter', '1 John', '2 John', '3 John', 'Jude', 'Revelation');
	}
}

/**
* Returns true if sermons are displayed on the current page
*
* @return bool
*/
function sb_display_front_end() {
	global $wpdb, $post;
	$pageid = sb_get_page_id();
	if ($pageid === 0)
		return FALSE;
	else
		return TRUE;
}

/**
* Get the page_id of the main sermons page
*
* @return integer
*/
function sb_get_page_id() {
	global $wpdb, $post;
	$pageid = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE (post_content LIKE '%[sermons]%' OR post_content LIKE '%[sermon]%') AND (post_type = 'page') AND (post_status = 'publish' OR post_status = 'private') ORDER BY post_date ASC LIMIT 1;");
	if (!$pageid)
		$pageid = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE (post_content LIKE '%[sermons]%' OR post_content LIKE '%[sermon]%') AND (post_status = 'publish' OR post_status = 'private') ORDER BY post_date ASC LIMIT 1;");
	if (!$pageid)
		$pageid = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE (post_content LIKE '%[sermon %' OR post_content LIKE '%[sermons %') AND (post_status = 'publish' OR post_status = 'private') ORDER BY post_date ASC LIMIT 1;");
	if (!$pageid)
		return 0;
	else
		return intval($pageid);
}

/**
* Get the URL of the main sermons page
*
* @return string
*/
function sb_display_url() {
	global $wpdb, $post, $sb_display_url;
	if ($sb_display_url == '') {
		$pageid = sb_get_page_id();
		if ($pageid == 0)
			return '';
		if (defined('SB_AJAX') && SB_AJAX)
			return site_url().'/?page_id='.$pageid; // Don't use permalinks in Ajax calls
		else {
			$sb_display_url = get_permalink($pageid);
			if ($sb_display_url == site_url() || $sb_display_url == '') // Hack to force true permalink even if page used for front page.
				$sb_display_url = site_url().'/?page_id='.$pageid;
			}
	}
	return $sb_display_url;
}

/**
* Returns the correct string to join the sermonbrowser parameters to the existing URL
*
* @param boolean $return_entity
* @return string (either '?', '&', or '&amp;')
*/
function sb_query_char ($return_entity = true) {
	if (strpos(sb_display_url(), '?')===FALSE)
		return '?';
	else
		if ($return_entity)
			return '&amp;';
		else
			return '&';
}

/**
* Create the shortcode handler
*
* Standard shortcode handler that inserts the sermonbrowser output into the post/page
*
* @param array $atts
* @param string $content
* @return string
*/
function sb_shortcode($atts, $content=null) {
	global $wpdb, $record_count;
	// Content is also rendered in admin, e.g. when the block editor preloads the page's REST data
	require_once (SB_INCLUDES_DIR.'/frontend.php');
	ob_start();
	$atts = shortcode_atts(array(
		'filter' => sb_get_option('filter_type'),
		'filterhide' => sb_get_option('filter_hide'),
		'id' => isset($_REQUEST['sermon_id']) ? (int)$_REQUEST['sermon_id'] : '',
		'preacher' => isset($_REQUEST['preacher']) ? (int)$_REQUEST['preacher'] : '',
		'series' => isset($_REQUEST['series']) ? (int)$_REQUEST['series'] : '',
		'book' => isset($_REQUEST['book']) ? sanitize_text_field(wp_unslash($_REQUEST['book'])) : '',
		'service' => isset($_REQUEST['service']) ? (int)$_REQUEST['service'] : '',
		'date' => isset($_REQUEST['date']) ? sanitize_text_field(wp_unslash($_REQUEST['date'])) : '',
		'enddate' => isset($_REQUEST['enddate']) ? sanitize_text_field(wp_unslash($_REQUEST['enddate'])) : '',
		'tag' => isset($_REQUEST['stag']) ? sanitize_text_field(wp_unslash($_REQUEST['stag'])) : '',
		'title' => isset($_REQUEST['title']) ? sanitize_text_field(wp_unslash($_REQUEST['title'])) : '',
		'limit' => '0',
		'dir' => isset($_REQUEST['dir']) ? sanitize_text_field(wp_unslash($_REQUEST['dir'])) : '',	),
	$atts);
	if ($atts['id'] != '') {
		if (strtolower($atts['id']) == 'latest') {
			$atts['id'] = '';
			$wpdb->query('SET SQL_BIG_SELECTS=1');
			$query = $wpdb->get_results(sb_create_multi_sermon_query($atts, array(), 1, 1)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- prepared by sb_create_multi_sermon_query()
			$atts['id'] = $query[0]->id;
		}
		$sermon = sb_get_single_sermon((int) $atts['id']);
		if ($sermon)
			eval('?>'.sb_get_option('single_output'));
		else {
			echo "<div class=\"sermon-browser-results\"><span class=\"error\">";
			esc_html_e('No sermons found.', 'sermon-browser');
			echo "</span></div>";
		}
	} else {
		if (isset($_REQUEST['sortby']))
			$sort_criteria = sanitize_text_field(wp_unslash($_REQUEST['sortby']));
		else
			$sort_criteria = 'm.datetime';
		if (!empty($atts['dir']))
			$dir = $atts['dir'];
		elseif ($sort_criteria == 'm.datetime')
			$dir = 'desc';
		else
			$dir = 'asc';
		$sort_order = array('by' => $sort_criteria, 'dir' =>  $dir);
		if (isset($_REQUEST['pagenum']))
			$page = (int)$_REQUEST['pagenum'];
		else
			$page = 1;
		$hide_empty = sb_get_option('hide_no_attachments');
		$sermons = sb_get_sermons($atts, $sort_order, $page, (int)$atts['limit'], $hide_empty);
		$output = '?>'.sb_get_option('search_output');
		eval($output);
	}
	$content = ob_get_contents();
	ob_end_clean();
	return $content;
}

/**
* Registers the Sermon Browser widgets
*/
function sb_widget_sermon_init() {
	//Sermons Widget
	if (!$options = sb_get_option('sermons_widget_options'))
		$options = array();
	$widget_ops = array('classname' => 'sermon', 'description' => __('Display a list of recent sermons.', 'sermon-browser'));
	$control_ops = array('width' => 400, 'height' => 350, 'id_base' => 'sermon');
	$name = __('Sermons', 'sermon-browser');
	$registered = false;
	foreach (array_keys($options) as $o) {
		if (!isset($options[$o]['limit']))
			continue;
		$id = "sermon-$o";
		$registered = true;
		wp_register_sidebar_widget($id, $name, 'sb_widget_sermon_wrapper', $widget_ops, array('number' => $o));
		wp_register_widget_control($id, $name, 'sb_widget_sermon_control', $control_ops, array('number' => $o));
	}
	if (!$registered) {
		wp_register_sidebar_widget('sermon-1', $name, 'sb_widget_sermon_wrapper', $widget_ops, array('number' => -1));
		wp_register_widget_control('sermon-1', $name, 'sb_widget_sermon_control', $control_ops, array('number' => -1));
	}
	//Tags Widget
	wp_register_sidebar_widget('sermon-browser-tags', __('Sermon Browser tags', 'sermon-browser'), 'sb_widget_tag_cloud_wrapper');
	//Most popular widget
	$name = __('Most popular sermons', 'sermon-browser');
	$description = __('Display a list of the most popular sermons, series or preachers.', 'sermon-browser');
	$widget_ops = array('classname' => 'sermon-browser-popular', 'description' => $description);
	$control_ops = array('width' => 400, 'height' => 350, 'id_base' => 'sermon-browser-popular');
	wp_register_sidebar_widget( 'sermon-browser-popular', $name, 'sb_widget_popular_wrapper', $widget_ops);
	wp_register_widget_control( 'sermon-browser-popular', $name, 'sb_widget_popular_control', $control_ops);
}

/**
* Wrapper for sb_widget_sermon in frontend.php
*
* Allows main widget functionality to be in the frontend package, whilst still allowing widgets to be modified in admin
* @param array $args
* @param integer $widget_args
*/
function sb_widget_sermon_wrapper ($args, $widget_args = 1) {
	require_once (SB_INCLUDES_DIR.'/frontend.php');
	sb_widget_sermon($args, $widget_args);
}

/**
* Wrapper for sb_widget_tag_cloud in frontend.php
*
* Allows main widget functionality to be in the frontend package, whilst still allowing widgets to be modified in admin
* @param array $args
*/
function sb_widget_tag_cloud_wrapper ($args) {
	require_once (SB_INCLUDES_DIR.'/frontend.php');
	sb_widget_tag_cloud ($args);
}

/**
* Wrapper for sb_widget_popular in frontend.php
*
* Allows main widget functionality to be in the frontend package, whilst still allowing widgets to be modified in admin
* @param array $args
*/
function sb_widget_popular_wrapper ($args) {
	require_once (SB_INCLUDES_DIR.'/frontend.php');
	sb_widget_popular ($args);
}

/**
* Optimised replacement for get_option
*
* Returns any of the sermonbrowser options from one row of the database
* Large options (e.g. the template) are stored on additional rows by this function
* @param string $type
* @return mixed
*/
function sb_get_option($type) {
	global $sermonbrowser_options;
	$special_options = sb_special_option_names();
	if (in_array($type, $special_options)) {
		return stripslashes(base64_decode(get_option("sermonbrowser_{$type}")));
	} else {
		if (!$sermonbrowser_options) {
			$options = get_option('sermonbrowser_options');
			if ($options === FALSE)
				return FALSE;
			$sermonbrowser_options = sb_unserialize(base64_decode($options));
			if ($sermonbrowser_options === FALSE)
				wp_die (esc_html('Failed to get SermonBrowser options '.base64_decode(get_option('sermonbrowser_options'))));
		}
		if (isset($sermonbrowser_options[$type]))
			return $sermonbrowser_options[$type];
		else
			return '';
	}
}

/**
* Optimised replacement for update_option
*
* Stores all of sermonbrowser options on one row of the database
* Large options (e.g. the template) are stored on additional rows by this function
* @param string $type
* @param mixed $val
* @return bool
*/
function sb_update_option($type, $val) {
	global $sermonbrowser_options;
	$special_options = sb_special_option_names();
	if (in_array($type, $special_options))
		return update_option ("sermonbrowser_{$type}", base64_encode($val));
	else {
		if (!$sermonbrowser_options) {
			$options = get_option('sermonbrowser_options');
			if ($options !== FALSE) {
				$sermonbrowser_options = sb_unserialize(base64_decode($options));
				if ($sermonbrowser_options === FALSE)
					wp_die (esc_html('Failed to get SermonBrowser options '.base64_decode(get_option('sermonbrowser_options'))));
			}
		}
		if (!isset($sermonbrowser_options[$type]) || $sermonbrowser_options[$type] !== $val) {
			$sermonbrowser_options[$type] = $val;
			return update_option('sermonbrowser_options', base64_encode(serialize($sermonbrowser_options)));
		} else
			return false;
	}
}

/**
* Returns which options need to be stored in individual base64 format (i.e. potentially large strings)
*
* @return array
*/
function sb_special_option_names() {
	return array ('single_template', 'single_output', 'search_template', 'search_output', 'css_style');
}

/**
* Recursive mkdir function
*
* @param string $pathname
* @param string $mode
* return bool
*/
function sb_mkdir($pathname) {
	$created = wp_mkdir_p($pathname);
	sb_protect_upload_dir(trailingslashit($pathname));
	return $created;
}

/**
* Returns true if $dir is a folder inside the WordPress folder that sermons may be uploaded to
*
* The folder must be relative to ABSPATH, must not climb out of it, and must not be part of WordPress itself.
* @param string $dir (with trailing slash)
* @return bool
*/
function sb_is_valid_upload_dir($dir) {
	if ($dir == '/' || $dir[0] == '/' || strpos($dir, ':') !== false || strpos($dir, '\\') !== false || validate_file($dir) !== 0)
		return false;
	foreach (array('wp-admin/', 'wp-includes/', 'wp-content/plugins/', 'wp-content/themes/', 'wp-content/mu-plugins/') as $forbidden)
		if (stripos($dir, $forbidden) === 0)
			return false;
	return true;
}

/**
* Stops web servers running scripts from the upload folder
*
* Writes an .htaccess file (Apache) that refuses requests for script files.
* Other web servers need equivalent rules in their own configuration.
* @param string $dir (full path with trailing slash; defaults to the upload folder)
*/
function sb_protect_upload_dir($dir = '') {
	if ($dir == '')
		$dir = SB_ABSPATH.sb_get_option('upload_dir');
	if (!is_dir($dir) || file_exists($dir.'.htaccess'))
		return;
	$rules = "# Added by Sermon Browser: never run scripts from the sermon upload folder\n";
	$rules .= "<FilesMatch \"\\.(?i:php[0-9]?|phtml|phar|pht|shtml|cgi|pl|py|sh)$\">\n\tRequire all denied\n</FilesMatch>\n";
	@file_put_contents($dir.'.htaccess', $rules); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, PluginCheck.CodeAnalysis.WriteFile.ABSPATHDetected -- the upload folder, written directly like the uploads in it
}

/**
* Defines a number of constants used throughout the plugin
*/
function sb_define_constants() {
	$directories = explode(DIRECTORY_SEPARATOR,dirname(__FILE__));
	if ($plugin_dir = $directories[count($directories)-1] == 'mu-plugins' || is_multisite()) {
		define('IS_MU', TRUE);
	} else {
		define('IS_MU', FALSE);
	}
	if ($directories[count($directories)-1] == 'mu-plugins' )
		define ('SB_PLUGIN_URL', content_url().'/'.$plugin_dir);
	else
		define ('SB_PLUGIN_URL', rtrim(content_url().'/plugins/'.plugin_basename(dirname(__FILE__)), '/'));
	define ('SB_PLUGIN_DIR', sb_sanitise_path(defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH.'wp-content').'/plugins');
	define ('SB_WP_CONTENT_DIR', sb_sanitise_path(WP_CONTENT_DIR));
	define ('SB_INCLUDES_DIR', SB_PLUGIN_DIR.'/sermon-browser/sb-includes');
	define ('SB_ABSPATH', sb_sanitise_path(ABSPATH));
}

/**
* Returns list of bible books from the database
*
* @return array
*/
function sb_get_bible_books () {
	global $wpdb;
	return $wpdb->get_col("SELECT name FROM {$wpdb->prefix}sb_books order by id");
}

/**
* Get multiple sermons from the database
*
* Uses sb_create_multi_sermon_query to general the SQL statement
* @param array $filter
* @param string $order
* @param integer $page
* @param integer $limit
* @global integer record_count
* @return array
*/
function sb_get_sermons($filter, $order, $page = 1, $limit = 0, $hide_empty = false) {
	global $wpdb, $record_count;
	if ($limit == 0)
		$limit = sb_get_option('sermons_per_page');
	$wpdb->query('SET SQL_BIG_SELECTS=1');
	$query = $wpdb->get_results(sb_create_multi_sermon_query($filter, $order, $page, $limit, $hide_empty)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- prepared by sb_create_multi_sermon_query()
	$record_count = $wpdb->get_var("SELECT FOUND_ROWS()");
	return $query;
}

/**
* Create SQL query for returning multiple sermons
*
* @param array $filter
* @param string $order
* @param integer $page
* @param integer $limit
* @return string SQL query
*/
function sb_create_multi_sermon_query ($filter, $order, $page = 1, $limit = 0, $hide_empty = false) {
	global $wpdb;
	$default_filter = array(
		'title' => '',
		'preacher' => 0,
		'date' => '',
		'enddate' => '',
		'series' => 0,
		'service' => 0,
		'book' => '',
		'tag' => '',
		'id' => '',
	);
	$default_order = array(
		'by' => 'm.datetime',
		'dir' => 'desc',
	);
	$bs = '';
	$filter = array_merge($default_filter, (array)$filter);
	$order = array_merge($default_order, (array)$order);
	if ( strtolower($order['dir']) != 'desc' and strtolower($order['dir']) != 'asc' )
		$order['dir'] = $default_order['dir'];
	$valid_sortby_values = array( 'm.id', 'm.title', 'm.datetime', 'm.start', 'm.end', 'p.id', 'p.name', 's.id', 's.name', 'ss.id', 'ss.name');
	if ( !in_array($order['by'], $valid_sortby_values) )
		$order['by'] = $default_order['by'];
	$page = (int) $page;
	$cond = '1=1 ';
	// Text saved by the Add Sermon page is stored with quotes escaped (God\'s), so searches match both forms
	if ($filter['title'] != '') {
		$like = '%'.$wpdb->esc_like($filter['title']).'%';
		$slashed = '%'.$wpdb->esc_like(addslashes($filter['title'])).'%';
		$cond .= $wpdb->prepare('AND (m.title LIKE %s OR m.title LIKE %s OR m.description LIKE %s OR m.description LIKE %s OR t.name LIKE %s) ', $like, $slashed, $like, $slashed, $like);
	}
	if ((int) $filter['preacher'] != 0) {
		$cond .= $wpdb->prepare('AND m.preacher_id = %d ', $filter['preacher']);
	}
	if ($filter['date'] != '') {
		$cond .= $wpdb->prepare('AND m.datetime >= %s ', $filter['date']);
	}
	if ($filter['enddate'] != '') {
		$cond .= $wpdb->prepare('AND m.datetime <= %s ', $filter['enddate']);
	}
	if ((int) $filter['series'] != 0) {
		$cond .= $wpdb->prepare('AND m.series_id = %d ', $filter['series']);
	}
	if ((int) $filter['service'] != 0) {
		$cond .= $wpdb->prepare('AND m.service_id = %d ', $filter['service']);
	}
	if ($filter['book'] != '') {
		$cond .= $wpdb->prepare('AND bs.book_name = %s ', $filter['book']);
	} else {
		$bs = "AND bs.order = 0 AND bs.type= 'start' ";
	}
	if ($filter['tag'] != '') {
		$cond .= $wpdb->prepare('AND t.name LIKE %s ', '%'.$wpdb->esc_like($filter['tag']).'%');
	}
	if ($filter['id'] != '') {
		$cond .= $wpdb->prepare('AND m.id = %d ', $filter['id']);
	}
	if ($hide_empty) {
		$cond .= "AND stuff.name != '' ";
	}
	$limit = max(1, (int) $limit);
	$offset = $limit * max(0, $page - 1);
	// $cond is built from prepared fragments; the ORDER BY column and direction are checked against lists above
	return "SELECT SQL_CALC_FOUND_ROWS DISTINCT m.id, m.title, m.description, m.datetime, m.time, m.start, m.end, p.id as pid, p.name as preacher, p.description as preacher_description, p.image, s.id as sid, s.name as service, ss.id as ssid, ss.name as series
		FROM {$wpdb->prefix}sb_sermons as m
		LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id
		LEFT JOIN {$wpdb->prefix}sb_services as s ON m.service_id = s.id
		LEFT JOIN {$wpdb->prefix}sb_series as ss ON m.series_id = ss.id
		LEFT JOIN {$wpdb->prefix}sb_books_sermons as bs ON bs.sermon_id = m.id {$bs}
		LEFT JOIN {$wpdb->prefix}sb_books as b ON bs.book_name = b.name
		LEFT JOIN {$wpdb->prefix}sb_sermons_tags as st ON st.sermon_id = m.id
		LEFT JOIN {$wpdb->prefix}sb_tags as t ON t.id = st.tag_id
		LEFT JOIN {$wpdb->prefix}sb_stuff as stuff ON stuff.sermon_id = m.id
		WHERE {$cond} ORDER BY {$order['by']} {$order['dir']} LIMIT {$offset}, {$limit}";
}

/**
* Returns the default time for a particular service
*
* @param integer $service (id in database)
* @return string (service time)
*/
function sb_default_time($service) {
	global $wpdb;
	$sermon_time = $wpdb->get_var($wpdb->prepare("SELECT time FROM {$wpdb->prefix}sb_services WHERE id = %d", $service));
	if (isset($sermon_time)) {
		return $sermon_time;
	} else {
		return "00:00";
	}
}

/**
* Gets attachments from database
*
* @param integer $sermon (id in database)
* @param boolean $mp3_only (if true will only return MP3 files)
* @return array
*/
function sb_get_stuff($sermon, $mp3_only = FALSE) {
	global $wpdb;
	$sermon_id = (int) $sermon->id;
	if ($mp3_only) {
		$stuff = $wpdb->get_results($wpdb->prepare("SELECT f.type, f.name FROM {$wpdb->prefix}sb_stuff as f WHERE sermon_id = %d AND name LIKE %s ORDER BY id desc", $sermon_id, '%'.$wpdb->esc_like('.mp3')));
	} else {
		$stuff = $wpdb->get_results($wpdb->prepare("SELECT f.type, f.name FROM {$wpdb->prefix}sb_stuff as f WHERE sermon_id = %d ORDER BY id desc", $sermon_id));
	}
	$file = $url = $code = array();
	foreach ($stuff as $cur)
		${$cur->type}[] = $cur->name;
	return array(
		'Files' => $file,
		'URLs' => $url,
		'Code' => $code,
	);
}

/**
* Increases the download count for file attachments
*
* Increases the download count for the file $stuff_name
*
* @param string $stuff_name
*/
function sb_increase_download_count ($stuff_name) {
	if (!(current_user_can('edit_posts') || current_user_can('publish_posts'))) {
		global $wpdb;
		$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_stuff SET COUNT=COUNT+1 WHERE name = %s", $stuff_name));
	}
}

/**
* Outputs a remote or local file
*
* @param string $filename
* @return bool success or failure
*/
function sb_output_file($filename) {
	if (!is_readable($filename))
		return false;
	// Streamed in chunks, since sermon recordings can be larger than PHP's memory limit; WP_Filesystem can only read whole files
	$handle = fopen($filename, 'rb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ($handle === false)
		return false;
	if (ob_get_level() == 0)
		ob_start();
	while (!feof($handle)) {
		set_time_limit(ini_get('max_execution_time')); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- restarts the time limit for each chunk of a long download
		$buffer = fread($handle, 1048576); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the file's contents, sent as a download
		ob_flush();
		flush();
	}
	return fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
}

/**
* Unserializes data stored by this plugin, refusing objects
*
* @param string $data
* @return mixed
*/
function sb_unserialize($data) {
	if (!is_string($data))
		return $data;
	return @unserialize($data, array('allowed_classes' => false));
}

/**
* Filters embed code attached to a sermon
*
* Allows the HTML allowed in posts, plus iframes for embedded players. srcdoc is not allowed,
* because it would run arbitrary HTML in the site's own origin.
* @param string $code
* @return string
*/
function sb_kses_embed($code) {
	global $allowedposttags;
	$allowed_tags = $allowedposttags;
	$allowed_tags['iframe'] = array('width' => true, 'height' => true, 'src' => true, 'frameborder' => true, 'allowfullscreen' => true, 'allow' => true, 'style' => true, 'name' => true, 'id' => true, 'class' => true, 'title' => true, 'align' => true, 'sandbox' => true, 'loading' => true, 'referrerpolicy' => true);
	return wp_kses($code, $allowed_tags);
}

/**
* Returns true if $file_name is a bare file name, with no directory part
*
* @param string $file_name
* @return bool
*/
function sb_is_plain_file_name($file_name) {
	return is_string($file_name) && $file_name !== '' && $file_name === basename($file_name) && validate_file($file_name) === 0 && strpos($file_name, '\\') === false;
}

/**
* Returns true if $file_name is a bare file name whose extension WordPress allows the current user to upload
*
* Uses the site's allowed upload types (get_allowed_mime_types), so PHP and other executable files are never allowed.
* @param string $file_name
* @return bool
*/
function sb_is_allowed_file_name($file_name) {
	if (!sb_is_plain_file_name($file_name))
		return false;
	$filetype = wp_check_filetype($file_name);
	return !empty($filetype['ext']) && !empty($filetype['type']);
}

/**
* Checks an uploaded file's name and contents against the file types WordPress allows
*
* @param string $tmp_name (path of the uploaded file)
* @param string $file_name (name supplied by the browser)
* @param bool $images_only
* @return string|false the sanitized file name, or false if the file is not allowed
*/
function sb_check_uploaded_file($tmp_name, $file_name, $images_only = false) {
	$file_name = sanitize_file_name(wp_unslash($file_name));
	if (!sb_is_allowed_file_name($file_name))
		return false;
	$filetype = wp_check_filetype_and_ext($tmp_name, $file_name);
	if (empty($filetype['ext']) || empty($filetype['type']))
		return false;
	if ($images_only && strpos($filetype['type'], 'image/') !== 0)
		return false;
	if (!empty($filetype['proper_filename']))
		$file_name = $filetype['proper_filename'];
	return $file_name;
}

/**
* Moves an uploaded file, already checked with sb_check_uploaded_file(), into a folder under the given name
*
* @param array $file the file's entry in $_FILES (name, type, tmp_name, error, size)
* @param string $dir absolute path of the folder, without a trailing slash
* @param string $file_name
* @return boolean
*/
function sb_handle_upload($file, $dir, $file_name) {
	if (!function_exists('wp_handle_upload'))
		require_once(ABSPATH.'wp-admin/includes/file.php');
	$use_dir = function ($uploads) use ($dir) {
		$uploads['path'] = $dir;
		$uploads['subdir'] = '';
		$uploads['error'] = false;
		return $uploads;
	};
	$use_name = function () use ($file_name) {
		return $file_name;
	};
	add_filter('upload_dir', $use_dir);
	// The type has been checked by sb_check_uploaded_file(), which may also have corrected the extension
	$result = wp_handle_upload($file, array('test_form' => false, 'test_type' => false, 'unique_filename_callback' => $use_name));
	remove_filter('upload_dir', $use_dir);
	return empty($result['error']) && !empty($result['file']);
}

/**
* Moves or renames a file in the upload folder
*
* The plugin writes to its upload folder directly, as WordPress does with its own uploads, so this uses the direct
* filesystem class: WP_Filesystem() may choose FTP and need credentials, which an AJAX request can't ask for.
*
* @param string $from absolute path
* @param string $to absolute path
* @return boolean
*/
function sb_move_file($from, $to) {
	require_once(ABSPATH.'wp-admin/includes/class-wp-filesystem-base.php');
	require_once(ABSPATH.'wp-admin/includes/class-wp-filesystem-direct.php');
	// As WP_Filesystem() defines them, for the copy that move() falls back to
	if (!defined('FS_CHMOD_DIR'))
		define('FS_CHMOD_DIR', (fileperms(ABSPATH) & 0777 | 0755));
	if (!defined('FS_CHMOD_FILE'))
		define('FS_CHMOD_FILE', (fileperms(ABSPATH.'index.php') & 0777 | 0644));
	$filesystem = new WP_Filesystem_Direct(null);
	return $filesystem->move($from, $to);
}

/**
* Sanitizes Windows paths
*/
function sb_sanitise_path ($path) {
	$path = str_replace('\\','/',$path);
	$path = preg_replace('|/+|','/', $path);
	return $path;
}
?>