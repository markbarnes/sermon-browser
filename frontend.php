<?php 

// Required files
require_once('dictionary.php'); //Imports template tags
require_once('widget.php');

// Word list for URL building purpose
$wl = array('preacher', 'title', 'date', 'enddate', 'series', 'service', 'sortby', 'dir', 'page', 'sermon_id', 'book', 'stag', 'podcast');

// Hooks & filters
add_action('template_redirect', 'sb_hijack');
add_filter('wp_title', 'sb_page_title');
add_action('wp_head', 'sb_print_header');
add_filter('the_content', 'sb_sermons_filter');
add_action('widgets_init', 'sb_widget_sermon_init');

// Get the URL of the sermons page
function sb_display_url() {
	global $sef, $wpdb, $isMe, $post;
	if (!$sef) {
		if ($isMe) $display_url=get_permalink( $post->ID );
		else {
			$pageid = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content = '[sermons]' AND post_status = 'publish' AND post_date < NOW();");
			$display_url = get_permalink($pageid);
		}
		if (substr($display_url, -1) == '/') $display_url=substr($display_url, 0, -1);
		$sef=$display_url;
	}
	return $sef;
}

//Modify page title
function sb_page_title($title) {
	global $wpdb;
	if ($_GET['sermon_id']) {
		$id = $_GET['sermon_id'];
		$sermon = $wpdb->get_row("SELECT m.title as title, p.name as preacher FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p where m.id = $id");
		return $title.' ('.stripslashes($sermon->title).' - '.stripslashes($sermon->preacher).')';
	}
	else
		return $title;
}

//Fix for AudioPlayer v2
if (!function_exists('ap_insert_player_widgets') & function_exists('insert_audio_player')) {
	function ap_insert_player_widgets($params) {
		return insert_audio_player($params);
	}
}

function sb_edit_link ($id) {
	if (current_user_can('edit_posts')) 
		echo '<div class="sb_edit_link"><a href="'.get_bloginfo('wpurl').'/wp-admin/admin.php?page=sermon-browser/new_sermon.php&mid='.$id.'">Edit Sermon</a></div>';
}

//Download external webpage
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
		preg_match( '@([\w/+]+)(;\s+charset=(\S+))?@i', $content_type, $matches );
		if (isset($matches[3])) {$charset = $matches[3];}
			else {$charset = 'ISO-8859-1';} //Assume this charset for non-esv texts
		$blog_charset = get_option('blog_charset');
		if (strcasecmp($blog_charset, $charset)<>0) $contents = iconv ($charset, $blog_charset, $contents);
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
		fclose($handle);
		}
	}
	return $contents;
}

//Tidy Bible reference
function sb_tidy_reference ($start, $end) {
	$r1 = $start['book'];
	$r2 = $start['chapter'];
	$r3 = $start['verse'];
	$r4 = $end['book'];
	$r5 = $end['chapter'];
	$r6 = $end['verse'];
	if (empty($start['book'])) {
		return '';
	}
	if ($start['book'] == $end['book']) {
		if ($start['chapter'] == $end['chapter']) {
			$reference = "$r1 $r2:$r3-$r6";
		}
		else $reference = "$r1 $r2:$r3-$r5:$r6";
	}	
	else $reference =  "$r1 $r2:$r3 - $r4 $r5:$r6";
	return $reference;
}

//Add ESV text
function sb_add_esv_text ($start, $end) {
	// If you are experiencing errors, you should sign up for an ESV API key, and insert the name of your key in place of the letters IP in the URL below (.e.g. ...passageQuery?key=YOURAPIKEY&passage=...)
	$esv_url = 'http://www.esvapi.org/v2/rest/passageQuery?key=IP&passage='.urlencode(sb_tidy_reference ($start, $end)).'&include-headings=false&include-footnotes=false';
	return sb_download_page ($esv_url);
}

