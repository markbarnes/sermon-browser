<?php
/**
 * A service, such as Sunday Morning.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A service, such as Sunday Morning, with the time its sermons usually start.
 */
class Service {

	public int $id;

	public string $name;

	/**
	 * The usual start time, as entered (such as 10:30 or 9:30), or ''.
	 */
	public string $time;

	/**
	 * @param int    $id   Id.
	 * @param string $name Name.
	 * @param string $time Usual start time, or ''.
	 */
	public function __construct( int $id, string $name, string $time = '' ) {
		$this->id   = $id;
		$this->name = $name;
		$this->time = $time;
	}
}
