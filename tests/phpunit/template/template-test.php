<?php
/**
 * Parsing templates, and finding the tags that can't work where they are.
 *
 * @package SermonBrowser
 */

use SermonBrowser\Template;
use SermonBrowser\Template_Node;

/**
 * Tests SermonBrowser\Template.
 */
class Template_Test extends WP_UnitTestCase {

	/**
	 * Returns a template's faults as tag => fault.
	 *
	 * @param string $text Template.
	 * @param string $kind Template::SEARCH or Template::SINGLE.
	 * @return string[]
	 */
	private static function faults( $text, $kind = Template::SEARCH ) {
		return array_map( fn( $node ) => $node->tag() . ' ' . $node->fault, Template::parse( $text, $kind )->faults() );
	}

	/**
	 * Returns parsed nodes as a nested array of strings: text as itself, tags in brackets, loops as arrays.
	 *
	 * @param Template_Node[] $nodes Nodes.
	 * @return array
	 */
	private static function shape( array $nodes ) {
		return array_map(
			fn( $node ) => Template_Node::LOOP === $node->type && '' === $node->fault ? array( $node->tag() => self::shape( $node->children ) ) : ( Template_Node::TEXT === $node->type ? $node->value : $node->tag() ),
			$nodes
		);
	}

	public function test_parses_text_tags_and_loops() {
		$template = Template::parse( 'a[sermons_count]b[sermons_loop]c[sermon_title][files_loop][file][/files_loop][/sermons_loop]d', Template::SEARCH );
		$this->assertSame( array( 'a', '[sermons_count]', 'b', array( '[sermons_loop]' => array( 'c', '[sermon_title]', array( '[files_loop]' => array( '[file]' ) ) ) ), 'd' ), self::shape( $template->nodes ) );
		$this->assertSame( array(), $template->faults() );
	}

	public function test_only_the_templates_own_tags_are_tags() {
		$this->assertSame( array( '[sermon_description][Sermon_Title][ sermons_count ][', '[sermons_count]', ']' ), self::shape( Template::parse( '[sermon_description][Sermon_Title][ sermons_count ][[sermons_count]]', Template::SEARCH )->nodes ) );
		$this->assertSame( array( '[sermons_count][sermons_loop]', '[sermon_title]', '[/sermons_loop]' ), self::shape( Template::parse( '[sermons_count][sermons_loop][sermon_title][/sermons_loop]', Template::SINGLE )->nodes ) );
	}

	public function test_php_tags_become_text() {
		$this->assertSame( array( '&lt;?php echo 1; ?&gt;' ), self::shape( Template::parse( '<?php echo 1; ?>', Template::SEARCH )->nodes ) );
	}

	public function test_drops_one_line_break_after_most_tags() {
		$this->assertSame( array( '[sermons_count]', "\n", '[sermons_count]', "x\n", '[sermons_count]', ' ', '[sermons_count]' ), self::shape( Template::parse( "[sermons_count]\n\n[sermons_count]\r\nx\n[sermons_count]\r [sermons_count]\r", Template::SEARCH )->nodes ) );
		$this->assertSame( array( '[creditlink]', "\n", '[podcasticon]', "\r\n" ), self::shape( Template::parse( "[creditlink]\n[podcasticon]\r\n", Template::SEARCH )->nodes ) );
	}

	public function test_a_tag_outside_its_loop_is_a_fault() {
		$this->assertSame( array( '[sermon_title] context', '[files_loop] context', '[embed] context' ), self::faults( '[sermon_title][files_loop][file][/files_loop][embed]' ) );
		$this->assertSame( array( '[passage] context' ), self::faults( '[passages_loop][passage][/passages_loop][passage]', Template::SINGLE ) );
		$this->assertSame( array( '[sermon_title] context' ), self::faults( '[sermons_loop][/sermons_loop][sermon_title]' ) );
	}

	public function test_file_and_embed_tags_need_only_a_sermon() {
		$this->assertSame( array(), self::faults( '[sermons_loop][file][file_with_download][embed][embed_loop][file][/embed_loop][/sermons_loop]' ) );
		$this->assertSame( array(), self::faults( '[file][file_with_download][embed][sermon_title][date]', Template::SINGLE ) );
	}

	public function test_a_loop_that_is_never_closed_is_a_fault_and_what_was_inside_it_is_checked_without_it() {
		$template = Template::parse( 'a[sermons_loop]b[sermon_title][files_loop]c', Template::SEARCH );
		$this->assertSame( array( 'a', '[sermons_loop]', 'b', '[sermon_title]', '[files_loop]', 'c' ), self::shape( $template->nodes ) );
		$this->assertSame( array( '[sermons_loop] unclosed', '[sermon_title] context', '[files_loop] unclosed' ), self::faults( 'a[sermons_loop]b[sermon_title][files_loop]c' ) );
	}

	public function test_a_closing_tag_closes_the_innermost_loop_of_its_name_and_any_unclosed_loops_inside_it() {
		$this->assertSame( array( '[files_loop] unclosed' ), self::faults( '[sermons_loop][files_loop][file][/sermons_loop]' ) );
		$this->assertSame( array( '[files_loop] unclosed', '[/files_loop] unopened' ), self::faults( '[sermons_loop][files_loop][/sermons_loop][/files_loop]' ) );
		$this->assertSame( array(), self::faults( '[sermons_loop][sermons_loop][/sermons_loop][/sermons_loop]' ) );
	}

	public function test_a_closing_tag_with_no_loop_to_close_is_a_fault() {
		$this->assertSame( array( '[/sermons_loop] unopened', '[/files_loop] unopened' ), self::faults( '[sermons_loop][/sermons_loop][/sermons_loop][/files_loop]' ) );
		$this->assertSame( array( '[/embed_loop] unopened' ), self::faults( '[/embed_loop]', Template::SINGLE ) );
	}

	public function test_only_a_loop_is_reported_when_it_cannot_work_where_it_is() {
		$this->assertSame( array( '[files_loop] context' ), self::faults( '[files_loop][file][sermon_title][/files_loop]' ) );
	}

	public function test_explains_each_fault() {
		$template = Template::parse( '[sermon_title][sermons_loop][/files_loop]', Template::SEARCH );
		$this->assertSame(
			array(
				'[sermon_title] only works between [sermons_loop] and [/sermons_loop].',
				'[sermons_loop] is not closed with [/sermons_loop].',
				'[/files_loop] has no [files_loop] before it to close.',
			),
			array_map( array( $template, 'fault_message' ), $template->faults() )
		);
		$template = Template::parse( '[passage]', Template::SINGLE );
		$this->assertSame( array( '[passage] only works between [passages_loop] and [/passages_loop].' ), array_map( array( $template, 'fault_message' ), $template->faults() ) );
	}
}
