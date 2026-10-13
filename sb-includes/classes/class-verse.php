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
	 * Returns the verse as the array the template tags and SermonBrowser\Bible take.
	 *
	 * @return array Keys book, chapter and verse.
	 */
	public function to_array() {
		return array(
			'book'    => $this->book,
			'chapter' => $this->chapter,
			'verse'   => $this->verse,
		);
	}
}
