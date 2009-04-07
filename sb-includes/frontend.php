<?php 
// Create the shortcode handler
function sb_shortcode($atts, $content=null) {
	global $wpdb, $clr, $record_count;
	ob_start();
	$atts = shortcode_atts(array(
		'filter' => 'one-click',
		'id' => $_REQUEST['sermon_id'],
		'preacher' => $_REQUEST['preacher'],
		'series' => $_REQUEST['series'],
		'book' => $_REQUEST['book'],
		'service' => $_REQUEST['service'],
		'date' => $_REQUEST['date'],
		'enddate' => $_REQUEST['enddate'],
		'tag' => $_REQUEST['stag'],
		'title' => $_REQUEST['title'],
	), $atts);
	if ($atts['id'] != 0) {
		$clr = true;
		$sermon = sb_get_single_sermon((int) $atts['id']);
		eval('?>'.base64_decode(get_option('sb_sermon_single_output')));
	} else {
		$clr = false;
		$sermons = sb_get_sermons($atts,
		array(
			'by' => $_REQUEST['sortby'] ? $_REQUEST['sortby'] : 'm.date',
			'dir' => $_REQUEST['dir'],
		),
		$_REQUEST['page'] ? $_REQUEST['page'] : 1			
		);
		$output = '?>'.base64_decode(get_option('sb_sermon_multi_output'));
		eval($output);
	}			
	$content = ob_get_contents();
	ob_end_clean();		
	return $content;
}

