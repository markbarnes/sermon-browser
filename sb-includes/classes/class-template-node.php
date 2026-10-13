<?php
/**
 * A piece of a parsed template.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A piece of a parsed template: text, a tag, a loop with what is inside it, or a closing tag with no loop to close.
 */
class Template_Node {

	const TEXT  = 'text';
	const TAG   = 'tag';
	const LOOP  = 'loop';
	const CLOSE = 'close';

	/**
	 * The tag can't work where it is: it needs a sermon or a passage, and there is none.
	 */
	const NOT_IN_CONTEXT = 'context';

	/**
	 * A loop that is never closed.
	 */
	const UNCLOSED = 'unclosed';

	/**
	 * A closing tag with no loop to close.
	 */
	const UNOPENED = 'unopened';

	/**
	 * One of TEXT, TAG, LOOP or CLOSE.
	 */
	public string $type;

	/**
	 * The text, or the tag's or loop's name (sermons_loop, not [sermons_loop]).
	 */
	public string $value;

	/**
	 * @var Template_Node[] What is inside a loop.
	 */
	public array $children = array();

	/**
	 * '' if the node is fine, otherwise one of NOT_IN_CONTEXT, UNCLOSED or UNOPENED.
	 */
	public string $fault = '';

	/**
	 * For NOT_IN_CONTEXT, what was missing: Template::SERMON or Template::PASSAGE.
	 */
	public string $missing = '';

	/**
	 * @param string $type  One of TEXT, TAG, LOOP or CLOSE.
	 * @param string $value The text, or the tag's name.
	 */
	public function __construct( string $type, string $value ) {
		$this->type  = $type;
		$this->value = $value;
	}

	/**
	 * Returns the tag as it is written in the template, such as [sermon_title] or [/files_loop].
	 *
	 * @return string
	 */
	public function tag() {
		return self::CLOSE === $this->type ? "[/{$this->value}]" : "[{$this->value}]";
	}
}
