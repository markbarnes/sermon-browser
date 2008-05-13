<?php 
/*
Plugin Name: Sermon Browser
Plugin URI: http://www.4-14.org.uk/category/sermonbrowser
Description: Add sermons to your Wordpress blog. Coding by <a href="http://codeandmore.com/">Tien Do Xuan</a>. Design 
Author: Mark Barnes
Version: 0.1
Author URI: http://www.4-14.org.uk/

This work is licenced under the Creative Commons Attribution 2.0 UK: England & Wales License.
To view a copy of this licence, visit http://creativecommons.org/licenses/by/2.0/uk/ or send a
letter to Creative Commons, 171 Second Street, Suite 300, San Francisco, California 94105, USA.

*/

$sermon_domain = 'tdxsm';
$sermon_is_setup = 0;

require_once('dictionary.php');

function sermon_setup()
{
   global $sermon_domain, $sermon_is_setup;

   if($sermon_is_setup) {
      return;
   } 

   load_plugin_textdomain($sermon_domain, 'wp-content/plugins/sermonbrowser');
}

include_once('filetypes.php');
$url = get_bloginfo('wpurl');
global $wordpressRealPath;
$wordpressRealPath = str_replace("\\", "/", dirname(dirname(dirname(dirname(__FILE__)))));
global $defaultSermonPath;
$defaultSermonPath = "/wp-content/plugins/sermonbrowser/files/";
$defaultSermonURL = get_bloginfo('url')."/wp-content/plugins/sermonbrowser/files/";

sermon_setup();

$books = array('Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy', 'Joshua', 'Judges', 'Ruth', '1 Samuel', '2 Samuel', '1 Kings', '2 Kings', '1 Chronicles', '2 Chronicles', 'Ezra', 'Nehemiah', 'Esther', 'Job', 'Psalm', 'Proverbs', 'Ecclesiastes', 'Song of Solomon', 'Isaiah', 'Jeremiah', 'Lamentations', 'Ezekiel', 'Daniel', 'Hosea', 'Joel', 'Amos', 'Obadiah', 'Jonah', 'Micah', 'Nahum', 'Habakkuk', 'Zephaniah', 'Haggai', 'Zechariah', 'Malachi', 'Matthew', 'Mark', 'Luke', 'John', 'Acts', 'Romans', '1 Corinthians', '2 Corinthians', 'Galatians', 'Ephesians', 'Philippians', 'Colossians', '1 Thessalonians', '2 Thessalonians', '1 Timothy', '2 Timothy', 'Titus', 'Philemon', 'Hebrews', 'James', '1 Peter', '2 Peter', '1 John', '2 John', '3 John', 'Jude', 'Revelation');

include('frontend.php');

if ($_POST['sermon'] == 1) {	
	if ($_POST['pname']) {
		$pname = mysql_real_escape_string($_POST['pname']);
		if ($_POST['pid']) {
			$pid = (int) $_POST['pid'];
			if ($_POST['del']) {
				$wpdb->query("DELETE FROM {$wpdb->prefix}sb_preachers WHERE id = $pid;");
			} else {
				$wpdb->query("UPDATE {$wpdb->prefix}sb_preachers SET name = '$pname' WHERE id = $pid;");				
			}
			echo 'done';
		} else {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_preachers VALUES (null, '$pname');");
			echo $wpdb->insert_id;
		} 		
	} elseif ($_POST['sname']) {
		$sname = mysql_real_escape_string($_POST['sname']);
		list($sname, $stime) = split('@', $sname);
		$sname = trim($sname);
		$stime = trim($stime);
		if ($_POST['sid']) {
			$sid = (int) $_POST['sid'];
			if ($_POST['del']) {
				$wpdb->query("DELETE FROM {$wpdb->prefix}sb_services WHERE id = $sid;");
			} else {
				$wpdb->query("UPDATE {$wpdb->prefix}sb_services SET name = '$sname', time = '$stime' WHERE id = $sid;");
				$wpdb->query("UPDATE {$wpdb->prefix}sb_sermons SET time = '$stime' WHERE override = 0 AND service_id = $sid;");
			}			
			echo 'done';
		} else {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_services VALUES (null, '$sname', '$stime');");
			echo $wpdb->insert_id;
		}		
	} elseif ($_POST['ssname']) {
		$ssname = mysql_real_escape_string($_POST['ssname']);
		if ($_POST['ssid']) {
			$ssid = (int) $_POST['ssid'];
			if ($_POST['del']) {
				$wpdb->query("DELETE FROM {$wpdb->prefix}sb_series WHERE id = $ssid;");
			} else {
				$wpdb->query("UPDATE {$wpdb->prefix}sb_series SET name = '$ssname' WHERE id = $ssid;");
			}				
			echo 'done';
		} else {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_series VALUES (null, '$ssname');");
			echo $wpdb->insert_id;
		}
	} elseif ($_POST['fname']) {		
		$fname = mysql_real_escape_string($_POST['fname']);
		if ($_POST['fid']) {
			$fid = (int) $_POST['fid'];
			$oname = mysql_real_escape_string($_POST['oname']);			
			if ($_POST['del']) {
				if (unlink($wordpressRealPath.get_option('sb_sermon_upload_dir').$fname)) {
					$wpdb->query("DELETE FROM {$wpdb->prefix}sb_sermon_files WHERE id = $fid;");
					echo 'deleted';
				} else {
					echo 'failed';
				}				
			} else {
				
				$oname = mysql_real_escape_string($_POST['oname']);	
				// QAD			
				if (rename($wordpressRealPath.get_option('sb_sermon_upload_dir').$oname, $wordpressRealPath.get_option('sb_sermon_upload_dir').$fname)) {
					$wpdb->query("UPDATE {$wpdb->prefix}sb_sermon_files SET name = '$fname' WHERE id = $fid;");
					echo 'renamed';
				} else {		
					echo 'failed';				
				}
			}				
		}
	} elseif ($_POST['fetch']) {		
		$st = (int) $_POST['fetch'] - 1;
		if (!empty($_POST['title'])) {
			$cond = "and m.title LIKE '%" . mysql_real_escape_string($_POST['title']) . "%' ";
		}
		if ($_POST['preacher'] != 0) {
			$cond .= 'and m.preacher_id = ' . (int) $_POST['preacher'] . ' ';
		}
		if ($_POST['series'] != 0) {
			$cond .= 'and m.series_id = ' . (int) $_POST['series'] . ' ';
		}
		$m = $wpdb->get_results("SELECT m.id, m.title, m.date, p.name as pname, s.name as sname, ss.name as ssname FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id $cond ORDER BY m.title asc LIMIT $st, 15;");	
?>
		<?php foreach ($m as $sermon): ?>					
			<tr class="<?php echo ++$i % 2 == 0 ? 'alternate' : '' ?>">
				<th style="text-align:center" scope="row"><?php echo $sermon->id ?></th>
				<td><?php echo $sermon->title ?></td>
				<td><?php echo $sermon->pname ?></td>
				<td><?php echo $sermon->date ?></td>
				<td><?php echo $sermon->sname ?></td>
				<td><?php echo $sermon->ssname ?></td>
				<td style="text-align:center">
					<a href="<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/new_sermon.php&mid=<?php echo $sermon->id ?>"><?php _e('Edit', $sermon_domain) ?></a> | <a onclick="return confirm('Are you sure?')" href="<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php&mid=<?php echo $sermon->id ?>"><?php _e('Delete', $sermon_domain) ?></a>
				</td>
			</tr>
		<?php endforeach ?>
<?php
	} elseif ($_POST['fetchU'] || $_POST['fetchL'] || $_POST['search']) {
		if ($_POST['fetchU']) {
			$st = (int) $_POST['fetchU'] - 1;
			$abc = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_sermon_files AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id = 0 ORDER BY f.name LIMIT $st, 15;");
		} elseif ($_POST['fetchL']) {
			$st = (int) $_POST['fetchL'] - 1;
			$abc = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_sermon_files AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id <> 0 ORDER BY f.name LIMIT $st, 15;");
		} else {
			$s = mysql_real_escape_string($_POST['search']);
			$abc = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_sermon_files AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.name LIKE '%{$s}%' ORDER BY f.name;");
		}		
?>
	<?php if (count($abc) >= 1): ?>
		<?php foreach ($abc as $file): ?>
			<tr class="file <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="sfile<?php echo $file->id ?>">
				<th style="text-align:center" scope="row"><?php echo $file->id ?></th>
				<td id="s<?php echo $file->id ?>"><?php echo substr($file->name, 0, strrpos($file->name, '.')) ?></td>
				<td style="text-align:center"><?php echo isset($filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name']) ? $filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name'] : strtoupper(substr($file->name, strrpos($file->name, '.') + 1)) ?></td>
				<td><?php echo $file->title ?></td>
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
}

add_action('admin_menu', 'bb_add_pages');
register_activation_hook(__FILE__,'sermon_install');

function sermon_install () {
   global $wpdb, $mdict, $sdict;	
   global $sermon_domain;
   global $sermon_db_version;
   global $wordpressRealPath;
   global $defaultSermonPath, $defaultSermonURL;
   require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
   	
   $table_name = $wpdb->prefix . "sb_preachers";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {            
	  $sql = "CREATE TABLE " . $table_name . " (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
		`name` VARCHAR( 30 ) NOT NULL ,
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
	  $sql = "INSERT INTO " . $table_name . "(name) VALUES ( 'C H Spurgeon' );";
      dbDelta($sql);
	  $sql = "INSERT INTO " . $table_name . "(name) VALUES ( 'Martyn Lloyd-Jones' );";
      dbDelta($sql);
   }
   
   $table_name = $wpdb->prefix . "sb_series";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
	  $sql = "CREATE TABLE " . $table_name . " (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
		`name` VARCHAR( 255 ) NOT NULL ,
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
	  $sql = "INSERT INTO " . $table_name . "(name) VALUES ( 'Exposition of the Psalms' );";
      dbDelta($sql);
	  $sql = "INSERT INTO " . $table_name . "(name) VALUES ( 'Exposition of Romans' );";
      dbDelta($sql);
   }
   
   $table_name = $wpdb->prefix . "sb_services";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
	  $sql = "CREATE TABLE " . $table_name . " (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
		`name` VARCHAR( 255 ) NOT NULL ,
		`time` VARCHAR( 5 ) NOT NULL , 
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
	  $sql = "INSERT INTO " . $table_name . "(name, time) VALUES ( 'Sunday Morning', '10:30' );";
      dbDelta($sql);
      $sql = "INSERT INTO " . $table_name . "(name, time) VALUES ( 'Sunday Evening', '18:00' );";
      dbDelta($sql);
      $sql = "INSERT INTO " . $table_name . "(name, time) VALUES ( 'Midweek Meeting', '19:00' );";
      dbDelta($sql);
      $sql = "INSERT INTO " . $table_name . "(name, time) VALUES ( 'Special event', '20:00' );";
      dbDelta($sql);
   }
   
   $table_name = $wpdb->prefix . "sb_sermons";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
	  $sql = "CREATE TABLE " . $table_name . " (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
		`title` VARCHAR( 255 ) NOT NULL ,
		`preacher_id` INT( 10 ) NOT NULL ,
		`date` DATE NOT NULL ,
		`service_id` INT( 10 ) NOT NULL ,
		`series_id` INT( 10 ) NOT NULL ,
		`start` TEXT NOT NULL ,
		`end` TEXT NOT NULL ,
		`description` TEXT ,
		`time` VARCHAR ( 5 ), 
		`override` TINYINT ( 1 ) ,	
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
   }
   
   $table_name = $wpdb->prefix . "sb_sermon_files";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
	  $sql = "CREATE TABLE " . $table_name . " (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
		`name` VARCHAR( 255 ) NOT NULL ,
		`sermon_id` INT( 10 ) NOT NULL ,
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
   }
   $welcome_name = __('Delete', $sermon_domain);
   $welcome_text = __('Congratulations, you just completed the installation!', $sermon_domain);
   
   $sermon_db_version = "1.0";
   add_option('sb_sermon_db_version', $sermon_db_version);
   $sermonUploadDir = $defaultSermonPath;	
   add_option('sb_sermon_upload_dir', $sermonUploadDir);
   add_option('sb_sermon_upload_url', $defaultSermonURL);
   $fooz = '<div class="sermon-browser">
	<h2>Filters</h2>		
	[filters_form]
	<h2>Sermons ([sermons_count])</h2>   	
	<table class="sermons">
	[sermons_loop]	
		<tr>
			<td class="sermon-title">[sermon_title]</td>
		</tr>
		<tr>
			<td class="sermon-passage">[first_bible_passage] (Part of the [series_link] series).</td>
		</tr>
		<tr>
			<td class="files">[files_loop][file][/files_loop]</td>
		</tr>
		<tr>
			<td class="preacher">Preached by [preacher_link] on [date] ([service_link]).</td>
		</tr>
   	[/sermons_loop]
	</table>
   	[next_page]

   	[previous_page]

