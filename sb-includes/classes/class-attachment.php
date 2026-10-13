<?php
/**
 * Something attached to a sermon: a file, a URL or embed code.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Something attached to a sermon: a file in the upload folder, a URL, or embed code.
 */
class Attachment {

	const FILE  = 'file';
	const URL   = 'url';
	const EMBED = 'code';

	public int $id;

	/**
	 * One of the constants above.
	 */
	public string $type;

	/**
	 * The file name, the URL, or the embed code's HTML (unfiltered: filter it with sb_kses_embed() to print it).
	 */
	public string $content;

	/**
	 * How many times it has been downloaded or played through the plugin.
	 */
	public int $downloads;

	/**
	 * An MP3's length, as the podcast feed gives it (such as 34:12), once worked out; otherwise ''.
	 */
	public string $duration;

	/**
	 * @param int    $id        Id.
	 * @param string $type      One of the constants above.
	 * @param string $content   File name, URL or embed code.
	 * @param int    $downloads Download count.
	 * @param string $duration  Length, or ''.
	 */
	public function __construct( int $id, string $type, string $content, int $downloads = 0, string $duration = '' ) {
		$this->id        = $id;
		$this->type      = $type;
		$this->content   = $content;
		$this->downloads = $downloads;
		$this->duration  = $duration;
	}
}
