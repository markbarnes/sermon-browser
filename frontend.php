<?php 

require_once('dictionary.php');
$sef = "/sermons";
$wl = array('preacher', 'title', 'date', 'enddate', 'series', 'service', 'sortby', 'dir', 'page', 'sermon_id');

add_action('wp_head', 'bb_print_header');
add_filter('the_content', 'bb_sermons_filter');

function bb_sermons_filter($content) {
	global $wpdb, $clr;
	global $wordpressRealPath;
	
	ob_start();
	
	if ($_GET['sermon_id']) {
		$clr = true;
		$sermon = bb_get_single_sermon((int) $_GET['sermon_id']);
		include($wordpressRealPath.'/wp-content/plugins/sermonbrowser/single.php');
	} else {
		$clr = false;
		$sermons = bb_get_sermons(array(
			'title' => $_REQUEST['title'],
			'preacher' => $_REQUEST['preacher'],
			'date' => $_REQUEST['date'],
			'enddate' => $_REQUEST['enddate'],
			'series' => $_REQUEST['series'],
			'service' => $_REQUEST['service'],
		),
		array(
			'by' => $_REQUEST['sortby'] ? $_REQUEST['sortby'] : 'm.date',
			'dir' => $_REQUEST['dir'],
		),
		$_REQUEST['page'] ? $_REQUEST['page'] : 1			
		);
		include($wordpressRealPath.'/wp-content/plugins/sermonbrowser/multi.php');
	}			
	$content = str_replace('[sermons]', ob_get_contents(), $content);
	
	ob_end_clean();		
	
	return $content;
}

function bb_build_url($arr, $clear = false) {
	global $wl, $sef;
	$foo = array_merge((array) $_GET, (array) $_POST, $arr);
	foreach ($foo as $k => $v) {
		if (!$clear || in_array($k, array_keys($arr)) || !in_array($k, $wl)) {
			$bar[] = "$k=$v";
		}
	}	
	if ($sef != "") return get_bloginfo('url') . $sef.'/?' . implode('&', $bar);
	return get_bloginfo('url') . '?' . implode('&', $bar);
	
}

function bb_print_header() {
	$url = get_bloginfo('wpurl');
?>
	<link rel="stylesheet" href="<?php echo $url ?>/wp-content/plugins/sermonbrowser/datepicker.css" type="text/css">
	<link rel="stylesheet" href="<?php echo $url ?>/wp-content/plugins/sermonbrowser/style.css" type="text/css">
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/jquery/jquery.js"></script>
	<script type="text/javascript" src="<?php echo $url ?>/wp-content/plugins/sermonbrowser/datePicker.js"></script>
<?php
}

function bb_print_sermon_link($sermon) {
	echo bb_build_url(array('sermon_id' => $sermon->id), true);
}

function bb_print_preacher_link($sermon) {
	global $clr;
	echo bb_build_url(array('preacher' => $sermon->pid), $clr);
}

function bb_print_series_link($sermon) {
	global $clr;	
	echo bb_build_url(array('series' => $sermon->ssid), $clr);
}

function bb_print_service_link($sermon) {
	global $clr;
	echo bb_build_url(array('service' => $sermon->sid), $clr);
}

function bb_print_next_page_link($limit = 15) {
	global $sermon_domain;
	$current = $_REQUEST['page'] ? (int) $_REQUEST['page'] : 1;
	if ($current < bb_page_count($limit)) {
		$url = bb_build_url(array('page' => ++$current));
		echo '<a href="'.$url.'">'.__('Next page &raquo;', $sermon_domain).'</a>';
	}	
}

function bb_print_prev_page_link($limit = 15) {
	global $sermon_domain;
	$current = $_REQUEST['page'] ? (int) $_REQUEST['page'] : 1;
	if ($current > 1) {
		$url = bb_build_url(array('page' => --$current));
		echo '<a href="'.$url.'">'.__('&laquo; Previous page', $sermon_domain).'</a>';
	}	
}

function bb_print_file($name) {
	global $filetypes, $default_icon;
	$icon_url = get_bloginfo('wpurl').'/wp-content/plugins/sermonbrowser/icons/';
	$file_url = get_option('sb_sermon_upload_url');
	$ext = substr($name, strrpos($name, '.') + 1);
	if (strtolower($ext) == 'mp3' && function_exists('ap_insert_player_widgets')) {
		echo ap_insert_player_widgets('[audio:'.$file_url.$name.']');
	} elseif (isset($filetypes[$ext]['icon'])) {
		echo '<a href="'.$file_url.$name.'"><img class="sermon-icon" alt="'.$name.'" src="'.$icon_url.$filetypes[$ext]['icon'].'"></a>';
	} else {
		echo '<a href="'.$file_url.$name.'"><img class="sermon-icon" alt="'.$name.'" src="'.$icon_url.$default_icon.'"> '.$name.'</a>';
	}	
}

function bb_print_next_sermon_link($sermon) {
	global $wpdb;
	$next = $wpdb->get_row("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE date > '$sermon->date' AND id <> $sermon->id ORDER BY date asc");
	if (!$next) return;
	echo '<a href="';
	bb_print_sermon_link($next);
	echo '">'.$next->title.' &raquo;</a>';
}

