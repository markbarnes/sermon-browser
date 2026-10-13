<?php
if (!defined('ABSPATH'))
	exit;
/**
* Template tags: functions that print sermons and their links, and sb_display_sermons() for themes
*
* @package frontend_functions
*/
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the sermon list, its filters, feed and downloads are read-only GET requests
// Function to display sermons for users to add to their template
function sb_display_sermons($options = array()) {
	$default = array(
		'display_preacher' => 1,
		'display_passage' => 1,
		'display_date' => 1,
		'preacher' => 0,
		'service' => 0,
		'series' => 0,
		'limit' => 5,
		'url_only' => 0,
	);
	$options = array_merge($default, (array) $options);
	extract($options);
	if ($url_only == 1)
		$limit = 1;
	$sermons = sb_get_sermons(array(
			'preacher' => $preacher,
			'service' => $service,
			'series' => $series
		),
		array(), 1, $limit
	);
	if ($url_only == 1)
		sb_print_sermon_link($sermons[0], true);
	else {
		echo "<ul class=\"sermon-widget\">\r";
		foreach ((array) $sermons as $sermon) {
			echo "\t<li>";
			echo "<span class=\"sermon-title\"><a href=\"";
			sb_print_sermon_link($sermon, true);
			echo "\">".esc_html(stripslashes($sermon->title))."</a></span>";
			if ($display_passage) {
				$foo = sb_unserialize($sermon->start);
				$bar = sb_unserialize($sermon->end);
				echo "<span class=\"sermon-passage\"> (".(isset($foo[0], $bar[0]) ? wp_kses_post(sb_get_books($foo[0], $bar[0])) : '').")</span>";
			}
			if ($display_preacher) {
				echo "<span class=\"sermon-preacher\">".esc_html__('by', 'sermon-browser')." <a href=\"";
				sb_print_preacher_link($sermon);
				echo "\">".esc_html(stripslashes($sermon->preacher))."</a></span>";
			}
			if ($display_date)
				echo " <span class=\"sermon-date\">".esc_html__('on', 'sermon-browser')." ".esc_html(sb_formatted_date ($sermon))."</span>";
			echo ".</li>\r";
		}
		echo "</ul>\r";
	}
}

function sb_print_most_popular() {
	$args['before_widget'] = '<div id="sermon_most_popular" style="border: 1px solid ; margin: 0pt 0pt 1em 2em; padding: 5px; float: right; font-size: 75%; line-height: 1em">';
	$args['after_widget'] = '</div>';
	$args['before_title'] = '<span class="popular_title">';
	$args['after_title'] = '</span>';
	$args['suffix'] = '_f';
	SermonBrowser\Widgets\Popular::display($args);
}

//Modify page title
function sb_page_title($title) {
	if (isset($_GET['sermon_id'])) {
		$sermon = sb_find_sermon((int) $_GET['sermon_id']);
		if ($sermon)
			return $title.' ('.esc_html($sermon->title).' - '.esc_html($sermon->preacher ? $sermon->preacher->name : '').')';
		else
			return $title.' ('.esc_html__('No sermons found.', 'sermon-browser').')';
	}
	else
		return $title;
}

//Print unstyled bible passage
function sb_print_bible_passage ($start, $end) {
	echo "<p class='bible-passage'>".wp_kses_post(SermonBrowser\Bible::reference($start, $end))."</p>";
}

// Returns human friendly Bible reference with link to filter
function sb_get_books($start, $end) {
	return SermonBrowser\Bible::reference($start, $end, TRUE);
}

//Returns the text of a Bible passage (esv or net) for the single sermon page
function sb_add_bible_text ($start, $end, $version) {
	return SermonBrowser\Bible::text($start, $end, $version);
}

//Adds edit sermon link if current user has edit rights
function sb_edit_link ($id) {
	if (current_user_can('publish_posts')) {
		$id = (int)$id;
		echo '<div class="sb_edit_link"><a href="'.esc_url(admin_url('admin.php?page=sermon-browser/new_sermon.php&mid='.$id)).'">'.esc_html__('Edit Sermon', 'sermon-browser').'</a></div>';
	}
}

// Returns URL for search links
// Relative links now deprecated
function sb_build_url($arr, $clear = false) {
	global $wpdb;
	// Word list for URL building purpose
	$wl = array('preacher', 'title', 'date', 'enddate', 'series', 'service', 'sortby', 'dir', 'book', 'stag', 'podcast');
	$foo = array_merge((array) $_GET, (array) $_POST, $arr); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the filter form is a read-only search; values are URL-encoded below
	foreach ($foo as $k => $v) {
		if (is_scalar($v) && (in_array($k, array_keys($arr)) || (in_array($k, $wl) && !$clear))) {
			$bar[] = rawurlencode($k).'='.rawurlencode($v);
		}
	}
	if (isset($bar)) {
			return esc_url(sb_display_url().sb_query_char().implode('&', $bar));
	}
	else {
		return sb_display_url();
	}
}

