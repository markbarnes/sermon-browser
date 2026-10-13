<?php
/**
 * The [sermons] shortcode (also [sermon]).
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the sermon list and its filters are read-only GET requests

/**
 * Shows a single sermon, or a list of sermons, with the templates set on the Templates page.
 *
 * The templates are compiled to PHP by sb_compile_template() (dictionary.php) when they're saved,
 * and run here with eval(), with $sermon or $sermons, $atts and $record_count in scope.
 */
class Shortcode {

	/**
	 * Returns the shortcode's output.
	 *
	 * Takes the filters from the request (preacher=1 and so on) unless the shortcode sets them.
	 *
	 * @param array|string $atts    The shortcode's attributes.
	 * @param string|null  $content Unused.
	 * @return string
	 */
	public static function render( $atts, $content = null ) {
		global $record_count;
		ob_start();
		$atts = shortcode_atts(
			array(
				'filter'     => sb_get_option( 'filter_type' ),
				'filterhide' => sb_get_option( 'filter_hide' ),
				'id'         => isset( $_REQUEST['sermon_id'] ) ? (int) $_REQUEST['sermon_id'] : '',
				'preacher'   => isset( $_REQUEST['preacher'] ) ? (int) $_REQUEST['preacher'] : '',
				'series'     => isset( $_REQUEST['series'] ) ? (int) $_REQUEST['series'] : '',
				'book'       => isset( $_REQUEST['book'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['book'] ) ) : '',
				'service'    => isset( $_REQUEST['service'] ) ? (int) $_REQUEST['service'] : '',
				'date'       => isset( $_REQUEST['date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date'] ) ) : '',
				'enddate'    => isset( $_REQUEST['enddate'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['enddate'] ) ) : '',
				'tag'        => isset( $_REQUEST['stag'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['stag'] ) ) : '',
				'title'      => isset( $_REQUEST['title'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['title'] ) ) : '',
				'limit'      => '0',
				'dir'        => isset( $_REQUEST['dir'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['dir'] ) ) : '',
			),
			$atts
		);
		if ( $atts['id'] != '' ) {
			if ( strtolower( $atts['id'] ) == 'latest' ) {
				$atts['id'] = '';
				$latest     = Storage::sermons()->find( sb_sermon_query( $atts, array(), 1, 1 ) )->sermons;
				$atts['id'] = $latest ? $latest[0]->id : 0;
			}
			$sermon = sb_get_single_sermon( (int) $atts['id'] );
			if ( $sermon ) {
				eval( '?>' . sb_get_option( 'single_output' ) );
			} else {
				echo '<div class="sermon-browser-results"><span class="error">';
				esc_html_e( 'No sermons found.', 'sermon-browser' );
				echo '</span></div>';
			}
		} else {
			if ( isset( $_REQUEST['sortby'] ) ) {
				$sort_criteria = sanitize_text_field( wp_unslash( $_REQUEST['sortby'] ) );
			} else {
				$sort_criteria = 'm.datetime';
			}
			if ( ! empty( $atts['dir'] ) ) {
				$dir = $atts['dir'];
			} elseif ( $sort_criteria == 'm.datetime' ) {
				$dir = 'desc';
			} else {
				$dir = 'asc';
			}
			$sort_order = array(
				'by'  => $sort_criteria,
				'dir' => $dir,
			);
			if ( isset( $_REQUEST['pagenum'] ) ) {
				$page = (int) $_REQUEST['pagenum'];
			} else {
				$page = 1;
			}
			$hide_empty = sb_get_option( 'hide_no_attachments' );
			$sermons    = sb_get_sermons( $atts, $sort_order, $page, (int) $atts['limit'], $hide_empty );
			$output     = '?>' . sb_get_option( 'search_output' );
			eval( $output );
		}
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}
}
