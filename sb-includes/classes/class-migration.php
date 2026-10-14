<?php
/**
 * Copies sermons from the plugin's tables to the post types.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

use SermonBrowser\Storage\Posts\Attachment_Repository as Posts_Attachments;
use SermonBrowser\Storage\Posts\Sermon_Repository as Posts_Sermons;
use SermonBrowser\Storage\Tables\Sermon_Repository as Tables_Sermons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reads the plugin's own tables, and finds what it migrated by meta value, which WordPress's API can't do in one query

/**
 * Copies sermons, preachers, series, services and attachments from the plugin's tables to the post
 * types and taxonomies (Post_Types), in the format Storage\Posts reads. The tables are only read.
 *
 * Every post and term it writes has meta sb_legacy_id, the id of the row it came from, so running it
 * again updates what it migrated before, adds what is new and deletes what has gone from the tables:
 * the post types end up mirroring the tables however often it runs. Deleting a file's attachment
 * leaves the file in place. A dry run goes through the same steps without writing.
 *
 * It saves as an administrator's editor would, without kses (WP-CLI runs as no user, and kses would
 * turn & into &amp; in titles), and leaves WordPress's own changes alone: titles are trimmed, term names and descriptions
 * are sanitized as WordPress always does (descriptions keep only basic inline tags), a sermon dated in
 * the future is scheduled rather than published, and URLs that old versions saved with esc_url()
 * (&#038;) are decoded. verify() checks the result against the tables, allowing for exactly these.
 */
class Migration {

	const LEGACY_ID = 'sb_legacy_id';

	/**
	 * How many sermons to read at a time.
	 */
	const BATCH = 50;

	/**
	 * The report being written.
	 *
	 * @var Migration_Report
	 */
	private $report;

	/**
	 * What was migrated before: for each post type or taxonomy, legacy id => post or term id.
	 *
	 * @var array<string, array<int, int>>
	 */
	private $migrated = array();

	/**
	 * What this run found in the tables: for each post type or taxonomy, legacy id => post or term id
	 * (0 in a dry run for one not yet migrated).
	 *
	 * @var array<string, array<int, int>>
	 */
	private $seen = array();

	/**
	 * Names of the preachers, series and services this run has met, lowercase, to find those that share one.
	 *
	 * @var array<string, array<string, bool>>
	 */
	private $term_names = array();

	/**
	 * Term ids of tags and Bible books, by name, as found or added.
	 *
	 * @var array<string, array<string, int>>
	 */
	private $names = array();

	/**
	 * Copies everything, or in a dry run works out what copying would do.
	 *
	 * @param bool $dry_run Write nothing.
	 * @return Migration_Report
	 */
	public function run( bool $dry_run = false ) {
		$this->report = new Migration_Report( $dry_run );
		$this->find_migrated();

		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		kses_remove_filters();
		$podcast = has_action( 'save_post', array( Plugin::class, 'update_podcast_url' ) );
		remove_action( 'save_post', array( Plugin::class, 'update_podcast_url' ) );
		wp_defer_term_counting( true );
		try {
			$this->migrate_terms();
			$this->migrate_sermons();
			$this->migrate_unattached_files();
			$this->delete_the_rest();
		} finally {
			wp_defer_term_counting( false );
			if ( false !== $podcast ) {
				add_action( 'save_post', array( Plugin::class, 'update_podcast_url' ), $podcast );
			}
			if ( $kses ) {
				kses_init_filters();
			}
		}
		return $this->report;
	}

