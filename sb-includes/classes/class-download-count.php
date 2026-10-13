<?php
/**
 * How often a series' or preacher's attachments have been downloaded.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How often a series' or preacher's attachments have been downloaded, for the popular widget.
 */
class Download_Count {

	/**
	 * The series' or preacher's id.
	 */
	public int $id;

	public string $name;

	/**
	 * Downloads of all their sermons' attachments.
	 */
	public int $downloads;

	/**
	 * How many attachments their sermons have.
	 */
	public int $attachments;

	/**
	 * When their latest sermon with attachments was preached, or null if no date is known.
	 */
	public ?\DateTimeImmutable $latest;

	/**
	 * @param int                     $id          Id.
	 * @param string                  $name        Name.
	 * @param int                     $downloads   Downloads.
	 * @param int                     $attachments Attachments.
	 * @param \DateTimeImmutable|null $latest      Latest sermon's date.
	 */
	public function __construct( int $id, string $name, int $downloads, int $attachments, ?\DateTimeImmutable $latest ) {
		$this->id          = $id;
		$this->name        = $name;
		$this->downloads   = $downloads;
		$this->attachments = $attachments;
		$this->latest      = $latest;
	}

	/**
	 * Returns the average downloads per attachment.
	 *
	 * @return float
	 */
	public function average() {
		return $this->attachments ? $this->downloads / $this->attachments : 0.0;
	}
}
