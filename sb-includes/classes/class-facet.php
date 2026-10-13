<?php
/**
 * One choice in a filter: a preacher, series, service or book, and how many sermons it has.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One choice in a filter: a preacher, series, service or book, and how many sermons it has.
 */
class Facet {

	/**
	 * The preacher's, series' or service's id; 0 for a book, which is chosen by its name.
	 */
	public int $id;

	/**
	 * The name; for a book, its English name.
	 */
	public string $name;

	/**
	 * How many sermons.
	 */
	public int $count;

	/**
	 * @param int    $id    Id, or 0 for a book.
	 * @param string $name  Name.
	 * @param int    $count How many sermons.
	 */
	public function __construct( int $id, string $name, int $count ) {
		$this->id    = $id;
		$this->name  = $name;
		$this->count = $count;
	}
}
