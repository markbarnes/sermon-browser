<?php
/**
 * A Bible passage a sermon is on.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A Bible passage a sermon is on, from one verse to another.
 */
class Passage {

	public Verse $start;

	public Verse $end;

	/**
	 * @param Verse $start First verse.
	 * @param Verse $end   Last verse.
	 */
	public function __construct( Verse $start, Verse $end ) {
		$this->start = $start;
		$this->end   = $end;
	}

	/**
	 * Returns the reference, such as Romans 3:21-31 (not Romans 3:21-Romans 3:31), with the book names translated.
	 *
	 * @param bool $add_link Whether to link the book names to the sermons on that book.
	 * @return string HTML, or '' if the passage has no book.
	 */
	public function reference( $add_link = false ) {
		$start_name = trim( $this->start->book );
		$end_name   = trim( $this->end->book );
		if ( '' === $start_name ) {
			return '';
		}
		$translated_books = array_combine( sb_get_default( 'eng_bible_books' ), sb_get_default( 'bible_books' ) );
		$start_book       = esc_html( isset( $translated_books[ $start_name ] ) ? $translated_books[ $start_name ] : $start_name );
		$end_book         = esc_html( isset( $translated_books[ $end_name ] ) ? $translated_books[ $end_name ] : $end_name );
		$start            = $this->start;
		$end              = $this->end;
		if ( $add_link ) {
			$start_book = '<a href="' . sb_get_book_link( $start_name ) . "\">{$start_book}</a>";
			$end_book   = '<a href="' . sb_get_book_link( $end_name ) . "\">{$end_book}</a>";
		}
		if ( $start_book !== $end_book ) {
			return "$start_book {$start->chapter}:{$start->verse} - $end_book {$end->chapter}:{$end->verse}";
		}
		if ( $start->chapter !== $end->chapter ) {
			return "$start_book {$start->chapter}:{$start->verse}-{$end->chapter}:{$end->verse}";
		}
		if ( $start->verse !== $end->verse ) {
			return "$start_book {$start->chapter}:{$start->verse}-{$end->verse}";
		}
		return "$start_book {$start->chapter}:{$start->verse}";
	}

	/**
	 * Returns a passage from the arrays older code passes (keys book, chapter and verse), or null if they aren't a passage.
	 *
	 * @param mixed $start Start of the passage.
	 * @param mixed $end   End of the passage.
	 * @return Passage|null
	 */
	public static function from_arrays( $start, $end ) {
		if ( ! is_array( $start ) || ! is_array( $end ) || ! isset( $start['book'], $end['book'] ) ) {
			return null;
		}
		$verse = fn( $verse ) => new Verse( (string) $verse['book'], isset( $verse['chapter'] ) ? (int) $verse['chapter'] : 0, isset( $verse['verse'] ) ? (int) $verse['verse'] : 0 );
		return new self( $verse( $start ), $verse( $end ) );
	}
}
