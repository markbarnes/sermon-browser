<?php
/**
 * Building sermon queries from requests, shortcodes and links.
 *
 * @package SermonBrowser
 */

use SermonBrowser\Sermon_Query;

/**
 * Tests SermonBrowser\Sermon_Query's factories and sorting.
 */
class Sermon_Query_Test extends WP_UnitTestCase {

	public function tear_down() {
		$_REQUEST = array();
		parent::tear_down();
	}

	public function test_reads_the_filters_from_the_request() {
		$_REQUEST = array(
			'preacher' => '3x',
			'stag'     => 'grace',
			'title'    => "Paul\\'s <b>letter</b>",
			'date'     => '2026-03-01',
		);
		$this->assertSame(
			array(
				'title'    => "Paul's letter",
				'preacher' => 3,
				'date'     => '2026-03-01',
				'enddate'  => '',
				'series'   => '',
				'service'  => '',
				'book'     => '',
				'tag'      => 'grace',
			),
			Sermon_Query::filters_from_request()
		);
	}

	public function test_builds_a_query_from_filters_newest_first() {
		$query = Sermon_Query::from_filters(
			array(
				'title'    => 'faith',
				'preacher' => '2',
				'series'   => '',
				'book'     => 'Romans',
				'tag'      => 'grace',
				'date'     => '2026-01-01',
				'enddate'  => '2026-12-31',
				'filter'   => 'oneclick',
			)
		);
		$this->assertSame( array( 'faith', 2, 0, 0, 'Romans', 'grace', '2026-01-01', '2026-12-31', 0 ), array( $query->text, $query->preacher, $query->series, $query->service, $query->book, $query->tag, $query->from, $query->to, $query->id ) );
		$this->assertSame( array( Sermon_Query::BY_DATE, false, 1 ), array( $query->order_by, $query->ascending, $query->page ) );
	}

	/**
	 * @dataProvider sorts
	 *
	 * @param string $sortby    Sortby value.
	 * @param string $dir       Dir value.
	 * @param string $order_by  Expected order.
	 * @param bool   $ascending Expected direction.
	 */
	public function test_sorts_as_links_ask( $sortby, $dir, $order_by, $ascending ) {
		$query = new Sermon_Query();
		$query->sort_by( $sortby, $dir );
		$this->assertSame( array( $order_by, $ascending ), array( $query->order_by, $query->ascending ) );
	}

	public function sorts() {
		return array(
			'date, newest first by default' => array( 'm.datetime', '', Sermon_Query::BY_DATE, false ),
			'date ascending'                => array( 'm.datetime', 'ASC', Sermon_Query::BY_DATE, true ),
			'title, ascending by default'   => array( 'm.title', '', Sermon_Query::BY_TITLE, true ),
			'preacher descending'           => array( 'preacher', 'desc', Sermon_Query::BY_PREACHER, false ),
			'passage'                       => array( 'passage', 'asc', Sermon_Query::BY_PASSAGE, true ),
			'old preacher links'            => array( 'p.name', '', Sermon_Query::BY_PREACHER, true ),
			'old passage links'             => array( 'b.id', 'desc', Sermon_Query::BY_PASSAGE, false ),
			'other columns sort by date'    => array( 's.name', '', Sermon_Query::BY_DATE, true ),
		);
	}
}
