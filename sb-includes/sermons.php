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
	$order['by'] = sb_normalise_sortby($order['by']);
	$valid_sortby_values = array( 'm.id', 'm.title', 'm.datetime', 'm.start', 'm.end', 'p.id', 's.id', 's.name', 'ss.id', 'ss.name', 'preacher', 'passage');
	if ( !in_array($order['by'], $valid_sortby_values) )
		$order['by'] = $default_order['by'];
	// Sorting by preacher or passage lists ties newest first; sermons without a passage go last
	$sort_columns = $sort_join = '';
	if ($order['by'] == 'preacher') {
		$order_by = "p.name {$order['dir']}, m.datetime desc";
	} elseif ($order['by'] == 'passage') {
		// Sort on each sermon's first passage, joined separately so a book filter can't list a sermon twice
		$sort_columns = ', (sort_b.id IS NULL) AS sort_no_passage, sort_b.id AS sort_book, sort_bs.chapter AS sort_chapter, sort_bs.verse AS sort_verse';
		$sort_join = "LEFT JOIN {$wpdb->prefix}sb_books_sermons as sort_bs ON sort_bs.sermon_id = m.id AND sort_bs.order = 0 AND sort_bs.type = 'start'
		LEFT JOIN {$wpdb->prefix}sb_books as sort_b ON sort_bs.book_name = sort_b.name";
		$order_by = "sort_no_passage asc, sort_book {$order['dir']}, sort_chapter {$order['dir']}, sort_verse {$order['dir']}, m.datetime desc";
	} else {
		$order_by = "{$order['by']} {$order['dir']}";
	}
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
	return "SELECT SQL_CALC_FOUND_ROWS DISTINCT m.id, m.title, m.description, m.datetime, m.time, m.start, m.end, p.id as pid, p.name as preacher, p.description as preacher_description, p.image, s.id as sid, s.name as service, ss.id as ssid, ss.name as series{$sort_columns}
		FROM {$wpdb->prefix}sb_sermons as m
		LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id
		LEFT JOIN {$wpdb->prefix}sb_services as s ON m.service_id = s.id
		LEFT JOIN {$wpdb->prefix}sb_series as ss ON m.series_id = ss.id
		LEFT JOIN {$wpdb->prefix}sb_books_sermons as bs ON bs.sermon_id = m.id {$bs}
		LEFT JOIN {$wpdb->prefix}sb_books as b ON bs.book_name = b.name
		LEFT JOIN {$wpdb->prefix}sb_sermons_tags as st ON st.sermon_id = m.id
		LEFT JOIN {$wpdb->prefix}sb_tags as t ON t.id = st.tag_id
		LEFT JOIN {$wpdb->prefix}sb_stuff as stuff ON stuff.sermon_id = m.id
		{$sort_join}
		WHERE {$cond} ORDER BY {$order_by} LIMIT {$offset}, {$limit}";
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

//Gets single sermon from the database
function sb_get_single_sermon($id) {
	global $wpdb;
	$id = (int) $id;
	$sermon = $wpdb->get_row($wpdb->prepare("SELECT m.id, m.title, m.datetime, m.start, m.end, m.description, p.id as pid, p.name as preacher, p.image as image, p.description as preacher_description, s.id as sid, s.name as service FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s where m.preacher_id = p.id and m.service_id = s.id and m.id = %d", $id));
	$series = $wpdb->get_row($wpdb->prepare("SELECT ss.id as ssid, ss.name as series FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_series as ss WHERE m.series_id = ss.id and m.id = %d", $id));
	if ($sermon) {
		if ($series) {
			$sermon->series = $series->series;
			$sermon->ssid = $series->ssid;
		}
		else {
			$sermon->series = '';
			$sermon->ssid = 0;
		}
		$file = $code = $tags = array();
		$stuff = $wpdb->get_results($wpdb->prepare("SELECT f.id, f.type, f.name FROM {$wpdb->prefix}sb_stuff as f WHERE sermon_id = %d ORDER BY id desc", $id));
		$rawtags = $wpdb->get_results($wpdb->prepare("SELECT t.name FROM {$wpdb->prefix}sb_sermons_tags as st LEFT JOIN {$wpdb->prefix}sb_tags as t ON st.tag_id = t.id WHERE st.sermon_id = %d ORDER BY t.name asc", $id));
		foreach ($rawtags as $tag) {
			$tags[] = $tag->name;
		}
		foreach ($stuff as $cur)
			${$cur->type}[] = $cur->name;
		$sermon->start = sb_unserialize($sermon->start);
		$sermon->end = sb_unserialize($sermon->end);
		return array(
			'Sermon' => $sermon,
			'Files' => $file,
			'Code' => $code,
			'Tags' => $tags,
		);
	} else
		return false;
}
