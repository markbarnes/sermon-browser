<?php
/**
 * A series of sermons.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A series of sermons.
 */
class Series {

	public int $id;

	public string $name;

	/**
	 * @param int    $id   Id.
	 * @param string $name Name.
	 */
	public function __construct( int $id, string $name ) {
		$this->id   = $id;
		$this->name = $name;
	}
}
