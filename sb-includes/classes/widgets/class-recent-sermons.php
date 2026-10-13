<?php
/**
 * The Sermons widget: a list of recent sermons.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Sermons widget, which can be added more than once, each with its own settings.
 *
 * Its settings form is sb_widget_sermon_control() in admin.php; the settings of each
 * instance are in the sermons_widget_options option, by instance number.
 */
class Recent_Sermons {

	/**
	 * Registers each saved instance of the widget, or one new instance if none is saved.
	 */
	public static function register() {
		$options = sb_get_option( 'sermons_widget_options' );
		if ( ! $options ) {
			$options = array();
		}
		$widget_ops  = array(
			'classname'   => 'sermon',
			'description' => __( 'Display a list of recent sermons.', 'sermon-browser' ),
		);
		$control_ops = array(
			'width'   => 400,
			'height'  => 350,
			'id_base' => 'sermon',
		);
		$name        = __( 'Sermons', 'sermon-browser' );
		$registered  = false;
		foreach ( array_keys( $options ) as $o ) {
			if ( ! isset( $options[ $o ]['limit'] ) ) {
				continue;
			}
			$id         = "sermon-$o";
			$registered = true;
			wp_register_sidebar_widget( $id, $name, array( self::class, 'display' ), $widget_ops, array( 'number' => $o ) );
			wp_register_widget_control( $id, $name, 'sb_widget_sermon_control', $control_ops, array( 'number' => $o ) );
		}
		if ( ! $registered ) {
			wp_register_sidebar_widget( 'sermon-1', $name, array( self::class, 'display' ), $widget_ops, array( 'number' => -1 ) );
			wp_register_widget_control( 'sermon-1', $name, 'sb_widget_sermon_control', $control_ops, array( 'number' => -1 ) );
		}
	}

	/**
	 * Displays an instance of the widget.
	 *
	 * @param array     $args        The widget area's markup (before_widget and so on).
	 * @param array|int $widget_args The instance number, or array( 'number' => instance number ).
	 */
	public static function display( $args, $widget_args = 1 ) {
		if ( is_numeric( $widget_args ) ) {
			$widget_args = array( 'number' => $widget_args );
		}
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		$options     = sb_get_option( 'sermons_widget_options' );
		if ( ! isset( $options[ $widget_args['number'] ] ) ) {
			return;
		}
		$instance = $options[ $widget_args['number'] ];
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
		echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
		$query           = \SermonBrowser\Sermon_Query::from_filters(
			array(
				'preacher' => $instance['preacher'],
				'service'  => $instance['service'],
				'series'   => $instance['series'],
			)
		);
		$query->per_page = max( 1, (int) ( $instance['limit'] ? $instance['limit'] : sb_get_option( 'sermons_per_page' ) ) );
		$sermons         = \SermonBrowser\Storage::sermons()->find( $query )->sermons;
		echo '<ul class="sermon-widget">';
		foreach ( $sermons as $sermon ) {
			echo '<li><span class="sermon-title">';
			echo '<a href="' . esc_url( sb_build_url( array( 'sermon_id' => $sermon->id ), true ) ) . '">' . esc_html( $sermon->title ) . '</a></span>';
			if ( $instance['book'] && $sermon->passages ) {
				echo ' <span class="sermon-passage">(' . wp_kses_post( $sermon->passages[0]->reference( true ) ) . ')</span>';
			}
			if ( $instance['preacherz'] ) {
				echo ' <span class="sermon-preacher">' . esc_html__( 'by', 'sermon-browser' ) . ' <a href="';
				sb_print_preacher_link( $sermon );
				echo '">' . esc_html( $sermon->preacher ? $sermon->preacher->name : '' ) . '</a></span>';
			}
			if ( $instance['date'] ) {
				echo ' <span class="sermon-date">' . esc_html__( ' on ', 'sermon-browser' ) . esc_html( sb_formatted_date( $sermon ) ) . '</span>';
			}
			echo '.</li>';
		}
		echo '</ul>';
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
	}
}
