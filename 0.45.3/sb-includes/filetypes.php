<?php

// You can edit this file to include your own file types and icons. If you do, why not share it with other users?
// Just add an additional file-type by adding the icon to the icons folder,
//	'ext' => array(
//		'name' => 'Description of filetype',
//		'icon' => 'iconfilename.png',
//		'content-type' => 'content-type',
//	),
// Consult http://www.w3schools.com/media/media_mimeref.asp for content-type reference. Use application/octet-stream
// if unsure.

$filetypes = array(
	'mp3' => array(
		'name' => 'mp3',
		'icon' => 'audio.png',
		'content-type' => 'audio/mpeg',
	),
	'doc' => array(
		'name' => 'Microsoft Word',
		'icon' => 'doc.png',
		'content-type' => 'application/ms-word',
	),
	'docx' => array(
		'name' => 'Microsoft Word',
		'icon' => 'doc.png',
		'content-type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	),
	'rtf' => array(
		'name' => 'Rich Text Format',
		'icon' => 'doc.png',
		'content-type' => 'application/rtf',
	),
	'ppt' => array(
		'name' => 'Powerpoint',
		'icon' => 'ppt.png',
		'content-type' => 'application/vnd.ms-powerpoint',
	),
	'pptx' => array(
		'name' => 'Powerpoint',
		'icon' => 'ppt.png',
		'content-type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
	),
	'pdf' => array(
		'name' => 'Adobe Acrobat',
		'icon' => 'pdf.png',
		'content-type' => 'application/pdf',
	),
	'iso' => array(
		'name' => 'Disk image',
		'icon' => 'iso.png',
		'content-type' => 'application/octet-stream',
	),
	'wma' => array(
		'name' => 'Windows Media Audio',
		'icon' => 'audio.png',
		'content-type' => 'audio/x-ms-wma',
	),
	'txt' => array(
		'name' => 'Text',
		'icon' => 'txt.png',
		'content-type' => 'text/plain',
	),
	'wmv' => array(
		'name' => 'Windows Media Video',
		'icon' => 'video.png',
		'content-type' => 'video/x-ms-wmv',
	),
	'mov' => array(
		'name' => 'Quicktime Video',
		'icon' => 'video.png',
		'content-type' => 'video/quicktime',
	),
	'divx' => array(
		'name' => 'DivX Video',
		'icon' => 'video.png',
		'content-type' => 'video/divx',
	),
	'avi' => array(
		'name' => 'Video',
		'icon' => 'video.png',
		'content-type' => 'video/x-msvideo',
	),
	'xls' => array(
		'name' => 'Microsoft Excel',
		'icon' => 'xls.png',
		'content-type' => 'application/vnd.ms-excel',
	),
	'xlsx' => array(
		'name' => 'Microsoft Excel',
		'icon' => 'xls.png',
		'content-type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	),
	'zip' => array(
		'name' => 'Zip file',
		'icon' => 'zip.png',
		'content-type' => 'application/zip',
	),
	'gz' => array(
		'name' => 'Gzip file',
		'icon' => 'zip.png',
		'content-type' => 'application/x-gzip',
	),
);

$siteicons = array(
	'http://google.com' => 'url.png',
);

$default_file_icon = 'unknown.png';
$default_site_icon = 'url.png';
?>