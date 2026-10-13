<?php
/**
 * The attachment repository that reads the plugin's own tables.
 *
 * @package SermonBrowser
 */

/**
 * Runs the attachment repository contract against the tables.
 */
class Tables_Attachment_Repository_Test extends Attachment_Repository_Contract {

	protected function repository() {
		return new SermonBrowser\Storage\Tables\Attachment_Repository();
	}

	protected function sermons() {
		return new SermonBrowser\Storage\Tables\Sermon_Repository();
	}

	protected function writer() {
		return new Tables_Fixture_Writer();
	}
}