	/**
	 * Finds what earlier runs migrated, by legacy id.
	 */
	private function find_migrated() {
		global $wpdb;
		$this->migrated   = array();
		$this->seen       = array();
		$this->names      = array();
		$this->term_names = array();
		foreach ( array( Post_Types::SERMON, Post_Types::LINK, 'attachment' ) as $type ) {
			$this->migrated[ $type ] = array();
			$this->seen[ $type ]     = array();
		}
		foreach ( array( Post_Types::PREACHER, Post_Types::SERIES, Post_Types::SERVICE ) as $taxonomy ) {
			$this->migrated[ $taxonomy ] = array();
			$this->seen[ $taxonomy ]     = array();
		}
		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_type, pm.meta_value FROM {$wpdb->postmeta} AS pm JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id WHERE pm.meta_key = %s ORDER BY p.ID", self::LEGACY_ID ) );
		foreach ( $posts as $row ) {
			if ( isset( $this->migrated[ $row->post_type ] ) ) {
				$this->migrated[ $row->post_type ][ (int) $row->meta_value ] = (int) $row->ID;
			}
		}
		$terms = $wpdb->get_results( $wpdb->prepare( "SELECT tt.term_id, tt.taxonomy, tm.meta_value FROM {$wpdb->termmeta} AS tm JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_id = tm.term_id WHERE tm.meta_key = %s ORDER BY tt.term_id", self::LEGACY_ID ) );
		foreach ( $terms as $row ) {
			if ( isset( $this->migrated[ $row->taxonomy ] ) ) {
				$this->migrated[ $row->taxonomy ][ (int) $row->meta_value ] = (int) $row->term_id;
			}
		}
	}

