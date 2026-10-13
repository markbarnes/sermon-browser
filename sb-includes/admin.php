<?php
if (!defined('ABSPATH'))
	exit;
/**
* Admin functions
*
* Functions required exclusively in the back end.
 *
* @package admin_functions
*/

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for


/**
* Adds javascript and CSS where required in admin
*/
function sb_add_admin_headers() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only chooses the scripts to load
	$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
	if ( substr( $page, 14 ) == 'sermon-browser' ) {
		wp_enqueue_script('jquery');
	}
	if ($page == 'sermon-browser/new_sermon.php') {
		wp_enqueue_style ('sb_style');
	}
}

/**
* Converts php.ini mega- or giga-byte numbers into kilobytes
*
* @param string $val
* @return integer
*/
function sb_return_kbytes($val) {
	$val = trim((string) $val);
	if ($val === '')
		return 0;
	$last = strtolower($val[strlen($val)-1]);
	$number = (int) $val;
	switch($last) {
		case 'g':
			$number *= 1024;
		case 'm':
			$number *= 1024;
	}
	return $number;
}

/**
* Display the options page and handle changes
*/
function sb_options() {
	global $wpdb;
	//Security check
	if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(esc_html__("You do not have the correct permissions to edit the SermonBrowser options", 'sermon-browser'));
	}
	//Reset options to default
	if (isset($_POST['resetdefault'])) {

		if ( ! isset( $_POST['sermon_options_save_reset_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sermon_options_save_reset_nonce'] ), 'sermon_options_save_reset' ) ) {
			wp_die( esc_html__( "You do not have the correct permissions to edit the SermonBrowser options", 'sermon-browser' ) );
		}

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
		sb_update_option('mp3_shortcode', '[audio mp3="%SERMONURL%"]');
		sb_update_option('esv_api_key', '');
		if (!is_dir (SB_ABSPATH.$dir) && sb_mkdir (SB_ABSPATH.$dir)) {
			sb_protect_upload_dir();
		}
		if (!is_dir (SB_ABSPATH.$dir.'images') && sb_mkdir(SB_ABSPATH.$dir.'images')) {
			sb_protect_upload_dir();
		}
		$books = sb_get_default('bible_books');
		$eng_books = sb_get_default('eng_bible_books');
		// Reset bible books database
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sb_books");
		for ($i=0; $i < count($books); $i++) {
			$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_books VALUES (null, %s)", $books[$i]));
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_books_sermons SET book_name = %s WHERE book_name = %s", $books[$i], $eng_books[$i]));
		}
		// Rewrite booknames for non-English locales
		if ($books != $eng_books) {
			$sermon_books = $wpdb->get_results("SELECT id, start, end FROM {$wpdb->prefix}sb_sermons");
			 foreach ($sermon_books as $sermon_book) {
				$start_verse = sb_unserialize($sermon_book->start);
				$end_verse = sb_unserialize($sermon_book->end);
				$start_index = array_search( $start_verse[0]['book'], $eng_books, true );
				$end_index   = array_search( $end_verse[0]['book'], $eng_books, true );
				if ( $start_index !== false ) {
					$start_verse[0]['book'] = $books[$start_index];
				}
				if ( $end_index !== false ) {
					$end_verse[0]['book'] = $books[$end_index];
				}
				$sermon_book->start = serialize ($start_verse);
				$sermon_book->end = serialize ($end_verse);
				$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_sermons SET start = %s, end = %s WHERE id = %d", $sermon_book->start, $sermon_book->end, $sermon_book->id));
			}
		}

		   $checkSermonUpload = sb_checkSermonUploadable();
		   switch ($checkSermonUpload) {
			case "unwriteable":
				echo '<div id="message" class="updated fade"><p><b>';
				if (is_multisite() AND !is_super_admin()) {
					esc_html_e('Upload is disabled. Please contact your administrator.', 'sermon-browser');
				} else {
					esc_html_e('Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777.', 'sermon-browser');
				}
				echo '</b></div>';
				break;
			case "notexist":
				echo '<div id="message" class="updated fade"><p><b>';
				if (is_multisite() AND !is_super_admin()) {
					esc_html_e('Upload is disabled. Please contact your administrator.', 'sermon-browser');
				} else {
					esc_html_e('Error: The upload folder you have specified does not exist.', 'sermon-browser');
				}
				echo '</b></div>';
				break;
			default:
				echo '<div id="message" class="updated fade"><p><b>';
				esc_html_e('Default loaded successfully.', 'sermon-browser');
				echo '</b></div>';
				break;
			}
	} // Save options
	elseif ( isset( $_POST['save'] ) ) {
		if ( ! isset( $_POST['sermon_options_save_reset_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sermon_options_save_reset_nonce'] ), 'sermon_options_save_reset' ) ) {
			wp_die( esc_html__( "You do not have the correct permissions to edit the SermonBrowser options", 'sermon-browser' ) );
	}
		$dir = rtrim(str_replace("\\", "/", sanitize_text_field(wp_unslash(isset($_POST['dir']) ? $_POST['dir'] : ''))), "/")."/";
		if ((is_multisite() && !is_super_admin()) || !sb_is_valid_upload_dir($dir)) {
			if ($dir != sb_get_option('upload_dir'))
				{echo '<div id="message" class="updated fade"><p><b>'.esc_html__('The upload folder must be a folder inside your WordPress folder, such as wp-content/uploads/sermons/. It has not been changed.', 'sermon-browser').'</b></div>';}
			$dir = sb_get_option('upload_dir');
		}
		sb_update_option('podcast_url', isset($_POST['podcast']) ? esc_url_raw(wp_unslash($_POST['podcast'])) : '');
		$perpage = isset( $_POST['perpage'] ) ? intval( $_POST['perpage'] ) : 0;
		if ( $perpage > 0 ) {
			sb_update_option('sermons_per_page', $perpage);
		}
		sb_update_option('upload_dir', $dir);
		sb_update_option('filter_type', isset($_POST['filtertype']) ? sanitize_key($_POST['filtertype']) : 'oneclick');
		sb_update_option('filter_hide', isset($_POST['filterhide']));
		sb_update_option('upload_url', trailingslashit(site_url()).$dir);
		sb_update_option ('import_prompt', isset($_POST['import_prompt']));
		sb_update_option ('import_title', isset($_POST['import_title']));
		sb_update_option ('import_artist', isset($_POST['import_artist']));
		sb_update_option ('import_album', isset($_POST['import_album']));
		sb_update_option ('import_comments', isset($_POST['import_comments']));
		sb_update_option ('import_filename', isset($_POST['import_filename']) ? sanitize_key($_POST['import_filename']) : 'none');
		sb_update_option ('hide_no_attachments', isset($_POST['hide_no_attachments']));
		sb_update_option('mp3_shortcode', isset($_POST['mp3_shortcode']) ? sanitize_text_field(wp_unslash($_POST['mp3_shortcode'])) : '');
		sb_update_option('esv_api_key', isset($_POST['esv_api_key']) ? sanitize_text_field(wp_unslash($_POST['esv_api_key'])) : '');
		if ( ! is_dir( SB_ABSPATH . $dir ) ) {
			if ( sb_mkdir( SB_ABSPATH . $dir ) ) {
				sb_protect_upload_dir();
			}
		}
		if ( ! is_dir( SB_ABSPATH . $dir . 'images' ) && sb_mkdir( SB_ABSPATH . $dir . 'images' ) ) {
			sb_protect_upload_dir();
		}
		   $checkSermonUpload = sb_checkSermonUploadable();
		   switch ($checkSermonUpload) {
		   case "unwriteable":
			echo '<div id="message" class="updated fade"><p><b>';
			esc_html_e('Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777.', 'sermon-browser');
			echo '</b></div>';
			break;
		case "notexist":
			echo '<div id="message" class="updated fade"><p><b>';
			esc_html_e('Error: The upload folder you have specified does not exist.', 'sermon-browser');
			echo '</b></div>';
			break;
		default:
			echo '<div id="message" class="updated fade"><p><b>';
			esc_html_e('Options saved successfully.', 'sermon-browser');
			echo '</b></div>';
			break;
	   }
	}

	//Display error messsages when problems in php.ini
	function sb_display_error ($message) {
		echo	'<tr><td align="right" style="color:#AA0000; font-weight:bold">'.esc_html__('Error', 'sermon-browser').':</td>'.
				'<td style="color: #AA0000">'.esc_html($message).'</td></tr>';
	}

	//Display warning messsages when problems in php.ini
	function sb_display_warning ($message) {
		echo	'<tr><td align="right" style="color:#FFDC00; font-weight:bold">'.esc_html__('Warning', 'sermon-browser').':</td>'.
				'<td style="color: #FF8C00">'.esc_html($message).'</td></tr>';
	}

	sb_do_alerts();
	// HTML for options page
?>
	<div class="wrap">
        <img src="<?php echo esc_url( SB_PLUGIN_URL . '/sb-includes/logo-small.png' ); ?>"
                                                     width="191" height="35"
                                                     style="margin: 1em 2em; float: right;"/>
		<form method="post">
		<h2><?php esc_html_e('Basic Options', 'sermon-browser') ?></h2>
		<br style="clear:both"/>
		<table border="0" class="widefat">
			<?php
				if (!is_multisite() OR is_super_admin()) {
			?>
			<tr>
                        <td align="right" style="vertical-align:middle"><?php esc_html_e('Upload folder', 'sermon-browser' ) ?>
                            :
                        </td>
                        <td><input type="text" name="dir"
                                   value="<?php echo esc_attr( sb_get_option( 'upload_dir' ) ) ?>"
                                   style="width:100%"/></td>
			</tr>
			<?php
				} else {
			?>
                    <input type="hidden" name="dir"
                           value="<?php echo esc_attr( sb_get_option( 'upload_dir' ) ) ?>">
			<?php
				}
			?>
			<tr>
                    <td align="right"
                        style="vertical-align:middle"><?php esc_html_e('Public podcast feed', 'sermon-browser' ) ?>:
                    </td>
                    <td><input type="text" name="podcast"
                               value="<?php echo esc_attr( sb_get_option( 'podcast_url' ) ) ?>"
                               style="width:100%"/></td>
			</tr>
			<tr>
				<td align="right"><?php esc_html_e('Private podcast feed', 'sermon-browser') ?>: </td>
                    <td><?php if ( sb_display_url() == '' ) {
							echo esc_html( site_url() );
						} else {
							echo esc_html( sb_display_url() );
						}
						echo esc_html( sb_query_char() ); ?>podcast
                    </td>
			</tr>
			<tr>
                    <td align="right" style="vertical-align:middle"><?php esc_html_e('MP3 shortcode', 'sermon-browser' ) ?>:
                        <br/><?php esc_html_e('Default: ', 'sermon-browser' ) ?>[audio mp3=&quot;%SERMONURL%&quot;]
                    </td>
                    <td><input type="text" name="mp3_shortcode"
                               value="<?php echo esc_attr( sb_get_option( 'mp3_shortcode' ) ) ?>"
                               style="width:100%"/></td>
			</tr>
		<tr>
		<?php
				$template = sb_get_option ('single_template');
				if (!sb_get_option ('esv_api_key') && strpos ($template, '[esvtext]') !== false) {
					$extra_text = __ ('<br/>Without an API key, no Bible text is shown.', 'sermon-browser');
				} else {
					$extra_text = '';
				}
			?>
			<td align="right" style="vertical-align:middle"><?php esc_html_e('ESV API Key (required to display the ESV text)', 'sermon-browser') ?>: <br/><?php echo wp_kses_post( __('You can sign up for an API Key <a href="https://api.esv.org/account/create-application/">here</a> (you’ll need to create an account).<br/>A key looks like this: 82e261b8b5b6ed0b6e7f09332d2acc48d88ee7fa', 'sermon-browser').$extra_text ) ?></td>
			<td><input type="text" name="esv_api_key" value="<?php echo esc_attr(sb_get_option('esv_api_key')) ?>" style="width:100%" /></td>
		</tr>
		<tr>
                    <td align="right" style="vertical-align:middle"><?php esc_html_e('Sermons per page', 'sermon-browser' ) ?>
                        :
                    </td>
                    <td><input type="text" name="perpage" value="<?php echo (int) sb_get_option( 'sermons_per_page' ) ?>"/>
                    </td>
			</tr>
			<tr>
                    <td align="right" style="vertical-align:top"
                        rowspan="2"><?php esc_html_e('Filter type', 'sermon-browser' ) ?>:
                    </td>
				<td>
				<?php
					$ft = sb_get_option('filter_type');
						$filter_options = array(
							'dropdown' => __( 'Drop-down', 'sermon-browser' ),
							'oneclick' => __( 'One-click', 'sermon-browser' ),
							'none'     => __( 'None', 'sermon-browser' )
						);
					foreach ($filter_options as $value => $filter_option) {
						echo '<input type="radio" name="filtertype" value="' . esc_attr( $value ) . '" ';
							if ( $ft == $value ) {
							echo 'checked="checked" ';
							}
						echo '/> ' . esc_html( $filter_option ) . "<br/>\n";
					}
				?>
				</td>
			</tr>
			<tr>
				<td>
                        <input type="checkbox" name="filterhide" <?php if ( sb_get_option( 'filter_hide' ) == 'hide' ) {
							echo 'checked="checked" ';
						} ?> value="hide" \> <?php esc_html_e('Minimise filter', 'sermon-browser' ); ?>
				</td>
			</tr>
			<tr>
				<td align="right"><?php esc_html_e('Hide sermons without attachments?', 'sermon-browser') ?></td>
                    <td><input type="checkbox"
                               name="hide_no_attachments" <?php if ( sb_get_option( 'hide_no_attachments' ) )
							echo 'checked="checked" ' ?> value="1" \></td>
			</tr>
			<?php
				$allow_uploads = ini_get('file_uploads');
				$max_filesize = sb_return_kbytes(ini_get('upload_max_filesize'));
				$max_post = sb_return_kbytes(ini_get('post_max_size'));
				$max_execution = ini_get('max_execution_time');
				$max_input = ini_get('max_input_time');
				$max_memory = sb_return_kbytes(ini_get('memory_limit'));
				$checkSermonUpload = sb_checkSermonUploadable();
				if (is_multisite()) {
					if ( $checkSermonUpload == "unwriteable" ) {
						sb_display_error (__('The upload folder is not writeable. You need to specify a folder that you have permissions to write to.', 'sermon-browser'));
					} elseif ( $checkSermonUpload == "notexist" ) {
						sb_display_error (__('The upload folder you have specified does not exist.', 'sermon-browser'));
					}
					if ( $allow_uploads == '0' ) {
						sb_display_error( __( 'Your administrator does not allow file uploads. You will need to upload via FTP.', 'sermon-browser' ) );
					}
					$max_filesize = ($max_filesize < $max_post) ? $max_filesize : $max_post;
					if ( $max_filesize < 15360 ) {
						sb_display_warning( __( 'The maximum file size you can upload is only ', 'sermon-browser' ) . $max_filesize . __('k. You may need to upload via FTP.', 'sermon-browser') );
					}
					$max_execution = (($max_execution < $max_input) || $max_input == -1) ? $max_execution : $max_input;
					if ( $max_execution < 600 ) {
						sb_display_warning( __( 'The maximum time allowed for any script to run is only ', 'sermon-browser' ) . $max_execution . __(' seconds. If your files take longer than this to upload, you will need to upload via FTP.', 'sermon-browser') );
					}
				} else {
					if ( $checkSermonUpload == "unwriteable" ) {
						sb_display_error (__('The upload folder is not writeable. You need to specify a folder that you have permissions to write to, or CHMOD this folder to 666 or 777.', 'sermon-browser'));
					} elseif ( $checkSermonUpload == "notexist" ) {
						sb_display_error (__('The upload folder you have specified does not exist.', 'sermon-browser'));
				}
					if ( $allow_uploads == '0' ) {
						sb_display_error( __( 'Your php.ini file does not allow uploads. Please change file_uploads in php.ini.', 'sermon-browser' ) );
					}
					if ( $max_filesize < 15360 ) {
						sb_display_warning( __( 'The maximum file size you can upload is only ', 'sermon-browser' ) . $max_filesize . __('k. Please change upload_max_filesize to at least 15M in php.ini.', 'sermon-browser') );
					}
					if ( $max_post < 15360 ) {
						sb_display_warning( __( 'The maximum file size you send through the browser is only ', 'sermon-browser' ) . $max_post . __('k. Please change post_max_size to at least 15M in php.ini.', 'sermon-browser') );
					}
					if ( $max_execution < 600 ) {
						sb_display_warning( __( 'The maximum time allowed for any script to run is only ', 'sermon-browser' ) . $max_execution . __(' seconds. Please change max_execution_time to at least 600 in php.ini.', 'sermon-browser') );
					}
					if ( $max_input < 600 && $max_input != - 1 ) {
						sb_display_warning( __( 'The maximum time allowed for an upload script to run is only ', 'sermon-browser' ) . $max_input . __(' seconds. Please change max_input_time to at least 600 in php.ini.', 'sermon-browser') );
					}
					if ( $max_memory < 16384 ) {
						sb_display_warning( __( 'The maximum amount of memory allowed is only ', 'sermon-browser' ) . $max_memory . __('k. Please change memory_limit to at least 16M in php.ini.', 'sermon-browser') );
					}
				}
			?>
		</table>
		<h2><?php esc_html_e('Import Options', 'sermon-browser') ?></h2>
		<p><?php
		/* translators: %s: link to the Files page */
		printf(esc_html__('SermonBrowser can speed up the process of importing existing MP3s by reading the information stored in each MP3 file and pre-filling the SermonBrowser fields. Use this section to specify what information you want imported into SermonBrowser. Once you have selected the options, go to %s to import your files.', 'sermon-browser'), '<a href="'.esc_url(admin_url('admin.php?page=sermon-browser/files.php')).'">'.esc_html__('Files', 'sermon-browser').'</a>') ?>
		<table border="0" class="widefat">
			<tr>
				<td align="right"><?php esc_html_e('Add files prompt to top of Add Sermon page?', 'sermon-browser') ?></td>
                    <td><input type="checkbox" name="import_prompt" <?php if ( sb_get_option( 'import_prompt' ) )
							echo 'checked="checked" ' ?> value="1" \></td>
			</tr>
			<tr>
				<td align="right"><?php esc_html_e('Use title tag for sermon title?', 'sermon-browser') ?></td>
                    <td><input type="checkbox" name="import_title" <?php if ( sb_get_option( 'import_title' ) )
							echo 'checked="checked" ' ?> value="1" \></td>
			</tr>
			<tr>
				<td align="right"><?php esc_html_e('Use artist tag for preacher?', 'sermon-browser') ?></td>
                    <td><input type="checkbox" name="import_artist" <?php if ( sb_get_option( 'import_artist' ) )
							echo 'checked="checked" ' ?> value="1" \></td>
			</tr>
			<tr>
				<td align="right"><?php esc_html_e('Use album tag for series?', 'sermon-browser') ?></td>
                    <td><input type="checkbox" name="import_album" <?php if ( sb_get_option( 'import_album' ) )
							echo 'checked="checked" ' ?> value="1" \></td>
			</tr>
			<tr>
				<td align="right"><?php esc_html_e('Use comments tag for sermon description?', 'sermon-browser') ?></td>
                    <td><input type="checkbox" name="import_comments" <?php if ( sb_get_option( 'import_comments' ) )
							echo 'checked="checked" ' ?> value="1" \></td>
			</tr>
			<tr>
                    <td align="right"
                        style="vertical-align: middle"><?php esc_html_e('Attempt to extract date from filename', 'sermon-browser' ) ?></td>
				<td style="vertical-align: middle"><select name="import_filename">
				<?php
							$filename_options = array(
								'none' => __( 'Disabled', 'sermon-browser' ),
											   'uk' => __('UK-formatted date (dd-mm-yyyy)', 'sermon-browser'),
											   'us' => __('US-formatted date (mm-dd-yyyy)', 'sermon-browser'),
											   'int' => __('International formatted date (yyyy-mm-dd)', 'sermon-browser')
										);
					$saved_option = sb_get_option ('import_filename');
					foreach ($filename_options as $option => $text) {
						$sel = $saved_option == $option ? ' selected = "selected"' : '';
						echo '<option value="' . esc_attr( $option ) . '"' . $sel . '>' . esc_html( $text ) . "</option>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sel is fixed markup
					}
					echo "</select>\n<br/>";
					esc_html_e ('(Use if you name your files something like 2008-11-06-eveningsermon.mp3)', 'sermon-browser');
					?>
				</td>
			</tr>
		</table>
			<?php wp_nonce_field( 'sermon_options_save_reset', 'sermon_options_save_reset_nonce' ); ?>
            <p class="submit"><input type="submit" name="save" value="<?php esc_attr_e('Save', 'sermon-browser') ?> &raquo;" /> <input type="submit" name="resetdefault" value="<?php esc_attr_e('Reset to defaults', 'sermon-browser') ?>"  /></p>
	</div>
	</form>