function bb_print_prev_sermon_link($sermon) {
	global $wpdb;
	$prev = $wpdb->get_row("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE date < '$sermon->date' AND id <> $sermon->id ORDER BY date desc");
	if (!$prev) return;
	echo '<a href="';
	bb_print_sermon_link($prev);
	echo '">&laquo; '.$prev->title.'</a>';
}

function bb_print_sameday_sermon_link($sermon) {
	global $wpdb, $sermon_domain;
	$same = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE date = '$sermon->date' AND id <> $sermon->id");
	if (!$same) {
		_e('None', $sermon_domain);
		return;
	}
	foreach ($same as $cur) {
		echo '<a href="';
		bb_print_sermon_link($cur);
		echo '">'.$cur->title.'</a>';
	}
}

function bb_get_single_sermon($id) {
	global $wpdb;
	$id = (int) $id;
	$sermon = $wpdb->get_row("SELECT m.id, m.title, m.date, m.start, m.end, p.id as pid, p.name as preacher, s.id as sid, s.name as service, ss.id as ssid, ss.name as series FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id and m.id = $id");
	$files = $wpdb->get_results("SELECT f.name FROM {$wpdb->prefix}sb_sermon_files as f WHERE sermon_id = $id ORDER BY id desc");
	$sermon->start = unserialize($sermon->start);
	$sermon->end = unserialize($sermon->end);
	return array(		
		'Sermon' => $sermon,
		'Files' => $files,
	);
}

function bb_print_sermons_count() {
	echo bb_count_sermons(array(
		'title' => $_REQUEST['title'],
		'preacher' => $_REQUEST['preacher'],
		'date' => $_REQUEST['date'],
		'enddate' => $_REQUEST['enddate'],
		'series' => $_REQUEST['series'],
		'service' => $_REQUEST['service'],
	));
}

function bb_page_count($limit = 15) {
	$total = bb_count_sermons(array(
		'title' => $_REQUEST['title'],
		'preacher' => $_REQUEST['preacher'],
		'date' => $_REQUEST['date'],
		'enddate' => $_REQUEST['enddate'],
		'series' => $_REQUEST['series'],
		'service' => $_REQUEST['service'],		
	));
	return ceil($total / $limit);
}

function bb_count_sermons($filter) {
	global $wpdb;
	$default_filter = array(
		'title' => '',
		'preacher' => 0,
		'date' => '',
		'enddate' => '',
		'series' => 0,
		'service' => 0,
	);	
	$filter = array_merge($default_filter, $filter);	
	if ($filter['title'] != '') {
		$cond = "AND (m.title LIKE '%" . mysql_real_escape_string($filter['title']) . "%' OR m.description LIKE '%" . mysql_real_escape_string($filter['title']) . "%' ";
	}
	if ($filter['preacher'] != 0) {
		$cond .= 'AND m.preacher_id = ' . (int) $filter['preacher'] . ' ';
	}
	if ($filter['date'] != '') {
		$cond .= 'AND m.date >= "' . mysql_real_escape_string($filter['date']) . '" ';
	}
	if ($filter['enddate'] != '') {
		$cond .= 'AND m.date <= "' . mysql_real_escape_string($filter['date']) . '" ';
	}
	if ($filter['series'] != 0) {
		$cond .= 'AND m.series_id = ' . (int) $filter['series'] . ' ';
	}
	if ($filter['service'] != 0) {
		$cond .= 'AND m.service_id = ' . (int) $filter['service'] . ' ';
	}		
	$query = "SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id $cond";	
	return $wpdb->get_var($query);
}

function bb_get_sermons($filter, $order, $page = 1, $limit = 15) {
	global $wpdb;
	$default_filter = array(
		'title' => '',
		'preacher' => 0,
		'date' => '',
		'enddate' => '',
		'series' => 0,
		'service' => 0,
	);
	$default_order = array(
		'by' => 'm.date',
		'dir' => 'desc',
	);
	$filter = array_merge($default_filter, $filter);
	$order = array_merge($default_order, $order);
	$page = (int) $page;
	if ($filter['title'] != '') {
		$cond = "AND (m.title LIKE '%" . mysql_real_escape_string($filter['title']) . "%' OR m.description LIKE '%" . mysql_real_escape_string($filter['title']) . "%') ";
	}
	if ($filter['preacher'] != 0) {
		$cond .= 'AND m.preacher_id = ' . (int) $filter['preacher'] . ' ';
	}
	if ($filter['date'] != '') {
		$cond .= 'AND m.date >= "' . mysql_real_escape_string($filter['date']) . '" ';
	}
	if ($filter['enddate'] != '') {
		$cond .= 'AND m.date <= "' . mysql_real_escape_string($filter['date']) . '" ';
	}
	if ($filter['series'] != 0) {
		$cond .= 'AND m.series_id = ' . (int) $filter['series'] . ' ';
	}
	if ($filter['service'] != 0) {
		$cond .= 'AND m.service_id = ' . (int) $filter['service'] . ' ';
	}	
	$offset = $limit * ($page - 1);
	$query = "SELECT m.id, m.title, m.date, m.start, m.end, p.id as pid, p.name as preacher, s.id as sid, s.name as service, ss.id as ssid, ss.name as series  FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id $cond ORDER BY ". $order['by'] . " " . $order['dir'] . " LIMIT " . $offset . ", " . $limit;
	return $wpdb->get_results($query);
}

