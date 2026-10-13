<?php
/**
 * Serves the stylesheet edited on the Templates page (sb-style.css).
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's stylesheet.
 */
class Stylesheet {

	/**
	 * Sends the stylesheet, or 304 Not Modified, and ends the request.
	 */
	public static function serve() {
		header( 'Content-Type: text/css' );
		header( 'X-Content-Type-Options: nosniff' );
		$last_modified = sb_get_option( 'style_date_modified' );
		if ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) && strtotime( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) ) >= $last_modified ) {
			if ( 'CGI' === php_sapi_name() ) {
				header( 'Status: 304 Not Modified' );
			} else {
				header( 'HTTP/1.0 304 Not Modified' );
			}
		} else {
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s\G\M\T', $last_modified ), 200 );
		}
		$expires = 60 * 60 * 24 * 7;
		header( 'Cache-Control: max-age=' . $expires );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $expires ) . ' GMT' );
		print( sb_get_option( 'css_style' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the stylesheet admins edit on the Templates page, sent as text/css with nosniff
		die();
	}
}
