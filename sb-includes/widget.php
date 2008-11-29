<?php

//Fix for users of versions prior to 0.24
if (!function_exists('display_sermons')) {
	function display_sermons($params) {
		return sb_display_sermons($params);
	}
}

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
	// Do stuff for this widget, drawing data from $options[$number]
	$sermons = sb_get_sermons(array(
			'preacher' => $preacher,
			'service' => $service,
			'series' => $series
		),
		array(), 1, $limit		
	);
?>
	<ul class="sermon-widget">
	<?php foreach ((array) $sermons as $sermon): ?>
		<li><span class="sermon-title"><a href="<?php sb_print_sermon_link($sermon) ?>"><?php echo stripslashes($sermon->title) ?></a></span>
			<?php 	if ($display_passage): ?><span class="sermon-passage">(<?php $foo = unserialize($sermon->start); $bar = unserialize($sermon->end); echo sb_get_books($foo[0], $bar[0]) ?>)</span><?php endif; 
					if ($display_preacher): ?><span class="sermon-preacher"> <?php _e('by', $sermon_domain) ?> <a href="<?php sb_print_preacher_link($sermon) ?>"><?php echo stripslashes($sermon->preacher) ?></a></span><?php endif; 
					if ($display_date): ?><span class="sermon-date"><?php _e(' on ', $sermon_domain); echo date("j F Y", strtotime($sermon->date)); ?></span><?php endif ?>.
		</li>		
	<?php endforeach ?>
	</ul>
<?php
}


function sb_widget_sermon_init() {
	global $sermon_domain;
	if ( !$options = get_option('sb_widget_sermon') )
		$options = array();

	$widget_ops = array('classname' => 'sermon', 'description' => __('Sermon', $sermon_domain));
	$control_ops = array('width' => 400, 'height' => 350, 'id_base' => 'sermon');
	$name = __('Sermons', $sermon_domain);

	$registered = false;
	foreach ( array_keys($options) as $o ) {
		// Old widgets can have null values for some reason
		if ( !isset($options[$o]['limit']) ) // we used 'something' above in our example.  Replace with with whatever your real data are.
			continue;

		// $id should look like {$id_base}-{$o}
		$id = "sermon-$o"; // Never never never translate an id
		$registered = true;
		wp_register_sidebar_widget( $id, $name, 'sb_widget_sermon', $widget_ops, array( 'number' => $o ) );
		wp_register_widget_control( $id, $name, 'sb_widget_sermon_control', $control_ops, array( 'number' => $o ) );
	}

	// If there are none, we register the widget's existance with a generic template
	if ( !$registered ) {
		wp_register_sidebar_widget( 'sermon-1', $name, 'sb_widget_sermon', $widget_ops, array( 'number' => -1 ) );
		wp_register_widget_control( 'sermon-1', $name, 'sb_widget_sermon_control', $control_ops, array( 'number' => -1 ));
	}
}

