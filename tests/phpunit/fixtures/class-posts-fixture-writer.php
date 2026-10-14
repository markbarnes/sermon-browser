<?php
/**
 * Stores fixtures in the post types and taxonomies.
 *
 * @package SermonBrowser
 */

use SermonBrowser\Post_Types;
use SermonBrowser\Verse;

/**
 * Stores fixtures in the post types and taxonomies (SermonBrowser\Post_Types) through WordPress's
 * own functions, as the migration and the block editor will:
 *
 * - A sermon is an sb_sermon post: title, description as content, date (local time; an unknown date
 *   is 1970-01-01 00:00:00, as in the tables), published. A time that differs from the service's
 *   is meta sb_time.
 * - Preacher, series, service, tags and the books of its passages (start and end) are terms. A
 *   preacher's description is the term's description, which WordPress always filters down to basic
 *   inline tags (no paragraphs), even for administrators; the image is term meta sb_image. A service's
 *   time is term meta sb_time.
 * - Each passage is a meta sb_passage row, in the order entered: the start and end verses'
 *   Verse::key(), eight digits each, joined by a hyphen (45003021-45003031 is Romans 3:21-31).
 *   Meta sb_passage_sort is the first passage's start key, to sort by.
 * - A file is a media library attachment of the file where it is in the upload folder, whose parent is
 *   the sermon (or none). URLs and embed code are sb_link posts whose parent is the sermon: the URL or
 *   HTML as content, post_mime_type text/uri-list for a URL and text/html for embed code. Both have meta
 *   sb_downloads (always, so it can be counted up with one UPDATE) and, once known, sb_duration.
 *   Attachments are stored in the order entered, so a sermon's newest has the highest id.
 *
 * WordPress filters titles, content and term descriptions with kses for users without unfiltered_html,
 * which includes WP-CLI and tests (it would turn & into &amp; in titles), so the writer saves without it.
 */
class Posts_Fixture_Writer implements Fixture_Writer {

	/**
	 * {@inheritDoc}
	 *
	 * @param array $fixtures As tests/phpunit/fixtures/sermons.php returns.
	 * @return array[] Each fixture id => the post or term id stored.
	 */
	public function write( array $fixtures ) {
		$this->clear();

		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		kses_remove_filters();
		try {
			return $this->store( $fixtures );
		} finally {
			if ( $kses ) {
				kses_init_filters();
			}
		}
	}