function bb_get_files($sermon) {
	global $wpdb;
	return $wpdb->get_results("SELECT f.name FROM {$wpdb->prefix}sb_sermon_files as f WHERE sermon_id = $sermon->id ORDER BY id desc");
}

function bb_print_filters() {
	global $wpdb, $sermon_domain;
	
	$url = get_bloginfo('wpurl');
	
	$preachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY id;");	
	$series = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY id;");
	$services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_services ORDER BY id;");
	
	$sb = array(		
		'Title' => 'm.title',
		'Preacher' => 'preacher',
		'Date' => 'm.date',
	);
	
	$di = array(
		'Ascending' => 'asc',
		'Descending' => 'desc',
	);
	
	$csb = $_REQUEST['sortby'] ? $_REQUEST['sortby'] : 'm.date';
	$cd = $_REQUEST['dir'] ? $_REQUEST['dir'] : 'asc';	
?>	
	<form method="post" id="sermon-filter">
		<div style="clear:both">
		<div class="half">
				<div class="title"><?php _e('Keywords', $sermon_domain) ?></div>
				<input type="text" id="title" name="title" value="<?php echo mysql_real_escape_string($_REQUEST['title']) ?>" />
		</div>
		<div class="half">
				<div class="title"><?php _e('Preacher', $sermon_domain) ?></div>
				<select name="preacher" id="preacher">
					<option value="0" <?php echo $_REQUEST['preacher'] != 0 ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
					<?php foreach ($preachers as $preacher): ?>
					<option value="<?php echo $preacher->id ?>" <?php echo $_REQUEST['preacher'] == $preacher->id ? 'selected="selected"' : '' ?>><?php echo $preacher->name ?></option>
					<?php endforeach ?>
				</select>
		</div>
		</div>
		<div style="clear:both">
		<div class="half">		
				<div class="title"><?php _e('Series', $sermon_domain) ?></div>
				<select name="series" id="series">
					<option value="0" <?php echo $_REQUEST['series'] != 0 ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
					<?php foreach ($series as $item): ?>
					<option value="<?php echo $item->id ?>" <?php echo $_REQUEST['series'] == $item->id ? 'selected="selected"' : '' ?>><?php echo $item->name ?></option>
					<?php endforeach ?>
				</select>
		</div>
		<div class="half">		
				<div class="title"><?php _e('Services', $sermon_domain) ?></div>
				<select name="service" id="service">
					<option value="0" <?php echo $_REQUEST['service'] != 0 ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
					<?php foreach ($services as $service): ?>
					<option value="<?php echo $service->id ?>" <?php echo $_REQUEST['service'] == $service->id ? 'selected="selected"' : '' ?>><?php echo $service->name ?></option>
					<?php endforeach ?>
				</select>	
		</div>
		</div>
		<div style="clear:both">
		<div class="half">
				<div class="title"><?php _e('Start date', $sermon_domain) ?></div>
				<input type="text" name="date" id="date" value="<?php echo mysql_real_escape_string($_REQUEST['date']) ?>" />
		</div>
		<div class="half">
				<div class="title"><?php _e('End date', $sermon_domain) ?></div>
				<input type="text" name="date" id="enddate" value="<?php echo mysql_real_escape_string($_REQUEST['enddate']) ?>" />
		</div>
		</div>
		<div>
		<div class="half">
				<div class="title"><?php _e('Sort by', $sermon_domain) ?></div>
				<select name="sortby" id="sortby">
					<?php foreach ($sb as $k => $v): ?>
					<option value="<?php echo $v ?>" <?php echo $csb == $v ? 'selected="selected"' : '' ?>><?php _e($k, $sermon_domain) ?></option>
					<?php endforeach ?>
				</select>
		</div>
		<div class="half">
				<div class="title"><?php _e('Sorting direction', $sermon_domain) ?></div>
				<select name="dir" id="dir">
					<?php foreach ($di as $k => $v): ?>
					<option value="<?php echo $v ?>" <?php echo $cd == $v ? 'selected="selected"' : '' ?>><?php _e($k, $sermon_domain) ?></option>
					<?php endforeach ?>
				</select>
		</div>
		</div>
		<div style="clear:both">
		<div class="half">&nbsp;</div>
		<div class="half">
			<div class="title">&nbsp;</div>
			<input type="submit" class="filter" value="<?php _e('Filter &raquo;', $sermon_domain) ?>">			
		</div>
		<input type="hidden" name="page" value="1">
		</div>
	</form>
	<script type="text/javascript">
		jQuery.datePicker.setDateFormat('ymd','-');
		jQuery('#date').datePicker({startDate:'01/01/1970'});
		jQuery('#enddate').datePicker({startDate:'01/01/1970'});
	</script>
<?php
}

?>