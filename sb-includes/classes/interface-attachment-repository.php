<?php
/**
 * Where attachments are looked up, counted and measured.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Looks up attachments for downloads, and keeps their download counts and durations. Sermons
 * carry their attachments; this is for finding one by what it holds and changing it. Every storage
 * implements this, and passes the same contract tests (tests/phpunit/contracts/).
 */
interface Attachment_Repository {

	/**
	 * Returns the file or URL attachment that holds exactly this, attached to a sermon or not, or null.
	 *
	 * @param string $type    Attachment::FILE or Attachment::URL.
	 * @param string $content The file name or URL.
	 * @return Attachment|null
	 */
	public function find( $type, $content );

	/**
	 * Counts one more download or play of an attachment.
	 *
	 * @param Attachment $attachment The attachment.
	 */
	public function record_download( Attachment $attachment );

	/**
	 * Keeps the length of an attachment's audio, as the podcast feed gives it.
	 *
	 * @param Attachment $attachment The attachment.
	 * @param string     $duration   Its length, such as 34:12.
	 */
	public function set_duration( Attachment $attachment, $duration );
}
