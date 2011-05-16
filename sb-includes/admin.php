<?php
/**
* Admin functions
*
* Functions required exclusively in the back end.
* @package admin_functions
*/


/**
* Adds javascript and CSS where required in admin
*/
function sb_add_admin_headers() {
	if (isset($_REQUEST['page']) && substr($_REQUEST['page'],14) == 'sermon-browser')
		wp_enqueue_script('jquery');
	if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'sermon-browser/new_sermon.php') {
		wp_enqueue_script('sb_datepicker');
		wp_enqueue_script('sb_64');
		wp_enqueue_style ('sb_datepicker');
		wp_enqueue_style ('sb_style');
	}
}

/**
* Display the options page and handle changes
*/
function sb_options() {
	global $wpdb, $sermon_domain;
	//Security check
	if (!current_user_can('manage_options'))
			wp_die(__("You do not have the correct permissions to edit the SermonBrowser options", $sermon_domain));
	//Reset options to default
	if (isset($_POST['resetdefault'])) {
		$dir = sb_get_default('sermon_path');
		if (sb_display_url()=="#") {
			sb_update_option('podcast_url', site_url().sb_query_char(false).'podcast');
		} else {
			sb_update_option('podcast_url', sb_display_url().sb_query_char(false).'podcast');
		}
		sb_update_option('upload_dir', $dir);
		sb_update_option('upload_url', sb_get_default('attachment_url'));
		sb_update_option('display_method', 'dynamic');
		sb_update_option('sermons_per_page', '10');
		sb_update_option('filter_type', 'oneclick');
		sb_update_option('filter_hide', 'hide');
		sb_update_option('hide_no_attachments', false);
		sb_update_option('mp3_shortcode', '[audio:%SERMONURL%]');
		   if (!is_dir(SB_ABSPATH.$dir))
			if (sb_mkdir(SB_ABSPATH.$dir))
				@chmod(SB_ABSPATH.$dir, 0777);
		if(!is_dir(SB_ABSPATH.$dir.'images') && sb_mkdir(SB_ABSPATH.$dir.'images'))
			@chmod(SB_ABSPATH.$dir.'images', 0777);
		$books = sb_get_default('bible_books');
		$eng_books = sb_get_default('eng_bible_books');
		// Reset bible books database
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sb_books");
		for ($i=0; $i < count($books); $i++) {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_books VALUES (null, '$books[$i]')");
			$wpdb->query("UPDATE {$wpdb->prefix}sb_books_sermons SET book_name='{$books[$i]}' WHERE book_name='{$eng_books[$i]}'");
		}
		// Rewrite booknames for non-English locales
		if ($books != $eng_books) {
			$sermon_books = $wpdb->get_results("SELECT id, start, end FROM {$wpdb->prefix}sb_sermons");
			 foreach ($sermon_books as $sermon_book) {
				$start_verse = unserialize($sermon_book->start);
				$end_verse = unserialize($sermon_book->end);
				$start_index = array_search($start_verse[0]['book'], $eng_books, TRUE);
				$end_index = array_search($end_verse[0]['book'], $eng_books, TRUE);
				if ($start_index !== FALSE)
					$start_verse[0]['book'] = $books[$start_index];
				if ($end_index !== FALSE)
					$end_verse[0]['book'] = $books[$end_index];
				$sermon_book->start = serialize ($start_verse);
				$sermon_book->end = serialize ($end_verse);
				$wpdb->query("UPDATE {$wpdb->prefix}sb_sermons SET start='{$sermon_book->start}', end='{$sermon_book->end}' WHERE id={$sermon_book->id}");
			}
		}

		   $checkSermonUpload = sb_checkSermonUploadable();
		   switch ($checkSermonUpload) {
			case "unwriteable":
				echo '<div id="message" class="updated fade"><p><b>';
				if (IS_MU AND !is_site_admin()) {
					_e('Upload is disabled. Please contact your administrator.', $sermon_domain);
				} else {
					_e('Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777.', $sermon_domain);
				}
				echo '</b></div>';
				break;
			case "notexist":
				echo '<div id="message" class="updated fade"><p><b>';
				if (IS_MU AND !is_site_admin()) {
					_e('Upload is disabled. Please contact your administrator.', $sermon_domain);
				} else {
					_e('Error: The upload folder you have specified does not exist.', $sermon_domain);
				}
				echo '</b></div>';
				break;
			default:
				echo '<div id="message" class="updated fade"><p><b>';
				_e('Default loaded successfully.', $sermon_domain);
				echo '</b></div>';
				break;
			}
	}
	// Save options
	elseif (isset($_POST['save'])) {
		$dir = rtrim(str_replace("\\", "/", $_POST['dir']), "/")."/";
		sb_update_option('podcast_url', stripslashes($_POST['podcast']));
		if (intval($_POST['perpage']) > 0)
			sb_update_option('sermons_per_page', intval($_POST['perpage']));
		if (intval($_POST['perpage']) == -100)
			update_option('show_donate_reminder', 'off');
		sb_update_option('upload_dir', $dir);
		sb_update_option('filter_type', $_POST['filtertype']);
		sb_update_option('filter_hide', isset($_POST['filterhide']));
		sb_update_option('upload_url', trailingslashit(site_url()).$dir);
		sb_update_option ('import_prompt', isset($_POST['import_prompt']));
		sb_update_option ('import_title', isset($_POST['import_title']));
		sb_update_option ('import_artist', isset($_POST['import_artist']));
		sb_update_option ('import_album', isset($_POST['import_album']));
		sb_update_option ('import_comments', isset($_POST['import_comments']));
		sb_update_option ('import_filename', stripslashes($_POST['import_filename']));
		sb_update_option ('hide_no_attachments', isset($_POST['hide_no_attachments']));
		sb_update_option('mp3_shortcode', stripslashes($_POST['mp3_shortcode']));
		if (!is_dir(SB_ABSPATH.$dir))
			if (sb_mkdir(SB_ABSPATH.$dir))
				@chmod(SB_ABSPATH.$dir, 0777);
		if(!is_dir(SB_ABSPATH.$dir.'images') && sb_mkdir(SB_ABSPATH.$sermonUploadDir.'images'))
			@chmod(SB_ABSPATH.$dir.'images', 0777);
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
			_e('Options saved successfully.', $sermon_domain);
			echo '</b></div>';
			break;
	   }
	}

	//Display error messsages when problems in php.ini
	function sb_display_error ($message) {
		global $sermon_domain;
		return	'<tr><td align="right" style="color:#AA0000; font-weight:bold">'.__('Error', $sermon_domain).':</td>'.
				'<td style="color: #AA0000">'.$message.'</td></tr>';
	}
	//Display warning messsages when problems in php.ini
	function sb_display_warning ($message) {
		global $sermon_domain;
		return	'<tr><td align="right" style="color:#FFDC00; font-weight:bold">'.__('Warning', $sermon_domain).':</td>'.
				'<td style="color: #FF8C00">'.$message.'</td></tr>';
	}
	sb_do_alerts();
	// HTML for options page
?>
	<div class="wrap">
		<a href="http://www.sermonbrowser.com/"><img src="<?php echo SB_PLUGIN_URL; ?>/sb-includes/logo-small.png" width="191" height ="35" style="margin: 1em 2em; float: right; background: #f9f9f9;" /></a>
		<form method="post">
		<h2><?php _e('Basic Options', $sermon_domain) ?></h2>
		<br style="clear:both"/>
		<table border="0" class="widefat">
			<?php
				if (!IS_MU OR is_site_admin()) {
			?>
			<tr>
				<td align="right" style="vertical-align:middle"><?php _e('Upload folder', $sermon_domain) ?>: </td>
				<td><input type="text" name="dir" value="<?php echo htmlspecialchars(sb_get_option('upload_dir')) ?>" style="width:100%" /></td>
			</tr>
			<?php
				} else {
			?>
				<input type="hidden" name="dir" value="<?php echo htmlspecialchars(sb_get_option('upload_dir')) ?>">
			<?php
				}
			?>
			<tr>
				<td align="right" style="vertical-align:middle"><?php _e('Public podcast feed', $sermon_domain) ?>: </td>
				<td><input type="text" name="podcast" value="<?php echo htmlspecialchars(sb_get_option('podcast_url')) ?>" style="width:100%" /></td>
			</tr>
			<tr>
				<td align="right"><?php _e('Private podcast feed', $sermon_domain) ?>: </td>
				<td><?php if (sb_display_url()=='') { echo htmlspecialchars(site_url()); } else { echo htmlspecialchars(sb_display_url()); } echo sb_query_char(); ?>podcast</td>
			</tr>
			<tr>
				<td align="right" style="vertical-align:middle"><?php _e('MP3 shortcode', $sermon_domain) ?>: </td>
				<td><input type="text" name="mp3_shortcode" value="<?php echo htmlspecialchars(sb_get_option('mp3_shortcode')) ?>" style="width:100%" /></td>
			</tr>
			<tr>
				<td align="right" style="vertical-align:middle"><?php _e('Sermons per page', $sermon_domain) ?>: </td>
				<td><input type="text" name="perpage" value="<?php echo sb_get_option('sermons_per_page') ?>" /></td>
			</tr>
			<tr>
				<td align="right" style="vertical-align:top" rowspan="2"><?php _e('Filter type', $sermon_domain) ?>: </td>
				<td>
				<?php
					$ft = sb_get_option('filter_type');
					$filter_options = array ('dropdown' => __('Drop-down', $sermon_domain), 'oneclick' => __('One-click', $sermon_domain), 'none' => __('None', $sermon_domain));
					foreach ($filter_options as $value => $filter_option) {
						echo "<input type=\"radio\" name=\"filtertype\" value=\"{$value}\" ";
						if ($ft == $value)
							echo 'checked="checked" ';
						echo "/> {$filter_option}<br/>\n";
					}
				?>
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="filterhide" <?php if (sb_get_option('filter_hide') == 'hide') echo 'checked="checked" '; ?> value="hide" \> <?php _e('Minimise filter', $sermon_domain); ?>
				</td>
			</tr>
			<tr>
				<td align="right"><?php _e('Hide sermons without attachments?', $sermon_domain) ?></td>
				<td><input type="checkbox" name="hide_no_attachments" <?php if (sb_get_option('hide_no_attachments')) echo 'checked="checked" '?> value="1" \></td>
			</tr>
			<?php
				$allow_uploads = ini_get('file_uploads');
				$max_filesize = sb_return_kbytes(ini_get('upload_max_filesize'));
				$max_post = sb_return_kbytes(ini_get('post_max_size'));
				$max_execution = ini_get('max_execution_time');
				$max_input = ini_get('max_input_time');
				$max_memory = sb_return_kbytes(ini_get('memory_limit'));
				$checkSermonUpload = sb_checkSermonUploadable();
				if (IS_MU) {
					if ($checkSermonUpload=="unwriteable")
						echo sb_display_error (__('The upload folder is not writeable. You need to specify a folder that you have permissions to write to.', $sermon_domain));
					elseif ($checkSermonUpload=="notexist")
						sb_display_error (__('The upload folder you have specified does not exist.', $sermon_domain));
					if ($allow_uploads == '0') echo sb_display_error(__('Your administrator does not allow file uploads. You will need to upload via FTP.', $sermon_domain));
					$max_filesize = ($max_filesize < $max_post) ? $max_filesize : $max_post;
					if ($max_filesize < 15360) echo sb_display_warning(__('The maximum file size you can upload is only ', $sermon_domain).$max_filesize.__('k. You may need to upload via FTP.', $sermon_domain));
					$max_execution = (($max_execution < $max_input) || $max_input == -1) ? $max_execution : $max_input;
					if ($max_execution < 600) echo sb_display_warning(__('The maximum time allowed for any script to run is only ', $sermon_domain).$max_execution.__(' seconds. If your files take longer than this to upload, you will need to upload via FTP.', $sermon_domain));
				} else {
					if ($checkSermonUpload=="unwriteable")
						echo sb_display_error (__('The upload folder is not writeable. You need to specify a folder that you have permissions to write to, or CHMOD this folder to 666 or 777.', $sermon_domain));
					elseif ($checkSermonUpload=="notexist")
						sb_display_error (__('The upload folder you have specified does not exist.', $sermon_domain));
					if ($allow_uploads == '0') echo sb_display_error(__('Your php.ini file does not allow uploads. Please change file_uploads in php.ini.', $sermon_domain));
					if ($max_filesize < 15360) echo sb_display_warning(__('The maximum file size you can upload is only ', $sermon_domain).$max_filesize.__('k. Please change upload_max_filesize to at least 15M in php.ini.', $sermon_domain));
					if ($max_post < 15360) echo sb_display_warning(__('The maximum file size you send through the browser is only ', $sermon_domain).$max_post.__('k. Please change post_max_size to at least 15M in php.ini.', $sermon_domain));
					if ($max_execution < 600) echo sb_display_warning(__('The maximum time allowed for any script to run is only ', $sermon_domain).$max_execution.__(' seconds. Please change max_execution_time to at least 600 in php.ini.', $sermon_domain));
					if ($max_input < 600 && $max_input != -1) echo sb_display_warning(__('The maximum time allowed for an upload script to run is only ', $sermon_domain).$max_input.__(' seconds. Please change max_input_time to at least 600 in php.ini.', $sermon_domain));
					if ($max_memory < 16384) echo sb_display_warning(__('The maximum amount of memory allowed is only ', $sermon_domain).$max_memory.__('k. Please change memory_limit to at least 16M in php.ini.', $sermon_domain));
				}
			?>
		</table>
		<h2><?php _e('Import Options', $sermon_domain) ?></h2>
		<p><?php printf(__('SermonBrowser can speed up the process of importing existing MP3s by reading the information stored in each MP3 file and pre-filling the SermonBrowser fields. Use this section to specify what information you want imported into SermonBrowser. Once you have selected the options, go to %s to import your files.', $sermon_domain), '<a href="'.admin_url('admin.php?page=sermon-browser/files.php').'">'.__('Files', $sermon_domain).'</a>') ?>
		<table border="0" class="widefat">
			<tr>
				<td align="right"><?php _e('Add files prompt to top of Add Sermon page?', $sermon_domain) ?></td>
				<td><input type="checkbox" name="import_prompt" <?php if (sb_get_option('import_prompt')) echo 'checked="checked" '?> value="1" \></td>
			</tr>
			<tr>
				<td align="right"><?php _e('Use title tag for sermon title?', $sermon_domain) ?></td>
				<td><input type="checkbox" name="import_title" <?php if (sb_get_option('import_title')) echo 'checked="checked" '?> value="1" \></td>
			</tr>
			<tr>
				<td align="right"><?php _e('Use artist tag for preacher?', $sermon_domain) ?></td>
				<td><input type="checkbox" name="import_artist" <?php if (sb_get_option('import_artist')) echo 'checked="checked" '?> value="1" \></td>
			</tr>
			<tr>
				<td align="right"><?php _e('Use album tag for series?', $sermon_domain) ?></td>
				<td><input type="checkbox" name="import_album" <?php if (sb_get_option('import_album')) echo 'checked="checked" '?> value="1" \></td>
			</tr>
			<tr>
				<td align="right"><?php _e('Use comments tag for sermon description?', $sermon_domain) ?></td>
				<td><input type="checkbox" name="import_comments" <?php if (sb_get_option('import_comments')) echo 'checked="checked" '?> value="1" \></td>
			</tr>
			<tr>
				<td align="right" style="vertical-align: middle"><?php _e('Attempt to extract date from filename', $sermon_domain) ?></td>
				<td style="vertical-align: middle"><select name="import_filename">
				<?php
					$filename_options = array ('none' => __('Disabled', $sermon_domain),
											   'uk' => __('UK-formatted date (dd-mm-yyyy)', $sermon_domain),
											   'us' => __('US-formatted date (mm-dd-yyyy)', $sermon_domain),
											   'int' => __('International formatted date (yyyy-mm-dd)', $sermon_domain)
										);
					$saved_option = sb_get_option ('import_filename');
					foreach ($filename_options as $option => $text) {
						$sel = $saved_option == $option ? ' selected = "selected"' : '';
						echo "<option value=\"{$option}\"{$sel}>{$text}</option>\n";
					}
					echo "</select>\n<br/>";
					_e ('(Use if you name your files something like 2008-11-06-eveningsermon.mp3)', $sermon_domain);
					?>
				</td>
			</tr>
		</table>
		<p class="submit"><input type="submit" name="resetdefault" value="<?php _e('Reset to defaults', $sermon_domain) ?>"  />&nbsp;<input type="submit" name="save" value="<?php _e('Save', $sermon_domain) ?> &raquo;" /></p>
	</div>
	</form>
