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

	/**
	 * For 'sermons', 'preachers', 'series' and 'services', each fixture id => the id it was stored with.
	 *
	 * @var array[]
	 */
	private $stored;

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'Europe/London' );
		$this->write( require dirname( __DIR__ ) . '/fixtures/sermons.php' );
	}

	/**
	 * Stores fixtures, replacing what was there.
	 *
	 * @param array $fixtures As fixtures/sermons.php returns.
	 */
	private function write( array $fixtures ) {
		$this->stored = $this->writer()->write( $fixtures );
	}

	/**
	 * Returns the id a fixture was stored with. 0 stays 0; an id no fixture has becomes one no record has.
	 *
	 * @param string $kind 'sermons', 'preachers', 'series' or 'services'.
	 * @param int    $id   The fixture's id.
	 * @return int
	 */
	private function stored( $kind, $id ) {
		if ( 0 === $id ) {
			return 0;
		}
		return $this->stored[ $kind ][ $id ] ?? max( $this->stored[ $kind ] ) + $id;
	}

	/**
	 * Returns the fixture id of a stored record, or null if it isn't one of the fixtures.
	 *
	 * @param string   $kind 'sermons', 'preachers', 'series' or 'services'.
	 * @param int|null $id   The stored id.
	 * @return int|null
	 */
	private function fixture( $kind, $id ) {
		$fixture = array_search( $id, $this->stored[ $kind ], true );
		return false === $fixture ? null : $fixture;
	}

	/**
	 * Returns a fixture sermon from the repository.
	 *
	 * @param int $id The fixture's id.
	 * @return Sermon|null
	 */
	private function sermon( $id ) {
		return $this->repository()->get( $this->stored( 'sermons', $id ) );
	}

	/**
	 * Returns the fixture id of a sermon, or null for no sermon.
	 *
	 * @param Sermon|null $sermon A sermon from the repository.
	 * @return int|null
	 */
	private function fixture_id( $sermon ) {
		return null === $sermon ? null : $this->fixture( 'sermons', $sermon->id );
	}

	/**
	 * Returns the fixture ids of a query's sermons, in order.
	 *
	 * @param array $properties Sermon_Query properties to set, with fixture ids.
	 * @return int[]
	 */
	private function ids( array $properties = array() ) {
		return array_map( array( $this, 'fixture_id' ), $this->find( $properties )->sermons );
	}

	/**
	 * Returns a query, with fixture ids in its properties replaced by the stored ones.
	 *
	 * @param array $properties Sermon_Query properties to set, with fixture ids.
	 * @return Sermon_Query
	 */
	private function query( array $properties ) {
		$kinds = array(
			'id'       => 'sermons',
			'preacher' => 'preachers',
			'series'   => 'series',
			'service'  => 'services',
		);
		$query = new Sermon_Query();
		foreach ( $properties as $name => $value ) {
			$query->$name = isset( $kinds[ $name ] ) ? $this->stored( $kinds[ $name ], $value ) : $value;
		}
		return $query;
	}

	/**
	 * Runs a query.
	 *
	 * @param array $properties Sermon_Query properties to set, with fixture ids.
	 * @return SermonBrowser\Sermon_Page
	 */
	private function find( array $properties = array() ) {
		return $this->repository()->find( $this->query( $properties ) );
	}

	public function test_get_returns_a_sermon_with_everything_attached() {
		$sermon = $this->sermon( 1 );

		$this->assertInstanceOf( Sermon::class, $sermon );
		$this->assertSame( 1, $this->fixture_id( $sermon ) );
		$this->assertSame( "Paul's letter: Faith & Works", $sermon->title );
		$this->assertSame( '<p>Justified by grace <em>alone</em>, through faith.</p>', $sermon->description );
		$this->assertSame( '2026-03-01 10:30:00', $sermon->date->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( 'Europe/London', $sermon->date->getTimezone()->getName() );
		$this->assertSame( '', $sermon->time );

		$this->assertSame( array( 1, 'Dylan Jones', 'Pastor <em>since</em> 2015.', 'profile.jpg' ), array( $this->fixture( 'preachers', $sermon->preacher->id ), $sermon->preacher->name, $sermon->preacher->description, $sermon->preacher->image ) );
		$this->assertSame( array( 1, 'Romans' ), array( $this->fixture( 'series', $sermon->series->id ), $sermon->series->name ) );
		$this->assertSame( array( 1, 'Sunday Morning', '10:30' ), array( $this->fixture( 'services', $sermon->service->id ), $sermon->service->name, $sermon->service->time ) );
		$this->assertEquals( array( new Passage( new Verse( 'Romans', 3, 21 ), new Verse( 'Romans', 3, 31 ) ) ), $sermon->passages );
	}

	public function test_get_returns_tags_in_alphabetical_order() {
		$this->assertSame( array( 'faith', 'grace' ), $this->sermon( 1 )->tags );
	}

	public function test_get_returns_attachments_newest_first_with_embed_code_as_html() {
		$attachments = $this->sermon( 1 )->attachments;

		$this->assertSame( array( Attachment::EMBED, Attachment::URL, Attachment::FILE ), wp_list_pluck( $attachments, 'type' ) );
		$this->assertSame( '<iframe src="https://player.vimeo.com/video/123" allowfullscreen></iframe>', $attachments[0]->content );
		$this->assertSame( 'https://example.org/sermons/faith-and-works.mp3', $attachments[1]->content );
		$this->assertSame( array( '2026-03-01-faith-and-works.mp3', 5, '34:12' ), array( $attachments[2]->content, $attachments[2]->downloads, $attachments[2]->duration ) );
		$this->assertSame( 7, $this->sermon( 1 )->downloads() );
		$this->assertCount( 3, array_unique( wp_list_pluck( $attachments, 'id' ) ) );
	}

	public function test_get_returns_text_as_it_was_entered() {
		$sermon = $this->sermon( 2 );

		$this->assertSame( 'Gŵyl Dewi — the Lord’s my shepherd…', $sermon->title );
		$this->assertSame( "Psalm 23 for St David's Day.", $sermon->description );
		$this->assertSame( 'Rhiannon Llŷr', $sermon->preacher->name );
		$this->assertSame( 'The Psalms &#8211; songs for every season', $sermon->series->name );
		$this->assertSame( "Aled O'Brien", $this->sermon( 5 )->preacher->name );
	}

	public function test_get_returns_passages_in_the_order_entered() {
		$this->assertSame( array( 'Romans 5:1-11', 'Isaiah 53:4-6' ), array_map( function ( $passage ) { return $passage->reference(); }, $this->sermon( 3 )->passages ) );
		$this->assertSame( array( 'John 3:16', 'Genesis 1:1-2:3' ), array_map( function ( $passage ) { return $passage->reference(); }, $this->sermon( 5 )->passages ) );
	}

	public function test_get_returns_a_sermon_without_series_passages_tags_or_attachments() {
		$sermon = $this->sermon( 4 );

		$this->assertNull( $sermon->series );
		$this->assertSame( array(), $sermon->passages );
		$this->assertSame( array(), $sermon->tags );
		$this->assertSame( array(), $sermon->attachments );
		$this->assertSame( 0, $sermon->downloads() );
	}

	public function test_get_returns_a_time_that_differs_from_the_service() {
		$sermon = $this->sermon( 4 );

		$this->assertSame( '09:45', $sermon->time );
		$this->assertSame( '9:30', $sermon->service->time );
		$this->assertSame( '2025-12-25 09:45:00', $sermon->date->format( 'Y-m-d H:i:s' ) );
	}

	public function test_get_returns_no_date_when_it_is_unknown() {
		$this->assertNull( $this->sermon( 6 )->date );
	}

	public function test_get_returns_null_for_an_unknown_id() {
		$this->assertNull( $this->sermon( 99 ) );
		$this->assertNull( $this->sermon( 0 ) );
	}

	public function test_find_lists_every_sermon_newest_first_with_unknown_dates_last() {
		$page = $this->find();

		$this->assertSame( array( 5, 3, 2, 1, 4, 6 ), array_map( array( $this, 'fixture_id' ), $page->sermons ) );
		$this->assertSame( 6, $page->total );
	}

	public function test_find_returns_whole_sermons() {
		$this->assertEquals( $this->sermon( 1 ), $this->find( array( 'id' => 1 ) )->sermons[0] );
		$this->assertEquals( $this->sermon( 3 ), $this->find( array( 'id' => 3 ) )->sermons[0] );
	}

	public function test_find_returns_the_page_asked_for_and_the_total() {
		$page = $this->find( array( 'per_page' => 2, 'page' => 2 ) );

		$this->assertSame( array( 2, 1 ), array_map( array( $this, 'fixture_id' ), $page->sermons ) );
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
		$this->assertSame( 3, $this->fixture_id( $this->repository()->next( $this->sermon( 1 ) ) ) );
		$this->assertSame( 3, $this->fixture_id( $this->repository()->next( $this->sermon( 2 ) ) ) );
		$this->assertSame( 5, $this->fixture_id( $this->repository()->next( $this->sermon( 3 ) ) ) );
		$this->assertSame( 1, $this->fixture_id( $this->repository()->next( $this->sermon( 4 ) ) ) );
		$this->assertNull( $this->repository()->next( $this->sermon( 5 ) ) );
	}

	public function test_previous_is_the_last_sermon_on_an_earlier_day() {
		$this->assertSame( 2, $this->fixture_id( $this->repository()->previous( $this->sermon( 3 ) ) ) );
		$this->assertSame( 4, $this->fixture_id( $this->repository()->previous( $this->sermon( 1 ) ) ) );
		$this->assertSame( 4, $this->fixture_id( $this->repository()->previous( $this->sermon( 2 ) ) ) );
		$this->assertNull( $this->repository()->previous( $this->sermon( 4 ) ) );
	}

	public function test_same_day_lists_the_other_sermons_that_day_in_order() {
		$this->assertSame( array( 2 ), array_map( array( $this, 'fixture_id' ), $this->repository()->same_day( $this->sermon( 1 ) ) ) );
		$this->assertSame( array( 1 ), array_map( array( $this, 'fixture_id' ), $this->repository()->same_day( $this->sermon( 2 ) ) ) );
		$this->assertSame( array(), $this->repository()->same_day( $this->sermon( 3 ) ) );
	}

	public function test_an_undated_sermon_has_no_neighbours() {
		$undated = $this->sermon( 6 );

		$this->assertNull( $this->repository()->next( $undated ) );
		$this->assertNull( $this->repository()->previous( $undated ) );
		$this->assertSame( array(), $this->repository()->same_day( $undated ) );
	}

	/**
	 * Returns a list of facets as array( id, name, count ) for each, with fixture ids.
	 *
	 * @param SermonBrowser\Facet[] $facets Facets.
	 * @param string|null          $kind   'preachers', 'series' or 'services'; null for books and tags (id 0).
	 * @return array[]
	 */
	private function facet_list( array $facets, $kind = null ) {
		return array_map(
			function ( $facet ) use ( $kind ) {
				return array( null === $kind ? $facet->id : $this->fixture( $kind, $facet->id ), $facet->name, $facet->count );
			},
			$facets
		);
	}

	/**
	 * Returns facets for a query.
	 *
	 * @param array $properties Sermon_Query properties to set, with fixture ids.
	 * @return SermonBrowser\Sermon_Facets
	 */
	private function facets( array $properties = array() ) {
		return $this->repository()->facets( $this->query( $properties ) );
	}

	public function test_facets_count_every_sermon_by_preacher_most_first_then_latest_sermon_first() {
		$this->assertSame( array( array( 1, 'Dylan Jones', 3 ), array( 2, 'Rhiannon Llŷr', 2 ), array( 3, "Aled O'Brien", 1 ) ), $this->facet_list( $this->facets()->preachers, 'preachers' ) );
	}

	public function test_facets_count_series_with_the_latest_sermon_first() {
		$this->assertSame( array( array( 1, 'Romans', 2 ), array( 2, 'The Psalms &#8211; songs for every season', 1 ) ), $this->facet_list( $this->facets()->series, 'series' ) );
	}

	public function test_facets_count_services_most_first_then_by_name() {
		$this->assertSame( array( array( 1, 'Sunday Morning', 4 ), array( 3, 'Christmas morning', 1 ), array( 2, 'Sunday Evening', 1 ) ), $this->facet_list( $this->facets()->services, 'services' ) );
	}

	public function test_facets_count_sermons_with_any_passage_in_each_book_in_bible_order() {
		$this->assertSame( array( array( 0, 'Genesis', 1 ), array( 0, 'Psalm', 1 ), array( 0, 'Isaiah', 1 ), array( 0, 'John', 1 ), array( 0, 'Romans', 2 ) ), $this->facet_list( $this->facets()->books ) );
	}

	public function test_facets_list_dated_sermons_dates_in_order() {
		$dates = $this->facets()->dates;

		$this->assertSame( array( '2025-12-25 09:45', '2026-03-01 10:30', '2026-03-01 18:00', '2026-03-08 10:30', '2026-03-15 19:00' ), array_map( function ( $date ) { return $date->format( 'Y-m-d H:i' ); }, $dates ) );
	}

	public function test_facets_cover_only_the_sermons_a_query_matches() {
		$facets = $this->facets( array( 'preacher' => 1, 'per_page' => 1 ) );

		$this->assertSame( array( array( 1, 'Dylan Jones', 3 ) ), $this->facet_list( $facets->preachers, 'preachers' ) );
		$this->assertSame( array( array( 1, 'Romans', 2 ) ), $this->facet_list( $facets->series, 'series' ) );
		$this->assertSame( array( array( 1, 'Sunday Morning', 2 ), array( 3, 'Christmas morning', 1 ) ), $this->facet_list( $facets->services, 'services' ) );
		$this->assertSame( array( array( 0, 'Isaiah', 1 ), array( 0, 'Romans', 2 ) ), $this->facet_list( $facets->books ) );
		$this->assertCount( 3, $facets->dates );
	}

	public function test_facets_of_a_book_list_every_book_its_sermons_are_on() {
		$this->assertSame( array( array( 0, 'Isaiah', 1 ), array( 0, 'Romans', 1 ) ), $this->facet_list( $this->facets( array( 'book' => 'Isaiah' ) )->books ) );
		$this->assertSame( array( array( 1, 'Dylan Jones', 2 ) ), $this->facet_list( $this->facets( array( 'with_attachments' => true ) )->preachers, 'preachers' ) );
	}

	public function test_tag_counts_count_sermons_per_tag_most_first_then_alphabetically() {
		$this->assertSame( array( array( 0, 'grace', 2 ), array( 0, 'faith', 1 ), array( 0, 'new birth', 1 ), array( 0, 'psalms', 1 ) ), $this->facet_list( $this->repository()->tag_counts() ) );
	}

	public function test_most_downloaded_lists_sermons_with_attachments_most_downloaded_first() {
		$this->assertSame( array( 1, 3 ), array_map( array( $this, 'fixture_id' ), $this->repository()->most_downloaded( 5 ) ) );
		$this->assertSame( array( 1 ), array_map( array( $this, 'fixture_id' ), $this->repository()->most_downloaded( 1 ) ) );
	}

	public function test_most_downloaded_lists_ties_newest_first() {
		$fixtures = require dirname( __DIR__ ) . '/fixtures/sermons.php';
		$fixtures['sermons'][3]['attachments'][0][2] = 7;
		$this->write( $fixtures );

		$this->assertSame( array( 3, 1 ), array_map( array( $this, 'fixture_id' ), $this->repository()->most_downloaded( 5 ) ) );
	}

	public function test_download_counts_total_each_series_and_preachers_attachments() {
		$fixtures = require dirname( __DIR__ ) . '/fixtures/sermons.php';
		$fixtures['sermons'][2]['attachments'] = array( array( 'file', 'psalm-23.mp3', 3, '' ) );
		$fixtures['sermons'][6]['attachments'] = array( array( 'file', 'undated.mp3', 1, '' ), array( 'file', 'undated-2.mp3', 0, '' ) );
		$this->write( $fixtures );
		$counts = function ( $by, $kind ) {
			return array_map(
				function ( $count ) use ( $kind ) {
					return array( $this->fixture( $kind, $count->id ), $count->name, $count->downloads, $count->attachments, $count->latest ? $count->latest->format( 'Y-m-d' ) : null );
				},
				$this->repository()->download_counts( $by )
			);
		};

		// The one with the latest sermon first; only those with attachments.
		$this->assertSame( array( array( 1, 'Romans', 7, 4, '2026-03-08' ), array( 2, 'The Psalms &#8211; songs for every season', 3, 1, '2026-03-01' ) ), $counts( SermonBrowser\Sermon_Repository::GROUP_BY_SERIES, 'series' ) );
		$this->assertSame( array( array( 1, 'Dylan Jones', 7, 4, '2026-03-08' ), array( 2, 'Rhiannon Llŷr', 4, 3, '2026-03-01' ) ), $counts( SermonBrowser\Sermon_Repository::GROUP_BY_PREACHER, 'preachers' ) );
	}
}
