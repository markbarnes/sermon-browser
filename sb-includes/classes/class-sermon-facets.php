<?php
/**
 * What the sermons a query matches have in common, for the filters.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the sermons a query matches have in common: their preachers, series, services, books and dates.
 */
class Sermon_Facets {

	/**
	 * @var Facet[] Most sermons first, then the one with the latest sermon, then by name.
	 */
	public array $preachers;

	/**
	 * @var Facet[] The one with the latest sermon first, then by name.
	 */
	public array $series;

	/**
	 * @var Facet[] Most sermons first, then by name.
	 */
	public array $services;

	/**
	 * @var Facet[] Books with a passage of any of the sermons, in Bible order.
	 */
	public array $books;

	/**
	 * @var \DateTimeImmutable[] Each sermon's date, oldest first, leaving out unknown dates.
	 */
	public array $dates;

	/**
	 * @param Facet[]              $preachers Preachers.
	 * @param Facet[]              $series    Series.
	 * @param Facet[]              $services  Services.
	 * @param Facet[]              $books     Books.
	 * @param \DateTimeImmutable[] $dates     Dates.
	 */
	public function __construct( array $preachers, array $series, array $services, array $books, array $dates ) {
		$this->preachers = $preachers;
		$this->series    = $series;
		$this->services  = $services;
		$this->books     = $books;
		$this->dates     = $dates;
	}
}
