<?php
if (!defined('ABSPATH'))
	exit;
/**
* Turning the request's filters into a sermon query, and finding a sermon once a request
*
* @package common_functions
*/
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for

/**
* Turns the filter and order arrays the plugin has always used into a sermon query
*
* @param array $filter
* @param array $order
* @param integer $page
* @param integer $limit
* @param boolean $hide_empty
* @return SermonBrowser\Sermon_Query
*/
function sb_sermon_query($filter, $order, $page = 1, $limit = 0, $hide_empty = false) {
	$filter = array_merge(array('title' => '', 'preacher' => 0, 'date' => '', 'enddate' => '', 'series' => 0, 'service' => 0, 'book' => '', 'tag' => '', 'id' => 0), (array) $filter);
	$order = array_merge(array('by' => 'm.datetime', 'dir' => 'desc'), (array) $order);
	$sort_values = array('m.datetime' => 'date', 'm.title' => 'title', 'preacher' => 'preacher', 'passage' => 'passage');
	$query = new SermonBrowser\Sermon_Query();
	$query->text = (string) $filter['title'];
	$query->preacher = (int) $filter['preacher'];
	$query->from = (string) $filter['date'];
	$query->to = (string) $filter['enddate'];
	$query->series = (int) $filter['series'];
	$query->service = (int) $filter['service'];
	$query->book = (string) $filter['book'];
	$query->tag = (string) $filter['tag'];
	$query->id = (int) $filter['id'];
	$query->with_attachments = (bool) $hide_empty;
	$sortby = sb_normalise_sortby((string) $order['by']);
	$query->order_by = isset($sort_values[$sortby]) ? $sort_values[$sortby] : 'date';
	$query->ascending = strtolower((string) $order['dir']) == 'asc';
	$query->page = max(1, (int) $page);
	$query->per_page = max(1, (int) $limit);
	return $query;
}

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
* Maps the sort values of old links to the ones the sermon list uses now
*
* @param string $sortby
* @return string
*/
function sb_normalise_sortby($sortby) {
	$old_values = array( 'p.name' => 'preacher', 'b.id' => 'passage' );
	return isset($old_values[$sortby]) ? $old_values[$sortby] : $sortby;
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
