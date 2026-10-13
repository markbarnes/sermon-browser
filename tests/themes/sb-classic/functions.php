<?php
/**
 * Test site only: modern classic themes let WordPress print the title (title-tag support);
 * older ones call wp_title() themselves. The sb_test_title_tag option chooses which this theme does.
 */

add_action(
	'after_setup_theme',
	function () {
		if ( get_option( 'sb_test_title_tag' ) ) {
			add_theme_support( 'title-tag' );
		}
	}
);
