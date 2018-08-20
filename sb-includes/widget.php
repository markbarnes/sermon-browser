<?php
/**
* Widget functions
*
* Functions required to manage and display widgets
* @package widget_functions
*/


/**
* Deprecated function - displays error message
*
* @param array $options
*/
function display_sermons($options = array()) {
	echo "This function is now deprecated. Use sb_display_sermons or the sermon browser widget, instead.";
}
/**
* Function to display sermons for users to add to their template
*
* @param array $options
*/
function sb_display_sermons($options = array()) {
	$default = array(
		'display_preacher' => 1,
		'display_passage' => 1,
		'display_date' => 1,
		'preacher' => 0,
		'service' => 0,
		'series' => 0,
		'limit' => 5,
	);
	$options = array_merge($default, (array) $options);
	extract($options);
	$sermons = sb_get_sermons(array(
			'preacher' => $preacher,
			'service' => $service,
			'series' => $series
		),
		array(), 1, $limit
	);
	echo "<ul class=\"sermon-widget\">\r";
	foreach ((array) $sermons as $sermon) {
		echo "\t<li>";
		echo "<span class=\"sermon-title\"><a href=\"";
		sb_print_sermon_link($sermon);
		echo "\">".stripslashes($sermon->title)."</a></span>";
		if ($display_passage) {
			$foo = unserialize($sermon->start);
			$bar = unserialize($sermon->end);
			echo "<span class=\"sermon-passage\"> (".sb_get_books($foo[0], $bar[0]).")</span>";
		}
		if ($display_preacher) {
			echo "<span class=\"sermon-preacher\">".__('by', 'sermon-browser')." <a href=\"";
			sb_print_preacher_link($sermon);
			echo "\">".stripslashes($sermon->preacher)."</a></span>";
		}
		if ($display_date)
			echo " <span class=\"sermon-date\">".__('on', 'sermon-browser')." ".sb_formatted_date ($sermon)."</span>";
		echo ".</li>\r";
	}
	echo "</ul>\r";
}

/**
* Registers the Sermon Browser widget
*
*/
function sb_widget_sermon_init() {
	if ( !$options = get_option('sb_widget_sermon') )
		$options = array();
	$widget_ops = array('classname' => 'sermon', 'description' => __('Sermon', 'sermon-browser'));
	$control_ops = array('width' => 400, 'height' => 350, 'id_base' => 'sermon');
	$name = __('Sermons', 'sermon-browser');
	$registered = false;
	foreach ( array_keys($options) as $o ) {
		if ( !isset($options[$o]['limit']) )
			continue;
		$id = "sermon-$o";
		$registered = true;
		wp_register_sidebar_widget( $id, $name, 'sb_widget_sermon', $widget_ops, array( 'number' => $o ) );
		wp_register_widget_control( $id, $name, 'sb_widget_sermon_control', $control_ops, array( 'number' => $o ) );
	}
	if ( !$registered ) {
		wp_register_sidebar_widget( 'sermon-1', $name, 'sb_widget_sermon', $widget_ops, array( 'number' => -1 ) );
		wp_register_widget_control( 'sermon-1', $name, 'sb_widget_sermon_control', $control_ops, array( 'number' => -1 ));
	}
	wp_register_sidebar_widget(__('Sermon Browser tags', 'sermon-browser'), 'sb_widget_tag_cloud');
}

/**
* Displays the tag cloud in the sidebar
*
* @param array $args
*/
function sb_widget_tag_cloud ($args) {
	extract($args);
	echo $before_widget;
	echo $before_title.__('Sermon Browser tags', 'sermon-browser').$after_title;
	sb_print_tag_clouds();
	echo $after_widget;
}

