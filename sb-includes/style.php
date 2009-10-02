<?php 
// Outputs sermon-browser styles as a CSS file

header('Content-Type: text/css');
$lastModifiedDate = sb_get_option('style_date_modified');
if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModifiedDate) {
	if (php_sapi_name()=='CGI') {
		Header("Status: 304 Not Modified");
	} else {
		Header("HTTP/1.0 304 Not Modified");
	}
} else {
	$gmtDate = gmdate("D, d M Y H:i:s\G\M\T",$lastModifiedDate);
	header('Last-Modified: '.$gmtDate, 200);
}
$expires = 60*60*24*7;
header("Cache-Control: max-age=".$expires);
header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
print (sb_get_option('css_style'));
die();
?>