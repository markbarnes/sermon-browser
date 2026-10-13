<?php
/**
 * Starts the plugin: hooks, requests it answers itself, installing and upgrading.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the feed, stylesheet and downloads are read-only GET requests

/**
 * Sets up the plugin's hooks. sermon.php calls load(); follow the hooks from there.
 */
class Plugin {

	/**
	 * Where the plugin lives, its documentation (the readme's FAQ) and its issues.
	 */
	const HOME_URL   = 'https://github.com/markbarnes/sermon-browser';
	const HELP_URL   = self::HOME_URL . '/blob/master/readme.txt';
	const ISSUES_URL = self::HOME_URL . '/issues';

	/**
	 * Adds the hooks that start everything else.
	 */
	public static function load() {
		add_action( 'init', array( self::class, 'handle_request' ), 0 );
		add_action( 'init', array( self::class, 'init' ) );
		add_action( 'widgets_init', array( Widgets\Recent_Sermons::class, 'register' ) );
		add_action( 'widgets_init', array( Widgets\Tag_Cloud::class, 'register' ) );
		add_action( 'widgets_init', array( Widgets\Popular::class, 'register' ) );
	}

	/**
	 * Answers the requests that aren't pages: admin AJAX, the stylesheet and downloads.
	 *
	 * Runs at the start of init, before the page is built (translations can't be loaded earlier).
	 */
	public static function handle_request() {
		global $wpdb; // ajax.php runs in this method's scope.

		wp_timezone_override_offset();

		if ( isset( $_POST['sermon'] ) && 1 === (int) $_POST['sermon'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ajax.php checks the nonce
			require SB_INCLUDES_DIR . '/ajax.php';
		}
		if ( ( isset( $_SERVER['REQUEST_URI'] ) && false !== stripos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'sb-style.css' ) ) || isset( $_GET['sb-style'] ) ) {
			Stylesheet::serve();
		}
		Downloads::serve();
	}

	/**
	 * Loads translations, sends the podcast feed if it's requested, registers scripts, styles and the
	 * shortcode, installs or upgrades if needed, and adds the hooks for the front end or the admin.
	 */
	public static function init() {
		// Translations bundled in sb-includes; installed as a must-use plugin, the plugin's files are directly in mu-plugins.
		if ( 'mu-plugins' === basename( dirname( SB_PLUGIN_FILE ) ) ) {
			load_muplugin_textdomain( 'sermon-browser', 'sb-includes' );
		} else {
			load_plugin_textdomain( 'sermon-browser', false, dirname( plugin_basename( SB_PLUGIN_FILE ) ) . '/sb-includes' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- the translations bundled in sb-includes
		}

		if ( defined( 'WPLANG' ) && WPLANG != '' ) {
			setlocale( LC_ALL, WPLANG . '.UTF-8' );
		}

		if ( isset( $_GET['podcast'] ) ) {
			Podcast::serve();
		}

		wp_register_script( 'sb_64', SB_PLUGIN_URL . '/sb-includes/64.js', false, SB_CURRENT_VERSION, false );
		wp_register_script( 'sb_datepicker', SB_PLUGIN_URL . '/sb-includes/datePicker.js', array( 'jquery' ), SB_CURRENT_VERSION, false );
		wp_register_style( 'sb_datepicker', SB_PLUGIN_URL . '/sb-includes/datepicker.css', false, SB_CURRENT_VERSION );
		if ( get_option( 'permalink_structure' ) == '' ) {
			wp_register_style( 'sb_style', trailingslashit( home_url() ) . '?sb-style&', false, sb_get_option( 'style_date_modified' ) );
		} else {
			wp_register_style( 'sb_style', trailingslashit( home_url() ) . 'sb-style.css', false, sb_get_option( 'style_date_modified' ) );
		}

		add_shortcode( 'sermons', array( Shortcode::class, 'render' ) );
		add_shortcode( 'sermon', array( Shortcode::class, 'render' ) );

		// Install, or upgrade the database, when an administrator next opens the admin.
		if ( current_user_can( 'manage_options' ) && is_admin() ) {
			if ( get_option( 'sb_sermon_db_version' ) ) {
				$db_version = get_option( 'sb_sermon_db_version' );
			} else {
				$db_version = sb_get_option( 'db_version' );
			}
			if ( $db_version && SB_DATABASE_VERSION != $db_version ) {
				require_once SB_INCLUDES_DIR . '/upgrade.php';
				sb_database_upgrade( $db_version );
			} elseif ( ! $db_version ) {
				require_once SB_INCLUDES_DIR . '/sb-install.php';
				sb_install();
			}
		}
		// Upgrade after a code update, on any request.
		$sb_version = sb_get_option( 'code_version' );
		if ( false !== $sb_version && SB_CURRENT_VERSION != $sb_version ) {
			require_once SB_INCLUDES_DIR . '/upgrade.php';
			sb_version_upgrade( $sb_version, SB_CURRENT_VERSION );
		}

		add_action( 'save_post', array( self::class, 'update_podcast_url' ) );

		if ( ! is_admin() ) {
			add_action( 'wp_head', array( Page_Head::class, 'add' ), 0 );
			add_action( 'wp_head', 'wp_print_styles', 9 );
			add_action( 'admin_bar_menu', array( Admin_Bar::class, 'add_menu' ), 45 );
			add_filter( 'wp_title', 'sb_page_title' );
		} else {
			require_once SB_INCLUDES_DIR . '/admin.php';
			add_action( 'admin_menu', 'sb_add_pages' );
			add_action( 'admin_head', 'sb_print_ajax_nonce' );
			add_action( 'rightnow_end', 'sb_rightnow' );
			add_action( 'admin_init', 'sb_add_admin_headers' );
			add_action( 'current_screen', 'sb_add_help_tab' );
		}
	}

	/**
	 * Updates the podcast URL when a post is saved, in case the permalinks changed or [sermons] moved to another page.
	 *
	 * Only a URL on this site is updated: one on another host (such as FeedBurner) was set on purpose.
	 */
	public static function update_podcast_url() {
		$existing_url = sb_get_option( 'podcast_url' );
		if ( substr( $existing_url, 0, strlen( site_url() ) ) == site_url() ) {
			if ( sb_display_url() == '' ) {
				sb_update_option( 'podcast_url', site_url() . sb_query_char( false ) . 'podcast' );
			} else {
				sb_update_option( 'podcast_url', sb_display_url() . sb_query_char( false ) . 'podcast' );
			}
		}
	}
}
