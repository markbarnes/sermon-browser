<?php
/**
 * What to list: filters, order and page.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which sermons to list, in what order, and which page of them.
 *
 * Filters left at their defaults don't filter.
 */
class Sermon_Query {

	const BY_DATE     = 'date';
	const BY_TITLE    = 'title';
	const BY_PREACHER = 'preacher';
	const BY_PASSAGE  = 'passage';
	const BY_SERVICE  = 'service';
	const BY_SERIES   = 'series';
	const BY_ID       = 'id';

	/**
	 * Words to find in the title, the description or a tag's name.
	 */
	public string $text = '';

	public int $preacher = 0;

	public int $series = 0;

	public int $service = 0;

	/**
	 * A book's English name: sermons with any passage in it.
	 */
	public string $book = '';

	/**
	 * Sermons with a tag whose name contains this.
	 */
	public string $tag = '';

	/**
	 * Sermons preached at or after this moment: a date (meaning its midnight) or a date and time, in the site's time zone.
	 */
	public string $from = '';

	/**
	 * Sermons preached at or before this moment, as $from. A date alone means its midnight, so it leaves out that day's sermons.
	 */
	public string $to = '';

	/**
	 * Only this sermon.
	 */
	public int $id = 0;

	/**
	 * Only sermons with a file, URL or embed code.
	 */
	public bool $with_attachments = false;

	/**
	 * One of the BY_ constants. Ties are listed newest first.
	 */
	public string $order_by = self::BY_DATE;

	/**
	 * Ascending, or (false) descending.
	 */
	public bool $ascending = false;

	/**
	 * Page number, from 1.
	 */
	public int $page = 1;

	public int $per_page = 10;
}
