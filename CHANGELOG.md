# Changelog before 0.46.0

The changes in 0.46.0 and later are in `readme.txt`. Links to the old support forum on sermonbrowser.com, which is closed, have been removed.

## 0.45.22 (29 August 2018)
* **Bug fix:** Sermons couldn't be deleted. Now they can.
* **Bug fix:** Sermons weren't downloading on Apple's Podcast app. Now they are.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1932341/sermon-browser/trunk)

## 0.45.21 (11 August 2018)
* **Bug fix:** Several security improvements.
* **Bug fix:** Support sites that have renamed the wp-content folder
* **Enhancement:** Updated to v3 of the ESV Bible API (v2 was deprecated).
* **Enhancement:** Removed support for flash audio players.
* **Enhancement:** External https media files can now be downloaded
* See [changeset](https://plugins.trac.wordpress.org/changeset?new=1927443%40sermon-browser%2Ftrunk&old=1686060%40sermon-browser%2Ftrunk)

## 0.45.20 (27 June 2017)
* **Bug fix:** Added nonces to enhance security protection.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1686060/sermon-browser/trunk)

## 0.45.19 (31 May 2016)
* **Bug fix:** Fixed bug introduced in 0.45.16 that prevented iframe embeds from being saved.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1427807/sermon-browser/trunk)

## 0.45.18 (30 May 2016)
* **Bug fix:** Fixed bug that prevented shortcodes from working.
* **Enhancement:** No need to surround embedded videos with the [embed] shortcode.
* **Enhancement:** Added Italian translation (thanks to Manoah Cammarano)
* See [changeset](https://plugins.trac.wordpress.org/changeset/1426889/sermon-browser/trunk)

## 0.45.17 (23 May 2016)
* **Enhancement:** Minor, under-the-hood changes to translations.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1422176/sermon-browser/trunk)

## 0.45.16 (21 April 2016)
* **Bug fix:** Fixed potential XSS vulnerabilities.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1413162/sermon-browser/trunk)

## 0.45.15 (10 November 2015)
* **Bug fix:** Sermon duration is now correctly calculated.
* **Bug fix:** Fixed bug that prevented sermons being added from the 'Files' page.
* **Bug fix:** Remove empty rows from the list of files.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1283205/sermon-browser/trunk)

## 0.45.14 (6 November 2015)
* **Bug fix:** Fixed bug introduced in 0.45.13 which prevented series being edited/saved.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1281420/sermon-browser/trunk)

## 0.45.13 (6 November 2015)
* **Bug fix:** Compatibility with PHP 5.2 to 5.6 and WordPress 3.6 to 4.3.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1281163/sermon-browser)

