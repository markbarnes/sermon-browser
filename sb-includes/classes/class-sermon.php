<?php
/**
 * A sermon, with its preacher, series, service, passages, tags and attachments.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A sermon, with everything attached to it. Repositories build these; nothing here depends on how sermons are stored.
 */
class Sermon {

	public int $id;

	public string $title;

	/**
	 * HTML, as saved by an editor (filtered with kses when saved by users without unfiltered_html).
	 */
	public string $description;

	/**
	 * When it was preached, in the site's time zone, or null if that isn't known.
	 */
	public ?\DateTimeImmutable $date;

	/**
	 * The start time entered for this sermon when it differs from its service's usual time, or ''.
	 */
	public string $time;

	public ?Preacher $preacher;

	public ?Series $series;

	public ?Service $service;

	/**
	 * @var Passage[] In the order they were entered.
	 */
	public array $passages;

	/**
	 * @var string[] Tag names, in alphabetical order.
	 */
	public array $tags;

	/**
	 * @var Attachment[] Newest first.
	 */
	public array $attachments;

	/**
	 * @param int                     $id          Id.
	 * @param string                  $title       Title.
	 * @param string                  $description Description (HTML).
	 * @param \DateTimeImmutable|null $date        When it was preached, or null.
	 * @param string                  $time        Start time when it differs from the service's, or ''.
	 * @param Preacher|null           $preacher    Preacher.
	 * @param Series|null             $series      Series.
	 * @param Service|null            $service     Service.
	 * @param Passage[]               $passages    Bible passages.
	 * @param string[]                $tags        Tag names.
	 * @param Attachment[]            $attachments Files, URLs and embed code.
	 */
	public function __construct( int $id, string $title, string $description, ?\DateTimeImmutable $date, string $time, ?Preacher $preacher, ?Series $series, ?Service $service, array $passages = array(), array $tags = array(), array $attachments = array() ) {
		$this->id          = $id;
		$this->title       = $title;
		$this->description = $description;
		$this->date        = $date;
		$this->time        = $time;
		$this->preacher    = $preacher;
		$this->series      = $series;
		$this->service     = $service;
		$this->passages    = $passages;
		$this->tags        = $tags;
		$this->attachments = $attachments;
	}

	/**
	 * Returns the total downloads of the sermon's attachments.
	 *
	 * @return int
	 */
	public function downloads() {
		return array_sum( wp_list_pluck( $this->attachments, 'downloads' ) );
	}
}
