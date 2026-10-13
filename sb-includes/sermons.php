<?php
if (!defined('ABSPATH'))
	exit;
/**
* Finding a sermon once a request, and download counts for the admin pages
*
* @package common_functions
*/
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for

/**
* Returns a sermon, reading it only once in a request (the page head, title and shortcode all want it)
*
* @param integer $id
* @return SermonBrowser\Sermon|null
*/
function sb_find_sermon($id) {
	static $sermons = array();
	$id = (int) $id;
	if (!array_key_exists($id, $sermons))
		$sermons[$id] = SermonBrowser\Storage::sermons()->get($id);
	return $sermons[$id];
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
