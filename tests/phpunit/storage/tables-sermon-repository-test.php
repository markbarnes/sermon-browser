<?php
/**
 * The sermon repository that reads the plugin's own tables.
 *
 * @package SermonBrowser
 */

/**
 * Runs the sermon repository contract against the tables.
 */
class Tables_Sermon_Repository_Test extends Sermon_Repository_Contract {

	protected function repository() {
		return new SermonBrowser\Storage\Tables\Sermon_Repository();
	}

	protected function writer() {
		return new Tables_Fixture_Writer();
	}
}
