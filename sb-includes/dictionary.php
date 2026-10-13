<?php
if (!defined('ABSPATH'))
	exit;

/**
* Lists the search results template's tags, with its loops' opening and closing tags
*
* @return array '[tag]' => true
*/
function sb_search_results_dictionary() {
	return sb_template_tag_list(SermonBrowser\Template::SEARCH);
}

/**
* Lists the sermon page template's tags, with its loops' opening and closing tags
*
* @return array '[tag]' => true
*/
function sb_sermon_page_dictionary() {
	return sb_template_tag_list(SermonBrowser\Template::SINGLE);
}

/**
* Lists a template's tags (SermonBrowser\Template says what each needs)
*
* @param string $kind SermonBrowser\Template::SEARCH or SINGLE
* @return array '[tag]' => true
*/
function sb_template_tag_list($kind) {
	$tags = array();
	foreach (array_keys(SermonBrowser\Template::tags($kind)) as $tag)
		$tags["[{$tag}]"] = true;
	foreach (array_keys(SermonBrowser\Template::LOOPS[$kind]) as $loop) {
		$tags["[{$loop}]"] = true;
		$tags["[/{$loop}]"] = true;
	}
	return $tags;
}

/**
* Describes each template tag, for the help on the Templates page
*
* Closing tags ([/sermons_loop]) are described with their opening tag. Which template
* each tag works in comes from the dictionaries above.
* @return array tag => description
*/
function sb_template_tag_descriptions() {
	return array(
		'[filters_form]' => __('The search form, which filters sermons by preacher, series, service, Bible book and date (drop-down or one-click, as chosen on the Options page).', 'sermon-browser'),
		'[most_popular]' => __('The most popular sermons, series and preachers, as in the popular sermons widget.', 'sermon-browser'),
		'[tag_cloud]' => __('A cloud of the sermons\' tags, each linked to the sermons with that tag.', 'sermon-browser'),
		'[sermons_count]' => __('The number of sermons that match the current search.', 'sermon-browser'),
		'[sermons_loop]' => __('Put [sermons_loop] and [/sermons_loop] around the tags for one sermon: they are repeated for each sermon in the list.', 'sermon-browser'),
		'[first_passage]' => __('The sermon\'s first Bible passage.', 'sermon-browser'),
		'[previous_page]' => __('A link to the previous page of sermons, if there is one.', 'sermon-browser'),
		'[next_page]' => __('A link to the next page of sermons, if there is one.', 'sermon-browser'),
		'[podcast]' => __('The address of the podcast of all sermons.', 'sermon-browser'),
		'[podcast_for_search]' => __('The address of a podcast of the sermons that match the current search.', 'sermon-browser'),
		'[itunes_podcast]' => __('The podcast of all sermons, as an itpc:// address that opens in a podcast app.', 'sermon-browser'),
		'[itunes_podcast_for_search]' => __('The podcast of the sermons that match the current search, as an itpc:// address.', 'sermon-browser'),
		'[podcasticon]' => __('The podcast icon, for a link to the podcast of all sermons.', 'sermon-browser'),
		'[podcasticon_for_search]' => __('The podcast icon, for a link to the podcast of the current search.', 'sermon-browser'),
		'[sermon_title]' => __('The sermon\'s title (in the list, linked to the sermon\'s page).', 'sermon-browser'),
		'[sermon_description]' => __('The sermon\'s description.', 'sermon-browser'),
		'[preacher_link]' => __('The preacher\'s name, linked to their sermons.', 'sermon-browser'),
		'[preacher_description]' => __('The preacher\'s description.', 'sermon-browser'),
		'[preacher_image]' => __('The preacher\'s photo.', 'sermon-browser'),
		'[series_link]' => __('The series, linked to its sermons.', 'sermon-browser'),
		'[service_link]' => __('The service, linked to its sermons.', 'sermon-browser'),
		'[date]' => __('The date the sermon was preached.', 'sermon-browser'),
		'[passages_loop]' => __('Put [passages_loop] and [/passages_loop] around [passage] to show each of the sermon\'s Bible passages.', 'sermon-browser'),
		'[passage]' => __('A Bible passage, with the book linked to the sermons on it.', 'sermon-browser'),
		'[files_loop]' => __('Put [files_loop] and [/files_loop] around [file] or [file_with_download] to show each of the sermon\'s files and links. Without them, only the first is shown.', 'sermon-browser'),
		'[file]' => __('A file or link: a player for MP3s, otherwise a link with an icon for its type.', 'sermon-browser'),
		'[file_with_download]' => __('The same as [file], with a download link after the player for MP3 files.', 'sermon-browser'),
		'[embed_loop]' => __('Put [embed_loop] and [/embed_loop] around [embed] to show each of the sermon\'s embedded videos. Without them, only the first is shown.', 'sermon-browser'),
		'[embed]' => __('Embedded code, such as a video player, or a shortcode.', 'sermon-browser'),
		'[next_sermon]' => __('A link to the next sermon (not counting sermons preached the same day).', 'sermon-browser'),
		'[prev_sermon]' => __('A link to the previous sermon.', 'sermon-browser'),
		'[sameday_sermon]' => __('Links to other sermons preached the same day.', 'sermon-browser'),
		'[tags]' => __('The sermon\'s tags, each linked to the sermons with that tag.', 'sermon-browser'),
		'[esvtext]' => __('The text of the sermon\'s Bible passages in the ESV. Needs an ESV API key, entered on the Options page.', 'sermon-browser'),
		'[nettext]' => __('The text of the sermon\'s Bible passages in the NET Bible.', 'sermon-browser'),
		'[biblepassage]' => __('The references of the sermon\'s Bible passages, as plain text, for other Bible plugins to find.', 'sermon-browser'),
		'[editlink]' => __('A link to edit the sermon, shown only to users who can edit sermons.', 'sermon-browser'),
		'[creditlink]' => __('A "Powered by Sermon Browser" link to the plugin\'s home page.', 'sermon-browser'),
	);
}

/**
* Template tags for Bible versions that come from a service that no longer exists
*
* They are kept so that templates using them still work, but they print nothing.
* @return array
*/
function sb_retired_template_tags() {
	return array('[kjvtext]', '[asvtext]', '[ylttext]', '[webtext]', '[akjvtext]', '[hnvtext]', '[lbrvtext]', '[cornilescutext]', '[synodaltext]');
}
