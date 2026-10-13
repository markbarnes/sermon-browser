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
		$request = Sermon_Query::filters_from_request();
		$atts    = shortcode_atts(
			array(
				'filter'     => sb_get_option( 'filter_type' ),
				'filterhide' => sb_get_option( 'filter_hide' ),
				'id'         => isset( $_REQUEST['sermon_id'] ) ? (int) $_REQUEST['sermon_id'] : '',
				'preacher'   => $request['preacher'],
				'series'     => $request['series'],
				'book'       => $request['book'],
				'service'    => $request['service'],
				'date'       => $request['date'],
				'enddate'    => $request['enddate'],
				'tag'        => $request['tag'],
				'title'      => $request['title'],
				'limit'      => '0',
				'dir'        => isset( $_REQUEST['dir'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['dir'] ) ) : '',
			),
			$atts
		);
		if ( $atts['id'] != '' ) {
			if ( strtolower( $atts['id'] ) == 'latest' ) {
				$query           = Sermon_Query::from_filters( array_merge( $atts, array( 'id' => 0 ) ) );
				$query->per_page = 1;
				$latest          = Storage::sermons()->find( $query )->sermons;
				$atts['id']      = $latest ? $latest[0]->id : 0;
			}
			$sermon = sb_find_sermon( (int) $atts['id'] );
			// Sermons without a preacher or a service have never been shown on their own.
			if ( ! $sermon || ! $sermon->preacher || ! $sermon->service ) {
				return '<div class="sermon-browser-results"><span class="error">' . esc_html__( 'No sermons found.', 'sermon-browser' ) . '</span></div>';
			}
			return Template_Renderer::single( Template::parse( sb_get_option( 'single_template' ), Template::SINGLE ), $sermon );
		} else {
			$query = Sermon_Query::from_filters( array_merge( $atts, array( 'id' => 0 ) ) );
			$query->sort_by( isset( $_REQUEST['sortby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['sortby'] ) ) : 'm.datetime', (string) $atts['dir'] );
			$query->page             = isset( $_REQUEST['pagenum'] ) ? max( 1, (int) $_REQUEST['pagenum'] ) : 1;
			$query->per_page         = max( 1, (int) $atts['limit'] ? (int) $atts['limit'] : (int) sb_get_option( 'sermons_per_page' ) );
			$query->with_attachments = (bool) sb_get_option( 'hide_no_attachments' );
			return Template_Renderer::search( Template::parse( sb_get_option( 'search_template' ), Template::SEARCH ), Storage::sermons()->find( $query ), $atts );
		}
	}
}
