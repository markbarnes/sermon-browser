<?php
/**
* Handles AJAX requests from the admin pages (POST requests with sermon=1)
*
* Every request must come from a logged-in user, carry a valid sb_ajax nonce (_sbnonce),
* and is then checked against the capability of the admin page it belongs to.
*/
if (!defined('ABSPATH'))
	exit;

define ('SB_AJAX', true);

if (!is_user_logged_in() || !isset($_POST['_sbnonce']) || !wp_verify_nonce($_POST['_sbnonce'], 'sb_ajax')) {
	status_header(403);
	die('-1');
}

/**
* Ends the request unless the current user has the required capability
*
* @param string $capability
*/
function sb_ajax_require($capability) {
	if (!current_user_can($capability)) {
		status_header(403);
		die('-1');
	}
}

// Throughout this plugin, p stands for preacher, s stands for service and ss stands for series
// Creating is allowed from the Add Sermon page; editing and deleting only from the Preachers and Series & Services pages
if (isset($_POST['pname'])) { // preacher
	$pname = sanitize_text_field(wp_unslash($_POST['pname']));
	if (isset($_POST['pid'])) {
		sb_ajax_require('manage_categories');
		$pid = (int) $_POST['pid'];
		if (isset($_POST['del'])) {
			$wpdb->query("DELETE FROM {$wpdb->prefix}sb_preachers WHERE id = $pid;");
		} else {
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_preachers SET name = %s WHERE id = $pid;", $pname));
		}
		echo 'done';
		die();
	} else {
		sb_ajax_require('publish_posts');
		$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_preachers VALUES (null, %s, '', '');", $pname));
		echo (int) $wpdb->insert_id;
		die();
	}
} elseif (isset($_POST['sname'])) { // service
	$sname = sanitize_text_field(wp_unslash($_POST['sname']));
	$sname_parts = explode('@', $sname, 2);
	$sname = trim($sname_parts[0]);
	$stime = isset($sname_parts[1]) ? trim($sname_parts[1]) : '';
	if (!preg_match('/^\d{1,2}:\d{2}$/', $stime))
		$stime = '00:00';
	if (isset($_POST['sid'])) {
		sb_ajax_require('manage_categories');
		$sid = (int) $_POST['sid'];
		if (isset($_POST['del'])) {
			$wpdb->query("DELETE FROM {$wpdb->prefix}sb_services WHERE id = $sid;");
		} else {
			$old_time = $wpdb->get_var("SELECT time FROM {$wpdb->prefix}sb_services WHERE id = $sid;");
			if (!$old_time)
				$old_time = '00:00';
			$difference = (int) (strtotime($stime) - strtotime($old_time));
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_services SET name = %s, time = %s WHERE id = $sid;", $sname, $stime));
			$wpdb->query("UPDATE {$wpdb->prefix}sb_sermons SET datetime = DATE_ADD(datetime, INTERVAL {$difference} SECOND) WHERE override = 0 AND service_id = $sid;");
		}
		echo 'done';
		die();
	} else {
		sb_ajax_require('publish_posts');
		$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_services VALUES (null, %s, %s);", $sname, $stime));
		echo (int) $wpdb->insert_id;
		die();
	}
} elseif (isset($_POST['ssname'])) { // series
	$ssname = sanitize_text_field(wp_unslash($_POST['ssname']));
	if (isset($_POST['ssid'])) {
		sb_ajax_require('manage_categories');
		$ssid = (int) $_POST['ssid'];
		if (isset($_POST['del'])) {
			$wpdb->query("DELETE FROM {$wpdb->prefix}sb_series WHERE id = $ssid;");
		} else {
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_series SET name = %s WHERE id = $ssid;", $ssname));
		}
		echo 'done';
		die();
	} else {
		sb_ajax_require('publish_posts');
		$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_series VALUES (null, %s, 0);", $ssname));
		echo (int) $wpdb->insert_id;
		die();
	}
} elseif (isset($_POST['fid'])) { // Files
	sb_ajax_require('upload_files');
	// The file is identified by its id; posted names are never trusted as paths
	$fid = (int) $_POST['fid'];
	$old_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE id = %d AND type = 'file'", $fid));
	if ($old_name === null || !sb_is_plain_file_name($old_name)) {
		echo 'failed';
		die();
	}
	$upload_dir = SB_ABSPATH.sb_get_option('upload_dir');
	if (isset($_POST['del'])) {
		wp_delete_file($upload_dir.$old_name);
		if (!file_exists($upload_dir.$old_name)) {
			$wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE id = {$fid};");
			echo 'deleted';
		} else
			echo 'failed';
		die();
	} else {
		$fname = isset($_POST['fname']) ? sanitize_file_name(wp_unslash($_POST['fname'])) : '';
		if (!sb_is_allowed_file_name($fname)) {
			echo 'forbidden';
			die();
		}
		if (!file_exists($upload_dir.$fname) && rename($upload_dir.$old_name, $upload_dir.$fname)) {
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_stuff SET name = %s WHERE id = $fid;", $fname));
			echo 'renamed';
		} else
			echo 'failed';
		die();
	}
} elseif (isset($_POST['fetch'])) { // ajax pagination
	sb_ajax_require('publish_posts');
	wp_timezone_override_offset();
	$st = max(0, (int) $_POST['fetch'] - 1);
	$per_page = (int) sb_get_option('sermons_per_page');
	if (!empty($_POST['title'])) {
		// Titles saved by the Add Sermon page are stored with quotes escaped (God\'s), so match both forms
		$title = sanitize_text_field(wp_unslash($_POST['title']));
		$cond = $wpdb->prepare("and (m.title LIKE %s OR m.title LIKE %s) ", '%'.$wpdb->esc_like($title).'%', '%'.$wpdb->esc_like(addslashes($title)).'%');
	} else
		$cond = '';
	if (!empty($_POST['preacher'])) {
		$cond .= 'and m.preacher_id = ' . (int) $_POST['preacher'] . ' ';
	}
	if (!empty($_POST['series'])) {
		$cond .= 'and m.series_id = ' . (int) $_POST['series'] . ' ';
	}
	$m = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS m.id, m.title, m.datetime, p.name as pname, s.name as sname, ss.name as ssname
	FROM {$wpdb->prefix}sb_sermons as m
	LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id
	LEFT JOIN {$wpdb->prefix}sb_services as s ON m.service_id = s.id
	LEFT JOIN {$wpdb->prefix}sb_series as ss ON m.series_id = ss.id
	WHERE 1=1 {$cond}
	ORDER BY m.datetime desc, s.time desc LIMIT {$st}, {$per_page}");

	$cnt = $wpdb->get_var("SELECT FOUND_ROWS()");
	$i = 0;
	?>
	<?php foreach ($m as $sermon): ?>
		<tr class="<?php echo ++$i % 2 == 0 ? 'alternate' : '' ?>">
			<th style="text-align:center" scope="row"><?php echo (int) $sermon->id ?></th>
			<td><?php echo esc_html(stripslashes($sermon->title)) ?></td>
			<td><?php echo esc_html(stripslashes($sermon->pname)) ?></td>
			<td><?php echo ($sermon->datetime == '1970-01-01 00:00:00') ? __('Unknown', 'sermon-browser') : esc_html(date_i18n('d M y', strtotime($sermon->datetime))); ?></td>
			<td><?php echo esc_html(stripslashes($sermon->sname)) ?></td>
			<td><?php echo esc_html(stripslashes($sermon->ssname)) ?></td>
			<td><?php echo (int) sb_sermon_stats($sermon->id) ?></td>
			<td style="text-align:center">
				<a href="<?php echo esc_url(admin_url("admin.php?page=sermon-browser/new_sermon.php&mid={$sermon->id}")); ?>"><?php _e('Edit', 'sermon-browser') ?></a> | <a onclick="return confirm('Are you sure?')" href="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=sermon-browser/sermon.php&mid={$sermon->id}"), 'sermon_manage_sermons', 'sermon_manage_sermons_nonce')); ?>"><?php _e('Delete', 'sermon-browser'); ?></a> |
				<a href="<?php echo esc_url(sb_display_url().sb_query_char(false).'sermon_id='.(int) $sermon->id);?>">View</a>
			</td>
		</tr>
	<?php endforeach ?>
	<script type="text/javascript">
	<?php if($cnt < $per_page || $cnt <= $st + $per_page): ?>
		jQuery('#right').css('display','none');
	<?php elseif($cnt > $st + $per_page): ?>
		jQuery('#right').css('display','');
	<?php endif ?>
	</script>
	<?php
} elseif (isset($_POST['fetchU']) || isset($_POST['fetchL']) || isset($_POST['search'])) { // ajax pagination (uploads)
	sb_ajax_require('upload_files');
	require_once (SB_INCLUDES_DIR.'/admin.php');
	require (SB_INCLUDES_DIR.'/filetypes.php');
	$per_page = (int) sb_get_option('sermons_per_page');
	if (isset($_POST['fetchU'])) {
		$st = max(0, (int) $_POST['fetchU'] - 1);
		$abc = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id = 0 AND f.type = 'file' ORDER BY f.name LIMIT {$st}, {$per_page}");
	} elseif (isset($_POST['fetchL'])) {
		$st = max(0, (int) $_POST['fetchL'] - 1);
		$abc = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id <> 0 AND f.type = 'file' ORDER BY f.name LIMIT {$st}, {$per_page}");
	} else {
		$s = sanitize_text_field(wp_unslash($_POST['search']));
		$abc = $wpdb->get_results($wpdb->prepare("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.name LIKE %s AND f.type = 'file' ORDER BY f.name;", '%'.$wpdb->esc_like($s).'%'));
	}
	$id_prefix = isset($_POST['fetchU']) ? '' : 's';
	$i = 0;
?>
<?php if (count($abc) >= 1): ?>
	<?php foreach ($abc as $file): ?>
		<tr class="file <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="<?php echo $id_prefix ?>file<?php echo (int) $file->id ?>">
			<th style="text-align:center" scope="row"><?php echo (int) $file->id ?></th>
			<td id="<?php echo $id_prefix ?><?php echo (int) $file->id ?>"><?php echo esc_html(substr($file->name, 0, strrpos($file->name, '.'))) ?></td>
			<td style="text-align:center"><?php echo esc_html(sb_file_type_name($file->name, $filetypes)) ?></td>
			<?php if (!isset($_POST['fetchU'])) { ?><td><?php echo esc_html(stripslashes((string) $file->title)) ?></td><?php } ?>
			<td style="text-align:center">
				<?php if (isset($_POST['fetchU'])) { ?><a href="<?php echo esc_url(admin_url("admin.php?page=sermon-browser/new_sermon.php&getid3={$file->id}")); ?>"><?php _e('Create sermon', 'sermon-browser') ?></a> | <?php } ?>
				<?php sb_print_file_actions($file, $id_prefix) ?>
			</td>
		</tr>
	<?php endforeach ?>
<?php else: ?>
	<tr>
		<td><?php _e('No results', 'sermon-browser') ?></td>
	</tr>
<?php endif ?>
<?php
}
die();
