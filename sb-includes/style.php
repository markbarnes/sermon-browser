<?php 

// Outputs sermon-browser styles as a CSS file

$directories = explode(DIRECTORY_SEPARATOR,dirname(__FILE__));
if ($directories[count($directories)-1] == 'mu-plugins') {
	define('IS_MU', TRUE);
} else {
	define('IS_MU', FALSE);
}

if(IS_MU) {
	$wordpressRealPath = str_replace("\\", "/", dirname(dirname(dirname(dirname(__FILE__)))));
} else {
	$wordpressRealPath = str_replace("\\", "/", dirname(dirname(dirname(dirname(dirname(__FILE__))))));
}
if (file_exists($wordpressRealPath.'/wp-load.php')) {
	require_once($wordpressRealPath.'/wp-load.php');
} else {
	require_once($wordpressRealPath.'/wp-config.php');
}
include_once($wordpressRealPath.'/wp-includes/wp-db.php');

header('Content-Type: text/css');
$lastModifiedDate = get_option('sb_sermon_style_date_modified');
if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModifiedDate) {
	if (php_sapi_name()=='CGI') {
		Header("Status: 304 Not Modified");
	} else {
		Header("HTTP/1.0 304 Not Modified");
	}
} else {
	$gmtDate = gmdate("D, d M Y H:i:s\G\M\T",$lastModifiedDate);
	header('Last-Modified: '.$gmtDate);
}

print (base64_decode(get_option('sb_sermon_style_output')));

?>