//Add Bible text to single sermon page
function sb_add_bible_text ($start, $end, $version) {
	if ($version == "esv") {
		return sb_add_esv_text ($start, $end);
	}
	else {
		global $books;
		$r1 = array_search($start['book'], $books)+1;
		$r2 = $start['chapter'];
		$r3 = $start['verse'];
		$r4 = array_search($end['book'], $books)+1;
		$r5 = $end['chapter'];
		$r6 = $end['verse'];
		if (empty($start['book'])) {
			return '';
		}
		$ls_url = 'http://api.seek-first.com/v1/BibleSearch.php?type=lookup&appid=seekfirst&startbooknum='.$r1.'&startchapter='.$r2.'&startverse='.$r3.'&endbooknum='.$r4.'&endchapter='.$r5.'&endverse='.$r6.'&version='.$version;
		$content = sb_download_page ($ls_url);
		//Clean up and re-format data
		if ($content != '') {
			$r1++;
			for (; $r4>=$r1; $r1++) {
				$searchstring = '<BookNum>'.$r1.'</BookNum>';
				$findpos = strpos($content, $searchstring);
				$content=substr_replace($content, '</p><p><span class="chapter-num">'.$books[$r1-1].' 1:1</span>', $findpos, strlen($searchstring));
				$searchstring = '<Verse>1</Verse>';
				$findpos = strpos($content, $searchstring);
				$content=substr_replace($content, '', $findpos, strlen($searchstring));
			}
			if ($r2 > 1) {$r2++;} else {$closepara = '</p><p>';}
			for (; $r5>=$r2; $r2++) {
				$searchstring = '<Chapter>'.$r2.'</Chapter>';
				$findpos = strpos($content, $searchstring);
				$content=substr_replace($content, $closepara.'<span class="chapter-num">'.$r2.':1</span>', $findpos, strlen($searchstring));
				$searchstring = '<Verse>1</Verse>';
				$findpos = strpos($content, $searchstring);
				$content=substr_replace($content, '', $findpos, strlen($searchstring));
			}
			$patterns = array (
				'/<!--(.)*?-->/',
				'/<.?(Result|Results)>/',
				'/<ShortBook>(.*)<\/ShortBook>/',
				'/<Book>(.*)<\/Book>/',
				'/<Copyright>(.*)<\/Copyright>/',
				'/<VersionName>(.*)<\/VersionName>/',
				'/<Title>(.*)<\/Title>/',
				'/<TotalResults>(.*)<\/TotalResults>/',
				'/<BookNum>(.*)<\/BookNum>/',
				'/<Chapter>(.*)<\/Chapter>/',
				'/<Verse>/',
				'/<\/Verse>\n/',
				'/<Text>/',
				'/<\/Text>\n/',
				'/\n/',
			);
			$replace = array (
				'', '', '', '', '', '', '', '', '', '', '<span class="verse-num">', ' </span>', '', '', ' '
			);
			$content = preg_replace ($patterns, $replace, $content);
			while (strpos($content, '  ')!==FALSE) {
				$content = str_replace('  ', ' ', $content);
			}
			return '<div class="'.$version.'"><h2>'.sb_tidy_reference ($start, $end). '</h2><p>'.$content.' (<a href="http://biblepro.bibleocean.com/dox/default.aspx">'. strtoupper($version). '</a>)</p></div>';
		}
	}
}

//Print unstyled bible passage
function sb_print_bible_passage ($start, $end) {
	$r1 = $start['book'];
	$r2 = $start['chapter'];
	$r3 = $start['verse'];
	$r4 = $end['book'];
	$r5 = $end['chapter'];
	$r6 = $end['verse'];
	if (empty($start['book'])) {
		return '';
	}
	if ($start['book'] == $end['book']) {
		if ($start['chapter'] == $end['chapter']) {
			$reference = "$r1 $r2:$r3-$r6";
		}
		else $reference = "$r1 $r2:$r3-$r5:$r6";
	}	
	else $reference =  "$r1 $r2:$r3 - $r4 $r5:$r6";
	echo "<p class='bible-passage'>".$reference."</p>";
}

