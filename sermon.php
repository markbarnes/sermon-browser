<?php 
/*
Plugin Name: Sermon Browser
Plugin URI: http://www.4-14.org.uk/sermon-browser
Description: Add sermons to your Wordpress blog. Thanks to <a href="http://codeandmore.com/">Tien Do Xuan</a> for initial coding.
Author: Mark Barnes
Version: 0.43.5
Author URI: http://www.4-14.org.uk/

Copyright (c) 2008-2009 Mark Barnes

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
php4compat.php - Small number of functions required for PHP4 compatibility
podcast.php    - Handles the podcast feed
sb-install.php - Used only when installing the plugin for the first time
style.php      - Outputs the custom stylesheet
uninstall.php  - Removes the plugin and its databases tables and rows
upgrade.php    - Runs only when upgrading from earlier versions of SermonBrowser

If you want to follow the logic of the code, start with sb_sermon_init, and trace the Wordpress actions and filters.
The frontend output is inserted by sb_shortcode

*/

/**
* Initialisation
* 
* Sets version constants and basic Wordpress hooks.
* @package common_functions
*/
define('SB_CURRENT_VERSION', '0.43.5');
define('SB_DATABASE_VERSION', '1.6');
add_action ('plugins_loaded', 'sb_hijack');
add_action ('init', 'sb_sermon_init');
add_action ('widgets_init', 'sb_widget_sermon_init');

if (version_compare(PHP_VERSION, '5.0.0', '<'))
    require('sb-includes/php4compat.php');

/**
* Display podcast, or download linked files
* 
* Intercepts Wordpress at earliest opportunity. Checks whether the following are required before the full framework is loaded:
* Ajax data, stylesheet, file download
*/
function sb_hijack() {

    global $filetypes, $wpdb, $sermon_domain;
    sb_define_constants();

    if (function_exists('wp_timezone_supported') && wp_timezone_supported())
        wp_timezone_override_offset();

    if (isset($_POST['sermon']) && $_POST['sermon'] == 1)
        require('sb-includes/ajax.php');
    if (stripos($_SERVER['REQUEST_URI'], 'sb-style.css') !== FALSE || isset($_GET['sb-style']))
        require('sb-includes/style.php');

    //Forces sermon download of local file
    if (isset($_GET['download']) AND isset($_GET['file_name'])) {
        $file_name = $wpdb->escape(urldecode($_GET['file_name']));
        $file_name = $wpdb->get_var("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE name='{$file_name}'");
        if (!is_null($file_name)) {
            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
            header("Content-Type: application/force-download");
            header("Content-Type: application/octet-stream");
            header("Content-Type: application/download");
            header('Content-Disposition: attachment; filename="'.$file_name.'";');
            header("Content-Transfer-Encoding: binary");
            sb_increase_download_count ($file_name);
            $file_name = SB_ABSPATH.sb_get_option('upload_dir').$file_name;
            $filesize = filesize($file_name);
            if ($filesize != 0)
                header("Content-Length: ".filesize($file_name));
            output_file($file_name);
            die();
        } else
            wp_die(urldecode($_GET['file_name']).' '.__('not found', $sermon_domain), __('File not found', $sermon_domain), array('response' => 404));
    }

    //Forces sermon download of external URL
    if (isset($_REQUEST['download']) AND isset($_REQUEST['url'])) {
        $url = urldecode($_GET['url']);
        if(ini_get('allow_url_fopen')) {
            $headers = @get_headers($url, 1);
            if ($headers === FALSE || (isset($headers[0]) && strstr($headers[0], '404') !== FALSE))
                wp_die(urldecode($_GET['url']).' '.__('not found', $sermon_domain), __('URL not found', $sermon_domain), array('response' => 404));
            $headers = array_change_key_case($headers,CASE_LOWER);
            if (isset($headers['location'])) {
                $location =  $headers['location'];
                if (is_array($location))
                    $location = $location[0];
                if ($location && substr($location,0,7) != "http://") {
                    preg_match('@^(?:http://)?([^/]+)@i', $url, $matches);
                    $location = "http://".$matches[1].'/'.$location;
                }
                if ($location) {
                    header('Location: '.get_bloginfo('wpurl').'?download&url='.$location);
                    die();
                }
            }
            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
            header("Content-Type: application/force-download");
            header("Content-Type: application/octet-stream");
            header("Content-Type: application/download");
            if (isset($headers['last-modified']))
                header('Last-Modified: '.$headers['last-modified']);
            if (isset($headers['content-length']))
                header("Content-Length: ".$headers['content-length']);
            if (isset($headers['content-disposition']))
                header ('Content-Disposition: '.$headers['content-disposition']);
            else
                header('Content-Disposition: attachment; filename="'.basename($url).'";');
            header("Content-Transfer-Encoding: binary");
            header($_SERVER['SERVER_PROTOCOL'].' 200 OK');
            sb_increase_download_count($url);
            session_write_close();
            while (@ob_end_clean());
            output_file($url);
            die();
        } else {
            sb_increase_download_count ($url);
            header('Location: '.$url);
            die();
        }
    }
    
    //Returns local file (doesn't force download)
    if (isset($_GET['show']) AND isset($_GET['file_name'])) {
        global $filetypes;
        $file_name = $wpdb->escape(urldecode($_GET['file_name']));
        $file_name = $wpdb->get_var("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE name='{$file_name}'");
        if (!is_null($file_name)) {
            $url = sb_get_option('upload_url').$file_name;
            sb_increase_download_count ($file_name);
            header("Location: ".$url);
            die();
        } else
            wp_die(urldecode($_GET['file_name']).' '.__('not found', $sermon_domain), __('File not found', $sermon_domain), array('response' => 404));
    }
    
    //Returns contents of external URL(doesn't force download)
    if (isset($_REQUEST['show']) AND isset($_REQUEST['url'])) {
        $url = URLDecode($_GET['url']);
        sb_increase_download_count ($url);
        header('Location: '.$url);
        die();
    }
}