	/**
	 * Copies the preachers, series and services, with or without sermons.
	 */
	private function migrate_terms() {
		global $wpdb;
		foreach ( $wpdb->get_results( "SELECT id, name, description, image FROM {$wpdb->prefix}sb_preachers ORDER BY id" ) as $row ) {
			$id = $this->save_term( Post_Types::PREACHER, 'preachers', (int) $row->id, stripslashes( $row->name ), stripslashes( (string) $row->description ) );
			if ( $id ) {
				update_term_meta( $id, 'sb_image', wp_slash( (string) $row->image ) );
			}
		}
		foreach ( $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}sb_series ORDER BY id" ) as $row ) {
			$this->save_term( Post_Types::SERIES, 'series', (int) $row->id, stripslashes( $row->name ) );
		}
		foreach ( $wpdb->get_results( "SELECT id, name, time FROM {$wpdb->prefix}sb_services ORDER BY id" ) as $row ) {
			$id = $this->save_term( Post_Types::SERVICE, 'services', (int) $row->id, stripslashes( $row->name ) );
			if ( $id ) {
				update_term_meta( $id, 'sb_time', wp_slash( (string) $row->time ) );
			}
		}
	}

	/**
	 * Adds or updates a preacher, series or service.
	 *
	 * @param string $taxonomy    Taxonomy.
	 * @param string $kind        What the report calls them.
	 * @param int    $legacy_id   The row's id.
	 * @param string $name        Name.
	 * @param string $description Description.
	 * @return int The term's id, or 0 if it wasn't (or in a dry run, isn't yet) written.
	 */
	private function save_term( $taxonomy, $kind, $legacy_id, $name, $description = '' ) {
		$singular = array(
			'preachers' => 'Preacher',
			'series'    => 'Series',
			'services'  => 'Service',
		)[ $kind ];
		if ( '' === trim( self::stored_name( $name, $taxonomy ) ) ) {
			$this->report->problems[] = sprintf( '%s %d ("%s") has no name WordPress can keep, so it was left out.', $singular, $legacy_id, $name );
			return 0;
		}
		$existing                                    = $this->migrated[ $taxonomy ][ $legacy_id ] ?? 0;
		$args                                        = array(
			'name'        => wp_slash( $name ),
			'description' => wp_slash( $description ),
		);
		$lowercase                                   = mb_strtolower( trim( $name ) );
		$same_name                                   = isset( $this->term_names[ $taxonomy ][ $lowercase ] );
		$this->term_names[ $taxonomy ][ $lowercase ] = true;
		if ( ! $existing ) {
			if ( $same_name || term_exists( $name, $taxonomy ) ) {
				// WordPress allows a second term with the same name only with a slug of its own.
				$args['slug']          = wp_unique_term_slug(
					sanitize_title( $name ),
					(object) array(
						'taxonomy' => $taxonomy,
						'parent'   => 0,
					)
				);
				$this->report->notes[] = sprintf( '%s %d has the same name as another, "%s", and is kept separate.', $singular, $legacy_id, $name );
			}
		}
		$this->report->count( $kind, $existing ? Migration_Report::UPDATED : Migration_Report::CREATED );
		if ( $this->report->dry_run ) {
			$this->seen[ $taxonomy ][ $legacy_id ] = $existing;
			return 0;
		}
		$result = $existing ? wp_update_term( $existing, $taxonomy, $args ) : wp_insert_term( $args['name'], $taxonomy, $args );
		if ( is_wp_error( $result ) ) {
			$this->report->problems[] = sprintf( '%s %d ("%s") couldn\'t be saved: %s', $singular, $legacy_id, $name, $result->get_error_message() );
			return 0;
		}
		$id = (int) $result['term_id'];
		update_term_meta( $id, self::LEGACY_ID, $legacy_id );
		$this->seen[ $taxonomy ][ $legacy_id ] = $id;
		return $id;
	}

	/**
	 * Copies every sermon and its attachments, a batch at a time.
	 */
	private function migrate_sermons() {
		$tables = new Tables_Sermons();
		$query  = new Sermon_Query();

		$query->ascending = true;
		$query->per_page  = self::BATCH;
		for ( $query->page = 1; ; $query->page++ ) {
			$page = $tables->find( $query );
			foreach ( $page->sermons as $sermon ) {
				$this->save_sermon( $sermon );
			}
			if ( $query->page * self::BATCH >= $page->total ) {
				break;
			}
			if ( function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}
		}
	}

	/**
	 * Adds or updates a sermon, its terms, passages and attachments.
	 *
	 * @param Sermon $sermon A sermon from the tables.
	 */
	private function save_sermon( Sermon $sermon ) {
		$existing = $this->migrated[ Post_Types::SERMON ][ $sermon->id ] ?? 0;
		$date     = $sermon->date ? $sermon->date->format( 'Y-m-d H:i:s' ) : Tables_Sermons::UNKNOWN_DATE;
		if ( $sermon->date && $sermon->date > new \DateTimeImmutable( 'now', wp_timezone() ) ) {
			$this->report->problems[] = sprintf( 'Sermon %d is dated %s, in the future, so WordPress schedules it instead of publishing it: it won\'t be shown until then.', $sermon->id, $date );
		}
		$preacher = $this->term_for( Post_Types::PREACHER, $sermon->preacher, 'preacher', $sermon->id );
		$series   = $this->term_for( Post_Types::SERIES, $sermon->series, 'series', $sermon->id );
		$service  = $this->term_for( Post_Types::SERVICE, $sermon->service, 'service', $sermon->id );
		$passages = array();
		foreach ( $sermon->passages as $passage ) {
			$start = $passage->start->key();
			$end   = $passage->end->key();
			if ( null === $start || null === $end ) {
				$this->report->problems[] = sprintf( 'Sermon %d\'s passage %s isn\'t a Bible reference the plugin knows, so it was left out.', $sermon->id, $passage->reference() );
				continue;
			}
			$passages[] = array( $start, $end, $passage );
		}

		$this->report->count( 'sermons', $existing ? Migration_Report::UPDATED : Migration_Report::CREATED );
		$this->seen[ Post_Types::SERMON ][ $sermon->id ] = $existing;
		if ( $this->report->dry_run ) {
			foreach ( array_reverse( $sermon->attachments ) as $attachment ) {
				$this->save_attachment( $attachment, 0 );
			}
			return;
		}

		$post = array(
			'post_type'    => Post_Types::SERMON,
			'post_status'  => 'publish',
			'post_title'   => $sermon->title,
			'post_content' => $sermon->description,
			'post_date'    => $date,
			'edit_date'    => true,
		);
		if ( $existing ) {
			$post['ID'] = $existing;
			$id         = wp_update_post( wp_slash( $post ), true );
		} else {
			$post['post_author'] = get_current_user_id();
			$id                  = wp_insert_post( wp_slash( $post ), true );
		}
		if ( is_wp_error( $id ) ) {
			$this->report->problems[] = sprintf( 'Sermon %d ("%s") couldn\'t be saved: %s', $sermon->id, $sermon->title, $id->get_error_message() );
			return;
		}
		$this->seen[ Post_Types::SERMON ][ $sermon->id ] = $id;
		update_post_meta( $id, self::LEGACY_ID, $sermon->id );

		delete_post_meta( $id, 'sb_time' );
		if ( '' !== $sermon->time ) {
			add_post_meta( $id, 'sb_time', wp_slash( $sermon->time ) );
		}
		delete_post_meta( $id, 'sb_passage' );
		delete_post_meta( $id, 'sb_passage_sort' );
		$books = array();
		foreach ( $passages as $index => $passage ) {
			add_post_meta( $id, 'sb_passage', sprintf( '%08d-%08d', $passage[0], $passage[1] ) );
			if ( 0 === $index ) {
				add_post_meta( $id, 'sb_passage_sort', $passage[0], true );
			}
			$books[] = $this->name_term( Post_Types::BOOK, $passage[2]->start->book );
			$books[] = $this->name_term( Post_Types::BOOK, $passage[2]->end->book );
		}

		wp_set_object_terms( $id, $preacher ? array( $preacher ) : array(), Post_Types::PREACHER );
		wp_set_object_terms( $id, $series ? array( $series ) : array(), Post_Types::SERIES );
		wp_set_object_terms( $id, $service ? array( $service ) : array(), Post_Types::SERVICE );
		wp_set_object_terms( $id, array_values( array_unique( array_filter( $books ) ) ), Post_Types::BOOK );
		$tags = array();
		foreach ( $sermon->tags as $tag ) {
			$tags[] = $this->name_term( Post_Types::TAG, $tag );
		}
		wp_set_object_terms( $id, array_values( array_unique( array_filter( $tags ) ) ), Post_Types::TAG );

		if ( 'publish' !== get_post_status( $id ) && ! ( $sermon->date && $sermon->date > new \DateTimeImmutable( 'now', wp_timezone() ) ) ) {
			$this->report->problems[] = sprintf( 'Sermon %d was saved as %s, not published.', $sermon->id, get_post_status( $id ) );
		}

		// Oldest first, so the newest has the highest id, as in the tables.
		foreach ( array_reverse( $sermon->attachments ) as $attachment ) {
			$this->save_attachment( $attachment, $id );
		}
	}

	/**
	 * Returns the term a sermon's preacher, series or service was migrated to.
	 *
	 * @param string                       $taxonomy  Taxonomy.
	 * @param Preacher|Series|Service|null $record    The sermon's preacher, series or service.
	 * @param string                       $what      What to call it in the report.
	 * @param int                          $sermon_id The sermon's legacy id.
	 * @return int The term's id, or 0 for none.
	 */
	private function term_for( $taxonomy, $record, $what, $sermon_id ) {
		if ( null === $record ) {
			return 0;
		}
		if ( ! array_key_exists( $record->id, $this->seen[ $taxonomy ] ) ) {
			$this->report->problems[] = sprintf( 'Sermon %d\'s %s (%d, "%s") wasn\'t migrated, so the sermon has none.', $sermon_id, $what, $record->id, $record->name );
			return 0;
		}
		return $this->seen[ $taxonomy ][ $record->id ];
	}

	/**
	 * Returns the id of a tag or Bible book, adding it if it isn't there.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $name     Name.
	 * @return int The term's id, or 0 if it couldn't be added.
	 */
	private function name_term( $taxonomy, $name ) {
		if ( '' === trim( self::stored_name( $name, $taxonomy ) ) ) {
			if ( '' !== trim( $name ) ) {
				$this->report->problems[] = sprintf( 'The %s "%s" has no name WordPress can keep, so it was left out.', Post_Types::TAG === $taxonomy ? 'tag' : 'Bible book', $name );
			}
			return 0;
		}
		if ( ! isset( $this->names[ $taxonomy ][ $name ] ) ) {
			$term = get_term_by( 'name', $name, $taxonomy );
			if ( ! $term ) {
				$term = wp_insert_term( wp_slash( $name ), $taxonomy );
				if ( is_wp_error( $term ) && 'term_exists' === $term->get_error_code() ) {
					// The same name as WordPress stores it, such as A &amp; B for A & B.
					$term = array( 'term_id' => (int) $term->get_error_data() );
				} elseif ( is_wp_error( $term ) ) {
					$this->report->problems[] = sprintf( 'The %s "%s" couldn\'t be saved: %s', Post_Types::TAG === $taxonomy ? 'tag' : 'Bible book', $name, $term->get_error_message() );
					$term                     = array( 'term_id' => 0 );
				}
				$term = (object) $term;
			}
			$this->names[ $taxonomy ][ $name ] = (int) $term->term_id;
		}
		return $this->names[ $taxonomy ][ $name ];
	}

	/**
	 * Adds or updates an attachment: a file as a media library attachment of the file where it is,
	 * a URL or embed code as an sb_link post.
	 *
	 * @param Attachment $attachment An attachment from the tables.
	 * @param int        $parent_id  The sermon's post id, or 0.
	 */
	private function save_attachment( Attachment $attachment, $parent_id ) {
		$type     = Attachment::FILE === $attachment->type ? 'attachment' : Post_Types::LINK;
		$existing = $this->migrated[ $type ][ $attachment->id ] ?? 0;
		$content  = $attachment->content;
		if ( Attachment::FILE === $attachment->type ) {
			$path = SB_ABSPATH . sb_get_option( 'upload_dir' ) . $content;
			if ( ! sb_is_plain_file_name( $content ) ) {
				$this->report->problems[] = sprintf( 'File %d ("%s") isn\'t a plain file name in the upload folder, so it was left out.', $attachment->id, $content );
				return;
			}
			if ( ! file_exists( $path ) ) {
				$this->report->problems[] = sprintf( 'File %d ("%s") isn\'t in the upload folder; it was migrated, but can\'t be downloaded.', $attachment->id, $content );
			}
		} elseif ( Attachment::URL === $attachment->type ) {
			// Old versions saved URLs with esc_url(), which turns & into &#038;.
			$content = wp_specialchars_decode( $content, ENT_QUOTES );
		}

		$this->report->count( Attachment::FILE === $attachment->type ? 'files' : ( Attachment::URL === $attachment->type ? 'URLs' : 'embeds' ), $existing ? Migration_Report::UPDATED : Migration_Report::CREATED );
		$this->seen[ $type ][ $attachment->id ] = $existing;
		if ( $this->report->dry_run ) {
			return;
		}

		if ( Attachment::FILE === $attachment->type ) {
			$post = array(
				'post_title'     => pathinfo( $content, PATHINFO_FILENAME ),
				'post_mime_type' => (string) wp_check_filetype( $content )['type'],
				'post_status'    => 'inherit',
				'guid'           => sb_get_option( 'upload_url' ) . $content,
			);
			if ( $existing ) {
				$post['ID'] = $existing;
			}
			$id = wp_insert_attachment( wp_slash( $post ), $path, $parent_id, true );
		} else {
			$post = array(
				'post_type'      => Post_Types::LINK,
				'post_status'    => 'publish',
				'post_parent'    => $parent_id,
				'post_content'   => $content,
				'post_mime_type' => Attachment::URL === $attachment->type ? 'text/uri-list' : 'text/html',
			);
			if ( $existing ) {
				$post['ID'] = $existing;
			}
			$id = wp_insert_post( wp_slash( $post ), true );
		}
		if ( is_wp_error( $id ) ) {
			$this->report->problems[] = sprintf( 'Attachment %d couldn\'t be saved: %s', $attachment->id, $id->get_error_message() );
			return;
		}
		$this->seen[ $type ][ $attachment->id ] = $id;
		update_post_meta( $id, self::LEGACY_ID, $attachment->id );
		update_post_meta( $id, 'sb_downloads', $attachment->downloads );
		if ( '' === $attachment->duration ) {
			delete_post_meta( $id, 'sb_duration' );
		} else {
			update_post_meta( $id, 'sb_duration', wp_slash( $attachment->duration ) );
		}
	}

	/**
	 * Copies the files attached to no sermon (or to one that no longer exists).
	 */
	private function migrate_unattached_files() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT s.id, s.type, s.name, s.count, s.duration FROM {$wpdb->prefix}sb_stuff AS s LEFT JOIN {$wpdb->prefix}sb_sermons AS m ON m.id = s.sermon_id WHERE m.id IS NULL ORDER BY s.id" );
		foreach ( $rows as $row ) {
			if ( Attachment::FILE !== $row->type ) {
				$this->report->problems[] = sprintf( 'Attachment %d is a %s attached to no sermon, so it was left out.', $row->id, Attachment::URL === $row->type ? 'URL' : 'piece of embed code' );
				continue;
			}
			$this->save_attachment( new Attachment( (int) $row->id, Attachment::FILE, $row->name, (int) $row->count, (string) $row->duration ), 0 );
		}
	}

	/**
	 * Deletes what earlier runs migrated that has gone from the tables. Files stay where they are.
	 */
	private function delete_the_rest() {
		$kinds      = array(
			Post_Types::SERMON   => 'sermons',
			Post_Types::LINK     => 'URLs and embeds',
			'attachment'         => 'files',
			Post_Types::PREACHER => 'preachers',
			Post_Types::SERIES   => 'series',
			Post_Types::SERVICE  => 'services',
		);
		$keep_files = function () {
			return '';
		};
		add_filter( 'wp_delete_file', $keep_files );
		try {
			foreach ( $kinds as $type => $kind ) {
				foreach ( array_diff_key( $this->migrated[ $type ], $this->seen[ $type ] ) as $id ) {
					$this->report->count( $kind, Migration_Report::DELETED );
					if ( $this->report->dry_run ) {
						continue;
					}
					if ( taxonomy_exists( $type ) ) {
						wp_delete_term( $id, $type );
					} elseif ( 'attachment' === $type ) {
						wp_delete_attachment( $id, true );
					} else {
						wp_delete_post( $id, true );
					}
				}
			}
		} finally {
			remove_filter( 'wp_delete_file', $keep_files );
		}
	}

	/**
	 * Compares every sermon in the tables with what it was migrated to, allowing only for the changes
	 * the migration makes on purpose (see the class description).
	 *
	 * @return string[] The differences, one sentence each; none if the post types mirror the tables.
	 */
	public function verify() {
		global $wpdb;
		$this->find_migrated();
		$differences = array();
		$tables      = new Tables_Sermons();
		$posts       = new Posts_Sermons();
		$query       = new Sermon_Query();
		$found       = array();

		$query->ascending = true;
		$query->per_page  = self::BATCH;
		for ( $query->page = 1; ; $query->page++ ) {
			$page = $tables->find( $query );
			foreach ( $page->sermons as $sermon ) {
				$found[ $sermon->id ] = true;
				$id                   = $this->migrated[ Post_Types::SERMON ][ $sermon->id ] ?? 0;
				if ( ! $id ) {
					$differences[] = sprintf( 'Sermon %d wasn\'t migrated.', $sermon->id );
					continue;
				}
				$copy = $posts->get( $id );
				if ( ! $copy ) {
					$differences[] = sprintf( 'Sermon %d was migrated to post %d, which is %s, not published.', $sermon->id, $id, get_post_status( $id ) );
					continue;
				}
				foreach ( array_keys( array_diff_assoc( $this->describe( $sermon, true ), $this->describe( $copy, false ) ) ) as $field ) {
					$differences[] = sprintf( 'Sermon %d differs from post %d in its %s.', $sermon->id, $id, $field );
				}
			}
			if ( $query->page * self::BATCH >= $page->total ) {
				break;
			}
			if ( function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}
		}
		foreach ( array_diff_key( $this->migrated[ Post_Types::SERMON ], $found ) as $legacy_id => $id ) {
			$differences[] = sprintf( 'Post %d was migrated from sermon %d, which is no longer in the tables.', $id, $legacy_id );
		}

		$rows = array(
			Post_Types::PREACHER => "SELECT id, name FROM {$wpdb->prefix}sb_preachers",
			Post_Types::SERIES   => "SELECT id, name FROM {$wpdb->prefix}sb_series",
			Post_Types::SERVICE  => "SELECT id, name FROM {$wpdb->prefix}sb_services",
		);
		foreach ( $rows as $taxonomy => $sql ) {
			$ids = array();
			// $sql is fixed SQL above.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( $wpdb->get_results( $sql ) as $row ) {
				if ( '' !== trim( self::stored_name( stripslashes( $row->name ), $taxonomy ) ) ) {
					$ids[] = (int) $row->id;
				}
			}
			foreach ( array_diff( $ids, array_keys( $this->migrated[ $taxonomy ] ) ) as $legacy_id ) {
				$differences[] = sprintf( '%s %d wasn\'t migrated.', get_taxonomy( $taxonomy )->labels->singular_name, $legacy_id );
			}
			foreach ( array_diff( array_keys( $this->migrated[ $taxonomy ] ), $ids ) as $legacy_id ) {
				$differences[] = sprintf( 'Term %d was migrated from %s %d, which is no longer in the tables.', $this->migrated[ $taxonomy ][ $legacy_id ], strtolower( get_taxonomy( $taxonomy )->labels->singular_name ), $legacy_id );
			}
		}

		$attachments = new Posts_Attachments();
		foreach ( $wpdb->get_results( "SELECT s.id, s.name, s.count, s.duration FROM {$wpdb->prefix}sb_stuff AS s LEFT JOIN {$wpdb->prefix}sb_sermons AS m ON m.id = s.sermon_id WHERE m.id IS NULL AND s.type = 'file' ORDER BY s.id" ) as $row ) {
			$copy     = $attachments->find( Attachment::FILE, $row->name );
			$expected = $this->migrated['attachment'][ (int) $row->id ] ?? 0;
			if ( ! $copy || $expected !== $copy->id || (int) $row->count !== $copy->downloads || (string) $row->duration !== $copy->duration ) {
				$differences[] = sprintf( 'File %d, attached to no sermon, wasn\'t migrated as it is.', $row->id );
			}
		}
		return $differences;
	}

	/**
	 * Returns a name as WordPress stores it for a term: sanitized, as wp_insert_term() does.
	 *
	 * @param string $name     The name.
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	private static function stored_name( $name, $taxonomy ) {
		return wp_unslash( sanitize_term_field( 'name', wp_slash( $name ), 0, $taxonomy, 'db' ) );
	}

	/**
	 * Describes a sermon as field => text, to compare. For a sermon from the tables, applies the
	 * changes the migration makes on purpose.
	 *
	 * @param Sermon $sermon      The sermon.
	 * @param bool   $from_tables Whether it comes from the tables.
	 * @return array<string, string>
	 */
	private function describe( Sermon $sermon, $from_tables ) {
		$name = function ( $name, $taxonomy ) use ( $from_tables ) {
			return $from_tables ? self::stored_name( $name, $taxonomy ) : $name;
		};
		$kept = function ( $record, $taxonomy ) use ( $name ) {
			return $record && '' !== trim( $name( $record->name, $taxonomy ) ) ? $record : null;
		};
		$tags = array_filter(
			array_map(
				function ( $tag ) use ( $name ) {
					return $name( $tag, Post_Types::TAG );
				},
				$sermon->tags
			),
			function ( $tag ) {
				return '' !== trim( $tag );
			}
		);
		sort( $tags );
		$passages = array();
		foreach ( $sermon->passages as $passage ) {
			if ( null !== $passage->start->key() && null !== $passage->end->key() ) {
				$passages[] = $passage->reference();
			}
		}
		$attachments = array();
		foreach ( $sermon->attachments as $attachment ) {
			$content       = $from_tables && Attachment::URL === $attachment->type ? wp_specialchars_decode( $attachment->content, ENT_QUOTES ) : $attachment->content;
			$attachments[] = implode( "\n", array( $attachment->type, $content, $attachment->downloads, $attachment->duration ) );
		}
		$preacher = $kept( $sermon->preacher, Post_Types::PREACHER );
		$series   = $kept( $sermon->series, Post_Types::SERIES );
		$service  = $kept( $sermon->service, Post_Types::SERVICE );
		return array(
			'title'       => $from_tables ? trim( $sermon->title ) : $sermon->title,
			'description' => $sermon->description,
			'date'        => $sermon->date ? $sermon->date->format( 'Y-m-d H:i:s' ) : '',
			'time'        => $sermon->time,
			'preacher'    => $preacher ? implode(
				"\n",
				array(
					$name( $preacher->name, Post_Types::PREACHER ),
					$from_tables ? wp_unslash( sanitize_term_field( 'description', wp_slash( $preacher->description ), 0, Post_Types::PREACHER, 'db' ) ) : $preacher->description,
					$preacher->image,
				)
			) : '',
			'series'      => $series ? $name( $series->name, Post_Types::SERIES ) : '',
			'service'     => $service ? $name( $service->name, Post_Types::SERVICE ) . "\n" . $service->time : '',
			'passages'    => implode( "\n", $passages ),
			'tags'        => implode( "\n", $tags ),
			'attachments' => implode( "\n\n", $attachments ),
		);
	}
}
