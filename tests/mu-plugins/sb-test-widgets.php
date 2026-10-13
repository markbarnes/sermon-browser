<?php
/**
 * Test site only: a widget area, so the tests can show the plugin's widgets the way a
 * theme does, through WordPress's widget API.
 *
 * The test site's block theme has no widget areas of its own. The seed puts the plugin's
 * widgets in this one and adds a page (/sermon-widgets/) that shows each of them with
 * the Legacy Widget block, which renders a widget with its widget area's markup.
 */

add_action(
	'widgets_init',
	function () {
		register_sidebar(
			array(
				'id'            => 'sb-tests',
				'name'          => 'Sermon Browser tests',
				'before_widget' => '<section id="%1$s" class="widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			)
		);
	}
);
