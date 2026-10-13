<?php
/**
 * The Most popular sermons widget.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for

/**
 * The most downloaded sermons, series and preachers, with links to switch between them.
 *
 * Its settings form is sb_widget_popular_control() in admin.php. The [most_popular] template tag
 * shows it too.
 */
class Popular {

	/**
	 * Registers the widget.
	 */
	public static function register() {
		$name        = __( 'Most popular sermons', 'sermon-browser' );
		$description = __( 'Display a list of the most popular sermons, series or preachers.', 'sermon-browser' );
		$widget_ops  = array(
			'classname'   => 'sermon-browser-popular',
			'description' => $description,
		);
		$control_ops = array(
			'width'   => 400,
			'height'  => 350,
			'id_base' => 'sermon-browser-popular',
		);
		wp_register_sidebar_widget( 'sermon-browser-popular', $name, array( self::class, 'display' ), $widget_ops );
		wp_register_widget_control( 'sermon-browser-popular', $name, 'sb_widget_popular_control', $control_ops );
	}

	/**
	 * Displays the widget.
	 *
	 * @param array $args The widget area's markup (before_widget and so on); also suffix, added to its
	 *                    element ids, and options, which replace the saved settings.
	 */
	public static function display( $args ) {
		global $wpdb;
		$suffix  = isset( $args['suffix'] ) ? $args['suffix'] : '_w';
		$options = isset( $args['options'] ) ? $args['options'] : sb_get_option( 'popular_widget_options' );
		if ( ! is_array( $options ) ) {
			$options = array(
				'title'             => '',
				'limit'             => 5,
				'display_sermons'   => true,
				'display_series'    => true,
				'display_preachers' => true,
			);
		}
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
		if ( $options['title'] != '' ) {
			echo $args['before_title'] . esc_html( $options['title'] ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
		}
		$jscript         = '';
		$trigger         = array();
		$output          = array();
		$series_final    = array();
		$preachers_final = array();
		if ( $options['display_sermons'] ) {
			$sermons = $wpdb->get_results(
				"SELECT sermons.id, sermons.title, sum(stuff.count) AS total
									   FROM {$wpdb->prefix}sb_stuff AS stuff
									   JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
									   GROUP BY sermons.id ORDER BY total DESC LIMIT 0, " . (int) $options['limit']
			);
			if ( $sermons ) {
				$output['sermons'] = '<div class="popular-sermons' . $suffix . '"><ul>';
				foreach ( $sermons as $sermon ) {
					$output['sermons'] .= '<li><a href="' . sb_build_url( array( 'sermon_id' => $sermon->id ), true ) . '">' . esc_html( stripslashes( $sermon->title ) ) . '</a></li>';
				}
				$output['sermons'] .= '</ul></div>';
				$trigger[]          = '<a id="popular_sermons_trigger' . $suffix . '" href="#">Sermons</a>';
				$jscript           .= 'jQuery("#popular_sermons_trigger' . $suffix . '").click(function() {
							jQuery(this).attr("style", "font-weight:bold");
							jQuery("#popular_series_trigger' . $suffix . '").removeAttr("style");
							jQuery("#popular_preachers_trigger' . $suffix . '").removeAttr("style");
							jQuery.setSbCookie ("sermons");
							jQuery("#sb_popular_wrapper' . $suffix . '").fadeOut("slow", function() {
								jQuery("#sb_popular_wrapper' . $suffix . '").html(' . wp_json_encode( isset( $output['sermons'] ) ? $output['sermons'] : '' ) . ').fadeIn("slow");
							});
							return false;
						});';
			}
		}

