<?php
if (isset($_POST['wipe'])) {
	$dir = SB_ABSPATH.sb_get_option('upload_dir');
	if ($dh = @opendir($dir)) {
		while (false !== ($file = readdir($dh))) {
			if ($file != "." && $file != "..") {
				@unlink($dir.($file));
			}
		}
		closedir($dh);
	}
}
$tables = array ('sb_preachers', 'sb_series', 'sb_services', 'sb_sermons', 'sb_stuff', 'sb_books', 'sb_books_sermons', 'sb_sermons_tags', 'sb_tags');
global $wpdb;
foreach ($tables as $table)
	if ($wpdb->get_var("show tables like '{$wpdb->prefix}{$table}'") == $wpdb->prefix.$table)
		$wpdb->query("DROP TABLE {$wpdb->prefix}{$table}");

delete_option('sermonbrowser_options');
$special_options = sb_special_option_names();
foreach ($special_options as $option)
	delete_option("sermonbrowser_{$option}");

if (IS_MU) {
	echo '<div id="message" class="updated fade"><p><b>'.__('All sermon data has been removed.', $sermon_domain).'</b></div>';
} else {
	echo '<div id="message" class="updated fade"><p><b>'.__('Uninstall completed. The SermonBrowser plugin has been deactivated.', $sermon_domain).'</b></div>';
	$activeplugins = get_option('active_plugins');
	array_splice($activeplugins, array_search('sermon-browser/sermon.php', $activeplugins), 1 );
	do_action('deactivate_sermon-browser/sermon.php');
	update_option('active_plugins', $activeplugins);
}
?>