<?php
}

/**
* Display uninstall screen and perform uninstall if requested
*/
function sb_uninstall () {
	//Security check
	if ( ! ( current_user_can( 'edit_plugins' ) || ( is_multisite() && current_user_can( 'manage_options' ) ) ) ) {
			wp_die(esc_html__("You do not have the correct permissions to Uninstall SermonBrowser", 'sermon-browser'));
	}
	if ( isset( $_POST['uninstall'] ) ) {
		if ( ! isset( $_POST['sermon_browser_uninstall_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sermon_browser_uninstall_nonce'] ), 'sermon_browser_uninstall' ) ) {
			wp_die( esc_html__( "You do not have the correct permissions to Uninstall SermonBrowser", 'sermon-browser' ) );
		}
		require(SB_INCLUDES_DIR.'/uninstall.php');
	}

?>
	<form method="post">
	<div class="wrap">
		<?php if (is_multisite()) { ?>
			<h2> <?php esc_html_e('Reset SermonBrowser', 'sermon-browser'); ?></h2>
			<p><?php
					/* translators: %s: the name of the button */
					printf(esc_html__('Clicking the %s button below will remove ALL data (sermons, preachers, series, etc.) from SermonBrowser', 'sermon-browser'), esc_html__('Delete all', 'sermon-browser'));
					 echo '. ';
					 esc_html_e('You will NOT be able to undo this action.', 'sermon-browser') ?>
			</p>
		<?php } else {  ?>
			<h2> <?php esc_html_e('Uninstall', 'sermon-browser'); ?></h2>
			<p><?php
					/* translators: %s: the name of the button */
					printf(esc_html__('Clicking the %s button below will remove ALL data (sermons, preachers, series, etc.) from SermonBrowser', 'sermon-browser'), esc_html__('Uninstall', 'sermon-browser'));
					 echo ', ';
					 esc_html_e('and will deactivate the SermonBrowser plugin', 'sermon-browser');
					 echo '. ';
					 esc_html_e('You will NOT be able to undo this action.', 'sermon-browser');
					 echo ' ';
					 esc_html_e('If you only want to temporarily disable SermonBrowser, just deactivate it from the plugins page.', 'sermon-browser'); ?>
			</p>
		<?php } ?>
		<table border="0" class="widefat">
			<tr>
                    <td><input type="checkbox" name="wipe"
                               value="1"> <?php esc_html_e('Also remove all uploaded files', 'sermon-browser' ) ?></td>
			</tr>
		</table>
            <p class="submit"><input type="submit" name="uninstall" value="<?php if ( is_multisite() ) {
					esc_html_e('Delete all', 'sermon-browser' );
				} else {
					esc_html_e('Uninstall', 'sermon-browser' );
				} ?>"
                                     onclick="return confirm('<?php echo esc_js( __( 'Do you REALLY want to delete all data?', 'sermon-browser' ) ) ?>')"/>
            </p>
	</div>
		<?php wp_nonce_field( 'sermon_browser_uninstall', 'sermon_browser_uninstall_nonce' ); ?>
	</form>
	<script>
        jQuery( "form" ).submit( function ()
        {
			var yes = confirm(<?php echo wp_json_encode(__('Are you REALLY REALLY sure you want to remove SermonBrowser?', 'sermon-browser'))?>);
			if(!yes) return false;
		});
	</script>
<?php
}

