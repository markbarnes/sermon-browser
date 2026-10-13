<?php
/**
 * The template renderer prints what the compiled templates printed.
 *
 * @package SermonBrowser
 */

use SermonBrowser\Storage;
use SermonBrowser\Template;
use SermonBrowser\Template_Renderer;

/**
 * Renders templates with Template_Renderer and with the compiled templates (sb_compile_template()
 * and eval(), as SermonBrowser\Shortcode ran them up to 0.48), and compares the two.
 *
 * Only for templates the compiled templates print without warnings (other than deprecations
 * for sermons without a series, which they printed with any template): where they didn't, the
 * renderer behaves differently on purpose (tests/behaviour/template-errors.test.js). This test
 * goes when the compiler does.
 */
class Renderer_Matches_Compiled_Test extends WP_UnitTestCase {

	const SINGLE_IDS = array( 1, 2, 3, 4, 5, 6 );

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'Europe/London' );
		( new Tables_Fixture_Writer() )->write( require dirname( __DIR__ ) . '/fixtures/sermons.php' );
		require_once SB_INCLUDES_DIR . '/dictionary.php';
		require_once SB_INCLUDES_DIR . '/sb-install.php';
		sb_update_option(
			'popular_widget_options',
			array(
				'title'             => '',
				'limit'             => 5,
				'display_sermons'   => true,
				'display_series'    => true,
				'display_preachers' => true,
			)
		);
	}

	/**
	 * Returns the shortcode's attributes, as SermonBrowser\Shortcode gives them for a request with no filters.
	 *
	 * @return array
	 */
	private static function atts() {
		return array(
			'filter'     => sb_get_option( 'filter_type' ),
			'filterhide' => sb_get_option( 'filter_hide' ),
			'id'         => '',
			'preacher'   => '',
			'series'     => '',
			'book'       => '',
			'service'    => '',
			'date'       => '',
			'enddate'    => '',
			'tag'        => '',
			'title'      => '',
			'limit'      => '0',
			'dir'        => '',
		);
	}

	/**
	 * Audio players are numbered by how many the request has printed.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function without_player_ids( $html ) {
		return preg_replace( array( '/\b(audio|mejs)-\d+-\d+/', '/([?&;]_=)\d+/' ), array( '$1-N', '${1}N' ), $html );
	}

	/**
	 * Returns a sermon list printed with the compiled search results template.
	 *
	 * @param string $template Template.
	 * @param int    $page     Page number.
	 * @return string
	 */
	private static function compiled_search( $template, $page ) {
		global $record_count;
		$atts    = self::atts();
		$sermons = sb_get_sermons(
			$atts,
			array(
				'by'  => 'm.datetime',
				'dir' => 'desc',
			),
			$page,
			0,
			false
		);
		ob_start();
		// They passed null to stripslashes() for a sermon without a series.
		set_error_handler( fn() => true, E_DEPRECATED ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- tests only
		eval( '?>' . sb_compile_template( $template, sb_search_results_dictionary() ) ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- the compiled templates, to compare with
		restore_error_handler();
		return ob_get_clean();
	}

	/**
	 * Returns a sermon list printed with the renderer.
	 *
	 * @param string $template Template.
	 * @param int    $page     Page number.
	 * @return string
	 */
	private static function rendered_search( $template, $page ) {
		$sermons = Storage::sermons()->find(
			sb_sermon_query(
				self::atts(),
				array(
					'by'  => 'm.datetime',
					'dir' => 'desc',
				),
				$page,
				sb_get_option( 'sermons_per_page' )
			)
		);
		return Template_Renderer::search( Template::parse( $template, Template::SEARCH ), $sermons, self::atts() );
	}

	/**
	 * Returns a sermon page printed with the compiled sermon page template.
	 *
	 * @param string $template Template.
	 * @param int    $id       Sermon id.
	 * @return string
	 */
	private static function compiled_single( $template, $id ) {
		$sermon = sb_get_single_sermon( $id );
		ob_start();
		// They passed null to stripslashes() for a sermon without a series.
		set_error_handler( fn() => true, E_DEPRECATED ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- tests only
		eval( '?>' . sb_compile_template( $template, sb_sermon_page_dictionary() ) ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- the compiled templates, to compare with
		restore_error_handler();
		return ob_get_clean();
	}

	/**
	 * Compares a search results template's output, as a visitor and as an administrator, on the given pages.
	 *
	 * @param string $template Template.
	 * @param int[]  $pages    Page numbers.
	 */
	private function assert_search_matches( $template, array $pages = array( 1 ) ) {
		foreach ( array( 0, self::factory()->user->create( array( 'role' => 'administrator' ) ) ) as $user ) {
			wp_set_current_user( $user );
			foreach ( $pages as $page ) {
				$_REQUEST['pagenum'] = $page;
				$this->assertSame( self::without_player_ids( self::compiled_search( $template, $page ) ), self::without_player_ids( self::rendered_search( $template, $page ) ), "Page $page, user $user" );
			}
		}
		unset( $_REQUEST['pagenum'] );
	}

	/**
	 * Compares a sermon page template's output for each sermon, as a visitor and as an administrator.
	 *
	 * @param string $template Template.
	 */
	private function assert_single_matches( $template ) {
		foreach ( array( 0, self::factory()->user->create( array( 'role' => 'administrator' ) ) ) as $user ) {
			wp_set_current_user( $user );
			foreach ( self::SINGLE_IDS as $id ) {
				if ( ! sb_get_single_sermon( $id ) ) {
					continue;
				}
				$this->assertSame( self::without_player_ids( self::compiled_single( $template, $id ) ), self::without_player_ids( Template_Renderer::single( Template::parse( $template, Template::SINGLE ), sb_find_sermon( $id ) ) ), "Sermon $id, user $user" );
			}
		}
	}

	public function test_the_default_templates() {
		$this->assert_search_matches( sb_default_multi_template() );
		$this->assert_single_matches( sb_default_single_template() );
	}

	public function test_the_default_templates_with_files_downloadable_and_the_credit_link() {
		$this->assert_search_matches( str_replace( '[files_loop][file][/files_loop]', '[files_loop][file_with_download][/files_loop]', sb_default_multi_template() ) . "\r\n[creditlink]\r\n" );
		$this->assert_single_matches( sb_default_single_template() . "\r\n[creditlink]\r\n" );
	}

	public function test_each_search_results_tag() {
		$template = "<dl>\n";
		foreach ( array( 'filters_form', 'most_popular', 'tag_cloud', 'sermons_count', 'next_page', 'previous_page', 'podcast', 'podcast_for_search', 'itunes_podcast', 'itunes_podcast_for_search', 'podcasticon', 'podcasticon_for_search', 'creditlink' ) as $tag ) {
			$template .= "<dt>$tag</dt><dd>[$tag]</dd>\n";
		}
		$template .= "</dl>\n[sermons_loop]<dl>\n";
		foreach ( array( 'sermon_title', 'preacher_link', 'series_link', 'service_link', 'date', 'first_passage', 'editlink' ) as $tag ) {
			$template .= "<dt>$tag</dt><dd>[$tag]</dd>\n";
		}
		$template .= "<dd>[files_loop]<p>[file]</p>[/files_loop]</dd>\n<dd>[files_loop]<p>[file_with_download]</p>[/files_loop]</dd>\n<dd>[embed_loop]<p>[embed]</p>[/embed_loop]</dd>\n</dl>[/sermons_loop]\n";
		sb_update_option( 'sermons_per_page', 2 );
		$this->assert_search_matches( $template, array( 1, 2, 3 ) );
	}

	public function test_each_sermon_page_tag() {
		$template = "<dl>\n";
		// [esvtext] and [nettext] fetch text from other sites: tests/behaviour/bible-text.test.js covers them.
		foreach ( array( 'sermon_title', 'sermon_description', 'preacher_link', 'preacher_description', 'preacher_image', 'series_link', 'service_link', 'date', 'next_sermon', 'prev_sermon', 'sameday_sermon', 'tags', 'biblepassage', 'editlink', 'creditlink', 'kjvtext', 'asvtext', 'ylttext', 'webtext', 'akjvtext', 'hnvtext', 'lbrvtext', 'cornilescutext', 'synodaltext' ) as $tag ) {
			$template .= "<dt>$tag</dt><dd>[$tag]</dd>\n";
		}
		$template .= "<dd>[passages_loop][passage][/passages_loop]</dd>\n<dd>[files_loop]<p>[file]</p>[/files_loop]</dd>\n<dd>[files_loop]<p>[file_with_download]</p>[/files_loop]</dd>\n<dd>[embed_loop]<p>[embed]</p>[/embed_loop]</dd>\n</dl>\n";
		$this->assert_single_matches( $template );
	}

	/**
	 * The compiled templates ended most tags' code with ?>, after which PHP drops one line break.
	 */
	public function test_line_breaks_after_tags() {
		$breaks = array( "\n", "\r\n", "\r", "\n\n", "\r\n\r\n", " \n", "\t\r\n" );
		$search = '';
		foreach ( $breaks as $break ) {
			$search .= "[sermons_count]{$break}|[podcasticon]{$break}|[creditlink]{$break}|[sermons_loop]{$break}[sermon_title]{$break}[preacher_link]{$break}[series_link]{$break}[service_link]{$break}[date]{$break}[files_loop]{$break}[file]{$break}[/files_loop]{$break}[/sermons_loop]{$break}|";
		}
		$this->assert_search_matches( $search );
		$single = '';
		foreach ( $breaks as $break ) {
			$single .= "[sermon_title]{$break}[date]{$break}[passages_loop]{$break}[passage]{$break}[/passages_loop]{$break}[tags]{$break}[kjvtext]{$break}|";
		}
		$this->assert_single_matches( $single );
	}

	public function test_text_that_is_not_a_tag() {
		$this->assert_search_matches( "[sermon_description]|[next_sermon]|[Sermon_Title]|[ sermons_count ]|[unknown]|[[sermons_count]]|[sermons_count|[/sermons_count]|[]|[/]\n" );
		$this->assert_single_matches( "[sermons_count]|[filters_form]|[sermons_loop][sermon_title][/sermons_loop]|[first_passage]|[Date]|[[date]]\n" );
	}

	public function test_php_in_a_template_is_text() {
		$this->assert_search_matches( '<?php echo strrev( 1234 ); ?>|<?= 1 + 1 ?>|<? echo 2; ?>|?>|<?' );
		$this->assert_single_matches( '<?php echo strrev( 5678 ); ?>' );
	}

	public function test_tags_and_loops_used_more_than_once() {
		$this->assert_search_matches( '[sermons_count]/[sermons_count]<ol>[sermons_loop]<li>[sermon_title]</li>[/sermons_loop]</ol><ol>[sermons_loop]<li>[date][sermons_count]</li>[/sermons_loop]</ol>' );
		$this->assert_single_matches( '[date]/[date]<ol>[files_loop]<li>a</li>[/files_loop]</ol><ol>[files_loop]<li>b</li>[/files_loop]</ol><ol>[embed_loop]<li>c</li>[/embed_loop]</ol><ol>[embed_loop]<li>d</li>[/embed_loop]</ol>' );
	}

	public function test_loops_inside_loops() {
		$this->assert_search_matches( '[sermons_loop]<li>[files_loop]f[file][/files_loop]/[embed_loop]e[embed][/embed_loop]</li>[/sermons_loop]|[sermons_loop][sermons_loop]x[/sermons_loop][/sermons_loop]' );
		$this->assert_single_matches( '[files_loop]<li>[files_loop]<i>[file]</i>[/files_loop]</li>[/files_loop]|[passages_loop][files_loop]f[/files_loop][date][/passages_loop]' );
	}

	public function test_the_passages_loop() {
		$this->assert_single_matches( '<p>[passages_loop]<b>x</b>[passage]<i>y</i>[/passages_loop]</p><p>[passages_loop][passage][passage][/passages_loop]</p><p>[passages_loop][/passages_loop]</p>' );
	}

	public function test_an_empty_template() {
		$this->assert_search_matches( '' );
		$this->assert_single_matches( '' );
	}
}
