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
			$sermon = sb_find_sermon( (int) $atts['id'] );
			// Sermons without a preacher or a service have never been shown on their own.
			if ( ! $sermon || ! $sermon->preacher || ! $sermon->service ) {
				return '<div class="sermon-browser-results"><span class="error">' . esc_html__( 'No sermons found.', 'sermon-browser' ) . '</span></div>';
			}
			return Template_Renderer::single( Template::parse( sb_get_option( 'single_template' ), Template::SINGLE ), $sermon );
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
			$limit   = (int) $atts['limit'] ? (int) $atts['limit'] : sb_get_option( 'sermons_per_page' );
			$sermons = Storage::sermons()->find( sb_sermon_query( $atts, $sort_order, $page, $limit, sb_get_option( 'hide_no_attachments' ) ) );
			return Template_Renderer::search( Template::parse( sb_get_option( 'search_template' ), Template::SEARCH ), $sermons, $atts );
		}
	}
}
