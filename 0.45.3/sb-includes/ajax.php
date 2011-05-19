<?php
global $sermon_domain;
define ('SB_AJAX', true);

// Throughout this plugin, p stands for preacher, s stands for service and ss stands for series
if (isset($_POST['pname'])) { // preacher
	$pname = mysql_real_escape_string($_POST['pname']);
	if (isset($_POST['pid'])) {
		$pid = (int) $_POST['pid'];
		if (isset($_POST['del'])) {
			$wpdb->query("DELETE FROM {$wpdb->prefix}sb_preachers WHERE id = $pid;");
		} else {
			$wpdb->query("UPDATE {$wpdb->prefix}sb_preachers SET name = '$pname' WHERE id = $pid;");
		}
		echo 'done';
		die();
	} else {
		$wpdb->query("INSERT INTO {$wpdb->prefix}sb_preachers VALUES (null, '$pname', '', '');");
		echo $wpdb->insert_id;
		die();
	}
} elseif (isset($_POST['sname'])) { // service
	$sname = mysql_real_escape_string($_POST['sname']);
	list($sname, $stime) = split('@', $sname);
	$sname = trim($sname);
	$stime = trim($stime);
	if (isset($_POST['sid'])) {
		$sid = (int) $_POST['sid'];
		if (isset($_POST['del'])) {
			$wpdb->query("DELETE FROM {$wpdb->prefix}sb_services WHERE id = $sid;");
		} else {
			$old_time = $wpdb->get_var("SELECT time FROM {$wpdb->prefix}sb_services WHERE id = $sid;");
			if (!$old_time)
				$old_time = '00:00';
			$difference = strtotime($stime) - strtotime($old_time);
			$wpdb->query("UPDATE {$wpdb->prefix}sb_services SET name = '$sname', time = '$stime' WHERE id = $sid;");
			$wpdb->query("UPDATE {$wpdb->prefix}sb_sermons SET datetime = DATE_ADD(datetime, INTERVAL {$difference} SECOND) WHERE override = 0 AND service_id = $sid;");
		}
		echo 'done';
		die();
	} else {
		$wpdb->query("INSERT INTO {$wpdb->prefix}sb_services VALUES (null, '$sname', '$stime');");
		echo $wpdb->insert_id;
		die();
	}
} elseif (isset($_POST['ssname'])) { // series
	$ssname = mysql_real_escape_string($_POST['ssname']);
	if (isset($_POST['ssid'])) {
		$ssid = (int) $_POST['ssid'];
		if (isset($_POST['del'])) {
			$wpdb->query("DELETE FROM {$wpdb->prefix}sb_series WHERE id = $ssid;");
		} else {
			$wpdb->query("UPDATE {$wpdb->prefix}sb_series SET name = '$ssname' WHERE id = $ssid;");
		}
		echo 'done';
		die();
	} else {
		$wpdb->query("INSERT INTO {$wpdb->prefix}sb_series VALUES (null, '$ssname', 0);");
		echo $wpdb->insert_id;
		die();
	}
} elseif (isset($_POST['fname'])) { // Files
	$fname = mysql_real_escape_string($_POST['fname']);
	if (isset($_POST['fid'])) {
		$fid = (int) $_POST['fid'];
		$oname = isset($_POST['oname']) ? mysql_real_escape_string($_POST['oname']) : '';
		if (isset($_POST['del'])) {
			if (!file_exists(SB_ABSPATH.sb_get_option('upload_dir').$fname) || unlink(SB_ABSPATH.sb_get_option('upload_dir').$fname)) {
				$wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE id = {$fid};");
				echo 'deleted';
				die();
			} else {
				echo 'failed';
				die();
			}
		} else {
			$oname = mysql_real_escape_string($_POST['oname']);
			if (IS_MU) {
				$file_allowed = FALSE;
				global $wp_version;
				if (version_compare ($wp_version, '3.0', '<'))
					require_once(SB_ABSPATH . 'wp-includes/wpmu-functions.php');
				if (function_exists('get_site_option')) {
					$allowed_extensions = explode(" ", get_site_option("upload_filetypes"));
					foreach ($allowed_extensions as $ext) {
						if (substr(strtolower($filename), -(strlen($ext)+1)) == ".".strtolower($ext))
							$file_allowed = TRUE;
					}
				}
			} else {
				$file_allowed = TRUE;
			}
			if ($file_allowed) {
				if (!is_writable(SB_ABSPATH.sb_get_option('upload_dir').$fname) && rename(SB_ABSPATH.sb_get_option('upload_dir').$oname, SB_ABSPATH.sb_get_option('upload_dir').$fname)) {
					$wpdb->query("UPDATE {$wpdb->prefix}sb_stuff SET name = '$fname' WHERE id = $fid;");
					echo 'renamed';
					die();
				} else {
					echo 'failed';
					die();
				}
			} else {
				echo 'forbidden';
				die();
			}
		}
	}
} elseif (isset($_POST['fetch'])) { // ajax pagination
	if (function_exists('wp_timezone_override_offset'))
		wp_timezone_override_offset();
	$st = (int) $_POST['fetch'] - 1;
	if (!empty($_POST['title'])) {
		$cond = "and m.title LIKE '%" . mysql_real_escape_string($_POST['title']) . "%' ";
	} else
		$cond = '';
	if ($_POST['preacher'] != 0) {
		$cond .= 'and m.preacher_id = ' . (int) $_POST['preacher'] . ' ';
	}
	if ($_POST['series'] != 0) {
		$cond .= 'and m.series_id = ' . (int) $_POST['series'] . ' ';
	}
	$m = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS m.id, m.title, m.datetime, p.name as pname, s.name as sname, ss.name as ssname
	FROM {$wpdb->prefix}sb_sermons as m
	LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id
	LEFT JOIN {$wpdb->prefix}sb_services as s ON m.service_id = s.id
	LEFT JOIN {$wpdb->prefix}sb_series as ss ON m.series_id = ss.id
	WHERE 1=1 {$cond}
	ORDER BY m.datetime desc, s.time desc LIMIT {$st}, ".sb_get_option('sermons_per_page'));

	$cnt = $wpdb->get_var("SELECT FOUND_ROWS()");
	?>
	<?php foreach ($m as $sermon): ?>
		<tr class="<?php echo ++$i % 2 == 0 ? 'alternate' : '' ?>">
			<th style="text-align:center" scope="row"><?php echo $sermon->id ?></th>
			<td><?php echo stripslashes($sermon->title) ?></td>
			<td><?php echo stripslashes($sermon->pname) ?></td>
			<td><?php echo ($sermon->datetime == '1970-01-01 00:00:00') ? __('Unknown', $sermon_domain) : strftime('%d %b %y', strtotime($sermon->datetime)); ?></td>
			<td><?php echo stripslashes($sermon->sname) ?></td>
			<td><?php echo stripslashes($sermon->ssname) ?></td>
			<td><?php echo sb_sermon_stats($sermon->id) ?></td>
			<td style="text-align:center">
				<?php //Security check
						if (current_user_can('edit_posts')) { ?>
						<a href="<?php echo admin_url("admin.php?page=sermon-browser/new_sermon.php&mid={$sermon->id}"); ?>"><?php _e('Edit', $sermon_domain) ?></a> | <a onclick="return confirm('Are you sure?')" href="<?php echo admin_url("admin.php?page=sermon-browser/sermon.php&mid={$sermon->id}"); ?>"><?php _e('Delete', $sermon_domain); ?></a> |
				<?php } ?>
				<a href="<?php echo sb_display_url().sb_query_char(true).'sermon_id='.$sermon->id;?>">View</a>
			</td>
		</tr>
	<?php endforeach ?>
	<script type="text/javascript">
	<?php if($cnt<sb_get_option('sermons_per_page') || $cnt <= $st+sb_get_option('sermons_per_page')): ?>
		jQuery('#right').css('display','none');
	<?php elseif($cnt > $st+sb_get_option('sermons_per_page')): ?>
		jQuery('#right').css('display','');
	<?php endif ?>
	</script>
	<?php
} elseif (isset($_POST['fetchU']) || isset($_POST['fetchL']) || isset($_POST['search'])) { // ajax pagination (uploads)
	if (isset($_POST['fetchU'])) {
		$st = (int) $_POST['fetchU'] - 1;
		$abc = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id = 0 AND f.type = 'file' ORDER BY f.name LIMIT {$st}, ".sb_get_option('sermons_per_page'));
	} elseif (isset($_POST['fetchL'])) {
		$st = (int) $_POST['fetchL'] - 1;
		$abc = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id <> 0 AND f.type = 'file' ORDER BY f.name LIMIT {$st}, ".sb_get_option('sermons_per_page'));
	} else {
		$s = mysql_real_escape_string($_POST['search']);
		$abc = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.name LIKE '%{$s}%' AND f.type = 'file' ORDER BY f.name;");
	}
?>
<?php if (count($abc) >= 1): ?>
	<?php foreach ($abc as $file): ?>
		<tr class="file <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="<?php echo $_POST['fetchU'] ? '' : 's' ?>file<?php echo $file->id ?>">
			<th style="text-align:center" scope="row"><?php echo $file->id ?></th>
			<td id="<?php echo $_POST['fetchU'] ? '' : 's' ?><?php echo $file->id ?>"><?php echo substr($file->name, 0, strrpos($file->name, '.')) ?></td>
			<td style="text-align:center"><?php echo isset($filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name']) ? $filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name'] : strtoupper(substr($file->name, strrpos($file->name, '.') + 1)) ?></td>
			<?php if (!isset($_POST['fetchU'])) { ?><td><?php echo stripslashes($file->title) ?></td><?php } ?>
			<td style="text-align:center">
				<script type="text/javascript" language="javascript">
				function deletelinked_<?php echo $file->id;?>(filename, filesermon) {
					if (confirm('Do you really want to delete '+filename+'?')) {
						if (filesermon != '') {
							return confirm('This file is linked to the sermon called ['+filesermon+']. Are you sure you want to delete it?');
						}
						return true;
					}
					return false;
				}
				</script>
				<?php if (isset($_POST['fetchU'])) { ?><a id="" href="<?php echo admin_url("admin.php?page=sermon-browser/new_sermon.php&amp;getid3={$file->id}"); ?>"><?php _e('Create sermon', $sermon_domain) ?></a> | <?php } ?>
				<a id="link<?php echo $file->id ?>" href="javascript:rename(<?php echo $file->id ?>, '<?php echo $file->name ?>')"><?php _e('Rename', $sermon_domain) ?></a> | <a onclick="return deletelinked_<?php echo $file->id;?>('<?php echo str_replace("'", '', $file->name) ?>', '<?php echo str_replace("'", '', $file->title) ?>');" href="javascript:kill(<?php echo $file->id ?>, '<?php echo $file->name ?>');"><?php _e('Delete', $sermon_domain) ?></a>
			</td>
		</tr>
	<?php endforeach ?>
<?php else: ?>
	<tr>
		<td><?php _e('No results', $sermon_domain) ?></td>
	</tr>
<?php endif ?>
<?php
}
die();

?>