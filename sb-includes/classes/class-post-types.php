<?php
/**
 * The post types and taxonomies sermons are stored in.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the post types and taxonomies that sermons will be stored in instead of the plugin's
 * tables: sermons, with preachers, series, services, tags and Bible books as taxonomies; a sermon's
 * files as media library attachments; and its URLs and embed code as links, a post type of their own
 * whose posts are children of the sermon. Until the plugin reads from them they are hidden: no pages,
 * feeds, admin screens, REST routes or rewrite rules, so registering them changes nothing a visitor
 * or editor sees.
 */
class Post_Types {

	/**
	 * The post types' and taxonomies' names, prefixed so they can't clash with other plugins'.
	 */
	const SERMON   = 'sb_sermon';
	const LINK     = 'sb_link';
	const PREACHER = 'sb_preacher';
	const SERIES   = 'sb_series';
	const SERVICE  = 'sb_service';
	const TAG      = 'sb_sermon_tag';
	const BOOK     = 'sb_bible_book';

	/**
	 * Registers the post types and taxonomies. Runs on init, after the translations are loaded.
	 */
	public static function register() {
		$post_types = array(
			self::SERMON => array( __( 'Sermons', 'sermon-browser' ), __( 'Sermon', 'sermon-browser' ) ),
			self::LINK   => array( __( 'Sermon links', 'sermon-browser' ), __( 'Sermon link', 'sermon-browser' ) ),
		);
		foreach ( $post_types as $post_type => $labels ) {
			register_post_type(
				$post_type,
				array(
					'labels'              => array(
						'name'          => $labels[0],
						'singular_name' => $labels[1],
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
		}

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

	/**
	 * Deletes every sermon, link and term in the post types and taxonomies, and the media library's
	 * records of sermon files (attachments with a download count), leaving the files themselves.
	 */
	public static function delete_all() {
		global $wpdb;
		$keep_files = function () {
			return '';
		};
		add_filter( 'wp_delete_file', $keep_files );
		try {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- finds the plugin's attachments by meta, which WP_Query would load in full
			$posts = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type IN (%s, %s)
					UNION SELECT p.ID FROM {$wpdb->posts} AS p JOIN {$wpdb->postmeta} AS pm ON pm.post_id = p.ID AND pm.meta_key = 'sb_downloads' WHERE p.post_type = 'attachment'",
					self::SERMON,
					self::LINK
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			foreach ( $posts as $id ) {
				wp_delete_post( (int) $id, true );
			}
			foreach ( array( self::PREACHER, self::SERIES, self::SERVICE, self::TAG, self::BOOK ) as $taxonomy ) {
				$terms = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
						'fields'     => 'ids',
					)
				);
				foreach ( $terms as $term ) {
					wp_delete_term( $term, $taxonomy );
				}
			}
		} finally {
			remove_filter( 'wp_delete_file', $keep_files );
		}
	}
}
