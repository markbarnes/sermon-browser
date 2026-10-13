<?php
/**
 * Where sermons come from.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where sermons come from. Every storage implements this, and passes the same contract tests
 * (tests/phpunit/contracts/).
 */
interface Sermon_Repository {

	/**
	 * Returns one page of the sermons a query matches.
	 *
	 * @param Sermon_Query $query Filters, order and page.
	 * @return Sermon_Page
	 */
	public function find( Sermon_Query $query );

	/**
	 * Returns a sermon, or null if there's none with that id.
	 *
	 * @param int $id Id.
	 * @return Sermon|null
	 */
	public function get( $id );
}
