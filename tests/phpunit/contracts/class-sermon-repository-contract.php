<?php
/**
 * What every sermon repository must do.
 *
 * @package SermonBrowser
 */

use SermonBrowser\Attachment;
use SermonBrowser\Passage;
use SermonBrowser\Sermon;
use SermonBrowser\Sermon_Query;
use SermonBrowser\Sermon_Repository;
use SermonBrowser\Verse;

/**
 * The contract tests for SermonBrowser\Sermon_Repository. Each storage has a test class that extends
 * this one and says which repository to test and how to store the fixtures (fixtures/sermons.php).
 */
abstract class Sermon_Repository_Contract extends WP_UnitTestCase {

	/**
	 * Returns the repository under test.
	 *
	 * @return Sermon_Repository
	 */
	abstract protected function repository();

	/**
	 * Returns the writer that stores fixtures where the repository reads them.
	 *
	 * @return Fixture_Writer
	 */
	abstract protected function writer();

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'Europe/London' );
		$this->writer()->write( require dirname( __DIR__ ) . '/fixtures/sermons.php' );
	}

	/**
	 * Returns the ids of a query's sermons, in order.
	 *
	 * @param array $properties Sermon_Query properties to set.
	 * @return int[]
	 */
	private function ids( array $properties = array() ) {
		return wp_list_pluck( $this->find( $properties )->sermons, 'id' );
	}

	/**
	 * Runs a query.
	 *
	 * @param array $properties Sermon_Query properties to set.
	 * @return SermonBrowser\Sermon_Page
	 */
	private function find( array $properties = array() ) {
		$query = new Sermon_Query();
		foreach ( $properties as $name => $value ) {
			$query->$name = $value;
		}
		return $this->repository()->find( $query );
	}

	public function test_get_returns_a_sermon_with_everything_attached() {
		$sermon = $this->repository()->get( 1 );

		$this->assertInstanceOf( Sermon::class, $sermon );
		$this->assertSame( 1, $sermon->id );
		$this->assertSame( "Paul's letter: Faith & Works", $sermon->title );
		$this->assertSame( '<p>Justified by grace <em>alone</em>, through faith.</p>', $sermon->description );
		$this->assertSame( '2026-03-01 10:30:00', $sermon->date->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( 'Europe/London', $sermon->date->getTimezone()->getName() );
		$this->assertSame( '', $sermon->time );

		$this->assertSame( array( 1, 'Dylan Jones', '<p>Pastor since 2015.</p>', 'profile.jpg' ), array( $sermon->preacher->id, $sermon->preacher->name, $sermon->preacher->description, $sermon->preacher->image ) );
		$this->assertSame( array( 1, 'Romans' ), array( $sermon->series->id, $sermon->series->name ) );
		$this->assertSame( array( 1, 'Sunday Morning', '10:30' ), array( $sermon->service->id, $sermon->service->name, $sermon->service->time ) );
		$this->assertEquals( array( new Passage( new Verse( 'Romans', 3, 21 ), new Verse( 'Romans', 3, 31 ) ) ), $sermon->passages );
	}

	public function test_get_returns_tags_in_alphabetical_order() {
		$this->assertSame( array( 'faith', 'grace' ), $this->repository()->get( 1 )->tags );
	}

	public function test_get_returns_attachments_newest_first_with_embed_code_as_html() {
		$attachments = $this->repository()->get( 1 )->attachments;

		$this->assertSame( array( Attachment::EMBED, Attachment::URL, Attachment::FILE ), wp_list_pluck( $attachments, 'type' ) );
		$this->assertSame( '<iframe src="https://player.vimeo.com/video/123" allowfullscreen></iframe>', $attachments[0]->content );
		$this->assertSame( 'https://example.org/sermons/faith-and-works.mp3', $attachments[1]->content );
		$this->assertSame( array( '2026-03-01-faith-and-works.mp3', 5, '34:12' ), array( $attachments[2]->content, $attachments[2]->downloads, $attachments[2]->duration ) );
		$this->assertSame( 7, $this->repository()->get( 1 )->downloads() );
		$this->assertCount( 3, array_unique( wp_list_pluck( $attachments, 'id' ) ) );
	}

	public function test_get_returns_text_as_it_was_entered() {
		$sermon = $this->repository()->get( 2 );

		$this->assertSame( 'Gŵyl Dewi — the Lord’s my shepherd…', $sermon->title );
		$this->assertSame( "Psalm 23 for St David's Day.", $sermon->description );
		$this->assertSame( 'Rhiannon Llŷr', $sermon->preacher->name );
		$this->assertSame( 'The Psalms &#8211; songs for every season', $sermon->series->name );
		$this->assertSame( "Aled O'Brien", $this->repository()->get( 5 )->preacher->name );
	}

	public function test_get_returns_passages_in_the_order_entered() {
		$this->assertSame( array( 'Romans 5:1-11', 'Isaiah 53:4-6' ), array_map( function ( $passage ) { return $passage->reference(); }, $this->repository()->get( 3 )->passages ) );
		$this->assertSame( array( 'John 3:16', 'Genesis 1:1-2:3' ), array_map( function ( $passage ) { return $passage->reference(); }, $this->repository()->get( 5 )->passages ) );
	}

	public function test_get_returns_a_sermon_without_series_passages_tags_or_attachments() {
		$sermon = $this->repository()->get( 4 );

		$this->assertNull( $sermon->series );
		$this->assertSame( array(), $sermon->passages );
		$this->assertSame( array(), $sermon->tags );
		$this->assertSame( array(), $sermon->attachments );
		$this->assertSame( 0, $sermon->downloads() );
	}

	public function test_get_returns_a_time_that_differs_from_the_service() {
		$sermon = $this->repository()->get( 4 );

		$this->assertSame( '09:45', $sermon->time );
		$this->assertSame( '9:30', $sermon->service->time );
		$this->assertSame( '2025-12-25 09:45:00', $sermon->date->format( 'Y-m-d H:i:s' ) );
	}

	public function test_get_returns_no_date_when_it_is_unknown() {
		$this->assertNull( $this->repository()->get( 6 )->date );
	}

	public function test_get_returns_null_for_an_unknown_id() {
		$this->assertNull( $this->repository()->get( 99 ) );
		$this->assertNull( $this->repository()->get( 0 ) );
	}

	public function test_find_lists_every_sermon_newest_first_with_unknown_dates_last() {
		$page = $this->find();

		$this->assertSame( array( 5, 3, 2, 1, 4, 6 ), wp_list_pluck( $page->sermons, 'id' ) );
		$this->assertSame( 6, $page->total );
	}

	public function test_find_returns_whole_sermons() {
		$this->assertEquals( $this->repository()->get( 1 ), $this->find( array( 'id' => 1 ) )->sermons[0] );
		$this->assertEquals( $this->repository()->get( 3 ), $this->find( array( 'id' => 3 ) )->sermons[0] );
	}

	public function test_find_returns_the_page_asked_for_and_the_total() {
		$page = $this->find( array( 'per_page' => 2, 'page' => 2 ) );

		$this->assertSame( array( 2, 1 ), wp_list_pluck( $page->sermons, 'id' ) );
		$this->assertSame( 6, $page->total );
		$this->assertSame( array(), $this->ids( array( 'per_page' => 2, 'page' => 4 ) ) );
	}

	public function test_find_filters_by_preacher_series_and_service() {
		$this->assertSame( array( 3, 1, 4 ), $this->ids( array( 'preacher' => 1 ) ) );
		$this->assertSame( array( 3, 1 ), $this->ids( array( 'series' => 1 ) ) );
		$this->assertSame( array( 5, 3, 1, 6 ), $this->ids( array( 'service' => 1 ) ) );
		$this->assertSame( array( 3, 1 ), $this->ids( array( 'preacher' => 1, 'series' => 1 ) ) );
		$this->assertSame( 3, $this->find( array( 'preacher' => 1 ) )->total );
	}

	public function test_find_filters_by_any_passage_in_a_book() {
		$this->assertSame( array( 3, 1 ), $this->ids( array( 'book' => 'Romans' ) ) );
		$this->assertSame( array( 3 ), $this->ids( array( 'book' => 'Isaiah' ) ) );
		$this->assertSame( array( 5 ), $this->ids( array( 'book' => 'Genesis' ) ) );
		$this->assertSame( array(), $this->ids( array( 'book' => 'Jude' ) ) );
	}

	public function test_find_filters_by_part_of_a_tag() {
		$this->assertSame( array( 3, 1 ), $this->ids( array( 'tag' => 'grace' ) ) );
		$this->assertSame( array( 3, 1 ), $this->ids( array( 'tag' => 'gra' ) ) );
		$this->assertSame( array( 5 ), $this->ids( array( 'tag' => 'new birth' ) ) );
	}

	public function test_find_searches_titles_descriptions_and_tags() {
		$this->assertSame( array( 1 ), $this->ids( array( 'text' => "Paul's" ) ) );
		$this->assertSame( array( 2 ), $this->ids( array( 'text' => "David's" ) ) );
		$this->assertSame( array( 1 ), $this->ids( array( 'text' => 'Justified' ) ) );
		$this->assertSame( array( 3, 1 ), $this->ids( array( 'text' => 'grace' ) ) );
		$this->assertSame( array( 2 ), $this->ids( array( 'text' => 'shepherd…' ) ) );
		$this->assertSame( array(), $this->ids( array( 'text' => '%' ) ) );
	}

	public function test_find_filters_by_date() {
		$this->assertSame( array( 5, 3 ), $this->ids( array( 'from' => '2026-03-02' ) ) );
		$this->assertSame( array( 2, 1, 4 ), $this->ids( array( 'to' => '2026-03-08' ) ) );
		$this->assertSame( array( 2, 1 ), $this->ids( array( 'from' => '2026-03-01', 'to' => '2026-03-08' ) ) );
		$this->assertSame( array( 1 ), $this->ids( array( 'from' => '2026-03-01', 'to' => '2026-03-01 10:30:00' ) ) );
	}

	public function test_find_filters_by_id() {
		$this->assertSame( array( 3 ), $this->ids( array( 'id' => 3 ) ) );
		$this->assertSame( array(), $this->ids( array( 'id' => 99 ) ) );
	}

	public function test_find_filters_to_sermons_with_attachments() {
		$this->assertSame( array( 3, 1 ), $this->ids( array( 'with_attachments' => true ) ) );
		$this->assertSame( 2, $this->find( array( 'with_attachments' => true ) )->total );
	}

	public function test_find_sorts_by_date() {
		$this->assertSame( array( 6, 4, 1, 2, 3, 5 ), $this->ids( array( 'ascending' => true ) ) );
	}

	public function test_find_sorts_by_title() {
		$this->assertSame( array( 5, 6, 4, 2, 1, 3 ), $this->ids( array( 'order_by' => Sermon_Query::BY_TITLE, 'ascending' => true ) ) );
		$this->assertSame( array( 3, 1, 2, 4, 6, 5 ), $this->ids( array( 'order_by' => Sermon_Query::BY_TITLE ) ) );
	}

	public function test_find_sorts_by_preacher_with_each_preachers_sermons_newest_first() {
		$this->assertSame( array( 5, 3, 1, 4, 2, 6 ), $this->ids( array( 'order_by' => Sermon_Query::BY_PREACHER, 'ascending' => true ) ) );
		$this->assertSame( array( 2, 6, 3, 1, 4, 5 ), $this->ids( array( 'order_by' => Sermon_Query::BY_PREACHER ) ) );
	}

	public function test_find_sorts_by_first_passage_in_bible_order_with_sermons_without_one_last() {
		$this->assertSame( array( 2, 5, 1, 3, 4, 6 ), $this->ids( array( 'order_by' => Sermon_Query::BY_PASSAGE, 'ascending' => true ) ) );
		$this->assertSame( array( 3, 1, 5, 2, 4, 6 ), $this->ids( array( 'order_by' => Sermon_Query::BY_PASSAGE ) ) );
	}

	public function test_find_sorts_by_passage_within_a_book_listing_each_sermon_once() {
		$this->assertSame( array( 3, 1 ), $this->ids( array( 'book' => 'Romans', 'order_by' => Sermon_Query::BY_PASSAGE ) ) );
		$this->assertSame( array( 3 ), $this->ids( array( 'book' => 'Isaiah', 'order_by' => Sermon_Query::BY_PASSAGE, 'ascending' => true ) ) );
	}

	public function test_next_is_the_first_sermon_on_a_later_day() {
		$this->assertSame( 3, $this->repository()->next( $this->repository()->get( 1 ) )->id );
		$this->assertSame( 3, $this->repository()->next( $this->repository()->get( 2 ) )->id );
		$this->assertSame( 5, $this->repository()->next( $this->repository()->get( 3 ) )->id );
		$this->assertSame( 1, $this->repository()->next( $this->repository()->get( 4 ) )->id );
		$this->assertNull( $this->repository()->next( $this->repository()->get( 5 ) ) );
	}

	public function test_previous_is_the_last_sermon_on_an_earlier_day() {
		$this->assertSame( 2, $this->repository()->previous( $this->repository()->get( 3 ) )->id );
		$this->assertSame( 4, $this->repository()->previous( $this->repository()->get( 1 ) )->id );
		$this->assertSame( 4, $this->repository()->previous( $this->repository()->get( 2 ) )->id );
		$this->assertNull( $this->repository()->previous( $this->repository()->get( 4 ) ) );
	}

	public function test_same_day_lists_the_other_sermons_that_day_in_order() {
		$this->assertSame( array( 2 ), wp_list_pluck( $this->repository()->same_day( $this->repository()->get( 1 ) ), 'id' ) );
		$this->assertSame( array( 1 ), wp_list_pluck( $this->repository()->same_day( $this->repository()->get( 2 ) ), 'id' ) );
		$this->assertSame( array(), $this->repository()->same_day( $this->repository()->get( 3 ) ) );
	}

	public function test_an_undated_sermon_has_no_neighbours() {
		$undated = $this->repository()->get( 6 );

		$this->assertNull( $this->repository()->next( $undated ) );
		$this->assertNull( $this->repository()->previous( $undated ) );
		$this->assertSame( array(), $this->repository()->same_day( $undated ) );
	}
}
