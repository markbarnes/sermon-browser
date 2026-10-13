<?php
/**
 * Gives the rest of the plugin the repositories for the storage in use.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gives the rest of the plugin the repositories for the storage in use: the plugin's own tables, for now.
 */
class Storage {

	private static ?Sermon_Repository $sermons = null;

	private static ?Attachment_Repository $attachments = null;

	/**
	 * Returns the sermon repository.
	 *
	 * @return Sermon_Repository
	 */
	public static function sermons() {
		if ( null === self::$sermons ) {
			self::$sermons = new Storage\Tables\Sermon_Repository();
		}
		return self::$sermons;
	}

	/**
	 * Returns the attachment repository.
	 *
	 * @return Attachment_Repository
	 */
	public static function attachments() {
		if ( null === self::$attachments ) {
			self::$attachments = new Storage\Tables\Attachment_Repository();
		}
		return self::$attachments;
	}
}
