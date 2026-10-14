<?php
/**
 * How sermons are stored in the post types and taxonomies.
 *
 * @package SermonBrowser
 */

use SermonBrowser\Post_Types;

/**
 * Tests Posts_Fixture_Writer, which stores sermons as the migration and the block editor will:
 * this is the storage format the post-type repositories read.
 */
class Posts_Fixture_Writer_Test extends WP_UnitTestCase {

	/**
	 * For 'sermons', 'preachers', 'series' and 'services', each fixture id => the id stored.
	 *
	 * @var array[]
	 */
	private $stored;

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'Europe/London' );
		$this->stored = ( new Posts_Fixture_Writer() )->write( require dirname( __DIR__ ) . '/fixtures/sermons.php' );
	}

	/**
	 * Returns a sermon's post.
	 *
	 * @param int $id The fixture's id.
	 * @return WP_Post
	 */
	private function post( $id ) {
		return get_post( $this->stored['sermons'][ $id ] );
	}

	/**
	 * Returns the names of a sermon's terms in a taxonomy, sorted.
	 *
	 * @param int    $id       The fixture's id.
	 * @param string $taxonomy Taxonomy.
	 * @return string[]
	 */
	private function terms( $id, $taxonomy ) {
		$names = wp_get_object_terms( $this->stored['sermons'][ $id ], $taxonomy, array( 'fields' => 'names' ) );
		sort( $names );
		return $names;
	}

	/**
	 * Returns a sermon's attachments and links, oldest first.
	 *
	 * @param int $sermon_id The post's id, or 0.
	 * @return WP_Post[]
	 */
	private function attachments( $sermon_id ) {
		return get_posts(
			array(
				'post_type'   => array( 'attachment', Post_Types::LINK ),
				'post_status' => 'any',
				'post_parent' => $sermon_id,
				'orderby'     => 'ID',
				'order'       => 'ASC',
				'numberposts' => -1,
				'meta_key'    => 'sb_downloads', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- tests
			)
		);
	}

	public function test_says_which_id_each_fixture_was_stored_with() {
		$this->assertSame( array( 'sermons', 'preachers', 'series', 'services' ), array_keys( $this->stored ) );
		$this->assertSame( array( 1, 2, 3, 4, 5, 6 ), array_keys( $this->stored['sermons'] ) );
		foreach ( $this->stored['sermons'] as $id ) {
			$this->assertSame( Post_Types::SERMON, get_post_type( $id ) );
		}
		$this->assertSame( 'Dylan Jones', get_term( $this->stored['preachers'][1] )->name );
		$this->assertSame( Post_Types::SERIES, get_term( $this->stored['series'][2] )->taxonomy );
		$this->assertSame( 'Christmas morning', get_term( $this->stored['services'][3] )->name );
	}

	public function test_stores_a_sermon_as_a_published_post_with_text_as_entered() {
		global $wpdb;
		$post = $this->post( 1 );

		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( "Paul's letter: Faith & Works", $post->post_title );
		$this->assertSame( '<p>Justified by grace <em>alone</em>, through faith.</p>', $post->post_content );
		$this->assertSame( '2026-03-01 10:30:00', $post->post_date );
		$this->assertSame( "Paul's letter: Faith & Works", $wpdb->get_var( $wpdb->prepare( "SELECT post_title FROM {$wpdb->posts} WHERE ID = %d", $post->ID ) ) );
		$this->assertSame( 'Gŵyl Dewi — the Lord’s my shepherd…', $this->post( 2 )->post_title );
	}

	public function test_stores_an_unknown_date_as_1970() {
		$this->assertSame( '1970-01-01 00:00:00', $this->post( 6 )->post_date );
	}

	public function test_stores_a_time_only_when_it_differs_from_the_service() {
		$this->assertSame( array( '09:45' ), get_post_meta( $this->post( 4 )->ID, 'sb_time' ) );
		$this->assertSame( array(), get_post_meta( $this->post( 1 )->ID, 'sb_time' ) );
	}

	public function test_stores_preachers_series_services_and_tags_as_terms() {
		$this->assertSame( array( 'Dylan Jones' ), $this->terms( 1, Post_Types::PREACHER ) );
		$this->assertSame( array( 'Romans' ), $this->terms( 1, Post_Types::SERIES ) );
		$this->assertSame( array( 'Sunday Morning' ), $this->terms( 1, Post_Types::SERVICE ) );
		$this->assertSame( array( 'faith', 'grace' ), $this->terms( 1, Post_Types::TAG ) );
		$this->assertSame( array(), $this->terms( 4, Post_Types::SERIES ) );
		$this->assertSame( array( 'The Psalms &#8211; songs for every season' ), $this->terms( 2, Post_Types::SERIES ) );
		$this->assertSame( array( "Aled O'Brien" ), $this->terms( 5, Post_Types::PREACHER ) );
		$this->assertCount( 1, get_terms( array( 'taxonomy' => Post_Types::TAG, 'name' => 'grace', 'hide_empty' => false ) ) );
	}

	public function test_stores_a_preachers_description_and_image_and_a_services_time() {
		$preacher = get_term( $this->stored['preachers'][1] );
		$this->assertSame( 'Pastor <em>since</em> 2015.', $preacher->description );
		$this->assertSame( 'profile.jpg', get_term_meta( $preacher->term_id, 'sb_image', true ) );
		$this->assertSame( '9:30', get_term_meta( $this->stored['services'][3], 'sb_time', true ) );
	}

	public function test_stores_each_passage_as_a_row_in_the_order_entered_with_the_first_to_sort_by() {
		$this->assertSame( array( '45005001-45005011', '23053004-23053006' ), get_post_meta( $this->post( 3 )->ID, 'sb_passage' ) );
		$this->assertSame( '45005001', get_post_meta( $this->post( 3 )->ID, 'sb_passage_sort', true ) );
		$this->assertSame( array( '43003016-43003016', '01001001-01002003' ), get_post_meta( $this->post( 5 )->ID, 'sb_passage' ) );
		$this->assertSame( array(), get_post_meta( $this->post( 4 )->ID, 'sb_passage' ) );
		$this->assertSame( '', get_post_meta( $this->post( 4 )->ID, 'sb_passage_sort', true ) );
	}

	public function test_stores_the_books_of_every_passage_as_terms() {
		$this->assertSame( array( 'Isaiah', 'Romans' ), $this->terms( 3, Post_Types::BOOK ) );
		$this->assertSame( array( 'Genesis', 'John' ), $this->terms( 5, Post_Types::BOOK ) );
		$this->assertSame( array(), $this->terms( 4, Post_Types::BOOK ) );
		$this->assertCount( 1, get_terms( array( 'taxonomy' => Post_Types::BOOK, 'name' => 'Romans', 'hide_empty' => false ) ) );
	}

	public function test_stores_files_in_the_media_library_where_they_are_and_links_as_children_oldest_first() {
		$attachments = $this->attachments( $this->post( 1 )->ID );

		$this->assertSame( array( 'attachment', Post_Types::LINK, Post_Types::LINK ), wp_list_pluck( $attachments, 'post_type' ) );
		$this->assertSame( array( 'audio/mpeg', 'text/uri-list', 'text/html' ), wp_list_pluck( $attachments, 'post_mime_type' ) );

		$this->assertSame( 'sermons/2026-03-01-faith-and-works.mp3', get_post_meta( $attachments[0]->ID, '_wp_attached_file', true ) );
		$this->assertSame( wp_upload_dir()['baseurl'] . '/sermons/2026-03-01-faith-and-works.mp3', wp_get_attachment_url( $attachments[0]->ID ) );
		$this->assertSame( 'https://example.org/sermons/faith-and-works.mp3', $attachments[1]->post_content );
		$this->assertSame( '<iframe src="https://player.vimeo.com/video/123" allowfullscreen></iframe>', $attachments[2]->post_content );

		$this->assertSame( array( '5', '2', '0' ), array_map( function ( $post ) { return get_post_meta( $post->ID, 'sb_downloads', true ); }, $attachments ) );
		$this->assertSame( array( '34:12', '', '' ), array_map( function ( $post ) { return get_post_meta( $post->ID, 'sb_duration', true ); }, $attachments ) );
	}

	public function test_stores_a_file_attached_to_no_sermon_without_a_parent() {
		$this->assertSame( array( 'sermons/unlinked-sermon.mp3' ), array_map( function ( $post ) { return get_post_meta( $post->ID, '_wp_attached_file', true ); }, $this->attachments( 0 ) ) );
	}

	public function test_writing_again_replaces_everything() {
		$fixtures = require dirname( __DIR__ ) . '/fixtures/sermons.php';
		unset( $fixtures['sermons'][6] );
		$stored = ( new Posts_Fixture_Writer() )->write( $fixtures );

		$this->assertCount( 5, get_posts( array( 'post_type' => Post_Types::SERMON, 'numberposts' => -1 ) ) );
		$this->assertCount( 3, get_terms( array( 'taxonomy' => Post_Types::PREACHER, 'hide_empty' => false ) ) );
		$this->assertCount( 4, get_terms( array( 'taxonomy' => Post_Types::TAG, 'hide_empty' => false ) ) );
		$this->assertCount( 1, $this->attachments( 0 ) );
		$this->assertNull( get_post( $this->stored['sermons'][1] ) );
		$this->assertSame( Post_Types::SERMON, get_post_type( $stored['sermons'][1] ) );
	}

	public function test_leaves_kses_as_it_found_it() {
		kses_init_filters();
		( new Posts_Fixture_Writer() )->write( require dirname( __DIR__ ) . '/fixtures/sermons.php' );
		$this->assertNotFalse( has_filter( 'content_save_pre', 'wp_filter_post_kses' ) );

		kses_remove_filters();
		( new Posts_Fixture_Writer() )->write( require dirname( __DIR__ ) . '/fixtures/sermons.php' );
		$this->assertFalse( has_filter( 'content_save_pre', 'wp_filter_post_kses' ) );
		kses_init_filters();
	}
}
