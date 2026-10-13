<?php
/**
 * A preacher.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A preacher, as a sermon or a list of preachers gives it.
 */
class Preacher {

	public int $id;

	public string $name;

	/**
	 * HTML, as saved by an editor.
	 */
	public string $description;

	/**
	 * The image's file name in the upload folder's images folder, or ''.
	 */
	public string $image;

	/**
	 * @param int    $id          Id.
	 * @param string $name        Name.
	 * @param string $description Description (HTML).
	 * @param string $image       Image file name, or ''.
	 */
	public function __construct( int $id, string $name, string $description = '', string $image = '' ) {
		$this->id          = $id;
		$this->name        = $name;
		$this->description = $description;
		$this->image       = $image;
	}
}
