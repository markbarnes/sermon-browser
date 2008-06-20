<?php 
/*
Plugin Name: Sermon Browser
Plugin URI: http://www.4-14.org.uk/sermon-browser
Description: Add sermons to your Wordpress blog. Coding by <a href="http://codeandmore.com/">Tien Do Xuan</a>. Design 
Author: Mark Barnes
Version: 0.24
Author URI: http://www.4-14.org.uk/

Copyright (c) 2008 Mark Barnes

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

*/

// Localization and set-up
require_once('dictionary.php'); // Template functions
$sermon_domain = 'sermonbrowser';
load_plugin_textdomain($sermon_domain, 'wp-content/plugins/sermonbrowser');
include_once('filetypes.php'); // User-defined icons
include('frontend.php'); // Everything related to displaying sermons

// URLs and paths
$ser_ver = '1.1';
$url = get_bloginfo('wpurl');
global $wordpressRealPath;
$wordpressRealPath = str_replace("\\", "/", dirname(dirname(dirname(dirname(__FILE__)))));
global $defaultSermonPath;
$defaultSermonPath = '/'.get_option('upload_path').'/sermons/';
$defaultSermonURL = get_bloginfo('url').'/'.get_option('upload_path').'/sermons/';
$books = array('Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy', 'Joshua', 'Judges', 'Ruth', '1 Samuel', '2 Samuel', '1 Kings', '2 Kings', '1 Chronicles', '2 Chronicles', 'Ezra', 'Nehemiah', 'Esther', 'Job', 'Psalm', 'Proverbs', 'Ecclesiastes', 'Song of Solomon', 'Isaiah', 'Jeremiah', 'Lamentations', 'Ezekiel', 'Daniel', 'Hosea', 'Joel', 'Amos', 'Obadiah', 'Jonah', 'Micah', 'Nahum', 'Habakkuk', 'Zephaniah', 'Haggai', 'Zechariah', 'Malachi', 'Matthew', 'Mark', 'Luke', 'John', 'Acts', 'Romans', '1 Corinthians', '2 Corinthians', 'Galatians', 'Ephesians', 'Philippians', 'Colossians', '1 Thessalonians', '2 Thessalonians', '1 Timothy', '2 Timothy', 'Titus', 'Philemon', 'Hebrews', 'James', '1 Peter', '2 Peter', '1 John', '2 John', '3 John', 'Jude', 'Revelation');

//Attempt to set php.ini directives
if (sb_return_kbytes(ini_get('upload_max_filesize'))<15360) ini_set('upload_max_filesize', '15M');
if (sb_return_kbytes(ini_get('post_max_size'))<15360) ini_set('post_max_size', '15M');
if (sb_return_kbytes(ini_get('memory_limit'))<16384) ini_set('memory_limit', '16M');
if (intval(ini_get('max_input_time'))<600) ini_set('max_input_time','600');
if (intval(ini_get('max_execution_time'))<600) ini_set('max_execution_time', '600');
if (ini_get('file_uploads')<>'1') ini_set('file_uploads', '1');

//Processing for php.ini values
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

// Ajax stuff
// Throughout this plugin, p stands for preacher, s stands for service and ss stands for series
if ($_POST['sermon'] == 1) {	
	if ($_POST['pname']) { // preacher
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
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_preachers VALUES (null, '$pname', '', '');");
			echo $wpdb->insert_id;
		} 		
	} elseif ($_POST['sname']) { // service
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
	} elseif ($_POST['ssname']) { // series
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
	} elseif ($_POST['fname']) { // file		
		$fname = mysql_real_escape_string($_POST['fname']);
		if ($_POST['fid']) {
			$fid = (int) $_POST['fid'];
			$oname = mysql_real_escape_string($_POST['oname']);			
			if ($_POST['del']) {
				if (!file_exists($wordpressRealPath.get_option('sb_sermon_upload_dir').$fname) || unlink($wordpressRealPath.get_option('sb_sermon_upload_dir').$fname)) {
					$wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE id = $fid;");
					echo 'deleted';
				} else {
					echo 'failed';
				}				
			} else {				
				$oname = mysql_real_escape_string($_POST['oname']);	
				if ( !is_writable($wordpressRealPath.get_option('sb_sermon_upload_dir').$fname) && rename($wordpressRealPath.get_option('sb_sermon_upload_dir').$oname, $wordpressRealPath.get_option('sb_sermon_upload_dir').$fname)) {
					$wpdb->query("UPDATE {$wpdb->prefix}sb_stuff SET name = '$fname' WHERE id = $fid;");
					echo 'renamed';
				} else {		
					echo 'failed';				
				}
			}				
		}
	} elseif ($_POST['fetch']) { // ajax pagination (sermon browser)		
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
		$m = $wpdb->get_results("SELECT m.id, m.title, m.date, p.name as pname, s.name as sname, ss.name as ssname FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id $cond ORDER BY m.date desc, s.time desc LIMIT $st, 15;");
		$cnt = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id $cond;");	
		
?>
		<?php foreach ($m as $sermon): ?>					
			<tr class="<?php echo ++$i % 2 == 0 ? 'alternate' : '' ?>">
				<th style="text-align:center" scope="row"><?php echo $sermon->id ?></th>
				<td><?php echo stripslashes($sermon->title) ?></td>
				<td><?php echo stripslashes($sermon->pname) ?></td>
				<td><?php echo stripslashes($sermon->date) ?></td>
				<td><?php echo stripslashes($sermon->sname) ?></td>
				<td><?php echo stripslashes($sermon->ssname) ?></td>
				<td style="text-align:center">
					<a href="<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/new_sermon.php&mid=<?php echo $sermon->id ?>"><?php _e('Edit', $sermon_domain) ?></a> | <a onclick="return confirm('Are you sure?')" href="<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/sermon.php&mid=<?php echo $sermon->id ?>"><?php _e('Delete', $sermon_domain) ?></a>
				</td>
			</tr>
		<?php endforeach ?>
<script type="text/javascript">
<?php if($cnt<15 || $cnt <= $st+15): ?>
	jQuery('#right').css('display','none');
	<?php elseif($cnt > $st+15): ?>
	jQuery('#right').css('display','');
<?php endif ?>

	

</script>
<?php
	} elseif ($_POST['fetchU'] || $_POST['fetchL'] || $_POST['search']) { // ajax pagination (uploads)
		if ($_POST['fetchU']) {
			$st = (int) $_POST['fetchU'] - 1;
			$abc = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id = 0 AND f.type = 'file' ORDER BY f.name LIMIT $st, 15;");
		} elseif ($_POST['fetchL']) {
			$st = (int) $_POST['fetchL'] - 1;
			$abc = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id <> 0 AND f.type = 'file' ORDER BY f.name LIMIT $st, 15;");
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
				<td><?php echo stripslashes($file->title) ?></td>
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

// Installer
function sb_sermon_install () {
   global $wpdb, $mdict, $sdict, $books;	
   global $ser_ver;
   global $sermon_domain, $sef;
   global $wordpressRealPath;
   global $defaultSermonPath, $defaultSermonURL, $defaultMultiForm, $defaultSingleForm, $defaultStyle;
   require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
   
   if(get_option('sb_sermon_db_version') =='1.2'){
       return;
   }
   delete_option('sb_sermon_style');
   add_option('sb_sermon_style', base64_encode($defaultStyle));

   $sermonUploadDir = $defaultSermonPath;

	//Try to create the folder if it doesn't exist
   if (!is_dir($wordpressRealPath.$sermonUploadDir)) {
      //Create that folder
      if (@mkdir($wordpressRealPath.$sermonUploadDir)) {
         //try CHMOD it to 777
         @chmod($wordpressRealPath.$sermonUploadDir, 0777);          
      }
   }
	if(!is_dir($wordpressRealPath.$sermonUploadDir.'images') && @mkdir($wordpressRealPath.$sermonUploadDir.'images')){
		@chmod($wordpressRealPath.$sermonUploadDir.'images', 0777);
	}
	//Upgrade database from earlier versions
	switch (get_option('sb_sermon_db_version')) {
		case '1.0':
			// db
			
			// move files
			$oldSermonPath = "/wp-content/plugins/sermonbrowser/files/";
			$files = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_stuff WHERE type = 'file' ORDER BY name asc");	
			foreach ((array)$files as $file) {
				@chmod($wordpressRealPath.$oldSermonPath.$file->name, 0777);
				@rename($wordpressRealPath.$oldSermonPath.$file->name, $wordpressRealPath.$defaultSermonPath.$file->name);
			}
			//Now alter tables
			$table_name = $wpdb->prefix . "sb_preachers";
			if($wpdb->get_var("show tables like '$table_name'") == $table_name) {            
				  $sql = "ALTER TABLE " . $table_name . " ADD `description` TEXT NOT NULL, ADD `image` VARCHAR( 255 ) NOT NULL ;";
				  dbDelta($sql);
			}
			
			update_option('sb_sermon_db_version', '1.1');		
			return;
			break;
		case '1.1':
   	        add_option('sb_sermon_style', base64_encode($defaultStyle));
	if(!is_dir($wordpressRealPath.$sermonUploadDir.'images') && @mkdir($wordpressRealPath.$sermonUploadDir.'images')){
	     @chmod($wordpressRealPath.$sermonUploadDir.'images', 0777);
	   }
   	        update_option('sb_sermon_db_version', '1.2');	
		    return;
		    break;
		default:
			update_option('sb_sermon_db_version', '1.2');
			break;
	}   	

	//Create default tables
   $table_name = $wpdb->prefix . "sb_preachers";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {            
	  $sql = "CREATE TABLE " . $table_name . " (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
		`name` VARCHAR( 30 ) NOT NULL ,
		`description` TEXT NOT NULL ,
		`image` VARCHAR( 255 ) NOT NULL,
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
	  $sql = "INSERT INTO " . $table_name . "(name, description, image) VALUES ( 'C H Spurgeon', '', '' );";
      dbDelta($sql);
	  $sql = "INSERT INTO " . $table_name . "(name, description, image) VALUES ( 'Martyn Lloyd-Jones', '', '' );";
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

	$table_name = $wpdb->prefix . "sb_books_sermons";
   	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
	  $sql = "CREATE TABLE " . $table_name . " (		
		`id` INT(10) NOT NULL AUTO_INCREMENT,
		`book_name` VARCHAR( 30 ) NOT NULL ,		
		`chapter` INT(10) NOT NULL,
		`verse` INT(10) NOT NULL,
		`order` INT(10) NOT NULL,
		`type` VARCHAR ( 30 ), 
		`sermon_id` INT( 10 ) NOT NULL ,
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
   }

	$table_name = $wpdb->prefix . "sb_books";
   	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
	  $sql = "CREATE TABLE " . $table_name . " (		
		`id` INT(10) NOT NULL AUTO_INCREMENT,
		`name` VARCHAR( 30 ) NOT NULL ,
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
   }
   
   $table_name = $wpdb->prefix . "sb_stuff";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
	  $sql = "CREATE TABLE " . $table_name . " (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
		`type` VARCHAR( 30 ) NOT NULL ,
		`name` TEXT NOT NULL ,
		`sermon_id` INT( 10 ) NOT NULL ,
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
   }

	$table_name = $wpdb->prefix . "sb_tags";
	   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
		  $sql = "CREATE TABLE " . $table_name . " (
			`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
			`name` TEXT NOT NULL ,
			PRIMARY KEY ( `id` )
			);";
	      dbDelta($sql);
	   }
	
	$table_name = $wpdb->prefix . "sb_sermons_tags";
	   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
		  $sql = "CREATE TABLE " . $table_name . " (
			`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
			`sermon_id` INT( 10 ) NOT NULL ,
			`tag_id` INT( 10 ) NOT NULL ,
			PRIMARY KEY ( `id` )
			);";
	      dbDelta($sql);
	   }
	$welcome_name = __('Delete', $sermon_domain);
	$welcome_text = __('Congratulations, you just completed the installation!', $sermon_domain);	
	add_option('sb_sermon_upload_dir', $sermonUploadDir);
	add_option('sb_sermon_upload_url', $defaultSermonURL);
	add_option('sb_podcast', get_bloginfo('url').$sef.'/?podcast');
   
	delete_option('sb_sermon_multi_form');
   	add_option('sb_sermon_multi_form', base64_encode(sb_default_multi_template()));
	delete_option('sb_sermon_single_form');
   	add_option('sb_sermon_single_form', base64_encode(sb_default_single_template()));
	delete_option('sb_sermon_style');
   	add_option('sb_sermon_style', base64_encode(sb_default_css()));
 	
	for ($i=0; $i < count($books); $i++) { 
		$wpdb->query("INSERT INTO {$wpdb->prefix}sb_books VALUES (null, '$books[$i]');");
	}
}