/**
* Returns the first MP3 file attached to a sermon
* Stats have to be turned off for iTunes compatibility
*
* @param object $sermon
* @param boolean $stats
* @returns string - URL of the first MP3 file for this sermon
*/
function sb_first_mp3($sermon, $stats= TRUE) {
	$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	if (stripos($user_agent, 'itunes') !== FALSE || stripos($user_agent, 'FeedBurner') !== FALSE)
		$stats = FALSE;
	$stuff = sb_get_stuff($sermon, true);
	$stuff = array_merge((array)$stuff['Files'], (array)$stuff['URLs']);
	foreach ((array) $stuff as $file) {
		if (strtolower(substr($file, strrpos($file, '.') + 1)) == 'mp3') {
			if (substr($file,0,7) == "http://") {
				if ($stats)
					$file=sb_display_url().sb_query_char().'show&amp;url='.rawurlencode($file);
			} else {
				if (!$stats)
					$file=sb_get_value('wordpress_url').get_option('sb_sermon_upload_dir').rawurlencode($file);
				else
					$file=sb_display_url().sb_query_char().'show&amp;file_name='.rawurlencode($file);
			}
			return $file;
			break;
		}
	}
}

/**
* Displays the widget
*
* @param array $args
* @param mixed $widget_args - An array of arguments, or the id number of this widget
*/
function sb_widget_sermon( $args, $widget_args = 1 ) {
	extract( $args, EXTR_SKIP );
	if ( is_numeric($widget_args) )
		$widget_args = array( 'number' => $widget_args );
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract( $widget_args, EXTR_SKIP );
	$options = get_option('sb_widget_sermon');
	if ( !isset($options[$number]) )
		return;
	extract($options[$number]);
	echo $before_widget;
	echo $before_title . $title . $after_title;
	$sermons = sb_get_sermons(array(
			'preacher' => $preacher,
			'service' => $service,
			'series' => $series
		),
		array(), 1, $limit
	);
	$i=0;
	echo "<ul class=\"sermon-widget\">";
	foreach ((array) $sermons as $sermon){
		$i++;
		echo "<li><span class=\"sermon-title\">";
		echo "<a href=".sb_build_url(array('sermon_id' => $sermon->id), true).">".stripslashes($sermon->title)."</a></span>";
		if ($book) {
			$foo = unserialize($sermon->start);
			$bar = unserialize($sermon->end);
			echo " <span class=\"sermon-passage\">(".sb_get_books($foo[0], $bar[0]).")</span>";
		}
		if ($preacherz) {
			echo " <span class=\"sermon-preacher\">".__('by', 'sermon-browser')." <a href=\"";
			sb_print_preacher_link($sermon);
			echo "\">".stripslashes($sermon->preacher)."</a></span>";
		}
		if ($date)
			echo " <span class=\"sermon-date\">".__(' on ', 'sermon-browser').sb_formatted_date ($sermon)."</span>";
		echo ".</li>";
	}
	echo "</ul>";
	echo $after_widget;
}

/**
* Displays the widget options and handles changes
*
* @param mixed $widget_args - An array of arguments, or the id number of this widget
*/
function sb_widget_sermon_control( $widget_args = 1 ) {
	global $wpdb, $wp_registered_widgets;
	static $updated = false;

	$dpreachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY id;");
	$dseries = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY id;");
	$dservices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_services ORDER BY id;");

	if ( is_numeric($widget_args) )
		$widget_args = array( 'number' => $widget_args );
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract( $widget_args, EXTR_SKIP );

	$options = get_option('sb_widget_sermon');
	if ( !is_array($options) )
		$options = array();

	if ( !$updated && !empty($_POST['sidebar']) ) {
		$sidebar = (string) sanitize_text_field($_POST['sidebar']);
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
			$limit = (int) $widget_sermon_instance['limit'];
			$preacherz = (int) $widget_sermon_instance['preacherz'];
			$preacher = (int) $widget_sermon_instance['preacher'];
			$service = (int) $widget_sermon_instance['service'];
			$series = (int) $widget_sermon_instance['series'];
			$book = (int) $widget_sermon_instance['book'];
			$title = strip_tags(stripslashes($widget_sermon_instance['title']));
			$date = (int) $widget_sermon_instance['date'];
			$options[$widget_number] = array( 'limit' => $limit, 'preacherz' => $preacherz, 'book' => $book, 'preacher' => $preacher, 'service' => $service, 'series' => $series, 'title' => $title, 'date' => $date);
		}
		update_option('sb_widget_sermon', $options);
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
	} else {
		$limit = esc_attr($options[$number]['limit']);
		$preacher = esc_attr($options[$number]['preacher']);
		$service = esc_attr($options[$number]['service']);
		$series = esc_attr($options[$number]['series']);
		$preacherz = (int) $options[$number]['preacherz'];
		$book = (int) $options[$number]['book'];
		$title = esc_attr($options[$number]['title']);
		$date = (int) $options[$number]['date'];
	}

