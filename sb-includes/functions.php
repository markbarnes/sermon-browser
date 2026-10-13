<?php
if (!defined('ABSPATH'))
	exit;
/**
* Functions shared by the admin pages and the front end: options, the sermons page's URL, and the upload folder and its files
*
* @package common_functions
*/
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WordPress's posts table, searched for the shortcode

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
	return array ('single_template', 'search_template', 'css_style');
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
