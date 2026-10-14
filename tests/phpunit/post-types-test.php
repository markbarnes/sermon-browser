<?php
/**
 * The sermon post type and its taxonomies, registered but hidden until sermons are stored in them.
 *
 * @package SermonBrowser
 */

use SermonBrowser\Post_Types;

/**
 * Tests SermonBrowser\Post_Types.
 */
class Post_Types_Test extends WP_UnitTestCase {

	/**
	 * The taxonomies, by name.
	 *
	 * @return array
	 */
	private static function taxonomies() {
		return array( Post_Types::PREACHER, Post_Types::SERIES, Post_Types::SERVICE, Post_Types::TAG, Post_Types::BOOK );
	}

	public function test_registers_the_sermon_post_type_and_its_taxonomies() {
		$this->assertSame( 'sb_sermon', Post_Types::SERMON );
		$this->assertTrue( post_type_exists( Post_Types::SERMON ) );
		$this->assertSame(
			array( 'sb_preacher', 'sb_series', 'sb_service', 'sb_sermon_tag', 'sb_bible_book' ),
			self::taxonomies()
		);
		foreach ( self::taxonomies() as $taxonomy ) {
			$this->assertTrue( taxonomy_exists( $taxonomy ), $taxonomy );
			$this->assertSame( array( Post_Types::SERMON ), get_taxonomy( $taxonomy )->object_type, $taxonomy );
		}
		$this->assertEqualsCanonicalizing( self::taxonomies(), get_object_taxonomies( Post_Types::SERMON ) );
	}

	public function test_keeps_the_sermon_post_type_out_of_sight() {
		$post_type = get_post_type_object( Post_Types::SERMON );
		$this->assertFalse( $post_type->public );
		$this->assertFalse( $post_type->publicly_queryable );
		$this->assertFalse( is_post_type_viewable( $post_type ) );
		$this->assertFalse( $post_type->show_ui );
		$this->assertFalse( $post_type->show_in_menu );
		$this->assertFalse( $post_type->show_in_nav_menus );
		$this->assertFalse( $post_type->show_in_admin_bar );
		$this->assertFalse( $post_type->show_in_rest );
		$this->assertTrue( $post_type->exclude_from_search );
		$this->assertFalse( $post_type->query_var );
		$this->assertFalse( $post_type->rewrite );
		$this->assertFalse( $post_type->has_archive );
		$this->assertFalse( $post_type->can_export );
	}

	public function test_keeps_sermons_when_their_author_is_deleted() {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$sermon = self::factory()->post->create(
			array(
				'post_type'   => Post_Types::SERMON,
				'post_author' => $author,
			)
		);
		wp_delete_user( $author );
		$this->assertNotNull( get_post( $sermon ) );
	}

	public function test_keeps_the_taxonomies_out_of_sight() {
		foreach ( self::taxonomies() as $name ) {
			$taxonomy = get_taxonomy( $name );
			$this->assertFalse( $taxonomy->public, $name );
			$this->assertFalse( $taxonomy->publicly_queryable, $name );
			$this->assertFalse( is_taxonomy_viewable( $taxonomy ), $name );
			$this->assertFalse( $taxonomy->show_ui, $name );
			$this->assertFalse( $taxonomy->show_in_menu, $name );
			$this->assertFalse( $taxonomy->show_in_nav_menus, $name );
			$this->assertFalse( $taxonomy->show_in_rest, $name );
			$this->assertFalse( $taxonomy->show_tagcloud, $name );
			$this->assertFalse( $taxonomy->show_admin_column, $name );
			$this->assertFalse( $taxonomy->query_var, $name );
			$this->assertFalse( $taxonomy->rewrite, $name );
			$this->assertFalse( $taxonomy->hierarchical, $name );
		}
	}

	public function test_adds_no_rewrite_rules() {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$rules = implode( "\n", array_merge( array_keys( $wp_rewrite->wp_rewrite_rules() ), array_values( $wp_rewrite->wp_rewrite_rules() ) ) );
		foreach ( array_merge( array( Post_Types::SERMON ), self::taxonomies() ) as $name ) {
			$this->assertStringNotContainsString( $name, $rules );
		}
	}

	public function test_stores_sermons_with_their_terms() {
		$sermon = self::factory()->post->create( array( 'post_type' => Post_Types::SERMON ) );
		wp_set_object_terms( $sermon, 'Dylan Jones', Post_Types::PREACHER );
		wp_set_object_terms( $sermon, 'Romans', Post_Types::BOOK );
		$this->assertSame( array( 'Dylan Jones' ), wp_get_object_terms( $sermon, Post_Types::PREACHER, array( 'fields' => 'names' ) ) );
		$this->assertSame( array( 'Romans' ), wp_get_object_terms( $sermon, Post_Types::BOOK, array( 'fields' => 'names' ) ) );
	}
}