</div>';
	$barz = <<<HERE
<div class="sermon-browser-results">
	<h2>View single sermon</h2>
	Title: [sermon_title]<br />
	Preacher: [preacher_link]<br />
	Series: [series_link]<br />
	Service: [service_link]<br />
	Date: [date]<br />
	[passages_loop]	
		[start_passage] - [end_passage]<br />
	[/passages_loop]
	Files:<br />
	[files_loop]	
		[file]
	[/files_loop]
	[next_sermon]
	[prev_sermon]
	Same day: [sameday_sermon]
</div>		
HERE;
	delete_option('sb_sermon_multi_form');
   	add_option('sb_sermon_multi_form', base64_encode($fooz));
	delete_option('sb_sermon_single_form');
   	add_option('sb_sermon_single_form', base64_encode($barz));
   
   //Try to create the folder if not exist
   if (!is_dir($wordpressRealPath.$sermonUploadDir)) {
      //Create that folder
      if (@mkdir($wordpressRealPath.$sermonUploadDir)) {
         //try CHMOD it to 777
         @chmod($wordpressRealPath.$sermonUploadDir, 0777);          
      }
   }
/*
   	$fh = fopen($wordpressRealPath.'/wp-content/plugins/sermonbrowser/multi.php', 'w');
   	if ($fh) {
   		fwrite($fh, strtr($fooz, $mdict));
   		fclose($fh);
		//@chmod($wordpressRealPath.'/wp-content/plugins/sermonbrowser/multi.php', 0777);
	}
   	$fh = fopen($wordpressRealPath.'/wp-content/plugins/sermonbrowser/single.php', 'w');
	if ($fh) {
	   	fwrite($fh, strtr($barz, $sdict));
   		fclose($fh);
		//@chmod($wordpressRealPath.'/wp-content/plugins/sermonbrowser/single.php', 0777);
	}
*/
}

function bb_add_pages() {
	global $sermon_domain;
	add_menu_page(__('Sermons', $sermon_domain), __('Sermons', $sermon_domain), 8, __FILE__, 'bb_manage_sermons');
	add_submenu_page(__FILE__, __('Sermons', $sermon_domain), __('Sermons', $sermon_domain), 8, __FILE__, 'bb_manage_sermons');
	add_submenu_page(__FILE__, __('New Sermon', $sermon_domain), __('New Sermon', $sermon_domain), 8, 'sermonbrowser/new_sermon.php', 'bb_new_sermon');
	add_submenu_page(__FILE__, __('Manage', $sermon_domain), __('Manage', $sermon_domain), 8, 'sermonbrowser/manage.php', 'bb_manage_everything');
	add_submenu_page(__FILE__, __('Uploads', $sermon_domain), __('Uploads', $sermon_domain), 8, 'sermonbrowser/uploads.php', 'bb_uploads');
	add_submenu_page(__FILE__, __('Options', $sermon_domain), __('Options', $sermon_domain), 8, 'sermonbrowser/options.php', 'bb_options');
	add_submenu_page(__FILE__, __('Help', $sermon_domain), __('Help', $sermon_domain), 8, 'sermonbrowser/help.php', 'bb_help');
}

function bb_build_textarea($name, $html) {
	$out = '<textarea name="'.$name.'" cols="50" rows="5">';
	$out .= stripslashes(str_replace('\r\n', "\n", base64_decode($html))); 
	$out .= '</textarea>';
	echo $out;
}

