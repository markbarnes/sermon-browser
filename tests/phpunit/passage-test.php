<?php
/**
 * Bible references.
 *
 * @package SermonBrowser
 */

use SermonBrowser\Bible;
use SermonBrowser\Passage;
use SermonBrowser\Verse;

/**
 * Tests SermonBrowser\Passage::reference() and the array wrapper Bible::reference().
 */
class Passage_Test extends WP_UnitTestCase {

	/**
	 * Returns a passage.
	 *
	 * @param array $start Book, chapter, verse.
	 * @param array $end   Book, chapter, verse.
	 * @return Passage
	 */
	private static function passage( array $start, array $end ) {
		return new Passage( new Verse( ...$start ), new Verse( ...$end ) );
	}

	public function test_gives_the_shortest_reference() {
		$this->assertSame( 'John 3:16', self::passage( array( 'John', 3, 16 ), array( 'John', 3, 16 ) )->reference() );
		$this->assertSame( 'Romans 3:21-31', self::passage( array( 'Romans', 3, 21 ), array( 'Romans', 3, 31 ) )->reference() );
		$this->assertSame( 'Genesis 1:1-2:3', self::passage( array( 'Genesis', 1, 1 ), array( 'Genesis', 2, 3 ) )->reference() );
		$this->assertSame( 'Malachi 4:1 - Matthew 1:17', self::passage( array( 'Malachi', 4, 1 ), array( 'Matthew', 1, 17 ) )->reference() );
	}

	public function test_links_the_books_to_their_sermons() {
		$this->assertMatchesRegularExpression( '#^<a href="[^"]*book=Romans">Romans</a> 5:1-11$#', self::passage( array( 'Romans', 5, 1 ), array( 'Romans', 5, 11 ) )->reference( true ) );
		$this->assertMatchesRegularExpression( '#^<a href="[^"]*book=1%20John">1 John</a> 5:20 - <a href="[^"]*book=2%20John">2 John</a> 1:1$#', self::passage( array( '1 John', 5, 20 ), array( '2 John', 1, 1 ) )->reference( true ) );
	}

	public function test_gives_nothing_for_a_passage_without_a_book() {
		$this->assertSame( '', self::passage( array( ' ', 1, 1 ), array( '', 1, 1 ) )->reference() );
	}

	public function test_bible_reference_takes_the_arrays_older_code_passes() {
		$this->assertSame( 'Psalm 23:1-6', Bible::reference( array( 'book' => 'Psalm', 'chapter' => '23', 'verse' => '1' ), array( 'book' => 'Psalm', 'chapter' => 23, 'verse' => 6 ) ) );
		$this->assertSame( '', Bible::reference( array(), array() ) );
		$this->assertSame( '', Bible::reference( null, null ) );
	}
}