/**
* Main initialisation function
* 
* Sets up most Wordpress hooks and filters, depending on whether request is for front or back end.
*/
function sb_sermon_init () {
	global $sermon_domain, $wpdb, $defaultMultiForm, $defaultSingleForm, $defaultStyle;
	$sermon_domain = 'sermon-browser';
	if (IS_MU) {
			load_plugin_textdomain($sermon_domain, '', 'sb-includes');
	} else {
			load_plugin_textdomain($sermon_domain, '', 'sermon-browser/sb-includes');
	}
	if (WPLANG != '')
		setlocale(LC_ALL, WPLANG.'.UTF-8');

    //Display the podcast if that's what's requested
    if (isset($_GET['podcast']))
        require('sb-includes/podcast.php');

	// Register custom CSS and javascript files
	wp_register_script('sb_64', SB_PLUGIN_URL.'/sb-includes/64.js', false, SB_CURRENT_VERSION);
	wp_register_script('sb_datepicker', SB_PLUGIN_URL.'/sb-includes/datePicker.js', array('jquery'), SB_CURRENT_VERSION);
	wp_register_style('sb_datepicker', SB_PLUGIN_URL.'/sb-includes/datepicker.css', false, SB_CURRENT_VERSION);
    if (get_option('permalink_structure') == '')
	    wp_register_style('sb_style', trailingslashit(get_option('siteurl')).'?sb-style&', false, sb_get_option('style_date_modified'));
    else
        wp_register_style('sb_style', trailingslashit(get_option('siteurl')).'sb-style.css', false, sb_get_option('style_date_modified'));

	// Register [sermon] shortcode handler
	add_shortcode('sermons', 'sb_shortcode');

	// Attempt to set php.ini directives
	if (sb_return_kbytes(ini_get('upload_max_filesize'))<15360)
		ini_set('upload_max_filesize', '15M');
	if (sb_return_kbytes(ini_get('post_max_size'))<15360)
		ini_set('post_max_size', '15M');
	if (sb_return_kbytes(ini_get('memory_limit'))<49152)
		ini_set('memory_limit', '48M');
	if (intval(ini_get('max_input_time'))<600)
		ini_set('max_input_time','600');
	if (intval(ini_get('max_execution_time'))<600)
		ini_set('max_execution_time', '600');
	if (ini_get('file_uploads')<>'1')
		ini_set('file_uploads', '1');

	// Check whether upgrade required
    if (current_user_can('manage_options') && is_admin()) {
        if (get_option('sb_sermon_db_version'))
            $db_version = get_option('sb_sermon_db_version');
	    else
            $db_version = sb_get_option('db_version');
        if ($db_version && $db_version != SB_DATABASE_VERSION) {
            require_once ('sb-includes/upgrade.php');
            sb_database_upgrade ($db_version);
        } elseif (!$db_version) {
            require ('sb-includes/sb-install.php');
            sb_install();
        }
	    $sb_version = sb_get_option('code_version');
	    if ($sb_version != SB_CURRENT_VERSION) {
		    require_once ('sb-includes/upgrade.php');
		    sb_version_upgrade ($sb_version, SB_CURRENT_VERSION);
	    }
    }

	
	// Load shared (admin/frontend) features
	add_action ('save_post', 'update_podcast_url');

	// Check to see what functions are required, and only load what is needed
	if (stripos($_SERVER['REQUEST_URI'], '/wp-admin/') === FALSE) {
		require ('sb-includes/frontend.php');
		add_action('wp_head', 'sb_add_headers', 0);
		add_action('wp_head', 'wp_print_styles', 9);
		add_filter('wp_title', 'sb_page_title');
		if (defined('SAVEQUERIES') && SAVEQUERIES)
			add_action ('wp_footer', 'sb_footer_stats');
    } else {
		require ('sb-includes/admin.php');
		add_action ('admin_menu', 'sb_add_pages');
		add_action ('rightnow_end', 'sb_rightnow');
		add_action('admin_init', 'sb_add_admin_headers');
        add_filter('contextual_help', 'sb_add_contextual_help');
		if (defined('SAVEQUERIES') && SAVEQUERIES)
			add_action('admin_footer', 'sb_footer_stats');
	}
}

