<?php
/*
Plugin Name: Sermon Browser
Plugin URI: http://www.sermonbrowser.com/
Description: Upload sermons to your website, where they can be searched, listened to, and downloaded. Easy to use with comprehensive help and tutorials.
Author: Mark Barnes
Text Domain: sermon-browser
Version: 0.46.1
Author URI: https://www.markbarnes.net/
Requires at least: 5.8
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Copyright (c) 2008-2026 Mark Barnes

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

The structure of this plugin is as follows:
===========================================
sermon.php     - This file. Defines the constants, loads the files below and starts the plugin (SermonBrowser\Plugin::load()).

In sb-includes:
functions.php     - Functions shared by the admin pages and the front end: options, the sermons page's URL, the upload folder.
sermons.php       - Reads sermons and their attachments from the database.
template-tags.php - Functions the templates call to print sermons, and sb_display_sermons() for themes.
classes/          - Classes (namespace SermonBrowser), loaded by sb_autoload(): the plugin's hooks (Plugin), the shortcode,
                    filters, widgets, podcast feed, downloads, stylesheet, Bible text, page head and admin bar.
admin.php         - The admin pages.
ajax.php          - Handles the admin pages' AJAX requests.
dictionary.php    - Compiles the templates' tags into PHP. Used when saving a template.
filetypes.php     - The names, icons and MIME types of the file types sermons may have attached.
sb-install.php    - Installs the plugin's tables and default settings.
uninstall.php     - Removes the plugin's tables, options and (if asked) files.
upgrade.php       - Upgrades from earlier versions.

To follow the logic of the code, start with SermonBrowser\Plugin::load() and trace the WordPress actions and filters.
The sermon list and sermon pages are output by SermonBrowser\Shortcode::render().

*/

if (!defined('ABSPATH'))
	exit;

define('SB_CURRENT_VERSION', '0.46.1');
define('SB_DATABASE_VERSION', '1.7');
define('SB_PLUGIN_FILE', __FILE__);
sb_define_constants();
spl_autoload_register('sb_autoload');
require_once (SB_INCLUDES_DIR.'/functions.php');
require_once (SB_INCLUDES_DIR.'/sermons.php');
require_once (SB_INCLUDES_DIR.'/template-tags.php');
SermonBrowser\Plugin::load();

/**
* Loads the plugin's classes (namespace SermonBrowser) from sb-includes/classes
*
* SermonBrowser\Widgets\Tag_Cloud is in sb-includes/classes/widgets/class-tag-cloud.php.
* @param string $class
*/
function sb_autoload($class) {
	if (strpos($class, 'SermonBrowser\\') !== 0)
		return;
	$parts = explode('\\', strtolower(str_replace('_', '-', substr($class, strlen('SermonBrowser\\')))));
	$name = array_pop($parts);
	$file = __DIR__.'/sb-includes/classes/'.($parts ? implode('/', $parts).'/' : '').'class-'.$name.'.php';
	if (is_readable($file))
		require $file;
}

/**
* Defines a number of constants used throughout the plugin
*/
function sb_define_constants() {
	$directories = explode(DIRECTORY_SEPARATOR,dirname(__FILE__));
	if ($plugin_dir = $directories[count($directories)-1] == 'mu-plugins' || is_multisite()) {
		define('IS_MU', TRUE);
	} else {
		define('IS_MU', FALSE);
	}
	if ($directories[count($directories)-1] == 'mu-plugins' )
		define ('SB_PLUGIN_URL', content_url().'/'.$plugin_dir);
	else
		define ('SB_PLUGIN_URL', rtrim(content_url().'/plugins/'.plugin_basename(dirname(__FILE__)), '/'));
	define ('SB_PLUGIN_DIR', sb_sanitise_path(defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH.'wp-content').'/plugins');
	define ('SB_WP_CONTENT_DIR', sb_sanitise_path(WP_CONTENT_DIR));
	define ('SB_INCLUDES_DIR', SB_PLUGIN_DIR.'/sermon-browser/sb-includes');
	define ('SB_ABSPATH', sb_sanitise_path(ABSPATH));
}

/**
* Sanitizes Windows paths
*/
function sb_sanitise_path ($path) {
	$path = str_replace('\\','/',$path);
	$path = preg_replace('|/+|','/', $path);
	return $path;
}
?>