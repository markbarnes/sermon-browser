<?php
/**
 * Stores the contract tests' sermons in one kind of storage.
 *
 * @package SermonBrowser
 */

/**
 * Stores fixtures (tests/phpunit/fixtures/sermons.php) with the ids they give, replacing what was there.
 * Runs inside each test's transaction, so it mustn't create, drop or truncate tables.
 */
interface Fixture_Writer {

	/**
	 * Replaces the stored sermons with these.
	 *
	 * @param array $fixtures As tests/phpunit/fixtures/sermons.php returns.
	 */
	public function write( array $fixtures );
}