<?php
}

/**
* Display uninstall screen and perform uninstall if requested
*/
function sb_uninstall () {
	global $sermon_domain;
	//Security check
	if (!(current_user_can('edit_plugins') | (IS_MU && current_user_can('manage_options'))))
			wp_die(__("You do not have the correct permissions to Uninstall SermonBrowser", $sermon_domain));
	if (isset($_POST['uninstall']))
		require(SB_INCLUDES_DIR.'/uninstall.php');
?>
	<form method="post">
	<div class="wrap">
		<?php if (IS_MU) { ?>
			<h2> <?php _e('Reset SermonBrowser', $sermon_domain); ?></h2>
			<p><?php printf(__('Clicking the %s button below will remove ALL data (sermons, preachers, series, etc.) from SermonBrowser', $sermon_domain), __('Delete all', $sermon_domain));
					 echo '. ';
					 _e('You will NOT be able to undo this action.', $sermon_domain) ?>
			</p>
		<?php } else {  ?>
			<h2> <?php _e('Uninstall', $sermon_domain); ?></h2>
			<p><?php printf(__('Clicking the %s button below will remove ALL data (sermons, preachers, series, etc.) from SermonBrowser', $sermon_domain), __('Uninstall', $sermon_domain));
					 echo ', ';
					 _e('and will deactivate the SermonBrowser plugin', $sermon_domain);
					 echo '. ';
					 _e('You will NOT be able to undo this action.', $sermon_domain);
					 echo ' ';
					 _e('If you only want to temporarily disable SermonBrowser, just deactivate it from the plugins page.', $sermon_domain); ?>
			</p>
		<?php } ?>
		<table border="0" class="widefat">
			<tr>
				<td><input type="checkbox" name="wipe" value="1"> <?php _e('Also remove all uploaded files', $sermon_domain) ?></td>
			</tr>
		</table>
		<p class="submit"><input type="submit" name="uninstall" value="<?php if (IS_MU) { _e('Delete all', $sermon_domain); } else { _e('Uninstall', $sermon_domain); } ?>" onclick="return confirm('<?php _e('Do you REALLY want to delete all data?', $sermon_domain)?>')" /></p>
	</div>
	</form>
	<script>
		jQuery("form").submit(function() {
			var yes = confirm("<?php _e('Are you REALLY REALLY sure you want to remove SermonBrowser?', $sermon_domain)?>");
			if(!yes) return false;
		});
	</script>
<?php
}