## 0.45.12 (16 September 2013)
* **Bug fix:** Removed call to deprecated wp_timezone_supported function if WordPress version is 3.2 or later.
* **Bug fix:** In default style template, font-style should be "italic", not "italics"..
* **Bug fix:** Replaced deprecated functions wpdb::escape, attribute_escape, ereg_replace
* **Bug fix:** Download link now works for MP3 URLs with capital letter extensions. (Lowercase extensions recommended for best compatibility.).
* **Bug fix:** Strengthen plugin against bad URL requests..
* **Bug fix:** Fixed creation of "images" subfolder when moving sermon Upload folder to new location.
* **Bug fix:** Improved download link operation on Android..
* See [changeset](http://plugins.trac.wordpress.org/changeset/773534/sermon-browser)

## 0.45.11 (7 August 2013)
* **Bug fix:** Fixed Media Upload conflict with WordPress 3.6 related to getid3 library..
* See [changeset](https://plugins.trac.wordpress.org/changeset/753109/sermon-browser)

## 0.45.10 (31 July 2013)
* **Enhancement:** Welsh translation (cy) added.
* **Bug fix:** Sermon Tag Widget fixed..
* **Bug fix:** Bible book links fixed for translated installations..
* **Bug fix:** Bible books in database were wrong for new installations on non-English sites.  Corrected new installations.
* See [changeset](https://plugins.trac.wordpress.org/changeset/748934/sermon-browser)

## 0.45.9 (8 July 2013)
* **Bug fix:** Auto fill of Bible books on Sermon edit admin screen works better.
* **Bug fix:** Fix Bible passage link on function sb_display_sermons.
* **Bug fix:** Fix sermon title link on Recent Sermons widget.
* **Bug fix:** Loosen requirements for a valid header from MP3 at external URL.
* **Bug fix:** Fix "No Sermons Found" bug when sermon is missing Series info.
* **Bug fix:** Widget miniplayer didn't work with MP3 files attached via URL.
* See [changeset](https://plugins.trac.wordpress.org/changeset/737860/sermon-browser)

## 0.45.8 (31 May 2013)
* **Bug fix:** Allow MP3 external URLs beginning with "https://".
* **Bug fix:** Sermon page not recognized for new installs.
* **Enhancement:** Default settings now support MediaElement.js player, or built-in player with WordPress 3.6.
* See [changeset](http://plugins.trac.wordpress.org/changeset/723323/)

## 0.45.7 (21 May 2013)
* **Bug fix:** Fix for function sb_display_sermons, a function users can use to display sermons in their theme templates.
* **Bug fix:** Fix ability to delete series, services, and preachers.
* **Bug fix:** Import ID3 tags now working from Create Sermon page.
* See [changeset](http://plugins.trac.wordpress.org/changeset/716907/sermon-browser)

## 0.45.6 (10 May 2013)
* **Bug fix:** Fixed fatal error on versions of WordPress earlier than 3.0.
* **Bug fix:** Always use absolute links when pretty permalinks are not being used.
* **Bug fix:** Bible book names not showing correctly on add sermon admin screen for translated sites.
* See [changeset](http://plugins.trac.wordpress.org/changeset/710876/sermon-browser)

## 0.45.5 (6 May 2013)
* **Thanks:** Updated version patched by Ben Miller.
* **Bug fix:** Fix style sheet call when WordPress is installed in a subdirectory (home_url is different than site_url).
* **Bug fix:** Help Sermon Browser choose the correct page for the links.
* **Bug fix:** Next page link shows same sermons.
* **Bug fix:** Preachers, series, services admin bug.
* **Bug fix:** Changes to default templates.
* **Bug fix:** Display text from Psalms in non-ESV translations.
* **Bug fix:** Use relative links instead of absolute links in some places, which allows a website to have more than one sermon page.
* **Bug fix:** Download link when using [file_with_download] template tag, not using old WP Audio Player plugin.
* **Bug fix:** Fix for Popular Sermon widget in PHP 5.4.
* **Bug fix:** Translation fixes.
* **Bug fix:** Prevent PHP warnings when ini_set function is disabled.
* **Enhancement:** New Hindi translation, and updated Romanian and Russian translations (thanks to [Chandel](http://outshinesolutions.com/), [Lucian Mihailescu](http://lucianwebservice.com/) and [Vadym Gulyi](http://www.vady.kiev.ua/)).
* See [changeset](http://plugins.trac.wordpress.org/changeset/708683/sermon-browser)

## 0.45.4 (26 May 2011)
* **Bug fix:** Fixes broken iTunes feed on some installations.
* See [changeset](http://plugins.trac.wordpress.org/changeset?reponame=&new=389533%40sermon-browser%2Ftrunk&old=386544%40sermon-browser%2Ftrunk).

## 0.45.3 (19 May 2011)
* **Bug fix:** Fixes further PHP4 incompatibility with default audio player - Fatal error: Call to undefined function: str_ireplace().
* See [changeset](http://plugins.trac.wordpress.org/changeset/386541/sermon-browser/trunk).

## 0.45.2 (15 May 2011)
* **Bug fix:** Fixes bug when upgrading from very early versions of Sermon Browser.
* See [changeset](http://plugins.trac.wordpress.org/changeset/385616/sermon-browser/trunk).

## 0.45.1 (15 May 2011)
* **Bug fix:** Syntax errors on PHP4, and URLs not attaching correctly.
* See [changeset](http://plugins.trac.wordpress.org/changeset/385190/sermon-browser/trunk).

## 0.45 (13 May 2011)
* **New feature:** You can now use many more MP3 players, not just WPAudio. Any MP3 WordPress plugin that supports shortcodes can be used. Go to Options and insert the shortcode for your preferred player.
* **Compatibility:** Now fully compatible with WordPress multisite.
* **Enhancement:** Prevent deletion of final preacher/series/service.
* **Enhancement:** You can now use WordPress shortcode in the 'embed' field on each sermon. For example, if you have a Vimeo plugin installed, you could attached a Vimeo video using the shortcode [vimeo clip_id="XXXXXXX" width="400" height="225"].
* **Enhancement:** You can now see how many sermons are assigned to each preacher, series and service in the admin pages.
* **Bug fix:** Apostrophes and double quotes are now supported in filenames.
* See [changeset](http://plugins.trac.wordpress.org/changeset?reponame=&new=384459%40sermon-browser%2Ftrunk&old=382873%40sermon-browser%2Ftrunk).

## 0.44.1 (5 May 2011)
* **Bug fix:** Fixes bug which breaks sites that have a page with [sermons xxxxx], but no page with just [sermons].
* **Enhancement:** Default CSS works much better with Wordpress 3.0+ default Twenty Ten theme. Existing users should reset the template to default to get it.
* See [changeset](http://plugins.trac.wordpress.org/changeset/382871/sermon-browser)

## 0.44 (30 April 2011)
* **New feature:** Sermon Browser added to the new menu bar in Wordpress 3.1 and above.
* **New feature:** Added support for Brazilian Portuguese and German (thanks to [DJIO](http://www.djio.com.br/sermonbrowser-em-portugues-brasileiro-pt_br/) and Monika Gause).
* **Compatibility:** Now fully compatible with Wordpress 2.6 - 3.1.
* **Enhancement:** [sermons] shortcode now supports the 'limit' and 'dir' parameters (thanks to liggit)
* **Bug fix:** More security fixes.
* **Bug fix:** Custom podcasts are now working again
* **Bug fix:** Fixed SQL_BIG_SELECTS issue on some hosts which could result in blank podcasts and sermons pages
* **Bug fix:** Tags are now displaying correctly on sermons page
* **Bug fix:** Slashes no longer appear in some saved text.
* **Bug fix:** Sermon widget now works for users who have changed their database prefix
* **Bug fix:** Text on javascript pop-ups is now ready for translation
* **Bug fix:** Edit links on the main sermons page are no longer missing after the first page
* **Bug fix:** Missing slash meant preacher image was not displaying for some people
* **Bug fix:** Book counts now accurate even when more than one passage is applied to a sermon
* **Bug fix:** Mini-flash player now inherits the colour of both Audio Player v1, and v2
* **Bug fix:** Filenames with spaces are now encoded in an iTunes compatible way
* **Bug fix:** Sermon filter now always correctly displays which Bible book is being filtered on
* See [changeset](http://plugins.trac.wordpress.org/changeset/379041/sermon-browser/trunk)

## 0.43.6 (26 April 2011)
* **Bug fix:** Important security fixes. All users should upgrade to this version.
* See [changeset](http://plugins.trac.wordpress.org/changeset/379041/sermon-browser/trunk)

## 0.43.5 (6 October 2009)
* **Bug fix:** Fixed bug preventing some external URLs playing in AudioPlayer.
* See [changeset](http://plugins.trac.wordpress.org/changeset/161052/sermon-browser/trunk)

## 0.43.4 (2 October 2009)
* **Bug fix:** Fixed bug preventing install introduced in 0.43.2.
* See [changeset](http://plugins.trac.wordpress.org/changeset/160038/sermon-browser/trunk)

## 0.43.3 (2 October 2009)
* **Bug fix:** Fixed bug preventing downloads of external URLs on PHP4.
* See [changeset](http://plugins.trac.wordpress.org/changeset/160006/sermon-browser/trunk)

## 0.43.2 (2 October 2009)
* **New feature:** Added `url_only` option to sb_display_sermons.
* **New feature:** Added [most_popular] template tag. Displays the data from the 'most popular' widget, but on your sermon search page.
* **New feature:** Added Russian and Romanian translations (thanks to [FatCow](http://www.fatcow.com/) and [Lucian Mihailescu](http://intercer.net)).
* **New feature:** The Russian Synodal Bible version has been added. Use the template tag [synodaltext].
* **Optimisation:** Added error checking for possible bug with PHPs `unserialize` function which caused multiple upgrades to SermonBrowser's database.
* **Optimisation:** More intelligent caching for SermonBrowser's style sheet, meaning quicker page loads.
* **Bug fix:** Podcast fixed for PHP4.
* **Bug fix:** Stopped the SermonBrowser help menu showing for registered users who do not have permissions to use SermonBrowser.
* **Bug fix:** `array_key_exists` error no longer displayed on sidebarless pages.
* **Bug fix:** Series drop down now shows correct search result.
* **Bug fix:** Fixed bug which set the wrong upload path if Wordpress' own upload feature had never been used.
* **Bug fix:** ID3 uploader now hidden until upload options set.
* **Bug fix:** Popular sermons widget now works with non-standard database prefixes.
* See [changeset](http://plugins.trac.wordpress.org/changeset/159840/sermon-browser/trunk)

## 0.43.1 (18 September 2009)
* **New feature:** The Romanian Cornilescu Bible version has been added. Use the template tag [cornliescutext]
* **Optimisation:** Most bible texts now served from SermonBrowser's own API service, not Living Stones Ministries (ESV and NET continue to be supplied direct from the publisher). Unfortunately the Hebrew Names Version is no longer available, but *it makes possible the addition of bibles in dozens of other languages*. If you would like your language included, please ask.
* **Optimisation:** Improved Spanish translation (thanks to Marvin Ortega).
* **Bug fix:** Removed error with wp&#95;timezone&#95;supported() on Wordpress 2.6 and 2.7.
* **Bug fix:** Warning message now correctly displays in admin when [sermons] shortcode is missing.
* **Bug fix:** Stylesheet now loaded correctly even with default permalinks.
* **Bug fix:** Fixed link to podcast background image.
* See [changeset](http://plugins.trac.wordpress.org/changeset/156082/sermon-browser/trunk)

## 0.43 (14 September 2009)
* **New feature:** Automatically populate entry fields using the ID3 tags in MP3 files (thanks to James Hudson who helped with this feature).
* **New feature:** New widget that displays most popular sermons/series/preachers. Can also be added to your theme by calling sb&#95;print&#95;most&#95;popular(), or to the SermonBrowser template by using [most_popular]
* **New feature:** Optionally hide sermons that do not have files attached.
* **New feature:** Copy MP3 files from other websites to your server.
* **New feature:** Contextual help in admin (Wordpress 2.7+ only).
* **Optimisation:** Sermon length now stored in the database.
* **Optimisation:** Major changes to coding structure for better performance and easier updates.
* **Optimisation:** Tidied up code to enable WP_DEBUG to be set without throwing up dozens of notices.
* **Bug fix:** Sort by date now takes proper account of service times.
* **Bug fix:** Double-quotes in sermon titles don't disappear when editing.
* **Bug fix:** Now displays friendly error with invalid sermon_id.
* **Bug fix:** Links to series/service/preacher now work correctly on individual sermon page.
* **Bug fix:** Multiple bible passages and same-day sermons now display more neatly on individual sermon page.
* **Bug fix:** Widgets now link to shortcode pages if there is no [sermons] page.
* See [changeset](http://plugins.trac.wordpress.org/changeset/154761/sermon-browser/trunk)

## 0.42.4 (21 June 2009)
* **Bug fix:** Fixed several problems in Admin caused by incompatibilities with Wordpress 2.8.
* See [changeset](http://plugins.trac.wordpress.org/changeset/127554/sermon-browser/trunk)

## 0.42.3 (17 April 2009)
* **Bug fix:** Missing dates now display correctly.
* **Bug fix:** Podcast now works even with PHP4.
* **Bug fix:** Definitely no more SQL warnings on install.
* See [changeset](http://plugins.trac.wordpress.org/changeset/111224/sermon-browser/trunk)

## 0.42.2 (13 April 2009)
* **Bug fix:** Fixed weird error preventing audio plays on some set-ups.
* See [changeset](http://plugins.trac.wordpress.org/changeset/110084/sermon-browser/trunk)

## 0.42.1 (13 April 2009)
* **Bug fix:** Fixed SQL error with embedded URLs.
* **Bug fix:** Possible fix for download failure on some set-ups.
* **Bug fix:** Podcast now works even if some URLs are invalid.
* **Bug fix:** No more SQL warnings on install.
* See [changeset](http://plugins.trac.wordpress.org/changeset/109985/sermon-browser/trunk)

## 0.42 (10 April 2009)
* **New feature:** Sermon Browser shortcodes allow you to include single sermons or lists of sermons on any post or page on your website. For example, adding the shortcode [sermons preacher=1 id=latest] to your pastor's page would display full details of his most recent sermon on that page.
* **Optimisation:** Consolidated various help and tutorial pages to avoid having to keep three different versions up to date!
* **Bug fix:** External URLs now display on the search page alongside attached files.
* **Bug fix:** No more SQL errors on Wordpress custom pages.
* See [changeset](http://plugins.trac.wordpress.org/changeset/109328/sermon-browser/trunk)

## 0.41.2 (9 April 2009)
* **Bug fix:** Some dates displaying incorrectly after Daylight Savings Time change.
* See [changeset](http://plugins.trac.wordpress.org/changeset/109047/sermon-browser/trunk)

## 0.41.1 (9 April 2009)
* **Bug fix:** URLs (and not just files) now show up in podcast feeds. Multiple files/URLs per sermon are now also supported, as are some non-mp3 files such as .mov and .mp4
* **Bug fix:** Minor bug affecting people who have renamed their Wordpress database.
* See [changeset](http://plugins.trac.wordpress.org/changeset/108954/sermon-browser/trunk)

## 0.41 (8 April 2009)
* **New feature:** Alternative 'one-click' filtering system. Go to SermonBrowser/Options to select it.
* **New feature:** Filter can now be 'minimised' to make better use of space.
* **New feature:** NET Bible can now be used, bringing the total to nine translations.
* **New feature:** Podcast feed now displays sermon length and preacher's name in iTunes.
* **Optimisation:** Less javascript loaded on front-end.
* **Optimisation:** Icon added to admin pages in Wordpress 2.7.
* **Optimisation:** Much more subtle podcast icons should display better in most themes (reset template to default to benefit).
* **Bug fix:** Sermons now display in admin even if there are no bible references or series.
* **Bug fix:** Single verse references now display correctly (thanks to Mark Bouchard).
* **Bug fix:** More error checks for bad data in podcast feed.
* **Bug fix:** Mini-player now displays in correct colour when used with AudioPlayer v2.
* **Change:** The plugin now requires Wordpress 2.5 or above.
* See [changeset](http://plugins.trac.wordpress.org/changeset/108590/sermon-browser/trunk)

## 0.40.2 (3 January 2009)
* **Bug fix:** Further fix to ensure valid podcast feeds.
* See [changeset](http://plugins.trac.wordpress.org/changeset/83832/sermon-browser/trunk)

## 0.40.1 (30 December 2008)
* **Bug fix:** Fixed bug introduced in previous version that prevented podcasts working in iTunes.
* See [changeset](http://plugins.trac.wordpress.org/changeset/83055/sermon-browser/trunk)

## 0.40 (12 December 2008)
* **New feature:** Added sermon browser tag cloud widget.
* **New feature:** Optional mini audio player now in the sidebar widget. Go to the widget options to turn it on.
* **Optimisation:** Admin pages now display correctly in Wordpress 2.7.
* **Optimisation:** Added workaround to ensure iTunes works with Feedburner.
* **Bug fix:** Hopefully finally fixed the podcast problems for those not using permalinks. You may need to resave your options, or even reset your options if upgrading from an earlier version.
* See [changeset](http://plugins.trac.wordpress.org/changeset/79270/sermon-browser/trunk)

## 0.39 (1 December 2008)
* **New feature:** Sermon Browser in Spanish. Thanks to Juan for providing the translation.
* **New feature:** If you have 'Update services' enabled in Wordpress, a special web-service is now 'pinged' when sermons are edited. This will help your sermons be found by search engines.
* **Bug fix:** Calendar now displays correctly in admin (removed CSS clash).
* **Bug fix:** Bible books and dates now appear correctly in non-English languages. You may need to reset the options to default to make this work.
* See [changeset](http://plugins.trac.wordpress.org/changeset/76786/sermon-browser/trunk)

## 0.38 (29 November 2008)
* **New Feature:** Spanish Reina Valera Bible now available (use the code [lbrvtext] in the template). Thanks to Juan and Living Stones Ministries.
* **New Feature:** Podcast now displays the time a sermon was preached, as well as the date.
* **Optimisation:** Administrators and editors can now include any HTML code in sermon descriptions without it being stripped.
* **Optimisation:** Reminder in admin to install AudioPlayer plugin.
* **Optimisation:** Podcast now only includes sermons that have MP3 attachments.
* **Bug fix:** The Uploads page now works correctly. Files can be named and deleted, and the search facility works again. Deleted files no longer show up in the list of unlinked files. Thanks to Matthew Hiatt for solving this.
* **Bug fix:** Users in all timezones should see sermon dates correctly.
* **Bug fix:** File downloads should be much more reliable, and no more randomly corrupted filenames. Filenames with spaces should also no longer present a problem.
* **Bug fix:** Sermon Browser can now be displayed on private pages.
* **Bug fix:** Office 2007 files now have correct MIME-type.
* **Bug fix:** Javascript error in admin due to jQuery conflict is now fixed.
* See [changeset](http://plugins.trac.wordpress.org/changeset/76389/sermon-browser/trunk)

## 0.37.3 (14 October 2008)
* **Bug fix:** HTML should now validate (ampersands in URLs now correct).
* **Bug fix:** Podcast feed on sites without pretty permalinks should now work.
* See [changeset](http://plugins.trac.wordpress.org/changeset/68914/sermon-browser/trunk)

## 0.37.2 (9 October 2008)
* **Bug fix:** Fixed incompatibilities with a few more plug-ins introduced in the previous version.
* See [changeset](http://plugins.trac.wordpress.org/changeset/68148/sermon-browser/trunk)

## 0.37.1 (8 October 2008)
* **Bug fix:** Now compatible with the Gengo plugin.
* See [changeset](http://plugins.trac.wordpress.org/changeset/67967/sermon-browser/trunk)

## 0.37 (6 October 2008)
* **Optimisation:** Download stats are no longer counted for blog authors/admins (thanks Matthew Hiatt)
* **Optimisation:** Sermon Browser now deactivates itself when it is uninstalled.
* **Bug fix:** Rogue slashes no longer display in filter.
* **Bug fix:** Divide by zero error on dashboard fixed (thanks Matthew Hiatt).
* **Bug fix:** User templates now updated after upgrade if dictionary.php is changed.
* **Bug fix:** Sermon Browser now uses the date format from Wordpress settings.
* See [changeset](http://plugins.trac.wordpress.org/changeset/67681/sermon-browser/trunk)

## 0.36 (1 October 2008)
* **Added:** Two new Bible versions (American King James [AKJV], and the Hebrew Names Version [HNV])
* **Fixed:** Filter now works even when sermons page is the front page of site.
* **Fixed:** Non-ESV bibles now display.
* See [changeset](http://plugins.trac.wordpress.org/changeset/67002/sermon-browser/trunk)

## 0.35 (4 August 2008)
* **Added:** Simple statistics on Dashboard (Wordpress 2.5+)
* **Optimisation:** Large download files less likely to cause errors.
* **Optimisation:** More robust tag handling.
* **Fixed:** Closed security loophole.
* **Fixed:** Duplicate indexes bug.
* **Fixed:** PHP errors when adding sermons with no bible passage.
* See [changeset](http://plugins.trac.wordpress.org/changeset/58027/sermon-browser/trunk)

## 0.34 (31 July 2008)
* **Added:** Support for WordPress MU!
* **Changed:** Will now create 'uploads' folder if it doesn't already exist.
* **Fixed:** YLT and WEB Bibles now display correctly.
* **Fixed:** Minor bug affecting those who have renamed their Wordpress database.
* See [changeset](http://plugins.trac.wordpress.org/changeset/57080/sermon-browser/trunk)

## 0.33 (28 July 2008)
* **Fixed:** Two minor bugs affecting users with Wordpress installs away from their root directory.
* See [changeset](http://plugins.trac.wordpress.org/changeset/56760/sermon-browser/trunk)

## 0.32 (28 July 2008)
* **Fixed:** Sermons now download correctly in iTunes (although iTunes downloads won't count towards your stats due to an iTunes limitation).
* **Fixed:** You can now add more than three bible passages to a sermon.
* **Fixed:** ALT text now correct on file icons.
* **Fixed:** Links now work correctly even when Wordpress is not in the root.
* **Fixed:** Double-path bug for podcast URL fixed.
* **Added:** Compatibility with Wordpress 2.6 custom plug-in folders.
* **Added:** Auto-discovery for custom podcasts.
* **Optimisation:** Additional files are now inserted into <HEAD> only when required, rather than on every page.
* See [changeset](http://plugins.trac.wordpress.org/changeset/56704/sermon-browser/trunk)

## 0.31 (3 July 2008)
* **Added:** The sermon description now preserves lines breaks and allows most HTML code.
* **Added:** More helpful warnings if permissions on image uploads folder are incorrect, or if [sermons] tag not included in a page/post.
* **Added:** Option to change how many sermons are displayed per page.
* **Added:** Better security to prevent unauthorised users from editing data.
* **Changed:** Plug-in no longer uses single.php, multi.php and style.css. This should reduce the number of people having file permission problems. Users of older versions can now safely delete those files.
* **Changed:** Templates now have their own menu. Some of the other options screens have been tweaked slightly.
* **Changed:** Podcast now attaches linked URLs as well as uploaded files.
* **Changed:** Tag cloud now uses proportional, not fixed size fonts.
* **Changed:** Default template should display podcast links better on most sites. (It now uses TABLEs rather than DIVs.) Reset the templates to default to benefit.
* **Fixed:** Bible books are now displayed in the filter even for previous users of older versions.
* **Fixed:** Newly uploaded files now save correctly to sermon.
* **Fixed:** Next/previous buttons now display correctly.
* **Fixed:** Prevented 'Maximum time for script is -1 seconds' message on older versions of PHP.
* **Optimisation:** Better commenting throughout PHP.
* See [changeset](http://plugins.trac.wordpress.org/changeset/52938/sermon-browser/trunk)

## 0.30.1 (25 June 2008)
* **Fixed:** Three bugs - one that prevented uploading, one that made series/sermons/preachers appear in the filters even if they had not been assign to any sermons, and one that prevented downloads when pretty permalinks were not used.
* See [changeset](http://plugins.trac.wordpress.org/changeset/52078/sermon-browser/trunk)

## 0.30 (25 June 2008)
* **Added:** Statistics! See how often a sermon has been listened to in the admin section. Works for sermons that are downloaded or played inline with the AudioPlayer. It doesn't work (and never can) for embedded files (e.g. videos). At the moment the stats are only there in the admin section, but feel free to suggest ways they could be helpful to viewers of your website.
* **Added:** The drop-down menus for the search options are now more intelligent. (1) They display the number of sermons recorded for each preacher/series/services/bible book. (2) They only display bible books that have sermons listed. (3) The more important preachers/series/services are displayed higher up in the drop-down list.
* **Changed:** Some significant optimisations. The main database queries are now twenty times faster than before. On my server the database activity to display the sermon-browser page takes less than seven hundredths of a second (it was about half a second). This is with 180 sermons in the database.
* **Fixed:** Preacher's name now displays correctly in page title.
* **Fixed:** All links now work correctly even when permalinks are turned off.
* See [changeset](http://plugins.trac.wordpress.org/changeset/51888/sermon-browser/trunk)

## 0.25 (24 June 2008)
* **Added:** [editlink] tag adds an 'Edit sermon' link if the currently logged-in user has edit privileges.
* **Added:** [sermon_description] tag now displays description of sermon on sermon page
* **Added:** [file_with&#95;download] tag is similar to [file], but adds a download link if the AudioPlayer is displayed
* **Changed:** External URLs and local files are now treated in the same way.
* See [changeset](http://plugins.trac.wordpress.org/changeset/51772/sermon-browser/trunk)

## 0.24 (21 June 2008)
* **Added:** Plug-in now updates automatically on Wordpress 2.3 and above
* **Added:** Podcasts now include filesizes of MP3 files
* **Fixed:** Now able to delete and rename linked files
* **Fixed:** Custom podcast now always returns most recent sermons
* **Fixed:** Podcasts now return a maximum of 15 sermons
* See [changeset](http://plugins.trac.wordpress.org/changeset/51345/sermon-browser/trunk)

## 0.23 (13 June 2008)
* **Added:** Individual sermon pages now show sermon details in page title for better navigation and SEO.
* **Added:** Several checks for settings in php.ini that might cause uploads to fail.
* **Added:** Support for cURL (provides better compatibility for displaying bible texts).
* **Fixed:** Now able to display images and descriptions of preachers on their sermons.
* **Optimisation:** Several other minor bug fixes.
* See [changeset](http://plugins.trac.wordpress.org/changeset/57043/sermon-browser/branches/0.10-0.23)

## 0.22 (12 June 2008)
* **Added:** Four new Bible versions: ASV, KJV, YLT and WEB (in addition to ESV)
* **Added:** Support for version 2 of the 1PixelOut Audio Player
* **Optimisation:** Two minor bug fixes.
* See [changeset](http://plugins.trac.wordpress.org/changeset/57040/sermon-browser/branches/0.10-0.23)

## 0.21 (12 June 2008)
* **Added:** Template tags for iTunes specific podcast links.
* **Changed:** Non-administrators can now use Sermon Browser (although only Administrators can change the options).
* **Changed:** Updated default template and CSS. Reset to defaults in Options to use it.
* **Changed:** More options in sidebar widget.
* **Optimisation:** Reduced number of database queries, reducing page-creation time by around 15%.
* **Fixed:** Links in the widget give 'Page not found errors'
* **Fixed:** Filter by date gives incorrect results
* See [changeset](http://plugins.trac.wordpress.org/changeset/57039/sermon-browser/branches/0.10-0.23)

## 0.2 (10 June 2008)
* **Added:** Podcasting support
* **Added:** Sidebar widget
* **Added:** Now possible to edit CSS
* **Added:** Support for embedded video and linked files
* **Added:** Option to display ESV text
* **Added:** Option to allow filtering by Bible book
* **Fixed:** 'Page not found' errors when linking to individual sermons
* **Changed:** Display of bible references is now more intelligent
* **Optimisation:** Many other bug fixes and minor enhancements
* See [changeset](http://plugins.trac.wordpress.org/changeset/57037/sermon-browser/branches/0.10-0.23)

## 0.1 (13 May 2008)
* **Initial release**