// Formats date into words
function sb_formatted_date ($sermon) {
	if ($sermon->datetime == '1970-01-01 00:00:00')
		return __('Unknown Date', 'sermon-browser');
	else
		return date_i18n(get_option('date_format'), strtotime($sermon->datetime));
}

// Returns podcast URL
function sb_podcast_url() {
	return str_replace(' ', '%20', sb_build_url(array('podcast' => 1, 'dir'=>'desc', 'sortby'=>'m.datetime')));
}

// Prints sermon search URL
function sb_print_sermon_link($sermon, $echo = true) {
	if ($echo)
		echo esc_url(sb_build_url(array('sermon_id' => $sermon->id), true));
	else
		return sb_build_url(array('sermon_id' => $sermon->id), true);
}

// Prints preacher search URL
function sb_print_preacher_link($sermon) {
	echo esc_url(sb_build_url(array('preacher' => $sermon->pid), false));
}

// Prints series search URL
function sb_print_series_link($sermon) {
	echo esc_url(sb_build_url(array('series' => $sermon->ssid), false));
}

// Prints service search URL
function sb_print_service_link($sermon) {
	echo esc_url(sb_build_url(array('service' => $sermon->sid), false));
}

// Prints bible book search URL
function sb_get_book_link($book_name) {
	return sb_build_url(array('book' => $book_name), false);
}

// Prints tag search URL
function sb_get_tag_link($tag) {
	return sb_build_url(array('stag' => $tag), false);
}

// Prints tags
function sb_print_tags($tags) {
	$out = array();
	foreach ((array) $tags as $tag) {
		$tag = stripslashes($tag);
		$out[] = '<a href="'.esc_url(sb_get_tag_link($tag)).'">'.esc_html($tag).'</a>';
	}
	echo wp_kses_post(implode(', ', (array) $out));
}

//Prints tag cloud
function sb_print_tag_clouds($minfont=80, $maxfont=150) {
	// Most used first, then alphabetically
	$cnt = array();
	$out = array();
	foreach (SermonBrowser\Storage::sermons()->tag_counts() as $tag)
		$cnt[$tag->name] = $tag->count;
	if (count($cnt) == 0)
		return;
	$fontrange = $maxfont - $minfont;
	$maxcnt = 0;
	$mincnt = 1000000;
	foreach ($cnt as $cur) {
		if ($cur > $maxcnt) $maxcnt = $cur;
		if ($cur < $mincnt) $mincnt = $cur;
	}
	$cntrange = $maxcnt + 1 - $mincnt;
	$minlog = log($mincnt);
	$maxlog = log($maxcnt);
	$logrange = $maxlog == $minlog ? 1 : $maxlog - $minlog;
	foreach ($cnt as $tag => $count) {
		$size = $minfont + $fontrange * (log($count) - $minlog) / $logrange;
		$out[] = '<a style="font-size:'.(int) $size.'%" href="'.esc_url(sb_get_tag_link($tag)).'">'.esc_html($tag).'</a>';
	}
	echo wp_kses_post(implode(' ', $out));
}

//Prints link to next page
function sb_print_next_page_link($limit = 0) {
	global $record_count;
	if ($limit == 0)
		$limit = sb_get_option('sermons_per_page');
	$current = isset($_REQUEST['pagenum']) ? (int) $_REQUEST['pagenum'] : 1;
	if ($current < ceil($record_count / $limit)) {
		$url = sb_build_url(array('pagenum' => ++$current), false);
		echo '<a href="'.esc_url($url).'">'.esc_html__('Next page', 'sermon-browser').' &raquo;</a>';
	}
}

//Prints link to previous page
function sb_print_prev_page_link($limit = 0) {
	if ($limit == 0) $limit = sb_get_option('sermons_per_page');
	$current = isset($_REQUEST['pagenum']) ? (int) $_REQUEST['pagenum'] : 1;
	if ($current > 1) {
		$url = sb_build_url(array('pagenum' => --$current), false);
		echo '<a href="'.esc_url($url).'">&laquo; '.esc_html__('Previous page', 'sermon-browser').'</a>';
	}
}

