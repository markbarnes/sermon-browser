<?php
/**
 * Copying sermons from the plugin's tables to the post types.
 *
 * @package SermonBrowser
 */

use SermonBrowser\Attachment;
use SermonBrowser\Migration;
use SermonBrowser\Migration_Report;
use SermonBrowser\Post_Types;
use SermonBrowser\Sermon;
use SermonBrowser\Storage\Posts\Attachment_Repository as Posts_Attachments;
use SermonBrowser\Storage\Posts\Sermon_Repository as Posts_Sermons;
use SermonBrowser\Storage\Tables\Sermon_Repository as Tables_Sermons;

/**
 * Tests SermonBrowser\Migration, with the contract tests' fixtures in the tables.
 */
class Migration_Test extends WP_UnitTestCase {

	/**
	 * Files this test put in the upload folder, to remove afterwards.
	 *
	 * @var string[]
	 */
	private $files = array();

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'Europe/London' );
		$this->write( require __DIR__ . '/fixtures/sermons.php' );
	}

	public function tear_down() {
		foreach ( $this->files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- tests
			}
		}
		parent::tear_down();
	}

	/**
	 * Stores fixtures in the tables.
	 *
	 * @param array $fixtures As fixtures/sermons.php returns.
	 */
	private function write( array $fixtures ) {
		( new Tables_Fixture_Writer() )->write( $fixtures );
	}

	/**
	 * Returns the post or term a row was migrated to, by its legacy id.
	 *
	 * @param string $type      Post type or taxonomy.
	 * @param int    $legacy_id The row's id.
	 * @return int 0 if none.
	 */
	private function migrated( $type, $legacy_id ) {
		if ( taxonomy_exists( $type ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $type,
					'hide_empty' => false,
					'fields'     => 'ids',
					'meta_key'   => Migration::LEGACY_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- tests
					'meta_value' => $legacy_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- tests
				)
			);
			return $terms ? (int) $terms[0] : 0;
		}
		$posts = get_posts(
			array(
				'post_type'   => $type,
				'post_status' => 'any',
				'fields'      => 'ids',
				'meta_key'    => Migration::LEGACY_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- tests
				'meta_value'  => $legacy_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- tests
			)
		);
		return $posts ? (int) $posts[0] : 0;
	}

	/**
	 * Returns an attachment's id in the tables (they aren't the same in every test).
	 *
	 * @param string $name The file name, URL or embed code.
	 * @return int
	 */
	private static function stuff_id( $name ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb_stuff WHERE name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tests
	}

	/**
	 * Returns a sermon with every id set to 0, to compare sermons from different storage.
	 *
	 * @param Sermon $sermon The sermon.
	 * @return Sermon
	 */
	private static function without_ids( Sermon $sermon ) {
		$copy     = clone $sermon;
		$copy->id = 0;
		foreach ( array( 'preacher', 'series', 'service' ) as $field ) {
			if ( $copy->$field ) {
				$copy->$field     = clone $copy->$field;
				$copy->$field->id = 0;
			}
		}
		$copy->attachments = array_map(
			function ( $attachment ) {
				$attachment     = clone $attachment;
				$attachment->id = 0;
				return $attachment;
			},
			$copy->attachments
		);
		return $copy;
	}

	/**
	 * Returns the number of rows in each of the plugin's tables, and a checksum of each.
	 *
	 * @return array
	 */
	private static function tables() {
		global $wpdb;
		$state = array();
		foreach ( array( 'sb_preachers', 'sb_series', 'sb_services', 'sb_sermons', 'sb_stuff', 'sb_books_sermons', 'sb_sermons_tags', 'sb_tags' ) as $table ) {
			$state[ $table ] = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}{$table} ORDER BY id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- tests
		}
		return $state;
	}

	public function test_migrated_sermons_read_as_they_do_from_the_tables() {
		$report = ( new Migration() )->run();

		$tables = new Tables_Sermons();
		$posts  = new Posts_Sermons();
		foreach ( array( 1, 2, 3, 4, 5, 6 ) as $legacy_id ) {
			$id = $this->migrated( Post_Types::SERMON, $legacy_id );
			$this->assertNotSame( 0, $id, "sermon $legacy_id" );
			$this->assertEquals( self::without_ids( $tables->get( $legacy_id ) ), self::without_ids( $posts->get( $id ) ), "sermon $legacy_id" );
		}
		$this->assertSame( array(), ( new Migration() )->verify() );
		$this->assertSame( 6, $report->counts['sermons'][ Migration_Report::CREATED ] );
		$this->assertSame( 3, $report->counts['preachers'][ Migration_Report::CREATED ] );
		$this->assertSame( 2, $report->counts['series'][ Migration_Report::CREATED ] );
		$this->assertSame( 3, $report->counts['services'][ Migration_Report::CREATED ] );
		$this->assertSame( 3, $report->counts['files'][ Migration_Report::CREATED ] );
		$this->assertSame( 1, $report->counts['URLs'][ Migration_Report::CREATED ] );
		$this->assertSame( 1, $report->counts['embeds'][ Migration_Report::CREATED ] );
	}

	public function test_migrates_files_attached_to_no_sermon() {
		( new Migration() )->run();

		$file = ( new Posts_Attachments() )->find( Attachment::FILE, 'unlinked-sermon.mp3' );
		$this->assertNotNull( $file );
		$this->assertSame( 0, wp_get_post_parent_id( $file->id ) );
	}

	public function test_migrates_preachers_series_and_services_without_sermons() {
		global $wpdb;
		$wpdb->insert( "{$wpdb->prefix}sb_preachers", array( 'id' => 9, 'name' => 'Visiting preacher', 'description' => '', 'image' => 'visitor.jpg' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tests
		( new Migration() )->run();

		$term = $this->migrated( Post_Types::PREACHER, 9 );
		$this->assertSame( 'Visiting preacher', get_term( $term )->name );
		$this->assertSame( 'visitor.jpg', get_term_meta( $term, 'sb_image', true ) );
		$this->assertSame( '9:30', get_term_meta( $this->migrated( Post_Types::SERVICE, 3 ), 'sb_time', true ) );
	}

	public function test_only_reads_the_tables() {
		$before = self::tables();
		( new Migration() )->run();
		( new Migration() )->run();

		$this->assertSame( $before, self::tables() );
	}

	public function test_a_dry_run_writes_nothing_and_says_what_it_would_do() {
		$report = ( new Migration() )->run( true );

		$this->assertTrue( $report->dry_run );
		$this->assertSame( 6, $report->counts['sermons'][ Migration_Report::CREATED ] );
		$this->assertSame( 3, $report->counts['files'][ Migration_Report::CREATED ] );
		$this->assertSame( array(), get_posts( array( 'post_type' => array( Post_Types::SERMON, Post_Types::LINK, 'attachment' ), 'post_status' => 'any' ) ) );
		$this->assertSame( array(), get_terms( array( 'taxonomy' => Post_Types::PREACHER, 'hide_empty' => false ) ) );
	}

	public function test_running_again_updates_what_it_migrated_without_duplicating_it() {
		( new Migration() )->run();
		$sermon = $this->migrated( Post_Types::SERMON, 1 );
		$term   = $this->migrated( Post_Types::PREACHER, 1 );
		$file   = $this->migrated( 'attachment', self::stuff_id( '2026-03-01-faith-and-works.mp3' ) );
		$this->assertNotSame( 0, $file );

		$report = ( new Migration() )->run();

		$this->assertSame( 6, $report->counts['sermons'][ Migration_Report::UPDATED ] );
		$this->assertSame( 0, $report->counts['sermons'][ Migration_Report::CREATED ] );
		$this->assertSame( 0, $report->counts['sermons'][ Migration_Report::DELETED ] );
		$this->assertSame( $sermon, $this->migrated( Post_Types::SERMON, 1 ) );
		$this->assertSame( $term, $this->migrated( Post_Types::PREACHER, 1 ) );
		$this->assertSame( $file, $this->migrated( 'attachment', self::stuff_id( '2026-03-01-faith-and-works.mp3' ) ) );
		$this->assertCount( 6, get_posts( array( 'post_type' => Post_Types::SERMON, 'numberposts' => -1 ) ) );
		$this->assertCount( 3, get_terms( array( 'taxonomy' => Post_Types::PREACHER, 'hide_empty' => false ) ) );
		$this->assertCount( 4, get_terms( array( 'taxonomy' => Post_Types::TAG, 'hide_empty' => false ) ) );
		$this->assertSame( array(), ( new Migration() )->verify() );
	}

	public function test_running_again_follows_changes_in_the_tables() {
		global $wpdb;
		( new Migration() )->run();
		$removed_sermon = $this->migrated( Post_Types::SERMON, 2 );
		$removed_link   = $this->migrated( Post_Types::LINK, self::stuff_id( base64_encode( '<iframe src="https://player.vimeo.com/video/123" allowfullscreen></iframe>' ) ) );
		$this->assertNotSame( 0, $removed_link );

		$fixtures = require __DIR__ . '/fixtures/sermons.php';
		$fixtures['sermons'][1]['title']          = 'Faith and works';
		$fixtures['sermons'][1]['attachments'][0] = array( 'file', '2026-03-01-faith-and-works.mp3', 40, '34:12' );
		unset( $fixtures['sermons'][1]['attachments'][2] );
		$fixtures['sermons'][3]['passages'][]     = array( 'Jude', 1, 24, 'Jude', 1, 25 );
		$fixtures['sermons'][3]['tags']           = array( 'peace' );
		$fixtures['sermons'][5]['preacher']       = 1;
		unset( $fixtures['sermons'][2], $fixtures['preachers'][3] );
		$fixtures['sermons'][7] = $fixtures['sermons'][4];
		$this->write( $fixtures );

		$report = ( new Migration() )->run();

		$this->assertSame( array(), ( new Migration() )->verify() );
		$this->assertSame( 1, $report->counts['sermons'][ Migration_Report::CREATED ] );
		$this->assertSame( 1, $report->counts['sermons'][ Migration_Report::DELETED ] );
		$this->assertSame( 1, $report->counts['preachers'][ Migration_Report::DELETED ] );
		$this->assertNull( get_post( $removed_sermon ) );
		$this->assertNull( get_post( $removed_link ) );
		$posts = new Posts_Sermons();
		$this->assertSame( 'Faith and works', $posts->get( $this->migrated( Post_Types::SERMON, 1 ) )->title );
		$this->assertSame( array( 'Romans 5:1-11', 'Isaiah 53:4-6', 'Jude 1:24-25' ), array_map( function ( $passage ) { return $passage->reference(); }, $posts->get( $this->migrated( Post_Types::SERMON, 3 ) )->passages ) );
		$this->assertSame( array( 'peace' ), $posts->get( $this->migrated( Post_Types::SERMON, 3 ) )->tags );
	}

	public function test_deleting_a_files_attachment_leaves_the_file() {
		$folder = SB_ABSPATH . sb_get_option( 'upload_dir' );
		wp_mkdir_p( $folder );
		$this->files[] = $folder . 'migration-test-kept.mp3';
		file_put_contents( $this->files[0], 'ID3' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tests
		$fixtures                    = require __DIR__ . '/fixtures/sermons.php';
		$fixtures['unattached'][]    = array( 'file', 'migration-test-kept.mp3', 0, '' );
		$this->write( $fixtures );
		( new Migration() )->run();
		$this->assertNotNull( ( new Posts_Attachments() )->find( Attachment::FILE, 'migration-test-kept.mp3' ) );

		array_pop( $fixtures['unattached'] );
		$this->write( $fixtures );
		( new Migration() )->run();

		$this->assertNull( ( new Posts_Attachments() )->find( Attachment::FILE, 'migration-test-kept.mp3' ) );
		$this->assertFileExists( $this->files[0] );
	}

	public function test_decodes_urls_old_versions_saved_with_esc_url() {
		$fixtures = require __DIR__ . '/fixtures/sermons.php';
		$fixtures['sermons'][1]['attachments'][1][1] = 'https://example.org/play?id=1&#038;format=mp3';
		$this->write( $fixtures );
		( new Migration() )->run();

		$this->assertSame( 'https://example.org/play?id=1&format=mp3', ( new Posts_Sermons() )->get( $this->migrated( Post_Types::SERMON, 1 ) )->attachments[1]->content );
		$this->assertSame( array(), ( new Migration() )->verify() );
	}

	public function test_keeps_series_with_the_same_name_separate() {
		$fixtures                  = require __DIR__ . '/fixtures/sermons.php';
		$fixtures['series'][3]     = 'Romans';
		$fixtures['sermons'][5]['series'] = 3;
		$this->write( $fixtures );
		$report = ( new Migration() )->run();

		$this->assertNotSame( $this->migrated( Post_Types::SERIES, 1 ), $this->migrated( Post_Types::SERIES, 3 ) );
		$this->assertSame( 'Romans', get_term( $this->migrated( Post_Types::SERIES, 3 ) )->name );
		$this->assertStringContainsString( 'Series 3 has the same name as another', implode( "\n", $report->notes ) );
		$this->assertSame( array(), ( new Migration() )->verify() );
	}

	public function test_keeps_text_as_entered_and_leaves_term_names_and_descriptions_to_wordpress() {
		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}sb_preachers", array( 'name' => 'Aled O\\\'Brien & co', 'description' => '<p>Visiting <em>often</em>.</p>' ), array( 'id' => 3 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tests
		( new Migration() )->run();

		$sermon = ( new Posts_Sermons() )->get( $this->migrated( Post_Types::SERMON, 1 ) );
		$this->assertSame( "Paul's letter: Faith & Works", $sermon->title );
		$preacher = get_term( $this->migrated( Post_Types::PREACHER, 3 ) );
		$this->assertSame( "Aled O'Brien &amp; co", $preacher->name );
		$this->assertSame( 'Visiting <em>often</em>.', $preacher->description );
		$this->assertSame( array(), ( new Migration() )->verify() );
	}

	public function test_leaves_wordpress_to_trim_titles() {
		$fixtures                         = require __DIR__ . '/fixtures/sermons.php';
		$fixtures['sermons'][2]['title']  = ' Psalm 23 ';
		$this->write( $fixtures );
		( new Migration() )->run();

		$this->assertSame( 'Psalm 23', ( new Posts_Sermons() )->get( $this->migrated( Post_Types::SERMON, 2 ) )->title );
		$this->assertSame( array(), ( new Migration() )->verify() );
	}

	public function test_leaves_out_names_wordpress_cant_keep() {
		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}sb_series", array( 'name' => '<script>alert(1)</script>' ), array( 'id' => 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tests
		$report = ( new Migration() )->run();

		$this->assertStringContainsString( 'Series 1 ("<script>alert(1)</script>") has no name WordPress can keep', implode( "\n", $report->problems ) );
		$this->assertStringContainsString( 'Sermon 1\'s series (1, "<script>alert(1)</script>") wasn\'t migrated, so the sermon has none.', implode( "\n", $report->problems ) );
		$this->assertNull( ( new Posts_Sermons() )->get( $this->migrated( Post_Types::SERMON, 1 ) )->series );
		$this->assertSame( array(), ( new Migration() )->verify() );
	}

	public function test_delete_all_removes_the_copy_and_leaves_files_and_other_media() {
		$folder = SB_ABSPATH . sb_get_option( 'upload_dir' );
		wp_mkdir_p( $folder );
		$this->files[] = $folder . 'migration-test-delete-all.mp3';
		file_put_contents( $this->files[0], 'ID3' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tests
		$fixtures                 = require __DIR__ . '/fixtures/sermons.php';
		$fixtures['unattached'][] = array( 'file', 'migration-test-delete-all.mp3', 0, '' );
		$this->write( $fixtures );
		$other = self::factory()->attachment->create_object( 'photo.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
		( new Migration() )->run();

		Post_Types::delete_all();

		$this->assertSame( array(), get_posts( array( 'post_type' => array( Post_Types::SERMON, Post_Types::LINK ), 'post_status' => 'any' ) ) );
		$this->assertNull( ( new Posts_Attachments() )->find( Attachment::FILE, 'migration-test-delete-all.mp3' ) );
		foreach ( array( Post_Types::PREACHER, Post_Types::SERIES, Post_Types::SERVICE, Post_Types::TAG, Post_Types::BOOK ) as $taxonomy ) {
			$this->assertSame( array(), get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ), $taxonomy );
		}
		$this->assertFileExists( $this->files[0] );
		$this->assertNotNull( get_post( $other ) );
	}

	public function test_reports_what_it_cant_migrate() {
		global $wpdb;
		$fixtures                              = require __DIR__ . '/fixtures/sermons.php';
		$fixtures['services'][4]               = array( '', '11:00' );
		$fixtures['sermons'][3]['passages'][]  = array( 'Hezekiah', 1, 1, 'Hezekiah', 1, 2 );
		$fixtures['sermons'][2]['date']        = gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS );
		$fixtures['unattached'][]              = array( 'file', 'migration-test-missing.mp3', 0, '' );
		$this->write( $fixtures );
		$wpdb->insert( "{$wpdb->prefix}sb_stuff", array( 'type' => 'url', 'name' => 'https://example.org/orphan.mp3', 'sermon_id' => 0, 'count' => 0, 'duration' => '' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tests

		$report   = ( new Migration() )->run();
		$problems = implode( "\n", $report->problems );

		$this->assertStringContainsString( 'Service 4 ("") has no name WordPress can keep', $problems );
		$this->assertStringContainsString( 'Sermon 3\'s passage Hezekiah 1:1-2', $problems );
		$this->assertStringContainsString( 'Sermon 2 is dated', $problems );
		$this->assertMatchesRegularExpression( '/File \\d+ \\("migration-test-missing\\.mp3"\\) isn\'t in the upload folder/', $problems );
		$this->assertStringContainsString( 'is a URL attached to no sermon', $problems );
		$this->assertSame( 'future', get_post_status( $this->migrated( Post_Types::SERMON, 2 ) ) );
		$this->assertSame( array( 'Sermon 2 was migrated to post ' . $this->migrated( Post_Types::SERMON, 2 ) . ', which is future, not published.' ), ( new Migration() )->verify() );
	}

	public function test_verify_finds_what_differs() {
		( new Migration() )->run();
		$id = $this->migrated( Post_Types::SERMON, 1 );
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => 'Changed',
			)
		);
		$file = $this->migrated( 'attachment', self::stuff_id( '2026-03-01-faith-and-works.mp3' ) );
		$this->assertNotSame( 0, $file );
		update_post_meta( $file, 'sb_downloads', 99 );
		wp_delete_post( $this->migrated( Post_Types::SERMON, 4 ), true );
		wp_delete_term( $this->migrated( Post_Types::SERVICE, 2 ), Post_Types::SERVICE );

		$differences = ( new Migration() )->verify();

		$this->assertContains( "Sermon 1 differs from post $id in its title.", $differences );
		$this->assertContains( "Sermon 1 differs from post $id in its attachments.", $differences );
		$this->assertContains( 'Sermon 2 differs from post ' . $this->migrated( Post_Types::SERMON, 2 ) . ' in its service.', $differences );
		$this->assertContains( "Sermon 4 wasn't migrated.", $differences );
		$this->assertContains( "Service 2 wasn't migrated.", $differences );
	}
}
