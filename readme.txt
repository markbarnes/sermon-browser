=== Sermon Browser ===
Contributors: mark8barnes
Donate link: http://www.sermonbrowser.com/donate/
Tags: sermons, podcast, mp3, church, bible, audio, widget, embed, video, esv, wpmu, preach, iTunes, preacher, listen
Requires at least: 3.6
Tested up to: 4.9.8
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl.html

Upload sermons to your website, where they can be searched, listened to, and downloaded. Easy to use with comprehensive help and tutorials.



== Description ==

The Sermon Browser Wordpress Plugin allows churches to simply upload sermons to their website, where they can be searched, listened to, and downloaded. It is easy to use with comprehensive help and tutorials, and is used on hundreds of church websites. You can view working demos at [Bethel Evangelical Church](http://www.bethel-clydach.co.uk/sermons/), or the [Evangelical Movement of Wales](http://www.emw.org.uk/sermons/). Features include:

1. Store thousands of sermons, and **search** them by topic, preacher, bible passage or date.
2. Full **podcasting** capabilities, including custom podcasts for individual users.
3. Sermons uploaded in mp3 format can be **played directly** on your website using the WordPress 3.6 built-in player or your choice of WordPress MP3 plugins.
4. Three optional **sidebar widgets** can display sermons on all of your posts or pages.
5. **Embed videos** and other flash files from sites such as [YouTube](http://www.youtube.com/) or [Vimeo](http://www.vimeo.com/), using either HTML code provided by those sites, or shortcode providing by a WordPress plugin.
6. **Other file types** can also be uploaded, including PDF, Powerpoint, Word, text and RTF. Multiple files can be attached to single sermons.
7. The **full Bible text** of the passage being preached on can be included on each sermon page (eight English-language versions including ESV and NET, plus Spanish, Russian and Romanian).
8. Files can be uploaded to your own site **through the browser or via FTP**. Alternatively you can use free audio hosting sites.
9. Details about each sermon can be **added automatically from the MP3's ID3 tags**.
10. Powerful **templating function** allows complete customisation to complement the look of your site.
11. Simple statistics show how often each sermon has been listened to.
12. Support for multisite.
13. Extensive **help** and [tutorial screencasts](http://www.sermonbrowser.com/tutorials/).
14. Active [community support fourm](http://www.sermonbrowser.com/forum/).
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


== Installation ==

= Install the plugin in one of two ways: =
* In your WordPress admin panel, go to Plugins, Add New.  Search for "sermon browser".  Find "Sermon Browser" by Mark Barnes, and click "Install Now".

	or

* [Download the plugin](https://wordpress.org/plugins/sermon-browser/), unzip it, and upload it to your website, placing the sermon-browser folder in your wp-content/plugins folder.

= After you have installed the plugin: =
1. Activate the plugin from the plugins tab of your Wordpress admin.
2. You may have to change the permissions the upload folder (by default `wp-content/uploads/sermons`). See the FAQ for more details.
3. Create a Wordpress page with the text `[sermons]`. The plugin will display your sermons on this page.
4. You can also display sermons (filtered according to your criteria) on additional pages or posts by using **shortcodes**. See the Customisation page for more details.

= Installation in Wordpress MU =
1. Download the plugin, and unzip it.
2. Place the contents of the sermon-browser folder in your wp-content/mu-plugins folder and upload it to your website.
3. The plugin will be automatically activated and available for each user.

== Frequently Asked Questions ==

= I've activated the plugin, and entered in a few sermons, but they are not showing up to my website users. Where are they? =
SermonBrowser only displays your sermons where you choose. You need to create the page/post where you want the sermons to appear (or edit an existing one), and add [sermons] to the page/post. You can also add some explantory text if you wish. If you do so, the text will appear on all your sermons pages. If you want your text to only appear on the list of sermons, not on individual sermon pages, you need to edit the SermonBrowser templates (see customisation).

= What does the error message "Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777." mean? =
SermonBrowser tries to set the correct permissions on your folders for you, but sometimes restrictions mean that you have to do it yourself. You need to make sure that SermonBrowser is able to write to your sermons upload folder (usually `/wp-content/uploads/sermons/`). [This tutorial](http://samdevol.com/wordpress-troubleshooting-permissions-chmod-and-paths-oh-my/) explains how to use the free FileZilla FTP software to do this.

= SermonBrowser spends a long time attempting to upload files, but the file is never uploaded. What's happening? =
The most likely cause is that you're reaching either the maximum filesize that can be uploaded, or the maximum time a PHP script can run for. [Editing your php.ini](http://www.techrepublic.com/article/a-tour-of-the-phpini-configuration-file-part-2/5272345) may help overcome these problems - but if you're on shared hosting, it's possible your host has set maximum limits you cannot change. If that's the case, you should upload your files via FTP. This is generally a better option than using your browser, particularly if you have several files to upload. If you do edit your php.ini file, these settings should be adequate:

`file_uploads = On
upload_max_filesize = 15M
post_max_size = 15M
max_execution_time = 600
max_input_time = 600
memory_limit = 48M`

= Why are my MP3 files are appearing as an icon, rather than as a player, as I've seen on other SermonBrowser sites? =
If you are using a version of WordPress older than 3.6, you need to install and activate your favourite WordPress MP3 plugin. WordPress 3.6 has the MediaElement.js player built-in; if you are running an older version of WordPress, you can install the [Mediaelement.js plugin](https://wordpress.org/plugins/media-element-html5-video-and-audio-player/).  SermonBrowser also supports any WordPress MP3 player that allows you add the player by entering shortcodes in a post or page. To use a different media player plugin, change the MP3 shortcode setting on the Sermons, Options admin page.

= How do I change the Bible version from the ESV? =
Several Bible versions are supported by Sermon Browser. To switch to a different version, go to Options, and edit the single template. Replace `[esvtext]` with the appropriate template tag for the alternative version.(Template tags are listed on the `Customisation` page of this site). For example, to switch to the KJV, use the tag `[kjvtext]`. Thanks go to Crossway for providing access to the ESV, bible.org for the NET Bible. Other versions are supplied by SermonBrowser itself.

There are lots of other versions available in non-English languages.  [This forum post](http://www.sermonbrowser.com/forum/sermon-browser-support/german-bible-support/#p19464) describes what is available and how to add a new version to your Sermon Browser installation.

If you're desperate to use other versions not currently supported, you can manage it using other WordPress plugins (albeit with reduced functionality). The eBibleicious plugin allows for NASB, MSG, KJV, NKJV, ESV, HCSB, and NCV (use it in 'snippet' mode). However, there are three disadvantages. (1) To use it, you'll need to register for an API key (although it is free). (2) It uses Javascript so search engines won't see the Bible text, and nor will users with javascript turned off. (3) Most importantly, it only shows a maximum of four verses (the ESV shows up to 500 verses!).

You can also use the [RefTagger](http://www.reftagger.com) plugin, though this shows even few verses. Even worse (for our purposes) the bible passage only shows when you hover over a special link with your mouse. It does, however, provide an even longer list of translations. Please be aware that both RefTagger and eBibleicious will add bible text to bible references across your whole website, not just your sermons pages.

To use either of these alternatives, just download, install and activate them as you would for any other plugin. Check their settings (make sure you enter get an API key if you're using eBiblicious). You then need to make one change to your SermonBrowser options. In the Single Sermon form, look for `[esvtext]` and replace it with `[biblepassage]`. (By default it's right at the end of the code.)

= How do I get recent sermons to display in my sidebar or elsewhere in my theme? =
SermonBrowser comes with several widgets you can add to your sidebars - just go to Appearance and choose Widgets.

If you want to add sermons elsewhere on your site, and you are comfortable in editing template files, add the following code: `<?php if (function_exists('sb_display_sermons')) sb_display_sermons(array('display_preacher' => 1, 'display_passage' => 1, 'display_date' => 1, 'display_player' => 0, 'preacher' => 0, 'service' => 0, 'series' => 0, 'limit' => 5, 'url_only' => 0)) ?>`. Each of the values in that line can be changed or omitted (if they are omitted, the default values are used). For example, you could just use: `<?php if (function_exists('sb_display_sermons')) sb_display_sermons(array('display_player' => 1, 'preacher' => 12) ?>`. The various array keys are used to specify the following:

* display_preacher, display_passage, display_date and display_player affect what is displayed (0 is off, 1 is on).
* preacher, service and series allow you to limit the output to a particular preacher, service or series. Simply change the number of the ID of the preacher/services/series you want to display. You can get the ID from the Preachers page, or the Series & Services page. 0 shows all preachers/services/series.
* limit is the maximum number of sermons you want displayed.
* url_only means that only the URL of a sermon is returned. It's useful if you want to create your own link (e.g. click here for Bob's latest sermon). url_only means the display_ values are ignored, and limit is set to 1.

= My host only allows me a certain amount of disk space, and I have so many sermons uploaded, I've run out of space! What can I do? =
You could, of course, change your host to someone a little more generous! You should also make sure you encode your sermons at a medium to high compression. Usually, 22.05kHz, 48kbps mono is more than adequate (you could probably go down to 32kbps for even higher compression). 48kbps means every minute of recording takes up 360kb of disk space, so a thirty minute sermon will be just over 10Mb. At this setting, 5Gb would be enough for over 450 sermons.

If you can't change your host, you can still use SermonBrowser. You'll just have to upload your sermon files to another site - preferably a free one! You can then use the other site's embed code, or just link to the MP3 file if they allow you (when you add your sermon to SermonBrowser, select "Enter an URL" and paste it in).

= How do I upload videos to SermonBrowser? =
You can't - but you can upload videos to other sites, then embed them in your sermons. You can use any site that allows you to embed your video in other websites, including [YouTube](http://www.youtube.com), but we recommend [Vimeo](http://www.vimeo.com/) as the most suitable for sermons. That's because most video-sharing sites are designed for relatively short clips of 10 minutes or so, but Vimeo will accept videos of any length - and there are no quotas for the maximum size of a video, nor the number of videos you can store. Once your video is uploaded and available on Vimeo or YouTube, you can copy the embed code it gives you, edit your sermon, select "Enter embed code" and paste it in. If you are using a video plugin, you can even use that plugin's shortcode in the embed code.

= Can I turn off the "Powered by Sermonbrowser" link? =
The link is there so that people from other churches who listen to your sermons can find out about SermonBrowser themselves. But if you'd like to remove the link, just remove [creditlink] from the templates in SermonBrowser Options.

= What is the difference between the public and private podcast feeds? =
In SermonBrowser options, you are able to change the address of the public podcast feed. This is the feed that is shown on your sermons page, and is usually the same as your private feed (i.e. you won't need to change it). However, if you use a service such as FeedBurner, you can use your private feed to send data to feedburner, and change your public feed to your Feedburner address. If you do not use a service like Feedburner, just make sure your public and private feeds are the same.

= On the sermons page, what is the difference between subscribing to the podcast for "All sermons", and subscribing to a podcast for "Filtered sermons"? =
The link under Subscribe to Podcast for "All sermons" gives a podcast of all sermons that you add to your site through SermonBrowser. But it may be that some people may just want to subscribe to a feed for certain speakers, or for a certain service. If they wish to do this, they should set the search filters and perform their search, then click on the subscribe link for "Filtered sermons." This will give them a podcast according to the filter they selected. You could also copy this link, and display it elsewhere on the site - for example to provide separate feeds for morning and evening services.

= Can I change the default sort order of the sermons? =
Yes. Use the **shortcode** `[sermons dir=asc]` instead of just `[sermons]`.

= Why do I get a page not found error when I click on my podcast feed? =
You've probably changed the address of your public feed to an incorrect value. Try changing it back to the same value as your private feed in Sermon Options.

= Can I change the way sermons are displayed? =
Yes, definately, although you need to know a little HTML and/or CSS. SermonBrowser has a powerful templating function, so you can exclude certain parts of the output (e.g. if you don't want the links to other sermons preached on the same day to be displayed). The **Customisation** section has much more information.

= The search form is too big/too small for my layout. How do I make it narrower/wider? =
The search form is set to roughly 500 pixels, which should be about right for most WordPress templates. To change it, look for a line in the CSS stylesheet that begins `table.sermonbrowser td.field input`, and change the width specified after it. To make the form narrower, reduce the width. To make it bigger, increase the width. You'll also need to change the width of the date fields on the line below, which should be 20 pixels smaller.

Alternatively, you could try using the one-click style filter, which is inherently responsive.  The one-click style filter is selected on the Sermons, Options admin screen.

= Why is sometimes the Bible text missing? =
This usually happens for one of three reasons: (1) The Bible texts are provided by external websites, and sometimes they can go down. If you can't see Genesis 1 then the problem is with those websites. They're rarely down for long. (2) If you specify an invalid bible passage (e.g. Romans 22). If this is the case your sermon page will display ERROR: No results were found for your search. (3) If your webhost has disabled allow_url_fopen and cURL. Some cheaper webhosts have these essential features switched off. If they have, you won't be able to use this facility.

= Why does my sermon page say I have exceeded my quota for ESV lookups? =
The ESV website only allows 5,000 lookups per day from each IP address. That should be enough for most users of SermonBrowser. However, if you are using a shared host, there will be hundreds (perhaps thousands) of other websites on the same IP address as you. If any are also using the ESV API, they also get counted towards that total. If you are using less than 5,000 lookups per day (i.e. you are having less than 5,000 pageviews of your sermon pages), and you receive the error message you'll need to do two things in order to continue to display the text. (1) Sign up for an ESV API key. (2) Edit frontend.php (one of the SermonBrowser files). Look for the function `sb_add_esv_text` (at the time of writing it began on line 412), and replace ...`passageQuery?key=IP&passage=`... with ...`passageQuery?key=YOURAPIKEY&passage=`...

If you are having more than 5,000 page views per day, then this won't help. Instead, leave a message on the forum explaining your problem. SermonBrowser could probably be modified to provide a caching mechanism to reduce the likelihood of this error occurring, if there is demand.

= How can I change the file icons that Sermon Browser uses, or add new icons? =
You'll need to edit the `filetypes.php` file that comes with Sermon Browser. The icon is chosen on the basis of the file extension (or in the case of URLs the file extension then the site address). If you do create new icons for other filetypes, consider sending them to the author so they can be included in future versions of the plugin.

= Where is the best place to go for help, ask questions, or discuss a potential bug? =
Sermon Browser has an [active support forum](http://www.sermonbrowser.com/forum/sermon-browser-support/) on sermonbrowser.com.  If you post there looking for help on your website, don't forget to include a link to your site.

= Are there any more Frequently Asked Questions? =
Yes.  There are additonal [FAQs on the Sermon Browser support forum](http://www.sermonbrowser.com/forum/sermon-browser-support/troubleshooting-faq/).

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
Sets the maximum number of sermons to be displayed.

#### [sermons dir=asc|desc] ####
Sets the sort order to ascending or descending.

### Template Tags ###
If you want to change the output of Sermon Browser, you'll need to edit the templates. You'll need to understand the basics of HTML and CSS, and to know the special SermonBrowser template tags. There are two templates, one (called the results page) is used to produce the search results on the main sermons page. The other template (called the sermon page) is used to produce the page for single sermon. Most tags can be used in both templates, but some are specific.
#### Results Page Only ####
* **[filters_form]** - The search form which allows filtering by preacher, series, date, etc.
* **[sermons_count]** - The number of sermons which match the current search critera.
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

#### Both results page and sermon page ####
* **[sermon_title]** - The title of the sermon
* **[preacher_link]** - The name of the preacher (hyperlinked to his search results)
* **[series_link]** - The name of the series (hyperlinked to search results)
* **[service_link]** - The name of the service (hyperlinked to search results)
* **[date]** - The date of the sermon
* **[files_loop][/files_loop]** - These two tags should be placed around the [file] tag if you want to display all the files linked with to sermon. They are not needed if you only want to display the first file.
* **[file]** - Displays the files and external URLs
* **[file_with_download]** - The same as [file], but includes a download link after the media player for MP3 files
* **[embed_loop][/embed_loop]** - These two tags should be placed around the tag if you want to display all the embedded objects linked to this sermon. They are not needed if you only want to display the first embedded object.
* **[embed]** - Displays an embedded object (e.g. video)
* **[creditlink]** - displays a "Powered by Sermon Browser" link.
* **[editlink]** - Displays a link to edit the current sermon if you are logged in as an admin

#### Sermon page only ####
* **[preacher_description]** - The description of the preacher.
* **[preacher_image]** - The photo of the preacher.
* **[passages_loop][/passages_loop]** - These two tags should be placed around the [passage] tag if you want to display all the passages linked with to sermon.
* **[passage]** - Displays the reference of the bible passage with the book name hyperlinked to search results.
* **[next_sermon]** - Displays a link to the next sermon preached (excluding ones preached on the same day)
* **[prev_sermon]** - Displays a link to the previous sermon preached
* **[sameday_sermon]** - Displays a link to other sermons preached on that day
* **[tags]** - Displays the tags for that sermons
* **[esvtext]** - Displays the full text of the ESV Bible for all passages linked to that sermon.
* **[asvtext]** - Displays the full text of the ASV Bible for all passages linked to that sermon.
* **[kjvtext]** - Displays the full text of the KJV Bible for all passages linked to that sermon.
* **[ylttext]** - Displays the full text of the YLT Bible for all passages linked to that sermon.
* **[webtext]** - Displays the full text of the WEB Bible for all passages linked to that sermon.
* **[akjvtext]** - Displays the full text of the AKJV Bible for all passages linked to that sermon.
* **[hnvtext]** - Displays the full text of the HNV Bible for all passages linked to that sermon.
* **[lbrvtext]** - Displays the full text of the Reina Valera Bible (Spanish) for all passages linked to that sermon.
* **[cornilescutext]** - Displays the full text of the Cornilescu Bible (Romanian) for all passages linked to that sermon.
* **[synodaltext]** - Displays the full text of the Synodal 1876 Translation (Russian) for all passages linked to that sermon.
* **[biblepassage]** - Displays the reference of the bible passages for that sermon. Useful for utilising other bible plugins (see FAQ).



== Upgrade Notice ==

= 0.45.22 =
Fixed sermons not deleting, and podcasts not downloading on iOS.

= 0.45.21 =
Several security enhancements, and update to allow the the ESV Bible text to continue to be used.

= 0.45.20 =
Added nonces to enhance security protection.

= 0.45.19 =
Fixed bug that prevented iframe embeds from being saved.

= 0.45.18 =
Fixed bug that prevented shortcodes from working and adds Italian translation.

= 0.45.17 =
Minor, under-the-hood changes to translations.

= 0.45.16 =
Fixed potential XSS vulnerabilities.

= 0.45.15 =
Fixed bugs that prevented adding sermons from MP3 files.

= 0.45.14 =
Fixed bug introduced in 0.45.13 which prevented series being edited/saved.

= 0.45.13 =
Compatibility with PHP 5.2 to 5.6 and WordPress 3.6 to 4.3.

= 0.45.12 =
More bug fixes.

= 0.45.11 =
WordPress 3.6 compatibility fix.

= 0.45.10 =
More bug fixes and new Welsh language translation.

= 0.45.9 =
Six more bug fixes.

= 0.45.8 =
More bug fixes, collated and patched by [Ben Miller](http://www.sermonbrowser.com/forum/profile/bhengh/).

= 0.45.7 =
Three more bug fixes, collated and patched by [Ben Miller](http://www.sermonbrowser.com/forum/profile/bhengh/).

= 0.45.6 =
Three more bug fixes, collated and patched by [Ben Miller](http://www.sermonbrowser.com/forum/profile/bhengh/).

= 0.45.5 =
Many bug fixes, collated and patched by [Ben Miller](http://www.sermonbrowser.com/forum/profile/bhengh/) - thanks, Ben! See the support forum for details and recommendations: http://www.sermonbrowser.com/forum/sermon-browser-support/announcing-version-0-45-5/

= 0.45.4 =
Fixes broken iTunes feed on some installations.

= 0.45.3 =
Fixes further PHP4 incompatibility with default audio player.

= 0.45.2 =
Fixes bug when upgrading from very early versions of Sermon Browser.

= 0.45.1 =
Fixes PHP4 incompatibility, and bug when attaching URLs.

= 0.45 =
Adds compatibility with WordPress Multisite, and with many more MP3 players. Also fixes several minor bugs.

= 0.44.1 =
Fixes bug which breaks sites that have a page with [sermons xxxxx], but no page with just [sermons].

= 0.44 =
Adds WordPress 3.1 compatibility, fixes several bugs (including an important security fix), and adds support for the admin bar in WordPress 3.1

= 0.43.6 =
Important security fixes. All users should upgrade to this version.



== Changelog ==

= 0.45.22 (29 August 2018) =
* **Bug fix:** Sermons couldn't be deleted. Now they can.
* **Bug fix:** Sermons weren't downloading on Apple's Podcast app. Now they are.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1932341/sermon-browser/trunk)

= 0.45.21 (11 August 2018) =
* **Bug fix:** Several security improvements.
* **Bug fix:** Support sites that have renamed the wp-content folder
* **Enhancement:** Updated to v3 of the ESV Bible API (v2 was deprecated).
* **Enhancement:** Removed support for flash audio players.
* **Enhancement:** External https media files can now be downloaded
* See [changeset](https://plugins.trac.wordpress.org/changeset?new=1927443%40sermon-browser%2Ftrunk&old=1686060%40sermon-browser%2Ftrunk)

= 0.45.20 (27 June 2017) =
* **Bug fix:** Added nonces to enhance security protection.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1686060/sermon-browser/trunk)

= 0.45.19 (31 May 2016) =
* **Bug fix:** Fixed bug introduced in 0.45.16 that prevented iframe embeds from being saved.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1427807/sermon-browser/trunk)

= 0.45.18 (30 May 2016) =
* **Bug fix:** Fixed bug that prevented shortcodes from working.
* **Enhancement:** No need to surround embedded videos with the [embed] shortcode.
* **Enhancement:** Added Italian translation (thanks to Manoah Cammarano)
* See [changeset](https://plugins.trac.wordpress.org/changeset/1426889/sermon-browser/trunk)

= 0.45.17 (23 May 2016) =
* **Enhancement:** Minor, under-the-hood changes to translations.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1422176/sermon-browser/trunk)

= 0.45.16 (21 April 2016) =
* **Bug fix:** Fixed potential XSS vulnerabilities.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1413162/sermon-browser/trunk)

= 0.45.15 (10 November 2015) =
* **Bug fix:** Sermon duration is now correctly calculated.
* **Bug fix:** Fixed bug that prevented sermons being added from the 'Files' page.
* **Bug fix:** Remove empty rows from the list of files.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1283205/sermon-browser/trunk)

= 0.45.14 (6 November 2015) =
* **Bug fix:** Fixed bug introduced in 0.45.13 which prevented series being edited/saved.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1281420/sermon-browser/trunk)

= 0.45.13 (6 November 2015) =
* **Bug fix:** Compatibility with PHP 5.2 to 5.6 and WordPress 3.6 to 4.3.
* See [changeset](https://plugins.trac.wordpress.org/changeset/1281163/sermon-browser)

= 0.45.12 (16 September 2013) =
* **Bug fix:** Removed call to deprecated wp_timezone_supported function if WordPress version is 3.2 or later.
* **Bug fix:** In default style template, font-style should be "italic", not "italics". ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/italics-not-a-font-style-in-sb-style-css/)).
* **Bug fix:** Replaced deprecated functions wpdb::escape, attribute_escape, ereg_replace
* **Bug fix:** Download link now works for MP3 URLs with capital letter extensions. (Lowercase extensions recommended for best compatibility.) ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/download-link-not-working-for-mp3/)).
* **Bug fix:** Strengthen plugin against bad URL requests. ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/error_log-entry-for-20-aug-2013-re-sermon-browser/)).
* **Bug fix:** Fixed creation of "images" subfolder when moving sermon Upload folder to new location.
* **Bug fix:** Improved download link operation on Android. ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/downloading-mp3s-on-android-phones/)).
* See [changeset](http://plugins.trac.wordpress.org/changeset/773534/sermon-browser)

= 0.45.11 (7 August 2013) =
* **Bug fix:** Fixed Media Upload conflict with WordPress 3.6 related to getid3 library. ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/wordpress-3-6-any-issues/#p20501)).
* See [changeset](https://plugins.trac.wordpress.org/changeset/753109/sermon-browser)

= 0.45.10 (31 July 2013) =
* **Enhancement:** Welsh translation (cy) added.
* **Bug fix:** Sermon Tag Widget fixed. ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/sermon-browser-tags-widget-doesnt-display-tags-correctly/)).
* **Bug fix:** Bible book links fixed for translated installations. ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/language-support/page-3/#p20409)).
* **Bug fix:** Bible books in database were wrong for new installations on non-English sites.  Corrected new installations.
* See [changeset](https://plugins.trac.wordpress.org/changeset/748934/sermon-browser)

= 0.45.9 (8 July 2013) =
* **Bug fix:** Auto fill of Bible books on Sermon edit admin screen works better ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/adding-second-bible-passage-bug/)).
* **Bug fix:** Fix Bible passage link on function sb_display_sermons ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/relative-vs-absolute-url-on-home-page/)).
* **Bug fix:** Fix sermon title link on Recent Sermons widget.
* **Bug fix:** Loosen requirements for a valid header from MP3 at external URL ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/patch-for-invalid-url-problem-on-url-import/)).
* **Bug fix:** Fix "No Sermons Found" bug when sermon is missing Series info.
* **Bug fix:** Widget miniplayer didn't work with MP3 files attached via URL ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/mini-player-not-working-1/)).
* See [changeset](https://plugins.trac.wordpress.org/changeset/737860/sermon-browser)

= 0.45.8 (31 May 2013) =
* **Bug fix:** Allow MP3 external URLs beginning with "https://" ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/url-redirect-not-working/#p19959)).
* **Bug fix:** Sermon page not recognized for new installs ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/bug-fix-sermon-page-not-recognized-for-new-installs/)).
* **Enhancement:** Default settings now support MediaElement.js player, or built-in player with WordPress 3.6 ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/heads-up-about-wordpress-3-6/#p19977)).
* See [changeset](http://plugins.trac.wordpress.org/changeset/723323/)

= 0.45.7 (21 May 2013) =
* **Bug fix:** Fix for function sb_display_sermons, a function users can use to display sermons in their theme templates ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/problem-with-recent-sermons-url-link-on-the-front-page/)).
* **Bug fix:** Fix ability to delete series, services, and preachers ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/bug-fix-delete-series-services-and-preachers/)).
* **Bug fix:** Import ID3 tags now working from Create Sermon page ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/you-do-not-have-the-correct-permissions-to-create-sermons/#p3152)).
* See [changeset](http://plugins.trac.wordpress.org/changeset/716907/sermon-browser)

= 0.45.6 (10 May 2013) =
* **Bug fix:** Fixed fatal error on versions of WordPress earlier than 3.0.
* **Bug fix:** Always use absolute links when pretty permalinks are not being used ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/announcing-version-0-45-5/#p19841)).
* **Bug fix:** Bible book names not showing correctly on add sermon admin screen for translated sites ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/language-support/)).
* See [changeset](http://plugins.trac.wordpress.org/changeset/710876/sermon-browser)

= 0.45.5 (6 May 2013) =
* **Thanks:** Updated version patched by [Ben Miller](http://www.sermonbrowser.com/forum/profile/bhengh/).
* **Bug fix:** Fix style sheet call when WordPress is installed in a subdirectory (home_url is different than site_url) ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/sermon-browser-screen-layout-issues)).
* **Bug fix:** Help Sermon Browser choose the correct page for the links ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/all-links-broken/)).
* **Bug fix:** Next page link shows same sermons ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/next-page-shows-same-sermons/)).
* **Bug fix:** Preachers, series, services admin bug ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/adding-preachers-not-working-correctly/)).
* **Bug fix:** Changes to default templates ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/itunes-button-hidden-behind-text-on-the-sermons-page/)).
* **Bug fix:** Display text from Psalms in non-ESV translations ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/will-not-display-any-scripture-from-book-of-psalms/)).
* **Bug fix:** Use relative links instead of absolute links in some places, which allows a website to have more than one sermon page ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/next-page-links-to-different-service/)).
* **Bug fix:** Download link when using [file_with_download] template tag, not using old WP Audio Player plugin ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/file_with_download-not-showing-the-download-link/)).
* **Bug fix:** Fix for Popular Sermon widget in PHP 5.4 ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/warning-creating-default-object-from-empty-value-after-upgrade-to-php-5-4/)).
* **Bug fix:** Translation fixes ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/problem-with-romanian-bible-books-showing-in-english/), [link](http://www.sermonbrowser.com/forum/sermon-browser-support/translating-things-which-arent-on-the-pot-file/)).
* **Bug fix:** Prevent PHP warnings when ini_set function is disabled ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/warning-ini_set-has-been-disabled/)).
* **Enhancement:** New Hindi translation, and updated Romanian and Russian translations (thanks to [Chandel](http://outshinesolutions.com/), [Lucian Mihailescu](http://lucianwebservice.com/) and [Vadym Gulyi](http://www.vady.kiev.ua/)).
* See [changeset](http://plugins.trac.wordpress.org/changeset/708683/sermon-browser)

= 0.45.4 (26 May 2011) =
* **Bug fix:** Fixes broken iTunes feed on some installations.
* See [changeset](http://plugins.trac.wordpress.org/changeset?reponame=&new=389533%40sermon-browser%2Ftrunk&old=386544%40sermon-browser%2Ftrunk).

= 0.45.3 (19 May 2011) =
* **Bug fix:** Fixes further PHP4 incompatibility with default audio player - Fatal error: Call to undefined function: str_ireplace(). ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/fatal-error-when-viewing-a-sermon/)).
* See [changeset](http://plugins.trac.wordpress.org/changeset/386541/sermon-browser/trunk).

= 0.45.2 (15 May 2011) =
* **Bug fix:** Fixes bug when upgrading from very early versions of Sermon Browser ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/fatal-error-call-to-undefined-function-sb_delete_unused_tags-in-homepages35d195956195htdocslivingword-netdwp-contentpluginssermon-browsersb-includesupgrade-plivingwor/)).
* See [changeset](http://plugins.trac.wordpress.org/changeset/385616/sermon-browser/trunk).

= 0.45.1 (15 May 2011) =
* **Bug fix:** Syntax errors on PHP4, and URLs not attaching correctly ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/upgrade-error-line-816/)).
* See [changeset](http://plugins.trac.wordpress.org/changeset/385190/sermon-browser/trunk).

= 0.45 (13 May 2011) =
* **New feature:** You can now use many more MP3 players, not just WPAudio. Any MP3 WordPress plugin that supports shortcodes can be used. Go to Options and insert the shortcode for your preferred player.
* **Compatibility:** Now fully compatible with WordPress multisite.
* **Enhancement:** Prevent deletion of final preacher/series/service ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/no-sermons-found-on-single-sermon-page/)).
* **Enhancement:** You can now use WordPress shortcode in the 'embed' field on each sermon. For example, if you have a Vimeo plugin installed, you could attached a Vimeo video using the shortcode [vimeo clip_id="XXXXXXX" width="400" height="225"].
* **Enhancement:** You can now see how many sermons are assigned to each preacher, series and service in the admin pages.
* **Bug fix:** Apostrophes and double quotes are now supported in filenames ([link](http://www.4-14.org.uk/forum/sermon-browser-support/word-to-the-wise-no-apostrophes-in-filename)).
* See [changeset](http://plugins.trac.wordpress.org/changeset?reponame=&new=384459%40sermon-browser%2Ftrunk&old=382873%40sermon-browser%2Ftrunk).

= 0.44.1 (5 May 2011) =
* **Bug fix:** Fixes bug which breaks sites that have a page with [sermons xxxxx], but no page with just [sermons]. ([link](http://www.sermonbrowser.com/forum/sermon-browser-support/updated-site-and-now-sermons-page-gives-file-not-found/))
* **Enhancement:** Default CSS works much better with Wordpress 3.0+ default Twenty Ten theme (see [demo](http://www.sermonbrowser.com/demo/)). Existing users should reset the template to default to get it.
* See [changeset](http://plugins.trac.wordpress.org/changeset/382871/sermon-browser)

= 0.44 (30 April 2011) =
* **New feature:** Sermon Browser added to the new menu bar in Wordpress 3.1 and above.
* **New feature:** Added support for Brazilian Portuguese and German (thanks to [DJIO](http://www.djio.com.br/sermonbrowser-em-portugues-brasileiro-pt_br/) and Monika Gause).
* **Compatibility:** Now fully compatible with Wordpress 2.6 - 3.1. ([link](http://www.4-14.org.uk/forum/sermon-browser-support/cant-add-new-preacherseriesservice-in-add-sermon-page-w-wp-3-1/))
* **Enhancement:** [sermons] shortcode now supports the 'limit' and 'dir' parameters (thanks to [liggit](http://www.4-14.org.uk/forum/sermon-browser-support/patches-to-add-support-for-limit-and-dir-shortcode-attrs))
* **Bug fix:** More security fixes.
* **Bug fix:** Custom podcasts are now working again ([link](http://www.4-14.org.uk/forum/sermon-browser-support/custom-podcast-link-no-longer-working))
* **Bug fix:** Fixed SQL_BIG_SELECTS issue on some hosts which could result in blank podcasts and sermons pages ([link](http://www.4-14.org.uk/forum/sermon-browser-support/can-only-ad-finite-sermons-till-plugin-breaks))
* **Bug fix:** Tags are now displaying correctly on sermons page
* **Bug fix:** Slashes no longer appear in some saved text.
* **Bug fix:** Sermon widget now works for users who have changed their database prefix ([link](http://www.4-14.org.uk/forum/sermon-browser-support/finish-previous-bug-fix))
* **Bug fix:** Text on javascript pop-ups is now ready for translation
* **Bug fix:** Edit links on the main sermons page are no longer missing after the first page
* **Bug fix:** Missing slash meant preacher image was not displaying for some people
* **Bug fix:** Book counts now accurate even when more than one passage is applied to a sermon
* **Bug fix:** Mini-flash player now inherits the colour of both Audio Player v1, and v2
* **Bug fix:** Filenames with spaces are now encoded in an iTunes compatible way ([link](http://www.4-14.org.uk/forum/sermon-browser-support/patches-to-add-support-for-limit-and-dir-shortcode-attrs/#p1231))
* **Bug fix:** Sermon filter now always correctly displays which Bible book is being filtered on ([link](http://www.4-14.org.uk/forum/sermon-browser-support/important-info-about-the-future-of-sermon-browser/#p2849))
* See [changeset](http://plugins.trac.wordpress.org/changeset/379041/sermon-browser/trunk)

= 0.43.6 (26 April 2011) =
* **Bug fix:** Important security fixes. All users should upgrade to this version.
* See [changeset](http://plugins.trac.wordpress.org/changeset/379041/sermon-browser/trunk)

= 0.43.5 (6 October 2009) =
* **Bug fix:** Fixed bug preventing some external URLs playing in AudioPlayer.
* See [changeset](http://plugins.trac.wordpress.org/changeset/161052/sermon-browser/trunk)

= 0.43.4 (2 October 2009) =
* **Bug fix:** Fixed bug preventing install introduced in 0.43.2.
* See [changeset](http://plugins.trac.wordpress.org/changeset/160038/sermon-browser/trunk)

= 0.43.3 (2 October 2009) =
* **Bug fix:** Fixed bug preventing downloads of external URLs on PHP4.
* See [changeset](http://plugins.trac.wordpress.org/changeset/160006/sermon-browser/trunk)

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
* See [changeset](http://plugins.trac.wordpress.org/changeset/159840/sermon-browser/trunk)

= 0.43.1 (18 September 2009) =
* **New feature:** The Romanian Cornilescu Bible version has been added. Use the template tag [cornliescutext]
* **Optimisation:** Most bible texts now served from SermonBrowser's own API service, not Living Stones Ministries (ESV and NET continue to be supplied direct from the publisher). Unfortunately the Hebrew Names Version is no longer available, but *it makes possible the addition of bibles in dozens of other languages*. If you would like your language included, please ask.
* **Optimisation:** Improved Spanish translation (thanks to Marvin Ortega).
* **Bug fix:** Removed error with wp&#95;timezone&#95;supported() on Wordpress 2.6 and 2.7.
* **Bug fix:** Warning message now correctly displays in admin when [sermons] shortcode is missing.
* **Bug fix:** Stylesheet now loaded correctly even with default permalinks.
* **Bug fix:** Fixed link to podcast background image.
* See [changeset](http://plugins.trac.wordpress.org/changeset/156082/sermon-browser/trunk)

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
* See [changeset](http://plugins.trac.wordpress.org/changeset/154761/sermon-browser/trunk)

= 0.42.4 (21 June 2009) =
* **Bug fix:** Fixed several problems in Admin caused by incompatibilities with Wordpress 2.8.
* See [changeset](http://plugins.trac.wordpress.org/changeset/127554/sermon-browser/trunk)

= 0.42.3 (17 April 2009) =
* **Bug fix:** Missing dates now display correctly.
* **Bug fix:** Podcast now works even with PHP4.
* **Bug fix:** Definitely no more SQL warnings on install.
* See [changeset](http://plugins.trac.wordpress.org/changeset/111224/sermon-browser/trunk)

= 0.42.2 (13 April 2009) =
* **Bug fix:** Fixed weird error preventing audio plays on some set-ups.
* See [changeset](http://plugins.trac.wordpress.org/changeset/110084/sermon-browser/trunk)

= 0.42.1 (13 April 2009) =
* **Bug fix:** Fixed SQL error with embedded URLs.
* **Bug fix:** Possible fix for download failure on some set-ups.
* **Bug fix:** Podcast now works even if some URLs are invalid.
* **Bug fix:** No more SQL warnings on install.
* See [changeset](http://plugins.trac.wordpress.org/changeset/109985/sermon-browser/trunk)

= 0.42 (10 April 2009) =
* **New feature:** Sermon Browser shortcodes allow you to include single sermons or lists of sermons on any post or page on your website. For example, adding the shortcode [sermons preacher=1 id=latest] to your pastor's page would display full details of his most recent sermon on that page.
* **Optimisation:** Consolidated various help and tutorial pages to avoid having to keep three different versions up to date!
* **Bug fix:** External URLs now display on the search page alongside attached files.
* **Bug fix:** No more SQL errors on Wordpress custom pages.
* See [changeset](http://plugins.trac.wordpress.org/changeset/109328/sermon-browser/trunk)

= 0.41.2 (9 April 2009) =
* **Bug fix:** Some dates displaying incorrectly after Daylight Savings Time change.
* See [changeset](http://plugins.trac.wordpress.org/changeset/109047/sermon-browser/trunk)

= 0.41.1 (9 April 2009) =
* **Bug fix:** URLs (and not just files) now show up in podcast feeds. Multiple files/URLs per sermon are now also supported, as are some non-mp3 files such as .mov and .mp4
* **Bug fix:** Minor bug affecting people who have renamed their Wordpress database.
* See [changeset](http://plugins.trac.wordpress.org/changeset/108954/sermon-browser/trunk)

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
* See [changeset](http://plugins.trac.wordpress.org/changeset/108590/sermon-browser/trunk)

= 0.40.2 (3 January 2009) =
* **Bug fix:** Further fix to ensure valid podcast feeds.
* See [changeset](http://plugins.trac.wordpress.org/changeset/83832/sermon-browser/trunk)

= 0.40.1 (30 December 2008) =
* **Bug fix:** Fixed bug introduced in previous version that prevented podcasts working in iTunes.
* See [changeset](http://plugins.trac.wordpress.org/changeset/83055/sermon-browser/trunk)

= 0.40 (12 December 2008) =
* **New feature:** Added sermon browser tag cloud widget.
* **New feature:** Optional mini audio player now in the sidebar widget. Go to the widget options to turn it on.
* **Optimisation:** Admin pages now display correctly in Wordpress 2.7.
* **Optimisation:** Added workaround to ensure iTunes works with Feedburner.
* **Bug fix:** Hopefully finally fixed the podcast problems for those not using permalinks. You may need to resave your options, or even reset your options if upgrading from an earlier version.
* See [changeset](http://plugins.trac.wordpress.org/changeset/79270/sermon-browser/trunk)

= 0.39 (1 December 2008) =
* **New feature:** Sermon Browser in Spanish. Thanks to Juan for providing the translation.
* **New feature:** If you have 'Update services' enabled in Wordpress, a special web-service is now 'pinged' when sermons are edited. This will help your sermons be found by search engines.
* **Bug fix:** Calendar now displays correctly in admin (removed CSS clash).
* **Bug fix:** Bible books and dates now appear correctly in non-English languages. You may need to reset the options to default to make this work.
* See [changeset](http://plugins.trac.wordpress.org/changeset/76786/sermon-browser/trunk)

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
* See [changeset](http://plugins.trac.wordpress.org/changeset/76389/sermon-browser/trunk)

= 0.37.3 (14 October 2008) =
* **Bug fix:** HTML should now validate (ampersands in URLs now correct).
* **Bug fix:** Podcast feed on sites without pretty permalinks should now work.
* See [changeset](http://plugins.trac.wordpress.org/changeset/68914/sermon-browser/trunk)

= 0.37.2 (9 October 2008) =
* **Bug fix:** Fixed incompatibilities with a few more plug-ins introduced in the previous version.
* See [changeset](http://plugins.trac.wordpress.org/changeset/68148/sermon-browser/trunk)

= 0.37.1 (8 October 2008) =
* **Bug fix:** Now compatible with the Gengo plugin.
* See [changeset](http://plugins.trac.wordpress.org/changeset/67967/sermon-browser/trunk)

= 0.37 (6 October 2008) =
* **Optimisation:** Download stats are no longer counted for blog authors/admins (thanks Matthew Hiatt)
* **Optimisation:** Sermon Browser now deactivates itself when it is uninstalled.
* **Bug fix:** Rogue slashes no longer display in filter.
* **Bug fix:** Divide by zero error on dashboard fixed (thanks Matthew Hiatt).
* **Bug fix:** User templates now updated after upgrade if dictionary.php is changed.
* **Bug fix:** Sermon Browser now uses the date format from Wordpress settings.
* See [changeset](http://plugins.trac.wordpress.org/changeset/67681/sermon-browser/trunk)

= 0.36 (1 October 2008) =
* **Added:** Two new Bible versions (American King James [AKJV], and the Hebrew Names Version [HNV])
* **Fixed:** Filter now works even when sermons page is the front page of site.
* **Fixed:** Non-ESV bibles now display.
* See [changeset](http://plugins.trac.wordpress.org/changeset/67002/sermon-browser/trunk)

= 0.35 (4 August 2008) =
* **Added:** Simple statistics on Dashboard (Wordpress 2.5+)
* **Optimisation:** Large download files less likely to cause errors.
* **Optimisation:** More robust tag handling.
* **Fixed:** Closed security loophole.
* **Fixed:** Duplicate indexes bug.
* **Fixed:** PHP errors when adding sermons with no bible passage.
* See [changeset](http://plugins.trac.wordpress.org/changeset/58027/sermon-browser/trunk)

= 0.34 (31 July 2008) =
* **Added:** Support for WordPress MU!
* **Changed:** Will now create 'uploads' folder if it doesn't already exist.
* **Fixed:** YLT and WEB Bibles now display correctly.
* **Fixed:** Minor bug affecting those who have renamed their Wordpress database.
* See [changeset](http://plugins.trac.wordpress.org/changeset/57080/sermon-browser/trunk)

= 0.33 (28 July 2008) =
* **Fixed:** Two minor bugs affecting users with Wordpress installs away from their root directory.
* See [changeset](http://plugins.trac.wordpress.org/changeset/56760/sermon-browser/trunk)

= 0.32 (28 July 2008) =
* **Fixed:** Sermons now download correctly in iTunes (although iTunes downloads won't count towards your stats due to an iTunes limitation).
* **Fixed:** You can now add more than three bible passages to a sermon.
* **Fixed:** ALT text now correct on file icons.
* **Fixed:** Links now work correctly even when Wordpress is not in the root.
* **Fixed:** Double-path bug for podcast URL fixed.
* **Added:** Compatibility with Wordpress 2.6 custom plug-in folders.
* **Added:** Auto-discovery for custom podcasts.
* **Optimisation:** Additional files are now inserted into <HEAD> only when required, rather than on every page.
* See [changeset](http://plugins.trac.wordpress.org/changeset/56704/sermon-browser/trunk)

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
* See [changeset](http://plugins.trac.wordpress.org/changeset/52938/sermon-browser/trunk)

= 0.30.1 (25 June 2008) =
* **Fixed:** Three bugs - one that prevented uploading, one that made series/sermons/preachers appear in the filters even if they had not been assign to any sermons, and one that prevented downloads when pretty permalinks were not used.
* See [changeset](http://plugins.trac.wordpress.org/changeset/52078/sermon-browser/trunk)

= 0.30 (25 June 2008) =
* **Added:** Statistics! See how often a sermon has been listened to in the admin section. Works for sermons that are downloaded or played inline with the AudioPlayer. It doesn't work (and never can) for embedded files (e.g. videos). At the moment the stats are only there in the admin section, but feel free to suggest ways they could be helpful to viewers of your website.
* **Added:** The drop-down menus for the search options are now more intelligent. (1) They display the number of sermons recorded for each preacher/series/services/bible book. (2) They only display bible books that have sermons listed. (3) The more important preachers/series/services are displayed higher up in the drop-down list.
* **Changed:** Some significant optimisations. The main database queries are now twenty times faster than before. On my server the database activity to display the sermon-browser page takes less than seven hundredths of a second (it was about half a second). This is with 180 sermons in the database.
* **Fixed:** Preacher's name now displays correctly in page title.
* **Fixed:** All links now work correctly even when permalinks are turned off.
* See [changeset](http://plugins.trac.wordpress.org/changeset/51888/sermon-browser/trunk)

= 0.25 (24 June 2008) =
* **Added:** [editlink] tag adds an 'Edit sermon' link if the currently logged-in user has edit privileges.
* **Added:** [sermon_description] tag now displays description of sermon on sermon page
* **Added:** [file_with&#95;download] tag is similar to [file], but adds a download link if the AudioPlayer is displayed
* **Changed:** External URLs and local files are now treated in the same way.
* See [changeset](http://plugins.trac.wordpress.org/changeset/51772/sermon-browser/trunk)

= 0.24 (21 June 2008) =
* **Added:** Plug-in now updates automatically on Wordpress 2.3 and above
* **Added:** Podcasts now include filesizes of MP3 files
* **Fixed:** Now able to delete and rename linked files
* **Fixed:** Custom podcast now always returns most recent sermons
* **Fixed:** Podcasts now return a maximum of 15 sermons
* See [changeset](http://plugins.trac.wordpress.org/changeset/51345/sermon-browser/trunk)

= 0.23 (13 June 2008) =
* **Added:** Individual sermon pages now show sermon details in page title for better navigation and SEO.
* **Added:** Several checks for settings in php.ini that might cause uploads to fail.
* **Added:** Support for cURL (provides better compatibility for displaying bible texts).
* **Fixed:** Now able to display images and descriptions of preachers on their sermons.
* **Optimisation:** Several other minor bug fixes.
* See [changeset](http://plugins.trac.wordpress.org/changeset/57043/sermon-browser/branches/0.10-0.23)

= 0.22 (12 June 2008) =
* **Added:** Four new Bible versions: ASV, KJV, YLT and WEB (in addition to ESV)
* **Added:** Support for version 2 of the 1PixelOut Audio Player
* **Optimisation:** Two minor bug fixes.
* See [changeset](http://plugins.trac.wordpress.org/changeset/57040/sermon-browser/branches/0.10-0.23)

= 0.21 (12 June 2008) =
* **Added:** Template tags for iTunes specific podcast links.
* **Changed:** Non-administrators can now use Sermon Browser (although only Administrators can change the options).
* **Changed:** Updated default template and CSS. Reset to defaults in Options to use it.
* **Changed:** More options in sidebar widget.
* **Optimisation:** Reduced number of database queries, reducing page-creation time by around 15%.
* **Fixed:** Links in the widget give 'Page not found errors'
* **Fixed:** Filter by date gives incorrect results
* See [changeset](http://plugins.trac.wordpress.org/changeset/57039/sermon-browser/branches/0.10-0.23)

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
* See [changeset](http://plugins.trac.wordpress.org/changeset/57037/sermon-browser/branches/0.10-0.23)

= 0.1 (13 May 2008) =
* **Initial release**