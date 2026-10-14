<?php
/**
 * Reads sermons from the post types and taxonomies.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser\Storage\Posts;

use SermonBrowser\Attachment;
use SermonBrowser\Download_Count;
use SermonBrowser\Facet;
use SermonBrowser\Passage;
use SermonBrowser\Post_Types;
use SermonBrowser\Preacher;
use SermonBrowser\Sermon;
use SermonBrowser\Sermon_Facets;
use SermonBrowser\Sermon_Page;
use SermonBrowser\Sermon_Query;
use SermonBrowser\Series;
use SermonBrowser\Service;
use SermonBrowser\Verse;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- counts, groups and sorts across posts, terms and meta that WP_Query can't express; WordPress caches posts and meta, not such results

/**
 * Reads published sermons from the post types and taxonomies (SermonBrowser\Post_Types), stored as
 * tests/phpunit/fixtures/class-posts-fixture-writer.php describes.
 *
 * A sermon's attachments are its children (media library attachments and sb_link posts) that have a
 * download count, so other media attached to it, such as a featured image, aren't among them.
 */
class Sermon_Repository implements \SermonBrowser\Sermon_Repository {

	/**
	 * The date stored for a sermon whose date is unknown.
	 */
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
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} AS m WHERE {$where}" );
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT m.ID FROM {$wpdb->posts} AS m {$join} WHERE {$where} ORDER BY {$order_by} LIMIT %d, %d",
				$offset,
				$per_page
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
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
	 * {@inheritDoc}
	 *
	 * @param Sermon $sermon The sermon.
	 * @return Sermon|null
	 */
	public function next( Sermon $sermon ) {
		$sermons = $this->neighbours( $sermon, '>', 'ASC', 1 );
		return $sermons ? $sermons[0] : null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Sermon $sermon The sermon.
	 * @return Sermon|null
	 */
	public function previous( Sermon $sermon ) {
		$sermons = $this->neighbours( $sermon, '<', 'DESC', 1 );
		return $sermons ? $sermons[0] : null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Sermon $sermon The sermon.
	 * @return Sermon[]
	 */
	public function same_day( Sermon $sermon ) {
		return $this->neighbours( $sermon, '=', 'ASC' );
	}

	/**
	 * Returns other dated sermons on days before, after or the same as a sermon's.
	 *
	 * @param Sermon $sermon     The sermon.
	 * @param string $comparison <, > or =, comparing their day with the sermon's.
	 * @param string $direction  ASC or DESC.
	 * @param int    $limit      How many, or 0 for all.
	 * @return Sermon[]
	 */
	private function neighbours( Sermon $sermon, $comparison, $direction, $limit = 0 ) {
		global $wpdb;
		if ( ! $sermon->date || ! in_array( $comparison, array( '<', '>', '=' ), true ) || ! in_array( $direction, array( 'ASC', 'DESC' ), true ) ) {
			return array();
		}
		$published = $this->published();
		// $comparison and $direction are checked above, and $published is prepared.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT m.ID FROM {$wpdb->posts} AS m WHERE {$published} AND DATE(m.post_date) {$comparison} DATE(%s) AND m.ID <> %d AND m.post_date <> %s ORDER BY m.post_date {$direction}, m.ID {$direction} LIMIT %d",
				$sermon->date->format( 'Y-m-d H:i:s' ),
				$sermon->id,
				self::UNKNOWN_DATE,
				$limit ? $limit : PHP_INT_MAX
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $this->load( array_map( 'intval', $ids ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Sermon_Query $query Filters.
	 * @return Sermon_Facets
	 */
	public function facets( Sermon_Query $query ) {
		global $wpdb;
		$where     = $this->where( $query );
		$terms     = function ( $taxonomy, $order_by ) use ( $wpdb, $where ) {
			// $where is built from prepared fragments and $order_by is fixed SQL.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT t.term_id AS id, t.name, COUNT(DISTINCT m.ID) AS count
					FROM {$wpdb->posts} AS m
					JOIN {$wpdb->term_relationships} AS tr ON tr.object_id = m.ID
					JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
					JOIN {$wpdb->terms} AS t ON t.term_id = tt.term_id
					WHERE {$where} GROUP BY t.term_id, t.name ORDER BY {$order_by}",
					$taxonomy
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		};
		$preachers = $terms( Post_Types::PREACHER, 'count DESC, MAX(m.post_date) DESC, t.name ASC' );
		$series    = $terms( Post_Types::SERIES, 'MAX(m.post_date) DESC, t.name ASC' );
		$services  = $terms( Post_Types::SERVICE, 'count DESC, t.name ASC' );
		$books     = $terms( Post_Types::BOOK, 't.name ASC' );
		// $where is built from prepared fragments.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$dates = $wpdb->get_col( $wpdb->prepare( "SELECT m.post_date FROM {$wpdb->posts} AS m WHERE {$where} AND m.post_date <> %s ORDER BY m.post_date ASC", self::UNKNOWN_DATE ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// Books in Bible order, any the plugin doesn't know last.
		$bible = array_flip( sb_get_default( 'eng_bible_books' ) );
		usort(
			$books,
			function ( $a, $b ) use ( $bible ) {
				return ( $bible[ $a->name ] ?? PHP_INT_MAX ) <=> ( $bible[ $b->name ] ?? PHP_INT_MAX );
			}
		);
		$facets = function ( $rows, $with_id = true ) {
			return array_map(
				function ( $row ) use ( $with_id ) {
					return new Facet( $with_id ? (int) $row->id : 0, $row->name, (int) $row->count );
				},
				$rows
			);
		};
		return new Sermon_Facets(
			$facets( $preachers ),
			$facets( $series ),
			$facets( $services ),
			$facets( $books, false ),
			array_map(
				function ( $date ) {
					return new \DateTimeImmutable( $date, wp_timezone() );
				},
				$dates
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return Facet[]
	 */
	public function tag_counts() {
		global $wpdb;
		$published = $this->published();
		// $published is prepared.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.name, COUNT(DISTINCT m.ID) AS count
				FROM {$wpdb->posts} AS m
				JOIN {$wpdb->term_relationships} AS tr ON tr.object_id = m.ID
				JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
				JOIN {$wpdb->terms} AS t ON t.term_id = tt.term_id
				WHERE {$published} AND t.name <> '' GROUP BY t.term_id, t.name ORDER BY count DESC, t.name ASC",
				Post_Types::TAG
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return array_map(
			function ( $row ) {
				return new Facet( 0, $row->name, (int) $row->count );
			},
			$rows
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $limit How many.
	 * @return Sermon[]
	 */
	public function most_downloaded( $limit ) {
		global $wpdb;
		$published   = $this->published();
		$attachments = $this->attachments_join();
		// $published and $attachments are prepared.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT m.ID FROM {$wpdb->posts} AS m {$attachments} WHERE {$published} GROUP BY m.ID, m.post_date ORDER BY SUM(CAST(d.meta_value AS UNSIGNED)) DESC, m.post_date DESC, m.ID DESC LIMIT %d",
				max( 0, (int) $limit )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $this->load( array_map( 'intval', $ids ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $by GROUP_BY_SERIES or GROUP_BY_PREACHER.
	 * @return Download_Count[]
	 */
	public function download_counts( $by ) {
		global $wpdb;
		$taxonomies = array(
			self::GROUP_BY_SERIES   => Post_Types::SERIES,
			self::GROUP_BY_PREACHER => Post_Types::PREACHER,
		);
		if ( ! isset( $taxonomies[ $by ] ) ) {
			return array();
		}
		$published   = $this->published();
		$attachments = $this->attachments_join();
		// $published and $attachments are prepared.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id AS id, t.name, SUM(CAST(d.meta_value AS UNSIGNED)) AS downloads, COUNT(a.ID) AS attachments, MAX(m.post_date) AS latest
				FROM {$wpdb->posts} AS m {$attachments}
				JOIN {$wpdb->term_relationships} AS tr ON tr.object_id = m.ID
				JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
				JOIN {$wpdb->terms} AS t ON t.term_id = tt.term_id
				WHERE {$published} GROUP BY t.term_id, t.name ORDER BY latest DESC, t.name ASC",
				$taxonomies[ $by ]
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return array_map(
			function ( $row ) {
				return new Download_Count( (int) $row->id, $row->name, (int) $row->downloads, (int) $row->attachments, self::UNKNOWN_DATE === $row->latest ? null : new \DateTimeImmutable( $row->latest, wp_timezone() ) );
			},
			$rows
		);
	}

	/**
	 * Returns the condition for published sermons, as m.
	 *
	 * @return string SQL.
	 */
	private function published() {
		global $wpdb;
		return $wpdb->prepare( "m.post_type = %s AND m.post_status = 'publish'", Post_Types::SERMON );
	}

	/**
	 * Returns the joins from sermons (m) to their attachments (a) and those attachments' download counts (d).
	 *
	 * @return string SQL.
	 */
	private function attachments_join() {
		global $wpdb;
		return $wpdb->prepare(
			"JOIN {$wpdb->posts} AS a ON a.post_parent = m.ID AND a.post_type IN ('attachment', %s)
			JOIN {$wpdb->postmeta} AS d ON d.post_id = a.ID AND d.meta_key = 'sb_downloads'",
			Post_Types::LINK
		);
	}

	/**
	 * Returns a condition that sermon m has a term, chosen by id or by a LIKE pattern on its name.
	 *
	 * @param string $taxonomy  Taxonomy.
	 * @param string $condition SQL comparing t.term_id or t.name with one placeholder.
	 * @param mixed  $value     The value for the placeholder.
	 * @return string SQL.
	 */
	private function has_term( $taxonomy, $condition, $value ) {
		global $wpdb;
		// $condition is fixed SQL from this class, with the second placeholder.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		return $wpdb->prepare(
			"EXISTS (SELECT 1 FROM {$wpdb->term_relationships} AS ftr
				JOIN {$wpdb->term_taxonomy} AS ftt ON ftt.term_taxonomy_id = ftr.term_taxonomy_id AND ftt.taxonomy = %s
				JOIN {$wpdb->terms} AS t ON t.term_id = ftt.term_id
				WHERE ftr.object_id = m.ID AND {$condition})",
			$taxonomy,
			$value
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	}

	/**
	 * Returns the WHERE clause for a query's filters.
	 *
	 * @param Sermon_Query $query The query.
	 * @return string SQL, with every value prepared.
	 */
	private function where( Sermon_Query $query ) {
		global $wpdb;
		$conditions = array( $this->published() );
		if ( '' !== $query->text ) {
			$like         = '%' . $wpdb->esc_like( $query->text ) . '%';
			$conditions[] = '(' . $wpdb->prepare( 'm.post_title LIKE %s OR m.post_content LIKE %s', $like, $like ) . ' OR ' . $this->has_term( Post_Types::TAG, 't.name LIKE %s', $like ) . ')';
		}
		if ( $query->preacher ) {
			$conditions[] = $this->has_term( Post_Types::PREACHER, 't.term_id = %d', $query->preacher );
		}
		if ( $query->series ) {
			$conditions[] = $this->has_term( Post_Types::SERIES, 't.term_id = %d', $query->series );
		}
		if ( $query->service ) {
			$conditions[] = $this->has_term( Post_Types::SERVICE, 't.term_id = %d', $query->service );
		}
		if ( '' !== $query->book ) {
			$conditions[] = $this->has_term( Post_Types::BOOK, 't.name = %s', $query->book );
		}
		if ( '' !== $query->tag ) {
			$conditions[] = $this->has_term( Post_Types::TAG, 't.name LIKE %s', '%' . $wpdb->esc_like( $query->tag ) . '%' );
		}
		if ( '' !== $query->from ) {
			$conditions[] = $wpdb->prepare( 'm.post_date >= %s', $query->from );
		}
		if ( '' !== $query->to ) {
			$conditions[] = $wpdb->prepare( 'm.post_date <= %s', $query->to );
		}
		if ( '' !== $query->from || '' !== $query->to ) {
			$conditions[] = $wpdb->prepare( 'm.post_date <> %s', self::UNKNOWN_DATE );
		}
		if ( $query->id ) {
			$conditions[] = $wpdb->prepare( 'm.ID = %d', $query->id );
		}
		if ( $query->with_attachments ) {
			$conditions[] = $wpdb->prepare(
				"EXISTS (SELECT 1 FROM {$wpdb->posts} AS fa JOIN {$wpdb->postmeta} AS fd ON fd.post_id = fa.ID AND fd.meta_key = 'sb_downloads' WHERE fa.post_parent = m.ID AND fa.post_type IN ('attachment', %s))",
				Post_Types::LINK
			);
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
				return array( '', "m.post_title {$dir}, m.post_date DESC, m.ID DESC" );
			case Sermon_Query::BY_PREACHER:
				// The preacher's name; one row of it, in case a sermon has two preachers.
				return array(
					$wpdb->prepare(
						"LEFT JOIN {$wpdb->terms} AS p ON p.term_id = (SELECT MIN(ptt.term_id) FROM {$wpdb->term_relationships} AS ptr JOIN {$wpdb->term_taxonomy} AS ptt ON ptt.term_taxonomy_id = ptr.term_taxonomy_id AND ptt.taxonomy = %s WHERE ptr.object_id = m.ID)",
						Post_Types::PREACHER
					),
					"p.name {$dir}, m.post_date DESC, m.ID DESC",
				);
			case Sermon_Query::BY_PASSAGE:
				// The first passage's start, in Bible order (see Verse::key()); sermons without one last.
				return array(
					"LEFT JOIN {$wpdb->postmeta} AS ps ON ps.meta_id = (SELECT MIN(first.meta_id) FROM {$wpdb->postmeta} AS first WHERE first.post_id = m.ID AND first.meta_key = 'sb_passage_sort')",
					"ps.meta_value IS NULL, CAST(ps.meta_value AS UNSIGNED) {$dir}, m.post_date DESC, m.ID DESC",
				);
			default:
				return array( '', "m.post_date {$dir}, m.ID {$dir}" );
		}
	}

	/**
	 * Returns whole published sermons, in the order of the ids given.
	 *
	 * @param int[] $ids Ids.
	 * @return Sermon[]
	 */
	private function load( array $ids ) {
		global $wpdb;
		if ( ! $ids ) {
			return array();
		}
		$in        = implode( ',', array_map( 'intval', $ids ) );
		$published = $this->published();
		// $in holds integers only, and $published is prepared.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$posts = $wpdb->get_results( "SELECT m.ID, m.post_title, m.post_content, m.post_date FROM {$wpdb->posts} AS m WHERE m.ID IN ({$in}) AND {$published}", OBJECT_K );
		if ( ! $posts ) {
			return array();
		}
		$in          = implode( ',', array_map( 'intval', array_keys( $posts ) ) );
		$meta        = $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ({$in}) AND meta_key IN ('sb_time', 'sb_passage') ORDER BY meta_id" );
		$terms       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tr.object_id, tt.taxonomy, tt.description, t.term_id, t.name
				FROM {$wpdb->term_relationships} AS tr
				JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				JOIN {$wpdb->terms} AS t ON t.term_id = tt.term_id
				WHERE tr.object_id IN ({$in}) AND tt.taxonomy IN (%s, %s, %s, %s)
				ORDER BY t.name ASC, t.term_id ASC",
				Post_Types::PREACHER,
				Post_Types::SERIES,
				Post_Types::SERVICE,
				Post_Types::TAG
			)
		);
		$attachments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.ID, a.post_parent, a.post_type, a.post_mime_type, a.post_content, f.meta_value AS file, d.meta_value AS downloads, du.meta_value AS duration
				FROM {$wpdb->posts} AS a
				JOIN {$wpdb->postmeta} AS d ON d.post_id = a.ID AND d.meta_key = 'sb_downloads'
				LEFT JOIN {$wpdb->postmeta} AS f ON f.meta_id = (SELECT MIN(meta_id) FROM {$wpdb->postmeta} WHERE post_id = a.ID AND meta_key = '_wp_attached_file')
				LEFT JOIN {$wpdb->postmeta} AS du ON du.meta_id = (SELECT MIN(meta_id) FROM {$wpdb->postmeta} WHERE post_id = a.ID AND meta_key = 'sb_duration')
				WHERE a.post_parent IN ({$in}) AND a.post_type IN ('attachment', %s)
				ORDER BY a.ID DESC",
				Post_Types::LINK
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$times    = array();
		$passages = array();
		foreach ( $meta as $row ) {
			if ( 'sb_time' === $row->meta_key ) {
				$times[ $row->post_id ] = $row->meta_value;
			} else {
				$passage = self::passage( $row->meta_value );
				if ( $passage ) {
					$passages[ $row->post_id ][] = $passage;
				}
			}
		}
		$sermon_terms = array();
		$term_ids     = array();
		foreach ( $terms as $row ) {
			if ( Post_Types::TAG === $row->taxonomy ) {
				if ( '' !== $row->name ) {
					$sermon_terms[ $row->object_id ][ Post_Types::TAG ][] = $row->name;
				}
			} elseif ( ! isset( $sermon_terms[ $row->object_id ][ $row->taxonomy ] ) ) {
				$sermon_terms[ $row->object_id ][ $row->taxonomy ] = $row;
				$term_ids[]                                        = (int) $row->term_id;
			}
		}
		$term_meta = array();
		if ( $term_ids ) {
			$term_in = implode( ',', array_unique( $term_ids ) );
			// $term_in holds integers only.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$rows = $wpdb->get_results( "SELECT term_id, meta_key, meta_value FROM {$wpdb->termmeta} WHERE term_id IN ({$term_in}) AND meta_key IN ('sb_image', 'sb_time') ORDER BY meta_id DESC" );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			foreach ( $rows as $row ) {
				$term_meta[ $row->term_id ][ $row->meta_key ] = $row->meta_value;
			}
		}
		$attached = array();
		foreach ( $attachments as $row ) {
			$attachment = $this->attachment( $row );
			if ( $attachment ) {
				$attached[ $row->post_parent ][] = $attachment;
			}
		}

		$sermons = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $posts[ $id ] ) ) {
				continue;
			}
			$post      = $posts[ $id ];
			$term      = $sermon_terms[ $id ] ?? array();
			$preacher  = $term[ Post_Types::PREACHER ] ?? null;
			$series    = $term[ Post_Types::SERIES ] ?? null;
			$service   = $term[ Post_Types::SERVICE ] ?? null;
			$sermons[] = new Sermon(
				(int) $post->ID,
				$post->post_title,
				$post->post_content,
				self::UNKNOWN_DATE === $post->post_date ? null : new \DateTimeImmutable( $post->post_date, wp_timezone() ),
				$times[ $id ] ?? '',
				$preacher ? new Preacher( (int) $preacher->term_id, $preacher->name, $preacher->description, $term_meta[ $preacher->term_id ]['sb_image'] ?? '' ) : null,
				$series ? new Series( (int) $series->term_id, $series->name ) : null,
				$service ? new Service( (int) $service->term_id, $service->name, $term_meta[ $service->term_id ]['sb_time'] ?? '' ) : null,
				$passages[ $id ] ?? array(),
				$term[ Post_Types::TAG ] ?? array(),
				$attached[ $id ] ?? array()
			);
		}
		return $sermons;
	}

	/**
	 * Returns the passage an sb_passage meta value stands for: the start and end verses' keys, joined by a hyphen.
	 *
	 * @param string $value The meta value, such as 45003021-45003031.
	 * @return Passage|null Null if it isn't one.
	 */
	public static function passage( $value ) {
		if ( ! preg_match( '/^(\d{1,8})-(\d{1,8})$/', (string) $value, $keys ) ) {
			return null;
		}
		$start = Verse::from_key( (int) $keys[1] );
		$end   = Verse::from_key( (int) $keys[2] );
		return $start && $end ? new Passage( $start, $end ) : null;
	}

	/**
	 * Returns an attachment from its row.
	 *
	 * @param object $row A row of load()'s attachments query.
	 * @return Attachment|null Null for a file outside the upload folder.
	 */
	private function attachment( $row ) {
		if ( Post_Types::LINK === $row->post_type ) {
			$type    = 'text/uri-list' === $row->post_mime_type ? Attachment::URL : Attachment::EMBED;
			$content = $row->post_content;
		} else {
			$type    = Attachment::FILE;
			$content = Attachment_Repository::file_name( (string) $row->file );
			if ( null === $content ) {
				return null;
			}
		}
		return new Attachment( (int) $row->ID, $type, $content, (int) $row->downloads, (string) $row->duration );
	}
}