function bb_options() {
	global $wpdb, $sermon_domain, $mdict, $sdict;
	global $wordpressRealPath;
	global $defaultSermonPath, $defaultSermonURL;
	if ($_POST['resetdefault']) {
		$dir = $defaultSermonPath;
		update_option('sb_sermon_upload_dir', $defaultSermonPath);
		update_option('sb_sermon_upload_url', $defaultSermonURL);
	   	if (!is_dir($wordpressRealPath.$dir)) {
	      //Create that folder
	      if (@mkdir($wordpressRealPath.$dir)) {
	         //try CHMOD it to 777
	         @chmod($wordpressRealPath.$dir, 0777); 
	      }
	   	}	   	   	
	   	$checkSermonUpload = checkSermonUploadable();
	   	switch ($checkSermonUpload) {
	   	case "unwriteable":
			echo '<div id="message" class="updated fade"><p><b>';
			_e('Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777.', $sermon_domain);
			echo '</b></div>';
			break;
		case "notexist":
			echo '<div id="message" class="updated fade"><p><b>';
			_e('Error: The upload folder you have specified does not exist.', $sermon_domain);
			echo '</b></div>';
			break;
		default: 
			echo '<div id="message" class="updated fade"><p><b>';
			_e('Default loaded properly.', $sermon_domain);
			echo '</b></div>';
			break;
	   }
	}
	if ($_POST['save']) {
		$dir = rtrim(str_replace("\\", "/", $_POST['dir']), "/")."/";
		update_option('sb_sermon_upload_dir', $dir);
		update_option('sb_sermon_upload_url', get_bloginfo('url').$dir);		
	   	if (!is_dir($wordpressRealPath.$dir)) {
	      //Create that folder
	      if (@mkdir($wordpressRealPath.$dir)) {
	         //try CHMOD it to 777
	         @chmod($wordpressRealPath.$dir, 0777); 
	      }
	   	}	   	   	
	   	$checkSermonUpload = checkSermonUploadable();
	   	switch ($checkSermonUpload) {
	   	case "unwriteable":
			echo '<div id="message" class="updated fade"><p><b>';
			_e('Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777.', $sermon_domain);
			echo '</b></div>';
			break;
		case "notexist":
			echo '<div id="message" class="updated fade"><p><b>';
			_e('Error: The upload folder you have specified does not exist.', $sermon_domain);
			echo '</b></div>';
			break;
		default: 
			echo '<div id="message" class="updated fade"><p><b>';
			_e('Options saved properly.', $sermon_domain);
			echo '</b></div>';
			break;
	   }
	}
	if ($_POST['save2']) {
		$multi = $_POST['multi'];
		$single = $_POST['single'];
		//*
		update_option('sb_sermon_multi_form', base64_encode($multi));
		update_option('sb_sermon_single_form', base64_encode($single));
		//*/
	   	$checkSermonUpload = checkSermonUploadable();
	   	switch ($checkSermonUpload) {
	   	case "unwriteable":
			echo '<div id="message" class="updated fade"><p><b>';
			_e('Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777.', $sermon_domain);
			echo '</b></div>';
			break;
		case "notexist":
			echo '<div id="message" class="updated fade"><p><b>';
			_e('Error: The upload folder you have specified does not exist.', $sermon_domain);
			echo '</b></div>';
			break;
		default: 
		//*			
			$fh = @fopen($wordpressRealPath.'/wp-content/plugins/sermonbrowser/multi.php', 'w');
			if ($fh) {
				fwrite($fh, strtr(stripslashes($multi), $mdict));
				fclose($fh);
			} else {
				echo '<div id="message" class="updated fade"><p><b>';
				_e('Could not save multi template. Please check permission for multi.php in plugin folder', $sermon_domain);
				echo '</b></div>';			
			}
			$fh = @fopen($wordpressRealPath.'/wp-content/plugins/sermonbrowser/single.php', 'w');
			if ($fh) {
				fwrite($fh, strtr(stripslashes($single), $sdict));
				fclose($fh);
			} else {
				echo '<div id="message" class="updated fade"><p><b>';
				_e('Could not save single template. Please check permission for single.php in plugin folder', $sermon_domain);
				echo '</b></div>';			
			}
		//*/	
			echo '<div id="message" class="updated fade"><p><b>';
			_e('Options saved properly.', $sermon_domain);
			echo '</b></div>';		
			break;
	   }
	}
	if ($_POST['uninstall']) {
		if ($_POST['wipe'] == 1) {
			$dir = $wordpressRealPath.get_option('sb_sermon_upload_dir');
			if ($dh = @opendir($dir)) {
				while (false !== ($file = readdir($dh))) {
					if ($file != "." && $file != "..") {	    		
						@unlink($dir.($file));
					}	
				}
				closedir($dh);
			}
			//rmdir($wordpressRealPath.get_option('sb_sermon_upload_dir'));
		}
		$table_name = $wpdb->prefix."sb_preachers";
		if ($wpdb->get_var("show tables like '$table_name'") == $table_name) $wpdb->query("DROP TABLE $table_name");
		$table_name = $wpdb->prefix."sb_series";
		if ($wpdb->get_var("show tables like '$table_name'") == $table_name) $wpdb->query("DROP TABLE $table_name");
		$table_name = $wpdb->prefix."sb_services";
		if ($wpdb->get_var("show tables like '$table_name'") == $table_name) $wpdb->query("DROP TABLE $table_name");
		$table_name = $wpdb->prefix."sb_sermons";
		if ($wpdb->get_var("show tables like '$table_name'") == $table_name) $wpdb->query("DROP TABLE $table_name");
		$table_name = $wpdb->prefix."sb_sermon_files";
		if ($wpdb->get_var("show tables like '$table_name'") == $table_name) $wpdb->query("DROP TABLE $table_name");
		delete_option('sb_sermon_upload_dir');
		delete_option('sb_sermon_upload_url');
		delete_option('sb_sermon_single_form');
		delete_option('sb_sermon_multi_form');
		
		echo '<div id="message" class="updated fade"><p><b>'.__('Uninstall completed. Please deactivate the plugin', $sermon_domain).'</b></div>';
	}
?>
	<form method="post">
	<div class="wrap">
		<h2><?php _e('Options', $sermon_domain) ?></h2>
		<table border="0" class="widefat">
			<tr>
				<td align="right"><?php _e('Upload directory', $sermon_domain) ?>: </td>
				<td><input type="text" name="dir" value="<?php echo get_option('sb_sermon_upload_dir') ?>" style="width:300px"></td>
			</tr>
		</table>		
		<p class="submit"><input type="submit" name="resetdefault" value="<?php _e('Reset to defaults', $sermon_domain) ?>"  />&nbsp;<input type="submit" name="save" value="<?php _e('Save', $sermon_domain) ?> &raquo;" /></p> 
	</div>	
	<div class="wrap">
		<h2><?php _e('Templates', $sermon_domain) ?></h2>
		<table border="0" class="widefat">
			<tr>
				<td align="right"><?php _e('Multi-sermons form', $sermon_domain) ?>: </td>
				<td>
					<?php bb_build_textarea('multi', get_option('sb_sermon_multi_form')) ?>
				</td>
			</tr>
			<tr>
				<td align="right"><?php _e('Single sermon form', $sermon_domain) ?>: </td>
				<td>
					<?php bb_build_textarea('single', get_option('sb_sermon_single_form')) ?>
				</td>
			</tr>			
		</table>				
		<p class="submit"><input type="submit" name="save2" value="<?php _e('Save', $sermon_domain) ?> &raquo;" /></p> 
	</div>		
	<div class="wrap">
		<h2><?php _e('Uninstall', $sermon_domain) ?></h2>
		<table border="0" class="widefat">			
			<tr>
				<td><input type="checkbox" name="wipe" value="1"> <?php _e('Remove all files', $sermon_domain) ?></td>
			</tr>
		</table>
		<p class="submit"><input type="submit" name="uninstall" value="<?php _e('Uninstall', $sermon_domain) ?>" onclick="return confirm('Are you sure?')" /></p> 
	</div>
	</form>
<?php 
}

function bb_help() {
?>	
	<div class="wrap">
		<h2><?php _e('Help page', $sermon_domain) ?></h2>
		<table border="0" class="widefat">
			<tr>
				<td>Just help page</td>
			</tr>
		</table>
	</div>
	</form>
<?php 
}