// Display podcast or do download
function sb_hijack() {
	if (isset($_REQUEST['podcast'])) {
		global $wordpressRealPath;
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
		include($wordpressRealPath.'/wp-content/plugins/sermon-browser/podcast.php');
		die();
	}
	if (isset($_REQUEST['download']) AND isset($_REQUEST['file_name'])) {
		global $wordpressRealPath;
		$file_name = urldecode($_GET['file_name']);
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment; filename=".$file_name.";");
		header("Content-Transfer-Encoding: binary");
		$file_name = $wordpressRealPath.get_option("sb_sermon_upload_dir").$file_name;
		header("Content-Length: ".filesize($file_name));
		@readfile($file_name);
		exit();
	}
	if (isset($_REQUEST['download']) AND isset($_REQUEST['url'])) {
		$url = urldecode($_GET['url']);
		if(ini_get('allow_url_fopen')) {
			$headers = array_change_key_case(get_headers($url, 1),CASE_LOWER);
			$filesize = $headers['content-length'];
			$cd =  $headers['content-disposition'];
			$location =  $headers['location'];
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
				header("Content-Disposition: attachment; filename=".basename($url).";"); }
			header("Content-Transfer-Encoding: binary");
			if ($filesize) header("Content-Length: ".$filesize);
			@readfile($url);
			exit();
		}
		else {
			header('Location: '.$url);
		}
	}
}

//Emulates get_headers on PHP 4
if (!function_exists('get_headers')) {
function get_headers($Url, $Format= 0, $Depth= 0) {
    if ($Depth > 5) return;
    $Parts = parse_url($Url);
    if (!array_key_exists('path', $Parts))   $Parts['path'] = '/';
    if (!array_key_exists('port', $Parts))   $Parts['port'] = 80;
    if (!array_key_exists('scheme', $Parts)) $Parts['scheme'] = 'http';

    $Return = array();
    $fp = fsockopen($Parts['host'], $Parts['port'], $errno, $errstr, 30);
    if ($fp) {
        $Out = 'GET '.$Parts['path'].(isset($Parts['query']) ? '?'.@$Parts['query'] : '')." HTTP/1.1\r\n".
               'Host: '.$Parts['host'].($Parts['port'] != 80 ? ':'.$Parts['port'] : '')."\r\n".
               'Connection: Close'."\r\n";
        fwrite($fp, $Out."\r\n");
        $Redirect = false; $RedirectUrl = '';
        while (!feof($fp) && $InLine = fgets($fp, 1280)) {
            if ($InLine == "\r\n") break;
            $InLine = rtrim($InLine);

            list($Key, $Value) = explode(': ', $InLine, 2);
            if ($Key == $InLine) {
                if ($Format == 1)
                        $Return[$Depth] = $InLine;
                else    $Return[] = $InLine;

                if (strpos($InLine, 'Moved') > 0) $Redirect = true;
            } else {
                if ($Key == 'Location') $RedirectUrl = $Value;
                if ($Format == 1)
                        $Return[$Key] = $Value;
                else    $Return[] = $Key.': '.$Value;
            }
        }
        fclose($fp);
        if ($Redirect && !empty($RedirectUrl)) {
            $NewParts = parse_url($RedirectUrl);
            if (!array_key_exists('host', $NewParts))   $RedirectUrl = $Parts['host'].$RedirectUrl;
            if (!array_key_exists('scheme', $NewParts)) $RedirectUrl = $Parts['scheme'].'://'.$RedirectUrl;
            $RedirectHeaders = get_headers($RedirectUrl, $Format, $Depth+1);
            if ($RedirectHeaders) $Return = array_merge_recursive($Return, $RedirectHeaders);
        }
        return $Return;
    }
    return false;
}}

// main entry
function sb_sermons_filter($content) {
	global $wpdb, $clr;
	global $wordpressRealPath, $isMe;
	if (!strstr($content, '[sermons]')) { 
	    $isMe = false; return $content;
    } else {
        $isMe = true;
    }
	ob_start();
	
	if ($_GET['sermon_id']) {
		$clr = true;
		$sermon = sb_get_single_sermon((int) $_GET['sermon_id']);
		include($wordpressRealPath.'/wp-content/plugins/sermon-browser/single.php');
	} else {
		$clr = false;
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
		$_REQUEST['page'] ? $_REQUEST['page'] : 1			
		);
		include($wordpressRealPath.'/wp-content/plugins/sermon-browser/multi.php');		
	}			
	$content = str_replace('[sermons]', ob_get_contents(), $content);
	
	ob_end_clean();		
	
	return $content;
}

