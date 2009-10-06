=== Plugin Name ===
Contributors: mark8barnes
Donate link: http://www.4-14.org.uk/sermon-browser#support
Tags: sermons, podcast, mp3, church, bible, audio, widget, embed, video, esv, wpmu, preach, iTunes, preacher, listen
Requires at least: 2.6
Tested up to: 2.8.4
Stable tag: trunk

Upload sermons to your website, where they can be searched, listened to, and downloaded. Easy to use with comprehensive help and tutorials.

== Description ==

The Sermon Browser Wordpress Plugin allows churches to simply upload sermons to their website, where they can be searched, listened to, and downloaded. It is easy to use with comprehensive help and tutorials. Features include:

1. Sermons can be **searched** by topic, preacher, bible passage or date.
2. Full **podcasting** capabilities, including custom podcasts for individual users.
3. Sermons uploaded in mp3 format can be **played directly** on your website using the [1PixelOut Audio Player](http://wpaudioplayer.com/).
4. An optional **sidebar widget** displays sermons on all of your posts or pages.
5. **Embed videos** and other flash files from sites such as [YouTube](http://www.youtube.com/) or [Vimeo](http://www.vimeo.com/).
6. **Other file types** can also be uploaded, including PDF, Powerpoint, Word, text and RTF. Multiple files can be attached to single sermons.
7. The **full Bible text** of the passage being preached on can be included on each sermon page (eight different versions, including ESV).
8. Files can be uploaded to your own site **through the browser or via FTP**. Alternatively you can use other free audio hosting sites.
9. Powerful **templating function** allows complete customisation to complement the look of your site.
10. Simple statistics show how often each sermon has been listened to.
11. Support for [Wordpress MU](http://mu.wordpress.org/) (WPMU).
12. Extensive **help** and [tutorial screencasts](http://www.4-14.org.uk/wordpress-plugins/sermon-browser#tutorial).
13. English, Spanish, Romanian and Russian translations included.

== Installation ==

1. Download the plugin, and unzip it.
2. Place the sermon-browser folder in your wp-content/plugins folder and upload it to your website.
3. Activate the plugin from the plugins tab of your Wordpress admin.
4. You may have to [change the permissions](http://www.4-14.org.uk/wordpress-plugins/sermon-browser/faq#chmod) the upload folder (by default wp-content/uploads/sermons).
5. Create a Wordpress page with the text [sermons]. The plugin will display your sermons on this page.
6. You can also display sermons (filtered according to your criteria) on additional pages or posts by using [shortcodes](http://www.4-14.org.uk/wordpress-plugins/sermon-browser/shortcodes). 

#### Installation in Wordpress MU
1. Download the plugin, and unzip it.
2. Place the contents of the sermon-browser folder in your wp-content/mu-plugins folder and upload it to your website.
3. The plugin will be automatically activated and available for each user.

== Frequently Asked Questions ==

A [comprehensive FAQ](http://www.4-14.org.uk/wordpress-plugins/sermon-browser/faq) is available on the plugin's home page.

== Screenshots ==

1. **Displaying sermons on your website:** This first screen shot shows how Sermon Browser looks to your readers. This particular search shows only those sermons preached from 1 Corinthians. Each of the sermons has audio files, but one also has an embedded video. On the left-hand side you can see the widget showing the most recent sermons preached. At the top you can see a link to both the full podcast, and lower down to a custom podcast.
2. **The single sermon page (with Bible text):** This next screenshot shows the detail for one sermon. You can see additional links to other sermons preached around that time, plus the complete ESV text of the passage being preached on.
3. **Editing a sermon:** The third screenshot shows the main editing window. You can see a wide variety of information can be entered, including the bible passage being preached on, and any files linked to the sermon. Any number of Bible passages can be included, and any number of files (e.g. you could attach an mp3 file for the audio recording, a powerpoint file, and a word document of PDF of the sermon text).
4. **Using the template facility:** The final screenshot shows the options screen with its powerful templating facility. With a little knowledge of HTML and CSS and the Sermon Browser [template tags](http://www.4-14.org.uk/wordpress-plugins/sermon-browser/template-tags), you can easily adapt Sermon Browser's output to suit your own requirements. SermonBrowser produces valid XHTML code, using semantically correct markup and is fully standards compliant.

== Customising SermonBrowser ==

You can customise Sermon Browser to fit in with your own theme, and to display or hide whatever information you choose. If you want to create an extra page on your site that just shows a few sermons (for example, just the sermons preached at a recent conference), use [shortcodes](http://www.4-14.org.uk/wordpress-plugins/sermon-browser/shortcodes).

If you want to customise how Sermon Browser appears throughout your site, use [Template tags](http://www.4-14.org.uk/wordpress-plugins/sermon-browser/template-tags).

== Changelog ==

= 0.43.5 (6 October 2009) =
* **Bug fix:** Fixed bug preventing some external URLs playing in AudioPlayer.

= 0.43.4 (2 October 2009) =
* **Bug fix:** Fixed bug preventing install introduced in 0.43.2.

= 0.43.3 (2 October 2009) =
* **Bug fix:** Fixed bug preventing downloads of external URLs on PHP4.

= 0.43.2 (2 October 2009) =
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

= 0.43.1 (18 September 2009) =
* **New feature:** The Romanian Cornilescu Bible version has been added. Use the template tag [cornliescutext]
* **Optimisation:** Most bible texts now served from SermonBrowser's own API service, not Living Stones Ministries (ESV and NET continue to be supplied direct from the publisher). Unfortunately the Hebrew Names Version is no longer available, but *it makes possible the addition of bibles in dozens of other languages*. If you would like your language included, please ask.
* **Optimisation:** Improved Spanish translation (thanks to Marvin Ortega).
* **Bug fix:** Removed error with wp&#95;timezone&#95;supported() on Wordpress 2.6 and 2.7.
* **Bug fix:** Warning message now correctly displays in admin when [sermons] shortcode is missing.
* **Bug fix:** Stylesheet now loaded correctly even with default permalinks.
* **Bug fix:** Fixed link to podcast background image.

= 0.43 (14 September 2009) =
* **New feature:** Automatically populate entry fields using the ID3 tags in MP3 files (thanks to James Hudson who helped with this feature).
* **New feature:** New widget that displays most popular sermons/series/preachers. Can also be added to your theme by calling sb&#95;print&#95;most&#95;popular(), or to the SermonBrowser template by using [most_popular]
* **New feature:** Optionally hide sermons that do not have files attached.
* **New feature:** Copy MP3 files from other websites to your server.
* **New feature:** Contextual help in admin (Wordpress 2.7+ only).
* **New feature:** Thank you page for those who have donated.
* **Optimisation:** Sermon length now stored in the database.
* **Optimisation:** Major changes to coding structure for better performance and easier updates.
* **Optimisation:** Tidied up code to enable WP_DEBUG to be set without throwing up dozens of notices.
* **Bug fix:** Sort by date now takes proper account of service times.
* **Bug fix:** Double-quotes in sermon titles don't disappear when editing.
* **Bug fix:** Now displays friendly error with invalid sermon_id.
* **Bug fix:** Links to series/service/preacher now work correctly on individual sermon page.
* **Bug fix:** Multiple bible passages and same-day sermons now display more neatly on individual sermon page.
* **Bug fix:** Widgets now link to shortcode pages if there is no [sermons] page.

= 0.42.4 (21 June 2009) =
* **Bug fix:** Fixed several problems in Admin caused by incompatibilities with Wordpress 2.8.

= 0.42.3 (17 April 2009) =
* **Bug fix:** Missing dates now display correctly.
* **Bug fix:** Podcast now works even with PHP4.
* **Bug fix:** Definitely no more SQL warnings on install.

= 0.42.2 (13 April 2009) =
* **Bug fix:** Fixed weird error preventing audio plays on some set-ups.

= 0.42.1 (13 April 2009) =
* **Bug fix:** Fixed SQL error with embedded URLs.
* **Bug fix:** Possible fix for download failure on some set-ups.
* **Bug fix:** Podcast now works even if some URLs are invalid.
* **Bug fix:** No more SQL warnings on install.

= 0.42 (10 April 2009) =
* **New feature:** Sermon Browser shortcodes allow you to include single sermons or lists of sermons on any post or page on your website. For example, adding the shortcode [sermons preacher=1 id=latest] to your pastor's page would display full details of his most recent sermon on that page.
* **Optimisation:** Consolidated various help and tutorial pages to avoid having to keep three different versions up to date!
* **Bug fix:** External URLs now display on the search page alongside attached files.
* **Bug fix:** No more SQL errors on Wordpress custom pages.

= 0.41.2 (9 April 2009) =
* **Bug fix:** Some dates displaying incorrectly after Daylight Savings Time change.

= 0.41.1 (9 April 2009) =
* **Bug fix:** URLs (and not just files) now show up in podcast feeds. Multiple files/URLs per sermon are now also supported, as are some non-mp3 files such as .mov and .mp4
* **Bug fix:** Minor bug affecting people who have renamed their Wordpress database.

= 0.41 (8 April 2009) =
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

= 0.40.2 (3 January 2009) =
* **Bug fix:** Further fix to ensure valid podcast feeds.

= 0.40.1 (30 December 2008) =
* **Bug fix:** Fixed bug introduced in previous version that prevented podcasts working in iTunes.

= 0.40 (12 December 2008) =
* **New feature:** Added sermon browser tag cloud widget.
* **New feature:** Optional mini audio player now in the sidebar widget. Go to the widget options to turn it on.
* **Optimisation:** Admin pages now display correctly in Wordpress 2.7.
* **Optimisation:** Added workaround to ensure iTunes works with Feedburner.
* **Bug fix:** Hopefully finally fixed the podcast problems for those not using permalinks. You may need to resave your options, or even reset your options if upgrading from an earlier version.

= 0.39 (1 December 2008) =
* **New feature:** Sermon Browser in Spanish. Thanks to Juan for providing the translation.
* **New feature:** If you have 'Update services' enabled in Wordpress, a special web-service is now 'pinged' when sermons are edited. This will help your sermons be found by search engines.
* **Bug fix:** Calendar now displays correctly in admin (removed CSS clash).
* **Bug fix:** Bible books and dates now appear correctly in non-English languages. You may need to reset the options to default to make this work.

= 0.38 (29 November 2008) =
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

= 0.37.3 (14 Octoboer 2008) =
* **Bug fix:** HTML should now validate (ampersands in URLs now correct).
* **Bug fix:** Podcast feed on sites without pretty permalinks should now work.

= 0.37.2 (9 October 2008) =
* **Bug fix:** Fixed incompatibilities with a few more plug-ins introduced in the previous version.

= 0.37.1 (8 October 2008) =
* **Bug fix:** Now compatible with the Gengo plugin.

= 0.37 (6 October 2008) =
* **Optimisation:** Download stats are no longer counted for blog authors/admins (thanks Matthew Hiatt)
* **Optimisation:** Sermon Browser now deactivates itself when it is uninstalled.
* **Bug fix:** Rogue slashes no longer display in filter.
* **Bug fix:** Divide by zero error on dashboard fixed (thanks Matthew Hiatt).
* **Bug fix:** User templates now updated after upgrade if dictionary.php is changed.
* **Bug fix:** Sermon Browser now uses the date format from Wordpress settings.

= 0.36 (1 October 2008) =
* **Added:** Two new Bible versions (American King James [AKJV], and the Hebrew Names Version [HNV])
* **Fixed:** Filter now works even when sermons page is the front page of site.
* **Fixed:** Non-ESV bibles now display.

= 0.35 (4 August 2008) =
* **Added:** Simple statistics on Dashboard (Wordpress 2.5+)
* **Optimisation:** Large download files less likely to cause errors.
* **Optimisation:** More robust tag handling.
* **Fixed:** Closed security loophole.
* **Fixed:** Duplicate indexes bug.
* **Fixed:** PHP errors when adding sermons with no bible passage.

= 0.34 (31 July 2008) =
* **Added:** Support for WordPress MU!
* **Changed:** Will now create 'uploads' folder if it doesn't already exist.
* **Fixed:** YLT and WEB Bibles now display correctly.
* **Fixed:** Minor bug affecting those who have renamed their Wordpress database.

= 0.33 (28 July 2008) =
* **Fixed:** Two minor bugs affecting users with Wordpress installs away from their root directory.

= 0.32 (28 July 2008) =
* **Fixed:** Sermons now download correctly in iTunes (although iTunes downloads won't count towards your stats due to an iTunes limitation).
* **Fixed:** You can now add more than three bible passages to a sermon.
* **Fixed:** ALT text now correct on file icons.
* **Fixed:** Links now work correctly even when Wordpress is not in the root.
* **Fixed:** Double-path bug for podcast URL fixed.
* **Added:** Compatibility with Wordpress 2.6 custom plug-in folders.
* **Added:** Auto-discovery for custom podcasts.
* **Optimisation:** Additional files are now inserted into <HEAD> only when required, rather than on every page.

= 0.31 (3 July 2008) =
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

= 0.30.1 (25 June 2008) =
* **Fixed:** Three bugs - one that prevented uploading, one that made series/sermons/preachers appear in the filters even if they had not been assign to any sermons, and one that prevented downloads when pretty permalinks were not used.

= 0.30 (25 June 2008) =
* **Added:** Statistics! See how often a sermon has been listened to in the admin section. Works for sermons that are downloaded or played inline with the AudioPlayer. It doesn't work (and never can) for embedded files (e.g. videos). At the moment the stats are only there in the admin section, but feel free to suggest ways they could be helpful to viewers of your website.
* **Added:** The drop-down menus for the search options are now more intelligent. (1) They display the number of sermons recorded for each preacher/series/services/bible book. (2) They only display bible books that have sermons listed. (3) The more important preachers/series/services are displayed higher up in the drop-down list.
* **Changed:** Some significant optimisations. The main database queries are now twenty times faster than before. On my server the database activity to display the sermon-browser page takes less than seven hundredths of a second (it was about half a second). This is with 180 sermons in the database.
* **Fixed:** Preacher's name now displays correctly in page title.
* **Fixed:** All links now work correctly even when permalinks are turned off.

= 0.25 (24 June 2008) =
* **Added:** [editlink] tag adds an 'Edit sermon' link if the currently logged-in user has edit privileges.
* **Added:** [sermon_description] tag now displays description of sermon on sermon page
* **Added:** [file_with&#95;download] tag is similar to [file], but adds a download link if the AudioPlayer is displayed
* **Changed:** External URLs and local files are now treated in the same way.

= 0.24 (21 June 2008) =
* **Added:** Plug-in now updates automatically on Wordpress 2.3 and above
* **Added:** Podcasts now include filesizes of MP3 files
* **Fixed:** Now able to delete and rename linked files
* **Fixed:** Custom podcast now always returns most recent sermons
* **Fixed:** Podcasts now return a maximum of 15 sermons

= 0.23 (13 June 2008) =
* **Added:** Individual sermon pages now show sermon details in page title for better navigation and SEO.
* **Added:** Several checks for settings in php.ini that might cause uploads to fail.
* **Added:** Support for cURL (provides better compatibility for displaying bible texts).
* **Fixed:** Now able to display images and descriptions of preachers on their sermons.
* **Optimisation:** Several other minor bug fixes.

= 0.22 (12 June 2008) =
* **Added:** Four new Bible versions: ASV, KJV, YLT and WEB (in addition to ESV)
* **Added:** Support for version 2 of the 1PixelOut Audio Player
* **Optimisation:** Two minor bug fixes.

= 0.21 (12 June 2008) =
* **Added:** Template tags for iTunes specific podcast links.
* **Changed:** Non-administrators can now use Sermon Browser (although only Administrators can change the options).
* **Changed:** Updated default template and CSS. Reset to defaults in Options to use it.
* **Changed:** More options in sidebar widget.
* **Optimisation:** Reduced number of database queries, reducing page-creation time by around 15%.
* **Fixed:** Links in the widget give 'Page not found errors'
* **Fixed:** Filter by date gives incorrect results

= 0.2 (10 June 2008) =
* **Added:** Podcasting support
* **Added:** Sidebar widget
* **Added:** Now possible to edit CSS
* **Added:** Support for embedded video and linked files
* **Added:** Option to display ESV text
* **Added:** Option to allow filtering by Bible book
* **Fixed:** 'Page not found' errors when linking to individual sermons
* **Changed:** Display of bible references is now more intelligent
* **Optimisation:** Many other bug fixes and minor enhancements

= 0.1 (13 May 2008) =
* **Initial release**