/**
* Add Sermons menu and sub-menus in admin
*/
function sb_add_pages() {
	global $sermon_domain;
	add_menu_page(__('Sermons', $sermon_domain), __('Sermons', $sermon_domain), 'edit_posts', __FILE__, 'sb_manage_sermons', SB_PLUGIN_URL.'/sb-includes/sb-icon.png');
	add_submenu_page(__FILE__, __('Sermons', $sermon_domain), __('Sermons', $sermon_domain), 'edit_posts', __FILE__, 'sb_manage_sermons');
	if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'sermon-browser/new_sermon.php' && isset($_REQUEST['mid'])) {
		add_submenu_page(__FILE__, __('Edit Sermon', $sermon_domain), __('Edit Sermon', $sermon_domain), 'publish_posts', 'sermon-browser/new_sermon.php', 'sb_new_sermon');
	} else {
		add_submenu_page(__FILE__, __('Add Sermon', $sermon_domain), __('Add Sermon', $sermon_domain), 'publish_posts', 'sermon-browser/new_sermon.php', 'sb_new_sermon');
	}
	add_submenu_page(__FILE__, __('Files', $sermon_domain), __('Files', $sermon_domain), 'upload_files', 'sermon-browser/files.php', 'sb_files');
	add_submenu_page(__FILE__, __('Preachers', $sermon_domain), __('Preachers', $sermon_domain), 'manage_categories', 'sermon-browser/preachers.php', 'sb_manage_preachers');
	add_submenu_page(__FILE__, __('Series &amp; Services', $sermon_domain), __('Series &amp; Services', $sermon_domain), 'manage_categories', 'sermon-browser/manage.php', 'sb_manage_everything');
	add_submenu_page(__FILE__, __('Options', $sermon_domain), __('Options', $sermon_domain), 'manage_options', 'sermon-browser/options.php', 'sb_options');
	add_submenu_page(__FILE__, __('Templates', $sermon_domain), __('Templates', $sermon_domain), 'manage_options', 'sermon-browser/templates.php', 'sb_templates');
	add_submenu_page(__FILE__, __('Uninstall', $sermon_domain), __('Uninstall', $sermon_domain), 'edit_plugins', 'sermon-browser/uninstall.php', 'sb_uninstall');
	add_submenu_page(__FILE__, __('Help', $sermon_domain), __('Help', $sermon_domain), 'edit_posts', 'sermon-browser/help.php', 'sb_help');
}