function bb_manage_everything() {
	global $wpdb, $sermon_domain;
	
	$url = get_bloginfo('wpurl');
	
	$preachers = $wpdb->get_results("SELECT p.*, m.id AS mid FROM {$wpdb->prefix}sb_preachers AS p LEFT OUTER JOIN {$wpdb->prefix}sb_sermons AS m ON p.id = m.preacher_id ORDER BY p.name asc");
	$series = $wpdb->get_results("SELECT ss.*, m.id AS mid FROM {$wpdb->prefix}sb_series AS ss LEFT OUTER JOIN {$wpdb->prefix}sb_sermons AS m ON ss.id = m.series_id ORDER BY ss.name asc");	
	$services = $wpdb->get_results("SELECT s.*, m.id AS mid FROM {$wpdb->prefix}sb_services AS s LEFT OUTER JOIN {$wpdb->prefix}sb_sermons AS m ON s.id = m.service_id ORDER BY s.name asc");
	
	$toManage = array(
		'Preachers' => array('data' => $preachers),
		'Series' => array('data' => $series),
		'Services' => array('data' => $services),
	);
?>
	<style>
		#supersub {
			border-bottom: 1px solid #ccc;
			border-top: 1px solid #ccc;
			background: #fffeeb;
			line-height: 29px;
			font-size: 12px;
			color: #555;
			text-align: center;
		} 	
		#supersub a {
			font-size: 1.1em;
		}
		#supersub a:link {
			color: #036;
		}
	</style>
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/jquery/jquery.js"></script>
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/fat.js"></script>
	<script type="text/javascript">
		//<![CDATA[
		function updateClass(type) {
			jQuery('.' + type + ':visible').each(function(i) {
				jQuery(this).removeClass('alternate');
				if (++i % 2 == 0) {
					jQuery(this).addClass('alternate');
				}
			});
		}
		function createNewPreachers(s) {
			var p = prompt("New preacher's name?", "Preacher's name");
			if (p != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php', {pname: p, sermon: 1}, function(r) {
					if (r) {
						jQuery('#Preachers-list').append('\
							<tr style="display:none" class="Preachers" id="rowPreachers' + r + '">\
								<th style="text-align:center" scope="row">' + r + '</th>\
								<td id="Preachers' + r + '">' + p + '</td>\
								<td style="text-align:center">\
									<a id="linkPreachers' + r + '" href="javascript:renamePreachers(' + r + ', \'' + p + '\')">Rename</a> | <a onclick="return confirm(\'Are you sure?\');" href="javascript:deletePreachers(' + r + ')">Delete</a>\
								</td>\
							</tr>\
						');
						jQuery('#rowPreachers' + r).fadeIn(function() {
							updateClass('Preachers');
						});
					};
				});	
			}
		}
		function createNewServices(s) {
			var s = 'lol';
			while ((s.indexOf('@') == -1) || (s.match(/(.*?)@(.*)/)[2].match(/[0-9]{1,2}:[0-9]{1,2}/) == null)) {
				s = prompt("New service's name - default time?", "Service's name @ 18:00");							
				if (s == null) { break;	}
			}
			if (s != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php', {sname: s, sermon: 1}, function(r) {				
					if (r) {
						sz = s.match(/(.*?)@(.*)/)[1];
						t = s.match(/(.*?)@(.*)/)[2];
						jQuery('#Services-list').append('\
							<tr style="display:none" class="Services" id="rowServices' + r + '">\
								<th style="text-align:center" scope="row">' + r + '</th>\
								<td id="Services' + r + '">' + sz + '</td>\
								<td style="text-align:center">' + t + '</td>\
								<td style="text-align:center">\
									<a id="linkServices' + r + '" href="javascript:renameServices(' + r + ', \'' + sz + '\')">Edit</a> | <a onclick="return confirm(\'Are you sure?\');" href="javascript:deleteServices(' + r + ')">Delete</a>\
								</td>\
							</tr>\
						');
						jQuery('#rowServices' + r).fadeIn(function() {
							updateClass('Services');
						});
					};
				});	
			}
		}
		function createNewSeries(s) {
			var ss = prompt("New series' name?", "Series' name");
			if (ss != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php', {ssname: ss, sermon: 1}, function(r) {
					if (r) {
						jQuery('#Series-list').append('\
							<tr style="display:none" class="Series" id="rowSeries' + r + '">\
								<th style="text-align:center" scope="row">' + r + '</th>\
								<td id="Series' + r + '">' + ss + '</td>\
								<td style="text-align:center">\
									<a id="linkSeries' + r + '" href="javascript:renameSeries(' + r + ', \'' + ss + '\')">Rename</a> | <a onclick="return confirm(\'Are you sure?\');" href="javascript:deleteSeries(' + r + ')">Delete</a>\
								</td>\
							</tr>\
						');
						jQuery('#rowSeries' + r).fadeIn(function() {
							updateClass('Series');
						});
					};
				});	
			}
		}
		function deletePreachers(id) {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php', {pname: 'dummy', pid: id, del: 1, sermon: 1}, function(r) {
				if (r) {
					jQuery('#rowPreachers' + id).fadeOut(function() {
						updateClass('Preachers');
					});
				};
			});
		}
		function deleteSeries(id) {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php', {ssname: 'dummy', ssid: id, del: 1, sermon: 1}, function(r) {
				if (r) {
					jQuery('#rowSeries' + id).fadeOut(function() {
						updateClass('Series');
					});
				};
			});			
		}
		function deleteServices(id) {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php', {sname: 'dummy', sid: id, del: 1, sermon: 1}, function(r) {
				if (r) {
					jQuery('#rowServices' + id).fadeOut(function() {
						updateClass('Services');
					});
				};
			});			
		}
		function renamePreachers(id, old) {
			var p = prompt("New preacher's name?", old);
			if (p != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php', {pid: id, pname: p, sermon: 1}, function(r) {
					if (r) {
						jQuery('#Preachers' + id).text(p);
						jQuery('#linkPreachers' + id).attr('href', 'javascript:renamePreachers(' + id + ', "' + p + '")');
						Fat.fade_element('Preachers' + id);
					};
				});	
			}
		}
		function renameSeries(id, old) {
			var ss = prompt("New series' name?", old);
			if (ss != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php', {ssid: id, ssname: ss, sermon: 1}, function(r) {
					if (r) {
						jQuery('#Series' + id).text(ss);
						jQuery('#linkSeries' + id).attr('href', 'javascript:renameSeries(' + id + ', "' + ss + '")');
						Fat.fade_element('Series' + id);
					};
				});	
			}
		}
		function renameServices(id, old) {
			var s = 'lol';
			while ((s.indexOf('@') == -1) || (s.match(/(.*?)@(.*)/)[2].match(/[0-9]{1,2}:[0-9]{1,2}/) == null)) {
				s = prompt("New service's name - default time?", "Service's name @ 18:00");								
				if (s == null) { break;	}
			}
			if (s != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php', {sid: id, sname: s, sermon: 1}, function(r) {
					if (r) {
						sz = s.match(/(.*?)@(.*)/)[1];
						t = s.match(/(.*?)@(.*)/)[2];						
						jQuery('#Services' + id).text(sz);
						jQuery('#time' + id).text(t);
						jQuery('#linkServices' + id).attr('href', 'javascript:renameServices(' + id + ', "' + s + '")');
						Fat.fade_element('Services' + id);
						Fat.fade_element('time' + id);
					};
				});	
			}
		}
		//]]>
	</script>
	<a name="top"></a>
	<div id="supersub">
		<a href="#manage-Preachers"><?php _e('Manage Preachers', $sermon_domain) ?></a>
		<a href="#manage-Series"><?php _e('Manage Series', $sermon_domain) ?></a>
		<a href="#manage-Services"><?php _e('Manage Services', $sermon_domain) ?></a>
	</div>
<?php
	foreach ($toManage as $k => $v) {
		$i = 0;
?>
	<a name="manage-<?php echo $k ?>"></a>
	<div class="wrap">
		<h2><?php echo $k ?> (<a href="javascript:createNew<?php echo $k ?>()"><?php _e('add new', $sermon_domain) ?></a>)</h2> 
		<br style="clear:both">
		<table class="widefat">
			<thead>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Name', $sermon_domain) ?></div></th>
				<?php echo $k == 'Services' ? '<th scope="col"><div style="text-align:center">'.__('Default time', $sermon_domain).'</div></th>' : '' ?>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</thead>	
			<tbody id="<?php echo $k ?>-list">
				<?php if (is_array($v['data'])): ?>
					<?php $cheat = array() ?>
					<?php foreach ($v['data'] as $item): ?>
					<?php if (!in_array($item->id, $cheat)): ?>
						<?php $cheat[] = $item->id ?>
						<tr class="<?php echo $k ?> <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="row<?php echo $k ?><?php echo $item->id ?>">
							<th style="text-align:center" scope="row"><?php echo $item->id ?></th>
							<td id="<?php echo $k ?><?php echo $item->id ?>"><?php echo stripslashes($item->name) ?></td>
							<?php echo $k == 'Services' ? '<td style="text-align:center" id="time'.$item->id.'">'.$item->time.'</td>' : '' ?>
							<td style="text-align:center">
								<a id="link<?php echo $k ?><?php echo $item->id ?>" href="javascript:rename<?php echo $k ?>(<?php echo $item->id ?>, '<?php echo $item->name ?><?php echo $k == 'Services' ? ' @ '.$item->time : '' ?>')"><?php echo $k == 'Services' ? __('Edit', $sermon_domain) : __('Rename', $sermon_domain) ?></a> <?php if ($item->mid == ""): ?>| <a onclick="return confirm('Are you sure?');" href="javascript:delete<?php echo $k ?>(<?php echo $item->id ?>)"><?php _e('Delete', $sermon_domain) ?></a><?php else: ?> | <a href="javascript:alert('<?php switch ($k) { 
									case "Services": 
										_e('Some sermons are currently assigned to that service. You can only delete services that are not used in the database.', $sermon_domain); 
										break; 
									case "Series": 
										_e('Some sermons are currently in that series. You can only delete series that are empty.', $sermon_domain); 
										break; 
									case "Preachers": 
										_e('That preacher has sermons in the database. You can only delete preachers who have no sermons in the database.', $sermon_domain); 
										break;
									}?>')"><?php _e('Delete', $sermon_domain) ?></a><?php endif ?>
							</td>
						</tr>
					<?php endif ?>
					<?php endforeach ?>
				<?php endif ?>				
			</tbody>			
		</table>
		<br style="clear:both">
		<div style="text-align:right"><a href="#top">Top &dagger;</a></div>
	</div>	
<?php 
	}
}