?>
		<p><?php _e('Title:'); ?> <input class="widefat" id="widget-sermon-title" name="widget-sermon[<?php echo $number; ?>][title]" type="text" value="<?php echo $title; ?>" /></p>
		<p>
			<?php _e('Number of sermons: ', 'sermon-browser') ?><input class="widefat" id="widget-sermon-limit-<?php echo $number; ?>" name="widget-sermon[<?php echo $number; ?>][limit]" type="text" value="<?php echo $limit; ?>" />
			<hr />
			<input type="checkbox" id="widget-sermon-preacherz-<?php echo $number ?>" name="widget-sermon[<?php echo $number ?>][preacherz]" <?php echo $preacherz ? 'checked=checked' : '' ?> value="1"> <?php _e('Display preacher', 'sermon-browser') ?><br />
			<input type="checkbox" id="widget-sermon-book-<?php echo $number ?>" name="widget-sermon[<?php echo $number ?>][book]" <?php echo $book ? 'checked=checked' : '' ?> value="1"> <?php _e('Display bible passage', 'sermon-browser') ?><br />
			<input type="checkbox" id="widget-sermon-date-<?php echo $number ?>" name="widget-sermon[<?php echo $number ?>][date]" <?php echo $date ? 'checked=checked' : '' ?> value="1"> <?php _e('Display date', 'sermon-browser') ?><br />
			<hr />
			<table>
				<tr>
					<td><?php _e('Preacher: ', 'sermon-browser') ?></td>
					<td>
						<select name="widget-sermon[<?php echo $number; ?>][preacher]" id="widget-sermon-preacher-<?php echo $number; ?>">
							<option value="0" <?php echo $preacher ? '' : 'selected="selected"' ?>><?php _e('[All]', 'sermon-browser') ?></option>
							<?php foreach ($dpreachers as $cpreacher): ?>
								<option value="<?php echo $cpreacher->id ?>" <?php echo $preacher == $cpreacher->id ? 'selected="selected"' : '' ?>><?php echo $cpreacher->name ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
				<tr>
					<td><?php _e('Service: ', 'sermon-browser') ?></td>
					<td>
						<select name="widget-sermon[<?php echo $number; ?>][service]" id="widget-sermon-service-<?php echo $number; ?>">
							<option value="0" <?php echo $service ? '' : 'selected="selected"' ?>><?php _e('[All]', 'sermon-browser') ?></option>
							<?php foreach ($dservices as $cservice): ?>
								<option value="<?php echo $cservice->id ?>" <?php echo $service == $cservice->id ? 'selected="selected"' : '' ?>><?php echo $cservice->name ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
				<tr>
					<td><?php _e('Series: ', 'sermon-browser') ?></td>
					<td>
						<select name="widget-sermon[<?php echo $number; ?>][series]" id="widget-sermon-series-<?php echo $number; ?>">
							<option value="0" <?php echo $series ? '' : 'selected="selected"' ?>><?php _e('[All]', 'sermon-browser') ?></option>
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
?>