// admin cp pages
function sb_add_pages() {
	global $sermon_domain;
	add_menu_page(__('Sermons', $sermon_domain), __('Sermons', $sermon_domain), 'edit_posts', __FILE__, 'sb_manage_sermons');
	add_submenu_page(__FILE__, __('Sermons', $sermon_domain), __('Sermons', $sermon_domain), 'edit_posts', __FILE__, 'sb_manage_sermons');
	add_submenu_page(__FILE__, __('New Sermon', $sermon_domain), __('New Sermon', $sermon_domain), 'publish_posts', 'sermonbrowser/new_sermon.php', 'sb_new_sermon');
	add_submenu_page(__FILE__, __('Preachers', $sermon_domain), __('Preachers', $sermon_domain), 'manage_categories', 'sermonbrowser/preachers.php', 'sb_manage_preachers');
	add_submenu_page(__FILE__, __('Series &amp; Services', $sermon_domain), __('Series &amp; Services', $sermon_domain), 'manage_categories', 'sermonbrowser/manage.php', 'sb_manage_everything');
	add_submenu_page(__FILE__, __('Uploads', $sermon_domain), __('Uploads', $sermon_domain), 'upload_files', 'sermonbrowser/uploads.php', 'sb_uploads');
	add_submenu_page(__FILE__, __('Options', $sermon_domain), __('Options', $sermon_domain), 'manage_options', 'sermonbrowser/options.php', 'sb_options');
	add_submenu_page(__FILE__, __('Help', $sermon_domain), __('Help', $sermon_domain), 'read', 'sermonbrowser/help.php', 'sb_help');
}

// textarea @ option
function sb_build_textarea($name, $html) {
	$out = '<textarea name="'.$name.'" cols="75" rows="15">';
	$out .= stripslashes(str_replace('\r\n', "\n", base64_decode($html))); 
	$out .= '</textarea>';
	echo $out;
}