function bb_uploads() {
	global $wpdb, $filetypes, $sermon_domain;
	global $wordpressRealPath;
	bb_scan_dir();
	
	$url = get_bloginfo('wpurl');
	
	if ($_POST['save']) {
		if ($_FILES['upload']['error'] == UPLOAD_ERR_OK) {
			$filename = basename($_FILES['upload']['name']);
			$prefix = '';
			$dest = $wordpressRealPath.get_option('sb_sermon_upload_dir').$prefix.$filename;
			if (move_uploaded_file($_FILES['upload']['tmp_name'], $dest)) {
				$filename = $prefix.mysql_real_escape_string($filename);
				$query = "INSERT INTO {$wpdb->prefix}sb_sermon_files VALUES (null, '$filename', 0);";
				$wpdb->query($query);
			}
		}
		echo '<div id="message" class="updated fade"><p><b>'.__('Files saved to database.', $sermon_domain).'</b></div>';
	}	
	
	$unlinked = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_sermon_files AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id = 0 ORDER BY f.name LIMIT 0, 15;");
	$linked = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_sermon_files AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id <> 0 ORDER BY f.name LIMIT 0, 15;");
	
	$cntu = $wpdb->get_row("SELECT COUNT(*) as cntu FROM {$wpdb->prefix}sb_sermon_files WHERE sermon_id = 0", ARRAY_A);
	$cntu = $cntu['cntu'];		
	$cntl = $wpdb->get_row("SELECT COUNT(*) as cnt1 FROM {$wpdb->prefix}sb_sermon_files WHERE sermon_id <> 0", ARRAY_A);
	$cntl = $cntl['cnt1'];		
?>
	<style>
		#supersub {
			border-bottom: 1px solid #ccc;
			border-top: 1px solid #ccc;
			background: #fffeeb;
			line-height: 29px;
			font-size: 12px;
			color: #555;
			text-align: center;
		} 	
		#supersub a {
			font-size: 1.1em;
		}
		#supersub a:link {
			color: #036;
		}
	</style>
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/jquery/jquery.js"></script>
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/fat.js"></script>
	<script>
		function rename(id, old) {
			var f = prompt("New file name?", old);
			if (f != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/uploads.php', {fid: id, oname: old, fname: f, sermon: 1}, function(r) {
					if (r) {
						if (r == 'renamed') {
							jQuery('#' + id).text(f.substring(0,f.lastIndexOf(".")));
							jQuery('#link' + id).attr('href', 'javascript:rename(' + id + ', "' + f + '")');
							Fat.fade_element(id);
							jQuery('#s' + id).text(f.substring(0,f.lastIndexOf(".")));
							jQuery('#slink' + id).attr('href', 'javascript:rename(' + id + ', "' + f + '")');
							Fat.fade_element('s' + id);
						} else {
							alert('The script is unable to rename your file.');
						}
					};
				});	
			}
		}
		function kill(id, f) {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/uploads.php', {fname: f, fid: id, del: 1, sermon: 1}, function(r) {
				if (r) {
					if (r == 'deleted') {
						jQuery('#file' + id).fadeOut(function() {
							jQuery('.file:visible').each(function(i) {
								jQuery(this).removeClass('alternate');
								if (++i % 2 == 0) {
									jQuery(this).addClass('alternate');
								}
							});
						});
						jQuery('#sfile' + id).fadeOut(function() {
							jQuery('.file:visible').each(function(i) {
								jQuery(this).removeClass('alternate');
								if (++i % 2 == 0) {
									jQuery(this).addClass('alternate');
								}
							});
						});
					} else {
						alert('The script is unable to delete your file.');
					}
				};
			});	
		}
		function fetchU(st) {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/uploads.php', {fetchU: st + 1, sermon: 1}, function(r) {
				if (r) {
					jQuery('#the-list-u').html(r);					
					if (st >= 15) {
						x = st - 15;
						jQuery('#uleft').html('<a href="javascript:fetchU(' + x + ')">&laquo; Previous</a>');
					} else {
						jQuery('#uleft').html('');
					}
					if (st + 15 <= <?php echo $cntu ?>) {
						y = st + 15;
						jQuery('#uright').html('<a href="javascript:fetchU(' + y + ')">Next &raquo;</a>');
					} else {
						jQuery('#uright').html('');
					}
				};
			});	
		}
		function fetchL(st) {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/uploads.php', {fetchL: st + 1, sermon: 1}, function(r) {
				if (r) {
					jQuery('#the-list-l').html(r);					
					if (st >= 15) {
						x = st - 15;
						jQuery('#left').html('<a href="javascript:fetchL(' + x + ')">&laquo; Previous</a>');
					} else {
						jQuery('#left').html('');
					}
					if (st + 15 <= <?php echo $cntl ?>) {
						y = st + 15;
						jQuery('#right').html('<a href="javascript:fetchL(' + y + ')">Next &raquo;</a>');
					} else {
						jQuery('#right').html('');
					}
				};
			});	
		}
		function findNow() {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/uploads.php', {search: jQuery('#search').val(), sermon: 1}, function(r) {
				if (r) {
					jQuery('#the-list-s').html(r);										
				};
			});	
		}
	</script>
	<a name="top"></a>
	<div id="supersub">
		<a href="#unlinked"><?php _e('Unlinked files', $sermon_domain) ?></a>
		<a href="#linked"><?php _e('Linked files', $sermon_domain) ?></a>
		<a href="#search"><?php _e('Search for files', $sermon_domain) ?></a>
	</div>
	<a name="unlinked"></a>
	<div class="wrap">
		<h2>Unlinked files</h2>
		<br style="clear:both">
		<table class="widefat">
			<thead>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File name', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File type', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</thead>	
			<tbody id="the-list-u">
				<?php if (is_array($unlinked)): ?>
					<?php foreach ($unlinked as $file): ?>								
						<tr class="file <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="file<?php echo $file->id ?>">
							<th style="text-align:center" scope="row"><?php echo $file->id ?></th>
							<td id="<?php echo $file->id ?>"><?php echo substr($file->name, 0, strrpos($file->name, '.')) ?></td>
							<td style="text-align:center"><?php echo isset($filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name']) ? $filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name'] : strtoupper(substr($file->name, strrpos($file->name, '.') + 1)) ?></td>
							<td style="text-align:center">
								<?php if (is_writable($wordpressRealPath.get_option('sb_sermon_upload_dir').$file->name)): ?>
								<a id="link<?php echo $file->id ?>" href="javascript:rename(<?php echo $file->id ?>, '<?php echo $file->name ?>')"><?php _e('Rename', $sermon_domain) ?></a> | <a onclick="return confirm('Do you really want to delete <?php echo str_replace("'", '', $file->name) ?>?');" href="javascript:kill(<?php echo $file->id ?>, '<?php echo $file->name ?>');"><?php _e('Delete', $sermon_domain) ?></a> 
								<?php endif ?>
							</td>
						</tr>
					<?php endforeach ?>			
				<?php endif ?>
			</tbody>			
		</table>
		<br style="clear:both">
		<div class="navigation">
			<div class="alignleft" id="uleft"></div>
			<div class="alignright" id="uright"></div>
		</div>
	</div>
	<a name="linked"></a>
	<div class="wrap">
		<h2>Linked files</h2>
		<br style="clear:both">
		<table class="widefat">
			<thead>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File name', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File type', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Sermon', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</thead>	
			<tbody id="the-list-l">
				<?php if (is_array($linked)): ?>
					<?php foreach ($linked as $file): ?>
						<tr class="file <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="file<?php echo $file->id ?>">
							<th style="text-align:center" scope="row"><?php echo $file->id ?></th>
							<td id="<?php echo $file->id ?>"><?php echo substr($file->name, 0, strrpos($file->name, '.')) ?></td>
							<td style="text-align:center"><?php echo isset($filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name']) ? $filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name'] : strtoupper(substr($file->name, strrpos($file->name, '.') + 1)) ?></td>
							<td><?php echo $file->title ?></td>
							<td style="text-align:center">
                            <script type="text/javascript" language="javascript">
                            function deletelinked_<?php echo $file->id;?>(filename, filesermon) {
								if (confirm('Do you really want to delete '+filename+'?')) {
									return confirm('This file is linked to the sermon called ['+filesermon+']. Are you sure you want to delete it?');
								}
								return false;
							}
                            </script>
								<?php if (is_writable($wordpressRealPath.get_option('sb_sermon_upload_dir').$file->name)): ?>
								<a id="link<?php echo $file->id ?>" href="javascript:rename(<?php echo $file->id ?>, '<?php echo $file->name ?>')"><?php _e('Rename', $sermon_domain) ?></a> | <a onclick="return deletelinked_<?php echo $file->id;?>('<?php echo str_replace("'", '', $file->name) ?>', '<?php echo str_replace("'", '', $file->title) ?>');" href="javascript:kill(<?php echo $file->id ?>, '<?php echo $file->name ?>');"><?php _e('Delete', $sermon_domain) ?></a> 
								<?php endif ?>
							</td>
						</tr>
					<?php endforeach ?>			
				<?php endif ?>
			</tbody>			
		</table>
		<br style="clear:both">
		<div class="navigation">
			<div class="alignleft" id="left"></div>
			<div class="alignright" id="right"></div>
		</div>
	</div>	
	<div class="wrap">
		<h2><?php _e('Upload Files', $sermon_domain) ?></h2>		
        <?php
		$checkSermonUpload = checkSermonUploadable();
		if ($checkSermonUpload == 'writeable') {
		?>	
		<form method="post" enctype="multipart/form-data">
			<table width="100%" cellspacing="2" cellpadding="5" class="editform">
			<?php if ($fname): ?>
			<tr>
				<th valign="top" scope="row"><?php _e('Current file', $sermon_domain) ?>: </th>
				<td><?php echo $fname ?></td>
			</tr>				
			<?php endif ?>
			<tr>
				<th valign="top" scope="row"><?php _e('File to upload', $sermon_domain) ?>: </th>
				<td><input type="file" size="40" value="" name="upload"/></td>
			</tr>		
			<?php if ($fname): ?>
			<?php $sermons = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_sermons") ?>
			<tr>
				<th valign="top" scope="row"><?php _e('Sermon', $sermon_domain) ?>: </th>
				<td>
					<?php if (is_array($sermons)): ?>						
						<select name="sermon">
							<?php foreach ($sermons as $sermon): ?>								
								<?php $selected = ($sermon->id == $result->sermon_id) ? 'selected="selected"' : ''; ?>
								<option value="<?php echo $sermon->id ?>" <?php echo $selected ?>><?php echo htmlspecialchars(stripslashes($sermon->title), ENT_QUOTES) ?></option>
							<?php endforeach ?>
						</select>
					<?php endif ?>
				</td>
			</tr>				
			<?php endif ?>
			</table>
			<p class="submit"><input type="submit" name="save" value="<?php _e('Upload', $sermon_domain) ?> &raquo;" /></p> 
		</form>
        <?php
		} else {
		?>
        <p style="color:#FF0000"><?php _e('Upload is disabled. Please check your folder setting (Sermons / Options).', $sermon_domain);?></p>
        <?php
		}
		?>
	</div>	
	<a name="search"></a>
	<div class="wrap">
		<h2><?php _e('Search for files', $sermon_domain) ?></h2>
		<form id="searchform" name="searchform">			
		<fieldset>
			<legend><?php _e('File name', $sermon_domain) ?></legend>
			<input type="text" size="17" value="" id="search" />
		</fieldset>				
		<input type="submit" class="button" value="<?php _e('Search', $sermon_domain) ?> &raquo;" style="float:left;margin:14px 0pt 1em; position:relative;top:0.35em;" onclick="javascript:findNow();return false;" />
		</form>
		<br style="clear:both">
		<table class="widefat">
			<thead>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File name', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File type', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Sermon', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</thead>	
			<tbody id="the-list-s">	
				<tr>
					<td><?php _e('Search results will appear here.', $sermon_domain) ?></td>			
				</tr>
			</tbody>			
		</table>
		<br style="clear:both">
	</div>
	<script>
		<?php if ($cntu > 15): ?>
			jQuery('#uright').html('<a href="javascript:fetchU(15)">Next &raquo;</a>');
		<?php endif ?>
		<?php if ($cntl > 15): ?>
			jQuery('#right').html('<a href="javascript:fetchL(15)">Next &raquo;</a>');
		<?php endif ?>
	</script>