function sb_build_url($arr, $clear = false) {
	global $wl, $post, $sef, $wpdb;
	$sef = sb_display_url();
	$foo = array_merge((array) $_GET, (array) $_POST, $arr);
	foreach ($foo as $k => $v) {
		if (!$clear || in_array($k, array_keys($arr)) || !in_array($k, $wl)) {
			$bar[] = "$k=$v";
		}
	}
	if ($sef != "") return $sef.'?' . implode('&', $bar);
	return get_bloginfo('url') . '?' . implode('&', $bar);
}

function sb_print_header() {
	global $sermon_domain, $sermompage;
	$url = get_bloginfo('wpurl');
?>
	<link rel="alternate" type="application/rss+xml" title="<?php _e('Sermon podcast', $sermon_domain) ?>" href="<?php echo get_option('sb_podcast') ?>" />
	<link rel="stylesheet" href="<?php echo $url ?>/wp-content/plugins/sermon-browser/datepicker.css" type="text/css"/>
	<link rel="stylesheet" href="<?php echo $url ?>/wp-content/plugins/sermon-browser/style.css" type="text/css"/>
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/jquery/jquery.js"></script>
	<script type="text/javascript" src="<?php echo $url ?>/wp-content/plugins/sermon-browser/datePicker.js"></script>
<?php
}

// pretty books
function sb_get_books($start, $end) {
	$r1 = '<a href="'.sb_get_book_link($start['book']).'">'.$start['book'].'</a>';
	$r2 = $start['chapter'];
	$r3 = $start['verse'];
	
	$r4 = '<a href="'.sb_get_book_link($end['book']).'">'.$end['book'].'</a>';
	$r5 = $end['chapter'];
	$r6 = $end['verse'];
	
	if (empty($start['book'])) {
		return '';
	}
	
	if ($start['book'] == $end['book']) {
		if ($start['chapter'] == $end['chapter']) {
		    if($start['verse'] == $end['verse']){
		        return "$r1 $r2:$r3";
		    }
			return "$r1 $r2:$r3-$r6";
		}
		return "$r1 $r2:$r3-$r5:$r6";
	}	
	return "$r1 $r2:$r3 - $r4 $r5:$r6";
}

function sb_podcast_url() {
	return str_replace(' ', '%20', sb_build_url(array('podcast' => 1, 'dir'=>'desc', 'sortby'=>'m.date')));
}

function sb_print_first_mp3($sermon) {
	$stuff = sb_get_stuff($sermon);
	foreach ((array) $stuff['Files'] as $file) {
		$ext = substr($file, strrpos($file, '.') + 1);
		if (strtolower($ext) == 'mp3') {
			echo str_replace(' ', '%20', get_option('sb_sermon_upload_url').$file);
			break;
		}
	}
}
function sb_print_first_mp3_size($sermon) {
    global $wordpressRealPath;
	$stuff = sb_get_stuff($sermon);
	foreach ((array) $stuff['Files'] as $file) {
		$ext = substr($file, strrpos($file, '.') + 1);
		if (strtolower($ext) == 'mp3') {
		    $filename = $wordpressRealPath.get_option('sb_sermon_upload_dir').$file;
			echo @filesize($filename);
			break;
		}
	}
}

function sb_print_sermon_link($sermon) {
	echo sb_build_url(array('sermon_id' => $sermon->id), true);
}

function sb_print_preacher_link($sermon) {
	global $clr;
	echo sb_build_url(array('preacher' => $sermon->pid), $clr);
}

function sb_print_series_link($sermon) {
	global $clr;	
	echo sb_build_url(array('series' => $sermon->ssid), $clr);
}

function sb_print_service_link($sermon) {
	global $clr;
	echo sb_build_url(array('service' => $sermon->sid), $clr);
}

function sb_get_book_link($book_name) {
	global $clr;
	return sb_build_url(array('book' => $book_name), $clr);
}

function sb_get_tag_link($tag) {
	global $clr;
	return sb_build_url(array('stag' => $tag), $clr);
}

