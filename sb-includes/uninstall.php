<?php
if (!defined('ABSPATH'))
	exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for
if (isset($_POST['wipe'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- sb_uninstall() checks the nonce before loading this file
	$dir = SB_ABSPATH.sb_get_option('upload_dir');
	if ($dh = @opendir($dir)) {
		while (false !== ($file = readdir($dh))) {
			if ($file != "." && $file != "..") {
				wp_delete_file($dir.($file));
			}
		}
		closedir($dh);
	}
}
$tables = array ('sb_preachers', 'sb_series', 'sb_services', 'sb_sermons', 'sb_stuff', 'sb_books', 'sb_books_sermons', 'sb_sermons_tags', 'sb_tags');
global $wpdb;
foreach ($tables as $table)
	if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix.$table))) == $wpdb->prefix.$table)
		$wpdb->query("DROP TABLE {$wpdb->prefix}{$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- the plugin's own tables, listed above

delete_option('sermonbrowser_options');
$special_options = sb_special_option_names();
foreach ($special_options as $option)
	delete_option("sermonbrowser_{$option}");
// Templates compiled to PHP by versions before 0.49, in case the upgrade that removes them hasn't run
delete_option('sermonbrowser_search_output');
delete_option('sermonbrowser_single_output');

if (is_multisite()) {
	echo '<div id="message" class="updated fade"><p><b>'.esc_html__('All sermon data has been removed.', 'sermon-browser').'</b></div>';
} else {
	echo '<div id="message" class="updated fade"><p><b>'.esc_html__('Uninstall completed. The SermonBrowser plugin has been deactivated.', 'sermon-browser').'</b></div>';
	$activeplugins = get_option('active_plugins');
	array_splice($activeplugins, array_search('sermon-browser/sermon.php', $activeplugins), 1 );
	do_action('deactivate_sermon-browser/sermon.php');
	update_option('active_plugins', $activeplugins);
}
?>