function sb_widget_sermon( $args, $widget_args = 1 ) {
	global $sermon_domain;
	extract( $args, EXTR_SKIP );
	if ( is_numeric($widget_args) )
		$widget_args = array( 'number' => $widget_args );
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract( $widget_args, EXTR_SKIP );

	// Data should be stored as array:  array( number => data for that instance of the widget, ... )
	$options = get_option('sb_widget_sermon');
	if ( !isset($options[$number]) )
		return;
		
	extract($options[$number]);
	
	echo $before_widget;
	echo $before_title . $title . $after_title;
	// Do stuff for this widget, drawing data from $options[$number]
	$sermons = sb_get_sermons(array(
			'preacher' => $preacher,
			'service' => $service,
			'series' => $series
		),
		array(), 1, $limit		
	);
?>
	<ul class="sermon-widget">
	<?php foreach ((array) $sermons as $sermon): ?>
		<li><span class="sermon-title"><a href="<?php sb_print_sermon_link($sermon) ?>"><?php echo stripslashes($sermon->title) ?></a></span>
			<?php	if ($book): ?><span class="sermon-passage">(<?php $foo = unserialize($sermon->start); $bar = unserialize($sermon->end); echo sb_get_books($foo[0], $bar[0]) ?>)</span><?php endif;
					if ($preacherz): ?><span class="sermon-preacher"> <?php _e('by', $sermon_domain) ?> <a href="<?php sb_print_preacher_link($sermon) ?>"><?php echo stripslashes($sermon->preacher) ?></a></span><?php endif;
					if ($date): ?><span class="sermon-date"> <?php _e(' on ', $sermon_domain); echo date("j F Y", strtotime($sermon->date)); ?></span><?php endif ?>.
		</li>		
	<?php endforeach ?>
	</ul>
<?php echo $after_widget; ?>
<?php
}
function sb_widget_sermon_control( $widget_args = 1 ) {
	global $wpdb, $sermon_domain;
	global $wp_registered_widgets;
	static $updated = false; // Whether or not we have already updated the data after a POST submit
	
	$dpreachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY id;");	
	$dseries = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY id;");
	$dservices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_services ORDER BY id;");

	if ( is_numeric($widget_args) )
		$widget_args = array( 'number' => $widget_args );
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract( $widget_args, EXTR_SKIP );

	// Data should be stored as array:  array( number => data for that instance of the widget, ... )
	$options = get_option('sb_widget_sermon');
	if ( !is_array($options) )
		$options = array();
		

	// We need to update the data
	if ( !$updated && !empty($_POST['sidebar']) ) {
		// Tells us what sidebar to put the data in
		$sidebar = (string) $_POST['sidebar'];

		$sidebars_widgets = wp_get_sidebars_widgets();
		if ( isset($sidebars_widgets[$sidebar]) )
			$this_sidebar =& $sidebars_widgets[$sidebar];
		else
			$this_sidebar = array();

		foreach ( $this_sidebar as $_widget_id ) {
			// Remove all widgets of this type from the sidebar.  We'll add the new data in a second.  This makes sure we don't get any duplicate data
			// since widget ids aren't necessarily persistent across multiple updates
			if ( 'sb_widget_sermon' == $wp_registered_widgets[$_widget_id]['callback'] && isset($wp_registered_widgets[$_widget_id]['params'][0]['number']) ) {
				$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
				if ( !in_array( "sermon-$widget_number", $_POST['widget-id'] ) ) // the widget has been removed. "many-$widget_number" is "{id_base}-{widget_number}
					unset($options[$widget_number]);
			}
		}
		
		foreach ( (array) $_POST['widget-sermon'] as $widget_number => $widget_sermon_instance ) {
			// compile data from $widget_many_instance
			if ( !isset($widget_sermon_instance['limit']) && isset($options[$widget_number]) ) // user clicked cancel
				continue;
			$limit = wp_specialchars( $widget_sermon_instance['limit'] );
			$preacherz = (int) $widget_sermon_instance['preacherz'];
			$preacher = (int) $widget_sermon_instance['preacher'];
			$service = (int) $widget_sermon_instance['service'];
			$series = (int) $widget_sermon_instance['series'];
			$book = (int) $widget_sermon_instance['book'];
			$title = strip_tags(stripslashes($widget_sermon_instance['title']));
			$date = (int) $widget_sermon_instance['date'];
			$options[$widget_number] = array( 'limit' => $limit, 'preacherz' => $preacherz, 'book' => $book, 'preacher' => $preacher, 'service' => $service, 'series' => $series, 'title' => $title, 'date' => $date);  // Even simple widgets should store stuff in array, rather than in scalar
		}

		update_option('sb_widget_sermon', $options);

		$updated = true; // So that we don't go through this more than once
	}


	// Here we echo out the form
	if ( -1 == $number ) { // We echo out a template for a form which can be converted to a specific form later via JS
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
		$limit = attribute_escape($options[$number]['limit']);
		$preacher = attribute_escape($options[$number]['preacher']);
		$service = attribute_escape($options[$number]['service']);
		$series = attribute_escape($options[$number]['series']);
		$preacherz = (int) $options[$number]['preacherz'];
		$book = (int) $options[$number]['book'];
		$title = attribute_escape($options[$number]['title']);
		$date = (int) $options[$number]['date'];
	}

	// The form has inputs with names like widget-many[$number][something] so that all data for that instance of
	// the widget are stored in one $_POST variable: $_POST['widget-many'][$number]
?>
		<p><?php _e('Title:'); ?> <input class="widefat" id="widget-sermon-title" name="widget-sermon[<?php echo $number; ?>][title]" type="text" value="<?php echo $title; ?>" /></p>
		<p>
			<?php _e('Number of sermons: ', $sermon_domain) ?><input class="widefat" id="widget-sermon-limit-<?php echo $number; ?>" name="widget-sermon[<?php echo $number; ?>][limit]" type="text" value="<?php echo $limit; ?>" /><br />
			
			<input type="checkbox" id="widget-sermon-preacherz-<?php echo $number ?>" name="widget-sermon[<?php echo $number ?>][preacherz]" <?php echo $preacherz ? 'checked=checked' : '' ?> value="1"> <?php _e('Display preacher', $sermon_domain) ?>
			
			<input type="checkbox" id="widget-sermon-book-<?php echo $number ?>" name="widget-sermon[<?php echo $number ?>][book]" <?php echo $book ? 'checked=checked' : '' ?> value="1"> <?php _e('Display bible passage', $sermon_domain) ?><br />
			
			<input type="checkbox" id="widget-sermon-date-<?php echo $number ?>" name="widget-sermon[<?php echo $number ?>][date]" <?php echo $date ? 'checked=checked' : '' ?> value="1"> <?php _e('Display the date', $sermon_domain) ?><br />
			<?php _e('Preacher: ', $sermon_domain) ?><br />
			<select name="widget-sermon[<?php echo $number; ?>][preacher]" id="widget-sermon-preacher-<?php echo $number; ?>">
				<option value="0" <?php echo $preacher ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
				<?php foreach ($dpreachers as $cpreacher): ?>
				<option value="<?php echo $cpreacher->id ?>" <?php echo $preacher == $cpreacher->id ? 'selected="selected"' : '' ?>><?php echo $cpreacher->name ?></option>
				<?php endforeach ?>
			</select><br />
			
			<?php _e('Service: ', $sermon_domain) ?><br />
			<select name="widget-sermon[<?php echo $number; ?>][service]" id="widget-sermon-service-<?php echo $number; ?>">
				<option value="0" <?php echo $service ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
				<?php foreach ($dservices as $cservice): ?>
				<option value="<?php echo $cservice->id ?>" <?php echo $service == $cservice->id ? 'selected="selected"' : '' ?>><?php echo $cservice->name ?></option>
				<?php endforeach ?>
			</select><br />
			
			<?php _e('Series: ', $sermon_domain) ?><br />
			<select name="widget-sermon[<?php echo $number; ?>][series]" id="widget-sermon-series-<?php echo $number; ?>">
				<option value="0" <?php echo $series ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
				<?php foreach ($dseries as $cseries): ?>
				<option value="<?php echo $cseries->id ?>" <?php echo $series == $cseries->id ? 'selected="selected"' : '' ?>><?php echo $cseries->name ?></option>
				<?php endforeach ?>
			</select>
			<input type="hidden" id="widget-sermon-submit-<?php echo $number; ?>" name="widget-sermon[<?php echo $number; ?>][submit]" value="1" />
		</p>
<?php
}
?>