	/**
	 * Removes the stored sermons, links, sermon files and terms. Deletes the rows directly, as
	 * wp_delete_attachment() would delete the files too.
	 */
	private function clear() {
		global $wpdb;
		$posts = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type IN (%s, %s)
				UNION SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'sb_downloads'",
				Post_Types::SERMON,
				Post_Types::LINK
			)
		);
		if ( $posts ) {
			$ids = implode( ',', array_map( 'intval', $posts ) );
			$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$ids})" );
			$wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$ids})" );
			$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$ids})" );
		}
		$taxonomies = array( Post_Types::PREACHER, Post_Types::SERIES, Post_Types::SERVICE, Post_Types::TAG, Post_Types::BOOK );
		$terms      = $wpdb->get_col( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('" . implode( "','", $taxonomies ) . "')" );
		if ( $terms ) {
			$ids = implode( ',', array_map( 'intval', $terms ) );
			$wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN ({$ids}))" );
			$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE term_id IN ({$ids})" );
			$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE term_id IN ({$ids})" );
			$wpdb->query( "DELETE FROM {$wpdb->terms} WHERE term_id IN ({$ids})" );
		}
		wp_cache_flush();
	}

	/**
	 * Stores the fixtures.
	 *
	 * @param array $fixtures As tests/phpunit/fixtures/sermons.php returns.
	 * @return array[] Each fixture id => the post or term id stored.
	 */
	private function store( array $fixtures ) {
		$stored = array(
			'sermons'   => array(),
			'preachers' => array(),
			'series'    => array(),
			'services'  => array(),
		);
		foreach ( $fixtures['preachers'] as $id => $preacher ) {
			$stored['preachers'][ $id ] = $this->term( Post_Types::PREACHER, $preacher[0], $preacher[1] );
			add_term_meta( $stored['preachers'][ $id ], 'sb_image', wp_slash( $preacher[2] ), true );
		}
		foreach ( $fixtures['series'] as $id => $name ) {
			$stored['series'][ $id ] = $this->term( Post_Types::SERIES, $name );
		}
		foreach ( $fixtures['services'] as $id => $service ) {
			$stored['services'][ $id ] = $this->term( Post_Types::SERVICE, $service[0] );
			add_term_meta( $stored['services'][ $id ], 'sb_time', wp_slash( $service[1] ), true );
		}
		foreach ( $fixtures['unattached'] as $attachment ) {
			$this->attach( 0, $attachment );
		}
		foreach ( $fixtures['sermons'] as $id => $sermon ) {
			$stored['sermons'][ $id ] = $this->sermon( $sermon, $stored );
		}
		return $stored;
	}

	/**
	 * Stores a sermon and its attachments.
	 *
	 * @param array   $sermon A sermon from the fixtures.
	 * @param array[] $stored The terms stored so far, by fixture id.
	 * @return int The post's id.
	 */
	private function sermon( array $sermon, array $stored ) {
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => Post_Types::SERMON,
					'post_status'  => 'publish',
					'post_title'   => $sermon['title'],
					'post_content' => $sermon['description'],
					'post_date'    => null === $sermon['date'] ? '1970-01-01 00:00:00' : $sermon['date'],
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}
		if ( '' !== $sermon['time'] ) {
			add_post_meta( $id, 'sb_time', wp_slash( $sermon['time'] ) );
		}

		wp_set_object_terms( $id, array( $stored['preachers'][ $sermon['preacher'] ] ), Post_Types::PREACHER );
		wp_set_object_terms( $id, $sermon['series'] ? array( $stored['series'][ $sermon['series'] ] ) : array(), Post_Types::SERIES );
		wp_set_object_terms( $id, array( $stored['services'][ $sermon['service'] ] ), Post_Types::SERVICE );
		wp_set_object_terms( $id, array_map( array( $this, 'tag' ), $sermon['tags'] ), Post_Types::TAG );

		$books = array();
		foreach ( $sermon['passages'] as $index => $passage ) {
			$start = ( new Verse( $passage[0], $passage[1], $passage[2] ) )->key();
			$end   = ( new Verse( $passage[3], $passage[4], $passage[5] ) )->key();
			add_post_meta( $id, 'sb_passage', sprintf( '%08d-%08d', $start, $end ) );
			if ( 0 === $index ) {
				add_post_meta( $id, 'sb_passage_sort', $start, true );
			}
			$books[] = $this->term_id( Post_Types::BOOK, $passage[0] );
			$books[] = $this->term_id( Post_Types::BOOK, $passage[3] );
		}
		wp_set_object_terms( $id, array_values( array_unique( $books ) ), Post_Types::BOOK );

		foreach ( $sermon['attachments'] as $attachment ) {
			$this->attach( $id, $attachment );
		}
		return $id;
	}

	/**
	 * Stores an attachment: a file as a media library attachment, a URL or embed code as an sb_link post.
	 *
	 * @param int   $sermon_id  The sermon's post id, or 0.
	 * @param array $attachment Type, content, downloads and duration.
	 */
	private function attach( $sermon_id, array $attachment ) {
		list( $type, $content, $downloads, $duration ) = $attachment;
		if ( 'file' === $type ) {
			$path = SB_ABSPATH . sb_get_option( 'upload_dir' ) . $content;
			$id   = wp_insert_attachment(
				wp_slash(
					array(
						'post_title'     => pathinfo( $content, PATHINFO_FILENAME ),
						'post_mime_type' => wp_check_filetype( $content )['type'],
						'post_status'    => 'inherit',
					)
				),
				$path,
				$sermon_id,
				true
			);
		} else {
			$id = wp_insert_post(
				wp_slash(
					array(
						'post_type'      => Post_Types::LINK,
						'post_status'    => 'publish',
						'post_parent'    => $sermon_id,
						'post_content'   => $content,
						'post_mime_type' => 'url' === $type ? 'text/uri-list' : 'text/html',
					)
				),
				true
			);
		}
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}
		add_post_meta( $id, 'sb_downloads', $downloads, true );
		if ( '' !== $duration ) {
			add_post_meta( $id, 'sb_duration', wp_slash( $duration ), true );
		}
	}

	/**
	 * Adds a term.
	 *
	 * @param string $taxonomy    Taxonomy.
	 * @param string $name        Name, as entered.
	 * @param string $description Description.
	 * @return int The term's id.
	 */
	private function term( $taxonomy, $name, $description = '' ) {
		$term = wp_insert_term( wp_slash( $name ), $taxonomy, array( 'description' => wp_slash( $description ) ) );
		if ( is_wp_error( $term ) ) {
			throw new RuntimeException( $term->get_error_message() );
		}
		return $term['term_id'];
	}

	/**
	 * Returns a term's id, adding the term if it isn't there.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $name     Name.
	 * @return int
	 */
	private function term_id( $taxonomy, $name ) {
		$term = get_term_by( 'name', $name, $taxonomy );
		return $term ? $term->term_id : $this->term( $taxonomy, $name );
	}

	/**
	 * Returns a tag's term id, adding the tag if it isn't there.
	 *
	 * @param string $name Tag.
	 * @return int
	 */
	private function tag( $name ) {
		return $this->term_id( Post_Types::TAG, $name );
	}
}
