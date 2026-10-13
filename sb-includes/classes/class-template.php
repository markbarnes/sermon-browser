<?php
/**
 * A search results or sermon page template, parsed.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A template from the Templates page, parsed into text, tags and loops, with any tags that can't
 * work where they are marked as faults. Template_Renderer prints it.
 *
 * Templates are HTML with [tags]. Only the tags of the template's own kind are tags; anything else
 * in square brackets is text. Loops ([sermons_loop] … [/sermons_loop]) repeat what is inside them.
 */
class Template {

	const SEARCH = 'search';
	const SINGLE = 'single';

	// What a tag needs to work: the page, a sermon, or a passage.
	const PAGE    = 'page';
	const SERMON  = 'sermon';
	const PASSAGE = 'passage';

	// What a loop gives the tags inside it, as well as what it is in.
	const FILE  = 'file';
	const EMBED = 'embed';

	/**
	 * The tags that print the same in either template, and what each needs.
	 *
	 * [file], [file_with_download] and [embed] need only a sermon: outside their loops they print the sermon's first.
	 */
	const SHARED_TAGS = array(
		'sermon_title'       => self::SERMON,
		'preacher_link'      => self::SERMON,
		'series_link'        => self::SERMON,
		'service_link'       => self::SERMON,
		'date'               => self::SERMON,
		'file'               => self::SERMON,
		'file_with_download' => self::SERMON,
		'embed'              => self::SERMON,
		'editlink'           => self::SERMON,
		'creditlink'         => self::PAGE,
	);

	const SEARCH_TAGS = array(
		'filters_form'              => self::PAGE,
		'most_popular'              => self::PAGE,
		'tag_cloud'                 => self::PAGE,
		'sermons_count'             => self::PAGE,
		'first_passage'             => self::SERMON,
		'next_page'                 => self::PAGE,
		'previous_page'             => self::PAGE,
		'podcast_for_search'        => self::PAGE,
		'podcast'                   => self::PAGE,
		'itunes_podcast'            => self::PAGE,
		'itunes_podcast_for_search' => self::PAGE,
		'podcasticon'               => self::PAGE,
		'podcasticon_for_search'    => self::PAGE,
	);

	const SINGLE_TAGS = array(
		'sermon_description'   => self::SERMON,
		'preacher_description' => self::SERMON,
		'preacher_image'       => self::SERMON,
		'passage'              => self::PASSAGE,
		'next_sermon'          => self::SERMON,
		'prev_sermon'          => self::SERMON,
		'sameday_sermon'       => self::SERMON,
		'tags'                 => self::SERMON,
		'esvtext'              => self::SERMON,
		'nettext'              => self::SERMON,
		'biblepassage'         => self::SERMON,
		// Versions from a service that no longer exists: they print nothing.
		'kjvtext'              => self::SERMON,
		'asvtext'              => self::SERMON,
		'ylttext'              => self::SERMON,
		'webtext'              => self::SERMON,
		'akjvtext'             => self::SERMON,
		'hnvtext'              => self::SERMON,
		'lbrvtext'             => self::SERMON,
		'cornilescutext'       => self::SERMON,
		'synodaltext'          => self::SERMON,
	);

	/**
	 * Each template's loops: what each needs, and what it gives the tags inside it.
	 */
	const LOOPS = array(
		self::SEARCH => array(
			'sermons_loop' => array( self::PAGE, self::SERMON ),
			'files_loop'   => array( self::SERMON, self::FILE ),
			'embed_loop'   => array( self::SERMON, self::EMBED ),
		),
		self::SINGLE => array(
			'passages_loop' => array( self::SERMON, self::PASSAGE ),
			'files_loop'    => array( self::SERMON, self::FILE ),
			'embed_loop'    => array( self::SERMON, self::EMBED ),
		),
	);

	/**
	 * Tags after which a line break is printed. The templates used to be compiled to PHP, and PHP
	 * drops a line break straight after ?>, which ended every other tag's code.
	 */
	const KEEP_LINE_BREAK_AFTER = array(
		self::SEARCH => array( 'sermon_title', 'preacher_link', 'series_link', 'service_link', 'podcasticon', 'podcasticon_for_search', 'creditlink' ),
		self::SINGLE => array( 'preacher_link', 'series_link', 'service_link', 'creditlink' ),
	);

	/**
	 * SEARCH or SINGLE.
	 */
	public string $kind;

	/**
	 * @var Template_Node[]
	 */
	public array $nodes = array();

	/**
	 * @param string $kind SEARCH or SINGLE.
	 */
	private function __construct( string $kind ) {
		$this->kind = $kind;
	}

	/**
	 * Returns a template's tags (not its loops), each with what it needs: PAGE, SERMON or PASSAGE.
	 *
	 * @param string $kind SEARCH or SINGLE.
	 * @return array
	 */
	public static function tags( $kind ) {
		return array_merge( self::SHARED_TAGS, self::SEARCH === $kind ? self::SEARCH_TAGS : self::SINGLE_TAGS );
	}

