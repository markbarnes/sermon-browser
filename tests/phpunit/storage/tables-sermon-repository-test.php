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

	/**
	 * Only the tables keep each sermon's first passage twice (serialized, and in sb_books_sermons),
	 * so only they can disagree. The old list query hid duplicates with DISTINCT.
	 */
	public function test_sorting_by_passage_lists_each_sermon_once_if_its_first_passage_is_stored_twice() {
		global $wpdb;
		$wpdb->insert(
			"{$wpdb->prefix}sb_books_sermons",
			array(
				'book_name' => 'Acts',
				'chapter'   => 1,
				'verse'     => 1,
				'order'     => 0,
				'type'      => 'start',
				'sermon_id' => 3,
			)
		);
		$query           = new SermonBrowser\Sermon_Query();
		$query->order_by = SermonBrowser\Sermon_Query::BY_PASSAGE;

		$this->assertSame( array( 3, 1, 5, 2, 4, 6 ), wp_list_pluck( $this->repository()->find( $query )->sermons, 'id' ) );
	}
}
