<?php

// Checks for old-style sermonbrowser options (prior to 0.43)
function sb_upgrade_options () {
	$standard_options = array (
		array ('old_option' => 'sb_sermon_style_date_modified', 'new_option' => 'style_date_modified'),
		array ('old_option' => 'sb_sermon_db_version', 'new_option' => 'db_version'),
		array ('old_option' => 'sb_sermon_version', 'new_option' => 'code_version'),
		array ('old_option' => 'sb_podcast', 'new_option' => 'podcast_url'),
		array ('old_option' => 'sb_filtertype', 'new_option' => 'filter_type'),
		array ('old_option' => 'sb_filterhide', 'new_option' => 'filter_hide'),
		array ('old_option' => 'sb_widget_sermon', 'new_option' => 'sermons_widget_options'),
		array ('old_option' => 'sb_sermon_upload_dir', 'new_option' => 'upload_dir'),
		array ('old_option' => 'sb_sermon_upload_url', 'new_option' => 'upload_url'),
		array ('old_option' => 'sb_display_method', 'new_option' => 'display_method'),
		array ('old_option' => 'sb_sermons_per_page', 'new_option' => 'sermons_per_page'),
		array ('old_option' => 'sb_show_donate_reminder', 'new_option' => 'show_donate_reminder'),
		array ('old_option' => 'sb_display_method', 'new_option' => 'display_method'),
	);
	foreach ($standard_options as $option)
		if ($old = get_option($option['old_option'])) {
			sb_update_option($option['new_option'], $old);
			delete_option ($option['old_option']);
		}
	$base64_options = array (
		array ('old_option' => 'sb_sermon_single_form', 'new_option' => 'single_template'),
		array ('old_option' => 'sb_sermon_single_output', 'new_option' => 'single_output'),
		array ('old_option' => 'sb_sermon_multi_form', 'new_option' => 'search_template'),
		array ('old_option' => 'sb_sermon_multi_output', 'new_option' => 'search_output'),
		array ('old_option' => 'sb_sermon_style', 'new_option' => 'css_style'),
	);
	foreach ($base64_options as $option)
		if ($old = get_option($option['old_option'])) {
			$old = stripslashes(base64_decode($old));
			sb_update_option($option['new_option'], $old);
			delete_option ($option['old_option']);
		}
	delete_option('sb_sermon_style_output');
}

// Runs the version upgrade procedures (re-save templates, add options added since last db update)
function sb_version_upgrade ($old_version, $new_version) {
    require_once('dictionary.php');
	$sbmf = sb_get_option('search_template');
	if ($sbmf)
		sb_update_option('search_output', strtr($sbmf, sb_search_results_dictionary()));
	$sbsf = sb_get_option('single_template');
	if ($sbsf) 
		sb_update_option('single_output', strtr($sbsf, sb_sermon_page_dictionary()));
	sb_update_option('code_version', $new_version);
	if (sb_get_option('filter_type') == '')
		sb_update_option('filter_type', 'dropdown');
}
	