/**
* Display the templates page and handle changes
*/
function sb_templates () {
	//Security check
	if (!current_user_can('manage_options'))
			{wp_die(esc_html__("You do not have the correct permissions to edit the SermonBrowser templates", 'sermon-browser'));}
	//Save templates or reset to default
	if (isset($_POST['save']) || isset($_POST['resetdefault'])) {
        if (! isset($_POST['sermon_template_edit_nonce']) || ! wp_verify_nonce( sanitize_key( $_POST['sermon_template_edit_nonce'] ), 'sermon_template_edit' )) {
            wp_die(esc_html__("You do not have the correct permissions to edit the SermonBrowser templates", 'sermon-browser'));
        }
		// Filtered unslashed, so that kses reads quoted attributes as written, then saved slashed, as sb_update_option() has always stored them; sb_get_option() strips the slashes
		$multi = isset($_POST['multi']) ? wp_slash(wp_kses_post(wp_unslash($_POST['multi']))) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_kses_post() sanitises; wp_slash() keeps the stored format
		$single = isset($_POST['single']) ? wp_slash(wp_kses_post(wp_unslash($_POST['single']))) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- as above
		$style = isset($_POST['style']) ? wp_slash(wp_kses_post(wp_unslash($_POST['style']))) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- as above
		if(isset($_POST['resetdefault'])){
			require_once(SB_INCLUDES_DIR.'/sb-install.php');
			$multi = sb_default_multi_template();
			$single = sb_default_single_template();
			$style = sb_default_css();
		}
		sb_update_option('search_template', $multi);
		sb_update_option('single_template', $single);
		sb_update_option('css_style', $style);
		sb_update_option('style_date_modified', strtotime('now'));
		echo '<div id="message" class="updated fade"><p><b>';
		esc_html_e('Templates saved successfully.', 'sermon-browser');
		echo '</b></p></div>';
	}
	sb_print_template_faults();
	sb_do_alerts();
	// HTML for templates page
	?>
	<form method="post">
	<div class="wrap">
		<img src="<?php echo esc_url( SB_PLUGIN_URL . '/sb-includes/logo-small.png' ); ?>" width="191" height ="35" style="margin: 1em 2em; float: right;" />
		<h2><?php esc_html_e('Templates', 'sermon-browser') ?></h2>
		<br/>
		<table border="0" class="widefat">
			<tr>
				<td align="right"><?php esc_html_e('Search results page', 'sermon-browser') ?>: </td>
				<td>
					<?php sb_build_textarea('multi', sb_get_option('search_template')) ?>
				</td>
			</tr>
			<tr>
				<td align="right"><?php esc_html_e('Sermon page', 'sermon-browser') ?>: </td>
				<td>
					<?php sb_build_textarea('single', sb_get_option('single_template')) ?>
				</td>
			</tr>
			<tr>
				<td align="right"><?php esc_html_e('Style', 'sermon-browser') ?>: </td>
				<td>
					<?php sb_build_textarea('style', sb_get_option('css_style')) ?>
				</td>
			</tr>
		</table>
		<p class="submit"><input type="submit" name="save" value="<?php esc_attr_e('Save', 'sermon-browser') ?> &raquo;" /> <input type="submit" name="resetdefault" value="<?php esc_attr_e('Reset to defaults', 'sermon-browser') ?>"  /></p>
	</div>
	<?php  wp_nonce_field( 'sermon_template_edit', 'sermon_template_edit_nonce' ); ?>
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
* Warns about template tags that can't work where they are, which the sermon pages leave out
*/
function sb_print_template_faults() {
	$messages = array();
	foreach (array(
		SermonBrowser\Template::SEARCH => array('search_template', __('Search results page', 'sermon-browser')),
		SermonBrowser\Template::SINGLE => array('single_template', __('Sermon page', 'sermon-browser')),
	) as $kind => $option) {
		$template = SermonBrowser\Template::parse(sb_get_option($option[0]), $kind);
		foreach ($template->faults() as $fault)
			$messages[] = $option[1].': '.$template->fault_message($fault);
	}
	if (!$messages)
		return;
	echo '<div class="notice notice-warning"><p><b>'.esc_html__('Some template tags can\'t work where they are, so your pages leave them out:', 'sermon-browser').'</b></p><ul style="list-style-type:disc; margin-left: 2em">';
	foreach ($messages as $message)
		echo '<li>'.esc_html($message).'</li>';
	echo '</ul><p><a href="'.esc_url(admin_url('admin.php?page=sermon-browser/help.php').'#template-errors').'">'.esc_html__('How to use template tags', 'sermon-browser').'</a></p></div>';
}

/**
* Display the preachers page and handle changes
*/
function sb_manage_preachers() {
	global $wpdb;
	//Security check
	if (!current_user_can('manage_categories'))
			{wp_die(esc_html__("You do not have the correct permissions to manage the preachers' database", 'sermon-browser'));}
	if (isset($_GET['saved']))
		{echo '<div id="message" class="updated fade"><p><b>'.esc_html__('Preacher saved to database.', 'sermon-browser').'</b></div>';}
	$sermonUploadDir = sb_get_option('upload_dir');
	//Save changes
	if (isset($_POST['save'])) {
        if (!isset($_POST['sermon_manage_preachers_nonce']) || ! wp_verify_nonce( sanitize_key( $_POST['sermon_manage_preachers_nonce'] ), 'sermon_manage_preachers' )) {
            wp_die(esc_html__("You do not have the correct permissions to manage the preachers database", 'sermon-browser'));
        }
		// Saved slashed, as the plugin has always stored text; output strips the slashes
		$name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$error = false;
		$pid = isset($_REQUEST['pid']) ? (int) $_REQUEST['pid'] : 0;

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- the upload is checked by sb_check_uploaded_file() and moved by sb_handle_upload()
		if (empty($_FILES['upload']['name'])) {
			$p = $wpdb->get_row($wpdb->prepare("SELECT image FROM {$wpdb->prefix}sb_preachers WHERE id = %d", $pid));
			$filename = $p ? $p->image : '';
		} elseif ($_FILES['upload']['error'] == UPLOAD_ERR_OK) {
			$filename = sb_check_uploaded_file($_FILES['upload']['tmp_name'], $_FILES['upload']['name'], true);
			if (!is_dir(SB_ABSPATH.$sermonUploadDir.'images'))
				{sb_mkdir(SB_ABSPATH.$sermonUploadDir.'images');}
			sb_protect_upload_dir();
			if ($filename)
				{$filename = wp_unique_filename(SB_ABSPATH.$sermonUploadDir.'images', $filename);}
			if (!$filename) {
				$error = true;
				$filename = '';
				wp_delete_file($_FILES['upload']['tmp_name']);
				echo '<div id="message" class="updated fade"><p><b>'.esc_html__('Preacher images must be JPEG, PNG, GIF or WebP files.', 'sermon-browser').'</b></div>';
			} elseif (!sb_handle_upload($_FILES['upload'], SB_ABSPATH.$sermonUploadDir.'images', $filename)) {
				$error = true;
				$filename = '';
				echo '<div id="message" class="updated fade"><p><b>'.esc_html__('Could not save uploaded file. Please try again.', 'sermon-browser').'</b></div>';
			}
		} else {
				$error = true;
				echo '<div id="message" class="updated fade"><p><b>'.esc_html__('Could not upload file. Please check the Options page for any errors or warnings.', 'sermon-browser').'</b></div>';
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput

		$old_image = $pid ? (string) $wpdb->get_var($wpdb->prepare("SELECT image FROM {$wpdb->prefix}sb_preachers WHERE id = %d", $pid)) : '';
		if ($error)
			{$filename = $old_image;}
		if ($pid == 0) {
			$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_preachers VALUES (null, %s, %s, %s)", $name, $description, $filename));
		} else {
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_preachers SET name = %s, description = %s, image = %s WHERE id = %d", $name, $description, $filename, $pid));
			if ($old_image != $filename && sb_is_plain_file_name($old_image))
				wp_delete_file(SB_ABSPATH.sb_get_option('upload_dir').'images/'.$old_image);
		}
		if(isset($_POST['remove'])){
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_preachers SET name = %s, description = %s, image = '' WHERE id = %d", $name, $description, $pid));
			if (sb_is_plain_file_name($filename))
				{wp_delete_file(SB_ABSPATH.sb_get_option('upload_dir').'images/'.$filename);}
		}
		if(!$error)
			echo '<script>document.location = '.wp_json_encode(admin_url('admin.php?page=sermon-browser/preachers.php&saved=true')).';</script>';
	}

	if (isset($_GET['act']) && $_GET['act'] == 'kill') {
		check_admin_referer('sb_delete_preacher_'.(isset($_GET['pid']) ? (int) $_GET['pid'] : 0));
		$die = (isset($_GET['pid']) ? (int) $_GET['pid'] : 0);
		if($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons WHERE preacher_id = %d", $die)) > 0)
			{echo '<div id="message" class="updated fade"><p><b>'.esc_html__("You cannot delete this preacher until you first delete any sermons they have preached.", 'sermon-browser').'</b></div>';}
		else {
			$p = $wpdb->get_row($wpdb->prepare("SELECT image FROM {$wpdb->prefix}sb_preachers WHERE id = %d", $die));
			if ($p && sb_is_plain_file_name($p->image))
				{wp_delete_file(SB_ABSPATH.sb_get_option('upload_dir').'images/'.$p->image);}
			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_preachers WHERE id = %d", $die));
		}
	}

	if (isset($_GET['act']) && ($_GET['act'] == 'new' || $_GET['act'] == 'edit')) {
		if ($_GET['act'] == 'edit') {$preacher = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sb_preachers WHERE id = %d", isset($_GET['pid']) ? (int) $_GET['pid'] : 0));}
	//Display HTML
?>
	<div class="wrap">
		<img src="<?php echo esc_url( SB_PLUGIN_URL . '/sb-includes/logo-small.png' ); ?>" width="191" height ="35" style="margin: 1em 2em; float: right;" />
		<h2><?php echo $_GET['act'] == 'new' ? esc_html__('Add', 'sermon-browser') : esc_html__('Edit', 'sermon-browser') ?> <?php esc_html_e('preacher', 'sermon-browser') ?></h2>
		<br style="clear:both">
		<?php
			$checkSermonUpload = sb_checkSermonUploadable('images/');
			if ($checkSermonUpload == 'notexist') {
				if (!is_dir(SB_ABSPATH.$sermonUploadDir.'images') && sb_mkdir(SB_ABSPATH.$sermonUploadDir.'images'))
					sb_protect_upload_dir();
				$checkSermonUpload = sb_checkSermonUploadable('images/');
			}
			if ($checkSermonUpload != 'writeable')
				{echo '<div id="message" class="updated fade"><p><b>'.esc_html__("The images folder is not writeable. You won't be able to upload images.", 'sermon-browser').'</b></div>';}
		?>
		<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="pid" value="<?php echo (isset($_GET['pid']) ? (int) $_GET['pid'] : 0) ?>">
		<fieldset>
			<table class="widefat">
				<tr>
					<td>
						<strong><?php esc_html_e('Name', 'sermon-browser') ?></strong>
						<div>
							<input type="text" value="<?php echo isset($preacher->name) ? esc_attr(stripslashes($preacher->name)) : '' ?>" name="name" size="60" style="width:400px;" />
						</div>
					</td>
				</tr>
				<tr>
					<td>
						<strong><?php esc_html_e('Description', 'sermon-browser') ?></strong>
						<div>
							<textarea name="description" cols="100" rows="5"><?php echo isset($preacher->description) ? esc_textarea(stripslashes($preacher->description)) : ''?></textarea>
						</div>
					</td>
				</tr>
				<tr>
					<td>
						<?php if ($_GET['act'] == 'edit'): ?>
						<?php if ($preacher->image != '') { ?><div><img alt="" src="<?php echo esc_url(trailingslashit(site_url()).sb_get_option('upload_dir').'images/'.rawurlencode($preacher->image)) ?>"></div><?php } ?>
						<?php endif ?>
						<strong><?php esc_html_e('Image', 'sermon-browser') ?></strong>
						<div>
							<input type="file" name="upload">
							<label>Remove image&nbsp;<input type="checkbox" name="remove" value="true"></label>
						</div>
					</td>
				</tr>
			</table>
		</fieldset>
            <?php wp_nonce_field( 'sermon_manage_preachers', 'sermon_manage_preachers_nonce' ); ?>
		<p class="submit"><input type="submit" name="save" value="<?php esc_attr_e('Save', 'sermon-browser') ?> &raquo;" /></p>
		</form>
	</div>
<?php
		return;
	}

	$preachers = $wpdb->get_results("SELECT {$wpdb->prefix}sb_preachers.*, COUNT({$wpdb->prefix}sb_sermons.id) AS sermon_count FROM {$wpdb->prefix}sb_preachers LEFT JOIN {$wpdb->prefix}sb_sermons ON {$wpdb->prefix}sb_preachers.id=preacher_id GROUP BY id ORDER BY name ASC");
	sb_do_alerts();
?>
	<div class="wrap">
		<img src="<?php echo esc_url( SB_PLUGIN_URL . '/sb-includes/logo-small.png' ); ?>" width="191" height ="35" style="margin: 1em 2em; float: right;" />
		<h2><?php esc_html_e('Preachers', 'sermon-browser') ?> (<a href="<?php echo esc_url(admin_url('admin.php?page=sermon-browser/preachers.php&act=new')) ?>"><?php esc_html_e('add new', 'sermon-browser') ?></a>)</h2>
		<br/>
		<table class="widefat" style="width:auto">
			<thead>
			<tr>
				<th scope="col" style="text-align:center"><?php esc_html_e('ID', 'sermon-browser') ?></th>
				<th scope="col"><?php esc_html_e('Name', 'sermon-browser') ?></th>
				<th scope="col" style="text-align:center"><?php esc_html_e('Image', 'sermon-browser') ?></th>
				<th scope="col" style="text-align:center"><?php esc_html_e('Sermons', 'sermon-browser') ?></th>
				<th scope="col" style="text-align:center"><?php esc_html_e('Actions', 'sermon-browser') ?></th>
			</tr>
			</thead>
			<tbody>
				<?php foreach ((array) $preachers as $preacher): ?>
					<tr class="<?php $i=0; echo (++$i % 2 == 0) ? 'alternate' : '' ?>">
						<td style="text-align:center"><?php echo (int) $preacher->id ?></td>
						<td><?php echo esc_html(stripslashes($preacher->name)) ?></td>
						<td style="text-align:center"><?php echo ($preacher->image == '') ? '' : '<img alt="" src="'.esc_url(trailingslashit(site_url()).sb_get_option('upload_dir').'images/'.rawurlencode($preacher->image)).'">' ?></td>
						<td style="text-align:center"><?php echo (int) $preacher->sermon_count ?></td>
						<td style="text-align:center">
							<a href="<?php echo esc_url(admin_url('admin.php?page=sermon-browser/preachers.php&act=edit&pid='.(int) $preacher->id)) ?>"><?php esc_html_e('Edit', 'sermon-browser') ?></a>
							<?php if (count($preachers) < 2) { ?>
							    | <a href="javascript:alert('<?php echo esc_js(__('You must have at least one preacher in the database.', 'sermon-browser')) ?>')"><?php esc_html_e('Delete', 'sermon-browser') ?></a>
							<?php } elseif ($preacher->sermon_count != 0) { ?>
							    | <a href="javascript:alert('<?php echo esc_js(__('You cannot delete this preacher until you first delete any sermons they have preached.', 'sermon-browser')) ?>')"><?php esc_html_e('Delete', 'sermon-browser') ?></a>
							<?php } else { ?>
								| <a onclick="return confirm('<?php /* translators: %s: preacher's name */ echo esc_attr(esc_js(sprintf(__('Are you sure you want to delete %s?', 'sermon-browser'), stripslashes($preacher->name)))) ?>')" href="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=sermon-browser/preachers.php&act=kill&pid={$preacher->id}"), 'sb_delete_preacher_'.$preacher->id))?>"><?php esc_html_e('Delete', 'sermon-browser') ?></a>
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
	global $wpdb;
	//Security check
	if (!current_user_can('manage_categories'))
			{wp_die(esc_html__("You do not have the correct permissions to manage the series and services database", 'sermon-browser'));}

	$series = $wpdb->get_results("SELECT {$wpdb->prefix}sb_series.*, COUNT({$wpdb->prefix}sb_sermons.id) AS sermon_count FROM {$wpdb->prefix}sb_series LEFT JOIN {$wpdb->prefix}sb_sermons ON series_id = {$wpdb->prefix}sb_series.id GROUP BY id ORDER BY name ASC");
	$services = $wpdb->get_results("SELECT {$wpdb->prefix}sb_services.*, COUNT({$wpdb->prefix}sb_sermons.id) AS sermon_count FROM {$wpdb->prefix}sb_services LEFT JOIN {$wpdb->prefix}sb_sermons ON service_id = {$wpdb->prefix}sb_services.id GROUP BY id ORDER BY name ASC");

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
				s = prompt(<?php echo wp_json_encode(__("New service's name @ default time?", 'sermon-browser')) ?>, <?php echo wp_json_encode(__("Service's name @ 18:00", 'sermon-browser')) ?>);
				if (s == null) { break;	}
			}
			if (s != null) {
				jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/sermon.php')); ?>, {sname: s, sermon: 1, _sbnonce: sbAjaxNonce}, function(r) {
					if (r) {
						sz = s.match(/(.*?)@(.*)/)[1];
						t = s.match(/(.*?)@(.*)/)[2];
						jQuery('#Services-list').append('\
							<tr style="display:none" class="Services" id="rowServices' + r + '">\
								<th style="text-align:center" scope="row">' + r + '</th>\
								<td id="Services' + r + '"></td>\
								<td style="text-align:center" id="time' + r + '"></td>\
								<td style="text-align:center">\
									<a id="linkServices' + r + '" href="#">Edit</a> | <a onclick="if (confirm(\'Are you sure?\')) { deleteServices(' + r + '); } return false;" href="#">Delete</a>\
								</td>\
							</tr>\
						');
						jQuery('#Services' + r).text(sz);
						jQuery('#time' + r).text(t);
						jQuery('#linkServices' + r).on('click', function() { renameServices(r, sz + ' @ ' + t); return false; });
						jQuery('#rowServices' + r).fadeIn(function() {
							updateClass('Services');
						});
					};
				});
			}
		}
		function createNewSeries(s) {
			var ss = prompt(<?php echo wp_json_encode(__("New series' name?", 'sermon-browser')) ?>, <?php echo wp_json_encode(__("Series' name", 'sermon-browser')) ?>);
			if (ss != null) {
				jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/sermon.php')); ?>, {ssname: ss, sermon: 1, _sbnonce: sbAjaxNonce}, function(r) {
					if (r) {
						jQuery('#Series-list').append('\
							<tr style="display:none" class="Series" id="rowSeries' + r + '">\
								<th style="text-align:center" scope="row">' + r + '</th>\
								<td id="Series' + r + '"></td>\
								<td style="text-align:center">\
									<a id="linkSeries' + r + '" href="#">Rename</a> | <a onclick="if (confirm(\'Are you sure?\')) { deleteSeries(' + r + '); } return false;" href="#">Delete</a>\
								</td>\
							</tr>\
						');
						jQuery('#Series' + r).text(ss);
						jQuery('#linkSeries' + r).on('click', function() { renameSeries(r, ss); return false; });
						jQuery('#rowSeries' + r).fadeIn(function() {
							updateClass('Series');
						});
					};
				});
			}
		}
		function deleteSeries(id) {
			jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/sermon.php')); ?>, {ssname: 'dummy', ssid: id, del: 1, sermon: 1, _sbnonce: sbAjaxNonce}, function(r) {
				if (r) {
					jQuery('#rowSeries' + id).fadeOut(function() {
						updateClass('Series');
					});
				};
			});
		}
		function deleteServices(id) {
			jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/sermon.php')); ?>, {sname: 'dummy', sid: id, del: 1, sermon: 1, _sbnonce: sbAjaxNonce}, function(r) {
				if (r) {
					jQuery('#rowServices' + id).fadeOut(function() {
						updateClass('Services');
					});
				};
			});
		}
		function renameSeries(id, old) {
			var ss = prompt(<?php echo wp_json_encode(__("New series' name?", 'sermon-browser')) ?>, old);
			if (ss != null) {
				jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/sermon.php')); ?>, {ssid: id, ssname: ss, sermon: 1, _sbnonce: sbAjaxNonce}, function(r) {
					if (r) {
						jQuery('#Series' + id).text(ss);
						jQuery('#linkSeries' + id).attr('onclick', '').off('click').on('click', function() { renameSeries(id, ss); return false; });
					};
				});
			}
		}
		function renameServices(id, old) {
			var s = 'lol';
			while ((s.indexOf('@') == -1) || (s.match(/(.*?)@(.*)/)[2].match(/[0-9]{1,2}:[0-9]{1,2}/) == null)) {
				s = prompt(<?php echo wp_json_encode(__("New service's name @ default time?", 'sermon-browser')) ?>, old);
				if (s == null) { break;	}
			}
			if (s != null) {
				jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/sermon.php')); ?>, {sid: id, sname: s, sermon: 1, _sbnonce: sbAjaxNonce}, function(r) {
					if (r) {
						sz = s.match(/(.*?)@(.*)/)[1];
						t = s.match(/(.*?)@(.*)/)[2];
						jQuery('#Services' + id).text(sz);
						jQuery('#time' + id).text(t);
						jQuery('#linkServices' + id).attr('onclick', '').off('click').on('click', function() { renameServices(id, s); return false; });
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
	<a name="manage-<?php echo esc_attr($k) ?>"></a>
	<div class="wrap">
		<?php if ($k == 'Series') { ?><img src="<?php echo esc_url( SB_PLUGIN_URL . '/sb-includes/logo-small.png' ); ?>" width="191" height ="35" style="margin: 1em 2em; float: right;" /><?php } ?>
		<h2><?php echo esc_html($k) ?> (<a href="javascript:createNew<?php echo esc_attr($k) ?>()"><?php esc_html_e('add new', 'sermon-browser') ?></a>)</h2>
		<br style="clear:both">
		<table class="widefat" style="width:auto">
			<thead>
			<tr>
				<th scope="col" style="text-align:center"><?php esc_html_e('ID', 'sermon-browser') ?></th>
				<th scope="col"><?php esc_html_e('Name', 'sermon-browser') ?></th>
				<?php echo $k == 'Services' ? '<th scope="col"><div style="text-align:center">'.esc_html__('Default time', 'sermon-browser').'</div></th>' : '' ?>
				<th scope="col" style="text-align:center"><?php esc_html_e('Sermons', 'sermon-browser') ?></th>
				<th scope="col" style="text-align:center"><?php esc_html_e('Actions', 'sermon-browser') ?></th>
			</tr>
			</thead>
			<tbody id="<?php echo esc_attr($k) ?>-list">
				<?php if (is_array($v['data'])): ?>
					<?php foreach ($v['data'] as $item): ?>
						<tr class="<?php echo esc_attr($k) ?> <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="row<?php echo esc_attr($k) ?><?php echo (int) $item->id ?>">
							<th style="text-align:center" scope="row"><?php echo (int) $item->id ?></th>
							<td id="<?php echo esc_attr($k) ?><?php echo (int) $item->id ?>"><?php echo esc_html(stripslashes($item->name)) ?></td>
							<?php echo $k == 'Services' ? '<td style="text-align:center" id="time'.(int) $item->id.'">'.esc_html($item->time).'</td>' : '' ?>
							<td style="text-align:center"><?php echo (int) $item->sermon_count; ?></td>
							<td style="text-align:center">
								<a id="link<?php echo esc_attr($k) ?><?php echo (int) $item->id ?>" href="#" onclick="<?php echo esc_attr('rename'.$k.'('.(int) $item->id.', '.wp_json_encode(stripslashes($item->name).($k == 'Services' ? ' @ '.$item->time : '')).'); return false;') ?>"><?php echo $k == 'Services' ? esc_html__('Edit', 'sermon-browser') : esc_html__('Rename', 'sermon-browser'); ?></a>
								<?php if (count($v['data']) < 2) { ?>
									| <a href="javascript:alert('<?php /* translators: %1$s: Series or Services */ echo esc_js(sprintf(__('You cannot delete this %1$s as you must have at least one %1$s in the database', 'sermon-browser'), $k)); ?>')"><?php esc_html_e('Delete', 'sermon-browser') ?></a>
								<?php } elseif ($item->sermon_count == 0) { ?>
									| <a href="#" onclick="<?php /* translators: %s: name of the series or service */ echo esc_attr('if (confirm('.wp_json_encode(sprintf(__('Are you sure you want to delete %s?', 'sermon-browser'), stripslashes($item->name))).')) { delete'.$k.'('.(int) $item->id.'); } return false;') ?>"><?php esc_html_e('Delete', 'sermon-browser'); ?></a>
								<?php } else { ?>
									| <a href="javascript:alert('<?php switch ($k) {
										case "Services":
											echo esc_js(__('Some sermons are currently assigned to that service. You can only delete services that are not used in the database.', 'sermon-browser'));
											break;
										case "Series":
											echo esc_js(__('Some sermons are currently in that series. You can only delete series that are empty.', 'sermon-browser'));
											break;
										case "Preachers":
											echo esc_js(__('That preacher has sermons in the database. You can only delete preachers who have no sermons in the database.', 'sermon-browser'));
											break;
									}?>')"><?php esc_html_e('Delete', 'sermon-browser') ?></a>
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
	global $wpdb, $filetypes;
	//Security check
	if (!current_user_can('upload_files'))
			{wp_die(esc_html__("You do not have the correct permissions to upload sermons", 'sermon-browser'));}
	// sync
	sb_scan_dir();

	if (isset($_POST['import_url']) || isset($_POST['save'])) {
		check_admin_referer('sb_upload', 'sb_upload_nonce');
	}
	if (isset($_POST['import_url'])) {
		$url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url']), array('http', 'https')) : '';
		$response = $url ? wp_safe_remote_head($url, array('redirection' => 5)) : false;
		if ($url && !is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
			if (isset($_POST['import_type']) && $_POST['import_type'] == 'download') {
				$filename = sanitize_file_name(rawurldecode(basename((string) wp_parse_url($url, PHP_URL_PATH))));
				if (!sb_is_allowed_file_name($filename))
					{echo '<div id="message" class="updated fade"><p><b>'.esc_html__('You are not permitted to upload files of that type.', 'sermon-browser').'</b></div>';}
				elseif (file_exists(SB_ABSPATH.sb_get_option('upload_dir').$filename))
					{/* translators: %s: file name */ echo '<div id="message" class="updated fade"><p><b>'.esc_html(sprintf(__('File %s already exists', 'sermon-browser'), $filename)).'</b></div>';}
				else {
					require_once (ABSPATH.'wp-admin/includes/file.php');
					$downloaded_file = download_url($url);
					if (is_wp_error($downloaded_file))
						{echo '<div id="message" class="updated fade"><p><b>'.esc_html__('Invalid URL.', 'sermon-browser').'</b></div>';}
					elseif (!sb_check_uploaded_file($downloaded_file, $filename)) {
						wp_delete_file($downloaded_file);
						echo '<div id="message" class="updated fade"><p><b>'.esc_html__('You are not permitted to upload files of that type.', 'sermon-browser').'</b></div>';
					} else {
						sb_protect_upload_dir();
						if (!sb_move_file($downloaded_file, SB_ABSPATH.sb_get_option('upload_dir').$filename))
							wp_delete_file($downloaded_file);
						$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'file', %s, 0, 0, 0)", $filename));
						echo '<script>document.location = '.wp_json_encode(admin_url('admin.php?page=sermon-browser/new_sermon.php&getid3='.(int) $wpdb->insert_id)).';</script>';
					}
				}
			} else {
				$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'url', %s, 0, 0, 0)", $url));
				echo '<script>document.location = '.wp_json_encode(admin_url('admin.php?page=sermon-browser/new_sermon.php&getid3='.(int) $wpdb->insert_id)).';</script>';
				die();
			}
		} else
			{echo '<div id="message" class="updated fade"><p><b>'.esc_html__('Invalid URL.', 'sermon-browser').'</b></div>';}
	}
	elseif (isset($_POST['save'])) {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- the upload is checked by sb_check_uploaded_file() and moved by sb_handle_upload()
		if (isset($_FILES['upload']['error']) && $_FILES['upload']['error'] == UPLOAD_ERR_OK) {
			$filename = sb_check_uploaded_file($_FILES['upload']['tmp_name'], $_FILES['upload']['name']);
			if ($filename) {
				$dest = SB_ABSPATH.sb_get_option('upload_dir').$filename;
				if($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sb_stuff WHERE name = %s", $filename)) == 0 && !file_exists($dest)) {
					sb_protect_upload_dir();
					if (sb_handle_upload($_FILES['upload'], untrailingslashit(SB_ABSPATH.sb_get_option('upload_dir')), $filename)) {
						$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'file', %s, 0, 0, 0)", $filename));
						if (sb_import_options_set ())
							{echo '<script>document.location = '.wp_json_encode(admin_url('admin.php?page=sermon-browser/new_sermon.php&getid3='.(int) $wpdb->insert_id)).';</script>';}
						else
							{echo '<div id="message" class="updated fade"><p><b>'.esc_html__('Files saved to database.', 'sermon-browser').'</b></div>';}
					}
				} else {
					echo '<div id="message" class="updated fade"><p><b>'.esc_html($filename).esc_html__(' already exists.', 'sermon-browser').'</b></div>';
				}
			} else {
				wp_delete_file($_FILES['upload']['tmp_name']);
				echo '<div id="message" class="updated fade"><p><b>'.esc_html__('You are not permitted to upload files of that type.', 'sermon-browser').'</b></div>';
			}
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput
	} elseif(isset($_POST['clean'])) {
	    if (!isset($_POST['sermon_browser_clean_nonce']) || !wp_verify_nonce(sanitize_key($_POST['sermon_browser_clean_nonce']), 'sermon_browser_clean')){
	        wp_die(esc_html__('Access denied.', 'sermon-browser'));
	    }

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
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_stuff WHERE id IN (".implode(', ', array_fill(0, count($wanted), '%d')).")", $wanted)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one %d placeholder per id
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
		function sbConfirmDelete(filename, filesermon) {
			if (confirm('Do you really want to delete ' + filename + '?')) {
				if (filesermon != '') {
					return confirm('This file is linked to the sermon called [' + filesermon + ']. Are you sure you want to delete it?');
				}
				return true;
			}
			return false;
		}
		function rename(id, old) {
			var f = prompt("<?php echo esc_js(__('New file name?', 'sermon-browser')) ?>", old);
			if (f != null) {
				jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/files.php')); ?>, {fid: id, fname: f, sermon: 1, _sbnonce: sbAjaxNonce}, function(r) {
					if (r) {
						if (r == 'renamed') {
							jQuery('#' + id).text(f.substring(0,f.lastIndexOf(".")));
							jQuery('#s' + id).text(f.substring(0,f.lastIndexOf(".")));
						} else {
							if (r == 'forbidden') {
								alert('<?php echo esc_js(__('You are not permitted files with that extension.', 'sermon-browser')) ?>');
							} else {
								alert('<?php echo esc_js(__('The script is unable to rename your file.', 'sermon-browser')) ?>');
							}
						}
					};
				});
			}
		}
		function kill(id) {
			jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/files.php')); ?>, {fid: id, del: 1, sermon: 1, _sbnonce: sbAjaxNonce}, function(r) {
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
						alert(<?php echo wp_json_encode(__('The script is unable to delete your file.', 'sermon-browser')) ?>);
					}
				};
			});
		}
		function fetchU(st) {
			jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/files.php')); ?>, {fetchU: st + 1, sermon: 1, _sbnonce: sbAjaxNonce}, function(r) {
				if (r) {
					jQuery('#the-list-u').html(r);
					if (st >= <?php echo (int) sb_get_option('sermons_per_page') ?>) {
						x = st - <?php echo (int) sb_get_option('sermons_per_page') ?>;
						jQuery('#uleft').html('<a href="javascript:fetchU(' + x + ')">&laquo; <?php echo esc_js(__('Previous', 'sermon-browser')) ?></a>');
					} else {
						jQuery('#uleft').html('');
					}
					if (st + <?php echo (int) sb_get_option('sermons_per_page') ?> <= <?php echo (int) $cntu ?>) {
						y = st + <?php echo (int) sb_get_option('sermons_per_page') ?>;
						jQuery('#uright').html('<a href="javascript:fetchU(' + y + ')"><?php echo esc_js(__('Next', 'sermon-browser')) ?> &raquo;</a>');
					} else {
						jQuery('#uright').html('');
					}
				};
			});
		}
		function fetchL(st) {
			jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/files.php')); ?>, {fetchL: st + 1, sermon: 1, _sbnonce: sbAjaxNonce}, function(r) {
				if (r) {
					jQuery('#the-list-l').html(r);
					if (st >= <?php echo (int) sb_get_option('sermons_per_page') ?>) {
						x = st - <?php echo (int) sb_get_option('sermons_per_page') ?>;
						jQuery('#left').html('<a href="javascript:fetchL(' + x + ')">&laquo; <?php echo esc_js(__('Previous', 'sermon-browser')) ?></a>');
					} else {
						jQuery('#left').html('');
					}
					if (st + <?php echo (int) sb_get_option('sermons_per_page') ?> <= <?php echo (int) $cntl ?>) {
						y = st + <?php echo (int) sb_get_option('sermons_per_page') ?>;
						jQuery('#right').html('<a href="javascript:fetchL(' + y + ')"><?php echo esc_js(__('Next', 'sermon-browser')) ?> &raquo;</a>');
					} else {
						jQuery('#right').html('');
					}
				};
			});
		}
		function findNow() {
			jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/files.php')); ?>, {search: jQuery('#search').val(), sermon: 1, _sbnonce: sbAjaxNonce}, function(r) {
				if (r) {
					jQuery('#the-list-s').html(r);
				};
			});
		}
	</script>
	<a name="top"></a>
	<div class="wrap">
		<img src="<?php echo esc_url( SB_PLUGIN_URL . '/sb-includes/logo-small.png' ); ?>" width="191" height ="35" style="margin: 1em 2em; float: right;" />
		<h2><?php esc_html_e('Upload Files', 'sermon-browser') ?></h2>
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
		<h2><?php esc_html_e('Unlinked files', 'sermon-browser') ?></h2>
		<br style="clear:both">
		<table class="widefat">
			<thead>
				<tr>
				<th width="10%" scope="col"><div style="text-align:center"><?php esc_html_e('ID', 'sermon-browser') ?></div></th>
				<th width="50%" scope="col"><div style="text-align:center"><?php esc_html_e('File name', 'sermon-browser') ?></div></th>
				<th width="20%" scope="col"><div style="text-align:center"><?php esc_html_e('File type', 'sermon-browser') ?></div></th>
				<th width="20%" scope="col"><div style="text-align:center"><?php esc_html_e('Actions', 'sermon-browser') ?></div></th>
				</tr>
			</thead>
			<tbody id="the-list-u">
				<?php if (is_array($unlinked)): ?>
					<?php $i = 0; foreach ($unlinked as $file): ?>
						<tr class="file <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="file<?php echo (int) $file->id ?>">
							<th style="text-align:center" scope="row"><?php echo (int) $file->id ?></th>
							<td id="<?php echo (int) $file->id ?>"><?php echo esc_html(substr($file->name, 0, strrpos($file->name, '.'))) ?></td>
							<td style="text-align:center"><?php echo esc_html(sb_file_type_name($file->name, $filetypes)) ?></td>
							<td style="text-align:center">
								<a href="<?php echo esc_url(admin_url("admin.php?page=sermon-browser/new_sermon.php&getid3={$file->id}")); ?>"><?php esc_html_e('Create sermon', 'sermon-browser') ?></a> |
								<?php sb_print_file_actions($file) ?>
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
		<h2><?php esc_html_e('Linked files', 'sermon-browser') ?></h2>
		<br style="clear:both">
		<table class="widefat">
			<thead>
			<tr>
				<th scope="col"><div style="text-align:center"><?php esc_html_e('ID', 'sermon-browser') ?></div></th>
				<th scope="col"><div style="text-align:center"><?php esc_html_e('File name', 'sermon-browser') ?></div></th>
				<th scope="col"><div style="text-align:center"><?php esc_html_e('File type', 'sermon-browser') ?></div></th>
				<th scope="col"><div style="text-align:center"><?php esc_html_e('Sermon', 'sermon-browser') ?></div></th>
				<th scope="col"><div style="text-align:center"><?php esc_html_e('Actions', 'sermon-browser') ?></div></th>
			</tr>
			</thead>
			<tbody id="the-list-l">
				<?php if (is_array($linked)): ?>
					<?php $i = 0; foreach ($linked as $file): ?>
						<tr class="file <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="file<?php echo (int) $file->id ?>">
							<th style="text-align:center" scope="row"><?php echo (int) $file->id ?></th>
							<td id="<?php echo (int) $file->id ?>"><?php echo esc_html(substr($file->name, 0, strrpos($file->name, '.'))) ?></td>
							<td style="text-align:center"><?php echo esc_html(sb_file_type_name($file->name, $filetypes)) ?></td>
							<td><?php echo esc_html(stripslashes($file->title)) ?></td>
							<td style="text-align:center">
								<?php sb_print_file_actions($file) ?>
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
		<h2><?php esc_html_e('Search for files', 'sermon-browser') ?></h2>
		<form id="searchform" name="searchform">
			<p>
				<input type="text" size="30" value="" id="search" />
				<input type="submit" class="button" value="<?php esc_attr_e('Search', 'sermon-browser') ?> &raquo;" onclick="javascript:findNow();return false;" />
			</p>
		</form>
		<table class="widefat">
			<thead>
			<tr>
				<th scope="col"><div style="text-align:center"><?php esc_html_e('ID', 'sermon-browser') ?></div></th>
				<th scope="col"><div style="text-align:center"><?php esc_html_e('File name', 'sermon-browser') ?></div></th>
				<th scope="col"><div style="text-align:center"><?php esc_html_e('File type', 'sermon-browser') ?></div></th>
				<th scope="col"><div style="text-align:center"><?php esc_html_e('Sermon', 'sermon-browser') ?></div></th>
				<th scope="col"><div style="text-align:center"><?php esc_html_e('Actions', 'sermon-browser') ?></div></th>
			</tr>
			</thead>
			<tbody id="the-list-s">
				<tr>
					<td><?php esc_html_e('Search results will appear here.', 'sermon-browser') ?></td>
				</tr>
			</tbody>
		</table>
		<br style="clear:both">
	</div>
	<script>
		<?php if ($cntu > sb_get_option('sermons_per_page')): ?>
			jQuery('#uright').html('<a href="javascript:fetchU(<?php echo (int) sb_get_option('sermons_per_page') ?>)">Next &raquo;</a>');
		<?php endif ?>
		<?php if ($cntl > sb_get_option('sermons_per_page')): ?>
			jQuery('#right').html('<a href="javascript:fetchL(<?php echo (int) sb_get_option('sermons_per_page') ?>)">Next &raquo;</a>');
		<?php endif ?>
	</script>
	<?php
		if (isset($checkSermonUpload) && $checkSermonUpload == 'writeable') {
		?>
		<div class="wrap">
			<h2><?php esc_html_e('Clean up', 'sermon-browser') ?></h2>
			<form method="post" >
				<p><?php esc_html_e('Pressing the button below scans every sermon in the database, and removes missing attachments. Use with caution!', 'sermon-browser') ?></p>
				<input type="submit" name="clean" value="<?php esc_attr_e('Clean up missing files', 'sermon-browser') ?>" />
				<?php wp_nonce_field( 'sermon_browser_clean', 'sermon_browser_clean_nonce' ); ?>
			</form>
		</div>
		<?php
	}
}

