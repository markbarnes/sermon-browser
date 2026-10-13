<?php
if (!defined('ABSPATH'))
	exit;

/**
* Compiles a template into the PHP code that SermonBrowser\Shortcode::render() runs
*
* Templates are HTML with [tags], and only the dictionary's code is allowed to run.
* Any PHP tags in the template itself are turned into harmless text.
* @param string $template
* @param array $dictionary
* @return string
*/
function sb_compile_template($template, $dictionary) {
	$template = str_replace(array('<?', '?>'), array('&lt;?', '?&gt;'), (string) $template);
	return strtr($template, $dictionary);
}

function sb_search_results_dictionary() {
	return array(
		'[filters_form]' => '<?php sb_print_filters($atts) ?>',
		'[most_popular]' => '<?php sb_print_most_popular() ?>',
		'[tag_cloud]' => '<?php sb_print_tag_clouds() ?>',
		'[sermons_count]' => '<?php echo (int) $record_count ?>',
		'[sermons_loop]' => '<?php foreach ($sermons as $sermon){ $media = sb_get_stuff($sermon); ?>',
		'[/sermons_loop]' => '<?php } ?>',
		'[sermon_title]' => '<a href="<?php sb_print_sermon_link($sermon) ?>"><?php echo esc_html(stripslashes($sermon->title)) ?></a>',
		'[preacher_link]' => '<a href="<?php sb_print_preacher_link($sermon) ?>"><?php echo esc_html(stripslashes($sermon->preacher)) ?></a>',
		'[series_link]' => '<a href="<?php sb_print_series_link($sermon) ?>"><?php echo esc_html(stripslashes($sermon->series)) ?></a>',
		'[service_link]' => '<a href="<?php sb_print_service_link($sermon) ?>"><?php echo esc_html(stripslashes($sermon->service)) ?></a>',
		'[date]' => '<?php echo sb_formatted_date ($sermon) ?>',
		'[first_passage]' => '<?php $foo = sb_unserialize($sermon->start); $bar = sb_unserialize($sermon->end); if (isset($foo[0]) && isset($bar[0])) echo sb_get_books($foo[0], $bar[0]) ?>',
		'[files_loop]' => '<?php foreach ((array) $media as $media_type => $media_names) { if (is_array($media_names) && $media_type != "Code") { foreach ((array)$media_names as $media_name) { ?>',
		'[/files_loop]' => '<?php } } } ?>',
		'[file]' => '<?php sb_print_url($media_name) ?>',
		'[file_with_download]' => '<?php sb_print_url_link($media_name) ?>',
		'[embed_loop]' => '<?php foreach ((array) $media as $media_type => $media_names) { if (is_array($media_names) && $media_type == "Code") { foreach ((array)$media_names as $media_name) { ?>',
		'[/embed_loop]' => '<?php } } } ?>',
		'[embed]' => '<?php sb_print_code($media_name) ?>',
		'[next_page]' => '<?php sb_print_next_page_link() ?>',
		'[previous_page]' => '<?php sb_print_prev_page_link() ?>',
		'[podcast_for_search]' => '<?php echo sb_podcast_url() ?>',
		'[podcast]' => '<?php echo esc_url(sb_get_option("podcast_url")) ?>',
		'[itunes_podcast]' => '<?php echo esc_attr(preg_replace("#^https?://#", "itpc://", sb_get_option("podcast_url"))) ?>',
		'[itunes_podcast_for_search]' => '<?php echo preg_replace("#^https?://#", "itpc://", sb_podcast_url()) ?>',
		'[podcasticon]' => '<img alt="Subscribe to full podcast" title="Subscribe to full podcast" class="podcasticon" src="<?php echo esc_url(SB_PLUGIN_URL) ?>/sb-includes/icons/podcast.png"/>',
		'[podcasticon_for_search]' => '<img alt="Subscribe to custom podcast" title="Subscribe to custom podcast" class="podcasticon" src="<?php echo esc_url(SB_PLUGIN_URL) ?>/sb-includes/icons/podcast_custom.png"/>',
		'[editlink]' => '<?php sb_edit_link($sermon->id) ?>',
		'[creditlink]' => '<div id="poweredbysermonbrowser">Powered by <a href="'.esc_url(SermonBrowser\Plugin::HOME_URL).'">Sermon Browser</a></div>',
	);
}