	/**
	 * Parses a template.
	 *
	 * @param string $text The template, as saved on the Templates page.
	 * @param string $kind SEARCH or SINGLE.
	 * @return Template
	 */
	public static function parse( $text, $kind ) {
		$template = new self( $kind );
		// Templates have never been able to run PHP: show any PHP tags in them as text.
		$text   = str_replace( array( '<?', '?>' ), array( '&lt;?', '?&gt;' ), (string) $text );
		$loops  = array_keys( self::LOOPS[ $kind ] );
		$opens  = implode( '|', array_merge( array_keys( self::tags( $kind ) ), $loops ) );
		$tokens = preg_split( '#(\[(?:' . $opens . ')\]|\[/(?:' . implode( '|', $loops ) . ')\])#', $text, -1, PREG_SPLIT_DELIM_CAPTURE );

		// The loops not closed yet, innermost last. Each node's children are where the next nodes go.
		$root  = new Template_Node( Template_Node::LOOP, '' );
		$stack = array( $root );
		foreach ( $tokens as $index => $token ) {
			if ( 0 === $index % 2 ) {
				if ( $index > 0 && ! in_array( trim( $tokens[ $index - 1 ], '[/]' ), self::KEEP_LINE_BREAK_AFTER[ $kind ], true ) ) {
					$token = preg_replace( '/^(\r\n|\n|\r)/', '', $token );
				}
				if ( '' !== $token ) {
					end( $stack )->children[] = new Template_Node( Template_Node::TEXT, $token );
				}
				continue;
			}
			$name = trim( $token, '[/]' );
			if ( '/' !== $token[1] ) {
				$node                     = new Template_Node( in_array( $name, $loops, true ) ? Template_Node::LOOP : Template_Node::TAG, $name );
				end( $stack )->children[] = $node;
				if ( Template_Node::LOOP === $node->type ) {
					$stack[] = $node;
				}
				continue;
			}
			// A closing tag closes the innermost loop of its name, and any loops inside that aren't closed.
			$open = array_reverse( array_slice( $stack, 1 ), true );
			$key  = array_search( $name, array_map( fn( $loop ) => $loop->value, $open ), true );
			if ( false === $key ) {
				$node                     = new Template_Node( Template_Node::CLOSE, $name );
				$node->fault              = Template_Node::UNOPENED;
				end( $stack )->children[] = $node;
				continue;
			}
			while ( count( $stack ) - 1 > $key + 1 ) {
				self::unclose( $stack );
			}
			array_pop( $stack );
		}
		while ( count( $stack ) > 1 ) {
			self::unclose( $stack );
		}
		$template->nodes = $root->children;
		$template->check( $template->nodes, self::SEARCH === $kind ? array( self::PAGE ) : array( self::PAGE, self::SERMON ) );
		return $template;
	}

	/**
	 * Marks the innermost open loop as never closed, and moves what is inside it to after it.
	 *
	 * @param Template_Node[] $stack The open loops.
	 */
	private static function unclose( array &$stack ) {
		$loop        = array_pop( $stack );
		$loop->fault = Template_Node::UNCLOSED;
		$parent      = end( $stack );
		array_push( $parent->children, ...$loop->children );
		$loop->children = array();
	}

	/**
	 * Marks the tags and loops that need what isn't there.
	 *
	 * @param Template_Node[] $nodes     Nodes.
	 * @param string[]        $available What the nodes are in: PAGE, SERMON and so on.
	 */
	private function check( array $nodes, array $available ) {
		$tags = self::tags( $this->kind );
		foreach ( $nodes as $node ) {
			if ( '' !== $node->fault ) {
				continue;
			}
			if ( Template_Node::TAG === $node->type ) {
				$needs = $tags[ $node->value ];
			} elseif ( Template_Node::LOOP === $node->type ) {
				$needs = self::LOOPS[ $this->kind ][ $node->value ][0];
			} else {
				continue;
			}
			if ( ! in_array( $needs, $available, true ) ) {
				$node->fault   = Template_Node::NOT_IN_CONTEXT;
				$node->missing = $needs;
			} elseif ( Template_Node::LOOP === $node->type ) {
				$this->check( $node->children, array_merge( $available, array( self::LOOPS[ $this->kind ][ $node->value ][1] ) ) );
			}
		}
	}

	/**
	 * Returns the tags that can't work where they are, in the order they appear.
	 *
	 * Inside a loop that can't work, only the loop is given.
	 *
	 * @param Template_Node[]|null $nodes Nodes to look through, or null for the whole template.
	 * @return Template_Node[]
	 */
	public function faults( $nodes = null ) {
		$faults = array();
		foreach ( null === $nodes ? $this->nodes : $nodes as $node ) {
			if ( '' !== $node->fault ) {
				$faults[] = $node;
			} elseif ( $node->children ) {
				array_push( $faults, ...$this->faults( $node->children ) );
			}
		}
		return $faults;
	}

	/**
	 * Explains why a tag can't work where it is.
	 *
	 * @param Template_Node $node A node from faults().
	 * @return string Text.
	 */
	public function fault_message( Template_Node $node ) {
		if ( Template_Node::UNCLOSED === $node->fault ) {
			/* translators: 1: a loop's opening tag, such as [sermons_loop], 2: its closing tag, such as [/sermons_loop] */
			return sprintf( __( '%1$s is not closed with %2$s.', 'sermon-browser' ), "[{$node->value}]", "[/{$node->value}]" );
		}
		if ( Template_Node::UNOPENED === $node->fault ) {
			/* translators: 1: a loop's closing tag, such as [/sermons_loop], 2: its opening tag, such as [sermons_loop] */
			return sprintf( __( '%1$s has no %2$s before it to close.', 'sermon-browser' ), "[/{$node->value}]", "[{$node->value}]" );
		}
		$loop = self::PASSAGE === $node->missing ? 'passages_loop' : 'sermons_loop';
		/* translators: 1: a template tag, such as [sermon_title], 2: a loop's opening tag, such as [sermons_loop], 3: its closing tag */
		return sprintf( __( '%1$s only works between %2$s and %3$s.', 'sermon-browser' ), $node->tag(), "[{$loop}]", "[/{$loop}]" );
	}
}