/**
* Converts php.ini mega- or giga-byte numbers into kilobytes
* 
* @param string $val
* @return integer
*/
function sb_return_kbytes($val) {
	$val = trim($val);
	$last = strtolower($val[strlen($val)-1]);
	switch($last) {
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
	}
   return intval($val);
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
	$stats = $wpdb->get_var("SELECT SUM(count) FROM ".$wpdb->prefix."sb_stuff WHERE sermon_id=".$sermonid);
	if ($stats > 0)
		return $stats;
}

/**
* Updates podcast URL in wp_options
* 
* Function required if permalinks changed or [sermons] added to a different page
*/
 function update_podcast_url () {
	global $wp_rewrite;
	$existing_url = sb_get_option('podcast_url');
	if (substr($existing_url, 0, strlen(get_bloginfo('wpurl'))) == get_bloginfo('wpurl')) {
		if (sb_display_url(TRUE)=="") {
			sb_update_option('podcast_url', get_bloginfo('wpurl').sb_query_char(false).'podcast');
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
	global $sermon_domain;
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
			return array(__('Genesis', $sermon_domain), __('Exodus', $sermon_domain), __('Leviticus', $sermon_domain), __('Numbers', $sermon_domain), __('Deuteronomy', $sermon_domain), __('Joshua', $sermon_domain), __('Judges', $sermon_domain), __('Ruth', $sermon_domain), __('1 Samuel', $sermon_domain), __('2 Samuel', $sermon_domain), __('1 Kings', $sermon_domain), __('2 Kings', $sermon_domain), __('1 Chronicles', $sermon_domain), __('2 Chronicles',$sermon_domain), __('Ezra', $sermon_domain), __('Nehemiah', $sermon_domain), __('Esther', $sermon_domain), __('Job', $sermon_domain), __('Psalm', $sermon_domain), __('Proverbs', $sermon_domain), __('Ecclesiastes', $sermon_domain), __('Song of Solomon', $sermon_domain), __('Isaiah', $sermon_domain), __('Jeremiah', $sermon_domain), __('Lamentations', $sermon_domain), __('Ezekiel', $sermon_domain), __('Daniel', $sermon_domain), __('Hosea', $sermon_domain), __('Joel', $sermon_domain), __('Amos', $sermon_domain), __('Obadiah', $sermon_domain), __('Jonah', $sermon_domain), __('Micah', $sermon_domain), __('Nahum', $sermon_domain), __('Habakkuk', $sermon_domain), __('Zephaniah', $sermon_domain), __('Haggai', $sermon_domain), __('Zechariah', $sermon_domain), __('Malachi', $sermon_domain), __('Matthew', $sermon_domain), __('Mark', $sermon_domain), __('Luke', $sermon_domain), __('John', $sermon_domain), __('Acts', $sermon_domain), __('Romans', $sermon_domain), __('1 Corinthians', $sermon_domain), __('2 Corinthians', $sermon_domain), __('Galatians', $sermon_domain), __('Ephesians', $sermon_domain), __('Philippians', $sermon_domain), __('Colossians', $sermon_domain), __('1 Thessalonians', $sermon_domain), __('2 Thessalonians', $sermon_domain), __('1 Timothy', $sermon_domain), __('2 Timothy', $sermon_domain), __('Titus', $sermon_domain), __('Philemon', $sermon_domain), __('Hebrews', $sermon_domain), __('James', $sermon_domain), __('1 Peter', $sermon_domain), __('2 Peter', $sermon_domain), __('1 John', $sermon_domain), __('2 John', $sermon_domain), __('3 John', $sermon_domain), __('Jude', $sermon_domain), __('Revelation', $sermon_domain));
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
	$pageid = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[sermons%' AND (post_status = 'publish' OR post_status = 'private') AND ID={$post->ID} AND post_date < NOW();");
	if ($pageid === NULL)
		return FALSE;
	else
		return TRUE;
}

/**
* Get the URL of the main sermons page
* 
* @return string
*/
function sb_display_url() {
	global $wpdb, $post, $sb_display_url;
	if ($sb_display_url == '') {
		$pageid = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[sermons]%' AND (post_status = 'publish' OR post_status = 'private') AND post_date < NOW();");
        if (!$pageid)
            $pageid = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[sermons %' AND (post_status = 'publish' OR post_status = 'private') AND post_date < NOW();");
        if (!$pageid)
            return '#';
		$sb_display_url = get_permalink($pageid);
		if ($sb_display_url == get_bloginfo('wpurl') || $sb_display_url == '') // Hack to force true permalink even if page used for front page.
			$sb_display_url = get_bloginfo('wpurl').'/?page_id='.$pageid;
	}
	return $sb_display_url;
}

/**
* Fix to ensure AudioPlayer v2 and AudioPlayer v1 both work
*/
if (!function_exists('ap_insert_player_widgets') && function_exists('insert_audio_player')) {
	function ap_insert_player_widgets($params) {
		return insert_audio_player($params);
	}
}

/**
* Adds database statistics to the HTML comments
* 
* Requires define('SAVEQUERIES', true) in wp-config.php
* Useful for diagnostics
*/
function sb_footer_stats() {
	global $wpdb;
	echo '<!-- ';
	echo($wpdb->num_queries.' queries. '.timer_stop().' seconds.');
	echo chr(13);
	print_r($wpdb->queries);
	echo chr(13);
	echo ' -->';
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
	global $wpdb, $record_count, $sermon_domain;
	ob_start();
	$atts = shortcode_atts(array(
		'filter' => sb_get_option('filter_type'),
		'filterhide' => sb_get_option('filter_hide'),
		'id' => isset($_REQUEST['sermon_id']) ? $_REQUEST['sermon_id'] : '',
		'preacher' => isset($_REQUEST['preacher']) ? $_REQUEST['preacher'] : '',
		'series' => isset($_REQUEST['series']) ? $_REQUEST['series'] : '',
		'book' => isset($_REQUEST['book']) ? $_REQUEST['book'] : '',
		'service' => isset($_REQUEST['service']) ? $_REQUEST['service'] : '',
		'date' => isset($_REQUEST['date']) ? $_REQUEST['date'] : '',
		'enddate' => isset($_REQUEST['enddate']) ? $_REQUEST['enddate'] : '',
		'tag' => isset($_REQUEST['stag']) ? $_REQUEST['stag'] : '',
		'title' => isset($_REQUEST['title']) ? $_REQUEST['title'] : '',
	), $atts);
	if ($atts['id'] != '') {
		if (strtolower($atts['id']) == 'latest') {
			$atts['id'] = '';
			$query = $wpdb->get_results(sb_create_multi_sermon_query($atts, array(), 1, 1));
			$atts['id'] = $query[0]->id;
		}
		$sermon = sb_get_single_sermon((int) $atts['id']);
        if ($sermon)
		    eval('?>'.sb_get_option('single_output'));
        else {
            echo "<div class=\"sermon-browser-results\"><span class=\"error\">";
            _e ('No sermons found.', $sermon_domain);
            echo "</span></div>";
        }
	} else {
		if (isset($_REQUEST['sortby']))
			$sort_criteria = $_REQUEST['sortby'];
		else
			$sort_criteria = 'm.datetime';
        if (isset($_REQUEST['dir']))
            $dir = $_REQUEST['dir'];
        elseif ($sort_criteria == 'm.datetime')
            $dir = 'desc';
        else
            $dir = 'asc';
		$sort_order = array('by' => $sort_criteria, 'dir' =>  $dir);
		if (isset($_REQUEST['page']))
			$page = $_REQUEST['page'];
		else
			$page = 1;
        $hide_empty = sb_get_option('hide_no_attachments');
		$sermons = sb_get_sermons($atts, $sort_order, $page, 0, $hide_empty);
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
	global $sermon_domain;
    //Sermons Widget
	if (!$options = sb_get_option('sermons_widget_options'))
		$options = array();
	$widget_ops = array('classname' => 'sermon', 'description' => __('Display a list of recent sermons.', $sermon_domain));
	$control_ops = array('width' => 400, 'height' => 350, 'id_base' => 'sermon');
	$name = __('Sermons', $sermon_domain);
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
	wp_register_sidebar_widget('sermon-browser-tags', __('Sermon Browser tags', $sermon_domain), 'sb_widget_tag_cloud_wrapper');
    //Most popular widget
    $name = __('Most popular sermons', $sermon_domain);
    $description = __('Display a list of the most popular sermons, series or preachers.', $sermon_domain);
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
	require_once ('sb-includes/frontend.php');
	sb_widget_sermon($args, $widget_args);
}

/**
* Wrapper for sb_widget_tag_cloud in frontend.php
* 
* Allows main widget functionality to be in the frontend package, whilst still allowing widgets to be modified in admin
* @param array $args
*/
function sb_widget_tag_cloud_wrapper ($args) {
	require_once ('sb-includes/frontend.php');
	sb_widget_tag_cloud ($args);
}

/**
* Wrapper for sb_widget_popular in frontend.php
* 
* Allows main widget functionality to be in the frontend package, whilst still allowing widgets to be modified in admin
* @param array $args
*/
function sb_widget_popular_wrapper ($args) {
    require_once ('sb-includes/frontend.php');
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
			$sermonbrowser_options = unserialize(base64_decode($options));
            if ($sermonbrowser_options === FALSE)
                wp_die ('Failed to get SermonBrowser options '.base64_decode(get_option('sermonbrowser_options')));
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
			    $sermonbrowser_options = unserialize(base64_decode($options));
                if ($sermonbrowser_options === FALSE)
                    wp_die ('Failed to get SermonBrowser options '.base64_decode(get_option('sermonbrowser_options')));
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
function sb_mkdir($pathname, $mode=0777) {
	is_dir(dirname($pathname)) || sb_mkdir(dirname($pathname), $mode);
	@mkdir($pathname, $mode);
	return @chmod($pathname, $mode);
}

/**
* Defines a number of constants used throughout the plugin
*/
function sb_define_constants() {
	$directories = explode(DIRECTORY_SEPARATOR,dirname(__FILE__));
	if ($directories[count($directories)-1] == 'mu-plugins') {
		define('IS_MU', TRUE);
	} else {
		define('IS_MU', FALSE);
	}
	if ( !defined('WP_CONTENT_URL') )
		define( 'WP_CONTENT_URL', trailingslashit(get_option('siteurl')) . 'wp-content');
	$plugin_dir = $directories[count($directories)-1];
	if (IS_MU)
		define ('SB_PLUGIN_URL', WP_CONTENT_URL.'/'.$plugin_dir);
	else
		define ('SB_PLUGIN_URL', rtrim(WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)), '/'));
    define ('SB_WP_PLUGIN_DIR', sb_sanitise_path(WP_PLUGIN_DIR));
    define ('SB_WP_CONTENT_DIR', sb_sanitise_path(WP_CONTENT_DIR));
    define ('SB_ABSPATH', sb_sanitise_path(ABSPATH));
    define ('GETID3_INCLUDEPATH', SB_WP_PLUGIN_DIR.'/'.$plugin_dir.'/sb-includes/getid3/');
    define ('GETID3_HELPERAPPSDIR', GETID3_INCLUDEPATH); 
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
	$query = $wpdb->get_results(sb_create_multi_sermon_query($filter, $order, $page, $limit, $hide_empty));
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
	$page = (int) $page;
	$cond = '1=1 ';
	if ($filter['title'] != '') {
		$cond .= "AND (m.title LIKE '%" . $wpdb->escape($filter['title']) . "%' OR m.description LIKE '%" . $wpdb->escape($filter['title']). "%' OR t.name LIKE '%" . $wpdb->escape($filter['title']) . "%') ";
	}
	if ($filter['preacher'] != 0) {
		$cond .= 'AND m.preacher_id = ' . (int) $filter['preacher'] . ' ';
	}
	if ($filter['date'] != '') {
		$cond .= 'AND m.datetime >= "' . $wpdb->escape($filter['date']) . '" ';
	}
	if ($filter['enddate'] != '') {
		$cond .= 'AND m.datetime <= "' . $wpdb->escape($filter['enddate']) . '" ';
	}
	if ($filter['series'] != 0) {
		$cond .= 'AND m.series_id = ' . (int) $filter['series'] . ' ';
	}
	if ($filter['service'] != 0) {
		$cond .= 'AND m.service_id = ' . (int) $filter['service'] . ' ';
	}	
	if ($filter['book'] != '') {
		$cond .= 'AND bs.book_name = "' . $wpdb->escape($filter['book']) . '" ';
	} else {
		$bs = "AND bs.order = 0 AND bs.type= 'start' ";
	}
	if ($filter['tag'] != '') {
		$cond .= "AND t.name LIKE '%" . $wpdb->escape($filter['tag']) . "%' ";
	}
	if ($filter['id'] != '') {
		$cond .= "AND m.id LIKE '" . $wpdb->escape($filter['id']) . "' ";
	}
    if ($hide_empty) {
        $cond .= "AND stuff.name != '' ";
    }
	$offset = $limit * ($page - 1);
	if ($order['by'] == 'b.id' ) {
	    $order['by'] = 'b.id '.$order['dir'].', bs.chapter '.$order['dir'].', bs.verse';
	}
	return "SELECT SQL_CALC_FOUND_ROWS DISTINCT m.id, m.title, m.description, m.datetime, m.time, m.start, m.end, p.id as pid, p.name as preacher, p.description as preacher_description, p.image, s.id as sid, s.name as service, ss.id as ssid, ss.name as series 
		FROM {$wpdb->prefix}sb_sermons as m 
		LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id 
		LEFT JOIN {$wpdb->prefix}sb_services as s ON m.service_id = s.id 
		LEFT JOIN {$wpdb->prefix}sb_series as ss ON m.series_id = ss.id 
		LEFT JOIN {$wpdb->prefix}sb_books_sermons as bs ON bs.sermon_id = m.id $bs 
		LEFT JOIN {$wpdb->prefix}sb_books as b ON bs.book_name = b.name 
		LEFT JOIN {$wpdb->prefix}sb_sermons_tags as st ON st.sermon_id = m.id 
		LEFT JOIN {$wpdb->prefix}sb_tags as t ON t.id = st.tag_id 
        LEFT JOIN {$wpdb->prefix}sb_stuff as stuff ON stuff.sermon_id = m.id 
		WHERE {$cond} ORDER BY ". $order['by'] . " " . $order['dir'] . " LIMIT " . $offset . ", " . $limit;
}

/**
* Returns the default time for a particular service
* 
* @param integer $service (id in database)
* @return string (service time)
*/
function sb_default_time($service) {
	global $wpdb;
	$sermon_time = $wpdb->get_var("SELECT time FROM {$wpdb->prefix}sb_services WHERE id='{$service}'");
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
	if ($mp3_only) {
		$stuff = $wpdb->get_results("SELECT f.type, f.name FROM {$wpdb->prefix}sb_stuff as f WHERE sermon_id = $sermon->id AND name LIKE '%.mp3' ORDER BY id desc");
	} else {
		$stuff = $wpdb->get_results("SELECT f.type, f.name FROM {$wpdb->prefix}sb_stuff as f WHERE sermon_id = $sermon->id ORDER BY id desc");
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
    if (function_exists('current_user_can')&&!(current_user_can('edit_posts')|current_user_can('publish_posts'))) {
        global $wpdb;
        $wpdb->query("UPDATE ".$wpdb->prefix."sb_stuff SET COUNT=COUNT+1 WHERE name='".$wpdb->escape($stuff_name)."'");
    }
}

/**
* Outputs a remote or local file
* 
* @param string $filename
* @return bool success or failure
*/
function output_file($filename) {
    $handle = fopen($filename, 'rb');
    if ($handle === false)
        return false;
    if (ob_get_level() == 0)
        ob_start(); 
    while (!feof($handle)) {
        set_time_limit(ini_get('max_execution_time'));
        $buffer = fread($handle, 1048576);
        echo $buffer;
        ob_flush();
        flush();
    }
    return fclose($handle);
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