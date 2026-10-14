<?php
/**
 * The sermon post type and its taxonomies.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the post type and taxonomies that sermons will be stored in instead of the plugin's
 * tables. Until the plugin reads from them they are hidden: no pages, feeds, admin screens,
 * REST routes or rewrite rules, so registering them changes nothing a visitor or editor sees.
 */
class Post_Types {

	/**
	 * The post type's and taxonomies' names, prefixed so they can't clash with other plugins'.
	 */
	const SERMON   = 'sb_sermon';
	const PREACHER = 'sb_preacher';
	const SERIES   = 'sb_series';
	const SERVICE  = 'sb_service';
	const TAG      = 'sb_sermon_tag';
	const BOOK     = 'sb_bible_book';

	/**
	 * Registers the post type and its taxonomies. Runs on init, after the translations are loaded.
	 */
	public static function register() {
		register_post_type(
			self::SERMON,
			array(
				'labels'              => array(
					'name'          => __( 'Sermons', 'sermon-browser' ),
					'singular_name' => __( 'Sermon', 'sermon-browser' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'has_archive'         => false,
				'can_export'          => false,
				'delete_with_user'    => false,
				'supports'            => array( 'title', 'editor' ),
			)
		);

		$taxonomies = array(
			self::PREACHER => array( __( 'Preachers', 'sermon-browser' ), __( 'Preacher', 'sermon-browser' ) ),
			self::SERIES   => array( __( 'Series', 'sermon-browser' ), __( 'Series', 'sermon-browser' ) ),
			self::SERVICE  => array( __( 'Services', 'sermon-browser' ), __( 'Service', 'sermon-browser' ) ),
			self::TAG      => array( __( 'Tags', 'sermon-browser' ), __( 'Tag', 'sermon-browser' ) ),
			self::BOOK     => array( __( 'Bible books', 'sermon-browser' ), __( 'Bible book', 'sermon-browser' ) ),
		);
		foreach ( $taxonomies as $taxonomy => $labels ) {
			register_taxonomy(
				$taxonomy,
				self::SERMON,
				array(
					'labels'             => array(
						'name'          => $labels[0],
						'singular_name' => $labels[1],
					),
					'public'             => false,
					'publicly_queryable' => false,
					'hierarchical'       => false,
					'show_ui'            => false,
					'show_in_menu'       => false,
					'show_in_nav_menus'  => false,
					'show_in_rest'       => false,
					'show_tagcloud'      => false,
					'show_in_quick_edit' => false,
					'show_admin_column'  => false,
					'query_var'          => false,
					'rewrite'            => false,
				)
			);
		}
	}
}
