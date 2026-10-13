<?php
/**
 * Reads sermons from the plugin's own tables.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser\Storage\Tables;

use SermonBrowser\Attachment;
use SermonBrowser\Passage;
use SermonBrowser\Preacher;
use SermonBrowser\Sermon;
use SermonBrowser\Sermon_Page;
use SermonBrowser\Sermon_Query;
use SermonBrowser\Series;
use SermonBrowser\Service;
use SermonBrowser\Verse;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for

/**
 * Reads sermons from the wp_sb_* tables.
 *
 * What the tables hold that callers shouldn't see is undone here: text saved by the Add Sermon page
 * has its quotes escaped (God\'s), embed code is base64-encoded, passages are serialized, an unknown
 * date is 1970-01-01, and a time is kept only when it overrides the service's.
 */
class Sermon_Repository implements \SermonBrowser\Sermon_Repository {

	const UNKNOWN_DATE = '1970-01-01 00:00:00';

	/**
	 * {@inheritDoc}
	 *
	 * @param Sermon_Query $query Filters, order and page.
	 * @return Sermon_Page
	 */
	public function find( Sermon_Query $query ) {
		global $wpdb;
		$where                   = $this->where( $query );
		$per_page                = max( 1, $query->per_page );
		$offset                  = $per_page * max( 0, $query->page - 1 );
		list( $join, $order_by ) = $this->order( $query );
		// $where is built from prepared fragments and $join and $order_by from fixed SQL.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons AS m WHERE {$where}" );
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT m.id FROM {$wpdb->prefix}sb_sermons AS m {$join} WHERE {$where} ORDER BY {$order_by} LIMIT %d, %d",
				$offset,
				$per_page
			)
		);
		// phpcs:enable
		return new Sermon_Page( $this->load( array_map( 'intval', $ids ) ), $total );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $id Id.
	 * @return Sermon|null
	 */
	public function get( $id ) {
		$sermons = $this->load( array( (int) $id ) );
		return $sermons ? $sermons[0] : null;
	}

	/**
	 * Returns the WHERE clause for a query's filters.
	 *
	 * @param Sermon_Query $query The query.
	 * @return string SQL, with every value prepared.
	 */
	private function where( Sermon_Query $query ) {
		global $wpdb;
		$conditions = array( '1=1' );
		if ( '' !== $query->text ) {
			// Text saved by the Add Sermon page is stored with quotes escaped (God\'s), so match both forms.
			$like         = '%' . $wpdb->esc_like( $query->text ) . '%';
			$slashed      = '%' . $wpdb->esc_like( addslashes( $query->text ) ) . '%';
			$conditions[] = $wpdb->prepare(
				"(m.title LIKE %s OR m.title LIKE %s OR m.description LIKE %s OR m.description LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->prefix}sb_sermons_tags AS st JOIN {$wpdb->prefix}sb_tags AS t ON t.id = st.tag_id WHERE st.sermon_id = m.id AND t.name LIKE %s))",
				$like,
				$slashed,
				$like,
				$slashed,
				$like
			);
		}
		if ( $query->preacher ) {
			$conditions[] = $wpdb->prepare( 'm.preacher_id = %d', $query->preacher );
		}
		if ( $query->series ) {
			$conditions[] = $wpdb->prepare( 'm.series_id = %d', $query->series );
		}
		if ( $query->service ) {
			$conditions[] = $wpdb->prepare( 'm.service_id = %d', $query->service );
		}
		if ( '' !== $query->book ) {
			$conditions[] = $wpdb->prepare( "EXISTS (SELECT 1 FROM {$wpdb->prefix}sb_books_sermons AS bs WHERE bs.sermon_id = m.id AND bs.book_name = %s)", $query->book );
		}
		if ( '' !== $query->tag ) {
			$conditions[] = $wpdb->prepare( "EXISTS (SELECT 1 FROM {$wpdb->prefix}sb_sermons_tags AS st JOIN {$wpdb->prefix}sb_tags AS t ON t.id = st.tag_id WHERE st.sermon_id = m.id AND t.name LIKE %s)", '%' . $wpdb->esc_like( $query->tag ) . '%' );
		}
		if ( '' !== $query->from ) {
			$conditions[] = $wpdb->prepare( 'm.datetime >= %s', $query->from );
		}
		if ( '' !== $query->to ) {
			$conditions[] = $wpdb->prepare( 'm.datetime <= %s', $query->to );
		}
		if ( '' !== $query->from || '' !== $query->to ) {
			$conditions[] = $wpdb->prepare( 'm.datetime <> %s', self::UNKNOWN_DATE );
		}
		if ( $query->id ) {
			$conditions[] = $wpdb->prepare( 'm.id = %d', $query->id );
		}
		if ( $query->with_attachments ) {
			$conditions[] = "EXISTS (SELECT 1 FROM {$wpdb->prefix}sb_stuff AS stuff WHERE stuff.sermon_id = m.id AND stuff.name != '')";
		}
		return implode( ' AND ', $conditions );
	}

	/**
	 * Returns the joins and ORDER BY clause for a query's order.
	 *
	 * @param Sermon_Query $query The query.
	 * @return string[] The joins, and the ORDER BY clause.
	 */
	private function order( Sermon_Query $query ) {
		global $wpdb;
		$dir = $query->ascending ? 'ASC' : 'DESC';
		switch ( $query->order_by ) {
			case Sermon_Query::BY_TITLE:
				return array( '', "m.title {$dir}, m.datetime DESC, m.id DESC" );
			case Sermon_Query::BY_PREACHER:
				return array( "LEFT JOIN {$wpdb->prefix}sb_preachers AS p ON p.id = m.preacher_id", "p.name {$dir}, m.datetime DESC, m.id DESC" );
			case Sermon_Query::BY_PASSAGE:
				// Each sermon's first passage, in Bible order (sb_books lists the books in order).
				return array(
					"LEFT JOIN {$wpdb->prefix}sb_books_sermons AS bs ON bs.sermon_id = m.id AND bs.order = 0 AND bs.type = 'start'
					LEFT JOIN {$wpdb->prefix}sb_books AS b ON b.name = bs.book_name",
					"b.id IS NULL, b.id {$dir}, bs.chapter {$dir}, bs.verse {$dir}, m.datetime DESC, m.id DESC",
				);
			default:
				return array( '', "m.datetime {$dir}, m.id {$dir}" );
		}
	}

	/**
	 * Returns whole sermons, in the order of the ids given.
	 *
	 * @param int[] $ids Ids.
	 * @return Sermon[]
	 */
	private function load( array $ids ) {
		global $wpdb;
		if ( ! $ids ) {
			return array();
		}
		$in = implode( ',', array_map( 'intval', $ids ) );
		// $in holds integers only.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows        = $wpdb->get_results(
			"SELECT m.id, m.title, m.description, m.datetime, m.time, m.override, m.start, m.end,
				p.id AS preacher_id, p.name AS preacher_name, p.description AS preacher_description, p.image AS preacher_image,
				ss.id AS series_id, ss.name AS series_name,
				s.id AS service_id, s.name AS service_name, s.time AS service_time
			FROM {$wpdb->prefix}sb_sermons AS m
			LEFT JOIN {$wpdb->prefix}sb_preachers AS p ON p.id = m.preacher_id
			LEFT JOIN {$wpdb->prefix}sb_series AS ss ON ss.id = m.series_id
			LEFT JOIN {$wpdb->prefix}sb_services AS s ON s.id = m.service_id
			WHERE m.id IN ({$in})",
			OBJECT_K
		);
		$tag_rows    = $wpdb->get_results( "SELECT st.sermon_id, t.name FROM {$wpdb->prefix}sb_sermons_tags AS st JOIN {$wpdb->prefix}sb_tags AS t ON t.id = st.tag_id WHERE st.sermon_id IN ({$in}) ORDER BY t.name ASC" );
		$attachments = $wpdb->get_results( "SELECT id, sermon_id, type, name, count, duration FROM {$wpdb->prefix}sb_stuff WHERE sermon_id IN ({$in}) ORDER BY id DESC" );
		// phpcs:enable
		$tags = array();
		foreach ( $tag_rows as $tag ) {
			if ( null !== $tag->name && '' !== $tag->name ) {
				$tags[ $tag->sermon_id ][] = stripslashes( $tag->name );
			}
		}
		$attached = array();
		foreach ( $attachments as $attachment ) {
			$attached[ $attachment->sermon_id ][] = new Attachment(
				(int) $attachment->id,
				$attachment->type,
				Attachment::EMBED === $attachment->type ? (string) base64_decode( $attachment->name ) : $attachment->name, // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- embed code is stored base64-encoded
				(int) $attachment->count,
				(string) $attachment->duration
			);
		}
		$sermons = array();
		foreach ( $ids as $id ) {
			if ( isset( $rows[ $id ] ) ) {
				$row       = $rows[ $id ];
				$sermons[] = new Sermon(
					(int) $row->id,
					stripslashes( $row->title ),
					stripslashes( (string) $row->description ),
					self::UNKNOWN_DATE === $row->datetime ? null : new \DateTimeImmutable( $row->datetime, wp_timezone() ),
					$row->override ? (string) $row->time : '',
					null === $row->preacher_id ? null : new Preacher( (int) $row->preacher_id, stripslashes( $row->preacher_name ), stripslashes( $row->preacher_description ), $row->preacher_image ),
					null === $row->series_id ? null : new Series( (int) $row->series_id, stripslashes( $row->series_name ) ),
					null === $row->service_id ? null : new Service( (int) $row->service_id, stripslashes( $row->service_name ), $row->service_time ),
					$this->passages( $row->start, $row->end ),
					isset( $tags[ $id ] ) ? $tags[ $id ] : array(),
					isset( $attached[ $id ] ) ? $attached[ $id ] : array()
				);
			}
		}
		return $sermons;
	}

	/**
	 * Returns the passages serialized in a sermon's start and end columns.
	 *
	 * @param string $start Serialized array of the passages' first verses.
	 * @param string $end   Serialized array of their last verses.
	 * @return Passage[]
	 */
	private function passages( $start, $end ) {
		$start    = sb_unserialize( $start );
		$end      = sb_unserialize( $end );
		$passages = array();
		foreach ( is_array( $start ) ? $start : array() as $i => $first ) {
			if ( ! is_array( $first ) || ! isset( $first['book'], $end[ $i ]['book'] ) || '' === trim( $first['book'] ) ) {
				continue;
			}
			$last       = $end[ $i ];
			$passages[] = new Passage(
				new Verse( trim( $first['book'] ), (int) $first['chapter'], (int) $first['verse'] ),
				new Verse( trim( $last['book'] ), (int) $last['chapter'], (int) $last['verse'] )
			);
		}
		return $passages;
	}
}
