<?php
/**
 * A Bible passage a sermon is on.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A Bible passage a sermon is on, from one verse to another.
 */
class Passage {

	public Verse $start;

	public Verse $end;

	/**
	 * @param Verse $start First verse.
	 * @param Verse $end   Last verse.
	 */
	public function __construct( Verse $start, Verse $end ) {
		$this->start = $start;
		$this->end   = $end;
	}

	/**
	 * Returns the reference, such as Romans 3:21-31, with the book names translated.
	 *
	 * @param bool $add_link Whether to link the book names to the sermons on that book.
	 * @return string HTML.
	 */
	public function reference( $add_link = false ) {
		return Bible::reference( $this->start->to_array(), $this->end->to_array(), $add_link );
	}
}