/**
* Displays Sermons page
*/
function sb_manage_sermons() {
	global $wpdb;
	//Security check
	if (!(current_user_can('publish_posts') || current_user_can('publish_pages')))
		{wp_die(esc_html__("You do not have the correct permissions to edit sermons", 'sermon-browser'));}
	sb_do_alerts();
	if (isset($_GET['saved'])) {
		echo '<div id="message" class="updated fade"><p><b>'.esc_html__('Sermon saved to database.', 'sermon-browser').'</b></div>';
	}

	if (isset($_GET['mid'])) {
        if (!isset($_GET['sermon_manage_sermons_nonce']) || ! wp_verify_nonce( sanitize_key( $_GET['sermon_manage_sermons_nonce'] ), 'sermon_manage_sermons' )) {
            wp_die(esc_html__("You do not have the correct permissions to edit sermons", 'sermon-browser'));
        }
		//Security check
		if (!current_user_can('publish_posts'))
			{wp_die(esc_html__("You do not have the correct permissions to delete sermons", 'sermon-browser'));}
		$mid = (int) $_GET['mid'];
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_sermons WHERE id = %d;", $mid));
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_sermons_tags WHERE sermon_id = %d;", $mid));
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_books_sermons WHERE sermon_id = %d;", $mid));
		$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_stuff SET sermon_id = 0 WHERE sermon_id = %d AND type = 'file';", $mid));
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = %d AND type <> 'file';", $mid));
		sb_delete_unused_tags();
		echo '<div id="message" class="updated fade"><p><b>'.esc_html__('Sermon removed from database.', 'sermon-browser').'</b></div>';
	}

	$cnt = $wpdb->get_row("SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons", ARRAY_A);
	$cnt = $cnt['COUNT(*)'];

	$sermons = $wpdb->get_results($wpdb->prepare("SELECT m.id, m.title, m.datetime, p.name as pname, s.name as sname, ss.name as ssname
		FROM {$wpdb->prefix}sb_sermons as m
		LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id
		LEFT JOIN {$wpdb->prefix}sb_services as s ON m.service_id = s.id
		LEFT JOIN {$wpdb->prefix}sb_series as ss ON m.series_id = ss.id
		ORDER BY m.datetime desc, s.time desc LIMIT 0, %d", (int) sb_get_option('sermons_per_page')));
	$preachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY name;");
	$series = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY name;");
?>
	<script>
		function sbFetchSermons(st) {
			jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/sermon.php')); ?>, {fetch: st + 1, sermon: 1, _sbnonce: sbAjaxNonce, title: jQuery('#search').val(), preacher: jQuery('#preacher').val(), series: jQuery('#series').val() }, function(r) {
				if (r) {
					jQuery('#the-list').html(r);
					if (st >= <?php echo (int) sb_get_option('sermons_per_page') ?>) {
						x = st - <?php echo (int) sb_get_option('sermons_per_page') ?>;
						jQuery('#left').html('<a href="javascript:sbFetchSermons(' + x + ')">&laquo; Previous</a>');
					} else {
						jQuery('#left').html('');
					}
					if (st + <?php echo (int) sb_get_option('sermons_per_page') ?> <= <?php echo (int) $cnt ?>) {
						y = st + <?php echo (int) sb_get_option('sermons_per_page') ?>;
						jQuery('#right').html('<a href="javascript:sbFetchSermons(' + y + ')">Next &raquo;</a>');
					} else {
						jQuery('#right').html('');
					}
				};
			});
		}
	</script>
	<div class="wrap">
			<img src="<?php echo esc_url( SB_PLUGIN_URL . '/sb-includes/logo-small.png' ); ?>" width="191" height ="35" style="margin: 1em 2em; float: right;" />
			<h2>Filter</h2>
			<form id="searchform" name="searchform">
			<fieldset style="float:left; margin-right: 1em">
				<legend><?php esc_html_e('Title', 'sermon-browser') ?></legend>
				<input type="text" size="17" value="" id="search" />
			</fieldset>
			<fieldset style="float:left; margin-right: 1em">
				<legend><?php esc_html_e('Preacher', 'sermon-browser') ?></legend>
				<select id="preacher">
					<option value="0"></option>
					<?php foreach ($preachers as $preacher): ?>
						<option value="<?php echo (int) $preacher->id ?>"><?php echo esc_html(stripslashes($preacher->name)) ?></option>
					<?php endforeach ?>
				</select>
			</fieldset>
			<fieldset style="float:left; margin-right: 1em">
				<legend><?php esc_html_e('Series', 'sermon-browser') ?></legend>
				<select id="series">
					<option value="0"></option>
					<?php foreach ($series as $item): ?>
						<option value="<?php echo (int) $item->id ?>"><?php echo esc_html(stripslashes($item->name)) ?></option>
					<?php endforeach ?>
				</select>
			</fieldset style="float:left; margin-right: 1em">
			<input type="submit" class="button" value="<?php esc_attr_e('Filter', 'sermon-browser') ?> &raquo;" style="float:left;margin:14px 0pt 1em; position:relative;top:0.35em;" onclick="javascript:sbFetchSermons(0);return false;" />
			</form>
		<br style="clear:both">
		<h2><?php esc_html_e('Sermons', 'sermon-browser') ?></h2>
		<br style="clear:both">
		<table class="widefat">
			<thead>
			<tr>
				<th scope="col" style="text-align:center"><?php esc_html_e('ID', 'sermon-browser') ?></th>
				<th scope="col"><?php esc_html_e('Title', 'sermon-browser') ?></th>
				<th scope="col"><?php esc_html_e('Preacher', 'sermon-browser') ?></th>
				<th scope="col"><?php esc_html_e('Date', 'sermon-browser') ?></th>
				<th scope="col"><?php esc_html_e('Service', 'sermon-browser') ?></th>
				<th scope="col"><?php esc_html_e('Series', 'sermon-browser') ?></th>
				<th scope="col" style="text-align:center"><?php esc_html_e('Stats', 'sermon-browser') ?></th>
				<th scope="col" style="text-align:center"><?php esc_html_e('Actions', 'sermon-browser') ?></th>
			</tr>
			</thead>
			<tbody id="the-list">
				<?php if (is_array($sermons)): $i = 0; ?>
					<?php foreach ($sermons as $sermon): ?>
					<tr class="<?php echo ++$i % 2 == 0 ? 'alternate' : '' ?>">
						<th style="text-align:center" scope="row"><?php echo (int) $sermon->id ?></th>
						<td><?php echo esc_html(stripslashes($sermon->title)) ?></td>
						<td><?php echo esc_html(stripslashes($sermon->pname)) ?></td>
						<td><?php echo ($sermon->datetime == '1970-01-01 00:00:00') ? esc_html__('Unknown', 'sermon-browser') : esc_html(date_i18n('d M y', strtotime($sermon->datetime))); ?></td>
						<td><?php echo esc_html(stripslashes($sermon->sname)) ?></td>
						<td><?php echo esc_html(stripslashes($sermon->ssname)) ?></td>
						<td><?php echo (int) sb_sermon_stats($sermon->id) ?></td>
						<td style="text-align:center">
							<?php //Security check
									if (current_user_can('publish_posts')) { ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=sermon-browser/new_sermon.php&mid='.(int) $sermon->id), 'sermon_new_sermons', 'sermon_new_sermons_nonce' ) );  ?>"><?php esc_html_e('Edit', 'sermon-browser') ?></a> | <a onclick="return confirm('Are you sure?')" href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=sermon-browser/sermon.php&mid='.(int) $sermon->id), 'sermon_manage_sermons', 'sermon_manage_sermons_nonce' ) );  ?>"><?php esc_html_e('Delete', 'sermon-browser'); ?></a> |
							<?php } ?>
							<a href="<?php echo esc_url(sb_display_url().sb_query_char(false).'sermon_id='.(int) $sermon->id);?>">View</a>
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
			jQuery('#right').html('<a href="javascript:sbFetchSermons(<?php echo (int) sb_get_option('sermons_per_page') ?>)">Next &raquo;</a>');
		<?php endif ?>
	</script>