function sb_sermon_page_dictionary() {
	return array(
		'[sermon_title]' => '<?php echo esc_html(stripslashes($sermon["Sermon"]->title)) ?>',
		'[sermon_description]' => '<?php echo wpautop(stripslashes($sermon["Sermon"]->description)) ?>',
		'[preacher_link]' => '<a href="<?php sb_print_preacher_link($sermon["Sermon"]) ?>"><?php echo esc_html(stripslashes($sermon["Sermon"]->preacher)) ?></a>',
		'[preacher_description]' => '<?php sb_print_preacher_description($sermon["Sermon"]) ?>',
		'[preacher_image]' => '<?php sb_print_preacher_image($sermon["Sermon"]) ?>',
		'[series_link]' => '<a href="<?php sb_print_series_link($sermon["Sermon"]) ?>"><?php echo esc_html(stripslashes($sermon["Sermon"]->series)) ?></a>',
		'[service_link]' => '<a href="<?php sb_print_service_link($sermon["Sermon"]) ?>"><?php echo esc_html(stripslashes($sermon["Sermon"]->service)) ?></a>',
		'[date]' => '<?php echo sb_formatted_date ($sermon["Sermon"]) ?>',
		'[passages_loop]' => '<?php $ref_output = array(); for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): ?>',
		'[/passages_loop]' => '<?php endfor; echo implode(", ", $ref_output); ?>',
		'[passage]' => '<?php $ref_output[] = sb_get_books($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i]) ?>',
		'[files_loop]' => '<?php $media = sb_get_stuff($sermon["Sermon"]); foreach ((array) $media as $media_type => $media_names) { if (is_array($media_names) && $media_type != "Code") { foreach ((array)$media_names as $media_name) {  ?>',
		'[/files_loop]' => '<?php } } } ?>',
		'[file]' => '<?php sb_print_url($media_name) ?>',
		'[file_with_download]' => '<?php sb_print_url_link($media_name) ?>',
		'[embed_loop]' => '<?php $media = sb_get_stuff($sermon["Sermon"]); foreach ((array) $media as $media_type => $media_names) { if (is_array($media_names) && $media_type == "Code") { foreach ((array)$media_names as $media_name) { ?>',
		'[/embed_loop]' => '<?php } } } ?>',
		'[embed]' => '<?php sb_print_code($media_name) ?>',
		'[next_sermon]' => '<?php sb_print_next_sermon_link($sermon["Sermon"]) ?>',
		'[prev_sermon]' => '<?php sb_print_prev_sermon_link($sermon["Sermon"]) ?>',
		'[sameday_sermon]' => '<?php sb_print_sameday_sermon_link($sermon["Sermon"]) ?>',
		'[tags]' => '<?php sb_print_tags($sermon["Tags"]) ?>',
		'[esvtext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "esv"); endfor ?>',
		'[kjvtext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "kjv"); endfor ?>',
		'[asvtext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "asv"); endfor ?>',
		'[nettext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "net"); endfor ?>',
		'[ylttext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "ylt"); endfor ?>',
		'[webtext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "web"); endfor ?>',
		'[akjvtext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "akjv"); endfor ?>',
		'[hnvtext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "hnv"); endfor ?>',
		'[lbrvtext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "rv1909"); endfor ?>',
		'[cornilescutext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "cornilescu"); endfor ?>',
		'[synodaltext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "synodal"); endfor ?>',
		'[biblepassage]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): sb_print_bible_passage ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i]); endfor ?>',
		'[editlink]' => '<?php sb_edit_link($sermon["Sermon"]->id) ?>',
		'[creditlink]' => '<div id="poweredbysermonbrowser">Powered by <a href="'.esc_url(SermonBrowser\Plugin::HOME_URL).'">Sermon Browser</a></div>',
	);
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