<?php	
}

function bb_manage_sermons() {
	global $wpdb, $sermon_domain;

	$url = get_bloginfo('wpurl');
	
	if ($_GET['saved']) {
		echo '<div id="message" class="updated fade"><p><b>'.__('Sermon saved to database.', $sermon_domain).'</b></div>';
	}
	
	if ($_GET['mid']) {
		$mid = (int) $_GET['mid'];
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_sermons WHERE id = $mid;");
		echo '<div id="message" class="updated fade"><p><b>'.__('Sermon removed from database.', $sermon_domain).'</b></div>';
	}
	
	$cnt = $wpdb->get_row("SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons", ARRAY_A);
	$cnt = $cnt['COUNT(*)'];		
			
	$sermons = $wpdb->get_results("SELECT m.id, m.title, m.date, p.name as pname, s.name as sname, ss.name as ssname FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where (m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id) ORDER BY m.date desc LIMIT 0, 15;");	
	$preachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY name;");	
	$series = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY name;");
?>	
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/jquery/jquery.js"></script>
	<script>
		function fetch(st) {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php', {fetch: st + 1, sermon: 1, title: jQuery('#search').val(), preacher: jQuery('#preacher option[@selected]').val(), series: jQuery('#series option[@selected]').val() }, function(r) {
				if (r) {
					jQuery('#the-list').html(r);					
					if (st >= 15) {
						x = st - 15;
						jQuery('#left').html('<a href="javascript:fetch(' + x + ')">&laquo; Previous</a>');
					} else {
						jQuery('#left').html('');
					}
					if (st + 15 <= <?php echo $cnt ?>) {
						y = st + 15;
						jQuery('#right').html('<a href="javascript:fetch(' + y + ')">Next &raquo;</a>');
					} else {
						jQuery('#right').html('');
					}
				};
			});	
		}
	</script>	
	<div class="wrap">
		<h2>Filter</h2>
			<form id="searchform" name="searchform">			
			<fieldset>
				<legend><?php _e('Title', $sermon_domain) ?></legend>
				<input type="text" size="17" value="" id="search" />
			</fieldset>				
			<fieldset>
				<legend><?php _e('Preacher', $sermon_domain) ?></legend>				
				<select id="preacher">
					<option value="0"></option>
					<?php foreach ($preachers as $preacher): ?>
						<option value="<?php echo $preacher->id ?>"><?php echo htmlspecialchars(stripslashes($preacher->name), ENT_QUOTES) ?></option>
					<?php endforeach ?>
				</select>
			</fieldset>	
			<fieldset>
				<legend><?php _e('Series', $sermon_domain) ?></legend>
				<select id="series">
					<option value="0"></option>
					<?php foreach ($series as $item): ?>
						<option value="<?php echo $item->id ?>"><?php echo htmlspecialchars(stripslashes($item->name), ENT_QUOTES) ?></option>
					<?php endforeach ?>
				</select>
			</fieldset>							
			<input type="submit" class="button" value="<?php _e('Filter', $sermon_domain) ?> &raquo;" style="float:left;margin:14px 0pt 1em; position:relative;top:0.35em;" onclick="javascript:fetch(0);return false;" />
			</form>
		<br style="clear:both">
		<h2><?php _e('Sermons', $sermon_domain) ?></h2>		
		<br style="clear:both">
		<table class="widefat">
			<thead>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Title', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Preacher', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Date', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Service', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Series', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</thead>	
			<tbody id="the-list">
				<?php if (is_array($sermons)): ?>
					<?php foreach ($sermons as $sermon): ?>					
					<tr class="<?php echo ++$i % 2 == 0 ? 'alternate' : '' ?>">
						<th style="text-align:center" scope="row"><?php echo $sermon->id ?></th>
						<td><?php echo $sermon->title ?></td>
						<td><?php echo $sermon->pname ?></td>
						<td><?php echo $sermon->date ?></td>
						<td><?php echo $sermon->sname ?></td>
						<td><?php echo $sermon->ssname ?></td>
						<td style="text-align:center">
							<a href="<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/new_sermon.php&mid=<?php echo $sermon->id ?>"><?php _e('Edit', $sermon_domain) ?></a> | <a onclick="return confirm('Are you sure?')" href="<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php&mid=<?php echo $sermon->id ?>"><?php _e('Delete', $sermon_domain) ?></a>
						</td>
					</tr>
					<?php endforeach ?>
				<?php endif ?>				
			</tbody>			
		</table>
		<div class="navigation">
			<div class="alignleft" id="left"></div>
			<div class="alignright" id="right"></div>
		</div>
	</div>	
	<script>
		<?php if ($cnt > 15): ?>
			jQuery('#right').html('<a href="javascript:fetch(15)">Next &raquo;</a>');
		<?php endif ?>
	</script>
<?php 
}

