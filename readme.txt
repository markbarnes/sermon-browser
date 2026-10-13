=== Sermon Browser ===
Contributors: mark8barnes
Tags: sermons, church, podcast, bible, audio
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.48.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Upload sermons to your website, where they can be searched, listened to, and downloaded. Easy to use, with help built in.



== Description ==

The Sermon Browser Wordpress Plugin allows churches to simply upload sermons to their website, where they can be searched, listened to, and downloaded. It is easy to use, and is used on hundreds of church websites. Features include:

1. Store thousands of sermons, and **search** them by topic, preacher, bible passage or date.
2. Full **podcasting** capabilities, including custom podcasts for individual users.
3. Sermons uploaded as MP3s can be **played directly** on your website, using WordPress's built-in player or any MP3 plugin that provides a shortcode.
4. Three optional **sidebar widgets** can display sermons on all of your posts or pages.
5. **Embed videos** from sites such as [YouTube](https://www.youtube.com/) or [Vimeo](https://vimeo.com/), using the embed code those sites provide, or a shortcode from a WordPress plugin.
6. **Other file types** can also be uploaded, including PDF, Powerpoint, Word, text and RTF. Multiple files can be attached to single sermons.
7. The **full Bible text** of the passage being preached on can be included on each sermon page (ESV, with a free API key, or NET).
8. Files can be uploaded to your own site **through the browser or via FTP**. Alternatively you can use free audio hosting sites.
9. Details about each sermon can be **added automatically from the MP3's ID3 tags**.
10. Powerful **templating function** allows complete customisation to complement the look of your site.
11. Simple statistics show how often each sermon has been listened to.
12. Support for multisite.
13. **Help** built in: a help page, and help on each admin page, including every template tag.
14. Report problems and suggest features on [GitHub](https://github.com/markbarnes/sermon-browser/issues).
15. Translated into Brazilian Portugese, German, Hindi, Italian, Romanian, Russian, Spanish, Ukrainian and Welsh.

= Translations provided by =

* Brazilian Portugese [DIJO](http://www.djio.com.br/sermonbrowser-em-portugues-brasileiro-pt_br/)
* German - Monika Gause
* Hindi - [Chanel](http://outshinesolutions.com/)
* Italian - Manoah Cammarano
* Romanian - [Lucian Mihailescu](http://lucianwebservice.com/)
* Russian - [FatCow](http://www.fatcow.com/), [Vadym Gulyi](http://www.vady.kiev.ua/) and [Alisa Bagrii](http://www.everycloudtech.com/)
* Spanish - Juan, and Marvin Ortega
* Ukrainian - Alisa Bagrii from [Everycloudtech](http://www.everycloudtech.com/)
* Welsh - Emyr James


== External services ==

Sermon Browser connects to these services only when you use the features that need them.

= ESV API (Crossway) =
If a template includes `[esvtext]` and you enter an ESV API key on the Options page, each sermon page fetches the ESV text of the sermon's Bible passages from api.esv.org when it is viewed. The request sends the passage reference and your API key. [Terms (on the API's home page)](https://api.esv.org/), [privacy policy](https://www.crossway.org/policy/).

= NET Bible Web Service (Bible.org) =
If a template includes `[nettext]`, each sermon page fetches the NET Bible text of the sermon's passages from labs.bible.org when it is viewed. The request sends the passage reference. [API documentation](https://labs.bible.org/api_web_service); use of the API must comply with the [NET Bible copyright](https://bible.org/copyright#cpyrt).

= Files and links you add =
When you attach a URL to a sermon or import a file from a URL, the site requests that URL to check it, to copy the file (if you choose to) and to read its ID3 tags. The podcast feed asks for the size of each attached URL, and the download link for an attached URL fetches it to send to the visitor. These requests go only to the addresses you entered. Embedded media (such as Vimeo or YouTube players) is loaded by visitors' browsers from the service you embedded.

== Installation ==

= Installing =
* Until Sermon Browser is back on WordPress.org, download `sermon-browser.zip` from the [latest release on GitHub](https://github.com/markbarnes/sermon-browser/releases/latest). In your WordPress admin, go to Plugins, Add New, Upload Plugin, and choose the zip. (Don't use GitHub's "Download ZIP" button: it unpacks to a folder with the wrong name, and the plugin's folder must be called `sermon-browser`.)
* Once it is back on WordPress.org, go to Plugins, Add New, search for "sermon browser", and click Install Now.

= After you have installed the plugin: =
1. Activate the plugin from the Plugins page. On a multisite network, activate it on each site that needs it, or network-activate it.
2. Create a page with the text `[sermons]`. The plugin will display your sermons on this page.
3. Add your preachers, series and services, then your sermons, under Sermons in the admin menu. The Help page there explains each step.
4. You can also display sermons (filtered according to your criteria) on additional pages or posts by using **shortcodes**. See Customisation for more details.

== Frequently Asked Questions ==

= I've activated the plugin, and entered in a few sermons, but they are not showing up to my website users. Where are they? =
SermonBrowser only displays your sermons where you choose. You need to create the page/post where you want the sermons to appear (or edit an existing one), and add [sermons] to the page/post. You can also add some explantory text if you wish. If you do so, the text will appear on all your sermons pages. If you want your text to only appear on the list of sermons, not on individual sermon pages, you need to edit the SermonBrowser templates (see customisation).

= What does the error message "Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777." mean? =
SermonBrowser creates its upload folder (usually `wp-content/uploads/sermons/`) and needs to be able to write to it. If it can't, give the web server permission to write to that folder, as it can to `wp-content/uploads`, or ask your host to. Don't make the folder writeable by everyone (777), whatever the message says.

= SermonBrowser spends a long time attempting to upload files, but the file is never uploaded. What's happening? =
The most likely cause is that you're reaching either the maximum file size that can be uploaded, or the maximum time a PHP script can run for. The Options page warns if either is low. You may be able to raise them in php.ini or your host's control panel, but if you're on shared hosting, your host may have set limits you cannot change. If that's the case, upload your files to the upload folder by FTP, and the Files page will find them. FTP is generally a better option than your browser anyway, particularly if you have several files to upload. These settings should be adequate:

`file_uploads = On
upload_max_filesize = 15M
post_max_size = 15M
max_execution_time = 600
max_input_time = 600
memory_limit = 48M`

= Why are my MP3 files are appearing as an icon, rather than as a player, as I've seen on other SermonBrowser sites? =
SermonBrowser shows each MP3 with the MP3 shortcode on the Options page. The default, `[audio mp3="%SERMONURL%"]`, uses WordPress's built-in player. If you see an icon, check that the shortcode includes `%SERMONURL%`, and that the plugin providing it (if you use another player) is active. The admin pages warn about either problem.

= How do I change the Bible version from the ESV? =
Sermon Browser can show the ESV (which needs a free API key, entered on the Options page) or the NET Bible. To switch to the NET Bible, go to Templates, and edit the single template: replace `[esvtext]` with `[nettext]`. Thanks go to Crossway for providing access to the ESV, and bible.org for the NET Bible.

The other versions Sermon Browser used to offer (KJV, ASV, YLT, WEB, AKJV, HNV, Reina Valera, Cornilescu and Synodal) came from a service that no longer exists. Their template tags now show nothing.

To show a version Sermon Browser doesn't offer, use another Bible plugin that finds references on the page: in the single template, replace `[esvtext]` with `[biblepassage]`, which prints the sermon's Bible references as plain text for such plugins to find.

= How do I get recent sermons to display in my sidebar or elsewhere in my theme? =
SermonBrowser comes with several widgets you can add to your sidebars - just go to Appearance and choose Widgets.

If you want to add sermons elsewhere on your site, and you are comfortable in editing template files, add the following code: `<?php if (function_exists('sb_display_sermons')) sb_display_sermons(array('display_preacher' => 1, 'display_passage' => 1, 'display_date' => 1, 'preacher' => 0, 'service' => 0, 'series' => 0, 'limit' => 5, 'url_only' => 0)) ?>`. Each of the values in that line can be changed or omitted (if they are omitted, the default values are used). For example, you could just use: `<?php if (function_exists('sb_display_sermons')) sb_display_sermons(array('preacher' => 12)) ?>`. The various array keys are used to specify the following:

* display_preacher, display_passage and display_date affect what is displayed (0 is off, 1 is on).
* preacher, service and series allow you to limit the output to a particular preacher, service or series. Simply change the number of the ID of the preacher/services/series you want to display. You can get the ID from the Preachers page, or the Series & Services page. 0 shows all preachers/services/series.
* limit is the maximum number of sermons you want displayed.
* url_only means that only the URL of a sermon is returned. It's useful if you want to create your own link (e.g. click here for Bob's latest sermon). url_only means the display_ values are ignored, and limit is set to 1.

= My host only allows me a certain amount of disk space, and I have so many sermons uploaded, I've run out of space! What can I do? =
You could, of course, change your host to someone a little more generous! You should also make sure you encode your sermons at a medium to high compression. Usually, 22.05kHz, 48kbps mono is more than adequate (you could probably go down to 32kbps for even higher compression). 48kbps means every minute of recording takes up 360kb of disk space, so a thirty minute sermon will be just over 10Mb. At this setting, 5Gb would be enough for over 450 sermons.

If you can't change your host, you can still use SermonBrowser. You'll just have to upload your sermon files to another site. You can then use the other site's embed code, or just link to the MP3 file if they allow you (when you add your sermon to SermonBrowser, select "Enter an URL" and paste it in).

= How do I upload videos to SermonBrowser? =
SermonBrowser doesn't host videos. Upload them to a video site such as [YouTube](https://www.youtube.com/) or [Vimeo](https://vimeo.com/), and copy the embed code it gives you. Then edit your sermon, choose "Enter embed or shortcode", and paste it in. A video plugin's shortcode works too.

= Can I turn off the "Powered by Sermonbrowser" link? =
The link is only shown if a template includes [creditlink]. New installations don't include it; templates saved by older versions may, and you can remove it on the Templates page. If you'd like to help other churches find SermonBrowser, add [creditlink] to your templates.

= What is the difference between the public and private podcast feeds? =
In SermonBrowser options, you are able to change the address of the public podcast feed. This is the feed that is shown on your sermons page, and is usually the same as your private feed (i.e. you won't need to change it). However, if you publish your podcast through another service, which reads your private feed, set the public feed to that service's address. Otherwise, just make sure your public and private feeds are the same.

= On the sermons page, what is the difference between subscribing to the podcast for "All sermons", and subscribing to a podcast for "Filtered sermons"? =
The link under Subscribe to Podcast for "All sermons" gives a podcast of all sermons that you add to your site through SermonBrowser. But it may be that some people may just want to subscribe to a feed for certain speakers, or for a certain service. If they wish to do this, they should set the search filters and perform their search, then click on the subscribe link for "Filtered sermons." This will give them a podcast according to the filter they selected. You could also copy this link, and display it elsewhere on the site - for example to provide separate feeds for morning and evening services.

= Can I change the default sort order of the sermons? =
Yes. Use the **shortcode** `[sermons dir=asc]` instead of just `[sermons]`.

= Why do I get a page not found error when I click on my podcast feed? =
You've probably changed the address of your public feed to an incorrect value. Try changing it back to the same value as your private feed in Sermon Options.

= Can I change the way sermons are displayed? =
Yes, definitely, although you need to know a little HTML and/or CSS. SermonBrowser has a powerful templating function, so you can exclude certain parts of the output (e.g. if you don't want the links to other sermons preached on the same day to be displayed). The **Customisation** section has much more information.

= The search form is too big/too small for my layout. How do I make it narrower/wider? =
The search form is set to roughly 500 pixels, which should be about right for most WordPress templates. To change it, look for a line in the CSS stylesheet that begins `table.sermonbrowser td.field input`, and change the width specified after it. To make the form narrower, reduce the width. To make it bigger, increase the width. You'll also need to change the width of the date fields on the line below, which should be 20 pixels smaller.

Alternatively, you could try using the one-click style filter, which is inherently responsive.  The one-click style filter is selected on the Sermons, Options admin screen.

= Why is sometimes the Bible text missing? =
This usually happens for one of three reasons: (1) The Bible texts are provided by external websites, and sometimes they can go down. If you can't see Genesis 1 then the problem is with those websites. They're rarely down for long. (2) If you specify an invalid bible passage (e.g. Romans 22). If this is the case your sermon page will display ERROR: No results were found for your search. (3) Your site must be able to fetch pages from other websites. Some hosts block this; if yours does, ask them to allow requests to api.esv.org and labs.bible.org.

= Why is the ESV text missing? =
The ESV text needs an API key from api.esv.org, entered on the Options page. Without one, the `[esvtext]` tag prints nothing. The ESV API allows 5,000 lookups a day for each key, and SermonBrowser looks up the passage each time a sermon page with `[esvtext]` is viewed, so a busy site may run out. Once the limit is reached, sermon pages show no ESV text.

= How can I change the file icons that Sermon Browser uses, or add new icons? =
You'll need to edit the `filetypes.php` file that comes with Sermon Browser. The icon is chosen on the basis of the file extension (or in the case of URLs the file extension then the site address). If you do create new icons for other file types, consider offering them [on GitHub](https://github.com/markbarnes/sermon-browser/issues) so they can be included in future versions of the plugin.

= Where is the best place to go for help, ask questions, or discuss a potential bug? =
The Help page in the plugin's admin menu explains how to get started, the shortcode and every template tag, and each admin page has a help tab. To ask a question, report a bug or suggest a feature, [open an issue on GitHub](https://github.com/markbarnes/sermon-browser/issues). Include a link to your site, and your WordPress and PHP versions.

== Screenshots ==

1. **Displaying sermons on your website:** This first screen shot shows how Sermon Browser looks on your site. You can see one of the widgets in the left-hand sidebar, together with the main sermons page showing the one-click filter/search. Each of the sermons has an MP3 files attached, this can be played without leaving the site.
2. **The single sermon page (with Bible text):** This shows the detail for one sermon. You can see additional links to other sermons preached around that time, plus the complete ESV text of the passage being preached on, and a photograph of the preacher.
3. **Editing a sermon:** You can see a wide variety of information can be entered, including the bible passage being preached on, and any files linked to the sermon. Any number of Bible passages can be included, and any number of files (e.g. you could attach an mp3 file for the audio recording, a powerpoint file, and a PDF of the sermon text).
4. **The options page:** You can customise SermonBrowser through this option page. At the bottom of the screen are the settings that allow you to customise how MP3 ID3 tags will be imported.
5. **Using the template facility:** The powerful templating facility means that with a little knowledge of HTML, CSS, and the Sermon Browser template tags, you can easily adapt Sermon Browser's output to suit your own requirements. SermonBrowser produces valid XHTML code, using semantically correct markup and is fully standards compliant.

== Customisation ==

Sermon Browser works out of the box, but if you wish, you can customise it to fit in with your own theme, and to display or hide whatever information you choose. If you want to create an extra page on your site that just shows a few sermons (for example, just the sermons preached at a recent conference), use **shortcodes**. If you want to customise how Sermon Browser appears throughout your site, use **template tags** (scroll down for more info), or the built-in CSS editor.

### Shortcodes ###
Shortcodes allow you to put individual sermons or lists of sermons on any page or post of your website. A simple shortcode looks like this: `[sermons id=52]`, though you can combine parameters like this: `[sermons filter=none preacher=3 series=7]`. The list below gives examples of shortcode uses. A pipe character `|` means 'or'. So `[sermons id=52|latest]` means you would either write `[sermons id=52]`, or `[sermons id=latest]`.

#### [sermons id=52|latest] ####
Displays a single sermon page corresponding to the ID of the sermon (you can see a list of sermon IDs by looking on the Sermons page in admin). You can also use the special value of `latest` which displays the most recent sermon.

#### [sermons filter=dropdown|oneclick|none] ####
Specifies which filter to display with a sermon list.

#### [sermons filterhide=show|hide] ####
Specifies whether the filter should be shown or hidden by default.

#### [sermons preacher=6] ####
Displays a list of sermons preached by one preacher (you can see a list of preacher IDs by looking on the Preachers page in admin).

#### [sermons series=11] ####
Displays a list of sermons in particular series (you can see a list of series IDs by looking on the Series & Services page in admin).

#### [sermons service=2] ####
Displays a list of sermons preached at a particular service (you can see a list of service IDs by looking on the Series & Services page in admin).

#### [sermons book="1 John"] ####
Displays a list of sermons on a particular Bible book. The book name should be written out in full, and if it includes spaces, should be surrounded by quotes.

#### [sermons tag=hope] ####
Displays a list of sermons matching a particular tag.

#### [sermons limit=5] ####
Sets the number of sermons on each page, instead of the number on the Options page.

#### [sermons dir=asc|desc] ####
Sets the sort order to ascending or descending.

### Template Tags ###
If you want to change the output of Sermon Browser, you'll need to edit the templates. You'll need to understand the basics of HTML and CSS, and to know the special SermonBrowser template tags. There are two templates, one (called the results page) is used to produce the search results on the main sermons page. The other template (called the sermon page) is used to produce the page for single sermon. Most tags can be used in both templates, but some are specific.
#### Results Page Only ####
* **[filters_form]** - The search form which allows filtering by preacher, series, date, etc.
* **[sermons_count]** - The number of sermons which match the current search criteria.
* **[sermons_loop][/sermons_loop]** - These two tags should be placed around the output for one sermon. (That is all of the tags that return data about sermons should come between these two tags.)
* **[first_passage]** - The main bible passage for this sermon
* **[previous_page]** - Displays the link to the previous page of search results (if needed)
* **[next_page]** - Displays the link to the next page of search results (if needed)
* **[podcast]** - Link to the podcast of all sermons
* **[podcast_for_search]** - Link to the podcast of sermons that match the current search
* **[itunes_podcast]** - iTunes (itpc://) link to the podcast of all sermons
* **[itunes_podcast_for_search]** - iTunes (itpc://) link to the podcast of sermons that match the current search
* **[podcasticon]** - Displays the icon used for the main podcast
* **[podcasticon_for_search]** - Displays the icon used for the custom podcast
* **[tag_cloud]** - Displays a tag cloud
* **[most_popular]** - The most popular sermons, series and preachers, as in the popular sermons widget

#### Both results page and sermon page ####
* **[sermon_title]** - The title of the sermon
* **[preacher_link]** - The name of the preacher (hyperlinked to their sermons)
* **[series_link]** - The name of the series (hyperlinked to search results)
* **[service_link]** - The name of the service (hyperlinked to search results)
* **[date]** - The date of the sermon
* **[files_loop][/files_loop]** - These two tags should be placed around the [file] tag if you want to display all the files linked with to sermon. They are not needed if you only want to display the first file.
* **[file]** - Displays the files and external URLs
* **[file_with_download]** - The same as [file], but includes a download link after the media player for MP3 files
* **[embed_loop][/embed_loop]** - These two tags should be placed around the tag if you want to display all the embedded objects linked to this sermon. They are not needed if you only want to display the first embedded object.
* **[embed]** - Displays an embedded object (e.g. video)
* **[creditlink]** - displays a "Powered by Sermon Browser" link to the plugin's home page.
* **[editlink]** - Displays a link to edit the current sermon if you are logged in as an admin

#### Sermon page only ####
* **[sermon_description]** - The description of the sermon.
* **[preacher_description]** - The description of the preacher.
* **[preacher_image]** - The photo of the preacher.
* **[passages_loop][/passages_loop]** - These two tags should be placed around the [passage] tag if you want to display all the passages linked with to sermon.
* **[passage]** - Displays the reference of the bible passage with the book name hyperlinked to search results.
* **[next_sermon]** - Displays a link to the next sermon preached (excluding ones preached on the same day)
* **[prev_sermon]** - Displays a link to the previous sermon preached
* **[sameday_sermon]** - Displays a link to other sermons preached on that day
* **[tags]** - Displays the tags for that sermons
* **[esvtext]** - Displays the full text of the ESV Bible for all passages linked to that sermon (needs an API key).
* **[nettext]** - Displays the full text of the NET Bible for all passages linked to that sermon.
* **[biblepassage]** - Displays the reference of the bible passages for that sermon. Useful for utilising other bible plugins (see FAQ).

The tags for other Bible versions (`[kjvtext]`, `[asvtext]`, `[ylttext]`, `[webtext]`, `[akjvtext]`, `[hnvtext]`, `[lbrvtext]`, `[cornilescutext]` and `[synodaltext]`) print nothing, because the service that provided them no longer exists. The help tab on the Templates page lists every tag.



== Upgrade Notice ==

= 0.48.0 =
Reads sermons through a new storage layer, the first step towards storing them as WordPress posts. Fixes a fatal error on some old sermons' pages; a few orders and filters change slightly (see the changelog).

= 0.47.1 =
Sorting by preacher and passage works again, the admin pages are hardened, and the help is built in. The "Powered by" link now points to GitHub.

= 0.47.0 =
Reorganises the plugin's code, with no change for visitors except two broken sort options removed. Themes calling the plugin's internal functions should check the changelog.

= 0.46.1 =
Fixes NET Bible text and searching for titles with apostrophes, and removes Bible versions whose service no longer exists.

= 0.46.0 =
Security release: fixes CVE-2022-0499 and other serious vulnerabilities. Update as soon as possible.

== Changelog ==

= Unreleased =

* **Change:** The search results and sermon page templates are read when a page is shown, instead of being turned into PHP when saved and run with `eval()`. They print the same as before.
* **Change:** A template tag where it can't work (outside its loop, after its loop has ended, or in a loop that is never closed) is left out, instead of printing PHP warnings, the loop's last value or stopping the page with an error. Those who can edit the templates see why, with a link to a new section of the Help page, and the Templates page lists them.
* **Bug fix:** `[file]`, `[file_with_download]` and `[embed]` outside their loops show the sermon's first file or embed, as the template tag help has always said; they printed a broken link, or the last one shown.
* **Bug fix:** The sermon list printed PHP deprecation notices for sermons without a series.
* **Bug fix:** A backslash in a sermon's title or tags, or in a preacher's, series' or service's name, was removed from sermon lists, sermon pages and the recent sermons widget.
* **Bug fix:** `sb_display_sermons()` with `url_only` printed PHP warnings when no sermon matched.
* **Bug fix:** Saving the templates turned an attribute in single quotes, such as `data-x='y'`, into `data-x="&apos;y&apos;"`.

= 0.48.0 (13 October 2026) =
Sermons are read through one storage layer, the first step towards storing them as WordPress posts.

* **Bug fix:** The page of a sermon whose passages were saved empty by an old version (stored as `null`) stopped with a fatal error on PHP 8.
* **Bug fix:** `[sermons id="latest"]` printed PHP warnings when no sermon matched its filters.
* **Change:** Sermon lists sort only by date, title, preacher and passage, the orders the filters offer; links that ask for another column (such as `sortby=s.name`) sort by date.
* **Change:** The tag cloud lists tags used equally often in alphabetical order, and the most popular widget lists sermons downloaded equally often newest first, and series and preachers that rank equally by their latest sermon; these followed the database's order before.
* **Change:** Filtering by date leaves out sermons whose date is unknown, and the one-click filter's dates no longer list them under 1970.
* **Development:** Sermons are read through a repository (`SermonBrowser\Sermon_Repository`) that returns `SermonBrowser\Sermon` objects, instead of queries spread through the plugin, with PHPUnit contract tests that any storage must pass.

= 0.47.1 (13 October 2026) =
Restores sorting by preacher and passage, finishes escaping the admin pages, and moves the plugin's help from sermonbrowser.com into the plugin.

* **Bug fix:** The drop-down filter can sort by preacher and by passage again, which had sorted by date since 0.45.12. Passages sort in Bible order of each sermon's first passage, with sermons that have none at the end; a preacher's sermons are listed newest first. Old links that sort by `p.name` or `b.id` still work.
* **Bug fix:** After uploading or importing a file on the Files page with import options set, the Add Sermon page opened without the file's details, because the link to it was escaped for HTML inside a script.
* **Bug fix:** Series, preacher and service names with HTML entities (such as an en dash saved as `&#8211;`) showed the entity in the admin pages' drop-down lists.
* **Bug fix:** The Options page didn't show its error when the upload folder doesn't exist.
* **Bug fix:** Some of the dashboard's sermon statistics weren't translated.
* **Change:** The plugin's home is now [on GitHub](https://github.com/markbarnes/sermon-browser), until it is back on WordPress.org. The sermonbrowser.com site, its forum and its tutorials are no longer maintained, so the plugin no longer links to them: the "Powered by" link (`[creditlink]`), the podcast feed's generator and the plugin's home page point to GitHub, and the Help page and help tabs now hold the help themselves, including a description of every template tag. Report problems on GitHub.
* **Change:** Removed the global constants `IS_MU` (use WordPress's `is_multisite()`), `SB_PLUGIN_DIR` and `SB_WP_CONTENT_DIR`, and support for installing the plugin in `mu-plugins`, which didn't work: its URLs were wrong.
* **Documentation:** The FAQ and installation instructions are brought up to date, and `[sermon_description]` and `[most_popular]` are documented. The changelog before 0.46.0 is in CHANGELOG.md on GitHub.
* **Hardening:** The admin pages escape their output where it is printed, use prepared statements for every query, and sanitise request values where they are read, as the rest of the plugin has since 0.46.1. Plugin Check now reports nothing in the plugin except the templates' `eval()`.

= 0.47.0 (13 October 2026) =
The plugin's code is reorganised, to prepare for moving sermons to a custom post type. Output is unchanged except where noted below.

* **Change:** The code is split into classes (namespace `SermonBrowser`, in `sb-includes/classes`) and files by purpose: `functions.php`, `sermons.php` and `template-tags.php` (which was `frontend.php`). `podcast.php` and `style.php` are gone. `sb_display_sermons()` and the functions templates use are unchanged, but themes or plugins calling other internal functions (such as `sb_shortcode()`, `sb_hijack()`, `sb_add_headers()` or the widget functions) need updating.
* **Change:** Removed functions nothing called: `display_sermons()` (deprecated since 0.41), `sb_first_mp3()`, `sb_get_bible_books()`, `sb_display_front_end()` and `sb_default_excerpt_template()`.
* **Change:** The drop-down filter no longer offers sorting by preacher or passage. Neither has worked since 0.45.12; both sorted by date.
* **Bug fix:** The default stylesheet's podcast box background pointed to the wrong folder. Saved stylesheets are unchanged; resetting to the default fixes it.
* **Documentation:** The FAQ on ESV text describes the API key on the Options page.

= 0.46.1 (13 October 2026) =
Preparation for reopening on WordPress.org, from the results of its Plugin Check.

* **Security:** With SAVEQUERIES on, every page printed every database query (with its values) in an HTML comment that visitors could see. That output is gone.
* **Bug fix:** NET Bible text (`[nettext]`) showed only the heading, because bible.org's old endpoint stopped working. It uses the current one.
* **Bug fix:** Searching the sermon list for a title with an apostrophe (Paul's) found nothing.
* **Bug fix:** Editing a page with the `[sermons]` shortcode in the block editor caused a fatal error.
* **Bug fix:** The bundled translations weren't loaded on multisite networks.
* **Change:** The KJV, ASV, YLT, WEB, AKJV, HNV, Reina Valera, Cornilescu and Synodal texts came from a service that no longer exists, so they already showed nothing. Their template tags now print nothing without trying, and `[esvtext]` without an API key shows nothing instead of falling back to the KJV.
* **Change:** New installations no longer put a "Powered by Sermon Browser" link in the templates. Existing templates are unchanged.
* **Change:** Removed an admin page that loaded remote content, and the notices shown at random after saving a sermon.
* **Change:** The plugin no longer changes PHP settings (memory limit, time limit and upload sizes) on every request.
* **Change:** Updates from WordPress.org are allowed again (the Update URI header is gone), ready for the plugin to be reopened there.
* **Hardening:** Output on the front end, in the feed and in AJAX responses is escaped where it is printed, every query uses prepared statements, request values are sanitised where they are read, and uploads, deleting and renaming files use WordPress's functions. The readme lists the external services the plugin uses.

= 0.46.0 (12 October 2026) =
Security release. The plugin was closed on WordPress.org in February 2022 for CVE-2022-0499; this version fixes that and the other problems found in a full review. Update from WordPress.org is disabled (Update URI), so install it by uploading a zip.

* **Security:** AJAX requests from the admin pages had no authentication, so anyone could add, rename or delete preachers, series and services, and rename or delete uploaded files. They now need a logged-in user, a nonce and the right capability.
* **Security (CVE-2022-0499):** Uploads (Files page, URL import, Add Sermon, preacher images) had no nonce and no file type check, so a forged request could upload and run PHP. Uploads now need a nonce and only accept file types WordPress allows. The upload folder gets an .htaccess that refuses scripts, and must be inside the WordPress folder.
* **Security:** ?download&url= and ?show&url= worked as an open proxy and open redirect for any URL. They now only serve URLs attached to a sermon.
* **Hardening:** Queries that built SQL from request data or MP3 ID3 tags now use prepared statements. These weren't exploitable in 0.45.22, because the values were slashed, cast to integers or HTML-encoded first, but they relied on that.
* **Security:** Output is escaped throughout (front end, widgets, podcast feed, admin), templates can no longer contain PHP, embed code can no longer use iframe srcdoc, and Bible text from remote APIs is filtered.
* **Bug fix:** Works on PHP 8: the sermon list was always empty, and saving a sermon without a Bible passage or showing the popular sermons widget caused fatal errors.
* **Bug fix:** No PHP errors, warnings, notices or deprecations on PHP 7.4 to 8.5 (tested on each), including for sermons without a Bible passage, missing files and new widgets. Translations are no longer loaded before init, which WordPress 6.7 and later warn about.
* **Bug fix:** The popular sermons widget showed an empty entry in each list when a file wasn't attached to a sermon.

= Earlier versions =
The changes in 0.45.22 and earlier are in [CHANGELOG.md](https://github.com/markbarnes/sermon-browser/blob/master/CHANGELOG.md) on GitHub.
