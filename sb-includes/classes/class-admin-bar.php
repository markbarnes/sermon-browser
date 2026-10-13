<?php
/**
 * The Sermons menu in the admin bar, on the front end.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reads the sermon being viewed

/**
 * Adds the Sermons menu to the admin bar, with the pages the user can open.
 */
class Admin_Bar {

	/**
	 * Adds the menu (admin_bar_menu action).
	 */
	public static function add_menu() {
		global $wp_admin_bar;
		if ( ! current_user_can( 'edit_posts' ) || ! class_exists( 'WP_Admin_Bar' ) ) {
			return;
		}
		if ( isset( $_GET['sermon_id'] ) && (int) $_GET['sermon_id'] != 0 && current_user_can( 'publish_pages' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'id'    => 'sermon-browser-menu',
					'title' => __( 'Edit Sermon', 'sermon-browser' ),
					'href'  => admin_url( 'admin.php?page=sermon-browser/new_sermon.php&mid=' . (int) $_GET['sermon_id'] ),
				)
			);
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'sermon-browser-menu',
					'id'     => 'sermon-browser-sermons',
					'title'  => __( 'List Sermons', 'sermon-browser' ),
					'href'   => admin_url( 'admin.php?page=sermon-browser/sermon.php' ),
				)
			);
		} else {
			$wp_admin_bar->add_menu(
				array(
					'id'    => 'sermon-browser-menu',
					'title' => __( 'Sermons', 'sermon-browser' ),
					'href'  => admin_url( 'admin.php?page=sermon-browser/sermon.php' ),
				)
			);
			if ( current_user_can( 'publish_pages' ) ) {
				$wp_admin_bar->add_menu(
					array(
						'parent' => 'sermon-browser-menu',
						'id'     => 'sermon-browser-add',
						'title'  => __( 'Add Sermon', 'sermon-browser' ),
						'href'   => admin_url( 'admin.php?page=sermon-browser/new_sermon.php' ),
					)
				);
			}
		}
		if ( current_user_can( 'upload_files' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'sermon-browser-menu',
					'id'     => 'sermon-browser-files',
					'title'  => __( 'Files', 'sermon-browser' ),
					'href'   => admin_url( 'admin.php?page=sermon-browser/files.php' ),
				)
			);
		}
		if ( current_user_can( 'manage_categories' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'sermon-browser-menu',
					'id'     => 'sermon-browser-preachers',
					'title'  => __( 'Preachers', 'sermon-browser' ),
					'href'   => admin_url( 'admin.php?page=sermon-browser/preachers.php' ),
				)
			);
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'sermon-browser-menu',
					'id'     => 'sermon-browser-series',
					'title'  => __( 'Series &amp; Services', 'sermon-browser' ),
					'href'   => admin_url( 'admin.php?page=sermon-browser/manage.php' ),
				)
			);
		}
		if ( current_user_can( 'manage_options' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'sermon-browser-menu',
					'id'     => 'sermon-browser-options',
					'title'  => __( 'Options', 'sermon-browser' ),
					'href'   => admin_url( 'admin.php?page=sermon-browser/options.php' ),
				)
			);
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'sermon-browser-menu',
					'id'     => 'sermon-browser-series',
					'title'  => __( 'Templates', 'sermon-browser' ),
					'href'   => admin_url( 'admin.php?page=sermon-browser/templates.php' ),
				)
			);
		}
		if ( current_user_can( 'edit_plugins' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'sermon-browser-menu',
					'id'     => 'sermon-browser-uninstall',
					'title'  => __( 'Uninstall', 'sermon-browser' ),
					'href'   => admin_url( 'admin.php?page=sermon-browser/uninstall.php' ),
				)
			);
		}
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'sermon-browser-menu',
				'id'     => 'sermon-browser-help',
				'title'  => __( 'Help', 'sermon-browser' ),
				'href'   => admin_url( 'admin.php?page=sermon-browser/help.php' ),
			)
		);
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'new-content',
				'id'     => 'sermon-browser-add2',
				'title'  => __( 'Sermon', 'sermon-browser' ),
				'href'   => admin_url( 'admin.php?page=sermon-browser/new_sermon.php' ),
			)
		);
	}
}