/**
* Display the templates page and handle changes
*/
function sb_templates () {
	global $sermon_domain;
	//Security check
	if (function_exists('current_user_can')&&!current_user_can('manage_options'))
			wp_die(__("You do not have the correct permissions to edit the SermonBrowser templates", $sermon_domain));
	//Save templates or reset to default
	if (isset($_POST['save']) || isset($_POST['resetdefault'])) {
		require(SB_INCLUDES_DIR.'/dictionary.php');
		$multi = $_POST['multi'];
		$single = $_POST['single'];
		$style = $_POST['style'];
		if(isset($_POST['resetdefault'])){
			require(SB_INCLUDES_DIR.'/sb-install.php');
			$multi = sb_default_multi_template();
			$single = sb_default_single_template();
			$style = sb_default_css();
		}
		sb_update_option('search_template', $multi);
		sb_update_option('single_template', $single);
		sb_update_option('css_style', $style);
		sb_update_option('search_output', strtr($multi, sb_search_results_dictionary()));
		sb_update_option('single_output', strtr($single, sb_sermon_page_dictionary()));
		sb_update_option('style_date_modified', strtotime('now'));
		echo '<div id="message" class="updated fade"><p><b>';
		_e('Templates saved successfully.', $sermon_domain);
		echo '</b></p></div>';
	}
	sb_do_alerts();
	// HTML for templates page
	?>
	<form method="post">
	<div class="wrap">
		<a href="http://www.sermonbrowser.com/"><img src="<?php echo SB_PLUGIN_URL; ?>/sb-includes/logo-small.png" width="191" height ="35" style="margin: 1em 2em; float: right; background: #f9f9f9;" /></a>
		<h2><?php _e('Templates', $sermon_domain) ?></h2>
		<br/>
		<table border="0" class="widefat">
			<tr>
				<td align="right"><?php _e('Search results page', $sermon_domain) ?>: </td>
				<td>
					<?php sb_build_textarea('multi', sb_get_option('search_template')) ?>
				</td>
			</tr>
			<tr>
				<td align="right"><?php _e('Sermon page', $sermon_domain) ?>: </td>
				<td>
					<?php sb_build_textarea('single', sb_get_option('single_template')) ?>
				</td>
			</tr>
			<tr>
				<td align="right"><?php _e('Style', $sermon_domain) ?>: </td>
				<td>
					<?php sb_build_textarea('style', sb_get_option('css_style')) ?>
				</td>
			</tr>
		</table>
		<p class="submit"><input type="submit" name="resetdefault" value="<?php _e('Reset to defaults', $sermon_domain) ?>"  />&nbsp;<input type="submit" name="save" value="<?php _e('Save', $sermon_domain) ?> &raquo;" /></p>
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

/**
* Display the preachers page and handle changes
*/
function sb_manage_preachers() {
	global $wpdb, $sermon_domain;
	//Security check
	if (function_exists('current_user_can')&&!current_user_can('manage_categories'))
			wp_die(__("You do not have the correct permissions to manage the preachers' database", $sermon_domain));
	if (isset($_GET['saved']))
		echo '<div id="message" class="updated fade"><p><b>'.__('Preacher saved to database.', $sermon_domain).'</b></div>';
	$sermonUploadDir = sb_get_option('upload_dir');
	//Save changes
	if (isset($_POST['save'])) {
		$name = $wpdb->escape($_POST['name']);
		$description = $wpdb->escape($_POST['description']);
		$error = false;
		$pid = (int) $_REQUEST['pid'];

		if (empty($_FILES['upload']['name'])) {
			$p = $wpdb->get_row("SELECT image FROM {$wpdb->prefix}sb_preachers WHERE id = $pid");
			$filename = $p ? $p->image : '';
		} elseif ($_FILES['upload']['error'] == UPLOAD_ERR_OK) {
			$filename = basename($_FILES['upload']['name']);
			$prefix = '';
			if(!is_dir(SB_ABSPATH.$sermonUploadDir.'images') && sb_mkdir(SB_ABSPATH.$sermonUploadDir.'images'))
				@chmod(SB_ABSPATH.$sermonUploadDir.'images', 0777);
			$dest = SB_ABSPATH.$sermonUploadDir.'images/'.$filename;
			if (@move_uploaded_file($_FILES['upload']['tmp_name'], $dest))
				$filename = $prefix.mysql_real_escape_string($filename);
			else {
				$error = true;
				echo '<div id="message" class="updated fade"><p><b>'.__('Could not save uploaded file. Please try again.', $sermon_domain).'</b></div>';
				@chmod(SB_ABSPATH.$sermonUploadDir.'images', 0777);
			}
		} else {
				$error = true;
				echo '<div id="message" class="updated fade"><p><b>'.__('Could not upload file. Please check the Options page for any errors or warnings.', $sermon_domain).'</b></div>';
		}

		if ($pid == 0) {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_preachers VALUES (null, '$name', '$description', '$filename')");
		} else {
			$wpdb->query("UPDATE {$wpdb->prefix}sb_preachers SET name = '$name', description = '$description', image = '$filename' WHERE id = $pid");
			if ($_POST['old'] != $filename)
				@unlink(SB_ABSPATH.sb_get_option('upload_dir').'images/'.mysql_real_escape_string($_POST['old']));
		}
		if(isset($_POST['remove'])){
			$wpdb->query("UPDATE {$wpdb->prefix}sb_preachers SET name = '$name', description = '$description', image = '' WHERE id = $pid");
			@unlink(SB_ABSPATH.sb_get_option('upload_dir').'images/'.mysql_real_escape_string($_POST['old']));
		}
		if(!$error)
			echo "<script>document.location = '".site_url()."/wp-admin/admin.php?page=sermon-browser/preachers.php&saved=true';</script>";
	}

	if (isset($_GET['act']) && $_GET['act'] == 'kill') {
		$die = (int) $_GET['pid'];
		if($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons WHERE preacher_id = $die") > 0)
			echo '<div id="message" class="updated fade"><p><b>'.__("You cannot delete this preacher until you first delete any sermons they have preached.", $sermon_domain).'</b></div>';
		else {
			$p = $wpdb->get_row("SELECT image FROM {$wpdb->prefix}sb_preachers WHERE id = $die");
			@unlink(SB_ABSPATH.sb_get_option('upload_dir').'images/'.$p->image);
			$wpdb->query("DELETE FROM {$wpdb->prefix}sb_preachers WHERE id = $die");
		}
	}

	if (isset($_GET['act']) && ($_GET['act'] == 'new' || $_GET['act'] == 'edit')) {
		if ($_GET['act'] == 'edit') $preacher = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}sb_preachers WHERE id = ".(int) $_GET['pid']);
	//Display HTML
?>
	<div class="wrap">
		<a href="http://www.sermonbrowser.com/"><img src="<?php echo SB_PLUGIN_URL; ?>/sb-includes/logo-small.png" width="191" height ="35" style="margin: 1em 2em; float: right; background: #f9f9f9;" /></a>
		<h2><?php echo $_GET['act'] == 'new' ? __('Add', $sermon_domain) : __('Edit', $sermon_domain) ?> <?php _e('preacher', $sermon_domain) ?></h2>
		<br style="clear:both">
		<?php
			$checkSermonUpload = sb_checkSermonUploadable('images/');
			if ($checkSermonUpload == 'notexist') {
				echo SB_ABSPATH.$sermonUploadDir.'images';
				if (!is_dir(SB_ABSPATH.$sermonUploadDir.'images') && mkdir(SB_ABSPATH.$sermonUploadDir.'images'))
					chmod(SB_ABSPATH.$sermonUploadDir.'images', 0777);
				$checkSermonUpload = sb_checkSermonUploadable('images/');
			}
			if ($checkSermonUpload != 'writeable')
				echo '<div id="message" class="updated fade"><p><b>'.__("The images folder is not writeable. You won't be able to upload images.", $sermon_domain).'</b></div>';
		?>
		<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="pid" value="<?php echo (int) $_GET['pid'] ?>">
		<fieldset>
			<table class="widefat">
				<tr>
					<td>
						<strong><?php _e('Name', $sermon_domain) ?></strong>
						<div>
							<input type="text" value="<?php echo isset($preacher->name) ? stripslashes($preacher->name) : '' ?>" name="name" size="60" style="width:400px;" />
						</div>
					</td>
				</tr>
				<tr>
					<td>
						<strong><?php _e('Description', $sermon_domain) ?></strong>
						<div>
							<textarea name="description" cols="100" rows="5"><?php echo isset($preacher->description) ? stripslashes($preacher->description) : ''?></textarea>
						</div>
					</td>
				</tr>
				<tr>
					<td>
						<?php if ($_GET['act'] == 'edit'): ?>
						<div><img src="<?php echo trailingslashit(site_url()).sb_get_option('upload_dir').'images/'.$preacher->image ?>"></div>
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

	$preachers = $wpdb->get_results("SELECT {$wpdb->prefix}sb_preachers.*, COUNT({$wpdb->prefix}sb_sermons.id) AS sermon_count FROM {$wpdb->prefix}sb_preachers LEFT JOIN {$wpdb->prefix}sb_sermons ON {$wpdb->prefix}sb_preachers.id=preacher_id GROUP BY preacher_id ORDER BY name ASC");
	sb_do_alerts();
?>
	<div class="wrap">
		<a href="http://www.sermonbrowser.com/"><img src="<?php echo SB_PLUGIN_URL; ?>/sb-includes/logo-small.png" width="191" height ="35" style="margin: 1em 2em; float: right; background: #f9f9f9;" /></a>
		<h2><?php _e('Preachers', $sermon_domain) ?> (<a href="<?php echo site_url() ?>/wp-admin/admin.php?page=sermon-browser/preachers.php&act=new"><?php _e('add new', $sermon_domain) ?></a>)</h2>
		<br/>
		<table class="widefat" style="width:auto">
			<thead>
			<tr>
				<th scope="col" style="text-align:center"><?php _e('ID', $sermon_domain) ?></th>
				<th scope="col"><?php _e('Name', $sermon_domain) ?></th>
				<th scope="col" style="text-align:center"><?php _e('Image', $sermon_domain) ?></th>
				<th scope="col" style="text-align:center"><?php _e('Sermons', $sermon_domain) ?></th>
				<th scope="col" style="text-align:center"><?php _e('Actions', $sermon_domain) ?></th>
			</tr>
			</thead>
			<tbody>
				<?php foreach ((array) $preachers as $preacher): ?>
					<tr class="<?php $i=0; echo (++$i % 2 == 0) ? 'alternate' : '' ?>">
						<td style="text-align:center"><?php echo $preacher->id ?></td>
						<td><?php echo stripslashes($preacher->name) ?></td>
						<td style="text-align:center"><?php echo ($preacher->image == '') ? '' : '<img src="'.trailingslashit(site_url()).sb_get_option('upload_dir').'images/'.$preacher->image.'">' ?></td>
						<td style="text-align:center"><?php echo $preacher->sermon_count ?></td>
						<td style="text-align:center">
							<a href="<?php echo site_url() ?>/wp-admin/admin.php?page=sermon-browser/preachers.php&act=edit&pid=<?php echo $preacher->id ?>"><?php _e('Edit', $sermon_domain) ?></a>
							<?php if (count($preachers) < 2) { ?>
							    | <a href="javascript:alert('<?php _e('You must have at least one preacher in the database.', $sermon_domain)?>')"><?php _e('Delete', $sermon_domain) ?></a>
							<?php } elseif ($preacher->sermon_count != 0) { ?>
							    | <a href="javascript:alert('<?php _e('You cannot delete this preacher until you first delete any sermons they have preached.', $sermon_domain)?>')"><?php _e('Delete', $sermon_domain) ?></a>
							<?php } else { ?>
								| <a onclick="return confirm('<?php printf(__('Are you sure you want to delete %s?', $sermon_domain), stripslashes($preacher->name)) ?>')" href="<?php echo site_url() ?>/wp-admin/admin.php?page=sermon-browser/preachers.php&act=kill&pid=<?php echo $preacher->id ?>"><?php _e('Delete', $sermon_domain) ?></a>
							<?php } ?>
						</td>
					</tr>
				<?php endforeach ?>
			</tbody>
		</table>
	</div>
<?php
}

/**
* Display services & series page and handle changes
*/
function sb_manage_everything() {
	global $wpdb, $sermon_domain;
	//Security check
	if (function_exists('current_user_can')&&!current_user_can('manage_categories'))
			wp_die(__("You do not have the correct permissions to manage the series and services database", $sermon_domain));

	$series = $wpdb->get_results("SELECT {$wpdb->prefix}sb_series.*, COUNT({$wpdb->prefix}sb_sermons.id) AS sermon_count FROM {$wpdb->prefix}sb_series LEFT JOIN {$wpdb->prefix}sb_sermons ON series_id = {$wpdb->prefix}sb_series.id GROUP BY series_id ORDER BY name ASC");
	$services = $wpdb->get_results("SELECT {$wpdb->prefix}sb_services.*, COUNT({$wpdb->prefix}sb_sermons.id) AS sermon_count FROM {$wpdb->prefix}sb_services LEFT JOIN {$wpdb->prefix}sb_sermons ON service_id = {$wpdb->prefix}sb_services.id GROUP BY service_id ORDER BY name ASC");

	$toManage = array(
		'Series' => array('data' => $series),
		'Services' => array('data' => $services),
	);
	sb_do_alerts();
?>
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
				s = prompt("<?php _e("New service's name @ default time?", $sermon_domain)?>", "<?php _e("Service's name @ 18:00", $sermon_domain)?>");
				if (s == null) { break;	}
			}
			if (s != null) {
				jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/sermon.php'); ?>', {sname: s, sermon: 1}, function(r) {
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
			var ss = prompt("<?php _e("New series' name?", $sermon_domain)?>", "<?php _e("Series' name", $sermon_domain)?>");
			if (ss != null) {
				jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/sermon.php'); ?>', {ssname: ss, sermon: 1}, function(r) {
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
			jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/sermon.php'); ?>', {ssname: 'dummy', ssid: id, del: 1, sermon: 1}, function(r) {
				if (r) {
					jQuery('#rowSeries' + id).fadeOut(function() {
						updateClass('Series');
					});
				};
			});
		}
		function deleteServices(id) {
			jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/sermon.php'); ?>', {sname: 'dummy', sid: id, del: 1, sermon: 1}, function(r) {
				if (r) {
					jQuery('#rowServices' + id).fadeOut(function() {
						updateClass('Services');
					});
				};
			});
		}
		function renameSeries(id, old) {
			var ss = prompt("<?php _e("New series' name?", $sermon_domain)?>", old);
			if (ss != null) {
				jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/sermon.php'); ?>', {ssid: id, ssname: ss, sermon: 1}, function(r) {
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
				s = prompt("<?php _e("New service's name @ default time?", $sermon_domain)?>", old);
				if (s == null) { break;	}
			}
			if (s != null) {
				jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/sermon.php'); ?>', {sid: id, sname: s, sermon: 1}, function(r) {
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
<?php
	foreach ($toManage as $k => $v) {
		$i = 0;
?>
	<a name="manage-<?php echo $k ?>"></a>
	<div class="wrap">
		<?php if ($k == 'Series') { ?><a href="http://www.sermonbrowser.com/"><img src="<?php echo SB_PLUGIN_URL; ?>/sb-includes/logo-small.png" width="191" height ="35" style="margin: 1em 2em; float: right; background: #f9f9f9;" /></a><?php } ?>
		<h2><?php echo $k ?> (<a href="javascript:createNew<?php echo $k ?>()"><?php _e('add new', $sermon_domain) ?></a>)</h2>
		<br style="clear:both">
		<table class="widefat" style="width:auto">
			<thead>
			<tr>
				<th scope="col" style="text-align:center"><?php _e('ID', $sermon_domain) ?></th>
				<th scope="col"><?php _e('Name', $sermon_domain) ?></th>
				<?php echo $k == 'Services' ? '<th scope="col"><div style="text-align:center">'.__('Default time', $sermon_domain).'</div></th>' : '' ?>
				<th scope="col" style="text-align:center"><?php _e('Sermons', $sermon_domain) ?></th>
				<th scope="col" style="text-align:center"><?php _e('Actions', $sermon_domain) ?></th>
			</tr>
			</thead>
			<tbody id="<?php echo $k ?>-list">
				<?php if (is_array($v['data'])): ?>
					<?php foreach ($v['data'] as $item): ?>
						<tr class="<?php echo $k ?> <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="row<?php echo $k ?><?php echo $item->id ?>">
							<th style="text-align:center" scope="row"><?php echo $item->id ?></th>
							<td id="<?php echo $k ?><?php echo $item->id ?>"><?php echo stripslashes($item->name) ?></td>
							<?php echo $k == 'Services' ? '<td style="text-align:center" id="time'.$item->id.'">'.$item->time.'</td>' : '' ?>
							<td style="text-align:center"><?php echo $item->sermon_count; ?></td>
							<td style="text-align:center">
								<a id="link<?php echo $k ?><?php echo $item->id ?>" href="javascript:rename<?php echo $k ?>(<?php echo $item->id ?>, '<?php echo $item->name ?><?php echo $k == 'Services' ? ' @ '.$item->time : '' ?>')"><?php echo $k == 'Services' ? __('Edit', $sermon_domain) : __('Rename', $sermon_domain) ?></a>
								<?php if (count($v['data']) < 2) { ?>
									| <a href="javascript:alert('<?php printf(__('You cannot delete this %1$s as you must have at least one %1$s in the database', $sermon_domain), $k); ?>')"><?php _e('Delete', $sermon_domain) ?></a>
								<?php } elseif ($item->sermon_count == 0) { ?>
									| <a href="javascript:alert('<?php printf(__('Are you sure you want to delete %s?', $sermon_domain), $item->name); ?>')"><?php _e('Delete', $sermon_domain) ?></a>
								<?php } else { ?>
									| <a href="javascript:alert('<?php switch ($k) {
										case "Services":
											_e('Some sermons are currently assigned to that service. You can only delete services that are not used in the database.', $sermon_domain);
											break;
										case "Series":
											_e('Some sermons are currently in that series. You can only delete series that are empty.', $sermon_domain);
											break;
										case "Preachers":
											_e('That preacher has sermons in the database. You can only delete preachers who have no sermons in the database.', $sermon_domain);
											break;
									}?>')"><?php _e('Delete', $sermon_domain) ?></a>
								<?php } ?>
							</td>
						</tr>
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

/**
* Display files page and handle changes
*/
function sb_files() {
	global $wpdb, $filetypes, $sermon_domain;
	//Security check
	if (!current_user_can('upload_files'))
			wp_die(__("You do not have the correct permissions to upload sermons", $sermon_domain));
	// sync
	sb_scan_dir();

	if (isset($_POST['import_url'])) {
		$url = $_POST['url'];
		$valid_url = false;
		if(ini_get('allow_url_fopen')) {
			$headers = array_change_key_case(get_headers($url, 1),CASE_LOWER);
			if ($headers[0] == 'HTTP/1.1 200 OK') {
				if ($_POST['import_type'] == 'download') {
					$filename = substr($url, strrpos ($url, '/')+1);
					$filename = substr($filename, 0, strrpos ($filename, '?'));
					if (file_exists(SB_ABSPATH.sb_get_option('upload_dir').$filename))
						echo '<div id="message" class="updated fade"><p><b>'.sprintf(__('File %s already exists', $sermon_domain), $filename).'</b></div>';
					else {
						$file = @fopen(SB_ABSPATH.sb_get_option('upload_dir').$filename, 'wb');
						$remote_file = @fopen($url, 'r');
						$remote_contents = '';
						while (!feof($remote_file))
							$remote_contents .= fread($remote_file, 8192);
						fwrite($file, $remote_contents);
						fclose($remote_file);
						fclose($file);
						$wpdb->query("INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'file', '".$wpdb->escape($filename)."', 0, 0, 0)");
						echo "<script>document.location = '".admin_url('admin.php?page=sermon-browser/new_sermon.php&getid3='.$wpdb->insert_id)."';</script>";
					}
				} else {
					$wpdb->query("INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'url', '".$wpdb->escape($url)."', 0, 0, 0)");
					echo "<script>document.location = '".admin_url('admin.php?page=sermon-browser/new_sermon.php&getid3='.$wpdb->insert_id)."';</script>";
					die();
				}
			} else
				echo '<div id="message" class="updated fade"><p><b>'.__('Invalid URL.', $sermon_domain).'</b></div>';
		} else
			echo '<div id="message" class="updated fade"><p><b>'.__('Your host does not allow remote downloading of files.', $sermon_domain).'</b></div>';
	}
	elseif (isset($_POST['save'])) {
		if ($_FILES['upload']['error'] == UPLOAD_ERR_OK) {
			$filename = basename($_FILES['upload']['name']);
			if (IS_MU) {
				$file_allowed = FALSE;
				global $wp_version;
				if (version_compare ($wp_version, '3.0', '<'))
					require_once(SB_ABSPATH . 'wp-includes/wpmu-functions.php');
				else
					require_once(SB_ABSPATH . 'wp-includes/ms-functions.php');
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
				$prefix = '';
				$dest = SB_ABSPATH.sb_get_option('upload_dir').$prefix.$filename;
				if($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_stuff WHERE name = '".mysql_real_escape_string($filename)."'") == 0) {
					$filename = mysql_real_escape_string($filename);
					if (move_uploaded_file($_FILES['upload']['tmp_name'], $dest)) {
						$filename = $prefix.$filename;
						$wpdb->query("INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'file', '{$filename}', 0, 0, 0)");
						if (sb_import_options_set ())
							echo "<script>document.location = '".admin_url('admin.php?page=sermon-browser/new_sermon.php&getid3='.$wpdb->insert_id)."';</script>";
						else
							echo '<div id="message" class="updated fade"><p><b>'.__('Files saved to database.', $sermon_domain).'</b></div>';
					}
				} else {
					echo '<div id="message" class="updated fade"><p><b>'.__($filename. ' already exists.', $sermon_domain).'</b></div>';
				}
			} else {
				@unlink($_FILES['upload']['tmp_name']);
				echo '<div id="message" class="updated fade"><p><b>'.__('You are not permitted to upload files of that type.', $sermon_domain).'</b></div>';
			}
		}
	} elseif(isset($_POST['clean'])) {
		$unlinked = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id = 0 AND f.type = 'file' ORDER BY f.name;");
		$linked = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id <> 0 AND f.type = 'file' ORDER BY f.name;");
		$wanted = array(-1);
		foreach ((array) $unlinked as $k => $file) {
			if (!file_exists(SB_ABSPATH.sb_get_option('upload_dir').$file->name)) {
				$wanted[] = $file->id;
				unset($unlinked[$k]);
			}
		}
		foreach ((array) $linked as $k => $file) {
			if (!file_exists(SB_ABSPATH.sb_get_option('upload_dir').$file->name)) {
				$wanted[] = $file->id;
				unset($unlinked[$k]);
			}
		}
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE id IN (".implode(', ', (array) $wanted).")");
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE type != 'file' AND sermon_id=0");
	}

	$unlinked = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id = 0 AND f.type = 'file' ORDER BY f.name LIMIT 10;");
	$linked = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id <> 0 AND f.type = 'file' ORDER BY f.name LIMIT 10;");
	$cntu = $wpdb->get_row("SELECT COUNT(*) as cntu FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = 0 AND type = 'file' ", ARRAY_A);
	$cntu = $cntu['cntu'];
	$cntl = $wpdb->get_row("SELECT COUNT(*) as cntl FROM {$wpdb->prefix}sb_stuff WHERE sermon_id <> 0 AND type = 'file' ", ARRAY_A);
	$cntl = $cntl['cntl'];
	sb_do_alerts();
?>
	<script>
		function rename(id, old) {
			var f = prompt("<?php _e('New file name?', $sermon_domain) ?>", old);
			if (f != null) {
				jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/uploads.php'); ?>', {fid: id, oname: old, fname: f, sermon: 1}, function(r) {
					if (r) {
						if (r == 'renamed') {
							jQuery('#' + id).text(f.substring(0,f.lastIndexOf(".")));
							jQuery('#link' + id).attr('href', 'javascript:rename(' + id + ', "' + f + '")');
							Fat.fade_element(id);
							jQuery('#s' + id).text(f.substring(0,f.lastIndexOf(".")));
							jQuery('#slink' + id).attr('href', 'javascript:rename(' + id + ', "' + f + '")');
							Fat.fade_element('s' + id);
						} else {
							if (r == 'forbidden') {
								alert('<?php _e('You are not permitted files with that extension.', $sermon_domain) ?>');
							} else {
								alert('<?php _e('The script is unable to rename your file.', $sermon_domain) ?>');
							}
						}
					};
				});
			}
		}
		function kill(id, f) {
			jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/files.php'); ?>', {fname: f, fid: id, del: 1, sermon: 1}, function(r) {
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
						alert('<?php _e('The script is unable to delete your file.', $sermon_domain) ?>');
					}
				};
			});
		}
		function fetchU(st) {
			jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/uploads.php'); ?>', {fetchU: st + 1, sermon: 1}, function(r) {
				if (r) {
					jQuery('#the-list-u').html(r);
					if (st >= <?php echo sb_get_option('sermons_per_page') ?>) {
						x = st - <?php echo sb_get_option('sermons_per_page') ?>;
						jQuery('#uleft').html('<a href="javascript:fetchU(' + x + ')">&laquo; <?php _e('Previous', $sermon_domain) ?></a>');
					} else {
						jQuery('#uleft').html('');
					}
					if (st + <?php echo sb_get_option('sermons_per_page') ?> <= <?php echo $cntu ?>) {
						y = st + <?php echo sb_get_option('sermons_per_page') ?>;
						jQuery('#uright').html('<a href="javascript:fetchU(' + y + ')"><?php _e('Next', $sermon_domain) ?> &raquo;</a>');
					} else {
						jQuery('#uright').html('');
					}
				};
			});
		}
		function fetchL(st) {
			jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/files.php'); ?>', {fetchL: st + 1, sermon: 1}, function(r) {
				if (r) {
					jQuery('#the-list-l').html(r);
					if (st >= <?php echo sb_get_option('sermons_per_page') ?>) {
						x = st - <?php echo sb_get_option('sermons_per_page') ?>;
						jQuery('#left').html('<a href="javascript:fetchL(' + x + ')">&laquo; <?php _e('Previous', $sermon_domain) ?></a>');
					} else {
						jQuery('#left').html('');
					}
					if (st + <?php echo sb_get_option('sermons_per_page') ?> <= <?php echo $cntl ?>) {
						y = st + <?php echo sb_get_option('sermons_per_page') ?>;
						jQuery('#right').html('<a href="javascript:fetchL(' + y + ')"><?php _e('Next', $sermon_domain) ?> &raquo;</a>');
					} else {
						jQuery('#right').html('');
					}
				};
			});
		}
		function findNow() {
			jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/files.php'); ?>', {search: jQuery('#search').val(), sermon: 1}, function(r) {
				if (r) {
					jQuery('#the-list-s').html(r);
				};
			});
		}
	</script>
	<a name="top"></a>
	<div class="wrap">
		<a href="http://www.sermonbrowser.com/"><img src="<?php echo SB_PLUGIN_URL; ?>/sb-includes/logo-small.png" width="191" height ="35" style="margin: 1em 2em; float: right; background: #f9f9f9;" /></a>
		<h2><?php _e('Upload Files', $sermon_domain) ?></h2>
		<?php if (!sb_import_options_set()) {
			echo '<p class="plugin-update">';
			sb_print_import_options_message();
			echo "</p>\n";
		} ?>
		<br style="clear:both">
		<?php
			sb_print_upload_form();
		?>
	</div>
	<div class="wrap">
		<h2><?php _e('Unlinked files', $sermon_domain) ?></h2>
		<br style="clear:both">
		<table class="widefat">
			<thead>
				<tr>
				<th width="10%" scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th width="50%" scope="col"><div style="text-align:center"><?php _e('File name', $sermon_domain) ?></div></th>
				<th width="20%" scope="col"><div style="text-align:center"><?php _e('File type', $sermon_domain) ?></div></th>
				<th width="20%" scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
				</tr>
			</thead>
			<tbody id="the-list-u">
				<?php if (is_array($unlinked)): ?>
					<?php foreach ($unlinked as $file): ?>
						<tr class="file <?php $i=0; echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="file<?php echo $file->id ?>">
							<th style="text-align:center" scope="row"><?php echo $file->id ?></th>
							<td id="<?php echo $file->id ?>"><?php echo substr($file->name, 0, strrpos($file->name, '.')) ?></td>
							<td style="text-align:center"><?php echo isset($filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name']) ? $filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name'] : strtoupper(substr($file->name, strrpos($file->name, '.') + 1)) ?></td>
							<td style="text-align:center">
								<a id="" href="<?php echo admin_url("admin.php?page=sermon-browser/new_sermon.php&amp;getid3={$file->id}"); ?>"><?php _e('Create sermon', $sermon_domain) ?></a> |
								<a id="link<?php echo $file->id ?>" href="javascript:rename(<?php echo $file->id ?>, '<?php echo $file->name ?>')"><?php _e('Rename', $sermon_domain) ?></a> | <a onclick="return confirm('Do you really want to delete <?php echo str_replace("'", '', $file->name) ?>?');" href="javascript:kill(<?php echo $file->id ?>, '<?php echo $file->name ?>');"><?php _e('Delete', $sermon_domain) ?></a>
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
		<h2><?php _e('Linked files', $sermon_domain) ?></h2>
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
								<a id="link<?php echo $file->id ?>" href="javascript:rename(<?php echo $file->id ?>, '<?php echo $file->name ?>')"><?php _e('Rename', $sermon_domain) ?></a> | <a onclick="return deletelinked_<?php echo $file->id;?>('<?php echo str_replace("'", '', $file->name) ?>', '<?php echo str_replace("'", '', $file->title) ?>');" href="javascript:kill(<?php echo $file->id ?>, '<?php echo $file->name ?>');"><?php _e('Delete', $sermon_domain) ?></a>
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
	<a name="search"></a>
	<div class="wrap">
		<h2><?php _e('Search for files', $sermon_domain) ?></h2>
		<form id="searchform" name="searchform">
			<p>
				<input type="text" size="30" value="" id="search" />
				<input type="submit" class="button" value="<?php _e('Search', $sermon_domain) ?> &raquo;" onclick="javascript:findNow();return false;" />
			</p>
		</form>
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
		<?php if ($cntu > sb_get_option('sermons_per_page')): ?>
			jQuery('#uright').html('<a href="javascript:fetchU(<?php echo sb_get_option('sermons_per_page') ?>)">Next &raquo;</a>');
		<?php endif ?>
		<?php if ($cntl > sb_get_option('sermons_per_page')): ?>
			jQuery('#right').html('<a href="javascript:fetchL(<?php echo sb_get_option('sermons_per_page') ?>)">Next &raquo;</a>');
		<?php endif ?>
	</script>
	<?php
		if (isset($checkSermonUpload) && $checkSermonUpload == 'writeable') {
		?>
		<div class="wrap">
			<h2><?php _e('Clean up', $sermon_domain) ?></h2>
			<form method="post" >
				<p><?php _e('Pressing the button below scans every sermon in the database, and removes missing attachments. Use with caution!', $sermon_domain) ?></p>
				<input type="submit" name="clean" value="<?php _e('Clean up missing files', $sermon_domain) ?>" />
			</form>
		</div>
		<?php
	}
}

/**
* Pings the sermon-browser gallery
*/
function sb_ping_gallery() {
	global $wpdb;
	if((ini_get('allow_url_fopen') | function_exists('curl_init')) & get_option('blog_public') == 1 & get_option('ping_sites') != "") {
		$url = "http://ping.preachingcentral.com/?sg_ping";
		$url .= "&name=".rawurlencode(get_option('blogname'));
		$url .= "&tagline=".rawurlencode(get_option('blogdescription'));
		$url .= "&site_url=".rawurlencode(site_url());
		$url .= "&sermon_url=".rawurlencode(sb_display_url());
		$url .= "&most_recent=".rawurlencode($wpdb->get_var("SELECT datetime FROM {$wpdb->prefix}sb_sermons ORDER BY datetime DESC LIMIT 1"));
		$url .= "&num_sermons=".rawurlencode($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons"));
		$url .= "&ver=".constant("SB_CURRENT_VERSION");
		 if (ini_get('allow_url_fopen')) {
			$headers = @get_headers($url, 1);
			if ($headers !="") {
				$headers = array_change_key_case($headers,CASE_LOWER);
			}
		} else {
			$curl = curl_init();
			curl_setopt ($curl, CURLOPT_URL, $url);
			curl_setopt ($curl, CURLOPT_HEADER, 1);
			curl_setopt ($curl, CURLOPT_NOBODY, 1);
			curl_setopt ($curl, CURLOPT_TIMEOUT, 2);
			curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt ($curl, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt ($curl, CURLOPT_MAXREDIRS, 10);
			$execute = curl_exec ($curl);
			$info = curl_getinfo ($curl);
			curl_close ($curl);
		}
	}
}

/**
* Displays Sermons page
*/
function sb_manage_sermons() {
	global $wpdb, $sermon_domain;
	//Security check
	if (function_exists('current_user_can') && !(current_user_can('publish_posts') || current_user_can('publish_pages')))
		wp_die(__("You do not have the correct permissions to edit sermons", $sermon_domain));
	sb_do_alerts();
	if (isset($_GET['saved'])) {
		echo '<div id="message" class="updated fade"><p><b>'.__('Sermon saved to database.', $sermon_domain).'</b></div>';
		if (rand (1,5) == 1 && sb_get_option('show_donate_reminder') != 'off')
			echo '<div id="message" class="updated"><p><b>'.sprintf(__('If you find SermonBrowser useful, please consider %1$ssupporting%2$s the ministry of Nathanael and Anna Ayling in Japan.', $sermon_domain), '<a href="'.admin_url('admin.php?page=sermon-browser/japan.php').'">', '</a>').'</b></div>';
	}

	if (isset($_GET['mid'])) {
		//Security check
		if (function_exists('current_user_can')&&!current_user_can('publish_posts'))
			wp_die(__("You do not have the correct permissions to delete sermons", $sermon_domain));
		$mid = (int) $_GET['mid'];
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_sermons WHERE id = $mid;");
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_sermons_tags WHERE sermon_id = $mid;");
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_books_sermons WHERE sermon_id = $mid;");
		$wpdb->query("UPDATE {$wpdb->prefix}sb_stuff SET sermon_id = 0 WHERE sermon_id = $mid AND type = 'file';");
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = $mid AND type <> 'file';");
		sb_delete_unused_tags();
		echo '<div id="message" class="updated fade"><p><b>'.__('Sermon removed from database.', $sermon_domain).'</b></div>';
	}

	$cnt = $wpdb->get_row("SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons", ARRAY_A);
	$cnt = $cnt['COUNT(*)'];

	$sermons = $wpdb->get_results("SELECT m.id, m.title, m.datetime, p.name as pname, s.name as sname, ss.name as ssname
		FROM {$wpdb->prefix}sb_sermons as m
		LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id
		LEFT JOIN {$wpdb->prefix}sb_services as s ON m.service_id = s.id
		LEFT JOIN {$wpdb->prefix}sb_series as ss ON m.series_id = ss.id
		ORDER BY m.datetime desc, s.time desc LIMIT 0, ".sb_get_option('sermons_per_page'));
	$preachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY name;");
	$series = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY name;");
?>
	<script>
		function fetch(st) {
			jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/sermon.php'); ?>', {fetch: st + 1, sermon: 1, title: jQuery('#search').val(), preacher: jQuery('#preacher').val(), series: jQuery('#series').val() }, function(r) {
				if (r) {
					jQuery('#the-list').html(r);
					if (st >= <?php echo sb_get_option('sermons_per_page') ?>) {
						x = st - <?php echo sb_get_option('sermons_per_page') ?>;
						jQuery('#left').html('<a href="javascript:fetch(' + x + ')">&laquo; Previous</a>');
					} else {
						jQuery('#left').html('');
					}
					if (st + <?php echo sb_get_option('sermons_per_page') ?> <= <?php echo $cnt ?>) {
						y = st + <?php echo sb_get_option('sermons_per_page') ?>;
						jQuery('#right').html('<a href="javascript:fetch(' + y + ')">Next &raquo;</a>');
					} else {
						jQuery('#right').html('');
					}
				};
			});
		}
	</script>
	<div class="wrap">
			<a href="http://www.sermonbrowser.com/"><img src="<?php echo SB_PLUGIN_URL; ?>/sb-includes/logo-small.png" width="191" height ="35" style="margin: 1em 2em; float: right; background: #f9f9f9;" /></a>
			<h2>Filter</h2>
			<form id="searchform" name="searchform">
			<fieldset style="float:left; margin-right: 1em">
				<legend><?php _e('Title', $sermon_domain) ?></legend>
				<input type="text" size="17" value="" id="search" />
			</fieldset>
			<fieldset style="float:left; margin-right: 1em">
				<legend><?php _e('Preacher', $sermon_domain) ?></legend>
				<select id="preacher">
					<option value="0"></option>
					<?php foreach ($preachers as $preacher): ?>
						<option value="<?php echo $preacher->id ?>"><?php echo htmlspecialchars(stripslashes($preacher->name), ENT_QUOTES) ?></option>
					<?php endforeach ?>
				</select>
			</fieldset>
			<fieldset style="float:left; margin-right: 1em">
				<legend><?php _e('Series', $sermon_domain) ?></legend>
				<select id="series">
					<option value="0"></option>
					<?php foreach ($series as $item): ?>
						<option value="<?php echo $item->id ?>"><?php echo htmlspecialchars(stripslashes($item->name), ENT_QUOTES) ?></option>
					<?php endforeach ?>
				</select>
			</fieldset style="float:left; margin-right: 1em">
			<input type="submit" class="button" value="<?php _e('Filter', $sermon_domain) ?> &raquo;" style="float:left;margin:14px 0pt 1em; position:relative;top:0.35em;" onclick="javascript:fetch(0);return false;" />
			</form>
		<br style="clear:both">
		<h2><?php _e('Sermons', $sermon_domain) ?></h2>
		<br style="clear:both">
		<table class="widefat">
			<thead>
			<tr>
				<th scope="col" style="text-align:center"><?php _e('ID', $sermon_domain) ?></th>
				<th scope="col"><?php _e('Title', $sermon_domain) ?></th>
				<th scope="col"><?php _e('Preacher', $sermon_domain) ?></th>
				<th scope="col"><?php _e('Date', $sermon_domain) ?></th>
				<th scope="col"><?php _e('Service', $sermon_domain) ?></th>
				<th scope="col"><?php _e('Series', $sermon_domain) ?></th>
				<th scope="col" style="text-align:center"><?php _e('Stats', $sermon_domain) ?></th>
				<th scope="col" style="text-align:center"><?php _e('Actions', $sermon_domain) ?></th>
			</tr>
			</thead>
			<tbody id="the-list">
				<?php if (is_array($sermons)): ?>
					<?php foreach ($sermons as $sermon): ?>
					<tr class="<?php $i=0; echo ++$i % 2 == 0 ? 'alternate' : '' ?>">
						<th style="text-align:center" scope="row"><?php echo $sermon->id ?></th>
						<td><?php echo stripslashes($sermon->title) ?></td>
						<td><?php echo stripslashes($sermon->pname) ?></td>
						<td><?php echo ($sermon->datetime == '1970-01-01 00:00:00') ? __('Unknown', $sermon_domain) : strftime('%d %b %y', strtotime($sermon->datetime)); ?></td>
						<td><?php echo stripslashes($sermon->sname) ?></td>
						<td><?php echo stripslashes($sermon->ssname) ?></td>
						<td><?php echo sb_sermon_stats($sermon->id) ?></td>
						<td style="text-align:center">
							<?php //Security check
									if (function_exists('current_user_can') && current_user_can('publish_posts')) { ?>
									<a href="<?php echo admin_url("admin.php?page=sermon-browser/new_sermon.php&mid={$sermon->id}"); ?>"><?php _e('Edit', $sermon_domain) ?></a> | <a onclick="return confirm('Are you sure?')" href="<?php echo admin_url("admin.php?page=sermon-browser/sermon.php&mid={$sermon->id}"); ?>"><?php _e('Delete', $sermon_domain); ?></a> |
							<?php } ?>
							<a href="<?php echo sb_display_url().sb_query_char(true).'sermon_id='.$sermon->id;?>">View</a>
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
		<?php if ($cnt > sb_get_option('sermons_per_page')): ?>
			jQuery('#right').html('<a href="javascript:fetch(<?php echo sb_get_option('sermons_per_page') ?>)">Next &raquo;</a>');
		<?php endif ?>
	</script>
<?php
}

/**
* Displays new/edit sermon page
*/
function sb_new_sermon() {
	global $wpdb, $sermon_domain, $allowedposttags;
	$getid3=false;
	//Security check
	if (!(current_user_can('publish_posts') || current_user_can('publish_pages')))
		wp_die(__("You do not have the correct permissions to edit or create sermons", $sermon_domain));
	include_once (SB_ABSPATH.'/wp-includes/kses.php');
	sb_scan_dir();

	if (isset($_POST['save']) && isset($_POST['title'])) {
	// prepare
		$title = $wpdb->escape($_POST['title']);
		$preacher_id = (int) $_POST['preacher'];
		$service_id = (int) $_POST['service'];
		$series_id = (int) $_POST['series'];
		$time = isset($_POST['time']) ? $wpdb->escape($_POST['time']) : '';
		$startz = $endz = array();
		for ($foo = 0; $foo < count($_POST['start']['book']); $foo++) {
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
		$start = $wpdb->escape(serialize($startz));
		$end = $wpdb->escape(serialize($endz));
		$date = strtotime($_POST['date']);
		$override = (isset($_POST['override']) && $_POST['override'] == 'on') ? 1 : 0;
		if ($date) {
			if (!$override) {
				$service_time = $wpdb->get_var("SELECT time FROM {$wpdb->prefix}sb_services WHERE id={$service_id}");
				if ($service_time)
					$date = $date - strtotime('00:00') + strtotime($service_time);
			} else
				$date = $date - strtotime('00:00') + strtotime($_POST['time']);
			$date = date('Y-m-d H:i:s', $date);
		} else
			$date = '1970-01-01 00:00';
		if (function_exists('current_user_can') && !current_user_can('unfiltered_html')) {
			$description = mysql_real_escape_string(wp_kses($_POST['description'], $allowedposttags));
		} else {
			$description = mysql_real_escape_string($_POST['description']);
		}
		// edit or not edit
		if (!$_GET['mid']) { // new
			//Security check
			if (!current_user_can('publish_pages'))
				wp_die(__("You do not have the correct permissions to create sermons", $sermon_domain));
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_sermons VALUES (null, '$title', '$preacher_id', '$date', '$service_id', '$series_id', '$start', '$end', '$description', '$time', '$override', 0)");
			$id = $wpdb->insert_id;
		} else { // edit
			//Security check
			if (!current_user_can('publish_posts'))
				wp_die(__("You do not have the correct permissions to edit sermons", $sermon_domain));
			$id = (int) $_GET['mid'];
			$wpdb->query("UPDATE {$wpdb->prefix}sb_sermons SET title = '$title', preacher_id = '$preacher_id', datetime = '$date', series_id = '$series_id', start = '$start', end = '$end', description = '$description', time = '$time', service_id = '$service_id', override = '$override' WHERE id = $id");
			$wpdb->query("UPDATE {$wpdb->prefix}sb_stuff SET sermon_id = 0 WHERE sermon_id = $id AND type = 'file'");
			$wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = $id AND type <> 'file'");
		}
		// deal with books
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_books_sermons WHERE sermon_id = $id;");
		if (isset($startz)) foreach ($startz as $i => $st) {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_books_sermons VALUES(null, '{$st['book']}', '{$st['chapter']}', '{$st['verse']}', $i, 'start', $id);");
		}
		if (isset($endz)) foreach ($endz as $i => $ed) {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_books_sermons VALUES(null, '{$ed['book']}', '{$ed['chapter']}', '{$ed['verse']}', $i, 'end', $id);");
		}
		// now previously uploaded files
		foreach ($_POST['file'] as $uid => $file) {
			if ($file != 0)
				$wpdb->query("UPDATE {$wpdb->prefix}sb_stuff SET sermon_id = $id WHERE id = $file;");
			elseif ($_FILES['upload']['error'][$uid] == UPLOAD_ERR_OK) {
				$filename = basename($_FILES['upload']['name'][$uid]);
				if (IS_MU) {
					$file_allowed = FALSE;
					global $wp_version;
					if (version_compare ($wp_version, '3.0', '<'))
						require_once(SB_ABSPATH . 'wp-includes/wpmu-functions.php');
					if (function_exists('get_site_option')) {
						$allowed_extensions = explode(' ', get_site_option('upload_filetypes'));
						foreach ($allowed_extensions as $ext) {
							if (substr(strtolower($filename), -(strlen($ext)+1)) == '.'.strtolower($ext))
								$file_allowed = TRUE;
						}
					}
				} else {
					$file_allowed = TRUE;
				}
				if ($file_allowed) {
					$prefix = '';
					$dest = SB_ABSPATH.sb_get_option('upload_dir').$prefix.$filename;
					if ($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_stuff WHERE name = '".$wpdb->escape($filename)."'") == 0 && move_uploaded_file($_FILES['upload']['tmp_name'][$uid], $dest)) {
						$filename = $prefix.mysql_real_escape_string($filename);
						$wpdb->query("INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'file', '".$wpdb->escape($filename)."', $id, 0, 0)");
					} else {
						echo '<div id="message" class="updated fade"><p><b>'.$filename.__(' already exists.', $sermon_domain).'</b></div>';
						$error = true;
					}
				} else {
					@unlink($_FILES['upload']['tmp_name']);
					echo '<div id="message" class="updated fade"><p><b>'.__('You are not permitted to upload files of that type.', $sermon_domain).'</b></div>';
					$error = true;
				}
			}
		}
		// then URLs
		foreach ((array) $_POST['url'] as $urlz) {
			if (!empty($urlz)) {
				$urlz = mysql_real_escape_string($urlz);
				$wpdb->query("INSERT INTO {$wpdb->prefix}sb_stuff VALUES(null, 'url', '$urlz', $id, 0, 0);");
			}
		}
		// embed code next
		foreach ((array) $_POST['code'] as $code) {
			if (!empty($code)) {
				$code = base64_encode(stripslashes($code));
				$wpdb->query("INSERT INTO {$wpdb->prefix}sb_stuff VALUES(null, 'code', '$code', $id, 0, 0)");
			}
		}
		// tags
		$tags = explode(',', $_POST['tags']);
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_sermons_tags WHERE sermon_id = $id;");
		foreach ($tags as $tag) {
			$clean_tag = trim(mysql_real_escape_string($tag));
			$existing_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}sb_tags WHERE name='$clean_tag'");
			if (is_null($existing_id)) {
				$wpdb->query("INSERT  INTO {$wpdb->prefix}sb_tags VALUES (null, '$clean_tag')");
				$existing_id = $wpdb->insert_id;
			}
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_sermons_tags VALUES (null, $id, $existing_id)");
		}
		sb_delete_unused_tags();
		// everything is fine, get out of here!
		if(!isset($error)) {
			sb_ping_gallery();
			echo "<script>document.location = '".admin_url('admin.php?page=sermon-browser/sermon.php&saved=true')."';</script>";
			die();
		}
	}

	$id3_tags = array();
	if (isset($_GET['getid3'])) {
		require_once(SB_INCLUDES_DIR.'/getid3/getid3.php');
		$file_data = $wpdb->get_row("SELECT name, type FROM {$wpdb->prefix}sb_stuff WHERE id = ".$wpdb->escape($_GET['getid3']));
		if ($file_data !== NULL) {
			$getID3 = new getID3;
			if ($file_data->type == 'url') {
				$filename = substr($file_data->name, strrpos ($file_data->name, '/')+1);
				$sermonUploadDir = SB_ABSPATH.sb_get_option('upload_dir');
				$tempfilename = $sermonUploadDir.preg_replace('/([ ])/e', 'chr(rand(97,122))', '		').'.mp3';
				if ($tempfile = @fopen($tempfilename, 'wb'))
					if ($remote_file = @fopen($file_data->name, 'r')) {
						$remote_contents = '';
						while (!feof($remote_file)) {
							$remote_contents .= fread($remote_file, 8192);
							if (strlen($remote_contents) > 65536)
							   break;
						}
						fwrite($tempfile, $remote_contents);
						fclose($remote_file);
						fclose($tempfile);
						$id3_raw_tags = $getID3->analyze(realpath($tempfilename));
						unlink ($tempfilename);
					}
			} else {
				$filename = $file_data->name;
				$id3_raw_tags = $getID3->analyze(realpath(SB_ABSPATH.sb_get_option('upload_dir').$filename));
			}
			if (!isset($id3_raw_tags['tags'])) {
				echo '<div id="message" class="updated fade"><p><b>'.__('No ID3 tags found.', $sermon_domain);
				if ($file_data->type == 'url')
					 echo ' Remote files must have id3v2 tags.';
				echo '</b></div>';
			}
			getid3_lib::CopyTagsToComments($id3_raw_tags);
			if (sb_get_option ('import_title'))
				$id3_tags['title'] = @$id3_raw_tags['comments_html']['title'][0];
			if (sb_get_option ('import_comments'))
				$id3_tags['description'] = @$id3_raw_tags['comments_html']['comments'][0];
			if (sb_get_option ('import_album')) {
				$id3_tags['series'] = @$id3_raw_tags['comments_html']['album'][0];
				if ($id3_tags['series'] != '') {
					$series_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}sb_series WHERE name LIKE '{$id3_tags['series']}'");
					if ($series_id === NULL) {
						$wpdb->query("INSERT INTO {$wpdb->prefix}sb_series VALUES (null, '{$id3_tags['series']}', '0')");
						$series_id = $wpdb->insert_id;
					}
					$id3_tags['series'] = $series_id;
				}
			}
			if (sb_get_option ('import_artist')) {
				$id3_tags['preacher'] = @$id3_raw_tags['comments_html']['artist'][0];
				if ($id3_tags['preacher'] != '') {
					$preacher_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}sb_preachers WHERE name LIKE '{$id3_tags['preacher']}'");
					if ($preacher_id === NULL) {
						$wpdb->query("INSERT INTO {$wpdb->prefix}sb_preachers VALUES (null, '{$id3_tags['preacher']}', '', '')");
						$preacher_id = $wpdb->insert_id;
					}
					$id3_tags['preacher'] = $preacher_id;
				}
			}
			$date_format = sb_get_option('import_filename');
			if ($date_format != '') {
				$filename = substr($filename, 0, strrpos($filename, '.'));
				$filename = str_replace ('--', '-', str_replace ('/', '-', $filename));
				$filename = trim(ereg_replace('[^0-9-]', '', $filename), '-');
				$date = explode('-', $filename, 3);
				$id3_tags['date'] = '';
				if (count($date) >= 3) {
					if ($date_format == 'uk')
						$id3_tags['date'] = date ('Y-m-d', mktime(0, 0, 0, $date[1], $date[0], $date[2]));
					elseif ($date_format == 'us')
						$id3_tags['date'] = date ('Y-m-d', mktime(0, 0, 0, $date[0], $date[1], $date[2]));
					elseif ($date_format == 'int')
						$id3_tags['date'] = date ('Y-m-d', mktime(0, 0, 0, $date[1], $date[2], $date[0]));
				}
			}
		}
	}

	// load existing data
	$preachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY name asc");
	$services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_services ORDER BY name asc");
	$series = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY name asc");
	$files = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = 0 AND type = 'file' ORDER BY name asc");

	// sync
	$wanted[] = -1;
	foreach ((array) $files as $k => $file) {
		if (!file_exists(SB_ABSPATH.sb_get_option('upload_dir').$file->name)) {
			$wanted[] = $file->id;
			unset($files[$k]);
		}
	}

	foreach ($services as $service) {
		$serviceId[] = $service->id;
		$deftime[] = $service->time;
	}

	$timeArr = '';
	for ($lol = 0; $lol < count($serviceId); $lol++) {
		$timeArr .= "timeArr[{$serviceId[$lol]}] = '$deftime[$lol]';";
	}

	if (isset($_GET['mid'])) {
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
	} else
		$startArr = $endArr = array();
	$books = sb_get_bible_books();
?>
	<script type="text/javascript">
		var timeArr = new Array();
		<?php echo $timeArr ?>
		function createNewPreacher(s) {
			if (jQuery('#preacher')[0].value != 'newPreacher') return;
			var p = prompt("<?php _e("New preacher's name?", $sermon_domain)?>", "<?php _e("Preacher's name", $sermon_domain)?>");
			if (p != null) {
				jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/sermon.php'); ?>', {pname: p, sermon: 1}, function(r) {
					if (r) {
						jQuery('#preacher option:first').before('<option value="' + r + '">' + p + '</option>');
						jQuery("#preacher option[value='" + r + "']").attr('selected', 'selected');
					};
				});
			}
		}
		function createNewService(s) {
			if (jQuery('#service')[0].value != 'newService') {
				if (!jQuery('#override')[0].checked) {
					jQuery('#time').val(timeArr[jQuery('#service')[0].value]).attr('disabled', 'disabled');
				}
				return;
			}
			var s = 'lol';
			while ((s.indexOf('@') == -1) || (s.match(/(.*?)@(.*)/)[2].match(/[0-9]{1,2}:[0-9]{1,2}/) == null)) {
				s = prompt("<?php _e("New service's name @ default time?", $sermon_domain)?>", "<?php _e("Service's name @ 18:00", $sermon_domain)?>");
				if (s == null) { break;	}
			}
			if (s != null) {
				jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/sermon.php'); ?>', {sname: s, sermon: 1}, function(r) {
					if (r) {
						jQuery('#service option:first').before('<option value="' + r + '">' + s.match(/(.*?)@/)[1] + '</option>');
						jQuery("#service option[value='" + r + "']").attr('selected', 'selected');
						jQuery('#time').val(s.match(/(.*?)@\s*(.*)/)[2]);
					};
				});
			}
		}
		function createNewSeries(s) {
			if (jQuery('#series')[0].value != 'newSeries') return;
			var ss = prompt("<?php _e("New series' name?", $sermon_domain)?>", "<?php _e("Series' name", $sermon_domain)?>");
			if (ss != null) {
				jQuery.post('<?php echo admin_url('admin.php?page=sermon-browser/sermon.php'); ?>', {ssname: ss, sermon: 1}, function(r) {
					if (r) {
						jQuery('#series option:first').before('<option value="' + r + '">' + ss + '</option>');
						jQuery("#series option[value='" + r + "']").attr('selected', 'selected');
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
			if (jQuery('#endbook')[0].value != "") return;
			var slc = jQuery('#startbook')[0].value;
			jQuery('.passage').each(function(i) {
				if (this == jQuery(s).parents('.passage')[0]) {
					jQuery('.end').each(function(j) {
						if (i == j) {
							jQuery("option[value='" + slc + "']", this).attr('selected', 'selected');
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
			jQuery("option[value='0']", f).attr('selected', 'selected');
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
				jQuery('#time').addClass('gray').val(timeArr[jQuery('#service')[0].value]).attr('disabled', 'disabled');
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
	<?php sb_do_alerts(); ?>
	<div class="wrap">
		<a href="http://www.sermonbrowser.com/"><img src="<?php echo SB_PLUGIN_URL; ?>/sb-includes/logo-small.png" width="191" height ="35" style="margin: 1em 2em; float: right; background: #f9f9f9;" /></a>
		<h2><?php echo isset($_GET['mid']) ? 'Edit Sermon' : 'Add Sermon' ?></h2>
		<?php if (!isset($_GET['mid']) && !isset($_GET['getid3']) && sb_get_option('import_prompt')) {
			if  (!sb_import_options_set()) {
				echo '<p class="plugin-update">';
				sb_print_import_options_message(true);
				echo "</p>\n";
			} else {
				sb_print_upload_form();
			}
		} ?>
		<br/>
		<form method="post" enctype="multipart/form-data">
		<fieldset>
			<table class="widefat">
				<thead>
					<tr>
						<th scope="col" colspan="2"><?php _e('Enter sermon details', $sermon_domain) ?></th>
					</tr>
				</thead>
				<tr>
					<td>
						<strong><?php _e('Title', $sermon_domain) ?></strong>
						<div>
							<input type="text" value="<?php if (isset($id3_tags['title'])) echo $id3_tags['title']; elseif (isset($curSermon->title)) echo htmlspecialchars(stripslashes($curSermon->title)); ?>" name="title" size="60" style="width:400px;" />
						</div>
					</td>
					<td>
						<strong><?php _e('Tags (comma separated)', $sermon_domain) ?></strong>
						<div>
							<input type="text" name="tags" value="<?php echo isset($tags) ? stripslashes($tags) : ''?>" style="width:400px" />
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
									<?php foreach ($preachers as $preacher):
											if (isset($id3_tags['preacher']))
												$preacher_id = $id3_tags['preacher'];
											elseif (isset ($curSermon->preacher_id))
												$preacher_id = $curSermon->preacher_id;
											else
												$preacher_id = -1; ?>
									<option value="<?php echo $preacher->id ?>" <?php echo $preacher->id == $preacher_id ? 'selected="selected"' : ''?>><?php echo htmlspecialchars(stripslashes($preacher->name), ENT_QUOTES) ?></option>
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
								<?php foreach ($series as $item):
										if (isset($id3_tags['series']))
											$series_id = $id3_tags['series'];
										elseif (isset($curSermon->series_id))
											$series_id = $curSermon->series_id;
										else
											$series_id = -1; ?>
									<option value="<?php echo $item->id ?>" <?php echo $item->id == $series_id ? 'selected="selected"' : '' ?>><?php echo htmlspecialchars(stripslashes($item->name), ENT_QUOTES) ?></option>
								<?php endforeach ?>
							<?php endif ?>
							<option value="newSeries"><?php _e('Create new series', $sermon_domain) ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td style="overflow: visible">
						<strong><?php _e('Date', $sermon_domain) ?></strong> (yyyy-mm-dd)
						<div>
							<input type="text" id="date" name="date" value="<?php if ((isset($curSermon->datetime) && $curSermon->datetime != '1970-01-01 00:00:00') || isset($id3_tags['date'])) echo isset($id3_tags['date']) ? $id3_tags['date'] : substr(stripslashes($curSermon->datetime),0,10) ?>" />
						</div>
					</td>
					<td rowspan="3">
						<strong><?php _e('Description', $sermon_domain) ?></strong>
						<div>
							<?php	if (isset($id3_tags['description']))
										$desc = $id3_tags['description'];
									elseif (isset($curSermon->description))
										$desc = stripslashes($curSermon->description);
									else
										$desc = ''; ?>
							<textarea name="description" cols="50" rows="7"><?php echo $desc; ?></textarea>
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
									<option value="<?php echo $service->id ?>" <?php echo (isset($curSermon->service_id) && $service->id == $curSermon->service_id) ? 'selected="selected"' : '' ?>><?php echo htmlspecialchars(stripslashes($service->name), ENT_QUOTES) ?></option>
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
							<input type="text" name="time" value="<?php echo isset($curSermon->time) ? $curSermon->time : ''?>" id="time" <?php echo isset($curSermon->override) && $curSermon->override ? '' : 'disabled="disabled" class="gray"' ?> />
							<input type="checkbox" name="override" style="width:30px" id="override" onchange="doOverride()" <?php echo isset($curSermon->override) && $curSermon->override ? 'checked="checked"' : ''?>> <?php _e('Override default time', $sermon_domain) ?>
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
									<select id="startbook" name="start[book][]" onchange="syncBook(this)" class="start1">
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
									<select id="endbook" name="end[book][]" class="end">
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
						<strong><?php _e('Attachments', $sermon_domain) ?></strong> (<a href="javascript:addFile()"><?php _e('add more', $sermon_domain) ?></a>)
					</td>
				</tr>
				<tr >
					<td colspan="2">
						<table>
							<tr id="choosefile" class="choose">
								<th scope="row" style="padding:3px 7px">
								<select class="choosefile" name="choosefile" onchange="chooseType(this.name, this.value);">
								<option value="filelist"><?php _e('Choose existing file:', $sermon_domain) ?></option>
								<option value="newupload"><?php _e('Upload a new one:', $sermon_domain) ?></option>
								<option value="newurl"><?php _e('Enter an URL:', $sermon_domain) ?></option>
								<option value="newcode"><?php _e('Enter embed or shortcode:', $sermon_domain) ?></option>
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
			jQuery('#time').val(timeArr[jQuery('*[selected]', jQuery("select[name='service']")).attr('value')]);
		<?php endif ?>
		<?php if (isset($mid) | (isset($filename) && $filename != '')): ?>
			stuff = new Array();
			type = new Array();
			start1 = new Array();
			start2 = new Array();
			start3 = new Array();
			end1 = new Array();
			end2 = new Array();
			end3 = new Array();

			<?php
				if (isset($mid)) {
					$assocFiles = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = {$mid} AND type = 'file' ORDER BY name asc;");
					$assocURLs = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = {$mid} AND type = 'url' ORDER BY name asc;");
					$assocCode = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = {$mid} AND type = 'code' ORDER BY name asc;");
				}
				else
					$assocFiles = $assocURLs = $assocCode = array();
				$r = false;
				if (isset($filename) && $filename != '')
					if ($file_data->type == 'url')
						$assocURLs[]->name = $file_data->name;
					else
						$assocFiles[]->id = $_GET['getid3'];
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
						jQuery("option[value='filelist']", this).attr('selected', 'selected');
						jQuery('.filelist', this).css('display','');
						jQuery("option[value='" + stuff[i] + "']", this).attr('selected', 'selected');
						break;
					case 'url':
						jQuery('td', this).css('display', 'none');
						jQuery("option[value='newurl']", this).attr('selected', 'selected');
						jQuery('.newurl ', this).css('display','');
						jQuery(".newurl input", this).val(stuff[i]);
						break;
					case 'code':
						jQuery('td', this).css('display', 'none');
						jQuery("option[value='newcode']", this).attr('selected', 'selected');
						jQuery('.newcode', this).css('display','');
						jQuery(".newcode input", this).val(Base64.decode(stuff[i]));
						break;
				}
			});

			jQuery('.start1').each(function(i) {
				jQuery("option[value='" + start1[i] + "']", this).attr('selected', 'selected');
			});

			jQuery('.end').each(function(i) {
				jQuery("option[value='" + end1[i] + "']", this).attr('selected', 'selected');
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

/**
* Displays the help page
*/
function sb_help() {
global $sermon_domain;
sb_do_alerts();
?>
	<div class="wrap">
		<a href="http://www.sermonbrowser.com/"><img src="<?php echo SB_PLUGIN_URL; ?>/sb-includes/logo-small.png" width="191" height ="35" style="margin: 1em 2em; float: right; background: #f9f9f9;" /></a>
		<div style="width:45%;float:right;clear:right">
		<h2>Thank you</h2>
		<p>A number of individuals and churches have kindly <a href="http://www.sermonbrowser.com/donate/">donated</a> to the development of Sermon Browser. Their support is very much appreciated. Since April 2011, all donations have been sent to <a href="<?php echo admin_url('admin.php?page=sermon-browser/japan.php')?>">support the ministry of Nathanael and Anna Ayling</a> in Japan.</p>
		<ul style="list-style-type:circle; margin-left: 2em">
			<li><a href="http://www.cambray.org/" target="_blank">Cambray Baptist Church</a>, UK</li>
			<li><a href="http://www.bethel-clydach.co.uk/" target="_blank">Bethel Baptist Church</a>, Clydach, UK</li>
			<li><a href="http://www.bethel-laleston.co.uk/" target="_blank">Bethel Baptist Church</a>, Laleston, UK</li>
			<li><a href="http://www.hessonchurch.com/" target="_blank">Hesson Christian Fellowship</a>, Ontario, Canada</li>
			<li><a href="http://www.icvineyard.org/" target="_blank">Vineyard Community Church</a>, Iowa</li>
			<li><a href="http://www.cbcsd.us/" target="_blank">Chinese Bible Church of San Diego</a>, California</li>
			<li><a href="http://thecreekside.org/" target="_blank">Creekside Community Church</a>, Texas</li>
			<li><a href="http://stluke.info/" target="_blank">St. Luke Lutheran Church, Gales Ferry</a>, Connecticut</li>
			<li><a href="http://www.bunnbaptistchurch.org/" target="_blank">Bunn Baptist Church</a>, North Carolina</li>
			<li><a href="http://www.ccpconline.org" target="_blank">Christ Community Presbyterian Church</a>, Florida</li>
			<li><a href="http://www.harborhawaii.org" target="_blank">Harbor Church</a>, Hawaii</li>
			<li>Vicky H, UK</li>
			<li>Ben S, UK</li>
			<li>Tom W, UK</li>
			<li>Gavin D, UK</li>
			<li>Douglas C, UK</li>
			<li>David A, UK</li>
			<li>Thomas C, Canada</li>
			<li>Daniel J, Germany</li>
			<li>Hiromi O, Japan</li>
			<li>David C, Australia</li>
			<li>Lou B, Australia</li>
			<li>Edward P, Delaware</li>
			<li>Steve J, Pensylvania</li>
			<li>William H, Indiana</li>
			<li>Brandon E, New Jersey</li>
			<li>Jamon A, Missouri</li>
			<li>Chuck H, Tennessee</li>
			<li>David F, Maryland</li>
			<li>Antony L, California</li>
			<li>David W, Florida</li>
			<li>Fabio P, Connecticut</li>
			<li>Bill C, Georgia</li>
			<li>Scott J, Florida</li>
			<li><a href="http://www.emw.org.uk/" target="_blank">Evangelical Movement of Wales</a>, UK</li>
			<li><a href="http://BetterCommunication.org" target="_blank">BetterCommunication.org</a></li>
			<li>Home and Outdoor Living, Indiana</li>
			<li><a href="http://design.ddandhservices.com/" target="_blank">DD&H Services</a>, British Columbia</li>
			<li><a href="http://www.dirtroadphotography.com" target="_blank">Dirt Road Photography</a>, Nebraska</li>
			<li><a href="http://www.hardeysolutions.com/" target="_blank">Hardey Solutions</a>, Houston</li>
			<li><a href="http://www.olivetreehost.com/" target="_blank">Olivetreehost.com</a></li>
			<li><a href="http://www.onQsites.com/" target="_blank">onQsites</a>, South Carolina</li>
			<li>Glorified Web Solutions</li>
		</ul>
		<p>Additional help was also received from:</p>
		<ul style="list-style-type:circle; margin-left: 2em">
			<li><a href="http://codeandmore.com/">Tien Do Xuan</a> (help with initial coding).
			<li>James Hudson, Matthew Hiatt, Mark Bouchard (code contributions)</li>
			<li>Juan Carlos and Marvin Ortega (Spanish translation)</li>
			<li><a href="http://www.fatcow.com/">FatCow</a> (Russian translation)</li>
			<li><a href="http://intercer.net/">Lucian Mihailescu</a> (Romanian translation)</li>
			<li>Monika Gause (German translation)</li>
			<li><a href="http://www.djio.com.br/sermonbrowser-em-portugues-brasileiro-pt_br/">DJIO</a> (Brazilian Portugese translation)</li>
			<li>Numerous <a href="http://www.sermonbrowser.com/forum/">forum contributors</a> for feature suggestions and bug reports</li>
		</ul>
	</div>
		<div style="width:45%;float:left">
		<h2><?php _e('Help page', $sermon_domain) ?></h2>
		<h3>Screencasts</h3>
		<p>If you need help with using SermonBrowser for the first time, these five minute screencast tutorials should be your first port of call (the tutorials were created with an older version of SermonBrowser, and an older version of Wordpress, but things haven't changed a great deal):</p>
		<ul>
			<li><a href="http://www.sermonbrowser.com/tutorials/#efe-swf-1" target="_blank">Installation and Overview</a></li>
			<li><a href="http://www.sermonbrowser.com/tutorials/#efe-swf-2" target="_blank">Basic Options</a></li>
			<li><a href="http://www.sermonbrowser.com/tutorials/#efe-swf-3" target="_blank">Preachers, Series and Services</a></li>
			<li><a href="http://www.sermonbrowser.com/tutorials/#efe-swf-4" target="_blank">Entering a new sermon</a></li>
			<li><a href="http://www.sermonbrowser.com/tutorials/#efe-swf-5" target="_blank">Editing a sermon and adding embedded video</a></li>
		</ul>
		<h3>Template tags</h3>
		<p>If you want to change the way SermonBrowser displays on your website, you'll need to edit the templates and/or CSS file. Check out this guide to <a href="http://www.sermonbrowser.com/customisation/" target="_blank">template tags</a>.</p>
		<h3>Shortcode</h3>
		<p>You can put individual sermons or lists of sermons on any page of your website. You do this by adding a <a href="http://www.sermonbrowser.com/customisation/" target="_blank">shortcode</a> into a WordPress post or page.</p>
		<h3>Frequently asked questions</h3>
		<p>A <a href="http://www.sermonbrowser.com/faq/" target="_blank">comprehensive FAQ</a> is available on sermonbrowser.com.</p>
		<h3>Further help</h3>
		<p>If you have a problem that the FAQ doesn't answer, or you have a feature suggestion, please use the <a href="http://www.sermonbrowser.com/forum/" target="_blank">SermonBrowser forum</a>.</p>
		</div>
	</form>
<?php
}

function sb_japan() {
sb_do_alerts();
?>
	<div class="wrap">
		<a href="hthttp://www.sermonbrowser.com/"><img src="<?php echo SB_PLUGIN_URL; ?>/sb-includes/logo-small.png" width="191" height ="35" style="margin: 1em 2em; float: right; background: #f9f9f9;" /></a>
		<h2 style=>Help support Christian ministry in Japan</h2>
		<div style="float:right;clear:both; width:208px; padding-left:20px">
			<img src="http://www.bethel-clydach.co.uk/wp-content/uploads/2010/01/Nathanael-and-Anna-188x300.jpg" width="188" height="300" />
		</div>
		<div style="width:533px; float:left">
			<iframe src="http://player.vimeo.com/video/19995544?title=0&amp;byline=0&amp;portrait=0" width="533" height="300" frameborder="0"></iframe>
		</div>
		<div style="margin-left:553px;">
			<p>Since April 2011, all gifts donated to Sermon Browser have been given to support the work of <a href="http://www.bethel-clydach.co.uk/about/mission-partners/nathanael-and-anna-ayling/">Nathanael and Anna Ayling</a> in Japan.
		 	Nathanael and Anna are members of a small church in the UK where the the author of Sermon Browser is a minister. Together with little Ethan, they have been in Japan since April 2010, and are based in Sappororo in the north,
		 	undergoing intensive language training so that by God's grace they can work alongside Japanese Christians to make disciples of Jesus among Japanese students. They are being cared for by <a href="http://www.omf.org/omf/japan/about_us">OMF International</a> (formerly known as the China Inland Mission, and founded by 
		 	Hudson Taylor in 1865).</p>
		 	<p>If you value Sermon Browser, please consider supporting Nathanael and Anna. You can do this by:</p>
		 	<ul>
		 		<li><a href="http://ateamjapan.wordpress.com/">Looking at their blog</a>, and praying about their latest news.</li>
		 		<li><a href="http://www.omf.org/omf/uk/omf_at_work/pray_for_omf_workers">Signing up</a> to receiving their regular prayer news.</li>
		 		<li><form style="float:left" action="https://www.paypal.com/cgi-bin/webscr" method="post"><input type="hidden" name="cmd" value="_s-xclick" /><input type="hidden" name="hosted_button_id" value="YTB9ZW4P5F536" /><input type="image" src="https://www.paypalobjects.com/WEBSCR-640-20110429-1/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!" /><img alt="" border="0" src="https://www.paypalobjects.com/WEBSCR-640-20110429-1/en_GB/i/scr/pixel.gif" width="1" height="1" /></form> towards their ongoing support.</li>
		 	</ul>
		</div>
	</div>
<?php	
}
/***************************************
 ** Supplementary functions           **
 **************************************/

/**
* Displays alerts in admin for new users
*/
function sb_do_alerts() {
	global $wpdb, $sermon_domain;
	if (stripos(sb_get_option('mp3_shortcode'), '%SERMONURL%') === FALSE) {
		echo '<div id="message" class="updated fade"><p><b>';
		_e('Error:</b> The MP3 shortcode must link to individual sermon files. You do this by including <span style="color:red">%SERMONURL%</span> in your shortcode (e.g. [audio:%SERMONURL%]). SermonBrowser will then replace %SERMONURL% with a link to each sermon.', $sermon_domain);
		echo '</div>';
	} elseif (do_shortcode(sb_get_option('mp3_shortcode')) == sb_get_option('mp3_shortcode')) {
		if ((substr(sb_get_option('mp3_shortcode'), 0, 18) == '[audio:%SERMONURL%') && !function_exists('ap_insert_player_widgets')) {
			if ($wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}sb_stuff WHERE name LIKE '%.mp3'")>0)
				echo '<div id="message" class="updated"><p><b>'.sprintf(__('Tip: Installing the %1$sWordpress Audio Player%2$s, or another Wordpress MP3 player plugin will allow users to listen to your sermons more easily.', $sermon_domain), '<a href="'.site_url().'/wp-admin/plugin-install.php?tab=search&s=audio%20player&type=term&search=Search">', '</a>').'</b></div>';
		} elseif (substr(sb_get_option('mp3_shortcode'), 0, 18) != '[audio:%SERMONURL%') {
			echo '<div id="message" class="updated fade"><p><b>';
			_e('Error:</b> You have specified a custom MP3 shortcode, but Wordpress doesn&#146;t know how to interpret it. Make sure the shortcode is correct, and that the appropriate plugin is activated.', $sermon_domain);
			echo '</div>';
		}
	}
	if (sb_display_url() == "") {
		echo '<div id="message" class="updated"><p><b>'.__('Hint:', $sermon_domain).'</b> '.sprintf(__('%sCreate a page%s that includes the shortcode [sermons], so that SermonBrowser knows where to display the sermons on your site.', $sermon_domain), '<a href="'.site_url().'/wp-admin/page-new.php">', '</a>').'</div>';
	} else {
		if (!function_exists('ap_insert_player_widgets')) {
		}
	}
}

/**
* Show the textarea input
*/
function sb_build_textarea($name, $html) {
	$out = '<textarea name="'.$name.'" cols="75" rows="20" style="width:100%">';
	$out .= stripslashes(str_replace('\r\n', "\n", $html));
	$out .= '</textarea>';
	echo $out;
}

/**
* Displays stats in the dashboard
*/
function sb_rightnow () {
	global $wpdb, $sermon_domain;
	$file_count = $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."sb_stuff WHERE type='file'");
	$output_string = '';
	if ($file_count > 0) {
		$sermon_count = $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."sb_sermons");
		$preacher_count = $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."sb_preachers");
		$series_count = $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."sb_series");
		$tag_count = $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."sb_tags WHERE name<>''");
		$download_count = $wpdb->get_var("SELECT SUM(count) FROM ".$wpdb->prefix."sb_stuff");
		if ($sermon_count == 0) {
			$download_average = 0;
		} else {
			$download_average = round($download_count/$sermon_count, 1);
		}
		$most_popular = $wpdb->get_results("SELECT title, sermon_id, sum(count) AS c FROM {$wpdb->prefix}sb_stuff LEFT JOIN {$wpdb->prefix}sb_sermons ON {$wpdb->prefix}sb_sermons.id = sermon_id GROUP BY sermon_id ORDER BY c DESC LIMIT 1");
		$most_popular = $most_popular[0];
		$output_string .= '<p class="youhave">'.__("You have")." ";
		$output_string .= '<a href="'.site_url().'/wp-admin/admin.php?page=sermon-browser/files.php">';
		$output_string .= sprintf(_n('%s file', '%s files', $file_count), number_format($file_count))."</a> ";
		if ($sermon_count > 0) {
			$output_string .= __("in")." ".'<a href="'.admin_url('admin.php?page=sermon-browser/sermon.php').'">';
			$output_string .= sprintf(_n('%s sermon', '%s sermons', $sermon_count), number_format($sermon_count))."</a> ";
		}
		if ($preacher_count > 0) {
			$output_string .= __("from")." ".'<a href="'.site_url().'/wp-admin/admin.php?page=sermon-browser/preachers.php">';
			$output_string .= sprintf(_n('%s preacher', '%s preachers', $preacher_count), number_format($preacher_count))."</a> ";
		}
		if ($series_count > 0) {
			$output_string .= __("in")." ".'<a href="'.site_url().'/wp-admin/admin.php?page=sermon-browser/manage.php">';
			$output_string .= sprintf(__('%s series'), number_format($series_count))."</a> ";
		}
		if ($tag_count > 0)
			$output_string .= __("using")." ".sprintf(_n('%s tag', '%s tags', $tag_count), number_format($tag_count))." ";
		if (substr($output_string, -1) == " ")
			$output_string = substr($output_string, 0, -1);
		if ($download_count > 0)
			$output_string .= ". ".sprintf(_n('Only one file has been downloaded', 'They have been downloaded a total of %s times', $download_count), number_format($download_count));
		if ($download_count > 1) {
			$output_string .= ", ".sprintf(_n('an average of once per sermon', 'an average of %d times per sermon', $download_average), $download_average);
			$most_popular_title = '<a href="'.sb_display_url().sb_query_char(true).'sermon_id='.$most_popular->sermon_id.'">'.stripslashes($most_popular->title).'</a>';
			$output_string .= ". ".sprintf(__('The most popular sermon is %s, which has been downloaded %s times'), $most_popular_title, number_format($most_popular->c));
		}
		$output_string .= '.</p>';
	}
	echo $output_string;
}

/**
* Find new files uploaded by FTP
*/
function sb_scan_dir() {
	global $wpdb;
	$files = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE type = 'file';");
	$bnn = array();
	$dir = SB_ABSPATH.sb_get_option('upload_dir');
	foreach ($files as $file) {
		$bnn[] = $file->name;
		if (!file_exists($dir.$file->name)) {
			$wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE name='".mysql_real_escape_string($file->name)."' AND sermon_id=0;");
		}
	}

	if ($dh = @opendir($dir)) {
		while (false !== ($file = readdir($dh))) {
			if ($file != "." && $file != ".." && !is_dir($dir.$file) && !in_array($file, $bnn)) {
				$file = mysql_real_escape_string($file);
				$wpdb->query("INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'file', '{$file}', 0, 0, 0);");
			   }
		}
		   closedir($dh);
	}
}

/**
* Check to see if upload folder is writeable
*
* @return string 'writeable/unwriteable/notexist'
*/

function sb_checkSermonUploadable($foldername = "") {
	$sermonUploadDir = SB_ABSPATH.sb_get_option('upload_dir').$foldername;
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

/**
* Delete any unused tags
*/
function sb_delete_unused_tags() {
	global $wpdb;
	$unused_tags = $wpdb->get_results("SELECT {$wpdb->prefix}sb_tags.id AS id, {$wpdb->prefix}sb_sermons_tags.id AS sid FROM {$wpdb->prefix}sb_tags LEFT JOIN {$wpdb->prefix}sb_sermons_tags ON {$wpdb->prefix}sb_tags.id = {$wpdb->prefix}sb_sermons_tags.tag_id WHERE {$wpdb->prefix}sb_sermons_tags.tag_id IS NULL");
	if (is_array($unused_tags))
		foreach ($unused_tags AS $unused_tag)
			$wpdb->query("DELETE FROM {$wpdb->prefix}sb_tags WHERE id='{$unused_tag->id}'");
}

/**
* Displays the main sermon widget options and handles changes
*/
function sb_widget_sermon_control( $widget_args = 1 ) {
	global $wpdb, $sermon_domain;
	global $wp_registered_widgets;
	static $updated = false;

	$dpreachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY id;");
	$dseries = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY id;");
	$dservices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_services ORDER BY id;");

	if ( is_numeric($widget_args) )
		$widget_args = array( 'number' => $widget_args );
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract( $widget_args, EXTR_SKIP );

	$options = sb_get_option('sermons_widget_options');
	if ( !is_array($options) )
		$options = array();

	if ( !$updated && !empty($_POST['sidebar']) ) {
		$sidebar = (string) $_POST['sidebar'];
		$sidebars_widgets = wp_get_sidebars_widgets();
		if ( isset($sidebars_widgets[$sidebar]) )
			$this_sidebar =& $sidebars_widgets[$sidebar];
		else
			$this_sidebar = array();
		foreach ( $this_sidebar as $_widget_id ) {
			if ( 'sb_widget_sermon' == $wp_registered_widgets[$_widget_id]['callback'] && isset($wp_registered_widgets[$_widget_id]['params'][0]['number']) ) {
				$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
				if ( !in_array( "sermon-$widget_number", $_POST['widget-id'] ) )
					unset($options[$widget_number]);
			}
		}
		foreach ( (array) $_POST['widget-sermon'] as $widget_number => $widget_sermon_instance ) {
			if ( !isset($widget_sermon_instance['limit']) && isset($options[$widget_number]) )
				continue;
			$limit = wp_specialchars( $widget_sermon_instance['limit'] );
			$preacherz = (int) $widget_sermon_instance['preacherz'];
			$preacher = (int) $widget_sermon_instance['preacher'];
			$service = (int) $widget_sermon_instance['service'];
			$series = (int) $widget_sermon_instance['series'];
			$book = (int) $widget_sermon_instance['book'];
			$title = strip_tags(stripslashes($widget_sermon_instance['title']));
			$date = (int) $widget_sermon_instance['date'];
			$player = (int) $widget_sermon_instance['player'];
			$options[$widget_number] = array( 'limit' => $limit, 'preacherz' => $preacherz, 'book' => $book, 'preacher' => $preacher, 'service' => $service, 'series' => $series, 'title' => $title, 'date' => $date, 'player' => $player);
		}
		sb_update_option('sermons_widget_options', $options);
		$updated = true;
	}

	// Display widget form
	if ( -1 == $number ) {
		$limit = '';
		$preacherz = 0;
		$book = 0;
		$number = '%i%';
		$preacher = '';
		$service = '';
		$series = '';
		$title ='';
		$date = '';
		$player = '';
	} else {
		$limit = attribute_escape($options[$number]['limit']);
		$preacher = attribute_escape($options[$number]['preacher']);
		$service = attribute_escape($options[$number]['service']);
		$series = attribute_escape($options[$number]['series']);
		$preacherz = (int) $options[$number]['preacherz'];
		$book = (int) $options[$number]['book'];
		$title = attribute_escape($options[$number]['title']);
		$date = (int) $options[$number]['date'];
		$player = attribute_escape($options[$number]['player']);
	}

?>
		<p><?php _e('Title:'); ?> <input class="widefat" id="widget-sermon-title" name="widget-sermon[<?php echo $number; ?>][title]" type="text" value="<?php echo $title; ?>" /></p>
		<p>
			<?php _e('Number of sermons: ', $sermon_domain) ?><input class="widefat" id="widget-sermon-limit-<?php echo $number; ?>" name="widget-sermon[<?php echo $number; ?>][limit]" type="text" value="<?php echo $limit; ?>" />
			<hr />
			<input type="checkbox" id="widget-sermon-preacherz-<?php echo $number ?>" name="widget-sermon[<?php echo $number ?>][preacherz]" <?php echo $preacherz ? 'checked=checked' : '' ?> value="1"> <?php _e('Display preacher', $sermon_domain) ?><br />
			<input type="checkbox" id="widget-sermon-book-<?php echo $number ?>" name="widget-sermon[<?php echo $number ?>][book]" <?php echo $book ? 'checked=checked' : '' ?> value="1"> <?php _e('Display bible passage', $sermon_domain) ?><br />
			<input type="checkbox" id="widget-sermon-date-<?php echo $number ?>" name="widget-sermon[<?php echo $number ?>][date]" <?php echo $date ? 'checked=checked' : '' ?> value="1"> <?php _e('Display date', $sermon_domain) ?><br />
			<input type="checkbox" id="widget-sermon-player-<?php echo $number ?>" name="widget-sermon[<?php echo $number ?>][player]" <?php echo $player ? 'checked=checked' : '' ?> value="1"> <?php _e('Display mini-player', $sermon_domain) ?>
			<hr />
			<table>
				<tr>
					<td><?php _e('Preacher: ', $sermon_domain) ?></td>
					<td>
						<select name="widget-sermon[<?php echo $number; ?>][preacher]" id="widget-sermon-preacher-<?php echo $number; ?>">
							<option value="0" <?php echo $preacher ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
							<?php foreach ($dpreachers as $cpreacher): ?>
								<option value="<?php echo $cpreacher->id ?>" <?php echo $preacher == $cpreacher->id ? 'selected="selected"' : '' ?>><?php echo $cpreacher->name ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
				<tr>
					<td><?php _e('Service: ', $sermon_domain) ?></td>
					<td>
						<select name="widget-sermon[<?php echo $number; ?>][service]" id="widget-sermon-service-<?php echo $number; ?>">
							<option value="0" <?php echo $service ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
							<?php foreach ($dservices as $cservice): ?>
								<option value="<?php echo $cservice->id ?>" <?php echo $service == $cservice->id ? 'selected="selected"' : '' ?>><?php echo $cservice->name ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
				<tr>
					<td><?php _e('Series: ', $sermon_domain) ?></td>
					<td>
						<select name="widget-sermon[<?php echo $number; ?>][series]" id="widget-sermon-series-<?php echo $number; ?>">
							<option value="0" <?php echo $series ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
							<?php foreach ($dseries as $cseries): ?>
								<option value="<?php echo $cseries->id ?>" <?php echo $series == $cseries->id ? 'selected="selected"' : '' ?>><?php echo $cseries->name ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
			</table>
			<input type="hidden" id="widget-sermon-submit-<?php echo $number; ?>" name="widget-sermon[<?php echo $number; ?>][submit]" value="1" />
		</p>
<?php
}

/**
* Displays the most popular sermons widget options and handles changes
*/
function sb_widget_popular_control() {
	global $sermon_domain;

	$options = sb_get_option('popular_widget_options');
	if ( !is_array($options) )
		$options = array('title' => '', 'limit' => 5, 'display_sermons' => true, 'display_series' => true, 'display_preachers' => true);

	if (isset($_POST['widget-popular-sermons-submit'])) {
		$title = strip_tags(stripslashes($_POST['widget-popular-title']));
		$limit = (int) ($_POST['widget-popular-limit']);
		$display_sermons = (isset($_POST['widget-popular-display-sermons']));
		$display_series = (isset($_POST['widget-popular-display-series']));
		$display_preachers = (isset($_POST['widget-popular-display-preachers']));

		$options = array('title' => $title, 'limit' => $limit, 'display_sermons' => $display_sermons, 'display_series' => $display_series, 'display_preachers' => $display_preachers);
		sb_update_option('popular_widget_options', $options);
	}

	$title = attribute_escape($options['title']);
	$limit = attribute_escape($options['limit']);
	$display_sermons = (boolean) attribute_escape($options['display_sermons']);
	$display_series = (boolean) attribute_escape($options['display_series']);
	$display_preachers = (boolean) attribute_escape($options['display_preachers']);

?>
		<p><?php _e('Title:'); ?> <input class="widefat" id="widget-popular-title" name="widget-popular-title" type="text" value="<?php echo $title; ?>" /></p>
		<p>
			<?php _e('Number of sermons: ', $sermon_domain) ?><select id="widget-popular-limit" name="widget-popular-limit"><?php for($i=1; $i<=15; $i++) { $sel = ($i==$limit) ? ' selected="yes"' : ''; echo "<option value=\"{$i}\"{$sel}>{$i}</option>"; } ?></select>
			<div style="clear:both">
				<input type="checkbox" id="widget-popular-display-sermons" name="widget-popular-display-sermons" <?php echo $display_sermons ? 'checked=checked' : '' ?> value="1"> <?php _e('Display popular sermons', $sermon_domain) ?><br />
				<input type="checkbox" id="widget-popular-display-series" name="widget-popular-display-series" <?php echo $display_series ? 'checked=checked' : '' ?> value="1"> <?php _e('Display popular series', $sermon_domain) ?><br />
				<input type="checkbox" id="widget-popular-display-preachers" name="widget-popular-display-preachers" <?php echo $display_preachers ? 'checked=checked' : '' ?> value="1"> <?php _e('Display popular preachers', $sermon_domain) ?><br />
			</div>
			<input type="hidden" id="widget-popular-sermons-submit" name="widget-popular-sermons-submit" value="1" />
		</p>
<?php
}

/**
* Returns true if any ID3 import options have been selected
*
* @return boolean
*/
function sb_import_options_set () {
	if (!sb_get_option('import_title') && !sb_get_option('import_artist') && !sb_get_option('import_album') && !sb_get_option('import_comments') && (!sb_get_option('import_filename') || sb_get_option('import_filename') == 'none'))
		return false;
	else
		return true;
}

/**
* Displays notice if ID3 import options have not been set
*/
function sb_print_import_options_message($long = FALSE) {
	global $sermon_domain;
	if (!sb_import_options_set()) {
		if ($long) {
			_e ('SermonBrowser can automatically pre-fill this form by reading ID3 tags from MP3 files.', $sermon_domain);
			echo ' ';
		}
		printf (__ ('You will need to set the %s before you can import MP3s and pre-fill the Add Sermons form.', $sermon_domain), '<a href="'.admin_url('admin.php?page=sermon-browser/options.php').'">'.__('import options', $sermon_domain).'</a>');
	}
}

/**
* echoes the upload form
*/
function sb_print_upload_form () {
	global $wpdb, $sermon_domain;
?>
	<table width="100%" cellspacing="2" cellpadding="5" class="widefat">
		<form method="post" enctype="multipart/form-data" action ="<?php echo admin_url('admin.php?page=sermon-browser/files.php'); ?>" >
		<thead>
		<tr>
			<th scope="col" colspan="3"><?php if (sb_import_options_set()) printf(__("Select an MP3 file here to have the %s form pre-filled using ID3 tags.", $sermon_domain), "<a href=\"".admin_url('admin.php?page=sermon-browser/new_sermon.php')."\">".__('Add Sermons', $sermon_domain).'</a>'); else _e('Upload file', $sermon_domain);?></th>
		</tr>
		</thead>
		<tbody>
		<tr>
			<th nowrap style="width:20em" valign="top" scope="row"><?php _e('File to upload', $sermon_domain) ?>: </th>
	<?php
	$checkSermonUpload = sb_checkSermonUploadable();
	if ($checkSermonUpload == 'writeable') {
	?>
			<td width ="40"><input type="file" size="40" value="" name="upload" /></td>
			<td class="submit"><input type="submit" name="save" value="<?php _e('Upload', $sermon_domain) ?> &raquo;" /></td>
	<?php
	} else
		if (IS_MU) {
	?>
			<td><?php _e('Upload is disabled. Please contact your systems administrator.', $sermon_domain);?></p>
	<?php
		} else {
	?>
			<td><?php _e('Upload is disabled. Please check your folder setting in Options.', $sermon_domain);?></p>
	<?php
	}
	?>
		</tr>
	<?php if (sb_import_options_set()) { ?>
		<tr>
			<th nowrap valign="top" scope="row"><?php _e('URL to import', $sermon_domain) ?>: </th>
			<td>
				<input type="text" size="40" value="" name="url"/><br/>
				<span style="line-height: 29px"><input type="radio" name="import_type" value="remote" checked="checked" /><?php _e('Link to remote file', $sermon_domain) ?> <input type="radio" name="import_type" value="download" /><?php _e('Copy remote file to server', $sermon_domain) ?></span>
			</td>
			<td class="submit"><input type="submit" name="import_url" value="<?php _e('Import', $sermon_domain) ?> &raquo;" /></td>
		</tr>
	<?php } ?>
	</form>
	<?php if ($_GET['page'] == 'sermon-browser/new_sermon.php') { ?>
		<form method="get" action="<?php echo admin_url();?>">
		<input type="hidden" name="page" value="sermon-browser/new_sermon.php" />
		<tr>
			<th nowrap valign="top" scope="row"><?php _e('Choose existing file', $sermon_domain) ?>: </th>
			<td>
				<select name="getid3">
					<?php
						$files = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = 0 AND type = 'file' ORDER BY name asc");
						echo count($files) == 0 ? '<option value="0">No files found</option>' : '<option value="0"></option>';
						foreach ($files as $file) { ?>
							<option value="<?php echo $file->id ?>"><?php echo $file->name ?></option>
						<?php } ?>
				</select>
			</td>
			<td class="submit"><input type="submit" value="<?php _e('Select', $sermon_domain) ?> &raquo;" /></td>
		</tr>
	</form>
	<?php } ?>
		</tbody>
</table>
<?php }

function sb_add_contextual_help($help) {
	global $sermon_domain;
	if (!isset($_GET['page']))
		return $help;
	else {
		$out = '<h5>'.__('SermonBrowser Help', $sermon_domain)."</h5>\n";
		$out .= '<div class="metabox-prefs"><p>';
		switch ($_GET['page']) {
			case 'sermon-browser/sermon.php':
				$out .= __('From this page you can edit or delete any of your sermons. The most recent sermons are found at the top. Use the filter options to quickly find the one you want.', $sermon_domain);
				break;
			case 'sermon-browser/new_sermon.php':
			case 'sermon-browser/files.php':
			case 'sermon-browser/preachers.php':
			case 'sermon-browser/manage.php':
			case 'sermon-browser/options.php':
				$out .= __('It&#146;s important that these options are set correctly, as otherwise SermonBrowser won&#146;t behave as you expect.', $sermon_domain).'<ul>';
				$out .= '<li>'.__('The upload folder would normally be <b>wp-content/uploads/sermons</b>', $sermon_domain).'</li>';
				$out .= '<li>'.__('You should only change the public podcast feed if you re-direct your podcast using a service like Feedburner. Otherwise it should be the same as the private podcast feed.', $sermon_domain).'</li>';
				$out .= '<li>'.__('The MP3 shortcode you need will be in the documation of your favourite MP3 plugin. Use the tag %SERMONURL% in place of the URL of the MP3 file (e.g. [haiku url="%SERMONURL%"] or [audio:%SERMONURL%]).', $sermon_domain).'</li></ul>';
				break;
			case 'sermon-browser/templates.php':
				$out .= sprintf(__('Template editing is one of the most powerful features of SermonBrowser. Be sure to look at the complete list of %stemplate tags%s.', $sermon_domain), '<a href="http://www.sermonbrowser.com/customisation/">', '</a>');
				break;
			case 'sermon-browser/uninstall.php':
			case 'sermon-browser/help.php':
		}
	}
	$out.= '</p><p><a href="http://www.sermonbrowser.com/tutorials/">'.__('Tutorial Screencasts').'</a>';
	$out.= ' | <a href="http://www.sermonbrowser.com/faq/">'.__('Frequently Asked Questions').'</a>';
	$out.= ' | <a href="http://www.sermonbrowser.com/forum/">'.__('Support Forum').'</a>';
	$out.= ' | <a href="http://www.sermonbrowser.com/customisation/">'.__('Shortcode syntax').'</a>';
	$out.= ' | <a href="http://www.sermonbrowser.com/donate/">'.__('Donate').'</a>';
	$out.= '</p></div>';
	return $out;
}
?>