function bb_new_sermon() {
	global $wpdb, $books, $sermon_domain;
	global $wordpressRealPath;
	bb_scan_dir();
	
	$url = get_bloginfo('wpurl');

	if ($_POST['save'] && $_POST['title']) {		
		$title = mysql_real_escape_string($_POST['title']);
		$preacher_id = (int) $_POST['preacher'];
		$service_id = (int) $_POST['service'];
		$series_id = (int) $_POST['series'];
		$time = mysql_real_escape_string($_POST['time']);
		for ($foo = 0; $foo < count($_POST['start']); $foo++) { 
			if (!empty($_POST['start']['chapter'][$foo]) && !empty($_POST['end']['chapter'][$foo]) && !empty($_POST['start']['verse'][$foo]) && !empty($_POST['end']['verse'][$foo])) {
				$start[] = array(
					'book' => $_POST['start']['book'][$foo],
					'chapter' => $_POST['start']['chapter'][$foo],
					'verse' => $_POST['start']['verse'][$foo],					
				);
				$end[] = array(
					'book' => $_POST['end']['book'][$foo],
					'chapter' => $_POST['end']['chapter'][$foo],
					'verse' => $_POST['end']['verse'][$foo],					
				);
			}
		}
		$start = mysql_real_escape_string(serialize($start));
		$end = mysql_real_escape_string(serialize($end));
		$date = date('Y-m-d', strtotime($_POST['date']));
		$description = mysql_real_escape_string(strip_tags($_POST['description']));
		$override = $_POST['override'] == 'on' ? 1 : 0;
		if (!$_GET['mid']) {
			$query1 = "INSERT INTO {$wpdb->prefix}sb_sermons VALUES (null, '$title', '$preacher_id', '$date', '$service_id', '$series_id', '$start', '$end', '$description', '$time', '$override')";
			$wpdb->query($query1);				
			$id = $wpdb->insert_id;				
		} else {
			$mid = (int) $_GET['mid'];
			$query1 = "UPDATE {$wpdb->prefix}sb_sermons SET title = '$title', preacher_id = '$preacher_id', date = '$date', series_id = '$series_id', start = '$start', end = '$end', description = '$description', time = '$time', service_id = '$service_id', override = '$override' WHERE id = $mid;";
			$wpdb->query($query1);
			$queryz = "UPDATE {$wpdb->prefix}sb_sermon_files SET sermon_id = 0 WHERE sermon_id = $mid;";
			$wpdb->query($queryz);
			$id = $mid;
		}		
		foreach ($_POST['file'] as $uid => $file) {
			if ($_FILES['upload']['error'][$uid] == UPLOAD_ERR_OK) {
				$filename = basename($_FILES['upload']['name'][$uid]);
				$prefix = '';
				$dest = $wordpressRealPath.get_option('sb_sermon_upload_dir').$prefix.$filename;
				if (move_uploaded_file($_FILES['upload']['tmp_name'][$uid], $dest)) {
					$filename = $prefix.mysql_real_escape_string($filename);
					$queryz = "INSERT INTO {$wpdb->prefix}sb_sermon_files VALUES (null, '$filename', 0);";
					$wpdb->query($queryz);
					$file = $wpdb->insert_id;				
					$query2 = "UPDATE {$wpdb->prefix}sb_sermon_files SET sermon_id = $id WHERE id = $file;";
					$wpdb->query($query2);							
				}
			} elseif ($file != 0) {
				$wpdb->query("UPDATE {$wpdb->prefix}sb_sermon_files SET sermon_id = $id WHERE id = $file;");
			} 				
		}
		echo "<script>document.location = '$url/wp-admin/admin.php?page=sermonbrowser/sermon.php&saved=true';</script>";
	}		
	
	$preachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY name asc");
	$services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_services ORDER BY name asc");
	$series = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY name asc");
	$files = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_sermon_files WHERE sermon_id = 0 ORDER BY name asc");
	
	foreach ($services as $service) {
		$serviceId[] = $service->id;
		$deftime[] = $service->time;
	}
	
	for ($lol = 0; $lol < count($serviceId); $lol++) { 
		$timeArr .= "timeArr[{$serviceId[$lol]}] = '$deftime[$lol]';"; 
	}	

	if ($_GET['mid']) {
		$mid = (int) $_GET['mid'];
		$curSermon = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}sb_sermons WHERE id = $mid");
		$files = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_sermon_files WHERE sermon_id IN (0, $mid) ORDER BY name asc");
		$startArr = unserialize($curSermon->start);
		$endArr = unserialize($curSermon->end);
	}
