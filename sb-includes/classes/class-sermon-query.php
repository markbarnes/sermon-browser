<?php
/**
 * What to list: filters, order and page.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which sermons to list, in what order, and which page of them.
 *
 * Filters left at their defaults don't filter.
 */
class Sermon_Query {

	const BY_DATE     = 'date';
	const BY_TITLE    = 'title';
	const BY_PREACHER = 'preacher';
	const BY_PASSAGE  = 'passage';
	const BY_SERVICE  = 'service';
	const BY_SERIES   = 'series';
	const BY_ID       = 'id';

	/**
	 * Words to find in the title, the description or a tag's name.
	 */
	public string $text = '';

	public int $preacher = 0;

	public int $series = 0;

	public int $service = 0;

	/**
	 * A book's English name: sermons with any passage in it.
	 */
	public string $book = '';

	/**
	 * Sermons with a tag whose name contains this.
	 */
	public string $tag = '';

	/**
	 * Sermons preached at or after this moment: a date (meaning its midnight) or a date and time, in the site's time zone.
	 */
	public string $from = '';

	/**
	 * Sermons preached at or before this moment, as $from. A date alone means its midnight, so it leaves out that day's sermons.
	 */
	public string $to = '';

	/**
	 * Only this sermon.
	 */
	public int $id = 0;

	/**
	 * Only sermons with a file, URL or embed code.
	 */
	public bool $with_attachments = false;

	/**
	 * One of the BY_ constants. Ties are listed newest first.
	 */
	public string $order_by = self::BY_DATE;

	/**
	 * Ascending, or (false) descending.
	 */
	public bool $ascending = false;

	/**
	 * Page number, from 1.
	 */
	public int $page = 1;

	public int $per_page = 10;

	/**
	 * The sortby values links and the drop-down filter use, and what each sorts by.
	 */
	const SORT_VALUES = array(
		'm.datetime' => self::BY_DATE,
		'm.title'    => self::BY_TITLE,
		'preacher'   => self::BY_PREACHER,
		'passage'    => self::BY_PASSAGE,
	);

	/**
	 * Sortby values from older links, and the values they mean now.
	 */
	const OLD_SORT_VALUES = array(
		'p.name' => 'preacher',
		'b.id'   => 'passage',
	);

	/**
	 * Returns the filters a request asks for (preacher=1 and so on), with '' for those it doesn't give.
	 *
	 * @return array Keys title, preacher, date, enddate, series, service, book and tag (from stag).
	 */
	public static function filters_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the sermon list's filters are read-only GET requests
		$text = fn( $name ) => isset( $_REQUEST[ $name ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $name ] ) ) : '';
		$id   = fn( $name ) => isset( $_REQUEST[ $name ] ) ? (int) $_REQUEST[ $name ] : '';
		// phpcs:enable
		return array(
			'title'    => $text( 'title' ),
			'preacher' => $id( 'preacher' ),
			'date'     => $text( 'date' ),
			'enddate'  => $text( 'enddate' ),
			'series'   => $id( 'series' ),
			'service'  => $id( 'service' ),
			'book'     => $text( 'book' ),
			'tag'      => $text( 'stag' ),
		);
	}

	/**
	 * Returns a query for the sermons that match filters, newest first.
	 *
	 * @param array $filters Keys as filters_from_request() gives them, and id; any may be left out.
	 * @return Sermon_Query
	 */
	public static function from_filters( array $filters ) {
		$filters         = array_merge(
			array(
				'title'    => '',
				'preacher' => 0,
				'date'     => '',
				'enddate'  => '',
				'series'   => 0,
				'service'  => 0,
				'book'     => '',
				'tag'      => '',
				'id'       => 0,
			),
			$filters
		);
		$query           = new self();
		$query->text     = (string) $filters['title'];
		$query->preacher = (int) $filters['preacher'];
		$query->from     = (string) $filters['date'];
		$query->to       = (string) $filters['enddate'];
		$query->series   = (int) $filters['series'];
		$query->service  = (int) $filters['service'];
		$query->book     = (string) $filters['book'];
		$query->tag      = (string) $filters['tag'];
		$query->id       = (int) $filters['id'];
		return $query;
	}

	/**
	 * Sets the order from a link's sortby and dir.
	 *
	 * Unknown sortby values sort by date. Without a dir, sorting by date is newest first and anything else ascending.
	 *
	 * @param string $sortby A key of SORT_VALUES or OLD_SORT_VALUES.
	 * @param string $dir    'asc', 'desc' or ''.
	 */
	public function sort_by( $sortby, $dir = '' ) {
		$value           = self::normalise_sortby( $sortby );
		$this->order_by  = isset( self::SORT_VALUES[ $value ] ) ? self::SORT_VALUES[ $value ] : self::BY_DATE;
		$this->ascending = '' === $dir ? 'm.datetime' !== $sortby : 'asc' === strtolower( $dir );
	}

	/**
	 * Returns the sortby value an older link's means now.
	 *
	 * @param string $sortby Sortby value.
	 * @return string
	 */
	public static function normalise_sortby( $sortby ) {
		return isset( self::OLD_SORT_VALUES[ $sortby ] ) ? self::OLD_SORT_VALUES[ $sortby ] : $sortby;
	}
}
