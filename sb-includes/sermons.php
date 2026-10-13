<?php
if (!defined('ABSPATH'))
	exit;
/**
* Reading sermons and their attachments from the database
*
* @package common_functions
*/
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for

/**
* Get multiple sermons, as the compiled templates and older code expect them
*
* Reads them through the sermon repository. Each sermon is a row object with the columns and aliases the
* old query returned (pid, preacher, ssid, series, start and end serialized and so on); text is unslashed.
* Stage 3 of the move to custom post types replaces the templates, and this, with Sermon objects.
* @param array $filter Keys title, preacher, date, enddate, series, service, book, tag, id
* @param array $order Keys by (a sortby value) and dir (asc or desc)
* @param integer $page
* @param integer $limit
* @param boolean $hide_empty Leave out sermons without attachments
* @global integer record_count Set to the number of sermons on all pages
* @return array
*/
function sb_get_sermons($filter, $order, $page = 1, $limit = 0, $hide_empty = false) {
	global $record_count;
	if ($limit == 0)
		$limit = sb_get_option('sermons_per_page');
	$result = SermonBrowser\Storage::sermons()->find(sb_sermon_query($filter, $order, $page, $limit, $hide_empty));
	$record_count = $result->total;
	$sermons = array();
	foreach ($result->sermons as $sermon) {
		sb_remember_sermon($sermon);
		$sermons[] = sb_sermon_row($sermon);
	}
	return $sermons;
}

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
* Returns a sermon as a row object with the columns and aliases of the old sermon list query
*
* @param SermonBrowser\Sermon $sermon
* @return stdClass
*/
function sb_sermon_row($sermon) {
	$start = $end = array();
	foreach ($sermon->passages as $passage) {
		$start[] = $passage->start->to_array();
		$end[] = $passage->end->to_array();
	}
	return (object) array(
		'id' => $sermon->id,
		'title' => $sermon->title,
		'description' => $sermon->description,
		'datetime' => $sermon->date ? $sermon->date->format('Y-m-d H:i:s') : '1970-01-01 00:00:00',
		'time' => $sermon->time,
		'start' => serialize($start),
		'end' => serialize($end),
		'pid' => $sermon->preacher ? $sermon->preacher->id : null,
		'preacher' => $sermon->preacher ? $sermon->preacher->name : null,
		'preacher_description' => $sermon->preacher ? $sermon->preacher->description : null,
		'image' => $sermon->preacher ? $sermon->preacher->image : null,
		'sid' => $sermon->service ? $sermon->service->id : null,
		'service' => $sermon->service ? $sermon->service->name : null,
		'ssid' => $sermon->series ? $sermon->series->id : null,
		'series' => $sermon->series ? $sermon->series->name : null,
	);
}

/**
* Keeps the sermons just listed, so sb_get_stuff() needn't read them again
*
* @param SermonBrowser\Sermon|null $sermon The sermon to keep, or null to look one up
* @param integer $id The id to look up
* @return SermonBrowser\Sermon|null
*/
function sb_remember_sermon($sermon = null, $id = 0) {
	static $sermons = array();
	if ($sermon) {
		$sermons[$sermon->id] = $sermon;
		return $sermon;
	}
	if (!isset($sermons[$id]))
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
* Gets a sermon's attachments, as the compiled templates and the feed expect them
*
* @param object $sermon (with its id in the id property)
* @param boolean $mp3_only (if true will only return MP3 files)
* @return array Files (file names), URLs, and Code (base64-encoded embed code), newest first
*/
function sb_get_stuff($sermon, $mp3_only = FALSE) {
	$file = $url = $code = array();
	$found = sb_remember_sermon(null, (int) $sermon->id);
	foreach ($found ? $found->attachments : array() as $attachment) {
		if ($mp3_only && strtolower(substr($attachment->content, -4)) != '.mp3')
			continue;
		if ($attachment->type == SermonBrowser\Attachment::FILE)
			$file[] = $attachment->content;
		elseif ($attachment->type == SermonBrowser\Attachment::URL)
			$url[] = $attachment->content;
		elseif ($attachment->type == SermonBrowser\Attachment::EMBED)
			$code[] = base64_encode($attachment->content);
	}
	return array(
		'Files' => $file,
		'URLs' => $url,
		'Code' => $code,
	);
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
* Gets a single sermon, as the compiled single sermon template expects it
*
* @param integer $id
* @return array|false Keys Sermon (a row object, with start and end as arrays), Files, Code and Tags
*/
function sb_get_single_sermon($id) {
	$sermon = sb_remember_sermon(null, (int) $id);
	// The old query joined the preacher and service without LEFT JOIN, so a sermon missing either wasn't found
	if (!$sermon || !$sermon->preacher || !$sermon->service)
		return false;
	$row = sb_sermon_row($sermon);
	$row->start = sb_unserialize($row->start);
	$row->end = sb_unserialize($row->end);
	$row->series = $sermon->series ? $sermon->series->name : '';
	$row->ssid = $sermon->series ? $sermon->series->id : 0;
	$stuff = sb_get_stuff($row);
	return array(
		'Sermon' => $row,
		'Files' => $stuff['Files'],
		'Code' => $stuff['Code'],
		'Tags' => $sermon->tags,
	);
}
