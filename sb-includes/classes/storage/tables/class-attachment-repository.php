<?php
/**
 * Looks up, counts and measures attachments in the plugin's own tables.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser\Storage\Tables;

use SermonBrowser\Attachment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for

/**
 * Looks up attachments in wp_sb_stuff.
 *
 * URLs are returned as stored: those saved by old versions with esc_url() contain &#038;.
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
		if ( ! in_array( $type, array( Attachment::FILE, Attachment::URL ), true ) ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, type, name, count, duration FROM {$wpdb->prefix}sb_stuff WHERE type = %s AND name = %s ORDER BY id LIMIT 1", $type, $content ) );
		return $row ? new Attachment( (int) $row->id, $row->type, $row->name, (int) $row->count, (string) $row->duration ) : null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Attachment $attachment The attachment.
	 */
	public function record_download( Attachment $attachment ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}sb_stuff SET count = count + 1 WHERE id = %d", $attachment->id ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Attachment $attachment The attachment.
	 * @param string     $duration   Its length, such as 34:12.
	 */
	public function set_duration( Attachment $attachment, $duration ) {
		global $wpdb;
		// The column holds six characters: 999:59.
		$wpdb->update( "{$wpdb->prefix}sb_stuff", array( 'duration' => substr( (string) $duration, 0, 6 ) ), array( 'id' => $attachment->id ) );
	}
}
