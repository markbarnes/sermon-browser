<?php
/**
 * Printing templates: what the behaviour tests' seed doesn't reach.
 *
 * @package SermonBrowser
 */

use SermonBrowser\Storage;
use SermonBrowser\Template;
use SermonBrowser\Template_Renderer;

/**
 * Tests SermonBrowser\Template_Renderer with the storage-neutral fixtures.
 */
class Renderer_Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'Europe/London' );
		( new Tables_Fixture_Writer() )->write( require dirname( __DIR__ ) . '/fixtures/sermons.php' );
	}

	/**
	 * Returns a sermon page template's output for a fixture sermon.
	 *
	 * @param string $template Template.
	 * @param int    $id       Sermon id.
	 * @return string
	 */
	private static function single( $template, $id ) {
		return Template_Renderer::single( Template::parse( $template, Template::SINGLE ), Storage::sermons()->get( $id ) );
	}

	public function test_a_sermon_whose_date_is_unknown() {
		$this->assertSame( 'Unknown Date', self::single( '[date]', 6 ) );
	}

	public function test_a_sermon_without_a_series_or_passage() {
		$this->assertMatchesRegularExpression( '#^<a href="[^"]*series=0"></a>\|$#', self::single( '[series_link]|[passages_loop][passage][/passages_loop][biblepassage]', 4 ) );
	}

	public function test_a_passage_across_books_and_chapters() {
		$this->assertMatchesRegularExpression( '#^<a href="[^"]*book=John">John</a> 3:16, <a href="[^"]*book=Genesis">Genesis</a> 1:1-2:3$#', self::single( '[passages_loop][passage][/passages_loop]', 5 ) );
	}
}