function sb_print_tags($tags) {
	foreach ((array) $tags as $tag) {
		$out[] = '<a href="'.sb_get_tag_link($tag).'">'.$tag.'</a>';
	}
	$tags = implode(', ', (array) $out);
	echo $tags;
}

function sb_print_tag_clouds() {
	global $wpdb;
	$rawtags = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_tags as t RIGHT JOIN {$wpdb->prefix}sb_sermons_tags as st ON t.id = st.tag_id");
	foreach ($rawtags as $tag) {
		$cnt[$tag->name]++;
	}
	
	$minfont = 10;
	$maxfont = 26;
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
		$out[] = '<a style="font-size:'.(int) $size.'px" href="'.sb_get_tag_link($tag).'">'.$tag.'</a>';
	}
	echo implode(' ', $out);
}

function sb_print_next_page_link($limit = 15) {
	global $sermon_domain;
	$current = $_REQUEST['page'] ? (int) $_REQUEST['page'] : 1;
	if ($current < sb_page_count($limit)) {
		$url = sb_build_url(array('page' => ++$current));
		echo '<a href="'.$url.'">'.__('Next page &raquo;', $sermon_domain).'</a>';
	}	
}

function sb_print_prev_page_link($limit = 15) {
	global $sermon_domain;
	$current = $_REQUEST['page'] ? (int) $_REQUEST['page'] : 1;
	if ($current > 1) {
		$url = sb_build_url(array('page' => --$current));
		echo '<a href="'.$url.'">'.__('&laquo; Previous page', $sermon_domain).'</a>';
	}	
}
/*
function sb_print_file($name) {
	$file_url = get_option('sb_sermon_upload_url').$name;
	sb_print_url($file_url);
}
*/
function sb_print_iso_date($sermon) {
	echo date('d M Y H:i:s O', strtotime($sermon->date.' '.$sermon->time));
}

function sb_print_url($url) {
	global $siteicons, $default_site_icon ,$filetypes;
	if (!substr($url,0,7) == "http://")
		$url=get_option('sb_sermon_upload_url').$url;
	$icon_url = get_bloginfo('wpurl').'/wp-content/plugins/sermon-browser/icons/';
	$uicon = $default_site_icon;
	foreach ($siteicons as $site => $icon) {
		if (strpos($url, $site) !== false) {
			$uicon = $icon;
			break;
		}
	}
	$pathinfo = pathinfo($url);
	$ext = $pathinfo['extension'];
	$uicon = isset($filetypes[$ext]['icon']) ? $filetypes[$ext]['icon'] : $uicon;
	if (strtolower($ext) == 'mp3' && function_exists('ap_insert_player_widgets')) {
	    echo ap_insert_player_widgets('[audio:'.$url.']');
	} else {
	    echo '<a href="'.$url.'"><img class="site-icon" alt="'.$url.'" title="'.$url.'" src="'.$icon_url.$uicon.'"></a>';
	}
    
}

function sb_print_url_link($url) {
	echo '<div class="sermon_file">';
	sb_print_url ($url);
	if (substr($url,0,7) == "http://") {
		$param="url"; }
	else {
		$param="file_name"; }
	if (substr($url, -4) == ".mp3" && function_exists('ap_insert_player_widgets')) {
		$url = URLencode($url);
		echo ' <a href="'.sb_display_url().'?download&'.$param.'='.$url.'">Download</a>';
	}
	echo '</div>';
}
/*
function sb_print_download_link($name) {
	echo '<div class="sermon_file">';
	sb_print_url($name);
	if (substr($name, -4) == ".mp3" && function_exists('ap_insert_player_widgets')) {
		$url = URLencode($name);
		echo ' <a href="'.sb_display_url().'?download&file_name='.$url.'">Download</a>';
	}
	echo '</div>';
}
*/
function sb_print_code($code) {
	echo base64_decode($code);
}

function sb_print_preacher_description($sermon) {
	global $sermon_domain;
	if (strlen($sermon->preacher_description)>0) {
		echo "<div class='preacher-description'><span class='about'>" . __('About', $sermon_domain).' '.stripslashes($sermon->preacher).': </span>';
		echo "<span class='description'>".stripslashes($sermon->preacher_description)."</span></div>";
	}
}

