<?php
/**
 * The sermons the contract tests use, independent of storage: a Fixture_Writer stores them.
 *
 * They include what real sermon data has: apostrophes, typographic punctuation, an HTML entity
 * in a series name, Welsh characters, several passages, a passage across chapters, a sermon
 * without a passage, series or tags, a time that differs from the service's, and an unknown date.
 *
 * @package SermonBrowser
 */

return array(
	'preachers' => array(
		1 => array( 'Dylan Jones', '<p>Pastor since 2015.</p>', 'profile.jpg' ),
		2 => array( 'Rhiannon Llŷr', '', '' ),
		3 => array( "Aled O'Brien", '', '' ),
	),
	'series'    => array(
		1 => 'Romans',
		2 => 'The Psalms &#8211; songs for every season',
	),
	'services'  => array(
		1 => array( 'Sunday Morning', '10:30' ),
		2 => array( 'Sunday Evening', '18:00' ),
		3 => array( 'Christmas morning', '9:30' ),
	),
	// Passages are array( book, chapter, verse, end book, end chapter, end verse ).
	// Attachments are array( type, content, downloads, duration ), oldest first.
	'sermons'   => array(
		1 => array(
			'title'       => "Paul's letter: Faith & Works",
			'description' => '<p>Justified by grace <em>alone</em>, through faith.</p>',
			'date'        => '2026-03-01 10:30:00',
			'time'        => '',
			'preacher'    => 1,
			'series'      => 1,
			'service'     => 1,
			'passages'    => array( array( 'Romans', 3, 21, 'Romans', 3, 31 ) ),
			'tags'        => array( 'grace', 'faith' ),
			'attachments' => array(
				array( 'file', '2026-03-01-faith-and-works.mp3', 5, '34:12' ),
				array( 'url', 'https://example.org/sermons/faith-and-works.mp3', 2, '' ),
				array( 'code', '<iframe src="https://player.vimeo.com/video/123" allowfullscreen></iframe>', 0, '' ),
			),
		),
		2 => array(
			'title'       => 'Gŵyl Dewi — the Lord’s my shepherd…',
			'description' => "Psalm 23 for St David's Day.",
			'date'        => '2026-03-01 18:00:00',
			'time'        => '',
			'preacher'    => 2,
			'series'      => 2,
			'service'     => 2,
			'passages'    => array( array( 'Psalm', 23, 1, 'Psalm', 23, 6 ) ),
			'tags'        => array( 'psalms' ),
			'attachments' => array(),
		),
		3 => array(
			'title'       => 'Romans 5: peace with God',
			'description' => '',
			'date'        => '2026-03-08 10:30:00',
			'time'        => '',
			'preacher'    => 1,
			'series'      => 1,
			'service'     => 1,
			'passages'    => array( array( 'Romans', 5, 1, 'Romans', 5, 11 ), array( 'Isaiah', 53, 4, 'Isaiah', 53, 6 ) ),
			'tags'        => array( 'grace' ),
			'attachments' => array( array( 'file', '2026-03-08-peace-with-god.mp3', 0, '' ) ),
		),
		4 => array(
			'title'       => 'Christmas morning: Unto us a child is born',
			'description' => '',
			'date'        => '2025-12-25 09:45:00',
			'time'        => '09:45',
			'preacher'    => 1,
			'series'      => 0,
			'service'     => 3,
			'passages'    => array(),
			'tags'        => array(),
			'attachments' => array(),
		),
		5 => array(
			'title'       => 'A sermon on new birth',
			'description' => '',
			'date'        => '2026-03-15 19:00:00',
			'time'        => '',
			'preacher'    => 3,
			'series'      => 0,
			'service'     => 1,
			'passages'    => array( array( 'John', 3, 16, 'John', 3, 16 ), array( 'Genesis', 1, 1, 'Genesis', 2, 3 ) ),
			'tags'        => array( 'new birth' ),
			'attachments' => array(),
		),
		6 => array(
			'title'       => 'An undated sermon',
			'description' => '',
			'date'        => null,
			'time'        => '',
			'preacher'    => 2,
			'series'      => 0,
			'service'     => 1,
			'passages'    => array(),
			'tags'        => array(),
			'attachments' => array(),
		),
	),
);
