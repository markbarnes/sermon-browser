<?php
/**
 * Serves the files and URLs attached to sermons (?download and ?show).
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- downloads are read-only GET requests

/**
 * Downloads and plays attachments, counting each one.
 *
 * Only attachments recorded in sb_stuff are served, so the site can't be used to read
 * other files, or as an open redirect or proxy.
 */
class Downloads {

	/**
	 * Serves the requested attachment and ends the request, if one is requested.
	 */
	public static function serve() {
		if ( ! isset( $_GET['download'] ) && ! isset( $_GET['show'] ) ) {
			return;
		}
		if ( isset( $_GET['file_name'] ) ) {
			self::serve_file( wp_unslash( $_GET['file_name'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared with the names stored in sb_stuff
		}
		if ( isset( $_GET['url'] ) ) {
			self::serve_url( wp_unslash( $_GET['url'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared with the URLs stored in sb_stuff
		}
	}

	/**
	 * Serves a file from the upload folder: only names listed in the database.
	 *
	 * @param string $requested The file_name parameter, unslashed.
	 */
	private static function serve_file( $requested ) {
		$file_name = self::attachment_name( rawurldecode( $requested ), 'file' );
		if ( null === $file_name || ! sb_is_plain_file_name( $file_name ) ) {
			wp_die( esc_html( rawurldecode( sanitize_text_field( $requested ) ) ) . ' ' . esc_html__( 'not found', 'sermon-browser' ), esc_html__( 'File not found', 'sermon-browser' ), array( 'response' => 404 ) );
		}
		self::count( $file_name );
		if ( isset( $_GET['download'] ) ) {
			// Forces the file to download.
			$file_path = SB_ABSPATH . sb_get_option( 'upload_dir' ) . $file_name;
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="' . self::header_file_name( $file_name ) . '"' );
			header( 'X-Content-Type-Options: nosniff' );
			$filesize = @filesize( $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file is sent without a length
			if ( $filesize ) {
				header( 'Content-Length: ' . $filesize );
			}
			self::output( $file_path );
		} else {
			// Plays the file in the browser.
			wp_redirect( sb_get_option( 'upload_url' ) . rawurlencode( $file_name ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the upload folder, which may be on another host (a CDN)
		}
		die();
	}

	/**
	 * Serves an external URL: only URLs attached to a sermon.
	 *
	 * @param string $requested The url parameter, unslashed.
	 */
	private static function serve_url( $requested ) {
		$stored_url = self::attachment_name( rawurldecode( $requested ), 'url' );
		$url        = is_null( $stored_url ) ? '' : esc_url_raw( wp_specialchars_decode( $stored_url, ENT_QUOTES ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			wp_die( esc_html( rawurldecode( sanitize_text_field( $requested ) ) ) . ' ' . esc_html__( 'not found', 'sermon-browser' ), esc_html__( 'URL not found', 'sermon-browser' ), array( 'response' => 404 ) );
		}
		if ( isset( $_GET['download'] ) ) {
			// Downloads the URL and passes it on as a download.
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$downloaded_file = download_url( $url );
			if ( is_wp_error( $downloaded_file ) ) {
				wp_die( esc_html( $url ) . ' ' . esc_html__( 'not found', 'sermon-browser' ), esc_html__( 'URL not found', 'sermon-browser' ), array( 'response' => 404 ) );
			}
			self::count( $stored_url );
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="' . self::header_file_name( basename( wp_parse_url( $url, PHP_URL_PATH ) ) ) . '"' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Content-Length: ' . filesize( $downloaded_file ) );
			self::output( $downloaded_file );
			wp_delete_file( $downloaded_file );
		} else {
			// Sends the browser to the URL.
			self::count( $stored_url );
			wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- only URLs attached to a sermon (checked above), which are usually on other hosts
		}
		die();
	}

	/**
	 * Returns the name of an attachment exactly as stored in the database, or null if there is none.
	 *
	 * @param string $name Name of a file, or a URL.
	 * @param string $type 'file' or 'url'.
	 * @return string|null
	 */
	private static function attachment_name( $name, $type ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}sb_stuff WHERE name = %s AND type = %s LIMIT 1", $name, $type ) );
	}

	/**
	 * Makes a file name safe to use in a quoted Content-Disposition header.
	 *
	 * @param string $file_name File name.
	 * @return string
	 */
	private static function header_file_name( $file_name ) {
		return str_replace( array( '"', '\\', "\r", "\n" ), '', (string) $file_name );
	}

	/**
	 * Adds one to an attachment's download count, unless the visitor can edit sermons.
	 *
	 * @param string $name Name of the attachment, as stored.
	 */
	private static function count( $name ) {
		if ( ! ( current_user_can( 'edit_posts' ) || current_user_can( 'publish_posts' ) ) ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}sb_stuff SET COUNT=COUNT+1 WHERE name = %s", $name ) );
		}
	}

	/**
	 * Sends a file's contents.
	 *
	 * @param string $filename Path of the file.
	 * @return bool Whether it was sent.
	 */
	private static function output( $filename ) {
		if ( ! is_readable( $filename ) ) {
			return false;
		}
		// Streamed in chunks, since sermon recordings can be larger than PHP's memory limit; WP_Filesystem can only read whole files.
		$handle = fopen( $filename, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return false;
		}
		if ( 0 === ob_get_level() ) {
			ob_start();
		}
		while ( ! feof( $handle ) ) {
			set_time_limit( ini_get( 'max_execution_time' ) ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- restarts the time limit for each chunk of a long download
			$buffer = fread( $handle, 1048576 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the file's contents, sent as a download
			ob_flush();
			flush();
		}
		return fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}
}
