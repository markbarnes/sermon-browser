<?php
/**
 * What a migration did, or would do.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a migration did, or in a dry run would do: how many of each kind of record it created,
 * updated and deleted, what it couldn't migrate (problems), and what it changed or noticed (notes).
 */
class Migration_Report {

	const CREATED = 'created';
	const UPDATED = 'updated';
	const DELETED = 'deleted';

	/**
	 * Whether nothing was written.
	 */
	public bool $dry_run;

	/**
	 * For each kind of record (sermons, preachers, ...), how many were created, updated and deleted.
	 *
	 * @var array<string, array<string, int>>
	 */
	public array $counts = array();

	/**
	 * What couldn't be migrated, or will look different, one sentence each.
	 *
	 * @var string[]
	 */
	public array $problems = array();

	/**
	 * What was done that is worth knowing but needs nothing doing, one sentence each.
	 *
	 * @var string[]
	 */
	public array $notes = array();

	/**
	 * @param bool $dry_run Whether nothing is written.
	 */
	public function __construct( bool $dry_run ) {
		$this->dry_run = $dry_run;
	}

	/**
	 * Counts a record created, updated or deleted.
	 *
	 * @param string $kind   Kind of record, such as sermons.
	 * @param string $action One of the constants above.
	 */
	public function count( string $kind, string $action ) {
		if ( ! isset( $this->counts[ $kind ] ) ) {
			$this->counts[ $kind ] = array(
				self::CREATED => 0,
				self::UPDATED => 0,
				self::DELETED => 0,
			);
		}
		++$this->counts[ $kind ][ $action ];
	}
}
