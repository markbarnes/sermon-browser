<?php
/**
 * Looks up, counts and measures attachments stored as posts.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser\Storage\Posts;

use SermonBrowser\Attachment;
use SermonBrowser\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lookups by meta value and a counter added to in one statement, which WordPress's API can't do

/**
 * Looks up attachments stored as posts: files as media library attachments of the files in the
 * upload folder, URLs and embed code as sb_link posts. Only those with a download count (meta
 * sb_downloads) are the plugin's.
 */
class Attachment_Repository implements \SermonBrowser\Attachment_Repository {

	/**
	 * {@inheritDoc}
	 *
	 * @param string $type    Attachment::FILE or Attachment::URL.
	 * @param string $content The file name or URL.
	 * @return Attachment|null
	 */
	public function find( $type, $content ) {
		global $wpdb;
		$content = (string) $content;
		if ( '' === $content ) {
			return null;
		}
		if ( Attachment::FILE === $type ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT a.ID, d.meta_value AS downloads, du.meta_value AS duration
					FROM {$wpdb->posts} AS a
					JOIN {$wpdb->postmeta} AS f ON f.post_id = a.ID AND f.meta_key = '_wp_attached_file' AND f.meta_value = %s
					JOIN {$wpdb->postmeta} AS d ON d.post_id = a.ID AND d.meta_key = 'sb_downloads'
					LEFT JOIN {$wpdb->postmeta} AS du ON du.post_id = a.ID AND du.meta_key = 'sb_duration'
					WHERE a.post_type = 'attachment' ORDER BY a.ID LIMIT 1",
					self::files_path() . $content
				)
			);
		} elseif ( Attachment::URL === $type ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT a.ID, d.meta_value AS downloads, du.meta_value AS duration
					FROM {$wpdb->posts} AS a
					JOIN {$wpdb->postmeta} AS d ON d.post_id = a.ID AND d.meta_key = 'sb_downloads'
					LEFT JOIN {$wpdb->postmeta} AS du ON du.post_id = a.ID AND du.meta_key = 'sb_duration'
					WHERE a.post_type = %s AND a.post_mime_type = 'text/uri-list' AND a.post_content = %s ORDER BY a.ID LIMIT 1",
					Post_Types::LINK,
					$content
				)
			);
		} else {
			return null;
		}
		return $row ? new Attachment( (int) $row->ID, $type, $content, (int) $row->downloads, (string) $row->duration ) : null;
	}

	/**
	 * {@inheritDoc} Adds one to the count in the database, so downloads at the same moment all count.
	 *
	 * @param Attachment $attachment The attachment.
	 */
	public function record_download( Attachment $attachment ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = CAST(meta_value AS UNSIGNED) + 1 WHERE post_id = %d AND meta_key = 'sb_downloads'", $attachment->id ) );
		wp_cache_delete( $attachment->id, 'post_meta' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Attachment $attachment The attachment.
	 * @param string     $duration   Its length, such as 34:12.
	 */
	public function set_duration( Attachment $attachment, $duration ) {
		update_post_meta( $attachment->id, 'sb_duration', wp_slash( (string) $duration ) );
	}

	/**
	 * Returns where the upload folder is, as a media library attachment's file path (meta
	 * _wp_attached_file) starts: relative to the uploads folder (sermons/ by default), or the whole
	 * path if it is elsewhere.
	 *
	 * @return string With a trailing slash.
	 */
	public static function files_path() {
		$folder  = trailingslashit( wp_normalize_path( SB_ABSPATH . sb_get_option( 'upload_dir' ) ) );
		$uploads = trailingslashit( wp_normalize_path( wp_upload_dir( null, false )['basedir'] ) );
		return 0 === strpos( $folder, $uploads ) ? substr( $folder, strlen( $uploads ) ) : $folder;
	}

	/**
	 * Returns the file name the plugin knows a media library attachment by: its path in the upload folder.
	 *
	 * @param string $file The attachment's file path (meta _wp_attached_file).
	 * @return string|null Null if it isn't in the upload folder.
	 */
	public static function file_name( $file ) {
		$path = self::files_path();
		if ( 0 !== strpos( $file, $path ) || strlen( $file ) === strlen( $path ) ) {
			return null;
		}
		return substr( $file, strlen( $path ) );
	}
}