		if ( $options['display_series'] ) {
			$series1 = $wpdb->get_results(
				"SELECT series.id, series.name, avg(stuff.count) AS average
									   FROM {$wpdb->prefix}sb_stuff AS stuff
									   JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
									   JOIN {$wpdb->prefix}sb_series AS series ON sermons.series_id = series.id
									   GROUP BY series.id ORDER BY average DESC"
			);
			$series2 = $wpdb->get_results(
				"SELECT series.id, sum(stuff.count) AS total
									   FROM {$wpdb->prefix}sb_stuff AS stuff
									   JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
									   JOIN {$wpdb->prefix}sb_series AS series ON sermons.series_id = series.id
									   GROUP BY series.id ORDER BY total DESC"
			);
			if ( $series1 ) {
				$i = 1;
				foreach ( $series1 as $series ) {
					$series_final[ $series->id ]       = new \stdClass();
					$series_final[ $series->id ]->name = $series->name;
					$series_final[ $series->id ]->rank = $i;
					$series_final[ $series->id ]->id   = $series->id;
					++$i;
				}
				$i = 1;
				foreach ( $series2 as $series ) {
					if ( isset( $series_final[ $series->id ] ) ) {
						$series_final[ $series->id ]->rank += $i;
					}
					++$i;
				}
				usort( $series_final, array( self::class, 'compare_rank' ) );
				$series_final     = array_slice( $series_final, 0, $options['limit'] );
				$output['series'] = '<div class="popular-series' . $suffix . '"><ul>';
				foreach ( $series_final as $series ) {
					$output['series'] .= '<li><a href="' . sb_build_url( array( 'series' => $series->id ), true ) . '">' . esc_html( stripslashes( $series->name ) ) . '</a></li>';
				}
				$output['series'] .= '</ul></div>';
			}
			$trigger[] = '<a id="popular_series_trigger' . $suffix . '" href="#">Series</a>';
			$jscript  .= 'jQuery("#popular_series_trigger' . $suffix . '").click(function() {
							jQuery(this).attr("style", "font-weight:bold");
							jQuery("#popular_sermons_trigger' . $suffix . '").removeAttr("style");
							jQuery("#popular_preachers_trigger' . $suffix . '").removeAttr("style");
							jQuery.setSbCookie ("series");
							jQuery("#sb_popular_wrapper' . $suffix . '").fadeOut("slow", function() {
								jQuery("#sb_popular_wrapper' . $suffix . '").html(' . wp_json_encode( isset( $output['series'] ) ? $output['series'] : '' ) . ').fadeIn("slow");
							});
							return false;
						});';
		}

		if ( $options['display_preachers'] ) {
			$preachers1 = $wpdb->get_results(
				"SELECT preachers.id, preachers.name, avg(stuff.count) AS average
										  FROM {$wpdb->prefix}sb_stuff AS stuff
										  JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
										  JOIN {$wpdb->prefix}sb_preachers AS preachers ON sermons.preacher_id = preachers.id
										  GROUP BY preachers.id
										  ORDER BY average DESC"
			);
			$preachers2 = $wpdb->get_results(
				"SELECT preachers.id, sum(stuff.count) AS total
										  FROM {$wpdb->prefix}sb_stuff AS stuff
										  JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
										  JOIN {$wpdb->prefix}sb_preachers AS preachers ON sermons.preacher_id = preachers.id
										  GROUP BY preachers.id
										  ORDER BY total DESC"
			);
			if ( $preachers1 ) {
				$i = 1;
				foreach ( $preachers1 as $preacher ) {
					$preachers_final[ $preacher->id ]       = new \stdClass();
					$preachers_final[ $preacher->id ]->name = $preacher->name;
					$preachers_final[ $preacher->id ]->rank = $i;
					$preachers_final[ $preacher->id ]->id   = $preacher->id;
					++$i;
				}
				$i = 1;
				foreach ( $preachers2 as $preacher ) {
					if ( isset( $preachers_final[ $preacher->id ] ) ) {
						$preachers_final[ $preacher->id ]->rank += $i;
					}
					++$i;
				}
				usort( $preachers_final, array( self::class, 'compare_rank' ) );
				$preachers_final     = array_slice( $preachers_final, 0, $options['limit'] );
				$output['preachers'] = '<div class="popular-preachers' . $suffix . '"><ul>';
				foreach ( $preachers_final as $preacher ) {
					$output['preachers'] .= '<li><a href="' . sb_build_url( array( 'preacher' => $preacher->id ), true ) . '">' . esc_html( stripslashes( $preacher->name ) ) . '</a></li>';
				}
				$output['preachers'] .= '</ul></div>';
				$trigger[]            = '<a id="popular_preachers_trigger' . $suffix . '" href="#">Preachers</a>';
				$jscript             .= 'jQuery("#popular_preachers_trigger' . $suffix . '").click(function() {
								jQuery(this).attr("style", "font-weight:bold");
								jQuery("#popular_series_trigger' . $suffix . '").removeAttr("style");
								jQuery("#popular_sermons_trigger' . $suffix . '").removeAttr("style");
								jQuery.setSbCookie("preachers");
								jQuery("#sb_popular_wrapper' . $suffix . '").fadeOut("slow", function() {
									jQuery("#sb_popular_wrapper' . $suffix . '").html(' . wp_json_encode( isset( $output['preachers'] ) ? $output['preachers'] : '' ) . ').fadeIn("slow");
								});
								return false;
							 });';
			}
		}

		$jscript .= 'if (jQuery.getSbCookie() == "preachers") { jQuery("#popular_preachers_trigger' . $suffix . '").attr("style", "font-weight:bold"); jQuery("#sb_popular_wrapper' . $suffix . '").html(' . wp_json_encode( isset( $output['preachers'] ) ? $output['preachers'] : '' ) . ')};';
		$jscript .= 'if (jQuery.getSbCookie() == "series") { jQuery("#popular_series_trigger' . $suffix . '").attr("style", "font-weight:bold"); jQuery("#sb_popular_wrapper' . $suffix . '").html(' . wp_json_encode( isset( $output['series'] ) ? $output['series'] : '' ) . ')};';
		$jscript .= 'if (jQuery.getSbCookie() == "sermons") { jQuery("#popular_sermons_trigger' . $suffix . '").attr("style", "font-weight:bold"); jQuery("#sb_popular_wrapper' . $suffix . '").html(' . wp_json_encode( isset( $output['sermons'] ) ? $output['sermons'] : '' ) . ')};';
		echo '<p>' . wp_kses_post( implode( ' | ', $trigger ) ) . '</p>';
		echo '<div id="sb_popular_wrapper' . esc_attr( $suffix ) . '">' . wp_kses_post( current( $output ) ) . '</div>';
		echo '<script type="text/javascript">jQuery.setSbCookie = function (value) {
											document.cookie = "sb_popular="+encodeURIComponent(value);
										 };</script>';
		echo '<script type="text/javascript">jQuery.getSbCookie = function () {
											var cookieValue = null;
											if (document.cookie && document.cookie != "") {
												var cookies = document.cookie.split(";");
												for (var i = 0; i < cookies.length; i++) {
													var cookie = jQuery.trim(cookies[i]);
													var name = "sb_popular";
													if (cookie.substring(0, name.length + 1) == (name + "=")) {
														cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
														break;
													}
												}
											}
										 return cookieValue;
										 }</script>';
		echo '<script type="text/javascript">jQuery(document).ready(function() {' . $jscript . '});</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JavaScript built above, with its HTML encoded by wp_json_encode()
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
	}

	/**
	 * Compares two series or preachers by rank, for usort().
	 *
	 * @param object $a A series or preacher.
	 * @param object $b A series or preacher.
	 * @return int
	 */
	private static function compare_rank( $a, $b ) {
		if ( $a->rank == $b->rank ) {
			return 0;
		}
		return ( $a->rank < $b->rank ) ? -1 : 1;
	}
}
