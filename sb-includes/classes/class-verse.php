<?php
/**
 * A verse: one end of a Bible passage.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A verse: one end of a Bible passage.
 */
class Verse {

	/**
	 * The book's English name, as in sb_get_default( 'eng_bible_books' ).
	 */
	public string $book;

	public int $chapter;

	public int $verse;

	/**
	 * @param string $book    The book's English name.
	 * @param int    $chapter Chapter.
	 * @param int    $verse   Verse.
	 */
	public function __construct( string $book, int $chapter, int $verse ) {
		$this->book    = $book;
		$this->chapter = $chapter;
		$this->verse   = $verse;
	}

	/**
	 * Returns the verse as a number in Bible order: the book's place in the Bible (1 to 66), then
	 * the chapter and the verse as three digits each, so Romans 3:21 is 45003021.
	 *
	 * @return int|null Null if the book isn't one of the 66, or the chapter or verse isn't 0 to 999.
	 */
	public function key() {
		$book = array_search( $this->book, sb_get_default( 'eng_bible_books' ), true );
		if ( false === $book || $this->chapter < 0 || $this->chapter > 999 || $this->verse < 0 || $this->verse > 999 ) {
			return null;
		}
		return ( $book + 1 ) * 1000000 + $this->chapter * 1000 + $this->verse;
	}

	/**
	 * Returns the verse a key() stands for.
	 *
	 * @param int $key A number from key().
	 * @return Verse|null Null if it isn't one.
	 */
	public static function from_key( int $key ) {
		$books = sb_get_default( 'eng_bible_books' );
		$book  = intdiv( $key, 1000000 ) - 1;
		if ( ! isset( $books[ $book ] ) ) {
			return null;
		}
		return new self( $books[ $book ], intdiv( $key % 1000000, 1000 ), $key % 1000 );
	}
}