function sb_print_preacher_image($sermon) {
	if ($sermon->image) 
		echo "<img alt='".stripslashes($sermon->preacher)."' class='preacher' src='".get_bloginfo("wpurl").get_option("sb_sermon_upload_dir")."images/".$sermon->image."'>";
}

function sb_print_next_sermon_link($sermon) {
	global $wpdb;
	$next = $wpdb->get_row("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE date > '$sermon->date' AND id <> $sermon->id ORDER BY date asc");
	if (!$next) return;
	echo '<a href="';
	sb_print_sermon_link($next);
	echo '">'.stripslashes($next->title).' &raquo;</a>';
}

function sb_print_prev_sermon_link($sermon) {
	global $wpdb;
	$prev = $wpdb->get_row("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE date < '$sermon->date' AND id <> $sermon->id ORDER BY date desc");
	if (!$prev) return;
	echo '<a href="';
	sb_print_sermon_link($prev);
	echo '">&laquo; '.stripslashes($prev->title).'</a>';
}

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

function sb_print_sermons_count() {
	echo sb_count_sermons(array(
		'title' => $_REQUEST['title'],
		'preacher' => $_REQUEST['preacher'],
		'date' => $_REQUEST['date'],
		'enddate' => $_REQUEST['enddate'],
		'series' => $_REQUEST['series'],
		'service' => $_REQUEST['service'],
		'book' => $_REQUEST['book'],
		'tag' => $_REQUEST['stag'],
	));
}

function sb_page_count($limit = 15) {
	$total = sb_count_sermons(array(
		'title' => $_REQUEST['title'],
		'preacher' => $_REQUEST['preacher'],
		'date' => $_REQUEST['date'],
		'enddate' => $_REQUEST['enddate'],
		'series' => $_REQUEST['series'],
		'service' => $_REQUEST['service'],	
		'book' => $_REQUEST['book'],		
		'tag' => $_REQUEST['stag'],		
	));
	return ceil($total / $limit);
}

function sb_count_sermons($filter) {
	global $wpdb, $sermoncount;
	if (!$sermoncount) {
		$default_filter = array(
			'title' => '',
			'preacher' => 0,
			'date' => '',
			'enddate' => '',
			'series' => 0,
			'service' => 0,
			'book' => '',
			'tag' => '',
		);	
		$filter = array_merge($default_filter, $filter);	
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
		$query = "SELECT COUNT(*) 
			FROM {$wpdb->prefix}sb_sermons as m 
			LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id 
			LEFT JOIN {$wpdb->prefix}sb_services as s ON m.service_id = s.id 
			LEFT JOIN {$wpdb->prefix}sb_series as ss ON m.series_id = ss.id 
			LEFT JOIN {$wpdb->prefix}sb_books_sermons as bs ON bs.sermon_id = m.id $bs 
			LEFT JOIN {$wpdb->prefix}sb_books as b ON bs.book_name = b.name 
			LEFT JOIN {$wpdb->prefix}sb_sermons_tags as st ON st.sermon_id = m.id 
			LEFT JOIN {$wpdb->prefix}sb_tags as t ON t.id = st.tag_id 
			WHERE 1 = 1 $cond ";
		$sermoncount = $wpdb->get_var($query);
	}
	return $sermoncount;
}

