<?php
/**
 * Verses as numbers in Bible order.
 *
 * @package SermonBrowser
 */

use SermonBrowser\Verse;

/**
 * Tests SermonBrowser\Verse::key() and Verse::from_key().
 */
class Verse_Test extends WP_UnitTestCase {

	public function test_key_is_the_books_place_in_the_bible_then_the_chapter_and_verse() {
		$this->assertSame( 1001001, ( new Verse( 'Genesis', 1, 1 ) )->key() );
		$this->assertSame( 19119176, ( new Verse( 'Psalm', 119, 176 ) )->key() );
		$this->assertSame( 45003021, ( new Verse( 'Romans', 3, 21 ) )->key() );
		$this->assertSame( 66022021, ( new Verse( 'Revelation', 22, 21 ) )->key() );
	}

	public function test_keys_sort_in_bible_order() {
		$verses = array( new Verse( 'Romans', 5, 1 ), new Verse( 'Genesis', 50, 26 ), new Verse( 'Romans', 3, 21 ), new Verse( 'Exodus', 1, 1 ), new Verse( 'Romans', 3, 9 ) );
		usort(
			$verses,
			function ( $a, $b ) {
				return $a->key() <=> $b->key();
			}
		);
		$this->assertSame( array( 'Genesis 50:26', 'Exodus 1:1', 'Romans 3:9', 'Romans 3:21', 'Romans 5:1' ), array_map( function ( $verse ) { return "{$verse->book} {$verse->chapter}:{$verse->verse}"; }, $verses ) );
	}

	public function test_key_is_null_for_a_verse_that_cant_have_one() {
		$this->assertNull( ( new Verse( 'Hezekiah', 1, 1 ) )->key() );
		$this->assertNull( ( new Verse( '', 1, 1 ) )->key() );
		$this->assertNull( ( new Verse( 'Psalm', 1000, 1 ) )->key() );
		$this->assertNull( ( new Verse( 'Psalm', 1, 1000 ) )->key() );
		$this->assertNull( ( new Verse( 'Psalm', -1, 1 ) )->key() );
	}

	public function test_from_key_gives_back_the_verse() {
		$this->assertEquals( new Verse( 'Romans', 3, 21 ), Verse::from_key( 45003021 ) );
		$this->assertEquals( new Verse( 'Revelation', 22, 21 ), Verse::from_key( 66022021 ) );
		$this->assertEquals( new Verse( 'Genesis', 0, 0 ), Verse::from_key( 1000000 ) );
		$this->assertNull( Verse::from_key( 67001001 ) );
		$this->assertNull( Verse::from_key( 0 ) );
	}
}
