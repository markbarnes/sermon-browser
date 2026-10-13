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

	const GROUP_BY_SERIES   = 'series';
	const GROUP_BY_PREACHER = 'preacher';

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

	/**
	 * Returns the first sermon preached on a later day, or null. Sermons with unknown dates have none.
	 *
	 * @param Sermon $sermon The sermon.
	 * @return Sermon|null
	 */
	public function next( Sermon $sermon );

	/**
	 * Returns the last sermon preached on an earlier day, or null. Sermons with unknown dates have none.
	 *
	 * @param Sermon $sermon The sermon.
	 * @return Sermon|null
	 */
	public function previous( Sermon $sermon );

	/**
	 * Returns the other sermons preached on the same day, in the order they were preached.
	 *
	 * @param Sermon $sermon The sermon.
	 * @return Sermon[]
	 */
	public function same_day( Sermon $sermon );

	/**
	 * Returns what the sermons a query matches have in common, on all its pages, for the filters.
	 *
	 * @param Sermon_Query $query Filters (its order and page don't matter).
	 * @return Sermon_Facets
	 */
	public function facets( Sermon_Query $query );

	/**
	 * Returns each tag with the number of sermons that have it: most first, then alphabetically.
	 *
	 * @return Facet[] With id 0: tags are chosen by name.
	 */
	public function tag_counts();

	/**
	 * Returns the sermons with attachments, most downloaded first (ties newest first).
	 *
	 * @param int $limit How many.
	 * @return Sermon[]
	 */
	public function most_downloaded( $limit );

	/**
	 * Returns the downloads of each series' or preacher's attachments: those with attachments only,
	 * the one with the latest sermon first, then by name.
	 *
	 * @param string $by GROUP_BY_SERIES or GROUP_BY_PREACHER.
	 * @return Download_Count[]
	 */
	public function download_counts( $by );
}