function sb_get_sermons($filter, $order, $page = 1, $limit = 15) {
	global $wpdb;
	$default_filter = array(
		'title' => '',
		'preacher' => 0,
		'date' => '',
		'enddate' => '',
		'series' => 0,
		'service' => 0,
		'book' => '',
		'tag' => '',
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
	$offset = $limit * ($page - 1);
	if ($order['by'] == 'm.date' ) {
	    if(!isset($order['dir'])) $order['dir'] = 'desc';
	    $order['by'] = 'm.date '.$order['dir'].', s.time';
	}
	if ($order['by'] == 'b.id' ) {
	    $order['by'] = 'b.id '.$order['dir'].', bs.chapter '.$order['dir'].', bs.verse';
	}
	$query = "SELECT DISTINCT m.id, m.title, m.description, m.date, m.time, m.start, m.end, p.id as pid, p.name as preacher, p.description as preacher_description, p.image, s.id as sid, s.name as service, ss.id as ssid, ss.name as series 
		FROM {$wpdb->prefix}sb_sermons as m 
		LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id 
		LEFT JOIN {$wpdb->prefix}sb_services as s ON m.service_id = s.id 
		LEFT JOIN {$wpdb->prefix}sb_series as ss ON m.series_id = ss.id 
		LEFT JOIN {$wpdb->prefix}sb_books_sermons as bs ON bs.sermon_id = m.id $bs 
		LEFT JOIN {$wpdb->prefix}sb_books as b ON bs.book_name = b.name 
		LEFT JOIN {$wpdb->prefix}sb_sermons_tags as st ON st.sermon_id = m.id 
		LEFT JOIN {$wpdb->prefix}sb_tags as t ON t.id = st.tag_id 
		WHERE 1 = 1 $cond ORDER BY ". $order['by'] . " " . $order['dir'] . " LIMIT " . $offset . ", " . $limit;
		
	return $wpdb->get_results($query);
}

function sb_get_stuff($sermon) {
	global $wpdb;
	$stuff = $wpdb->get_results("SELECT f.type, f.name FROM {$wpdb->prefix}sb_stuff as f WHERE sermon_id = $sermon->id ORDER BY id desc");
	foreach ($stuff as $cur) {
		${$cur->type}[] = $cur->name;
	}
	return array(		
		'Files' => $file,
		'URLs' => $url,
		'Code' => $code,
	);
}

function sb_print_filters() {
	global $wpdb, $sermon_domain, $books;
	
	$url = get_bloginfo('wpurl');
	
	$preachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY id;");	
	$series = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY id;");
	$services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_services ORDER BY id;");
	
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
	<form method="post" id="sermon-filter">
		<div style="clear:both">
			<table class="sermonbrowser">
				<tr>
					<td class="fieldname"><?php _e('Preacher', $sermon_domain) ?></td>
					<td class="field"><select name="preacher" id="preacher">
							<option value="0" <?php echo $_REQUEST['preacher'] != 0 ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
							<?php foreach ($preachers as $preacher): ?>
							<option value="<?php echo $preacher->id ?>" <?php echo $_REQUEST['preacher'] == $preacher->id ? 'selected="selected"' : '' ?>><?php echo $preacher->name ?></option>
							<?php endforeach ?>
						</select>
					</td>
					<td class="fieldname rightcolumn"><?php _e('Services', $sermon_domain) ?></td>
					<td class="field"><select name="service" id="service">
							<option value="0" <?php echo $_REQUEST['service'] != 0 ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
							<?php foreach ($services as $service): ?>
							<option value="<?php echo $service->id ?>" <?php echo $_REQUEST['service'] == $service->id ? 'selected="selected"' : '' ?>><?php echo $service->name ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
				<tr>
					<td class="fieldname"><?php _e('Book', $sermon_domain) ?></td>
					<td class="field"><select name="book">
							<option value=""><?php _e('[All]', $sermon_domain) ?></option>
							<?php foreach ($books as $book): ?>
							<option value="<?php echo $book ?>" <?php echo $_REQUEST['book'] == $book ? 'selected=selected' : '' ?>><?php echo $book ?></option>
							<?php endforeach ?>
						</select>
					</td>
					<td class="fieldname rightcolumn"><?php _e('Series', $sermon_domain) ?></td>
					<td class="field"><select name="series" id="series">
							<option value="0" <?php echo $_REQUEST['series'] != 0 ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
							<?php foreach ($series as $item): ?>
							<option value="<?php echo $item->id ?>" <?php echo $_REQUEST['series'] == $item->id ? 'selected="selected"' : '' ?>><?php echo $item->name ?></option>
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
	<script type="text/javascript">
		jQuery.datePicker.setDateFormat('ymd','-');
		jQuery('#date').datePicker({startDate:'01/01/1970'});
		jQuery('#enddate').datePicker({startDate:'01/01/1970'});
	</script>
<?php
}

?>