<?php
}

/**
* Displays new/edit sermon page
*/
function sb_new_sermon() {
	global $wpdb, $allowedposttags;
	$getid3=false;
	//Security check
	if (!(current_user_can('publish_posts') || current_user_can('publish_pages')))
		{wp_die(esc_html__("You do not have the correct permissions to edit or create sermons", 'sermon-browser'));}
	include_once (SB_ABSPATH.'/wp-includes/kses.php');
	sb_scan_dir();
	$translated_books = array_combine(sb_get_default('eng_bible_books'), sb_get_default('bible_books'));

	if (isset($_POST['save']) && isset($_POST['title'])) {
	// prepare
        if (!isset($_REQUEST['sermon_browser_save_nonce']) || ! wp_verify_nonce( sanitize_key( $_REQUEST['sermon_browser_save_nonce'] ), 'sermon_browser_save' )) {
            wp_die(esc_html__("You do not have the correct permissions to edit or create sermons", 'sermon-browser'));
        }
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- text is saved slashed, as the plugin has always stored it (output strips the slashes); each value is sanitised or cast where it is used, and uploads are checked by sb_check_uploaded_file()
		$title = sanitize_text_field($_POST['title']);
		$preacher_id = isset($_POST['preacher']) ? (int) $_POST['preacher'] : 0;
		$service_id = isset($_POST['service']) ? (int) $_POST['service'] : 0;
		$series_id = isset($_POST['series']) ? (int) $_POST['series'] : 0;
		$time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
		$startz = $endz = array();
		$passage_count = isset($_POST['start']['book']) && is_array($_POST['start']['book']) ? count($_POST['start']['book']) : 0;
		for ($foo = 0; $foo < $passage_count; $foo++) {
			if (isset($_POST['start']['book'][$foo], $_POST['end']['book'][$foo]) && !empty($_POST['start']['chapter'][$foo]) && !empty($_POST['end']['chapter'][$foo]) && !empty($_POST['start']['verse'][$foo]) && !empty($_POST['end']['verse'][$foo])) {
				$startz[] = array(
					'book' => sanitize_text_field($_POST['start']['book'][$foo]),
					'chapter' => (int)$_POST['start']['chapter'][$foo],
					'verse' => (int)$_POST['start']['verse'][$foo],
				);
				$endz[] = array(
					'book' => sanitize_text_field($_POST['end']['book'][$foo]),
					'chapter' => (int)$_POST['end']['chapter'][$foo],
					'verse' => (int)$_POST['end']['verse'][$foo],
				);
			}
		}
		$start = serialize($startz);
		$end = serialize($endz);
		$date = isset($_POST['date']) ? strtotime(sanitize_text_field($_POST['date'])) : false;
		$override = (isset($_POST['override']) && $_POST['override'] == 'on') ? 1 : 0;
		if ($date) {
			if (!$override) {
				$service_time = $wpdb->get_var($wpdb->prepare("SELECT time FROM {$wpdb->prefix}sb_services WHERE id=%d", $service_id));
				if ($service_time)
					{$date = $date - strtotime('00:00') + strtotime($service_time);}
			} else
				{$date = $date - strtotime('00:00') + strtotime($time);}
			$date = gmdate('Y-m-d H:i:s', $date);
		} else
			{$date = '1970-01-01 00:00';}
		$description = isset($_POST['description']) ? (string) $_POST['description'] : '';
		if (!current_user_can('unfiltered_html'))
			$description = wp_kses($description, $allowedposttags);
		// edit or not edit
		if ( empty($_GET['mid']) ) { // new
			//Security check
			if (!current_user_can('publish_pages'))
				{wp_die(esc_html__("You do not have the correct permissions to create sermons", 'sermon-browser'));}
			$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_sermons VALUES (null, %s, %d, %s, %d, %d, %s, %s, %s, %s, %d, 0)", $title, $preacher_id, $date, $service_id, $series_id, $start, $end, $description, $time, $override));
			$id = $wpdb->insert_id;
		} else { // edit
			//Security check
			if (!current_user_can('publish_posts'))
				{wp_die(esc_html__("You do not have the correct permissions to edit sermons", 'sermon-browser'));}
			$id = (int) $_GET['mid'];
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_sermons SET title = %s, preacher_id = %d, datetime = %s, series_id = %d, start = %s, end = %s, description = %s, time = %s, service_id = %d, override = %d WHERE id = %d", $title, $preacher_id, $date, $series_id, $start, $end, $description, $time, $service_id, $override, $id));
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_stuff SET sermon_id = 0 WHERE sermon_id = %d AND type = 'file'", $id));
			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = %d AND type <> 'file'", $id));
		}
		// deal with books
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_books_sermons WHERE sermon_id = %d;", $id));
		if (isset($startz)) {foreach ($startz as $i => $st) {
			$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_books_sermons VALUES(null, %s, %s, %s, %d, 'start', %d);", $st['book'], $st['chapter'], $st['verse'], $i, $id));
		}}
		if (isset($endz)) {foreach ($endz as $i => $ed) {
			$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_books_sermons VALUES(null, %s, %s, %s, %d, 'end', %d);", $ed['book'], $ed['chapter'], $ed['verse'], $i, $id));
		}}
		// now previously uploaded files
		foreach (isset($_POST['file']) ? (array) $_POST['file'] : array() as $uid => $file) {
			if ((int) $file != 0)
				$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_stuff SET sermon_id = %d WHERE id = %d AND type = 'file';", $id, $file));
			elseif (isset($_FILES['upload']['error'][$uid]) && $_FILES['upload']['error'][$uid] === UPLOAD_ERR_OK) {
				$filename = sb_check_uploaded_file($_FILES['upload']['tmp_name'][$uid], $_FILES['upload']['name'][$uid]);
				if ($filename) {
					$dest = SB_ABSPATH.sb_get_option('upload_dir').$filename;
					sb_protect_upload_dir();
					if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sb_stuff WHERE name = %s", $filename)) == 0 && !file_exists($dest) && sb_handle_upload(array('name' => $_FILES['upload']['name'][$uid], 'type' => $_FILES['upload']['type'][$uid], 'tmp_name' => $_FILES['upload']['tmp_name'][$uid], 'error' => $_FILES['upload']['error'][$uid], 'size' => $_FILES['upload']['size'][$uid]), untrailingslashit(SB_ABSPATH.sb_get_option('upload_dir')), $filename)) {
						$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'file', %s, %d, 0, 0)", $filename, $id));
					} else {
						echo '<div id="message" class="updated fade"><p><b>'.esc_html($filename).esc_html__(' already exists.', 'sermon-browser').'</b></div>';
						$error = true;
					}
				} else {
					wp_delete_file($_FILES['upload']['tmp_name'][$uid]);
					echo '<div id="message" class="updated fade"><p><b>'.esc_html__('You are not permitted to upload files of that type.', 'sermon-browser').'</b></div>';
					$error = true;
				}
			}
		}
		// then URLs
		foreach (isset($_POST['url']) ? (array) $_POST['url'] : array() as $urlz) {
			if (!empty($urlz)) {
				$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_stuff VALUES(null, 'url', %s, %d, 0, 0);", esc_url($urlz), $id));
			}
		}
		// embed code next
		foreach (isset($_POST['code']) ? (array) $_POST['code'] : array() as $code) {
			if (!empty($code)) {
				$code = base64_encode(sb_kses_embed(stripslashes($code)));
				$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_stuff VALUES(null, 'code', %s, %d, 0, 0)", $code, $id));
			}
		}
		// tags
		$tags = isset($_POST['tags']) ? explode(',', $_POST['tags']) : array();
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_sermons_tags WHERE sermon_id = %d;", $id));
		foreach ($tags as $tag) {
			$clean_tag = sanitize_text_field($tag);
			$existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sb_tags WHERE name=%s",$clean_tag));
			if (is_null($existing_id)) {
				$wpdb->query($wpdb->prepare("INSERT  INTO {$wpdb->prefix}sb_tags VALUES (null, %s)",$clean_tag));
				$existing_id = $wpdb->insert_id;
			}
			$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_sermons_tags VALUES (null, %d, %d)", $id, $existing_id));
		}
		sb_delete_unused_tags();
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput
		// everything is fine, get out of here!
		if(!isset($error)) {
			echo '<script>document.location = '.wp_json_encode(admin_url('admin.php?page=sermon-browser/sermon.php&saved=true')).';</script>';
			die();
		}
	}

	$id3_tags = array();
	if (isset($_GET['getid3'])) {
		$file_data = $wpdb->get_row($wpdb->prepare("SELECT name, type FROM {$wpdb->prefix}sb_stuff WHERE id = %d", (int) $_GET['getid3']));
		if ($file_data !== null) {
			if (!class_exists('getID3')) {
			    require(ABSPATH.WPINC.'/ID3/getid3.php' );
			}
			$getID3 = new getID3;
			$id3_raw_tags = array();
			if ($file_data->type == 'url') {
				$filename = substr($file_data->name, strrpos ($file_data->name, '/')+1);
				// The ID3v2 tags are at the start of the file, so only the first 64KB is fetched
				$response = wp_safe_remote_get(wp_specialchars_decode($file_data->name, ENT_QUOTES), array('timeout' => 15, 'limit_response_size' => 65536));
				if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
					require_once (ABSPATH.'wp-admin/includes/file.php');
					$tempfilename = wp_tempnam('sermon-browser-id3');
					if ($tempfilename && file_put_contents($tempfilename, wp_remote_retrieve_body($response)) !== false)
						{$id3_raw_tags = $getID3->analyze($tempfilename);}
					wp_delete_file($tempfilename);
				}
			} else {
				$filename = $file_data->name;
				if (sb_is_plain_file_name($filename) && file_exists(SB_ABSPATH.sb_get_option('upload_dir').$filename))
					{$id3_raw_tags = $getID3->analyze(SB_ABSPATH.sb_get_option('upload_dir').$filename);}
			}
			if (!isset($id3_raw_tags['tags'])) {
				echo '<div id="message" class="updated fade"><p><b>'.esc_html__('No ID3 tags found.', 'sermon-browser');
				if ($file_data->type == 'url')
					 {echo ' Remote files must have id3v2 tags.';}
				echo '</b></div>';
			}
			getid3_lib::CopyTagsToComments($id3_raw_tags);
			if (sb_get_option ('import_title'))
				{$id3_tags['title'] = @$id3_raw_tags['comments_html']['title'][0];}
			if (sb_get_option ('import_comments'))
				{$id3_tags['description'] = @$id3_raw_tags['comments_html']['comments'][0];}
			if (sb_get_option ('import_album')) {
				$id3_tags['series'] = @$id3_raw_tags['comments_html']['album'][0];
				if ($id3_tags['series'] != '') {
					$series_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sb_series WHERE name = %s", $id3_tags['series']));
					if ($series_id === null) {
						$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_series VALUES (null, %s, '0')", $id3_tags['series']));
						$series_id = $wpdb->insert_id;
					}
					$id3_tags['series'] = $series_id;
				}
			}
			if (sb_get_option ('import_artist')) {
				$id3_tags['preacher'] = @$id3_raw_tags['comments_html']['artist'][0];
				if ($id3_tags['preacher'] != '') {
					$preacher_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sb_preachers WHERE name LIKE %s", $id3_tags['preacher']));
					if ($preacher_id === null) {
						$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_preachers VALUES (null, %s, '', '')", $id3_tags['preacher']));
						$preacher_id = $wpdb->insert_id;
					}
					$id3_tags['preacher'] = $preacher_id;
				}
			}
			$date_format = sb_get_option('import_filename');
			if ($date_format != '') {
				$filename = substr($filename, 0, strrpos($filename, '.'));
				$filename = str_replace ('--', '-', str_replace ('/', '-', $filename));
				$filename = trim(preg_replace('/[^0-9-]/', '', $filename), '-');
				$date = explode('-', $filename);
				$id3_tags['date'] = '';
				if (count($date) >= 3) {
					if ($date_format == 'uk')
						{$id3_tags['date'] = gmdate('Y-m-d', gmmktime(0, 0, 0, $date[1], $date[0], $date[2]));}
					elseif ($date_format == 'us')
						{$id3_tags['date'] = gmdate('Y-m-d', gmmktime(0, 0, 0, $date[0], $date[1], $date[2]));}
					elseif ($date_format == 'int')
						{$id3_tags['date'] = gmdate('Y-m-d', gmmktime(0, 0, 0, $date[1], $date[2], $date[0]));}
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

	$serviceId = $deftime = array();
	foreach ($services as $service) {
		$serviceId[] = $service->id;
		$deftime[] = $service->time;
	}

	$timeArr = '';
	for ($lol = 0; $lol < count($serviceId); $lol++) {
		$timeArr .= 'timeArr['.(int) $serviceId[$lol].'] = '.wp_json_encode((string) $deftime[$lol]).';';
	}

	if (isset($_GET['mid'])) {
		$mid = (int) $_GET['mid'];
		$curSermon = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sb_sermons WHERE id = %d", $mid));
		$files = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sb_stuff WHERE sermon_id IN (0, %d) AND type = 'file' ORDER BY name asc", $mid));
		$startArr = (array) sb_unserialize($curSermon->start);
		$endArr = (array) sb_unserialize($curSermon->end);
		$rawtags = $wpdb->get_results($wpdb->prepare("SELECT t.name FROM {$wpdb->prefix}sb_sermons_tags as st LEFT JOIN {$wpdb->prefix}sb_tags as t ON st.tag_id = t.id WHERE st.sermon_id = %d ORDER BY t.name asc", $mid));
		$tags = array();
		foreach ($rawtags as $tag) {
			$tags[] = $tag->name;
		}
		$tags = implode(', ', (array) $tags);
	} else
		{$startArr = $endArr = array();}
	$books = sb_get_default('eng_bible_books');
?>
	<script type="text/javascript">
		var timeArr = new Array();
		<?php echo $timeArr // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from (int) ids and wp_json_encode() ?>
		function createNewPreacher(s) {
			if (jQuery('#preacher')[0].value != 'newPreacher') return;
			var p = prompt(<?php echo wp_json_encode(__("New preacher's name?", 'sermon-browser')) ?>, <?php echo wp_json_encode(__("Preacher's name", 'sermon-browser')) ?>);
			if (p != null) {
				jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/sermon.php')); ?>, {pname: p, sermon: 1, _sbnonce: sbAjaxNonce}, function(r) {
					if (r) {
						jQuery('#preacher option:first').before(jQuery('<option>').val(r).text(p));
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
				s = prompt(<?php echo wp_json_encode(__("New service's name @ default time?", 'sermon-browser')) ?>, <?php echo wp_json_encode(__("Service's name @ 18:00", 'sermon-browser')) ?>);
				if (s == null) { break;	}
			}
			if (s != null) {
				jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/sermon.php')); ?>, {sname: s, sermon: 1, _sbnonce: sbAjaxNonce}, function(r) {
					if (r) {
						jQuery('#service option:first').before(jQuery('<option>').val(r).text(s.match(/(.*?)@/)[1]));
						jQuery("#service option[value='" + r + "']").attr('selected', 'selected');
						jQuery('#time').val(s.match(/(.*?)@\s*(.*)/)[2]);
					};
				});
			}
		}
		function createNewSeries(s) {
			if (jQuery('#series')[0].value != 'newSeries') return;
			var ss = prompt(<?php echo wp_json_encode(__("New series' name?", 'sermon-browser')) ?>, <?php echo wp_json_encode(__("Series' name", 'sermon-browser')) ?>);
			if (ss != null) {
				jQuery.post(<?php echo wp_json_encode(admin_url('admin.php?page=sermon-browser/sermon.php')); ?>, {ssname: ss, sermon: 1, _sbnonce: sbAjaxNonce}, function(r) {
					if (r) {
						jQuery('#series option:first').before(jQuery('<option>').val(r).text(ss));
						jQuery("#series option[value='" + r + "']").attr('selected', 'selected');
					};
				});
			}
		}
		function addPassage() {
			var p = jQuery('#passage').clone();
			p.attr('id', 'passage' + gpid);
			jQuery('tr:first td:first', p).prepend('[<a href="javascript:removePassage(' + gpid++ + ')">x</a>] ');
			jQuery("select", p).attr('value', '');
			jQuery("input", p).attr('value', '');
			jQuery('.passage:last').after(p);
		}
		function removePassage(id) {
			jQuery('#passage' + id).remove();
		}
		function syncBook(s) {
			var slc = jQuery(s)[0].value;
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
		<img src="<?php echo esc_url( SB_PLUGIN_URL . '/sb-includes/logo-small.png' ); ?>" width="191" height ="35" style="margin: 1em 2em; float: right;" />
		<h2><?php echo isset($_GET['mid']) ? esc_html__('Edit Sermon', 'sermon-browser') : esc_html__('Add Sermon', 'sermon-browser'); ?></h2>
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
						<th scope="col" colspan="2"><?php esc_html_e('Enter sermon details', 'sermon-browser') ?></th>
					</tr>
				</thead>
				<tr>
					<td>
						<strong><?php esc_html_e('Title', 'sermon-browser') ?></strong>
						<div>
							<input type="text" value="<?php if (isset($id3_tags['title'])) {echo esc_attr(wp_specialchars_decode($id3_tags['title'], ENT_QUOTES));} elseif (isset($curSermon->title)) {echo esc_attr(stripslashes($curSermon->title));} ?>" name="title" size="60" style="width:400px;" />
						</div>
					</td>
					<td>
						<strong><?php esc_html_e('Tags (comma separated)', 'sermon-browser') ?></strong>
						<div>
							<input type="text" name="tags" value="<?php echo isset($tags) ? esc_attr(stripslashes($tags)) : ''?>" style="width:400px" />
						</div>
					</td>
				</tr>
				<tr>
					<td>
						<strong><?php esc_html_e('Preacher', 'sermon-browser') ?></strong><br/>
							<select id="preacher" name="preacher" onchange="createNewPreacher(this)">
								<?php if (count($preachers) == 0): ?>
									<option value="" selected="selected"></option>
								<?php else: ?>
									<?php foreach ($preachers as $preacher):
											if (isset($id3_tags['preacher']))
												{$preacher_id = $id3_tags['preacher'];}
											elseif (isset ($curSermon->preacher_id))
												{$preacher_id = $curSermon->preacher_id;}
											else
												{$preacher_id = -1;} ?>
									<option value="<?php echo (int) $preacher->id ?>" <?php echo $preacher->id == $preacher_id ? 'selected="selected"' : ''?>><?php echo esc_html(stripslashes($preacher->name)) ?></option>
									<?php endforeach ?>
								<?php endif ?>
								<option value="newPreacher"><?php esc_html_e('Create new preacher', 'sermon-browser') ?></option>
							</select>
					</td>
					<td>
						<strong><?php esc_html_e('Series', 'sermon-browser') ?></strong><br/>
						<select id="series" name="series" onchange="createNewSeries(this)">
							<?php if (count($series) == 0): ?>
								<option value="" selected="selected"></option>
							<?php else: ?>
								<?php foreach ($series as $item):
										if (isset($id3_tags['series']))
											{$series_id = $id3_tags['series'];}
										elseif (isset($curSermon->series_id))
											{$series_id = $curSermon->series_id;}
										else
											{$series_id = -1;} ?>
									<option value="<?php echo (int) $item->id ?>" <?php echo $item->id == $series_id ? 'selected="selected"' : '' ?>><?php echo esc_html(stripslashes($item->name)) ?></option>
								<?php endforeach ?>
							<?php endif ?>
							<option value="newSeries"><?php esc_html_e('Create new series', 'sermon-browser') ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td style="overflow: visible">
						<strong><?php esc_html_e('Date', 'sermon-browser') ?></strong> (yyyy-mm-dd)
						<div>
							<input type="date" id="date" name="date" value="<?php if ((isset($curSermon->datetime) && $curSermon->datetime != '1970-01-01 00:00:00') || isset($id3_tags['date'])) echo esc_attr(isset($id3_tags['date']) ? $id3_tags['date'] : substr(stripslashes($curSermon->datetime),0,10)) ?>" />
						</div>
					</td>
					<td rowspan="3">
						<strong><?php esc_html_e('Description', 'sermon-browser') ?></strong>
						<div>
							<?php	if (isset($id3_tags['description']))
										{$desc = wp_specialchars_decode($id3_tags['description'], ENT_QUOTES);}
									elseif (isset($curSermon->description))
										{$desc = stripslashes($curSermon->description);}
									else
										{$desc = '';} ?>
							<textarea name="description" cols="50" rows="7"><?php echo esc_textarea($desc); ?></textarea>
						</div>
					</td>
				</tr>
				<tr>
					<td>
						<strong><?php esc_html_e('Service', 'sermon-browser') ?></strong><br/>
						<select id="service" name="service" onchange="createNewService(this)">
							<?php if (count($services) == 0): ?>
								<option value="" selected="selected"></option>
							<?php else: ?>
								<?php foreach ($services as $service): ?>
									<option value="<?php echo (int) $service->id ?>" <?php echo (isset($curSermon->service_id) && $service->id == $curSermon->service_id) ? 'selected="selected"' : '' ?>><?php echo esc_html(stripslashes($service->name)) ?></option>
								<?php endforeach ?>
							<?php endif ?>
							<option value="newService"><?php esc_html_e('Create new service', 'sermon-browser') ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<strong><?php esc_html_e('Time', 'sermon-browser') ?></strong>
						<div>
							<input type="text" name="time" value="<?php echo isset($curSermon->time) ? esc_attr($curSermon->time) : ''?>" id="time" <?php echo isset($curSermon->override) && $curSermon->override ? '' : 'disabled="disabled" class="gray"' ?> />
							<input type="checkbox" name="override" id="override" onchange="doOverride()" <?php echo isset($curSermon->override) && $curSermon->override ? 'checked="checked"' : ''?>> <?php esc_html_e('Override default time', 'sermon-browser') ?>
						</div>
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<strong><?php esc_html_e('Bible passage', 'sermon-browser') ?></strong> (<a href="javascript:addPassage()"><?php esc_html_e('add more', 'sermon-browser') ?></a>)
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e('From', 'sermon-browser') ?></td>
					<td><?php esc_html_e('To', 'sermon-browser') ?></td>
				</tr>
				<tr id="passage" class="passage">
					<td>
						<table>
							<tr>
								<td>
									<select id="startbook" name="start[book][]" onchange="syncBook(this)" class="start1">
										<option value=""></option>
										<?php foreach ($books as $book): ?>
											<option value="<?php echo esc_attr($book) ?>"><?php echo esc_html($translated_books[$book]) ?></option>
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
											<option value="<?php echo esc_attr($book) ?>"><?php echo esc_html($translated_books[$book]) ?></option>
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
						<strong><?php esc_html_e('Attachments', 'sermon-browser') ?></strong> (<a href="javascript:addFile()"><?php esc_html_e('add more', 'sermon-browser') ?></a>)
					</td>
				</tr>
				<tr >
					<td colspan="2">
						<table>
							<tr id="choosefile" class="choose">
								<th scope="row" style="padding:3px 7px">
								<select class="choosefile" name="choosefile" onchange="chooseType(this.name, this.value);">
								<option value="filelist"><?php esc_html_e('Choose existing file:', 'sermon-browser') ?></option>
								<option value="newupload"><?php esc_html_e('Upload a new one:', 'sermon-browser') ?></option>
								<option value="newurl"><?php esc_html_e('Enter an URL:', 'sermon-browser') ?></option>
								<option value="newcode"><?php esc_html_e('Enter embed or shortcode:', 'sermon-browser') ?></option>
								</select>
								</th>
								<td class="filelist">
									<select id="file" name="file[]">
									<?php echo count($files) == 0 ? '<option value="0">No files found</option>' : '<option value="0"></option>' ?>
									<?php foreach ($files as $file): ?>
										<option value="<?php echo (int) $file->id ?>"><?php echo esc_html($file->name) ?></option>
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
		<p class="submit"><input type="submit" name="save" value="<?php esc_attr_e('Save', 'sermon-browser') ?> &raquo;" /></p>
		<?php wp_nonce_field('sermon_browser_save', 'sermon_browser_save_nonce'); ?>
		</form>
	</div>
	<script type="text/javascript">
		<?php if (empty($curSermon->time)): ?>
			jQuery('#time').val(timeArr[jQuery('*[selected]', jQuery("select[name='service']")).attr('value')]);
		<?php endif ?>
		<?php if (isset($mid) || (isset($filename) && $filename != '')): ?>
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
					$assocFiles = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = %d AND type = 'file' ORDER BY name asc;", $mid));
					$assocURLs = $wpdb->get_results($wpdb->prepare("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = %d AND type = 'url' ORDER BY name asc;", $mid));
					$assocCode = $wpdb->get_results($wpdb->prepare("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = %d AND type = 'code' ORDER BY name asc;", $mid));
				}
				else
					{$assocFiles = $assocURLs = $assocCode = array();}
				$r = false;
				if (isset($filename) && $filename != '')
					{if ($file_data->type == 'url')
						{$assocURLs[] = (object) array('name' => $file_data->name);}
					else
						{$assocFiles[] = (object) array('id' => (int) $_GET['getid3']);}}
			?>

			<?php for ($lolz = 0; $lolz < count($assocFiles); $lolz++): ?>
				<?php $r = true ?>
				addFile();
				stuff.push(<?php echo (int) $assocFiles[$lolz]->id ?>);
				type.push('file');
			<?php endfor ?>

			<?php for ($lolz = 0; $lolz < count($assocURLs); $lolz++): ?>
				<?php $r = true ?>
				addFile();
				stuff.push(<?php echo wp_json_encode(wp_specialchars_decode($assocURLs[$lolz]->name, ENT_QUOTES)) ?>);
				type.push('url');
			<?php endfor ?>

			<?php for ($lolz = 0; $lolz < count($assocCode); $lolz++): ?>
				<?php $r = true ?>
				addFile();
				stuff.push(<?php echo wp_json_encode(base64_decode($assocCode[$lolz]->name), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
				type.push('code');
			<?php endfor ?>

			<?php if ($r): ?>
			jQuery('.choose:last').remove();
			<?php endif ?>

			<?php for ($lolz = 0; $lolz < count($startArr); $lolz++): ?>
				<?php if ($lolz != 0): ?>
					addPassage();
				<?php endif ?>
				start1.push(<?php echo wp_json_encode((string) $startArr[$lolz]['book']) ?>);
				start2.push(<?php echo (int) $startArr[$lolz]['chapter'] ?>);
				start3.push(<?php echo (int) $startArr[$lolz]['verse'] ?>);
				end1.push(<?php echo wp_json_encode((string) $endArr[$lolz]['book']) ?>);
				end2.push(<?php echo (int) $endArr[$lolz]['chapter'] ?>);
				end3.push(<?php echo (int) $endArr[$lolz]['verse'] ?>);
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
						jQuery(".newcode input", this).val(stuff[i]);
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
sb_do_alerts();
?>
	<div class="wrap">
		<img src="<?php echo esc_url( SB_PLUGIN_URL . '/sb-includes/logo-small.png' ); ?>" width="191" height ="35" style="margin: 1em 2em; float: right;" />
		<div style="width:45%;float:right;clear:right">
		<h2><?php esc_html_e('Thank you', 'sermon-browser') ?></h2>
		<p><?php esc_html_e('Thanks to everyone who has helped with Sermon Browser:', 'sermon-browser') ?></p>
		<ul style="list-style-type:circle; margin-left: 2em">
			<li><a href="http://codeandmore.com/">Tien Do Xuan</a> (help with initial coding).
			<li>James Hudson, Matthew Hiatt, Mark Bouchard, Ben Miller (code contributions)</li>
			<li>Juan Carlos and Marvin Ortega (Spanish translation)</li>
			<li><a href="http://www.fatcow.com/">FatCow</a> (Russian translation)</li>
			<li><a href="http://intercer.net/">Lucian Mihailescu</a> (Romanian translation)</li>
			<li>Monika Gause (German translation)</li>
			<li><a href="http://www.djio.com.br/sermonbrowser-em-portugues-brasileiro-pt_br/">DJIO</a> (Brazilian Portugese translation)</li>
			<li><?php esc_html_e('Everyone who has reported bugs and suggested features', 'sermon-browser') ?></li>
		</ul>
	</div>
		<div style="width:45%;float:left">
		<h2><?php esc_html_e('Help page', 'sermon-browser') ?></h2>
		<h3><?php esc_html_e('Getting started', 'sermon-browser') ?></h3>
		<ol>
			<li><?php esc_html_e('Create a page with the shortcode [sermons]. Your sermons will be listed there, with a search form, and each sermon has its own page.', 'sermon-browser') ?></li>
			<li><?php
				/* translators: 1: link to the Preachers page, 2: link to the Series & Services page */
				printf(esc_html__('Add your preachers on the %1$s page, and your series and services on the %2$s page.', 'sermon-browser'), '<a href="'.esc_url(admin_url('admin.php?page=sermon-browser/preachers.php')).'">'.esc_html__('Preachers', 'sermon-browser').'</a>', '<a href="'.esc_url(admin_url('admin.php?page=sermon-browser/manage.php')).'">'.esc_html__('Series &amp; Services', 'sermon-browser').'</a>') ?></li>
			<li><?php
				/* translators: %s: link to the Add Sermon page */
				printf(esc_html__('Add a sermon on the %s page, and attach its recording. You can upload an MP3, choose one already uploaded (on the Files page, or copied to the upload folder by FTP), link to a file elsewhere, or embed a video.', 'sermon-browser'), '<a href="'.esc_url(admin_url('admin.php?page=sermon-browser/new_sermon.php')).'">'.esc_html__('Add Sermon', 'sermon-browser').'</a>') ?></li>
			<li><?php
				/* translators: %s: link to the Options page */
				printf(esc_html__('If your MP3 files are tagged with their title, preacher and series, set the import options on the %s page, and Sermon Browser will fill in the Add Sermon form for you.', 'sermon-browser'), '<a href="'.esc_url(admin_url('admin.php?page=sermon-browser/options.php')).'">'.esc_html__('Options', 'sermon-browser').'</a>') ?></li>
			<li><?php esc_html_e('To show recent or popular sermons elsewhere on your site, add the Sermons, Sermon Browser tags or Most popular sermons widget.', 'sermon-browser') ?></li>
		</ol>
		<h3><?php esc_html_e('Shortcode', 'sermon-browser') ?></h3>
		<p><?php esc_html_e('You can show one sermon, or a list of sermons, on any page or post, by adding the shortcode [sermons] with these options. For example, [sermons series=3 filter=none] lists the sermons in series 3 without the search form. The ids of preachers, series and services are on their admin pages.', 'sermon-browser') ?></p>
		<ul style="list-style-type:disc; margin-left: 2em">
			<li><code>id=52</code>, <code>id=latest</code>: <?php esc_html_e('one sermon, by its id, or the latest', 'sermon-browser') ?></li>
			<li><code>preacher=6</code>, <code>series=11</code>, <code>service=2</code>: <?php esc_html_e('sermons by one preacher, in one series or at one service', 'sermon-browser') ?></li>
			<li><code>book="1 John"</code>: <?php esc_html_e('sermons on a Bible book (its full name)', 'sermon-browser') ?></li>
			<li><code>tag=hope</code>: <?php esc_html_e('sermons with a tag', 'sermon-browser') ?></li>
			<li><code>limit=5</code>: <?php esc_html_e('the number of sermons on each page', 'sermon-browser') ?></li>
			<li><code>dir=asc</code>, <code>dir=desc</code>: <?php esc_html_e('oldest or newest first', 'sermon-browser') ?></li>
			<li><code>filter=dropdown</code>, <code>filter=oneclick</code>, <code>filter=none</code>: <?php esc_html_e('the kind of search form, or none', 'sermon-browser') ?></li>
			<li><code>filterhide=show</code>, <code>filterhide=hide</code>: <?php esc_html_e('whether the search form starts open or folded away', 'sermon-browser') ?></li>
		</ul>
		<h3><?php esc_html_e('Templates', 'sermon-browser') ?></h3>
		<p><?php
			/* translators: %s: link to the Templates page */
			printf(esc_html__('The %s page sets how the list of sermons and each sermon\'s page look, using HTML and these template tags. Its help tab lists them too.', 'sermon-browser'), '<a href="'.esc_url(admin_url('admin.php?page=sermon-browser/templates.php')).'">'.esc_html__('Templates', 'sermon-browser').'</a>') ?></p>
		<?php sb_print_template_tag_help() ?>
		<h4 id="template-errors"><?php esc_html_e('Where tags work', 'sermon-browser') ?></h4>
		<p><?php esc_html_e('In the search results template, the tags about a sermon (its title, preacher, date and so on) only work between [sermons_loop] and [/sermons_loop], which repeats them for each sermon. In the sermon page template, [passage] only works between [passages_loop] and [/passages_loop]. Every loop needs its closing tag.', 'sermon-browser') ?></p>
		<p><?php esc_html_e('[file], [file_with_download] and [embed] show each file or embed between [files_loop] and [/files_loop], or [embed_loop] and [/embed_loop]; elsewhere in a sermon, they show its first.', 'sermon-browser') ?></p>
		<p><?php esc_html_e('A tag that can\'t work where it is, or a loop that isn\'t closed, is left out of your pages. Administrators see a message there instead, and the Templates page lists them.', 'sermon-browser') ?></p>
		<h3><?php esc_html_e('Questions and problems', 'sermon-browser') ?></h3>
		<p><?php
			/* translators: 1: link to the frequently asked questions, 2: link to report a problem */
			printf(esc_html__('Answers to common questions are in the readme\'s %1$s. To report a problem or suggest a feature, %2$s.', 'sermon-browser'), '<a href="'.esc_url(SermonBrowser\Plugin::HELP_URL).'">'.esc_html__('frequently asked questions', 'sermon-browser').'</a>', '<a href="'.esc_url(SermonBrowser\Plugin::ISSUES_URL).'">'.esc_html__('open an issue on GitHub', 'sermon-browser').'</a>') ?></p>
		</div>
	</div>
<?php
}

/**
* Prints the template tags with their descriptions, grouped by the template they work in
*/
function sb_print_template_tag_help() {
	require_once(SB_INCLUDES_DIR.'/dictionary.php');
	$results = sb_search_results_dictionary();
	$single = sb_sermon_page_dictionary();
	$retired = sb_retired_template_tags();
	$groups = array(
		'both' => array(__('Both templates', 'sermon-browser'), array()),
		'results' => array(__('Search results page only', 'sermon-browser'), array()),
		'single' => array(__('Sermon page only', 'sermon-browser'), array()),
	);
	foreach (sb_template_tag_descriptions() as $tag => $description) {
		if (isset($results[$tag]) && isset($single[$tag]))
			$groups['both'][1][$tag] = $description;
		elseif (isset($results[$tag]))
			$groups['results'][1][$tag] = $description;
		else
			$groups['single'][1][$tag] = $description;
	}
	foreach ($groups as $group) {
		echo '<h4>'.esc_html($group[0])."</h4>\n<dl>\n";
		foreach ($group[1] as $tag => $description)
			echo '<dt><code>'.esc_html($tag).'</code></dt><dd>'.esc_html($description)."</dd>\n";
		echo "</dl>\n";
	}
	echo '<p>'.esc_html__('These tags print nothing, because the service that provided their Bible text no longer exists:', 'sermon-browser').' <code>'.esc_html(implode(' ', $retired))."</code></p>\n";
}

/***************************************
 ** Supplementary functions           **
 **************************************/

/**
* Displays alerts in admin for new users
*/
function sb_do_alerts() {
	global $wpdb;
	if (stripos(sb_get_option('mp3_shortcode'), '%SERMONURL%') === false) {
		echo '<div id="message" class="updated fade"><p><b>';
		echo wp_kses_post( __('Error:</b> The MP3 shortcode must link to individual sermon files. You do this by including <span style="color:red">%SERMONURL%</span> in your shortcode (e.g. [audio mp3="%SERMONURL%"]). SermonBrowser will then replace %SERMONURL% with a link to each sermon.', 'sermon-browser') );
		echo '</div>';
	} elseif (do_shortcode(sb_get_option('mp3_shortcode')) == sb_get_option('mp3_shortcode')) {
		echo '<div id="message" class="updated fade"><p><b>';
		echo wp_kses_post( __('Error:</b> You have specified a custom MP3 shortcode, but Wordpress doesn&#146;t know how to interpret it. Make sure the shortcode is correct, and that the appropriate plugin is activated.', 'sermon-browser') );
		echo '</div>';
	}
	if (sb_display_url() == "") {
		/* translators: the two %s are the start and end of a link to create a page */
		echo '<div id="message" class="updated"><p><b>'.esc_html__('Hint:', 'sermon-browser').'</b> '.sprintf(esc_html__('%sCreate a page%s that includes the shortcode [sermons], so that SermonBrowser knows where to display the sermons on your site.', 'sermon-browser'), '<a href="'.esc_url(admin_url('page-new.php')).'">', '</a>').'</div>'; // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- unchanged, so the existing translations still match
	}
}

/**
* Show the textarea input
*/
function sb_build_textarea($name, $html) {
	$out = '<textarea name="'.esc_attr($name).'" cols="75" rows="20" style="width:100%">';
	$out .= esc_textarea(stripslashes(str_replace('\r\n', "\n", $html)));
	$out .= '</textarea>';
	echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
}

/**
* Displays stats in the dashboard
*/
function sb_rightnow () {
	global $wpdb;
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
		$output_string .= '<p class="youhave">'.esc_html__("You have", 'sermon-browser')." ";
		$output_string .= '<a href="'.esc_url(admin_url('admin.php?page=sermon-browser/files.php')).'">';
		/* translators: %s: number of files */
		$output_string .= esc_html(sprintf(_n('%s file', '%s files', $file_count, 'sermon-browser'), number_format($file_count)))."</a> ";
		if ($sermon_count > 0) {
			$output_string .= esc_html__("in", 'sermon-browser')." ".'<a href="'.esc_url(admin_url('admin.php?page=sermon-browser/sermon.php')).'">';
			/* translators: %s: number of sermons */
			$output_string .= esc_html(sprintf(_n('%s sermon', '%s sermons', $sermon_count, 'sermon-browser'), number_format($sermon_count)))."</a> ";
		}
		if ($preacher_count > 0) {
			$output_string .= esc_html__("from", 'sermon-browser')." ".'<a href="'.esc_url(admin_url('admin.php?page=sermon-browser/preachers.php')).'">';
			/* translators: %s: number of preachers */
			$output_string .= esc_html(sprintf(_n('%s preacher', '%s preachers', $preacher_count, 'sermon-browser'), number_format($preacher_count)))."</a> ";
		}
		if ($series_count > 0) {
			$output_string .= esc_html__("in", 'sermon-browser')." ".'<a href="'.esc_url(admin_url('admin.php?page=sermon-browser/manage.php')).'">';
			/* translators: %s: number of series */
			$output_string .= esc_html(sprintf(__('%s series', 'sermon-browser'), number_format($series_count)))."</a> ";
		}
		if ($tag_count > 0)
			/* translators: %s: number of tags */
			{$output_string .= esc_html__("using", 'sermon-browser')." ".esc_html(sprintf(_n('%s tag', '%s tags', $tag_count, 'sermon-browser'), number_format($tag_count)))." ";}
		if (substr($output_string, -1) == " ")
			{$output_string = substr($output_string, 0, -1);}
		if ($download_count > 0)
			/* translators: %s: number of downloads */
			{$output_string .= ". ".esc_html(sprintf(_n('Only one file has been downloaded', 'They have been downloaded a total of %s times', $download_count, 'sermon-browser'), number_format($download_count)));} // phpcs:ignore WordPress.WP.I18n.MissingSingularPlaceholder -- the singular needs no number
		if ($download_count > 1) {
			/* translators: %d: average number of downloads */
			$output_string .= ", ".esc_html(sprintf(_n('an average of once per sermon', 'an average of %d times per sermon', $download_average, 'sermon-browser'), $download_average)); // phpcs:ignore WordPress.WP.I18n.MissingSingularPlaceholder -- the singular needs no number
			$most_popular_title = '<a href="'.esc_url(sb_display_url().sb_query_char(false).'sermon_id='.(int) $most_popular->sermon_id).'">'.esc_html(stripslashes($most_popular->title)).'</a>';
			/* translators: the first %s is the sermon's title, the second the number of downloads */
			$output_string .= ". ".sprintf(esc_html__('The most popular sermon is %s, which has been downloaded %s times', 'sermon-browser'), $most_popular_title, esc_html(number_format($most_popular->c))); // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- unchanged, so the existing translations still match
		}
		$output_string .= '.</p>';
	}
	echo $output_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it is built
}

/**
* Find new files uploaded by FTP
*/
function sb_scan_dir() {
	global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE type = 'file' AND name = '' AND sermon_id=0");
	$files = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE type = 'file';");
	$bnn = array();
	$dir = SB_ABSPATH.sb_get_option('upload_dir');
	foreach ($files as $file) {
		$bnn[] = $file->name;
		if (!file_exists($dir.$file->name)) {
			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_stuff WHERE name=%s AND sermon_id=0", $file->name));
		}
	}

	if ($dh = @opendir($dir)) {
		while (false !== ($file = readdir($dh))) {
			if (!is_dir($dir.$file) && !in_array($file, $bnn) && sb_is_allowed_file_name($file)) {
				$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'file', %s, 0, 0, 0);", $file));
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
		if (wp_is_writable($sermonUploadDir)) {
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
		{foreach ($unused_tags AS $unused_tag)
			{$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_tags WHERE id=%d", $unused_tag->id));}}
}

/**
* Displays the main sermon widget options and handles changes
*/
function sb_widget_sermon_control( $widget_args = 1 ) {
	global $wpdb, $wp_registered_widgets;
	static $updated = false;

	$dpreachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY id;");
	$dseries = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY id;");
	$dservices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_services ORDER BY id;");

	if ( is_numeric($widget_args) )
		{$widget_args = array( 'number' => $widget_args );}
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract( $widget_args, EXTR_SKIP );

	$options = sb_get_option('sermons_widget_options');
	if ( !is_array($options) )
		{$options = array();}

	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- WordPress checks the widget screen's nonce before calling widget controls; each value is cast or sanitised below
	if ( !$updated && !empty($_POST['sidebar']) ) {
		$sidebar = (string)sanitize_key($_POST['sidebar']);
		$sidebars_widgets = get_option('sidebars_widgets', array());
		if ( isset($sidebars_widgets[$sidebar]) )
			{$this_sidebar =& $sidebars_widgets[$sidebar];}
		else
			{$this_sidebar = array();}
		foreach ( $this_sidebar as $_widget_id ) {
			if ( 'sb_widget_sermon' == $wp_registered_widgets[$_widget_id]['callback'] && isset($wp_registered_widgets[$_widget_id]['params'][0]['number']) ) {
				$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
				if ( !in_array( "sermon-$widget_number", isset($_POST['widget-id']) ? (array) $_POST['widget-id'] : array() ) )
					{unset($options[$widget_number]);}
			}
		}
		foreach ( isset($_POST['widget-sermon']) ? (array) $_POST['widget-sermon'] : array() as $widget_number => $widget_sermon_instance ) {
			if ( !isset($widget_sermon_instance['limit']) && isset($options[$widget_number]) )
				{continue;}
			$limit = (int) $widget_sermon_instance['limit'];
			$preacherz = (int) $widget_sermon_instance['preacherz'];
			$preacher = (int) $widget_sermon_instance['preacher'];
			$service = (int) $widget_sermon_instance['service'];
			$series = (int) $widget_sermon_instance['series'];
			$book = (int) $widget_sermon_instance['book'];
			$title = wp_strip_all_tags(stripslashes($widget_sermon_instance['title']));
			$date = (int) $widget_sermon_instance['date'];
			$options[$widget_number] = array( 'limit' => $limit, 'preacherz' => $preacherz, 'book' => $book, 'preacher' => $preacher, 'service' => $service, 'series' => $series, 'title' => $title, 'date' => $date);
		}
		sb_update_option('sermons_widget_options', $options);
		$updated = true;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput

	// Display widget form
	if ( -1 == $number )
		$number = '%i%';
	if ( !isset($options[$number]) ) {
		$limit = '';
		$preacherz = 0;
		$book = 0;
		$preacher = '';
		$service = '';
		$series = '';
		$title ='';
		$date = '';
	} else {
		$limit = $options[$number]['limit'];
		$preacher = (int) $options[$number]['preacher'];
		$service = (int) $options[$number]['service'];
		$series = (int) $options[$number]['series'];
		$preacherz = (int) $options[$number]['preacherz'];
		$book = (int) $options[$number]['book'];
		$title = $options[$number]['title'];
		$date = (int) $options[$number]['date'];
	}

?>
		<p><?php esc_html_e('Title:', 'sermon-browser'); ?> <input class="widefat" id="widget-sermon-title" name="widget-sermon[<?php echo esc_attr($number) ?>][title]" type="text" value="<?php echo esc_attr($title) ?>" /></p>
		<p>
			<?php esc_html_e('Number of sermons: ', 'sermon-browser') ?><input class="widefat" id="widget-sermon-limit-<?php echo esc_attr($number) ?>" name="widget-sermon[<?php echo esc_attr($number) ?>][limit]" type="text" value="<?php echo esc_attr($limit) ?>" />
			<hr />
			<input type="checkbox" id="widget-sermon-preacherz-<?php echo esc_attr($number) ?>" name="widget-sermon[<?php echo esc_attr($number) ?>][preacherz]" <?php echo $preacherz ? 'checked=checked' : '' ?> value="1"> <?php esc_html_e('Display preacher', 'sermon-browser') ?><br />
			<input type="checkbox" id="widget-sermon-book-<?php echo esc_attr($number) ?>" name="widget-sermon[<?php echo esc_attr($number) ?>][book]" <?php echo $book ? 'checked=checked' : '' ?> value="1"> <?php esc_html_e('Display bible passage', 'sermon-browser') ?><br />
			<input type="checkbox" id="widget-sermon-date-<?php echo esc_attr($number) ?>" name="widget-sermon[<?php echo esc_attr($number) ?>][date]" <?php echo $date ? 'checked=checked' : '' ?> value="1"> <?php esc_html_e('Display date', 'sermon-browser') ?><br />
			<hr />
			<table>
				<tr>
					<td><?php esc_html_e('Preacher: ', 'sermon-browser') ?></td>
					<td>
						<select name="widget-sermon[<?php echo esc_attr($number) ?>][preacher]" id="widget-sermon-preacher-<?php echo esc_attr($number) ?>">
							<option value="0" <?php echo $preacher ? '' : 'selected="selected"' ?>><?php esc_html_e('[All]', 'sermon-browser') ?></option>
							<?php foreach ($dpreachers as $cpreacher): ?>
								<option value="<?php echo (int) $cpreacher->id ?>" <?php echo $preacher == $cpreacher->id ? 'selected="selected"' : '' ?>><?php echo esc_html(stripslashes($cpreacher->name)) ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e('Service: ', 'sermon-browser') ?></td>
					<td>
						<select name="widget-sermon[<?php echo esc_attr($number) ?>][service]" id="widget-sermon-service-<?php echo esc_attr($number) ?>">
							<option value="0" <?php echo $service ? '' : 'selected="selected"' ?>><?php esc_html_e('[All]', 'sermon-browser') ?></option>
							<?php foreach ($dservices as $cservice): ?>
								<option value="<?php echo (int) $cservice->id ?>" <?php echo $service == $cservice->id ? 'selected="selected"' : '' ?>><?php echo esc_html(stripslashes($cservice->name)) ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e('Series: ', 'sermon-browser') ?></td>
					<td>
						<select name="widget-sermon[<?php echo esc_attr($number) ?>][series]" id="widget-sermon-series-<?php echo esc_attr($number) ?>">
							<option value="0" <?php echo $series ? '' : 'selected="selected"' ?>><?php esc_html_e('[All]', 'sermon-browser') ?></option>
							<?php foreach ($dseries as $cseries): ?>
								<option value="<?php echo (int) $cseries->id ?>" <?php echo $series == $cseries->id ? 'selected="selected"' : '' ?>><?php echo esc_html(stripslashes($cseries->name)) ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
			</table>
			<input type="hidden" id="widget-sermon-submit-<?php echo esc_attr($number) ?>" name="widget-sermon[<?php echo esc_attr($number) ?>][submit]" value="1" />
		</p>
<?php
}

/**
* Displays the most popular sermons widget options and handles changes
*/
function sb_widget_popular_control() {
	$options = sb_get_option('popular_widget_options');
	if ( !is_array($options) )
		{$options = array('title' => '', 'limit' => 5, 'display_sermons' => true, 'display_series' => true, 'display_preachers' => true);}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- WordPress checks the widget screen's nonce before calling widget controls
	if (isset($_POST['widget-popular-sermons-submit'])) {
		$title = isset($_POST['widget-popular-title']) ? sanitize_text_field(wp_unslash($_POST['widget-popular-title'])) : '';
		$limit = isset($_POST['widget-popular-limit']) ? (int) $_POST['widget-popular-limit'] : 5;
		$display_sermons = (isset($_POST['widget-popular-display-sermons']));
		$display_series = (isset($_POST['widget-popular-display-series']));
		$display_preachers = (isset($_POST['widget-popular-display-preachers']));

		$options = array('title' => $title, 'limit' => $limit, 'display_sermons' => $display_sermons, 'display_series' => $display_series, 'display_preachers' => $display_preachers);
		sb_update_option('popular_widget_options', $options);
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$title = $options['title'];
	$limit = (int) $options['limit'];
	$display_sermons = (bool) esc_attr($options['display_sermons']);
	$display_series = (bool) esc_attr($options['display_series']);
	$display_preachers = (bool) esc_attr($options['display_preachers']);

?>
		<p><?php esc_html_e('Title:', 'sermon-browser'); ?> <input class="widefat" id="widget-popular-title" name="widget-popular-title" type="text" value="<?php echo esc_attr($title) ?>" /></p>
		<p>
			<?php esc_html_e('Number of sermons: ', 'sermon-browser') ?><select id="widget-popular-limit" name="widget-popular-limit"><?php for($i=1; $i<=15; $i++) { echo '<option value="'.(int) $i.'"'.($i==$limit ? ' selected="yes"' : '').'>'.(int) $i.'</option>'; } ?></select>
			<div style="clear:both">
				<input type="checkbox" id="widget-popular-display-sermons" name="widget-popular-display-sermons" <?php echo $display_sermons ? 'checked=checked' : '' ?> value="1"> <?php esc_html_e('Display popular sermons', 'sermon-browser') ?><br />
				<input type="checkbox" id="widget-popular-display-series" name="widget-popular-display-series" <?php echo $display_series ? 'checked=checked' : '' ?> value="1"> <?php esc_html_e('Display popular series', 'sermon-browser') ?><br />
				<input type="checkbox" id="widget-popular-display-preachers" name="widget-popular-display-preachers" <?php echo $display_preachers ? 'checked=checked' : '' ?> value="1"> <?php esc_html_e('Display popular preachers', 'sermon-browser') ?><br />
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
		{return false;}
	else
		{return true;}
}

/**
* Displays notice if ID3 import options have not been set
*/
function sb_print_import_options_message($long = false) {
	if (!sb_import_options_set()) {
		if ($long) {
			esc_html_e('SermonBrowser can automatically pre-fill this form by reading ID3 tags from MP3 files.', 'sermon-browser');
			echo ' ';
		}
		/* translators: %s: link to the import options */
		printf (esc_html__ ('You will need to set the %s before you can import MP3s and pre-fill the Add Sermons form.', 'sermon-browser'), '<a href="'.esc_url(admin_url('admin.php?page=sermon-browser/options.php')).'">'.esc_html__('import options', 'sermon-browser').'</a>');
	}
}

/**
* echoes the upload form
*/
function sb_print_upload_form () {
	global $wpdb;
?>
	<table width="100%" cellspacing="2" cellpadding="5" class="widefat">
		<form method="post" enctype="multipart/form-data" action ="<?php echo esc_url(admin_url('admin.php?page=sermon-browser/files.php')); ?>" >
		<?php wp_nonce_field('sb_upload', 'sb_upload_nonce'); ?>
		<thead>
		<tr>
			<th scope="col" colspan="3"><?php if (sb_import_options_set()) {/* translators: %s: link to the Add Sermons page */ printf(esc_html__("Select an MP3 file here to have the %s form pre-filled using ID3 tags.", 'sermon-browser'), '<a href="'.esc_url(admin_url('admin.php?page=sermon-browser/new_sermon.php')).'">'.esc_html__('Add Sermons', 'sermon-browser').'</a>');} else {esc_html_e('Upload file', 'sermon-browser');}?></th>
		</tr>
		</thead>
		<tbody>
		<tr>
			<th nowrap style="width:20em" valign="top" scope="row"><?php esc_html_e('File to upload', 'sermon-browser') ?>: </th>
	<?php
	$checkSermonUpload = sb_checkSermonUploadable();
	if ($checkSermonUpload == 'writeable') {
	?>
			<td width ="40"><input type="file" size="40" value="" name="upload" /></td>
			<td class="submit"><input type="submit" name="save" value="<?php esc_attr_e('Upload', 'sermon-browser') ?> &raquo;" /></td>
	<?php
	} else
		if (is_multisite()) {
	?>
			<td><?php esc_html_e('Upload is disabled. Please contact your systems administrator.', 'sermon-browser');?></td>
	<?php
		} else {
	?>
			<td><?php esc_html_e('Upload is disabled. Please check your folder setting in Options.', 'sermon-browser');?></td>
	<?php
	}
	?>
		</tr>
	<?php if (sb_import_options_set()) { ?>
		<tr>
			<th nowrap valign="top" scope="row"><?php esc_html_e('URL to import', 'sermon-browser') ?>: </th>
			<td>
				<input type="text" size="40" value="" name="url"/><br/>
				<span style="line-height: 29px"><input type="radio" name="import_type" value="remote" checked="checked" /><?php esc_html_e('Link to remote file', 'sermon-browser') ?> <input type="radio" name="import_type" value="download" /><?php esc_html_e('Copy remote file to server', 'sermon-browser') ?></span>
			</td>
			<td class="submit"><input type="submit" name="import_url" value="<?php esc_attr_e('Import', 'sermon-browser') ?> &raquo;" /></td>
		</tr>
	<?php } ?>
	</form>
	<?php if (isset($_GET['page']) && $_GET['page'] == 'sermon-browser/new_sermon.php') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only chooses the form to show ?>
		<form method="get" action="<?php echo esc_url(admin_url('admin.php?page=sermon-browser/new_sermon.php')) ?>">
		<input type="hidden" name="page" value="sermon-browser/new_sermon.php" />
		<tr>
			<th nowrap valign="top" scope="row"><?php esc_html_e('Choose existing file', 'sermon-browser') ?>: </th>
			<td>
				<select name="getid3">
					<?php
						$files = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = 0 AND type = 'file' ORDER BY name asc");
						echo count($files) == 0 ? '<option value="0">No files found</option>' : '<option value="0"></option>';
						foreach ($files as $file) { ?>
							<option value="<?php echo (int) $file->id ?>"><?php echo esc_html($file->name) ?></option>
						<?php } ?>
				</select>
			</td>
			<td class="submit"><input type="submit" value="<?php esc_attr_e('Select', 'sermon-browser') ?> &raquo;" /></td>
		</tr>
	</form>
	<?php } ?>
		</tbody>
</table>
<?php }

/**
* Prints the nonce used by the admin pages' AJAX requests
*/
function sb_print_ajax_nonce() {
	if (isset($_GET['page']) && strpos(sanitize_text_field(wp_unslash($_GET['page'])), 'sermon-browser/') === 0) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only chooses the pages that get the nonce
		echo '<script type="text/javascript">var sbAjaxNonce = '.wp_json_encode(wp_create_nonce('sb_ajax')).";</script>\n";
}

/**
* Returns a human-readable name for a file's type
*
* @param string $file_name
* @param array $filetypes (from filetypes.php)
* @return string
*/
function sb_file_type_name($file_name, $filetypes) {
	$extension = substr($file_name, strrpos($file_name, '.') + 1);
	if (isset($filetypes[$extension]['name']))
		return $filetypes[$extension]['name'];
	return strtoupper($extension);
}

/**
* Prints the Rename and Delete links for a file on the Files page
*
* @param object $file (row from sb_stuff, with the linked sermon's title)
* @param string $id_prefix (prefix for element ids, used by search results)
*/
function sb_print_file_actions($file, $id_prefix = '') {
	$id = (int) $file->id;
	$name = wp_json_encode($file->name);
	$title = wp_json_encode(isset($file->title) ? stripslashes((string) $file->title) : '');
	echo '<a id="'.esc_attr($id_prefix).'link'.(int) $id.'" href="#" onclick="'.esc_attr("rename({$id}, {$name}); return false;").'">'.esc_html__('Rename', 'sermon-browser').'</a> | ';
	echo '<a href="#" onclick="'.esc_attr("if (sbConfirmDelete({$name}, {$title})) { kill({$id}); } return false;").'">'.esc_html__('Delete', 'sermon-browser').'</a>';
}

/**
* Adds the SermonBrowser help tab to the plugin's admin pages
*
* @param WP_Screen $screen
*/
function sb_add_help_tab($screen) {
	if (!isset($_GET['page']) || strpos(sanitize_text_field(wp_unslash($_GET['page'])), 'sermon-browser/') !== 0) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only chooses the pages that get the help tab
		return;
	$screen->add_help_tab(array(
		'id' => 'sermon-browser-help',
		'title' => __('SermonBrowser Help', 'sermon-browser'),
		'content' => sb_add_contextual_help(''),
	));
	if ($_GET['page'] == 'sermon-browser/templates.php') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only chooses the pages that get the help tab
		ob_start();
		sb_print_template_tag_help();
		$screen->add_help_tab(array(
			'id' => 'sermon-browser-template-tags',
			'title' => __('Template tags', 'sermon-browser'),
			'content' => ob_get_clean(),
		));
	}
}

function sb_add_contextual_help($help) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only chooses the help to show
	if (!isset($_GET['page']))
		{return $help;}
	else {
		$out = '<h5>'.esc_html__('SermonBrowser Help', 'sermon-browser')."</h5>\n";
		$out .= '<div class="metabox-prefs"><p>';
		switch (sanitize_text_field(wp_unslash($_GET['page']))) {
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
			case 'sermon-browser/sermon.php':
				$out .= esc_html__('From this page you can edit or delete any of your sermons. The most recent sermons are found at the top. Use the filter options to quickly find the one you want.', 'sermon-browser');
				break;
			case 'sermon-browser/new_sermon.php':
				$out .= esc_html__('Choose the date the sermon was preached: its time comes from the service, unless you override it. Add as many Bible passages and attachments as you need. Tags, separated by commas, let visitors find sermons on a subject.', 'sermon-browser');
				break;
			case 'sermon-browser/files.php':
				$out .= esc_html__('Files in the upload folder are listed here, whether they were uploaded on this page or copied there by FTP. Unlinked files are not attached to a sermon yet: create a sermon from one, or attach it on the Add Sermon page.', 'sermon-browser');
				break;
			case 'sermon-browser/preachers.php':
			case 'sermon-browser/manage.php':
				$out .= esc_html__('Preachers, series and services can only be deleted once no sermons use them. A service\'s default time is used for its sermons, unless a sermon overrides it.', 'sermon-browser');
				break;
			case 'sermon-browser/options.php':
				$out .= wp_kses_post(__('It&#146;s important that these options are set correctly, as otherwise SermonBrowser won&#146;t behave as you expect.', 'sermon-browser')).'<ul>';
				$out .= '<li>'.wp_kses_post(__('The upload folder would normally be <b>wp-content/uploads/sermons</b>', 'sermon-browser')).'</li>';
				$out .= '<li>'.esc_html__('Only change the public podcast feed if your podcast is published through another service. Otherwise it should be the same as the private podcast feed.', 'sermon-browser').'</li>';
				$out .= '<li>'.esc_html__('The MP3 shortcode shows a player for each MP3. The default, [audio mp3="%SERMONURL%"], uses WordPress\'s own player. To use another player plugin, enter its shortcode, with %SERMONURL% in place of the address of the MP3 file.', 'sermon-browser').'</li></ul>';
				break;
			case 'sermon-browser/templates.php':
				$out .= esc_html__('The templates set how the list of sermons and each sermon\'s page look. They are HTML, with template tags in square brackets for each part of a sermon: the Template tags tab lists them.', 'sermon-browser');
				break;
			case 'sermon-browser/uninstall.php':
			case 'sermon-browser/help.php':
		}
	}
	$out.= '</p><p><a href="'.esc_url(admin_url('admin.php?page=sermon-browser/help.php')).'">'.esc_html__('Help page', 'sermon-browser').'</a>';
	$out.= ' | <a href="'.esc_url(SermonBrowser\Plugin::HELP_URL).'">'.esc_html__('Frequently Asked Questions', 'sermon-browser').'</a>';
	$out.= ' | <a href="'.esc_url(SermonBrowser\Plugin::ISSUES_URL).'">'.esc_html__('Report a problem', 'sermon-browser').'</a>';
	$out.= '</p></div>';
	return $out;
}

/**
* Add Sermons menu and sub-menus in admin
*/
function sb_add_pages() {
	add_menu_page(__('Sermons', 'sermon-browser'), __('Sermons', 'sermon-browser'), 'publish_posts', 'sermon-browser/sermon.php', 'sb_manage_sermons', SB_PLUGIN_URL.'/sb-includes/sb-icon.png');
	add_submenu_page('sermon-browser/sermon.php', __('Sermons', 'sermon-browser'), __('Sermons', 'sermon-browser'), 'publish_posts', 'sermon-browser/sermon.php', 'sb_manage_sermons');
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only chooses the menu item's name
	if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'sermon-browser/new_sermon.php' && isset($_REQUEST['mid'])) {
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
		add_submenu_page('sermon-browser/sermon.php', __('Edit Sermon', 'sermon-browser'), __('Edit Sermon', 'sermon-browser'), 'publish_posts', 'sermon-browser/new_sermon.php', 'sb_new_sermon');
	} else {
		add_submenu_page('sermon-browser/sermon.php', __('Add Sermon', 'sermon-browser'), __('Add Sermon', 'sermon-browser'), 'publish_posts', 'sermon-browser/new_sermon.php', 'sb_new_sermon');
	}
	add_submenu_page('sermon-browser/sermon.php', __('Files', 'sermon-browser'), __('Files', 'sermon-browser'), 'upload_files', 'sermon-browser/files.php', 'sb_files');
	add_submenu_page('sermon-browser/sermon.php', __('Preachers', 'sermon-browser'), __('Preachers', 'sermon-browser'), 'manage_categories', 'sermon-browser/preachers.php', 'sb_manage_preachers');
	add_submenu_page('sermon-browser/sermon.php', __('Series &amp; Services', 'sermon-browser'), __('Series &amp; Services', 'sermon-browser'), 'manage_categories', 'sermon-browser/manage.php', 'sb_manage_everything');
	add_submenu_page('sermon-browser/sermon.php', __('Options', 'sermon-browser'), __('Options', 'sermon-browser'), 'manage_options', 'sermon-browser/options.php', 'sb_options');
	add_submenu_page('sermon-browser/sermon.php', __('Templates', 'sermon-browser'), __('Templates', 'sermon-browser'), 'manage_options', 'sermon-browser/templates.php', 'sb_templates');
	add_submenu_page('sermon-browser/sermon.php', __('Uninstall', 'sermon-browser'), __('Uninstall', 'sermon-browser'), 'edit_plugins', 'sermon-browser/uninstall.php', 'sb_uninstall');
	add_submenu_page('sermon-browser/sermon.php', __('Help', 'sermon-browser'), __('Help', 'sermon-browser'), 'publish_posts', 'sermon-browser/help.php', 'sb_help');
}
?>