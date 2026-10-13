<?php
/**
 * One page of a list of sermons.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One page of a list of sermons, and how many sermons the whole list has.
 */
class Sermon_Page {

	/**
	 * @var Sermon[]
	 */
	public array $sermons;

	/**
	 * How many sermons match, on all pages.
	 */
	public int $total;

	/**
	 * @param Sermon[] $sermons The page's sermons.
	 * @param int      $total   How many sermons match.
	 */
	public function __construct( array $sermons, int $total ) {
		$this->sermons = $sermons;
		$this->total   = $total;
	}
}