?>
	<link rel="stylesheet" href="<?php echo $url ?>/wp-content/plugins/sermonbrowser/datepicker.css" type="text/css">
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/jquery/jquery.js"></script>
	<script type="text/javascript" src="<?php echo $url ?>/wp-content/plugins/sermonbrowser/datePicker.js"></script>
	<script type="text/javascript">		
		var timeArr = new Array();
		<?php echo $timeArr ?>		
		function createNewPreacher(s) {
			if (jQuery('*[@selected]', s).text() != 'Create new preacher') return;
			var p = prompt("New preacher's name?", "Preacher's name");
			if (p != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php', {pname: p, sermon: 1}, function(r) {
					if (r) {
						jQuery('#preacher option:first').before('<option value="' + r + '">' + p + '</option>');
						jQuery("#preacher option[@value='" + r + "']").attr('selected', 'selected');				
					};
				});	
			}
		}
		function createNewService(s) {
			if (jQuery('*[@selected]', s).text() != 'Create new service') {
				if (!jQuery('#override')[0].checked) {
					jQuery('#time').val(timeArr[jQuery('*[@selected]', s).attr('value')]).attr('disabled', 'disabled');
				}
				return;			
			}
			var s = 'lol';
			while ((s.indexOf('@') == -1) || (s.match(/(.*?)@(.*)/)[2].match(/[0-9]{1,2}:[0-9]{1,2}/) == null)) {
				s = prompt("New service's name - default time?", "Service's name @ 18:00");					
				if (s == null) { break;	}
			}
			if (s != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php', {sname: s, sermon: 1}, function(r) {
					if (r) {
						jQuery('#service option:first').before('<option value="' + r + '">' + s.match(/(.*?)@/)[1] + '</option>');	
						jQuery("#service option[@value='" + r + "']").attr('selected', 'selected');					
						jQuery('#time').val(s.match(/(.*?)@\s*(.*)/)[2]);
					};
				});	
			}
		}
		function createNewSeries(s) {
			if (jQuery('*[@selected]', s).text() != 'Create new series') return;
			var ss = prompt("New series' name?", "Series' name");
			if (ss != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php', {ssname: ss, sermon: 1}, function(r) {
					if (r) {
						jQuery('#series option:first').before('<option value="' + r + '">' + ss + '</option>');			
						jQuery("#series option[@value='" + r + "']").attr('selected', 'selected');	
					};
				});	
			}
		}
		function addPassage() {
			var p = jQuery('#passage').clone();	
			p.attr('id', 'passage' + gpid);
			jQuery('tr:first td:first', p).prepend('[<a href="javascript:removePassage(' + gpid++ + ')">x</a>] ');
			jQuery("input", p).attr('value', '');
			jQuery('.passage:last').after(p);
		}
		function removePassage(id) {
			jQuery('#passage' + id).remove();
		}
		function syncBook(s) {
			var slc = jQuery('*[@selected]', s).text();
			jQuery('.passage').each(function(i) {
				if (this == jQuery(s).parents('.passage')[0]) {
					jQuery('.end').each(function(j) {
						if (i == j) {
							jQuery("option[@value='" + slc + "']", this).attr('selected', 'selected');
						}
					});
				}
			});			
		}		
		function addFile() {
			var f = jQuery('#choosefile').clone();
			f.attr('id', 'choose' + gfid);
			jQuery('tr:first td:first', f).prepend('[<a href="javascript:removeFile(' + gfid++ + ')">x</a>] ');
			jQuery("option[@value='0']", f).attr('selected', 'selected');
			jQuery("input[@type='file']", f).val('');
			jQuery('.choose:last').after(f);			
		}
		function removeFile(id) {
			jQuery('#choose' + id).remove();
		}
		function doOverride(id) {
			var chk = jQuery('#override')[0].checked;
			if (chk) {
				jQuery('#time').removeClass('gray').attr('disabled', false);
			} else {
				jQuery('#time').addClass('gray').val(timeArr[jQuery('*[@selected]', jQuery("select[@name='service']")).attr('value')]).attr('disabled', 'disabled');
			}
		}
		var gfid = 0;
		var gpid = 0;
	</script>
	<div class="wrap">
		<h2><?php echo $_GET['mid'] ? 'Edit Sermon' : 'Add Sermon' ?></h2>
		<form method="post" enctype="multipart/form-data">
		<fieldset>
			<table class="widefat">
				<tr>
					<td colspan="2">
						<strong><?php _e('Title', $sermon_domain) ?></strong>
						<div>
							<input type="text" value="<?php echo $curSermon->title ?>" name="title" size="60" style="width:400px;" />
						</div>
					</td>					
				</tr>
				<tr>					
					<td>
						<strong><?php _e('Preacher', $sermon_domain) ?></strong><br/>
						<select id="preacher" name="preacher" onchange="createNewPreacher(this)">
							<?php if (count($preachers) == 0): ?>
								<option value="" selected="selected"></option>
							<?php else: ?>								
								<?php foreach ($preachers as $preacher): ?>
									<option value="<?php echo $preacher->id ?>" <?php echo $preacher->id == $curSermon->preacher_id ? 'selected="selected"' : '' ?>><?php echo htmlspecialchars(stripslashes($preacher->name), ENT_QUOTES) ?></option>
								<?php endforeach ?>
							<?php endif ?>
							<option value="newPreacher"><?php _e('Create new preacher', $sermon_domain) ?></option>
						</select>
					</td>
					<td>
						<strong><?php _e('Series', $sermon_domain) ?></strong><br/>
						<select id="series" name="series" onchange="createNewSeries(this)">
							<?php if (count($series) == 0): ?>
								<option value="" selected="selected"></option>
							<?php else: ?>
								<?php foreach ($series as $item): ?>
									<option value="<?php echo $item->id ?>" <?php echo $item->id == $curSermon->series_id ? 'selected="selected"' : '' ?>><?php echo htmlspecialchars(stripslashes($item->name), ENT_QUOTES) ?></option>
								<?php endforeach ?>
							<?php endif ?>
							<option value="newSeries"><?php _e('Create new series', $sermon_domain) ?></option>
						</select>
					</td>					
				</tr>
				<tr>
					<td>
						<strong><?php _e('Date', $sermon_domain) ?></strong> (yyyy-mm-dd)
						<div>
							<input type="text" id="date" name="date" value="<?php echo $curSermon->date ?>" />
						</div>
					</td>
					<td rowspan="3">
						<strong><?php _e('Description', $sermon_domain) ?></strong>
						<div>
							<textarea name="description" cols="50" rows="7"><?php echo $curSermon->description ?></textarea>
						</div>
					</td>
				</tr>
				<tr>
					<td>
						<strong><?php _e('Service', $sermon_domain) ?></strong><br/>
						<select id="service" name="service" onchange="createNewService(this)">
							<?php if (count($services) == 0): ?>
								<option value="" selected="selected"></option>
							<?php else: ?>
								<?php foreach ($services as $service): ?>
									<option value="<?php echo $service->id ?>" <?php echo $service->id == $curSermon->service_id ? 'selected="selected"' : '' ?>><?php echo htmlspecialchars(stripslashes($service->name), ENT_QUOTES) ?></option>
								<?php endforeach ?>
							<?php endif ?>
							<option value="newService"><?php _e('Create new service', $sermon_domain) ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<strong><?php _e('Time', $sermon_domain) ?></strong>
						<div>
							<input type="text" name="time" value="<?php echo $curSermon->time ?>" id="time" <?php echo !$curSermon->override ? 'disabled="disabled" class="gray"' : '' ?> /> 
							<input type="checkbox" name="override" style="width:30px" id="override" onchange="doOverride()" <?php echo !$curSermon->override ? '' : 'checked="checked"' ?>> <?php _e('Override default time', $sermon_domain) ?> 
						</div>
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<strong><?php _e('Bible passage', $sermon_domain) ?></strong> (<a href="javascript:addPassage()"><?php _e('add more', $sermon_domain) ?></a>)
					</td>
				</tr>
				<tr>
					<td><?php _e('From', $sermon_domain) ?></td>
					<td><?php _e('To', $sermon_domain) ?></td>
				</tr>
				<tr id="passage" class="passage">					
					<td>
						<table>
							<tr>
								<td>
									<select name="start[book][]" onchange="syncBook(this)" class="start1">
										<option value=""></option>
										<?php foreach ($books as $book): ?>
											<option value="<?php echo $book ?>"><?php echo $book ?></option>
										<?php endforeach ?>
									</select>
								</td>
								<td><input type="text" style="width:60px;" name="start[chapter][]" value="" class="start2" /><br /></td>
								<td><input type="text" style="width:60px;" name="start[verse][]" value="" class="start3" /><br /></td>
							</tr>
						</table>
					</td>
					<td>
						<table>
							<tr>								
								<td>
									<select name="end[book][]" class="end">
										<option value=""></option>
										<?php foreach ($books as $book): ?>
											<option value="<?php echo $book ?>"><?php echo $book ?></option>
										<?php endforeach ?>
									</select>
								</td>
								<td><input type="text" style="width:60px;" name="end[chapter][]" value="" class="end2" /><br /></td>
								<td><input type="text" style="width:60px;" name="end[verse][]" value="" class="end3" /><br /></td>
							</tr>
						</table>						
					</td>					
				</tr>
				<tr>
					<td colspan="2">
						<strong><?php _e('Files', $sermon_domain) ?></strong> (<a href="javascript:addFile()"><?php _e('add more', $sermon_domain) ?></a>)
					</td>
				</tr>
				<tr id="choosefile" class="choose">
					<td>
						<table>
							<tr class="choosefile">
								<td><?php _e('Choose existing file:', $sermon_domain) ?> </td>
								<td id="filelist">
									<select id="file" name="file[]">									
									<?php echo count($files) == 0 ? '<option value="0">No files found</option>' : '<option value="0"></option>' ?>
									<?php foreach ($files as $file): ?>										
										<option value="<?php echo $file->id ?>"><?php echo $file->name ?></option>
									<?php endforeach ?>									
									</select>									
								</td>
							</tr>
						</table>
					</td>
					<?php $wtf = checkSermonUploadable() ?>
					<?php if ($wtf == 'writeable'): ?>
					<td>
						<table>
							<tr>								
								<td><?php _e('Or upload a new one:', $sermon_domain) ?> </td>
								<td><input type="file" name="upload[]"></td>
							</tr>
						</table>					
					</td>
					<?php endif ?>
				</tr>
			</table>
		</fieldset>
		<p class="submit"><input type="submit" name="save" value="<?php _e('Save', $sermon_domain) ?> &raquo;" /></p> 
		</form>
	</div>		
	<script type="text/javascript">
		jQuery.datePicker.setDateFormat('ymd','-');
		jQuery('#date').datePicker({startDate:'01/01/1970'});
		<?php if (empty($curSermon->time)): ?>
			jQuery('#time').val(timeArr[jQuery('*[@selected]', jQuery("select[@name='service']")).attr('value')]);
		<?php endif ?>
		<?php if ($mid): ?>
			assocFiles = new Array();
			start1 = new Array();
			start2 = new Array();
			start3 = new Array();
			end1 = new Array();
			end2 = new Array();
			end3 = new Array();
			
			<?php $assocFiles = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}sb_sermon_files WHERE sermon_id = $mid ORDER BY name asc;") ?>
			
			<?php for ($lolz = 0; $lolz < count($assocFiles); $lolz++): ?>
				<?php if ($lolz != 0): ?>
					addFile();
				<?php endif ?>
				assocFiles.push(<?php echo $assocFiles[$lolz]->id ?>);
			<?php endfor ?>
			
			<?php for ($lolz = 0; $lolz < count($startArr); $lolz++): ?>
				<?php if ($lolz != 0): ?>
					addPassage();
				<?php endif ?>
				start1.push("<?php echo $startArr[$lolz]['book'] ?>");
				start2.push("<?php echo $startArr[$lolz]['chapter'] ?>");
				start3.push("<?php echo $startArr[$lolz]['verse'] ?>");
				end1.push("<?php echo $endArr[$lolz]['book'] ?>");
				end2.push("<?php echo $endArr[$lolz]['chapter'] ?>");
				end3.push("<?php echo $endArr[$lolz]['verse'] ?>");
			<?php endfor ?>
			
			jQuery('.choosefile').each(function(i) {
				jQuery("option[@value='" + assocFiles[i] + "']", this).attr('selected', 'selected');
			});		
			
			jQuery('.start1').each(function(i) {
				jQuery("option[@value='" + start1[i] + "']", this).attr('selected', 'selected');
			});	
			
			jQuery('.end').each(function(i) {
				jQuery("option[@value='" + end1[i] + "']", this).attr('selected', 'selected');
			});		
			
			jQuery('.start2').each(function(i) {
				jQuery(this).val(start2[i]);
			});	
			
			jQuery('.start3').each(function(i) {
				jQuery(this).val(start3[i]);
			});	
			
			jQuery('.end2').each(function(i) {
				jQuery(this).val(end2[i]);
			});	
			
			jQuery('.end3').each(function(i) {
				jQuery(this).val(end3[i]);
			});				
		<?php endif ?>		
	</script>
<?php 
}

function bb_scan_dir() {
	global $wpdb;
	global $wordpressRealPath;
	
	$files = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_sermon_files;");
	$bnn = array();
	foreach ($files as $file) {
		$bnn[] = $file->name;
	}
	
	$dir = $wordpressRealPath.get_option('sb_sermon_upload_dir');	
	if ($dh = @opendir($dir)) {
		while (false !== ($file = readdir($dh))) {
	    	if ($file != "." && $file != ".." && !in_array($file, $bnn)) {	    		
	    		$wpdb->query("INSERT INTO {$wpdb->prefix}sb_sermon_files VALUES (null, '$file', 0);");
	       	}	
		}
	   	closedir($dh);
	}
}

function checkSermonUploadable() {
	global $wordpressRealPath;
	$sermonUploadDir = $wordpressRealPath.get_option('sb_sermon_upload_dir');	
	if (is_dir($sermonUploadDir)) {
		//Dir exist
		$fp = @fopen($sermonUploadDir.'sermontest.txt', 'w');
		if ($fp) {
			//Delete this test file
			@unlink($sermonUploadDir.'sermontest.txt');
			fclose($fp);
			return 'writeable';			
		} else {
			return 'unwriteable';
		}
	} else {
		return 'notexist';
	}
	return false;
}

function nl2br2($string) {
	$string = str_replace(array("\r\n", "\r", "\n"), "<br />", $string);
	return $string;
}

?>