<?php
/**
 * Prints the search results and sermon page templates.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the sermon list's page number is a read-only GET value

/**
 * Prints a parsed Template for a list of sermons or for one sermon.
 *
 * Tags that can't work where they are print nothing, except to those who can edit the templates,
 * who see why instead, with a link to the Help page.
 */
class Template_Renderer {

	private Template $template;

	/**
	 * The shortcode's attributes, which the filters form uses.
	 */
	private array $atts;

	/**
	 * The page of sermons a sermon list shows, or null on a sermon page.
	 */
	private ?Sermon_Page $page;

	/**
	 * @param Template         $template Template.
	 * @param Sermon_Page|null $page     The sermons a list shows.
	 * @param array            $atts     The shortcode's attributes.
	 */
	private function __construct( Template $template, ?Sermon_Page $page, array $atts ) {
		$this->template = $template;
		$this->page     = $page;
		$this->atts     = $atts;
	}

	/**
	 * Returns a list of sermons, printed with the search results template.
	 *
	 * @param Template    $template A Template::SEARCH template.
	 * @param Sermon_Page $page     The page of sermons to list.
	 * @param array       $atts     The shortcode's attributes.
	 * @return string HTML.
	 */
	public static function search( Template $template, Sermon_Page $page, array $atts ) {
		return ( new self( $template, $page, $atts ) )->render( array() );
	}

	/**
	 * Returns a sermon, printed with the sermon page template.
	 *
	 * @param Template $template A Template::SINGLE template.
	 * @param Sermon   $sermon   The sermon.
	 * @return string HTML.
	 */
	public static function single( Template $template, Sermon $sermon ) {
		return ( new self( $template, null, array() ) )->render( array( Template::SERMON => $sermon ) );
	}

	/**
	 * Returns the template's output.
	 *
	 * @param array $context What the template is about: a Sermon under Template::SERMON on a sermon page.
	 * @return string
	 */
	private function render( array $context ) {
		ob_start();
		$this->nodes( $this->template->nodes, $context );
		return ob_get_clean();
	}

	/**
	 * Prints nodes.
	 *
	 * @param Template_Node[] $nodes   Nodes.
	 * @param array           $context The sermon, file, embed or passage the nodes are in, under Template's constants.
	 */
	private function nodes( array $nodes, array $context ) {
		foreach ( $nodes as $node ) {
			if ( '' !== $node->fault ) {
				$this->fault( $node );
			} elseif ( Template_Node::TEXT === $node->type ) {
				echo $node->value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template's own HTML, filtered with kses when saved
			} elseif ( Template_Node::LOOP === $node->type ) {
				$this->loop( $node, $context );
			} else {
				$this->tag( $node->value, $context );
			}
		}
	}