// configuration
function sb_options() {
	global $wpdb, $sermon_domain, $mdict, $sdict, $sef;
	global $wordpressRealPath;
	global $defaultSermonPath, $defaultSermonURL;
	$sef = sb_display_url();
	if ($_POST['resetdefault']) {
		$dir = $defaultSermonPath;
		update_option('sb_podcast', $sef.'/?podcast');
		update_option('sb_sermon_upload_dir', $defaultSermonPath);
		update_option('sb_sermon_upload_url', $defaultSermonURL);
	   	if (!is_dir($wordpressRealPath.$dir)) {
	      //Create that folder
	      if (@mkdir($wordpressRealPath.$dir)) {
	         //try CHMOD it to 777
	         @chmod($wordpressRealPath.$dir, 0777); 
	      }
	   	}
	    if(!is_dir($wordpressRealPath.$sermonUploadDir.'images') && @mkdir($wordpressRealPath.$sermonUploadDir.'images')){
	     @chmod($wordpressRealPath.$sermonUploadDir.'images', 0777);
	   }	   	   	
	   	$checkSermonUpload = sb_checkSermonUploadable();
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
		update_option('sb_podcast', $_POST['podcast']);
		update_option('sb_sermon_upload_dir', $dir);
		update_option('sb_sermon_upload_url', get_bloginfo('url').$dir);		
	   	if (!is_dir($wordpressRealPath.$dir)) {
	      //Create that folder
	      if (@mkdir($wordpressRealPath.$dir)) {
	         //try CHMOD it to 777
	         @chmod($wordpressRealPath.$dir, 0777); 
	      }
	   	}
	if(!is_dir($wordpressRealPath.$dir.'images') && @mkdir($wordpressRealPath.$sermonUploadDir.'images')){
	     @chmod($wordpressRealPath.$dir.'images', 0777);
	   }	   	   	
	   	$checkSermonUpload = sb_checkSermonUploadable();
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
	
	if ($_POST['save2'] || $_POST['resetdefault2']) {
		$multi = $_POST['multi'];
		$single = $_POST['single'];
		$style = $_POST['style'];
	    if($_POST['resetdefault2']){
		    $multi = sb_default_multi_template();
		    $single = sb_default_single_template();
		    $style = sb_default_css();
		}
		update_option('sb_sermon_multi_form', base64_encode($multi));
		update_option('sb_sermon_single_form', base64_encode($single));
		update_option('sb_sermon_style', base64_encode($style));
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
   	    $fh = @fopen($wordpressRealPath.'/wp-content/plugins/sermonbrowser/style.css', 'w');
		if ($fh) {
			fwrite($fh, $style);
			fclose($fh);
		} else {
			echo '<div id="message" class="updated fade"><p><b>';
			_e('Could not save style template. Please check permission for stlye.css in plugin folder', $sermon_domain);
			echo '</b></div>';			
		}
		echo '<div id="message" class="updated fade"><p><b>';
		_e('Options saved properly.', $sermon_domain);
		echo '</b></div>';		
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
		$table_name = $wpdb->prefix."sb_stuff";
		if ($wpdb->get_var("show tables like '$table_name'") == $table_name) $wpdb->query("DROP TABLE $table_name");
		
		delete_option('sb_podcast');
		delete_option('sb_sermon_upload_dir');
		delete_option('sb_sermon_upload_url');
		delete_option('sb_sermon_single_form');
		delete_option('sb_sermon_multi_form');
		delete_option('sb_sermon_db_version');
		delete_option('sb_sermon_style');
		echo '<div id="message" class="updated fade"><p><b>'.__('Uninstall completed. Please deactivate the plugin', $sermon_domain).'</b></div>';
	}
	
	function sb_display_error ($message) {
		return	'<tr><td align="right" style="color:#AA0000; font-weight:bold">'.__('Error', $sermon_domain).':</td>'.
				'<td style="color: #AA0000">'.$message.'</td></tr>';
	}
	function sb_display_warning ($message) {
		return	'<tr><td align="right" style="color:#FF8C00; font-weight:bold">'.__('Warning', $sermon_domain).':</td>'.
				'<td style="color: #FF8C00">'.$message.'</td></tr>';
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
			<tr>
				<td align="right"><?php _e('Public podcast feed', $sermon_domain) ?>: </td>
				<td><input type="text" name="podcast" value="<?php echo get_option('sb_podcast') ?>" style="width:300px"></td>
			</tr>
			<tr>
				<td align="right"><?php _e('Private podcast feed', $sermon_domain) ?>: </td>
				<td><?php echo $sef.'/?podcast' ?></td>
			</tr>
			<?php
				$checkSermonUpload = sb_checkSermonUploadable();
				if ($checkSermonUpload=="unwriteable") 
					echo sb_display_error (__('The upload folder is not writeable. You need to CHMOD the folder to 666 or 777.', $sermon_domain));
				elseif ($checkSermonUpload=="notexist") 
					sb_display_error (__('The upload folder you have specified does not exist.', $sermon_domain));
				$allow_uploads = ini_get('file_uploads');
				$max_filesize = sb_return_kbytes(ini_get('upload_max_filesize'));
				$max_post = sb_return_kbytes(ini_get('post_max_size'));
				$max_execution = ini_get('max_execution_time');
				$max_input = ini_get('max_input_time');
				$max_memory = sb_return_kbytes(ini_get('memory_limit'));
				if ($allow_uploads == '0') echo sb_display_error(__('Your php.ini file does not allow uploads. Please change file_uploads in php.ini.', $sermon_domain));
				if ($max_filesize < 15360) echo sb_display_warning(__('The maximum file size you can upload is only ', $sermon_domain).$max_filesize.__('k. Please change upload_max_filesize to at least 15M in php.ini.', $sermon_domain));
				if ($max_post < 15360) echo sb_display_warning(__('The maximum file size you send through the browser is only ', $sermon_domain).$max_post.__('k. Please change post_max_size to at least 15M in php.ini.', $sermon_domain));
				if ($max_execution < 600) echo sb_display_warning(__('The maximum time allowed for any script to run is only ', $sermon_domain).$max_execution.__(' seconds. Please change max_execution_time to at least 600 in php.ini.', $sermon_domain));
				if ($max_input < 600) echo sb_display_warning(__('The maximum time allowed for an upload script to run is only ', $sermon_domain).$max_input.__(' seconds. Please change max_input_time to at least 600 in php.ini.', $sermon_domain));
				if ($max_memory < 16384) echo sb_display_warning(__('The maximum amount of memory allowed is only ', $sermon_domain).$max_memory.__('k. Please change memory_limit to at least 16M in php.ini.', $sermon_domain));
			?>
		</table>		
		<p class="submit"><input type="submit" name="resetdefault" value="<?php _e('Reset to defaults', $sermon_domain) ?>"  />&nbsp;<input type="submit" name="save" value="<?php _e('Save', $sermon_domain) ?> &raquo;" /></p> 
	</div>	
	<div class="wrap">
		<h2><?php _e('Templates', $sermon_domain) ?></h2>
		<table border="0" class="widefat">
			<tr>
				<td align="right"><?php _e('Multi-sermons form', $sermon_domain) ?>: </td>
				<td>
					<?php sb_build_textarea('multi', get_option('sb_sermon_multi_form')) ?>
				</td>
			</tr>
			<tr>
				<td align="right"><?php _e('Single sermon form', $sermon_domain) ?>: </td>
				<td>
					<?php sb_build_textarea('single', get_option('sb_sermon_single_form')) ?>
				</td>
			</tr>
			<tr>
				<td align="right"><?php _e('Style', $sermon_domain) ?>: </td>
				<td>
					<?php sb_build_textarea('style', get_option('sb_sermon_style')) ?>
				</td>
			</tr>			
		</table>				
		<p class="submit"><input type="submit" name="resetdefault2" value="<?php _e('Reset to defaults', $sermon_domain) ?>"  />&nbsp;<input type="submit" name="save2" value="<?php _e('Save', $sermon_domain) ?> &raquo;" /></p> 
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
	<script>
		jQuery("form").submit(function() {
			var yes = confirm("Are you sure ?");
			if(!yes) return false;
		});
	</script>
<?php 
}

function sb_manage_preachers() {
	global $wpdb, $sermon_domain;
	global $wordpressRealPath;
	
	$url = get_bloginfo('wpurl');
	
	if ($_GET['saved']) {
		echo '<div id="message" class="updated fade"><p><b>'.__('Preacher saved to database.', $sermon_domain).'</b></div>';
	}
	
	if ($_POST['save']) {
		$name = mysql_real_escape_string($_POST['name']);
		$description = mysql_real_escape_string($_POST['description']);
		$error = false;
		$pid = (int) $_REQUEST['pid'];
		
		if (empty($_FILES['upload']['name'])) {
			$p = $wpdb->get_row("SELECT image FROM {$wpdb->prefix}sb_preachers WHERE id = $pid");
			$filename = $p->image;
		} elseif ($_FILES['upload']['error'] == UPLOAD_ERR_OK) {
			$filename = basename($_FILES['upload']['name']);
			$prefix = '';
			$dest = $wordpressRealPath.get_option('sb_sermon_upload_dir').'images/'.$filename;
			if (move_uploaded_file($_FILES['upload']['tmp_name'], $dest)) {
				$filename = $prefix.mysql_real_escape_string($filename);
			}else {
			    $error = true;
			    echo '<div id="message" class="updated fade"><p><b>'.__('Upload failed.', $sermon_domain).'</b></div>';
			}
		} else {
		        $error = true;
		    	echo '<div id="message" class="updated fade"><p><b>'.__('Upload failed.', $sermon_domain).'</b></div>';
		    			    
		}
		
		if ($pid == 0) {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_preachers VALUES (null, '$name', '$description', '$filename')");
		} else {
			$wpdb->query("UPDATE {$wpdb->prefix}sb_preachers SET name = '$name', description = '$description', image = '$filename' WHERE id = $pid");
			if ($_POST['old'] != $filename) {
				@unlink($wordpressRealPath.get_option('sb_sermon_upload_dir').'images/'.mysql_real_escape_string($_POST['old']));
			}			
		}
		if(isset($_POST['remove'])){
		    $wpdb->query("UPDATE {$wpdb->prefix}sb_preachers SET name = '$name', description = '$description', image = '' WHERE id = $pid");
		    @unlink($wordpressRealPath.get_option('sb_sermon_upload_dir').'images/'.mysql_real_escape_string($_POST['old']));
		}
		if(!$error) echo "<script>document.location = '$url/wp-admin/admin.php?page=sermonbrowser/preachers.php&saved=true';</script>";
	}
	
	if ($_GET['act'] == 'kill') {
		$die = (int) $_GET['pid'];
		if($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons WHERE preacher_id = $die") > 0){
		    echo '<div id="message" class="updated fade"><p><b>'.__('You can\'t delete this preacher.', $sermon_domain).'</b></div>';
		}else {
		    $p = $wpdb->get_row("SELECT image FROM {$wpdb->prefix}sb_preachers WHERE id = $die");
		    @unlink($wordpressRealPath.get_option('sb_sermon_upload_dir').'images/'.$p->image);
		    $wpdb->query("DELETE FROM {$wpdb->prefix}sb_preachers WHERE id = $die");
		}
	}	
	
	if ($_GET['act'] == 'new' || $_GET['act'] == 'edit') {
		if ($_GET['act'] == 'edit') $preacher = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}sb_preachers WHERE id = ".(int) $_GET['pid']);
?>
	<div class="wrap">
		<h2><?php echo $_GET['act'] == 'new' ? __('Add', $sermon_domain) : __('Edit', $sermon_domain) ?> <?php _e('preacher', $sermon_domain) ?></h2>
		<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="pid" value="<?php echo (int) $_GET['pid'] ?>">
		<fieldset>
			<table class="widefat">
				<tr>					
					<td>
						<strong><?php _e('Name', $sermon_domain) ?></strong>
						<div>							
							<input type="text" value="<?php echo stripslashes($preacher->name) ?>" name="name" size="60" style="width:400px;" />
						</div>
					</td>		
				</tr>
				<tr>
					<td>
						<strong><?php _e('Description', $sermon_domain) ?></strong>
						<div>
							<textarea name="description" cols="100" rows="5"><?php echo stripslashes($preacher->description) ?></textarea>
						</div>
					</td>
				</tr>
				<tr>
					<td>
						<?php if ($_GET['act'] == 'edit'): ?>
						<div><img src="<?php echo $url ?><?php echo get_option('sb_sermon_upload_dir').'images/'.$preacher->image ?>"></div>
						<input type="hidden" name="old" value="<?php echo $preacher->image ?>">
						<?php endif ?>
						<strong><?php _e('Image', $sermon_domain) ?></strong>
						<div>
							<input type="file" name="upload">
							<label>Remove image&nbsp;<input type="checkbox" name="remove" value="true"></label>
						</div>
					</td>
				</tr>
			</table>
		</fieldset>
		<p class="submit"><input type="submit" name="save" value="<?php _e('Save', $sermon_domain) ?> &raquo;" /></p> 
		</form>
	</div>
<?php
		return;
	}
	
	$preachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY name asc");
?>
	<div class="wrap">
		<h2><?php _e('Manage Preachers', $sermon_domain) ?> (<a href="<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/preachers.php&act=new"><?php _e('add new', $sermon_domain) ?></a>)</h2>
		<table class="widefat">
			<thead>
			<tr>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Name', $sermon_domain) ?></div></th>				
				<th scope="col"><div style="text-align:center"><?php _e('Image', $sermon_domain) ?></div></th>				
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</tr>
			</thead>
			<tbody>
				<?php foreach ((array) $preachers as $preacher): ?>
					<tr class="<?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>">
						<td><?php echo $preacher->id ?></td>
						<td><?php echo stripslashes($preacher->name) ?></td>
						<td><img src="<?php echo empty($preacher->image) ? '' : $url.get_option('sb_sermon_upload_dir').'images/'.$preacher->image ?>"></td>
						<td align="center">
							<a href="<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/preachers.php&act=edit&pid=<?php echo $preacher->id ?>"><?php _e('Edit', $sermon_domain) ?></a> | <a onclick="return confirm('Are you sure?')" href="<?php echo $url ?>/wp-admin/admin.php?page=sermonbrowser/preachers.php&act=kill&pid=<?php echo $preacher->id ?>"><?php _e('Delete', $sermon_domain) ?></a>
						</td>
					</tr>
				<?php endforeach ?>
			</tbody>
		</table>
	</div>
<?php
}

// manage <strike>preachers</strike>, services & series
// lots of ajaxy stuff
function sb_manage_everything() {
	global $wpdb, $sermon_domain;
	
	$url = get_bloginfo('wpurl');
	
	$series = $wpdb->get_results("SELECT ss.*, m.id AS mid FROM {$wpdb->prefix}sb_series AS ss LEFT OUTER JOIN {$wpdb->prefix}sb_sermons AS m ON ss.id = m.series_id ORDER BY ss.name asc");	
	$services = $wpdb->get_results("SELECT s.*, m.id AS mid FROM {$wpdb->prefix}sb_services AS s LEFT OUTER JOIN {$wpdb->prefix}sb_sermons AS m ON s.id = m.service_id ORDER BY s.name asc");
	
	$toManage = array(
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
				s = prompt("New service's name - default time?", old);								
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
			<tr>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Name', $sermon_domain) ?></div></th>
				<?php echo $k == 'Services' ? '<th scope="col"><div style="text-align:center">'.__('Default time', $sermon_domain).'</div></th>' : '' ?>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</tr>
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

// upload page
function sb_uploads() {
	global $wpdb, $filetypes, $sermon_domain;
	global $wordpressRealPath;
	// sync
	sb_scan_dir();
	
	$url = get_bloginfo('wpurl');
	
	if ($_POST['save']) {
		if ($_FILES['upload']['error'] == UPLOAD_ERR_OK) {
			$filename = basename($_FILES['upload']['name']);
			$prefix = '';
			$dest = $wordpressRealPath.get_option('sb_sermon_upload_dir').$prefix.$filename;
			if($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_stuff WHERE name = '$filename'") == 0) {
			    if (move_uploaded_file($_FILES['upload']['tmp_name'], $dest)) {
				    $filename = $prefix.mysql_real_escape_string($filename);
				    $query = "INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'file', '$filename', 0);";
				    $wpdb->query($query);
				    echo '<div id="message" class="updated fade"><p><b>'.__('Files saved to database.', $sermon_domain).'</b></div>';
			    }
			} else {
			    echo '<div id="message" class="updated fade"><p><b>'.__($filename. ' has already existed.', $sermon_domain).'</b></div>';
			}
		}
	}	
	
	$unlinked = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id = 0 AND f.type = 'file' ORDER BY f.name LIMIT 0, 15;");
	$linked = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id <> 0 AND f.type = 'file' ORDER BY f.name LIMIT 0, 15;");
    
	if($_POST['clean']) {
	    // sync (but different from last time)
	    $unlinked = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id = 0 AND f.type = 'file' ORDER BY f.name;");
	    $linked = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id <> 0 AND f.type = 'file' ORDER BY f.name;");
	    $wanted = array(-1);
	
	    foreach ((array) $unlinked as $k => $file) {
		    if (!file_exists($wordpressRealPath.get_option('sb_sermon_upload_dir').$file->name)) {
			    $wanted[] = $file->id;
			    unset($unlinked[$k]);
	    	}
	    }
	
	    foreach ((array) $linked as $k => $file) {		
		    if (!file_exists($wordpressRealPath.get_option('sb_sermon_upload_dir').$file->name)) {			
			    $wanted[] = $file->id;
		    	unset($unlinked[$k]);
		    }
	    }
	
	    $wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE id IN (".implode(', ', (array) $wanted).")");
	}
	
	$cntu = $wpdb->get_row("SELECT COUNT(*) as cntu FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = 0 AND type = 'file' ", ARRAY_A);
	$cntu = $cntu['cntu'];		
	$cntl = $wpdb->get_row("SELECT COUNT(*) as cntl FROM {$wpdb->prefix}sb_stuff WHERE sermon_id <> 0 AND type = 'file' ", ARRAY_A);
	$cntl = $cntl['cntl'];		
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
				<tr>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File name', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File type', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
				</tr>
			</thead>	
			<tbody id="the-list-u">
				<?php if (is_array($unlinked)): ?>
					<?php foreach ($unlinked as $file): ?>								
						<tr class="file <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="file<?php echo $file->id ?>">
							<th width="10%" style="text-align:center" scope="row"><?php echo $file->id ?></th>
							<td width="50%" id="<?php echo $file->id ?>"><?php echo substr($file->name, 0, strrpos($file->name, '.')) ?></td>
							<td width="20%" style="text-align:center"><?php echo isset($filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name']) ? $filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name'] : strtoupper(substr($file->name, strrpos($file->name, '.') + 1)) ?></td>
							<td width="20%" style="text-align:center">
								<?php //if (is_writable($wordpressRealPath.get_option('sb_sermon_upload_dir').$file->name)): ?>
								<a id="link<?php echo $file->id ?>" href="javascript:rename(<?php echo $file->id ?>, '<?php echo $file->name ?>')"><?php _e('Rename', $sermon_domain) ?></a> | <a onclick="return confirm('Do you really want to delete <?php echo str_replace("'", '', $file->name) ?>?');" href="javascript:kill(<?php echo $file->id ?>, '<?php echo $file->name ?>');"><?php _e('Delete', $sermon_domain) ?></a> 
								<?php //endif ?>
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
			<tr>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File name', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File type', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Sermon', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</tr>
			</thead>	
			<tbody id="the-list-l">
				<?php if (is_array($linked)): ?>
					<?php foreach ($linked as $file): ?>
						<tr class="file <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="file<?php echo $file->id ?>">
							<th style="text-align:center" scope="row"><?php echo $file->id ?></th>
							<td id="<?php echo $file->id ?>"><?php echo substr($file->name, 0, strrpos($file->name, '.')) ?></td>
							<td style="text-align:center"><?php echo isset($filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name']) ? $filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name'] : strtoupper(substr($file->name, strrpos($file->name, '.') + 1)) ?></td>
							<td><?php echo stripslashes($file->title) ?></td>
							<td style="text-align:center">
                            <script type="text/javascript" language="javascript">
                            function deletelinked_<?php echo $file->id;?>(filename, filesermon) {
								if (confirm('Do you really want to delete '+filename+'?')) {
									return confirm('This file is linked to the sermon called ['+filesermon+']. Are you sure you want to delete it?');
								}
								return false;
							}
                            </script>
								<?php //if (is_writable($wordpressRealPath.get_option('sb_sermon_upload_dir').$file->name)): ?>
								<a id="link<?php echo $file->id ?>" href="javascript:rename(<?php echo $file->id ?>, '<?php echo $file->name ?>')"><?php _e('Rename', $sermon_domain) ?></a> | <a onclick="return deletelinked_<?php echo $file->id;?>('<?php echo str_replace("'", '', $file->name) ?>', '<?php echo str_replace("'", '', $file->title) ?>');" href="javascript:kill(<?php echo $file->id ?>, '<?php echo $file->name ?>');"><?php _e('Delete', $sermon_domain) ?></a> 
								<?php //endif ?>
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
		$checkSermonUpload = sb_checkSermonUploadable();
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
			<p class="submit"><input type="submit" name="save" value="<?php _e('Upload', $sermon_domain) ?> &raquo;" />&nbsp;<input type="submit" name="clean" value="<?php _e('Clean up', $sermon_domain) ?>" /></p> 
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
			<tr>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File name', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File type', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Sermon', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</tr>
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

// manage sermon of coz
function sb_manage_sermons() {
	global $wpdb, $sermon_domain;

	$url = get_bloginfo('wpurl');
	
	if ($_GET['saved']) {
		echo '<div id="message" class="updated fade"><p><b>'.__('Sermon saved to database.', $sermon_domain).'</b></div>';
	}
	
	if ($_GET['mid']) {
		$mid = (int) $_GET['mid'];
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_sermons WHERE id = $mid;");
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_sermons_tags WHERE sermon_id = $mid;");
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_books_sermons WHERE sermon_id = $mid;");
		$wpdb->query("UPDATE {$wpdb->prefix}sb_stuff SET sermon_id = 0 WHERE sermon_id = $mid AND type = 'file';");
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = $mid AND type <> 'file';");
		echo '<div id="message" class="updated fade"><p><b>'.__('Sermon removed from database.', $sermon_domain).'</b></div>';
	}
	
	$cnt = $wpdb->get_row("SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons", ARRAY_A);
	$cnt = $cnt['COUNT(*)'];		
			
	$sermons = $wpdb->get_results("SELECT m.id, m.title, m.date, p.name as pname, s.name as sname, ss.name as ssname FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where (m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id) ORDER BY m.date desc, s.time desc LIMIT 0, 15;");	
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
			<tr>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Title', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Preacher', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Date', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Service', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Series', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</tr>
			</thead>	
			<tbody id="the-list">
				<?php if (is_array($sermons)): ?>
					<?php foreach ($sermons as $sermon): ?>					
					<tr class="<?php echo ++$i % 2 == 0 ? 'alternate' : '' ?>">
						<th style="text-align:center" scope="row"><?php echo $sermon->id ?></th>
						<td><?php echo stripslashes($sermon->title) ?></td>
						<td><?php echo stripslashes($sermon->pname) ?></td>
						<td><?php echo $sermon->date ?></td>
						<td><?php echo stripslashes($sermon->sname) ?></td>
						<td><?php echo stripslashes($sermon->ssname) ?></td>
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

// new sermon
// where everything is put together
function sb_new_sermon() {
	global $wpdb, $books, $sermon_domain;
	global $wordpressRealPath;
	$error = false;
	// sync
	sb_scan_dir();
	
	$url = get_bloginfo('wpurl');

	if ($_POST['save'] && $_POST['title']) {		
		// prepare
		$title = mysql_real_escape_string($_POST['title']);
		$preacher_id = (int) $_POST['preacher'];
		$service_id = (int) $_POST['service'];
		$series_id = (int) $_POST['series'];
		$time = mysql_real_escape_string($_POST['time']);
		for ($foo = 0; $foo < count($_POST['start']); $foo++) { 
			if (!empty($_POST['start']['chapter'][$foo]) && !empty($_POST['end']['chapter'][$foo]) && !empty($_POST['start']['verse'][$foo]) && !empty($_POST['end']['verse'][$foo])) {
				$startz[] = array(
					'book' => $_POST['start']['book'][$foo],
					'chapter' => $_POST['start']['chapter'][$foo],
					'verse' => $_POST['start']['verse'][$foo],					
				);
				$endz[] = array(
					'book' => $_POST['end']['book'][$foo],
					'chapter' => $_POST['end']['chapter'][$foo],
					'verse' => $_POST['end']['verse'][$foo],					
				);
			}
		}
		$start = mysql_real_escape_string(serialize($startz));
		$end = mysql_real_escape_string(serialize($endz));
		$date = date('Y-m-d', strtotime($_POST['date']));
		$description = mysql_real_escape_string(strip_tags($_POST['description']));
		$override = $_POST['override'] == 'on' ? 1 : 0;
		// edit or not edit
		if (!$_GET['mid']) { // new
			$query1 = "INSERT INTO {$wpdb->prefix}sb_sermons VALUES (null, '$title', '$preacher_id', '$date', '$service_id', '$series_id', '$start', '$end', '$description', '$time', '$override')";
			$wpdb->query($query1);				
			$id = $wpdb->insert_id;				
		} else { // edit
			$mid = (int) $_GET['mid'];
			$query1 = "UPDATE {$wpdb->prefix}sb_sermons SET title = '$title', preacher_id = '$preacher_id', date = '$date', series_id = '$series_id', start = '$start', end = '$end', description = '$description', time = '$time', service_id = '$service_id', override = '$override' WHERE id = $mid;";
			$wpdb->query($query1);
			$queryz = "UPDATE {$wpdb->prefix}sb_stuff SET sermon_id = 0 WHERE sermon_id = $mid AND type = 'file' ;";
			$wpdb->query($queryz);
			$queryz2 = "DELETE FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = $mid AND type <> 'file'; ";
			$wpdb->query($queryz2);
			$id = $mid;
		}	
		// deal with books
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_books_sermons WHERE sermon_id = $id;");	
		foreach ($startz as $i => $st) {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_books_sermons VALUES(null, '{$st['book']}', '{$st['chapter']}', '{$st['verse']}', $i, 'start', $id);");
		}
		foreach ($endz as $i => $ed) {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_books_sermons VALUES(null, '{$ed['book']}', '{$ed['chapter']}', '{$ed['verse']}', $i, 'end', $id);");
		}
		// now files
		foreach ($_POST['file'] as $uid => $file) {
			if ($_FILES['upload']['error'][$uid] == UPLOAD_ERR_OK) {
				$filename = basename($_FILES['upload']['name'][$uid]);
				$prefix = '';
				$dest = $wordpressRealPath.get_option('sb_sermon_upload_dir').$prefix.$filename;
				if ($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_stuff WHERE name = '$filename'") == 0 && move_uploaded_file($_FILES['upload']['tmp_name'][$uid], $dest)) {
					$filename = $prefix.mysql_real_escape_string($filename);
					$queryz = "INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'file', '$filename', $id);";
					$wpdb->query($queryz);					
				} else {
				    echo '<div id="message" class="updated fade"><p><b>'.__($filename. ' has already existed.', $sermon_domain).'</b></div>';
				    $error = true;
				}
			} elseif ($file != 0) {
				$wpdb->query("UPDATE {$wpdb->prefix}sb_stuff SET sermon_id = $id WHERE id = $file;");
			} 				
		}
		// then URLs
		foreach ((array) $_POST['url'] as $urlz) {
			if (!empty($urlz)) {
				$urlz = mysql_real_escape_string($urlz);
				$wpdb->query("INSERT INTO {$wpdb->prefix}sb_stuff VALUES(null, 'url', '$urlz', $id);");
			}			
		}
		// embed code next
		foreach ((array) $_POST['code'] as $code) {
			if (!empty($code)) {
				$code = base64_encode(stripslashes($code));
				$wpdb->query("INSERT INTO {$wpdb->prefix}sb_stuff VALUES(null, 'code', '$code', $id);");
			}
		}
		// tags
		$tags = explode(',', $_POST['tags']);
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_sermons_tags WHERE sermon_id = $id;");
		foreach ($tags as $tag) {
			$clean_tag = trim(mysql_real_escape_string($tag));
			$wpdb->query("INSERT IGNORE INTO {$wpdb->prefix}sb_tags VALUES (null, '$clean_tag')");
			$tag_id = $wpdb->insert_id;
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_sermons_tags VALUES (null, $id, $tag_id)");
		}
		// everything is fine, get out of here!
		if(!$error) echo "<script>document.location = '$url/wp-admin/admin.php?page=sermonbrowser/sermon.php&saved=true';</script>";
	}		
	
	// load existing data
	$preachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY name asc");
	$services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_services ORDER BY name asc");
	$series = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY name asc");
	$files = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = 0 AND type = 'file' ORDER BY name asc");	
	
	// sync
	$wanted[] = -1;
	foreach ((array) $files as $k => $file) {
		if (!file_exists($wordpressRealPath.get_option('sb_sermon_upload_dir').$file->name)) {
			$wanted[] = $file->id;
			unset($files[$k]);
		}
	}
	//$wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE id IN (".implode(', ', (array) $wanted).")");
	
	// these following code is for js
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
		$files = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_stuff WHERE sermon_id IN (0, $mid) AND type = 'file' ORDER BY name asc");
		$startArr = unserialize($curSermon->start);
		$endArr = unserialize($curSermon->end);
		$rawtags = $wpdb->get_results("SELECT t.name FROM {$wpdb->prefix}sb_sermons_tags as st LEFT JOIN {$wpdb->prefix}sb_tags as t ON st.tag_id = t.id WHERE st.sermon_id = $mid ORDER BY t.name asc");
		$tags = array();
		foreach ($rawtags as $tag) {
			$tags[] = $tag->name;
		}
		$tags = implode(', ', (array) $tags);
	}
?>
	<link rel="stylesheet" href="<?php echo $url ?>/wp-content/plugins/sermonbrowser/style.css" type="text/css">
	<link rel="stylesheet" href="<?php echo $url ?>/wp-content/plugins/sermonbrowser/datepicker.css" type="text/css">
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/jquery/jquery.js"></script>
	<script type="text/javascript" src="<?php echo $url ?>/wp-content/plugins/sermonbrowser/datePicker.js"></script>
	<script type="text/javascript" src="<?php echo $url ?>/wp-content/plugins/sermonbrowser/64.js"></script>
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
			jQuery(".choosefile", f).attr('name', 'choose' + gfid);	
			jQuery("td", f).css('display', 'none');
			jQuery("td:first", f).css('display', '');
			jQuery('th', f).prepend('[<a href="javascript:removeFile(' + gfid++ + ')">x</a>] ');
			jQuery("option[@value='0']", f).attr('selected', 'selected');
			jQuery("input", f).val('');
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
		
		function chooseType(id, type){
			jQuery("#"+id + " td").css("display", "none");
			jQuery("#"+id + " ."+type).css("display", "");
			jQuery("#"+id + " td input").val('');
			jQuery("#"+id + " td select").val('0');
		}
	</script>
	<div class="wrap">
		<h2><?php echo $_GET['mid'] ? 'Edit Sermon' : 'Add Sermon' ?></h2>
		<form method="post" enctype="multipart/form-data">
		<fieldset>
			<table class="widefat">
				<tr>
					<td>
						<strong><?php _e('Title', $sermon_domain) ?></strong>
						<div>
							<input type="text" value="<?php echo stripslashes($curSermon->title) ?>" name="title" size="60" style="width:400px;" />
						</div>
					</td>	
					<td>
						<strong><?php _e('Tags (comma separated)', $sermon_domain) ?></strong>
						<div>
							<input type="text" name="tags" value="<?php echo stripslashes($tags) ?>" style="width:400px" />
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
							<textarea name="description" cols="50" rows="7"><?php echo stripslashes($curSermon->description) ?></textarea>
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
				<tr >
					<td colspan="2">
						<table>
							<tr id="choosefile" class="choose">
								<th>
								<select class="choosefile" name="choosefile" onchange="chooseType(this.name, this.value);">
								<option value="filelist"><?php _e('Choose existing file:', $sermon_domain) ?></option>
								<option value="newupload"><?php _e('Upload a new one:', $sermon_domain) ?></option>
								<option value="newurl"><?php _e('Enter an URL:', $sermon_domain) ?></option>
								<option value="newcode"><?php _e('Enter embed code:', $sermon_domain) ?></option>
								</select>
								</th>
								<td class="filelist">
									<select id="file" name="file[]">									
									<?php echo count($files) == 0 ? '<option value="0">No files found</option>' : '<option value="0"></option>' ?>
									<?php foreach ($files as $file): ?>										
										<option value="<?php echo $file->id ?>"><?php echo $file->name ?></option>
									<?php endforeach ?>									
									</select>									
								</td>
								<td class="newupload" style="display:none"><input type="file" size="50" name="upload[]"/></td>
								<td class="newurl" style="display:none"><input type="text" size="50" name="url[]"/></td>
								<td class="newcode" style="display:none"><input type="text" size="92" name="code[]"/></td>
							</tr>
						</table>
					</td>
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
			stuff = new Array();
			type = new Array();
			start1 = new Array();
			start2 = new Array();
			start3 = new Array();
			end1 = new Array();
			end2 = new Array();
			end3 = new Array();
			
			<?php 
				$assocFiles = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = $mid AND type = 'file' ORDER BY name asc;");
				$assocURLs = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = $mid AND type = 'url' ORDER BY name asc;");
				$assocCode = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = $mid AND type = 'code' ORDER BY name asc;");	
				$r = false;
			?>
			
			<?php for ($lolz = 0; $lolz < count($assocFiles); $lolz++): ?>
				<?php $r = true ?>
				addFile();
				stuff.push(<?php echo $assocFiles[$lolz]->id ?>);
				type.push('file');
			<?php endfor ?>
			
			<?php for ($lolz = 0; $lolz < count($assocURLs); $lolz++): ?>
				<?php $r = true ?>
				addFile();
				stuff.push('<?php echo $assocURLs[$lolz]->name ?>');
				type.push('url');
			<?php endfor ?>

			<?php for ($lolz = 0; $lolz < count($assocCode); $lolz++): ?>
				<?php $r = true ?>
				addFile();
				stuff.push('<?php echo $assocCode[$lolz]->name ?>');
				type.push('code');
			<?php endfor ?>
			
			<?php if ($r): ?>
			jQuery('.choose:last').remove();
			<?php endif ?>
			
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
			
			jQuery('.choose').each(function(i) {
				switch (type[i]) {
					case 'file':
						jQuery("option[@value='filelist']", this).attr('selected', 'selected');
						jQuery('.filelist', this).css('display','');
						jQuery("option[@value='" + stuff[i] + "']", this).attr('selected', 'selected');
						break;
					case 'url':
						jQuery('td', this).css('display', 'none');
						jQuery("option[@value='newurl']", this).attr('selected', 'selected');
						jQuery('.newurl ', this).css('display','');			
						jQuery(".newurl input", this).val(stuff[i]);
						break;
					case 'code':
						jQuery('td', this).css('display', 'none');
						jQuery("option[@value='newcode']", this).attr('selected', 'selected');
						jQuery('.newcode', this).css('display','');
						jQuery(".newcode input", this).val(Base64.decode(stuff[i]));
						break;
				}
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

// looking for new files
function sb_scan_dir() {
	global $wpdb;
	global $wordpressRealPath;
	
	$files = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE type = 'file';");
	$bnn = array();
	foreach ($files as $file) {
		$bnn[] = $file->name;
	}
	
	$dir = $wordpressRealPath.get_option('sb_sermon_upload_dir');	
	if ($dh = @opendir($dir)) {
		while (false !== ($file = readdir($dh))) {
	    	if ($file != "." && $file != ".." && !is_dir($dir.$file) && !in_array($file, $bnn)) {	    		
	    		$wpdb->query("INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'file', '$file', 0);");
	       	}	
		}
	   	closedir($dh);
	}
}

// no need to explain here
function sb_checkSermonUploadable() {
	global $wordpressRealPath;
	$sermonUploadDir = $wordpressRealPath.get_option('sb_sermon_upload_dir');	
	if (is_dir($sermonUploadDir)) {
		//Dir exist
		$fp = @fopen($sermonUploadDir.'sermontest.txt', 'w');
		if ($fp) {
			//Delete this test file
			fclose($fp);
			unset($fp);
			@unlink($sermonUploadDir.'sermontest.txt');
			return 'writeable';			
		} else {
			return 'unwriteable';
		}
	} else {
		return 'notexist';
	}
	return false;
}

// manual
function sb_help() {
?>	
	<style>div.wrap h3, div.wrap h4, div.wrap h5 {margin-bottom: 0; margin-top: 2em} div.wrap p {margin-left: 2em; margin-top: 0.5em} div.wrap h3 {border-top: 1px solid #555555; padding-top: 0.5em}</style>
	<div class="wrap">
		<h2><?php _e('Help page', $sermon_domain) ?></h2>
		<h3>Screencasts</h3>
		<p>If you need help with using sermonbrowser for the first time, these five minute screencast tutorials should be your first port of call:</p>
		<ul>
			<li><a href="http://www.4-14.org.uk/sermonbrowser-tutorial/tutorial-1.html" target="_blank">Installation and Overview</a>.</li>
			<li><a href="http://www.4-14.org.uk/sermonbrowser-tutorial/tutorial-2.html" target="_blank">Basic Options</a>.</li>
			<li><a href="http://www.4-14.org.uk/sermonbrowser-tutorial/tutorial-3.html" target="_blank">Preachers, Series and Services</a>.</li>
			<li><a href="http://www.4-14.org.uk/sermonbrowser-tutorial/tutorial-4.html" target="_blank">Entering a new sermon</a>.</li>
			<li><a href="http://www.4-14.org.uk/sermonbrowser-tutorial/tutorial-5.html" target="_blank">Editing a sermon and adding embedded video</a>.</li>
		</ul>
		<h4>Template tags</h4>
		<p>If you want to change the way SermonBrowser displays on your website, you'll need to edit the templates and/or CSS file. Check out <a href="#templatetags">this guide to the template tags</a>.</p>
		<h3>Frequently asked questions</h3>
		<ul>
			<li><a href="#nosermons">I've activated the plugin, and entered in a few sermons, but they are not showing up to my website users. Where are they?</a></li>
			<li><a href="#chmod">What does the error message "Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777." mean?</a></li>
			<li><a href="#uploaderrors">SermonBrowser spends a long time attempting to upload files, but the file is never uploaded. What's happening?</a></li>
			<li><a href="#audioplayer">Why are my MP3 files are appearing as an icon, rather than as a player, as I've seen on other SermonBrowser sites?</a></li>
			<li><a href="#differentversions">How do I change the Bible version from the ESV?</a></li>
			<li><a href="#chipmunk">When using the 1pixelout audio player, my pastor sounds like a chipmunk! What's going on?</a>
			<li><a href="#sidebar">How do I get recent sermons to display in my sidebar?</a></li>
			<li><a href="#diskspace">My host only allows me a certain amount of disk space, and I have so many sermons uploaded, I've run out of space! What can I do?</a></li>
			<li><a href="#videos">How do I upload videos to SermonBrowser?</a></li>
			<li><a href="#poweredby">Can I turn off the "Powered by Sermonbrowser" link?</a></li>
			<li><a href="#publicprivate">What is the difference between the public and private podcast feeds?</a></li>
			<li><a href="#differentpodcasts">On the sermons page, what is the difference between subscribing to <b>full</b> podcast, and subscribing to a <b>custom</b> podcast?</a></li>
			<li><a href="#itunes">Why doesn't iTunes recognise the podcast links?</a></li>
			<li><a href="#sortorder">Can I change the default sort order of the sermons?</a></li>
			<li><a href="#pagenotfound">Why do I get a page not found error when I click on my podcast feed?</a></li>
			<li><a href="#changedisplay">Can I change the way sermons are displayed?</a></li>
			<li><a href="#changesearchform">The search form is too big/too small for my layout. How do I make it narrower/wider?</a></li>
			<li><a href="#bibletextmissing">Why is sometimes the Bible text missing?</a></li>
			<li><a href="#exceededquota">Why does my sermon page say I have exceeded my quota for ESV lookups?</a></li>
			<li><a href="#icons">How can I change the icons that Sermon Browser uses, or add new icons?</a></li>
		</ul>
		<hr style="width: 50%">
		<h4 id="nosermons">I've activated the plugin, and entered in a few sermons, but they are not showing up to my website users. Where are they?</h4>
		<p>SermonBrowser only displays your sermons where you choose. You need to create the page/post where you want the sermons to appear (or edit an existing one), and add <b>[sermons]</b> to the page/post. You can also add some explantory text if you wish. If you do so, the text will appear on <i>all</i> your sermons pages. If you want your text to only appear on the list of sermons, not on individual sermon pages, you need to edit the SermonBrowser templates (see below).</p>
		<h4 id="chmod">What does the error message "Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777." mean?</h4>
		<p>SermonBrowser tries to set the correct permissions on your folders for you, but sometimes restrictions mean that you have to do it yourself. You need to make sure that SermonBrowser is able to write to your sermons upload folder (usually /wp-content/uploads/sermons/). <a href="http://samdevol.com/wordpress-troubleshooting-permissions-chmod-and-paths-oh-my/" target="_blank">This tutorial</a> explains how to use the free FileZilla FTP software to do this.</p>
		<h4 id="uploaderrors">SermonBrowser spends a long time attempting to upload files, but the file is never uploaded. What's happening?</h4>
		<p>The most likely cause is that you're reaching either the maximum filesize that can be uploaded, or the maximum time a PHP script can run for. <a href="http://articles.techrepublic.com.com/5100-10878_11-5272345.html" target="_blank">Editing your php.ini</a> may help overcome these problems - but if you're on shared hosting, it's possible your host has set maximum limits you cannot change. If that's the case, you should upload your files via FTP. This is generally a better option than using your browser, particularly if you have several files to upload. If you do edit your php.ini file, these settings should be adequate:</p>
		<p style="font-family:monospace">file_uploads = On<br />
		upload_max_filesize = 15M<br />
		post_max_size = 15M<br />
		max_execution_time = 600<br/>
		max_input_time = 600<br />
		memory_limit = 16M<br /></p>
		<h4 id="audioplayer">Why are my MP3 files are appearing as an icon, rather than as a player, as I've seen on other SermonBrowser sites?</h4>
		<p>You need to install and activate the <a href="http://www.1pixelout.net/code/audio-player-wordpress-plugin/">1pixelout audio player</a> plugin. You can also customise the plugin so that its colours match your site.</p>
		<h4 id="differentversions">How do I change the Bible version from the ESV?</h4>
		<p>Five Bible versions are supported by Sermon Browser: the English Standard Version, American Standard Version, King James Version, Young's Literal Transaltion and the World English Bible. To change to one of these other versions, go to Options, and edit the single template. Replace [esvtext] with [asvtext], [kjvtext], [ylttext] or [webtext]. Thanks go to <a href="http://www.crosswaybibles.org/" target="_blank">Crossway</a> for providing access to the ESV, and <a href="http://www.lstones.com/" target="_blank">Living Stones Ministries</a> for the other versions.</p>
		<p>If you're desperate to use other versions not currently supported, you can manage it using other Wordpress plugins (albeit with reduced functionality).  However, if you're desperate to use other versions, you can manage it using other Wordpress plugins (albeit with reduced functionality). The <a href="http://wordpress.org/extend/plugins/ebibleicious/">eBibleicious</a> plugin allows for NASB, MSG, KJV, NKJV, ESV, HCSB, and NCV (use it in 'snippet' mode). However, there are three disadvantages. (1) To use it, you'll need to register for an API key (although it is free). (2) It uses Javascript so search engines won't see the Bible text, and nor will users with javascript turned off. (3) Most importantly, it only shows a maximum of four verses (the ESV shows up to 500 verses!).
		<p>You can also use the <a href="http://www.logos.com/reftagger">RefTagger</a> plugin, though this shows even fewer verses. Even worse (for our purposes) the bible passage only shows when you hover over a special link with your mouse. It does, however, provide an even longer list of translations. Please be aware that both RefTagger and eBibleicious will add bible text to bible references across your whole website, not just your sermons pages.</p>
		<p>To use either of these alternatives, just download, install and activate them as you would for any other plugin. Check their settings (make sure you enter get an API key if you're using eBiblicious). You then need to make one change to your SermonBrowser options. In the <i>Single Sermon form</i>, look for <b>[esvtext]</b> and replace it with <b>[biblepassage]</b>. (By default it's right at the end of the code.)</p>
		<h4 id="chipmunk">When using the 1pixelout audio player, my pastor sounds like a chipmunk! What's going on?</h4>
		<p>This 'feature' is caused by a well-known bug in Adobe flash. In order for the files to play correctly, when they are saved, the sample rate needs to be set at a multiple of 11.025kHz (i.e. 11.025, 22.05 or 44.1).</p>
		<h4 id="sidebar">How do I get recent sermons to display in my sidebar?</h4>
		<p>If your WordPress theme supports widgets, just go to Design and choose <a href="widgets.php">Widgets</a>. There you easily can add the Sermons widget to your sidebar. If your theme doesn't support widgets, you'll need to edit your theme manually. Usually, you'll be editing a file called <b>sidebar.php</b>, but your theme may give it a different name. Add the following code:</p>
		<p style="font-family:monospace">&lt;?php if (function_exists('display_sermons')) display_sermons(array('display_preacher' => 0, 'display_passage' => 1, 'preacher' => 0, 'service' => 0, 'series' => 0, 'limit' => 5)) ?></code>
		<p>Each of the numbers in that line can be changed. <b>display_preacher</b> and <b>display_passage</b> affect what is displayed (0 is off, 1 is on). <b>preacher</b>, <b>service</b> and <b>series</b> allow you to limit the output to a particular preacher, service or series. Simply change the number of the ID of the preacher/services/series you want to display. You can get the ID from the Preachers page, or the Series & Services page. 0 shows all preachers/services/series. <b>limit</b> is simply the maximum number of sermons you want displayed.</p>
		<h4 id="diskspace">My host only allows me a certain amount of disk space, and I have so many sermons uploaded, I've run out of space! What can I do?</h4>
		<p>You could, of course, change your host to someone a little more generous! I use <a href="http://www.vortechhosting.com/shared/windows.php">VortechHosting</a> for low traffic sites (5Gb of disk space for less than $10 a month), and <a href="https://www.liquidweb.com/cart/content/vps/">LiquidWeb VPS</a> for higher traffic sites (20Gb disk space for $60 a month). You should also make sure you encode your sermons at a medium to high compression. Usually, 22.05kHz, 48kbps mono is more than adequate (you could probably go down to 32kbps for even higher compression). 48kbps means every minute of recording takes up 360kb of disk space, so a thirty minute sermon will just over 10Mb. At this setting, 5Gb would be enough for over 450 sermons.</p>
		<p>If you can't change your host, you can still use SermonBrowser. You'll just have to upload your sermon files to another site - preferably a free one! We recommend <a href="http://www.odeo.com/" target="blank">Odeo</a>. If you want to use Odeo's audio player on your website, copy the embed code they give you, and when you add your sermon to SermonBrowser, select "Enter embed code:" and paste it in. If you want to use the standard 1pixelout audio player, copy the "Download MP3" link Odeo give you, and when you add your sermon to SermonBrowser, select "Enter an URL" and paste it in.</p>
		<h4 id="videos">How do I upload videos to SermonBrowser?</h4>
		<p>You can't - but you can upload videos to other sites, then embed them in your sermons. You can use any site that allows you to embed your video in other websites, including <a href="http://www.youtube.com/">YouTube</a>, but we recommend <a href="http://video.google.com/videouploadform">GoogleVideo</a> as the most suitable for sermons. That's because most video-sharing sites are designed for relatively short clips of 10 minutes or so, but GoogleVideo will accept videos of any length - and there are no quotas for the maximum size of a video, nor the number of videos you can store. Once your video is uploaded and available on Google Video, you can copy the embed code it gives you, edit your sermon, select "Enter embed code" and paste it in.</p>
		<h4 id="poweredby">Can I turn off the "Powered by Sermonbrowser" link?</h4>
		<p>The link is there so that people from other churches who listen to your sermons can find out about SermonBrowser themselves. But if you'd like to remove the link, just remove <b>[creditlink]</b> from the templates in SermonBrowser Options</a>.</p>
		<h4 id="publicprivate">What is the difference between the public and private podcast feeds?</h4>
		<p>In SermonBrowser options, you are able to change the address of the public podcast feed. This is the feed that is shown on your sermons page, and is usually the same as your private feed. However, if you use a service such as <a href="http://www.feedburner.com/" target="_blank">FeedBurner</a>, you can use your public feed to send data to feedburner, and change your private feed to your Feedburner address. If you do not use a service like Feedburner, just make sure your public and private feeds are the same.</p> 
		<h4 id="differentpodcasts">On the sermons page, what is the difference between subscribing to our podcast, and subscribing to a podcast for this search?</h4>
		<p>The link called <strong>subscribe to full podcast</strong> gives a podcast of <em>all</em> sermons that you add to your site through SermonBrowser. But it may be that some people may just want to subscribe to a feed for certain speakers, or for a certain service. If they wish to do this, they should set the search filters and perform their search, then click on the <strong>Subscribe to custom podcast </strong>link. This will give them a podcast according to the filter they selected. You could also copy this link, and display it elsewhere on the site - for example to provide separate feeds for morning and evening services.</p>
		<h4 id="iTunes">Why doesn't iTunes recognise the podcast links?</h4>
		<p>iTunes requires its own special links that are slightly different from other podcasting software. If you would like to display these links, you need to edit your template and add the tags [itunes_podcast] and [itunes_podcast_for_search].</p>
		<h4 id="sortorder">Can I change the default sort order of the sermons?</h4>
		<p>Unfortunately not. Unless the viewer specified otherwise, Sermonbrowser always displays the most recent sermons at the top.</p>
		<h4 id="pagenotfound">Why do I get a page not found error when I click on my podcast feed?</h4>
		<p>You've probably changed the address of your public feed. Try changing it back to the same value as your private feed in Sermon Options.</p>
		<h4 id="changedisplay">Can I change the way sermons are displayed?</h4>
		<p>Yes, definately, although you need to know a little HTML and/or CSS. SermonBrowser has a powerful templating function, so you can exclude certain parts of the output (e.g. if you don't want the links to other sermons preached on the same day to be displayed). To edit the templates, go to SermonBrowser Options. Below is a reference for all the <a href="templatetags">template tags</a> you need. If you just want to change the way the output looks, without changing what is displayed, you need to edit the CSS stylesheet, also in SermonBrowser Options. (See one example, below).</p>
		<h4 id="changesearchform">The search form is too big/too small for my layout. How do I make it narrower/wider?</h4>
		<p>The search form is set to roughly 500 pixels, which should be about right for most WordPress templates. To change it, look for a line in the CSS stylesheet that begins <b>table.sermonbrowser td.field input</b>, and change the width specified after it. To make the form narrower, reduce the width. To make it bigger, increase the width. You'll also need to change the width of the date fields on the line below, which should be 20 pixels smaller.</p>
		<h4 id="bibletextmissing">Why is sometimes the Bible text missing?</h4>
		<p>This usually happens for one of three reasons: (1) If the website providing the service is down. If you can't see Genesis 1 in the <a href="http://www.esvapi.org/v2/rest/passageQuery?key=IP&amp;passage=Gen+1&amp;include-headings=false">ESV</a> or <a href="http://api.seek-first.com/v1/BibleSearch.php?type=lookup&appid=seekfirst&startbooknum=1&startchapter=1&startverse=1&endbooknum=1&endchapter=1&endverse=30&version=KJV">the other versions</a>then the problem is with those websites. They're rarely down for long. (2) If you specify an invalid bible passage (e.g. Romans 22). If this is the case your sermon page will display <em>ERROR: No results were found for your search.</em> (3) If your webhost has disabled <strong>allow_url_fopen</strong> and cURL. Some cheaper webhosts have these essential features switched off. If they have, you won't be able to use this facility.</p>
		<h4 id="exceededquota">Why does my sermon page say I have exceeded my quota for ESV lookups?</h4>
		<p>The ESV website only allows 5,000 lookups per day from each IP address. That should be enough for most users of SermonBrowser. However, if you are using a shared host, there will be hundreds (perhaps thousands) of other websites on the same IP address as you. If any are also using the ESV API, they also get counted towards that total. If you are using less than 5,000 lookups per day (i.e. you are having less than 5,000 pageviews of your sermon pages), and you receive the error message you'll need to do two things in order to continue to display the text. (1) Sign up for an <a href="http://www.esvapi.org/signup">ESV API key</a>. (2) Edit frontend.php (one of the SermonBrowser files). Look for line 66, and replace <i>&hellip;passageQuery?key=<b>IP</b>&passage=&hellip;</i> with <i>&hellip;passageQuery?key=<b>YOURAPIKEY</b>&passage=&hellip;</i>.</p>
		<p>If you <i>are</i> having more than 5,000 page views per day, then this won't help. Instead, leave a message in the <a href="http://www.4-14.org.uk/sermon-browser#comments">SermonBrowser comments</a> explaining your problem. SermonBrowser could probably be modified to provide a caching mechanism to reduce the likelihood of this error occurring, if there is demand.</p>
		<h4 id="icons">How can I change the file icons that Sermon Browser uses, or add new icons?</h4>
		<p>You'll need to edit the <b>filetypes.php</b> file that comes with Sermon Browser. The icon is chosen on the basis of the file extension (or in the case of URLs the file extension then the site address). If you do create new icons for other filetypes, consider sending them to the author so they can be included in future versions of the plugin.</p>
		<h3 id="templatetags">Template tags</h3>
		<p>If you want to change the output of Sermon Browser, you'll need to edit the templates. You'll need to understand the basics of HTML and CSS, and to know the special SermonBrowser template tags. There are two templates, one (called "results page") is used to produce the search results on the main sermons page. The other template (called sermon page) is used to produce the page for single sermon. Most tags can be used in both templates, but some are specific.</p>
		<h4>Results page only</h4>
		<ul>
			<li><b>[filters_form]</b> - The search form which allows filtering by preacher, series, date, etc. <i>multi-sermons page only</i></li>
			<li><b>[sermons_count]</b> - The number of sermons which match the current search critera. </li>
			<li><b>[sermons_loop][/sermons_loop]</b> - These two tags should be placed around the output for one sermon. (That is all of the tags that return data about sermons should come between these two tags.)</li>
			<li><b>[first_passage]</b> - The main bible passage for this sermon</li>
			<li><b>[previous_page]</b> - Displays the link to the previous page of search results (if needed)</li>
			<li><b>[next_page]</b> - Displays the link to the next page of search results (if needed)</li>
			<li><b>[podcast]</b> - Link to the podcast of all sermons</li>
			<li><b>[podcast_for_search]</b> - Link to the podcast of sermons that match the current search</li>
			<li><b>[itunes_podcast]</b> - iTunes (itpc://) link to the podcast of all sermons</li>
			<li><b>[itunes_podcast_for_search]</b> - iTunes (itpc://) link to the podcast of sermons that match the current search</li>
			<li><b>[podcasticon]</b> - Displays the icon used for the main podcast</li>
			<li><b>[podcasticon_for_search]</b> - Displays the icon used for the custom podcast</li>
		</ul>
		<h4>Both results page and sermon page</h4>
		<ul>
			<li><b>[sermon_title]</b> - The title of the sermon</li>
			<li><b>[preacher_link]</b> - The name of the preacher (hyperlinked to his search results)</li>
			<li><b>[series_link]</b> - The name of the series (hyperlinked to search results)</li>
			<li><b>[service_link]</b> - The name of the service (hyperlinked to search results)</li>
			<li><b>[date]</b> - The date of the sermon</li>
			<li><b>[files_loop][/files_loop]</b> - These two tags should be placed around the [file] tag if you want to display all the files linked with to sermon. They are not needed if you only want to display the first file.</li>
			<li><b>[file]</b> - Displays the files and external URLs</li>
			<li><b>[embed_loop][/embed_loop]</b> - These two tags should be placed around the [embed] tag if you want to display all the embedded objects linked to this sermon. They are not needed if you only want to display the first embedded object.</li>
			<li><b>[embed]</b> - Displays an embedded object (e.g. video)</li>
			<li><b>[creditlink]</b> - displays a "Powered by Sermon Browser" link.</li>
		</ul>
		<h4>Sermon page only</h4>
		<ul>
			<li><b>[preacher_description]</b> - The description of the preacher.</li>
			<li><b>[preacher_image]</b> - The photo of the preacher.</li>
			<li><b>[passages_loop][/passages_loop]</b> - These two tags should be placed around the [passage] tag if you want to display all the passages linked with to sermon.</li>
			<li><b>[passage]</b> - Displays the reference of the bible passage with the book name hyperlinked to search results.</li>
			<li><b>[next_sermon]</b> - Displays a link to the next sermon preached (excluding ones preached on the same day)</li>
			<li><b>[prev_sermon]</b> - Displays a link to the previous sermon preached</li>
			<li><b>[sameday_sermon]</b> - Displays a link to other sermons preached on that day</li>
			<li><b>[tags]</b> - Displays the tags for that sermons</li>
			<li><b>[esvtext]</b> - Displays the full text of the ESV Bible for all passages linked to that sermon.</li>
			<li><b>[asvtext]</b> - Displays the full text of the ASV Bible for all passages linked to that sermon.</li>
			<li><b>[kjvtext]</b> - Displays the full text of the KJV Bible for all passages linked to that sermon.</li>
			<li><b>[ylttext]</b> - Displays the full text of the YLT Bible for all passages linked to that sermon.</li>
			<li><b>[webtext]</b> - Displays the full text of the WEB Bible for all passages linked to that sermon.</li>
			<li><b>[biblepassage]</b> - Displays the reference of the bible passages for that sermon. Useful for utilising other bible plugins (see <a href="#otherversions">FAQ</a>).</li>
	</div>
	</form>
<?php 
}

function sb_default_multi_template () {
$multi = <<<HERE
<div class="sermon-browser">
	<h2>Filters</h2>		
	[filters_form]
   	<div style="clear:both"><div class="podcastcustom"><a href="[podcast_for_search]">[podcasticon_for_search]</a><span><a href="[podcast_for_search]">Subscribe to custom podcast</a></span><br />(new sermons that match this <b>search</b>)</div><div class="podcastall"><a href="[podcast]">[podcasticon]</a><span><a href="[podcast]">Subscribe to full podcast</a></span><br />(<b>all</b> new sermons)</div>
</div>
	<h2>Sermons ([sermons_count])</h2>   	
   	<div class="floatright">[next_page]</div>
   	<div class="floatleft">[previous_page]</div>
	<table class="sermons">
	[sermons_loop]	
		<tr>
			<td class="sermon-title">[sermon_title]</td>
		</tr>
		<tr>
			<td class="sermon-passage">[first_passage] (Part of the [series_link] series).</td>
		</tr>
		<tr>
			<td class="files">[files_loop][file][/files_loop]</td>
		</tr>
		<tr>
			<td class="urls">[urls_loop][url][/urls_loop]</td>
		</tr>
		<tr>
			<td class="embed">[embed_loop][embed][/embed_loop]</td>
		</tr>
		<tr>
			<td class="preacher">Preached by [preacher_link] on [date] ([service_link]).</td>
		</tr>
   	[/sermons_loop]
	</table>
   	<div class="floatright">[next_page]</div>
   	<div class="floatleft">[previous_page]</div>
   	[creditlink]
</div>
HERE;
	return $multi;
}

function sb_default_single_template () {
$single = <<<HERE
<div class="sermon-browser-results">
	<h2>[sermon_title] <span class="scripture">([passages_loop][passage] [/passages_loop])</span></h2>
	[preacher_image]<span class="preacher">[preacher_link], [date]</span><br />
	Part of the [series_link] series, preached at a [service_link] service<br />
	Tags: [tags]<br />
	[files_loop]	
		[file]  
	[/files_loop]
	[urls_loop]
		[url]  
	[/urls_loop]
	[embed_loop]
		<br />[embed]<br />
	[/embed_loop]
	[preacher_description]
	<table class="nearby-sermons">
		<tr>
			<th class="earlier">Earlier:</th>
			<th>Same day:</th>
			<th class="later">Later:</th>
		</tr>
		<tr>
			<td class="earlier">[prev_sermon]</td>
			<td>[sameday_sermon]</td>
			<td class="later">[next_sermon]</td>
		</tr>
	</table>
	[esvtext]
   	[creditlink]
</div>
HERE;
	return $single;
}

function sb_default_css () {
$css = <<<HERE
.sermon-browser h2 {
	clear: both;
}

div.sermon-browser table.sermons {
	width: 100%;
	clear:both;
}

div.sermon-browser table.sermons td.sermon-title {
	font-weight:bold;
	font-size: 140%;
	padding-top: 2em;
}

div.sermon-browser table.sermons td.sermon-passage {
	font-weight:bold;
	font-size: 110%;
}

div.sermon-browser table.sermons td.preacher {
	border-bottom: 1px solid #444444;
}

div.sermon-browser table.sermons td.files img {
	border: none;
	margin-right: 24px;
}

table.sermonbrowser td.fieldname {
	font-weight:bold;
	padding-right: 10px;
	vertical-align:bottom;
}

table.sermonbrowser td.field input, table.sermonbrowser td.field select{
	width: 170px;
}

table.sermonbrowser td.field  #date, table.sermonbrowser td.field #enddate {
	width: 150px;
}

table.sermonbrowser td {
	white-space: nowrap;
	padding-top: 5px;
	padding-bottom: 5px;
}

table.sermonbrowser td.rightcolumn {
	padding-left: 10px;
}

div.sermon-browser div.floatright {
	float: right
}

div.sermon-browser div.floatleft {
	float: left
}

img.sermon-icon , img.site-icon {
	border: none;
}

div.podcastall {
	float:left;
	border: 2px solid #FC9328;
	background: #fff0c8 url(icons/podcast_background.png) repeat-x;
	padding: 0.3em;
}

div.podcastall img.podcasticon, div.podcastcustom img.podcasticon {
	float:left;
	margin-right: 1em;
	border: none;
}

div.podcastall span a{
	color: #FC9328;
	font-weight: bold;
	font-size:125%;
}

div.podcastcustom {
	float:right;
	border: 2px solid #b83ee5;
	background: #fce4ff url(icons/podcast_custom_background.png) repeat-x;
	padding: 0.3em;
}
div.podcastcustom span a{
	color: #b83ee5;
	font-weight: bold;
	font-size:125%;
}

div.sermon-browser-results span.preacher {
	font-size: 120%;
}

div.sermon-browser-results span.scripture {
	font-size: 80%;
}

div.sermon-browser-results img.preacher {
	float:right;
	margin-left: 1em;
}

div.sermon-browser-results div.preacher-description {
	margin-top: 0.5em;
}

div.sermon-browser-results div.preacher-description span.about {
	font-weight: bold;
	font-size: 120%;
}

span.chapter-num {
	font-weight: bold;
	font-size: 150%;
}

span.verse-num {
	vertical-align:super;
	line-height: 1em;
	font-size: 65%;
}

div.esv span.small-caps {
	font-variant: small-caps;
}


div.sermon-browser #poweredbysermonbrowser {
	text-align:center;
}
div.sermon-browser-results #poweredbysermonbrowser {
	text-align:right;
}

table.nearby-sermons {
	width: 100%;
	clear:both;
}

table.nearby-sermons td, table.nearby-sermons th {
	text-align: center;
}

table.nearby-sermons .earlier {
	padding-right: 1em;
	text-align: left;
}

table.nearby-sermons .later {
	padding-left: 1em;
	text-align:right;
}

table.nearby-sermons td {
	width: 33%;
	vertical-align: top;
}

ul.sermon-widget {
	list-style-type:none;
	margin:0;
	padding: 0;
}

ul.sermon-widget li {
	list-style-type:none;
	margin:0;
	padding: 0.25em 0;
}

ul.sermon-widget li span.sermon-title{
	font-weight:bold;
}
HERE;
   return $css;
}

// Hooks 
add_action('admin_menu', 'sb_add_pages');
register_activation_hook(__FILE__,'sb_sermon_install');
?>