// Get the URL of the sermons page
function sb_display_url() {
	global $wpdb, $post, $sb_display_url;
	if ($sb_display_url == '') {
		$pageid = null;
		if ($post->ID != '')
			$pageid = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[sermons%' AND (post_status = 'publish' OR post_status = 'private') AND ID={$post->ID} AND post_date < NOW();");
		if ($pageid === null)
			$pageid = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[sermons]%' AND (post_status = 'publish' OR post_status = 'private') AND post_date < NOW();");
		$sb_display_url = get_permalink($pageid);
		if ($sb_display_url == sb_get_value('wordpress_url')|$sb_display_url =="") // Hack to force true permalink even if page used for front page.
			$sb_display_url = sb_get_value('wordpress_url')."/?page_id=".$pageid;
	}
	return $sb_display_url;
}

//Are we appending sermon-browser query to an existing query, or not?
function sb_query_char ($return_entity = true) {
	if (strpos(sb_display_url(), '?')===FALSE)
		return '?';
	else
		if ($return_entity)
			return '&amp;';
		else
			return '&';
}

//Modify page title
function sb_page_title($title) {
	global $wpdb;
	if ($_GET['sermon_id']) {
		$id = $_GET['sermon_id'];
		$sermon = $wpdb->get_row("SELECT m.title, p.name FROM {$wpdb->prefix}sb_sermons as m LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id where m.id = $id");
		return $title.' ('.stripslashes($sermon->title).' - '.stripslashes($sermon->name).')';
	}
	else
		return $title;
}

//Shows database statistics in the HTML comments if define('SAVEQUERIES', true); is set in wp-config.php
//Useful for diagnostics
function sb_footer_stats() {
	if (SAVEQUERIES) {
		global $wpdb;
		echo '<!-- ';
		echo($wpdb->num_queries.' queries. '.timer_stop().' seconds.');
		echo chr(13);
		print_r($wpdb->queries);
		echo chr(13);
		echo ' -->';
	}
}

//Fix to ensure AudioPlayer v2 and AudioPlayer v1 both work
if (!function_exists('ap_insert_player_widgets') && function_exists('insert_audio_player')) {
	function ap_insert_player_widgets($params) {
		return insert_audio_player($params);
	}
}

//Downloads external webpage. Used to add Bible passages to sermon page.
function sb_download_page ($page_url) {
	if (function_exists(curl_init)) {
		$curl = curl_init();
		curl_setopt ($curl, CURLOPT_URL, $page_url);
		curl_setopt ($curl, CURLOPT_TIMEOUT, 2);
		curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($curl, CURLOPT_HTTPHEADER, array('Accept-Charset: utf-8;q=0.7,*;q=0.7'));
		$contents = curl_exec ($curl);
		$content_type = curl_getinfo( $curl, CURLINFO_CONTENT_TYPE );
		curl_close ($curl);
	}
	else
		{
		$handle = @fopen ($page_url, 'r');
		if ($handle) {
			stream_set_blocking($handle, TRUE );
			stream_set_timeout($handle, 2);
			$info = socket_get_status($handle);
			while (!feof($handle) && !$info['timed_out']) {
				$contents .= fread($handle, 8192);
				$info = socket_get_status($handle);
			}
		@fclose($handle);
		}
	}
	return $contents;
}

// Returns human friendly Bible reference (e.g. John 3:1-16, not John 3:1-John 3:16)
function sb_tidy_reference ($start, $end, $add_link = FALSE) {
	if (!trim($start['book'])) {
		return "";
	}
	$start_book = trim($start['book']);
	$end_book = trim($end['book']);
	$start_chapter = trim($start['chapter']);
	$end_chapter = trim($end['chapter']);
	$start_verse = trim($start['verse']);
	$end_verse = trim($end['verse']);
	if ($add_link) {
		$start_book = "<a href=\"".sb_get_book_link($start_book)."\">{$start_book}</a>";
		$end_book = "<a href=\"".sb_get_book_link($end_book)."\">{$end_book}</a>";
	}
	if ($start_book == $end_book) {
		if ($start_chapter == $end_chapter) {
			if ($start_verse == $end_verse) {
				$reference = "$start_book $start_chapter:$start_verse";
			} else {
				$reference = "$start_book $start_chapter:$start_verse-$end_verse";
			}
		} else {
			 $reference = "$start_book $start_chapter:$start_verse-$end_chapter:$end_verse";
		}
	} else {
		$reference =  "$start_book $start_chapter:$start_verse - $end_book $end_chapter:$end_verse";
	}
	return $reference;
}

//Print unstyled bible passage
function sb_print_bible_passage ($start, $end) {
	echo "<p class='bible-passage'>".sb_tidy_reference($start, $end)."</p>";
}

// Returns human friendly Bible reference with link to filter
function sb_get_books($start, $end) {
	return sb_tidy_reference ($start, $end, TRUE);
}

//Add Bible text to single sermon page
function sb_add_bible_text ($start, $end, $version) {
	if ($version == 'esv')
		return sb_add_esv_text ($start, $end);
	elseif ($version == 'net')
		return sb_add_net_text ($start, $end);
	else
		return sb_add_seek_first_text ($start, $end, $version);
}
	
//Returns ESV text
function sb_add_esv_text ($start, $end) {
	// If you are experiencing errors, you should sign up for an ESV API key, 
	// and insert the name of your key in place of the letters IP in the URL
	// below (.e.g. ...passageQuery?key=YOURAPIKEY&passage=...)
	$esv_url = 'http://www.esvapi.org/v2/rest/passageQuery?key=IP&passage='.urlencode(sb_tidy_reference ($start, $end)).'&include-headings=false&include-footnotes=false';
	return sb_download_page ($esv_url);
}

// Converts XML string to object
function sb_get_xml ($content) {
	if (class_exists('SimpleXMLElement')) {
		$xml = new SimpleXMLElement($content);
	} else {
		$xml = new SimpleXMLElement4();
		$xml = $xml->xml_load_file($content, 'object', 'utf-8');
	}
	return $xml;
}

//Returns NET Bible text
function sb_add_net_text ($start, $end) {
	$reference = str_replace(' ', '+', sb_tidy_reference ($start, $end));
	$old_chapter = $start['chapter'];
	$net_url = "http://labs.bible.org/api/xml/verse.php?passage={$reference}";
	if (class_exists('SimpleXMLElement')) // Ignore paragraph formatting on PHP4 because xml_parse_into_struct doesn't like HTML tags
		$xml = sb_get_xml(sb_download_page($net_url.'&formatting=para'));
	else
		$xml = sb_get_xml(sb_download_page($net_url));
	$output='';
	$items = array();
	$items = $xml->item;
	foreach ($items as $item) {
		if ($item->text != '[[EMPTY]]') {
			if (substr($item->text, 0, 8) == '<p class') {
				$paraend = stripos($item->text, '>', 8)+1;
				$output .= "\n".substr($item->text, 0, $paraend);
				$item->text = substr ($item->text, $paraend);
			}
			if ($old_chapter == $item->chapter) {
				$output .= " <span class=\"verse-num\">{$item->verse}</span>";
			} else {
				$output .= " <span class=\"chapter-num\">{$item->chapter}:{$item->verse}</span> ";
				$old_chapter = strval($item->chapter);
			}
			$output .= 	$item->text;
		}
	}
	return "<div class=\"net\">\r<h2>".sb_tidy_reference ($start, $end)."</h2><p>{$output} (<a href=\"http://net.bible.org/?{$reference}\">NET Bible</a>)</p></div>";
}

//Returns Bible text using the Living Stones API
function sb_add_seek_first_text ($start, $end, $version) {
	$books = sb_get_value('bible_books');
	$r1 = array_search($start['book'], $books)+1;
	$r2 = $start['chapter'];
	$r3 = $start['verse'];
	$r4 = array_search($end['book'], $books)+1;
	$r5 = $end['chapter'];
	$r6 = $end['verse'];
	if (empty($start['book']))
		return;
	else {
		$content = sb_download_page ('http://api.seek-first.com/v1/BibleSearch.php?type=lookup&appid=seekfirst&startbooknum='.$r1.'&startchapter='.$r2.'&startverse='.$r3.'&endbooknum='.$r4.'&endchapter='.$r5.'&endverse='.$r6.'&version='.$version);
		if ($content != '') {
			$patterns = array (
				'/<!--(.)*?-->/',
				'/<Copyright>(.*)<\/Copyright>/',
				'/<VersionName>(.*)<\/VersionName>/',
				'/<TotalResults>(.*)<\/TotalResults>/',
			);
			$replace = array ('', '', '', '');
			$content = trim(preg_replace ($patterns, $replace, $content));
			$old_chapter = $start['chapter'];
			$content = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\n".$content;
			$xml = sb_get_xml($content);
			$output='';
			$items = array();
			$items = $xml->Result;
			foreach ($items as $item) {
				if ($old_chapter == $item->Chapter) {
					$output .= " <span class=\"verse-num\">{$item->Verse}</span>";
				} else {
					$output .= " <span class=\"chapter-num\">{$item->Chapter}:{$item->Verse}</span> ";
					$old_chapter = strval($item->Chapter);
				}
				$output .= 	$item->Text;
			}
		}
		return '<div class="'.$version.'"><h2>'.sb_tidy_reference ($start, $end). '</h2><p>'.$output.' (<a href="http://biblepro.bibleocean.com/dox/default.aspx">'. strtoupper($version). '</a>)</p></div>';
	}
}

//Adds edit sermon link if current user has edit rights
function sb_edit_link ($id) {
	if (current_user_can('edit_posts')) 
		echo '<div class="sb_edit_link"><a href="'.get_bloginfo('wpurl').'/wp-admin/admin.php?page=sermon-browser/new_sermon.php&mid='.$id.'">Edit Sermon</a></div>';
}

// Display podcast, or download linked files
function sb_hijack() {
	//Increases the download count
	function sb_increase_download_count ($stuff_name) {
		if (function_exists('current_user_can')&&!(current_user_can('edit_posts')|current_user_can('publish_posts'))) {
			global $wpdb;
			$wpdb->query("UPDATE ".$wpdb->prefix."sb_stuff SET COUNT=COUNT+1 WHERE name='".mysql_real_escape_string($stuff_name)."'");
		}
	}
	
	// Safer readfile function for large files
	function readfile_segments($filename,$retbytes=true) {
		$segmentsize = 1048576;
		$buffer = '';
		$cnt =0;
		$handle = fopen($filename, 'rb');
		if ($handle === false) {
			return false;
		}
		while (!feof($handle)) {
			set_time_limit(ini_get('max_execution_time'));
			$buffer = fread($handle, $segmentsize);
			echo $buffer;
			ob_flush();
			flush();
			if ($retbytes) {
				$cnt += strlen($buffer);
			}
		}
		$status = fclose($handle);
		if ($retbytes && $status)
			return $cnt;
		return $status;
	}
	
	global $filetypes, $wpdb;
	
	//Displays podcast
	if (isset($_REQUEST['podcast'])) {
		$sermons = sb_get_sermons(array(
			'title' => $_REQUEST['title'],
			'preacher' => $_REQUEST['preacher'],
			'date' => $_REQUEST['date'],
			'enddate' => $_REQUEST['enddate'],
			'series' => $_REQUEST['series'],
			'service' => $_REQUEST['service'],
			'book' => $_REQUEST['book'],
			'tag' => $_REQUEST['stag'],
			),
		array(
			'by' => $_REQUEST['sortby'] ? $_REQUEST['sortby'] : 'm.date',
			'dir' => $_REQUEST['dir'],
		),
		$_REQUEST['page'] ? $_REQUEST['page'] : 1, 
		1000000			
		);
		header('Content-Type: application/rss+xml');
		echo '<?xml version="1.0" encoding="UTF-8"?>';
		include(sb_get_value('plugin_path').'podcast.php');
		die();
	}

	//Forces sermon download of local file
	if (isset($_REQUEST['download']) AND isset($_REQUEST['file_name'])) {
		$file_name = urldecode($_GET['file_name']);
		$file_name = $wpdb->get_var("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE name='{$file_name}'");
		if (!is_null($file_name)) {
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header('Content-Disposition: attachment; filename="'.$file_name.'";');
			header("Content-Transfer-Encoding: binary");
			sb_increase_download_count ($file_name);
			$file_name = sb_get_value('wordpress_path').get_option("sb_sermon_upload_dir").$file_name;
			header("Content-Length: ".filesize($file_name));
			readfile_segments($file_name);
		}
		exit();
	}
	
	//Forces sermon download of external URL
	if (isset($_REQUEST['download']) AND isset($_REQUEST['url'])) {
		$url = urldecode($_GET['url']);
		if(ini_get('allow_url_fopen')) {
			$headers = array_change_key_case(get_headers($url, 1),CASE_LOWER);
			$filesize = $headers['content-length'];
			$cd =  $headers['content-disposition'];
			$location =  $headers['location'];
			if (is_array($location)) $location = $location[0];
			if ($location && substr($location,0,7) != "http://") {
				preg_match('@^(?:http://)?([^/]+)@i', $url, $matches);
				$location = "http://".$matches[1].'/'.$location;
			}
			if ($location) {
				header('Location: '.get_bloginfo('wpurl').'?download&url='.$location);
				die();
			}
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			if ($cd) {
				header ("Content-Disposition: ".$cd); }
			else {
				header('Content-Disposition: attachment; filename="'.basename($url).'";'); }
			header("Content-Transfer-Encoding: binary");
			if ($filesize) header("Content-Length: ".$filesize);
			sb_increase_download_count ($url);
			readfile_segments($url);
			exit();
		}
		else {
			sb_increase_download_count ($url);
			header('Location: '.$url);
		}
	}
	
	//Returns local file (doesn't force download)
	if (isset($_REQUEST['show']) AND isset($_REQUEST['file_name'])) {
		global $filetypes;
		$file_name = urldecode($_GET['file_name']);
		$file_name = $wpdb->get_var("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE name='{$file_name}'");
		if (!is_null($file_name)) {
			$url = get_option('sb_sermon_upload_url').$file_name;
			sb_increase_download_count ($file_name);
			header("Location: ".$url);
		}
		exit();
	}
	
	//Returns contents of external URL(doesn't force download)
	if (isset($_REQUEST['show']) AND isset($_REQUEST['url'])) {
		$url = URLDecode($_GET['url']);
		sb_increase_download_count ($url);
		header('Location: '.$url);
	}
}

// Returns URL for search links
function sb_build_url($arr, $clear = false) {
	global $post, $wpdb;
	// Word list for URL building purpose
	$wl = array('preacher', 'title', 'date', 'enddate', 'series', 'service', 'sortby', 'dir', 'sermon_id', 'book', 'stag', 'podcast');
	$foo = array_merge((array) $_GET, (array) $_POST, $arr);
	foreach ($foo as $k => $v) {
		if (in_array($k, array_keys($arr)) | (in_array($k, $wl) && !$clear)) {
			$bar[] = "$k=$v";
		}
	}
	if (isset($bar))
		return sb_display_url().sb_query_char().implode('&amp;', $bar);
	else
		return sb_display_url();
}

// Adds javascript and CSS where required
function sb_add_headers() {
	global $sermon_domain, $post, $wpdb;
	echo "<!-- Added by SermonBrowser (version ".SB_CURRENT_VERSION.") - http://www.4-14.org.uk/sermon-browser -->\r";
	echo "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"".__('Sermon podcast', $sermon_domain)."\" href=\"".get_option('sb_podcast')."\" />\r";
	$pageid = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[sermons%' AND (post_status = 'publish' OR post_status = 'private') AND ID={$post->ID} AND post_date < NOW();");
	if ($pageid !== NULL) {
		wp_enqueue_script('sb_datepicker');
		wp_enqueue_style ('sb_datepicker');
		wp_enqueue_style ('sb_style');
		if ($_REQUEST['title'] OR $_REQUEST['preacher'] OR $_REQUEST['date'] OR $_REQUEST['enddate'] OR $_REQUEST['series'] OR $_REQUEST['service'] OR $_REQUEST['book'] OR $_REQUEST['stag'])
			echo "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"".__('Custom sermon podcast', $sermon_domain)."\" href=\"".sb_podcast_url()."\" />\r";
	}
}

//Returns the default time for a particular service
function sb_default_time($service) {
	global $wpdb;
	$sermon_time = $wpdb->get_var("SELECT time FROM {$wpdb->prefix}sb_services WHERE id='{$service}'");
	if (isset($sermon_time)) {
		return $sermon_time;
	} else {
		return "00:00";
	}
}

// Formats date into words
function sb_format_date ($the_date) {
	if (WPLANG == '' | WPLANG == "en_EN" | WPLANG == "en") {
		return date(get_option("date_format"), $the_date);
	} else {
		return strftime("%e %B %Y", $the_date);
	}
}

// Returns podcast URL
function sb_podcast_url() {
	return str_replace(' ', '%20', sb_build_url(array('podcast' => 1, 'dir'=>'desc', 'sortby'=>'m.date')));
}

// Prints sermon search URL
function sb_print_sermon_link($sermon) {
	echo sb_build_url(array('sermon_id' => $sermon->id), true);
}

// Prints preacher search URL
function sb_print_preacher_link($sermon) {
	global $clr;
	echo sb_build_url(array('preacher' => $sermon->pid), $clr);
}

// Prints series search URL
function sb_print_series_link($sermon) {
	global $clr;	
	echo sb_build_url(array('series' => $sermon->ssid), $clr);
}

// Prints service search URL
function sb_print_service_link($sermon) {
	global $clr;
	echo sb_build_url(array('service' => $sermon->sid), $clr);
}

// Prints bible book search URL
function sb_get_book_link($book_name) {
	global $clr;
	return sb_build_url(array('book' => $book_name), $clr);
}

// Prints tag search URL
function sb_get_tag_link($tag) {
	global $clr;
	return sb_build_url(array('stag' => $tag), $clr);
}

// Prints tags
function sb_print_tags($tags) {
	foreach ((array) $tags as $tag) {
		$out[] = '<a href="'.sb_get_tag_link($tag).'">'.$tag.'</a>';
	}
	$tags = implode(', ', (array) $out);
	echo $tags;
}

//Prints tag cloud
function sb_print_tag_clouds($minfont=80, $maxfont=150) {
	global $wpdb;
	$rawtags = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_tags as t RIGHT JOIN {$wpdb->prefix}sb_sermons_tags as st ON t.id = st.tag_id");
	foreach ($rawtags as $tag) {
		$cnt[$tag->name]++;
	}
	$fontrange = $maxfont - $minfont;
	$maxcnt = 0;
	$mincnt = 1000000;
	foreach ($cnt as $cur) {
		if ($cur > $maxcnt) $maxcnt = $cur;
		if ($cur < $mincnt) $minct = $cur; 
	}
	$cntrange = $maxcnt + 1 - $mincnt;
	$minlog = log($mincnt);
	$maxlog = log($maxcnt);
	$logrange = $maxlog == $minlog ? 1 : $maxlog - $minlog;
	arsort($cnt);
	foreach ($cnt as $tag => $count) {
		$size = $minfont + $fontrange * (log($count) - $minlog) / $logrange;
		$out[] = '<a style="font-size:'.(int) $size.'%" href="'.sb_get_tag_link($tag).'">'.$tag.'</a>';
	}
	echo implode(' ', $out);
}

//Prints link to next page
function sb_print_next_page_link($limit = 0) {
	global $sermon_domain, $record_count;
	if ($limit == 0) $limit = sb_get_value('sermons_per_page');
	$current = $_REQUEST['page'] ? (int) $_REQUEST['page'] : 1;
	if ($current < ceil($record_count / $limit)) {
		$url = sb_build_url(array('page' => ++$current));
		echo '<a href="'.$url.'">'.__('Next page', $sermon_domain).' &raquo;</a>';
	}	
}

//Prints link to previous page
function sb_print_prev_page_link($limit = 0) {
	global $sermon_domain;
	if ($limit == 0) $limit = sb_get_value('sermons_per_page');
	$current = $_REQUEST['page'] ? (int) $_REQUEST['page'] : 1;
	if ($current > 1) {
		$url = sb_build_url(array('page' => --$current));
		echo '<a href="'.$url.'">&laquo; '.__('Previous page', $sermon_domain).'</a>';
	}	
}

// Print link to attached files
function sb_print_url($url) {
	// Replacement htmlspecialchars_decode for PHP < 5.1
	if (!function_exists("htmlspecialchars_decode")) {
	    function htmlspecialchars_decode($string, $quote_style = ENT_COMPAT) {
	        return strtr($string, array_flip(get_html_translation_table(HTML_SPECIALCHARS, $quote_style)));
	    }
	}
	global $siteicons, $default_site_icon ,$filetypes;
	$pathinfo = pathinfo($url);
	$ext = $pathinfo['extension'];
	if (substr($url,0,7) == "http://") {
		$url=sb_display_url().htmlspecialchars_decode(sb_query_char()).'show&url='.URLencode($url);
	} else {
		if (strtolower($ext) == 'mp3' && function_exists('ap_insert_player_widgets')) {
			$url=sb_display_url().htmlspecialchars_decode(sb_query_char()).'show&file_name='.URLencode($url);
		} else {
			$url=sb_display_url().htmlspecialchars_decode(sb_query_char()).'download&file_name='.URLencode($url);
		}
	}
	$icon_url = sb_get_value('plugin_url').'/sb-includes/icons/';
	$uicon = $default_site_icon;
	foreach ($siteicons as $site => $icon) {
		if (strpos($url, $site) !== false) {
			$uicon = $icon;
			break;
		}
	}
	$uicon = isset($filetypes[$ext]['icon']) ? $filetypes[$ext]['icon'] : $uicon;
	if (strtolower($ext) == 'mp3' && function_exists('ap_insert_player_widgets')) {
	    echo ap_insert_player_widgets('[audio:'.$url.']');
	} else {
	    echo '<a href="'.$url.'"><img class="site-icon" alt="'.$filetypes[$ext]['name'].'" title="'.$filetypes[$ext]['name'].'" src="'.$icon_url.$uicon.'"></a>';
	}
    
}

// Print link to attached external URLs
function sb_print_url_link($url) {
	echo '<div class="sermon_file">';
	sb_print_url ($url);
	if (substr($url, -4) == ".mp3" && function_exists('ap_insert_player_widgets')) {
		if (substr($url,0,7) == "http://") {
			$param="url"; }
		else {
			$param="file_name"; }
		$url = URLencode($url);
		echo ' <a href="'.sb_display_url().sb_query_char().'download&amp;'.$param.'='.$url.'">Download</a>';
	}
	echo '</div>';
}

//Decode base64 encoded data
function sb_print_code($code) {
	echo base64_decode($code);
}

//Prints preacher description
function sb_print_preacher_description($sermon) {
	global $sermon_domain;
	if (strlen($sermon->preacher_description)>0) {
		echo "<div class='preacher-description'><span class='about'>" . __('About', $sermon_domain).' '.stripslashes($sermon->preacher).': </span>';
		echo "<span class='description'>".stripslashes($sermon->preacher_description)."</span></div>";
	}
}

//Prints preacher image
function sb_print_preacher_image($sermon) {
	if ($sermon->image) 
		echo "<img alt='".stripslashes($sermon->preacher)."' class='preacher' src='".get_bloginfo("wpurl").get_option("sb_sermon_upload_dir")."images/".$sermon->image."'>";
}

//Prints link to next sermon
function sb_print_next_sermon_link($sermon) {
	global $wpdb;
	$next = $wpdb->get_row("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE date > '$sermon->date' AND id <> $sermon->id ORDER BY date asc");
	if (!$next) return;
	echo '<a href="';
	sb_print_sermon_link($next);
	echo '">'.stripslashes($next->title).' &raquo;</a>';
}

//Prints link to previous sermon
function sb_print_prev_sermon_link($sermon) {
	global $wpdb;
	$prev = $wpdb->get_row("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE date < '$sermon->date' AND id <> $sermon->id ORDER BY date desc");
	if (!$prev) return;
	echo '<a href="';
	sb_print_sermon_link($prev);
	echo '">&laquo; '.stripslashes($prev->title).'</a>';
}

//Prints links to other sermons preached on the same day
function sb_print_sameday_sermon_link($sermon) {
	global $wpdb, $sermon_domain;
	$same = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE date = '$sermon->date' AND id <> $sermon->id");
	if (!$same) {
		_e('None', $sermon_domain);
		return;
	}
	foreach ($same as $cur) {
		echo '<a href="';
		sb_print_sermon_link($cur);
		echo '">'.stripslashes($cur->title).'</a>';
	}
}

//Gets single sermon from the database
function sb_get_single_sermon($id) {
	global $wpdb;
	$id = (int) $id;
	$sermon = $wpdb->get_row("SELECT m.id, m.title, m.date, m.start, m.end, m.description, p.id as pid, p.name as preacher, p.image as image, p.description as preacher_description, s.id as sid, s.name as service, ss.id as ssid, ss.name as series FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id and m.id = $id");
	$stuff = $wpdb->get_results("SELECT f.id, f.type, f.name FROM {$wpdb->prefix}sb_stuff as f WHERE sermon_id = $id ORDER BY id desc");	
	$rawtags = $wpdb->get_results("SELECT t.name FROM {$wpdb->prefix}sb_sermons_tags as st LEFT JOIN {$wpdb->prefix}sb_tags as t ON st.tag_id = t.id WHERE st.sermon_id = $sermon->id ORDER BY t.name asc");
	foreach ($rawtags as $tag) {
		$tags[] = $tag->name;
	}
	foreach ($stuff as $cur) {
		switch ($cur->type) {
			case "file":
			case "url":
				$file[] = $cur->name;
				break;
			default:
				${$cur->type}[] = $cur->name;
		}
	}
	$sermon->start = unserialize($sermon->start);
	$sermon->end = unserialize($sermon->end);
	print_r ($url);
	return array(		
		'Sermon' => $sermon,
		'Files' => $file,
		'Code' => $code,
		'Tags' => $tags,
	);
}

//Get multiple sermons from the database
function sb_get_sermons($filter, $order, $page = 1, $limit = 0) {
	global $wpdb, $record_count;
	if ($limit == 0) $limit = sb_get_value('sermons_per_page');
	$default_filter = array(
		'title' => '',
		'preacher' => 0,
		'date' => '',
		'enddate' => '',
		'series' => 0,
		'service' => 0,
		'book' => '',
		'tag' => '',
		'id' => '',
	);
	$default_order = array(
		'by' => 'm.date',
		'dir' => 'desc',
	);
	$bs = '';
	$filter = array_merge($default_filter, $filter);
	$order = array_merge($default_order, $order);
	$page = (int) $page;
	if ($filter['title'] != '') {
		$cond = "AND (m.title LIKE '%" . mysql_real_escape_string($filter['title']) . "%' OR m.description LIKE '%" . mysql_real_escape_string($filter['title']). "%' OR t.name LIKE '%" . mysql_real_escape_string($filter['title']) . "%') ";
	}
	if ($filter['preacher'] != 0) {
		$cond .= 'AND m.preacher_id = ' . (int) $filter['preacher'] . ' ';
	}
	if ($filter['date'] != '') {
		$cond .= 'AND m.date >= "' . mysql_real_escape_string($filter['date']) . '" ';
	}
	if ($filter['enddate'] != '') {
		$cond .= 'AND m.date <= "' . mysql_real_escape_string($filter['enddate']) . '" ';
	}
	if ($filter['series'] != 0) {
		$cond .= 'AND m.series_id = ' . (int) $filter['series'] . ' ';
	}
	if ($filter['service'] != 0) {
		$cond .= 'AND m.service_id = ' . (int) $filter['service'] . ' ';
	}	
	if ($filter['book'] != '') {
		$cond .= 'AND bs.book_name = "' . mysql_real_escape_string($filter['book']) . '" ';
	} else {
		$bs = "AND bs.order = 0 AND bs.type= 'start' ";
	}
	if ($filter['tag'] != '') {
		$cond .= "AND t.name LIKE '%" . mysql_real_escape_string($filter['tag']) . "%' ";
	}
	if ($filter['id'] != '') {
		$cond .= "AND m.id LIKE '" . mysql_real_escape_string($filter['id']) . "' ";
	}
	$offset = $limit * ($page - 1);
	if ($order['by'] == 'm.date' ) {
	    if(!isset($order['dir'])) $order['dir'] = 'desc';
	    $order['by'] = 'm.date '.$order['dir'].', s.time';
	}
	if ($order['by'] == 'b.id' ) {
	    $order['by'] = 'b.id '.$order['dir'].', bs.chapter '.$order['dir'].', bs.verse';
	}
	$query = "SELECT SQL_CALC_FOUND_ROWS DISTINCT m.id, m.title, m.description, m.date, m.time, m.start, m.end, p.id as pid, p.name as preacher, p.description as preacher_description, p.image, s.id as sid, s.name as service, ss.id as ssid, ss.name as series 
		FROM {$wpdb->prefix}sb_sermons as m 
		LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id 
		LEFT JOIN {$wpdb->prefix}sb_services as s ON m.service_id = s.id 
		LEFT JOIN {$wpdb->prefix}sb_series as ss ON m.series_id = ss.id 
		LEFT JOIN {$wpdb->prefix}sb_books_sermons as bs ON bs.sermon_id = m.id $bs 
		LEFT JOIN {$wpdb->prefix}sb_books as b ON bs.book_name = b.name 
		LEFT JOIN {$wpdb->prefix}sb_sermons_tags as st ON st.sermon_id = m.id 
		LEFT JOIN {$wpdb->prefix}sb_tags as t ON t.id = st.tag_id 
		WHERE 1 = 1 $cond ORDER BY ". $order['by'] . " " . $order['dir'] . " LIMIT " . $offset . ", " . $limit;
	$query = $wpdb->get_results($query);
	$record_count = $wpdb->get_var("SELECT FOUND_ROWS()");
	return $query;
}

//Gets attachments from database
function sb_get_stuff($sermon, $mp3_only = FALSE) {
	global $wpdb;
	if ($mp3_only) {
		$stuff = $wpdb->get_results("SELECT f.type, f.name FROM {$wpdb->prefix}sb_stuff as f WHERE sermon_id = $sermon->id AND name LIKE '%.mp3' ORDER BY id desc");
	} else {
		$stuff = $wpdb->get_results("SELECT f.type, f.name FROM {$wpdb->prefix}sb_stuff as f WHERE sermon_id = $sermon->id ORDER BY id desc");
	}
	foreach ($stuff as $cur) {
		${$cur->type}[] = $cur->name;
	}
	return array(		
		'Files' => $file,
		'URLs' => $url,
		'Code' => $code,
	);
}

//Prints the filter line for a given parameter
function sb_print_filter_line ($id, $results, $filter, $display, $max_num = 7) {
	global $more_applied;
	echo "<div id = \"{$id}\" class=\"filter\" style=\"margin-bottom: 1em\">\r<span style=\"font-weight:bold\">".ucwords($id).":</span> \r";
	$i = 1;
	$more = FALSE;
	foreach ($results as $result) {
		if ($i == $max_num) {
			echo "<span id=\"{$id}-more\">";
			$more = TRUE;
			$more_applied[] = $id;
		}
		if ($i != 1)
			echo ", \r";
		echo '<a href="'.sb_build_url(array($id => $result->$filter)).'">'.stripslashes($result->$display).'</a>&nbsp;('.$result->count.')';
		$i++;
	}
	echo ".";
	if ($more)
		echo "</span>\r<span id=\"{$id}-more-link\" style=\"display:none\">&hellip; (<a  id=\"{$id}-toggle\" href=\"#\"><strong>".($i-$max_num).' '.__('more', $sermon_domain).'</strong></a>)</span>';
	echo '</div>';
}

//Prints the filter line for the date parameter
function sb_print_date_filter_line ($dates) {
	global $more_applied;
	$date_output = "<div id = \"dates\" class=\"filter\" style=\"margin-bottom: 1em\">\r<span style=\"font-weight:bold\">Date:</span> \r";
	$first = $dates[0];
	$last = end($dates);
	$count = 0;
	if ($first->year == $last->year) {
		if ($first->month == $last->month) {
			$date_output = '';
		} else {
			foreach ($dates as $date) {
				if ($date->month != $previous_month) {
					if ($count != 0)
						$date_output .= '('.$count.'), ';
					$date_output .= '<a href="'.sb_build_url(Array ('date' => $date->year.'-'.$date->month.'-01', 'enddate' => $date->year.'-'.$date->month.'-31')).'">'.strftime('%B', strtotime("{$date->year}-{$date->month}-{$date->day}")).'</a> ';
					$previous_month = $date->month;
					$count = 1;
				} else
					$count++;
			}
			$date_output .= '('.$count.'), ';		
		}
	} else {
		foreach ($dates as $date) {
			if ($date->year !== $previous_year) {
				if ($count !== 0)
					$date_output .= '('.$count.'), ';
				$date_output .= '<a href="'.sb_build_url(Array ('date' => $date->year.'-01-01', 'enddate' => $date->year.'-12-31')).'">'.$date->year.'</a> ';
				$previous_year = $date->year;
				$count = 1;
			} else
				$count++;
		}
		$date_output .= '('.$count.'), ';		
	}
	if ($date_output != '')
		echo rtrim($date_output, ', ')."</div>\r";
}

//Returns the filter URL minus a given parameter
function sb_url_minus_parameter ($param1, $param2='') {
	global $filter_options;
	$existing_params = array_merge((array) $_GET, (array) $_POST);
	foreach (array_keys($existing_params) as $query) {
		if (in_array($query, $filter_options) && $query != $param1 && $query != $param2) {
			$returned_query[] = "{$query}={$existing_params[$query]}";
		}
	}
	if (count($returned_query) > 0)
		return sb_display_url().sb_query_char().implode('&amp;', $returned_query);
	else
		return sb_display_url();
}

//Displays the filter on sermon search page
function sb_print_filters($filter) {
	global $wpdb, $sermon_domain, $more_applied, $filter_options;
	if (get_option('sb_filterhide') == 'hide') {
		$hide_filter = TRUE;
	$js_hide = <<<HERE
		var filter_visible = false;
		jQuery("#mainfilter").hide();
		jQuery("#show_hide_filter").text("[ SHOW ]");
		jQuery("#show_hide_filter").click(function() {
			jQuery("#mainfilter:visible").slideUp("slow");
			jQuery("#mainfilter:hidden").slideDown("slow");
			if (filter_visible) {
				jQuery("#show_hide_filter").text("[ SHOW ]");
				filter_visible = false;
			} else {
				jQuery("#show_hide_filter").text("[ HIDE ]");
				filter_visible = true;
			}
			return false;
		});
HERE;
	}
	$js_hide = str_replace ('SHOW', __('Show filter', $sermon_domain), $js_hide);
	$js_hide = str_replace ('HIDE', __('Hide filter', $sermon_domain), $js_hide);
	if (get_option('sb_filtertype') == 'oneclick') {
		// One click filter
		$hide_custom_podcast = true;
		if ($filter['filter'] !== 'none') {
			$filter_options = array ('preacher', 'book', 'service', 'series', 'date', 'enddate', 'title');
			$output = '';
			foreach ($filter_options AS $filter_option)
				if ($_REQUEST[$filter_option]) {
					if ($filter_option != 'enddate') {
						if ($output != '')
							$output .= "\r, ";
						if ($filter_option == 'date') {
							$output .= '<strong>Date</strong>:&nbsp;';
							if (substr($_REQUEST['date'],0,4) == substr($_REQUEST['enddate'],0,4))
								$output .= substr($_REQUEST['date'],0,4).'&nbsp;(<a href="'.sb_url_minus_parameter('date', 'enddate').'">x</a>)';
							if (substr($_REQUEST['date'],5,2) == substr($_REQUEST['enddate'],5,2))
								$output .= ', '.strftime('%B', strtotime($_REQUEST['date'])).' (<a href="'.sb_build_url(Array ('date' => substr($_REQUEST['date'],0,4).'-01-01', 'enddate' => substr($_REQUEST['date'],0,4).'-12-31')).'">x</a>)';
						} else {
							$output .= '<strong>'.ucwords($filter_option).'</strong>:&nbsp;*'.$filter_option.'*';
							$output .= '&nbsp;(<a href="'.sb_url_minus_parameter($filter_option).'">x</a>)';
						}
					}
					$hide_custom_podcast = FALSE;
				}
			$sermons=sb_get_sermons($filter, array(), 1, 99999);
			$ids = array();
			foreach ($sermons as $sermon)
				$ids[] = $sermon->id;
			$ids = "('".implode ("', '", $ids)."')";

			$preachers = $wpdb->get_results("SELECT p.*, count(p.id) AS count FROM {$wpdb->prefix}sb_preachers AS p JOIN {$wpdb->prefix}sb_sermons AS sermons ON p.id = sermons.preacher_id WHERE sermons.id IN {$ids} GROUP BY p.id ORDER BY count DESC, sermons.date DESC");	
			$series = $wpdb->get_results("SELECT ss.*, count(ss.id) AS count FROM {$wpdb->prefix}sb_series AS ss JOIN {$wpdb->prefix}sb_sermons AS sermons ON ss.id = sermons.series_id  WHERE sermons.id IN {$ids} GROUP BY ss.id ORDER BY sermons.date DESC");
			$services = $wpdb->get_results("SELECT s.*, count(s.id) AS count FROM {$wpdb->prefix}sb_services AS s JOIN {$wpdb->prefix}sb_sermons AS sermons ON s.id = sermons.service_id  WHERE sermons.id IN {$ids} GROUP BY s.id ORDER BY count DESC");
			$book_count = $wpdb->get_results("SELECT bs.book_name AS name, count(b.id) AS count FROM {$wpdb->prefix}sb_books_sermons AS bs JOIN {$wpdb->prefix}sb_books as b ON bs.book_name=b.name WHERE bs.type = 'start' AND bs.sermon_id IN {$ids} GROUP BY b.id");	
			$dates = $wpdb->get_results("SELECT substr(date,1,4) as year, substr(date,6,2) as month, substr(date,9,2) as day FROM {$wpdb->prefix}sb_sermons WHERE id IN {$ids} ORDER BY date ASC");

			$more_applied = array();
			$output = str_replace ('*preacher*', $preachers[0]->name, $output);
			$output = str_replace ('*book*', $book_count[0]->name, $output);
			$output = str_replace ('*service*', $services[0]->name, $output);
			$output = str_replace ('*series*', $series[0]->name, $output);
		
			echo "<span class=\"inline_controls\"><a href=\"#\" id=\"show_hide_filter\"></a></span>";
			if ($output != '')
				echo '<div class="filtered">'.__('Active filter', $sermon_domain).': '.$output."</div>\r";
			echo '<div id="mainfilter">';
			if (count($preachers) > 1)
				sb_print_filter_line ('preacher', $preachers, 'id', 'name', 7);
			if (count($book_count) > 1)
				sb_print_filter_line ('book', $book_count, 'name', 'name', 10);
			if (count($series) > 1)
				sb_print_filter_line ('series', $series, 'id', 'name', 10);
			if (count($services) > 1)
				sb_print_filter_line ('service', $services, 'id', 'name', 10);
			sb_print_date_filter_line ($dates);
			echo "</div>\r";
		}
		if (count($more_applied) > 0 | $output != '' | $hide_custom_podcast === TRUE | $hide_filter === TRUE) {
			echo "<script type=\"text/javascript\">\r";
			echo "\tjQuery(document).ready(function() {\r";
			if ($hide_filter === TRUE)
				echo $js_hide."\r";
			if ($hide_custom_podcast === TRUE)
				echo "\t\tjQuery('.podcastcustom').hide();\r";
			if (count($more_applied) > 0) {
				foreach ($more_applied as $element_id) {
					echo "\t\tjQuery('#{$element_id}-more').hide();\r";
					echo "\t\tjQuery('#{$element_id}-more-link').show();\r";
					echo "\t\tjQuery('a#{$element_id}-toggle').click(function() {\r";
					echo "\t\t\tjQuery('#{$element_id}-more').show();\r";
					echo "\t\t\tjQuery('#{$element_id}-more-link').hide();\r";
					echo "\t\t\treturn false;\r";
					echo "\t\t});\r";
				}
			}
			echo "\t});\r";
			echo "</script>\r";
		}
	} elseif (get_option('sb_filtertype') == 'dropdown') {
		// Drop-down filter
		$preachers = $wpdb->get_results("SELECT p.*, count(p.id) AS count FROM {$wpdb->prefix}sb_preachers AS p JOIN {$wpdb->prefix}sb_sermons AS s ON p.id = s.preacher_id GROUP BY p.id ORDER BY count DESC, s.date DESC");	
		$series = $wpdb->get_results("SELECT ss.*, count(ss.id) AS count FROM {$wpdb->prefix}sb_series AS ss JOIN {$wpdb->prefix}sb_sermons AS sermons ON ss.id = sermons.series_id GROUP BY ss.id ORDER BY sermons.date DESC");
		$services = $wpdb->get_results("SELECT s.*, count(s.id) AS count FROM {$wpdb->prefix}sb_services AS s JOIN {$wpdb->prefix}sb_sermons AS sermons ON s.id = sermons.service_id GROUP BY s.id ORDER BY count DESC");
		$book_count = $wpdb->get_results("SELECT bs.book_name AS name, count( b.id ) AS count FROM {$wpdb->prefix}sb_books_sermons AS bs JOIN {$wpdb->prefix}sb_books AS b ON bs.book_name = b.name WHERE bs.type = 'start' GROUP BY b.id");	
		$sb = array(		
			'Title' => 'm.title',
			'Preacher' => 'preacher',
			'Date' => 'm.date',
			'Passage' => 'b.id',
		);
		$di = array(
			'Ascending' => 'asc',
			'Descending' => 'desc',
		);
		$csb = $_REQUEST['sortby'] ? $_REQUEST['sortby'] : 'm.date';
		$cd = $_REQUEST['dir'] ? $_REQUEST['dir'] : 'desc';	
		?>
		<span class="inline_controls"><a href="#" id="show_hide_filter"></a></span>
		<div id="mainfilter">
			<form method="post" id="sermon-filter" action="<?php echo sb_display_url(); ?>">
				<div style="clear:both">
					<table class="sermonbrowser">
						<tr>
							<td class="fieldname"><?php _e('Preacher', $sermon_domain) ?></td>
							<td class="field"><select name="preacher" id="preacher">
									<option value="0" <?php echo $_REQUEST['preacher'] != 0 ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
									<?php foreach ($preachers as $preacher): ?>
									<option value="<?php echo $preacher->id ?>" <?php echo $_REQUEST['preacher'] == $preacher->id ? 'selected="selected"' : '' ?>><?php echo stripslashes($preacher->name).' ('.$preacher->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php _e('Services', $sermon_domain) ?></td>
							<td class="field"><select name="service" id="service">
									<option value="0" <?php echo $_REQUEST['service'] != 0 ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
									<?php foreach ($services as $service): ?>
									<option value="<?php echo $service->id ?>" <?php echo $_REQUEST['service'] == $service->id ? 'selected="selected"' : '' ?>><?php echo stripslashes($service->name).' ('.$service->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td class="fieldname"><?php _e('Book', $sermon_domain) ?></td>
							<td class="field"><select name="book">
									<option value=""><?php _e('[All]', $sermon_domain) ?></option>
									<?php foreach ($book_count as $book): ?>
									<option value="<?php echo $book->name ?>" <?php echo $_REQUEST['book'] == $book->name ? 'selected=selected' : '' ?>><?php echo stripslashes($book->name). ' ('.$book->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php _e('Series', $sermon_domain) ?></td>
							<td class="field"><select name="series" id="series">
									<option value="0" <?php echo $_REQUEST['series'] != 0 ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
									<?php foreach ($series as $item): ?>
									<option value="<?php echo $item->id ?>" <?php echo $_REQUEST['series'] == $item->id ? 'selected="selected"' : '' ?>><?php echo stripslashes($item->name).' ('.$item->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td class="fieldname"><?php _e('Start date', $sermon_domain) ?></td>
							<td class="field"><input type="text" name="date" id="date" value="<?php echo mysql_real_escape_string($_REQUEST['date']) ?>" /></td>
							<td class="fieldname rightcolumn"><?php _e('End date', $sermon_domain) ?></td>
							<td class="field"><input type="text" name="enddate" id="enddate" value="<?php echo mysql_real_escape_string($_REQUEST['enddate']) ?>" /></td>
						</tr>
						<tr>
							<td class="fieldname"><?php _e('Keywords', $sermon_domain) ?></td>
							<td class="field" colspan="3"><input style="width: 98.5%" type="text" id="title" name="title" value="<?php echo mysql_real_escape_string($_REQUEST['title']) ?>" /></td>
						</tr>
						<tr>
							<td class="fieldname"><?php _e('Sort by', $sermon_domain) ?></td>
							<td class="field"><select name="sortby" id="sortby">
									<?php foreach ($sb as $k => $v): ?>
									<option value="<?php echo $v ?>" <?php echo $csb == $v ? 'selected="selected"' : '' ?>><?php _e($k, $sermon_domain) ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php _e('Direction', $sermon_domain) ?></td>
							<td class="field"><select name="dir" id="dir">
									<?php foreach ($di as $k => $v): ?>
									<option value="<?php echo $v ?>" <?php echo $cd == $v ? 'selected="selected"' : '' ?>><?php _e($k, $sermon_domain) ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td colspan="3">&nbsp;</td>
							<td class="field"><input type="submit" class="filter" value="<?php _e('Filter &raquo;', $sermon_domain) ?>">			</td>
						</tr>
					</table>
					<input type="hidden" name="page" value="1">
				</div>
			</form>
		</div>
		<script type="text/javascript">
			jQuery.datePicker.setDateFormat('ymd','-');
			jQuery('#date').datePicker({startDate:'01/01/1970'});
			jQuery('#enddate').datePicker({startDate:'01/01/1970'});
			<?php if ($hide_filter === TRUE) { ?>
			jQuery(document).ready(function() {
				<?php echo $js_hide; ?>
			});
			<?php } ?>
		</script>
	<?php
	}
}
?>