//Runs the database upgrade procedures (modifies database structure)
function sb_database_upgrade ($old_version) {
	global $wpdb;
	$sermonUploadDir = sb_get_default('sermon_path');
	switch ($old_version) {
		case '1.0': 
			// Also moves files from old default folder to new default folder
			$oldSermonPath = dirname(__FILE__)."/files/";
			$files = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_stuff WHERE type = 'file' ORDER BY name ASC");	
			foreach ((array)$files as $file) {
				@chmod(SB_ABSPATH.$oldSermonPath.$file->name, 0777);
				@rename(SB_ABSPATH.$oldSermonPath.$file->name, SB_ABSPATH.$sermonUploadDir.$file->name);
			}
			$table_name = $wpdb->prefix . "sb_preachers";
			if($wpdb->get_var("show tables like '{$table_name}'") == $table_name) {            
				  $wpdb->query("ALTER TABLE {$table_name} ADD description TEXT NOT NULL, ADD image VARCHAR(255) NOT NULL");
			}
			update_option('sb_sermon_db_version', '1.1');		
		case '1.1':
			add_option('sb_sermon_style', base64_encode($defaultStyle));
			if(!is_dir(SB_ABSPATH.$sermonUploadDir.'images') && sb_mkdir(SB_ABSPATH.$sermonUploadDir.'images')){
				@chmod(SB_ABSPATH.$sermonUploadDir.'images', 0777);
			}
			update_option('sb_sermon_db_version', '1.2');	
		case '1.2':
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_stuff ADD count INT(10) NOT NULL");
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_books_sermons ADD INDEX (sermon_id)");
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_sermons_tags ADD INDEX (sermon_id)");
			update_option('sb_sermon_db_version', '1.3');
		case '1.3':
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_series ADD page_id INT(10) NOT NULL");
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_sermons ADD page_id INT(10) NOT NULL");
			add_option('sb_display_method', 'dynamic');
			add_option('sb_sermons_per_page', '10');
			add_option('sb_sermon_multi_output', base64_encode(strtr(base64_decode(get_option('sb_sermon_multi_form')), sb_search_results_dictionary())));
			add_option('sb_sermon_single_output', base64_encode(strtr(base64_decode(get_option('sb_sermon_single_form')), sb_sermon_page_dictionary())));
			add_option('sb_sermon_style_output', base64_encode(stripslashes(base64_decode(get_option('sb_sermon_style')))));
			add_option('sb_sermon_style_date_modified', strtotime('now'));
			update_option('sb_sermon_db_version', '1.4');
		case '1.4' :
			//Remove duplicate indexes added by a previous bug
			$extra_indexes = $wpdb->get_results("SELECT index_name, table_name FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = '".DB_NAME."' AND index_name LIKE 'sermon_id_%'");
			if (is_array($extra_indexes))
				foreach ($extra_indexes as $extra_index)
					$wpdb->query("ALTER TABLE {$extra_index->table_name} DROP INDEX {$extra_index->index_name}");
			//Remove duplicate tags added by a previous bug
			$unique_tags = $wpdb->get_results("SELECT DISTINCT name FROM {$wpdb->prefix}sb_tags");
			if (is_array($unique_tags)) {
				foreach ($unique_tags as $tag) {
					$tag_ids = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}sb_tags WHERE name='{$tag->name}'");
					if (is_array($tag_ids)) {
						foreach ($tag_ids as $tag_id) {
							$wpdb->query("UPDATE {$wpdb->prefix}sb_sermons_tags SET tag_id='{$tag_ids[0]->id}' WHERE tag_id='{$tag_id->id}'");
							if ($tag_ids[0]->id != $tag_id->id)
								$wpdb->query("DELETE FROM {$wpdb->prefix}sb_tags WHERE id='{$tag_id->id}'");
						}
					}
				}
			}
			sb_delete_unused_tags();
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_tags CHANGE name name VARCHAR(255)");
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_tags ADD UNIQUE (name)");
			update_option('sb_sermon_db_version', '1.5');
		case '1.5' :
            sb_upgrade_options ();
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_stuff ADD duration VARCHAR (6) NOT NULL");
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_sermons CHANGE date `datetime` DATETIME NOT NULL");
			//Populate time portion of date/time field
			$sermon_dates = $wpdb->get_results("SELECT id, datetime, service_id, time, override FROM {$wpdb->prefix}sb_sermons");
			if ($sermon_dates) {
				$services = $wpdb->get_results("SELECT id, time FROM {$wpdb->prefix}sb_services ORDER BY id asc");
				foreach ($services as $service)
					$service_time[$service->id] = $service->time;
				foreach ($sermon_dates as $sermon_date) {
					if ($sermon_date->override)
						$sermon_date->datetime = strtotime($sermon_date->time)-strtotime('00:00')+strtotime($sermon_date->datetime);
					else {
						$sermon_date->datetime = strtotime($service_time[$sermon_date->service_id])-strtotime('00:00')+strtotime($sermon_date->datetime);
					}
					$sermon_date->datetime = date ("Y-m-d H:i:s", $sermon_date->datetime);
					$wpdb->query("UPDATE {$wpdb->prefix}sb_sermons SET datetime = '{$sermon_date->datetime}' WHERE id={$sermon_date->id}");
				}
			}
            sb_update_option('import_prompt', true);
            sb_update_option('import_title', false);
            sb_update_option('import_artist', false);
            sb_update_option('import_album', false);
            sb_update_option('import_comments', false);
            sb_update_option('import_filename', 'none');
            sb_update_option('hide_no_attachments', false);
			sb_update_option('db_version', '1.6');
			return;
		default :
			//To-do: Remove time field from sb_sermons?
			update_option('sb_sermon_db_version', '1.0');
	}
}
?>