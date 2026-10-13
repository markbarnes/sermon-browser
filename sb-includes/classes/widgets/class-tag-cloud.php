<?php
/**
 * The Sermon Browser tags widget.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A cloud of the tags given to sermons.
 */
class Tag_Cloud {

	/**
	 * Registers the widget.
	 */
	public static function register() {
		// Without a classname, WordPress made one from the name of the function the widget used to call, which themes may style.
		wp_register_sidebar_widget( 'sermon-browser-tags', __( 'Sermon Browser tags', 'sermon-browser' ), array( self::class, 'display' ), array( 'classname' => 'sb_widget_tag_cloud_wrapper' ) );
	}

	/**
	 * Displays the widget.
	 *
	 * @param array $args The widget area's markup (before_widget and so on).
	 */
	public static function display( $args ) {
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
		echo $args['before_title'] . esc_html__( 'Sermon Browser tags', 'sermon-browser' ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
		sb_print_tag_clouds();
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
	}
}