// Print link to attached files
function sb_print_url($url) {
	require (SB_INCLUDES_DIR.'/filetypes.php');
	$pathinfo = pathinfo($url);
	$ext = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';
	if ((substr($url,0,7) == "http://") or (substr($url,0,8) == 'https://'))
		$url=sb_display_url().sb_query_char(FALSE).'show&url='.rawurlencode($url);
	else
		if (strtolower($ext) == 'mp3')
			$url=sb_display_url().sb_query_char(FALSE).'show&file_name='.rawurlencode($url);
		else
			$url=sb_display_url().sb_query_char(FALSE).'download&file_name='.rawurlencode($url);
	$uicon = $default_site_icon;
	foreach ($siteicons as $site => $icon) {
		if (strpos($url, $site) !== false) {
			$uicon = $icon;
			break;
		}
	}
	$uicon = isset($filetypes[$ext]['icon']) ? $filetypes[$ext]['icon'] : $uicon;
	if (strtolower($ext) == 'mp3') {
		if (do_shortcode(sb_get_option('mp3_shortcode')) != sb_get_option('mp3_shortcode')) {
			echo do_shortcode(str_ireplace('%SERMONURL%', $url, sb_get_option('mp3_shortcode'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the player from the shortcode the site owner chose
			return;
		}
	}
	$uicon = SB_PLUGIN_URL.'/sb-includes/icons/'.$uicon;
	if (!isset($filetypes[$ext]['name']))
		/* translators: %s: a file extension, such as pdf */
		$filetypes[$ext]['name'] = sprintf(__('%s file', 'sermon-browser'), $ext);
	echo '<a href="'.esc_url($url).'"><img class="site-icon" alt="'.esc_attr($filetypes[$ext]['name']).'" title="'.esc_attr($filetypes[$ext]['name']).'" src="'.esc_url($uicon).'"></a>';
}

// Print link to attached external URLs
function sb_print_url_link($url) {
	echo '<div class="sermon_file">';
	sb_print_url ($url);
	if (strtolower(substr($url, -4)) == ".mp3") {
		if ((substr($url,0,7) == "http://") or (substr($url,0,8) == 'https://')) {
			$param="url"; }
		else {
			$param="file_name"; }
		$url = rawurlencode($url);
        echo ' <a href="'.esc_url(sb_display_url().sb_query_char(false).'download&'.$param.'='.$url).'">'.esc_html__('Download', 'sermon-browser').'</a>';
	}
	echo '</div>';
}

//Decode base64 encoded data
function sb_print_code($code) {
	echo do_shortcode(sb_kses_embed(base64_decode($code))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filtered by sb_kses_embed()
}

//Prints preacher description
function sb_print_preacher_description($sermon) {
	if (strlen($sermon->preacher_description)>0) {
		echo "<div class='preacher-description'><span class='about'>" . esc_html__('About', 'sermon-browser').' '.esc_html(stripslashes($sermon->preacher)).': </span>';
		echo "<span class='description'>".wp_kses_post(stripslashes($sermon->preacher_description))."</span></div>";
	}
}

//Prints preacher image
function sb_print_preacher_image($sermon) {
	if ($sermon->image)
		echo "<img alt='".esc_attr(stripslashes($sermon->preacher))."' class='preacher' src='".esc_url(trailingslashit(site_url()).sb_get_option('upload_dir').'images/'.rawurlencode($sermon->image))."'>";
}

//Prints link to sermon preached next (but not today)
function sb_print_next_sermon_link($sermon) {
	$sermon = sb_find_sermon((int) $sermon->id);
	$next = $sermon ? SermonBrowser\Storage::sermons()->next($sermon) : null;
	if (!$next) return;
	echo '<a href="';
	sb_print_sermon_link($next);
	echo '">'.esc_html($next->title).' &raquo;</a>';
}

//Prints link to sermon preached on previous days
function sb_print_prev_sermon_link($sermon) {
	$sermon = sb_find_sermon((int) $sermon->id);
	$prev = $sermon ? SermonBrowser\Storage::sermons()->previous($sermon) : null;
	if (!$prev) return;
	echo '<a href="';
	sb_print_sermon_link($prev);
	echo '">&laquo; '.esc_html($prev->title).'</a>';
}

//Prints links to other sermons preached on the same day
function sb_print_sameday_sermon_link($sermon) {
	$sermon = sb_find_sermon((int) $sermon->id);
	$same = $sermon ? SermonBrowser\Storage::sermons()->same_day($sermon) : array();
	if (!$same) {
		esc_html_e('None', 'sermon-browser');
		return;
	}
	$output = array();
	foreach ($same as $cur)
		$output[] = '<a href="'.esc_url(sb_print_sermon_link($cur, false)).'">'.esc_html($cur->title).'</a>';
	echo wp_kses_post(implode(', ', $output));
}

//Prints the filters on the sermon list
function sb_print_filters($filter) {
	SermonBrowser\Filters::display($filter);
}