	/**
	 * Prints why a tag can't work where it is, to those who can edit the templates.
	 *
	 * @param Template_Node $node The faulty node.
	 */
	private function fault( Template_Node $node ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<span class="sb-template-error">' . esc_html__( 'Template error:', 'sermon-browser' ) . ' ' . esc_html( $this->template->fault_message( $node ) ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=sermon-browser/help.php' ) . '#template-errors' ) . '">' . esc_html__( 'Help', 'sermon-browser' ) . '</a></span>';
	}

	/**
	 * Prints a loop: what is inside it, once for each sermon, file, embed or passage.
	 *
	 * @param Template_Node $node    The loop.
	 * @param array         $context What the loop is in.
	 */
	private function loop( Template_Node $node, array $context ) {
		switch ( $node->value ) {
			case 'sermons_loop':
				foreach ( $this->page->sermons as $sermon ) {
					$this->nodes( $node->children, array_merge( $context, array( Template::SERMON => $sermon ) ) );
				}
				break;
			case 'files_loop':
				foreach ( self::files( $context[ Template::SERMON ] ) as $file ) {
					$this->nodes( $node->children, array_merge( $context, array( Template::FILE => $file ) ) );
				}
				break;
			case 'embed_loop':
				foreach ( self::embeds( $context[ Template::SERMON ] ) as $embed ) {
					$this->nodes( $node->children, array_merge( $context, array( Template::EMBED => $embed ) ) );
				}
				break;
			case 'passages_loop':
				// [passage] adds each passage to a list, printed where the loop ends; the rest prints for each passage.
				$references = new \ArrayObject();
				foreach ( $context[ Template::SERMON ]->passages as $passage ) {
					$this->nodes(
						$node->children,
						array_merge(
							$context,
							array(
								Template::PASSAGE => $passage,
								'references'      => $references,
							)
						)
					);
				}
				echo implode( ', ', $references->getArrayCopy() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- references are built from escaped book names
				break;
		}
	}

	/**
	 * Returns a sermon's files and URLs: files first, then URLs, each newest first.
	 *
	 * @param Sermon $sermon Sermon.
	 * @return string[] File names and URLs.
	 */
	private static function files( Sermon $sermon ) {
		$files = array();
		foreach ( array( Attachment::FILE, Attachment::URL ) as $type ) {
			foreach ( $sermon->attachments as $attachment ) {
				if ( $attachment->type === $type ) {
					$files[] = $attachment->content;
				}
			}
		}
		return $files;
	}

	/**
	 * Returns a sermon's embed code, newest first.
	 *
	 * @param Sermon $sermon Sermon.
	 * @return string[] HTML, unfiltered.
	 */
	private static function embeds( Sermon $sermon ) {
		$embeds = array();
		foreach ( $sermon->attachments as $attachment ) {
			if ( Attachment::EMBED === $attachment->type ) {
				$embeds[] = $attachment->content;
			}
		}
		return $embeds;
	}

	/**
	 * Prints a tag.
	 *
	 * @param string $name    The tag's name.
	 * @param array  $context What the tag is in.
	 */
	private function tag( $name, array $context ) {
		$method = 'tag_' . $name;
		if ( method_exists( $this, $method ) ) {
			$this->$method( isset( $context[ Template::SERMON ] ) ? $context[ Template::SERMON ] : null, $context );
		}
		// Any other tag is a Bible version that no longer exists, and prints nothing.
	}

	// The tags, in the order of the Templates page's help. Each takes the sermon (if there is one) and the context.

	private function tag_filters_form() {
		sb_print_filters( $this->atts );
	}

	private function tag_most_popular() {
		sb_print_most_popular();
	}

	private function tag_tag_cloud() {
		sb_print_tag_clouds();
	}

	private function tag_sermons_count() {
		echo (int) $this->page->total;
	}

	private function tag_previous_page() {
		sb_print_prev_page_link();
	}

	private function tag_next_page() {
		sb_print_next_page_link( 0, $this->page->total );
	}

	private function tag_podcast() {
		echo esc_url( sb_get_option( 'podcast_url' ) );
	}

	private function tag_podcast_for_search() {
		echo sb_podcast_url(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by sb_build_url()
	}

	private function tag_itunes_podcast() {
		echo esc_attr( preg_replace( '#^https?://#', 'itpc://', sb_get_option( 'podcast_url' ) ) );
	}

	private function tag_itunes_podcast_for_search() {
		echo preg_replace( '#^https?://#', 'itpc://', sb_podcast_url() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by sb_build_url()
	}

	private function tag_podcasticon() {
		echo '<img alt="Subscribe to full podcast" title="Subscribe to full podcast" class="podcasticon" src="' . esc_url( SB_PLUGIN_URL ) . '/sb-includes/icons/podcast.png"/>';
	}

	private function tag_podcasticon_for_search() {
		echo '<img alt="Subscribe to custom podcast" title="Subscribe to custom podcast" class="podcasticon" src="' . esc_url( SB_PLUGIN_URL ) . '/sb-includes/icons/podcast_custom.png"/>';
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_sermon_title( Sermon $sermon ) {
		if ( Template::SEARCH === $this->template->kind ) {
			echo '<a href="';
			sb_print_sermon_link( $sermon );
			echo '">' . esc_html( $sermon->title ) . '</a>';
		} else {
			echo esc_html( $sermon->title );
		}
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_sermon_description( Sermon $sermon ) {
		echo wpautop( $sermon->description ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filtered with kses when saved by users without unfiltered_html
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_preacher_link( Sermon $sermon ) {
		echo '<a href="';
		sb_print_preacher_link( $sermon );
		echo '">' . esc_html( $sermon->preacher ? $sermon->preacher->name : '' ) . '</a>';
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_series_link( Sermon $sermon ) {
		echo '<a href="';
		// A sermon page has always linked a sermon without a series to series=0.
		sb_print_series_link( $sermon, Template::SINGLE === $this->template->kind ? 0 : null );
		echo '">' . esc_html( $sermon->series ? $sermon->series->name : '' ) . '</a>';
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_service_link( Sermon $sermon ) {
		echo '<a href="';
		sb_print_service_link( $sermon );
		echo '">' . esc_html( $sermon->service ? $sermon->service->name : '' ) . '</a>';
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_preacher_description( Sermon $sermon ) {
		sb_print_preacher_description( $sermon );
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_preacher_image( Sermon $sermon ) {
		sb_print_preacher_image( $sermon );
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_date( Sermon $sermon ) {
		echo esc_html( sb_formatted_date( $sermon ) );
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_first_passage( Sermon $sermon ) {
		if ( $sermon->passages ) {
			echo $sermon->passages[0]->reference( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped book names
		}
	}

	/**
	 * @param Sermon $sermon  Sermon.
	 * @param array  $context Context, with the passage and the loop's list of references.
	 */
	private function tag_passage( Sermon $sermon, array $context ) {
		$context['references'][] = $context[ Template::PASSAGE ]->reference( true );
	}

	/**
	 * Returns the file a tag is about: the files loop's, or else the sermon's first.
	 *
	 * @param Sermon $sermon  Sermon.
	 * @param array  $context Context.
	 * @return string|null File name or URL.
	 */
	private static function file( Sermon $sermon, array $context ) {
		if ( isset( $context[ Template::FILE ] ) ) {
			return $context[ Template::FILE ];
		}
		$files = self::files( $sermon );
		return $files ? $files[0] : null;
	}

	/**
	 * @param Sermon $sermon  Sermon.
	 * @param array  $context Context.
	 */
	private function tag_file( Sermon $sermon, array $context ) {
		$file = self::file( $sermon, $context );
		if ( null !== $file ) {
			sb_print_url( $file );
		}
	}

	/**
	 * @param Sermon $sermon  Sermon.
	 * @param array  $context Context.
	 */
	private function tag_file_with_download( Sermon $sermon, array $context ) {
		$file = self::file( $sermon, $context );
		if ( null !== $file ) {
			sb_print_url_link( $file );
		}
	}

	/**
	 * @param Sermon $sermon  Sermon.
	 * @param array  $context Context.
	 */
	private function tag_embed( Sermon $sermon, array $context ) {
		if ( isset( $context[ Template::EMBED ] ) ) {
			$embed = $context[ Template::EMBED ];
		} else {
			$embeds = self::embeds( $sermon );
			$embed  = $embeds ? $embeds[0] : null;
		}
		if ( null !== $embed ) {
			sb_print_code( $embed );
		}
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_next_sermon( Sermon $sermon ) {
		sb_print_next_sermon_link( $sermon );
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_prev_sermon( Sermon $sermon ) {
		sb_print_prev_sermon_link( $sermon );
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_sameday_sermon( Sermon $sermon ) {
		sb_print_sameday_sermon_link( $sermon );
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_tags( Sermon $sermon ) {
		sb_print_tags( $sermon->tags );
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_esvtext( Sermon $sermon ) {
		foreach ( $sermon->passages as $passage ) {
			echo Bible::text( $passage, 'esv' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Bible::text()
		}
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_nettext( Sermon $sermon ) {
		foreach ( $sermon->passages as $passage ) {
			echo Bible::text( $passage, 'net' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Bible::text()
		}
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_biblepassage( Sermon $sermon ) {
		foreach ( $sermon->passages as $passage ) {
			echo "<p class='bible-passage'>" . wp_kses_post( $passage->reference() ) . '</p>';
		}
	}

	/**
	 * @param Sermon $sermon Sermon.
	 */
	private function tag_editlink( Sermon $sermon ) {
		sb_edit_link( $sermon->id );
	}

	private function tag_creditlink() {
		echo '<div id="poweredbysermonbrowser">Powered by <a href="' . esc_url( Plugin::HOME_URL ) . '">Sermon Browser</a></div>';
	}
}
