<?php
/**
 * Stores the contract tests' sermons in one kind of storage.
 *
 * @package SermonBrowser
 */

/**
 * Stores fixtures (tests/phpunit/fixtures/sermons.php), replacing what was there, and says which id
 * each was stored with: a storage may not be able to keep the fixtures' ids (WordPress terms share
 * one sequence of ids, so preacher 1 and series 1 can't both be term 1).
 * Runs inside each test's transaction, so it mustn't create, drop or truncate tables.
 */
interface Fixture_Writer {

	/**
	 * Replaces the stored sermons with these.
	 *
	 * @param array $fixtures As tests/phpunit/fixtures/sermons.php returns.
	 * @return array[] For 'sermons', 'preachers', 'series' and 'services', each fixture id => the id stored.
	 */
	public function write( array $fixtures );
}
