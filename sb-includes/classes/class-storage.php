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
}
