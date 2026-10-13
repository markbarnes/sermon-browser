<?php
/**
 * What the plugin adds to the page head.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WordPress's posts table, searched for the shortcode
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the sermon list's filters are read-only GET requests

/**
 * Adds the podcast links, stylesheet and scripts to the head of pages with posts.
 */
class Page_Head {

	/**
	 * Prints the podcast links and enqueues the stylesheet and scripts (wp_head action).
	 */
	public static function add() {
		global $post, $wpdb;
		if ( isset( $post->ID ) && $post->ID != '' ) {
			echo '<!-- Added by SermonBrowser (version ' . esc_html( SB_CURRENT_VERSION ) . ") -->\r";
			echo '<link rel="alternate" type="application/rss+xml" title="' . esc_attr__( 'Sermon podcast', 'sermon-browser' ) . '" href="' . esc_url( sb_get_option( 'podcast_url' ) ) . "\" />\r";
			wp_enqueue_style( 'sb_style' );
			$pageid = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s AND (post_status = 'publish' OR post_status = 'private') AND ID = %d AND post_date < NOW();", '%' . $wpdb->esc_like( '[sermons' ) . '%', $post->ID ) );
			if ( $pageid !== null ) {
				if ( isset( $_REQUEST['title'] ) || isset( $_REQUEST['preacher'] ) || isset( $_REQUEST['date'] ) || isset( $_REQUEST['enddate'] ) || isset( $_REQUEST['series'] ) || isset( $_REQUEST['service'] ) || isset( $_REQUEST['book'] ) || isset( $_REQUEST['stag'] ) ) {
					echo '<link rel="alternate" type="application/rss+xml" title="' . esc_attr__( 'Custom sermon podcast', 'sermon-browser' ) . '" href="' . esc_url( sb_podcast_url() ) . "\" />\r";
				}
				wp_enqueue_script( 'jquery' );
			} else {
				if ( is_active_widget( false, 'sermon-browser-popular' ) ) {
					wp_enqueue_script( 'jquery' );
				}
			}
		}
	}
}
