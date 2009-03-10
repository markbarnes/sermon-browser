=== Plugin Name ===
Contributors: mark8barnes
Donate link: http://www.4-14.org.uk/sermon-browser#support
Tags: sermons, podcast, mp3, church, bible, audio, widget, embed, video, esv, wpmu, church, iTunes, preacher
Requires at least: 2.2
Tested up to: 2.7.1
Stable tag: trunk

Upload sermons to your website, where they can be searched, listened to, and downloaded. Easy to use with comprehensive help and tutorials.

== Description ==

The Sermon Browser Wordpress Plugin allows churches to simply upload sermons to their website, where they can be searched, listened to, and downloaded. It is easy to use with comprehensive help and tutorials. Features include:

1. Sermons can be **searched** by topic, preacher, bible passage or date.
2. Full **podcasting** capabilities, including custom podcasts for individual users.
3. Sermons uploaded in mp3 format can be **played directly** on your website using the 1PixelOut Audio Player.
4. An optional **sidebar widget** displays sermons on all of your posts or pages.
5. **Embed videos** and other flash files from sites such as YouTube or Google Video.
6. **Other file types** can also be uploaded, including PDF, Powerpoint, Word, text and RTF. Multiple files can be attached to single sermons.
7. The **full Bible text** of the passage being preached on can be included on each sermon page (five different versions, including ESV).
8. Files can be uploaded to your own site **through the browser or via FTP**. Alternatively you can use other free audio hosting sites such as Odeo.
9. Powerful **templating function** allows complete customisation to complement the look of your site.
10. Simple statistics show how often each sermon has been listened to.
11. Support for Wordpress MU (WPMU)
12. Extensive **help and tutorial** screencasts.

== Installation ==

1. Download the plugin, and unzip it.
2. Place the sermon-browser folder in your wp-content/plugins folder and upload it to your website.
3. Activate the plugin from the plugins tab of your Wordpress admin.
4. You may have to change the permissions the upload folder (by default wp-content/uploads/sermons).
5. Create a Wordpress page with the text [sermons]. The plugin will display your sermons on this page.

#### Installation in Wordpress MU
1. Download the plugin, and unzip it.
2. Place the contents of the sermon-browser folder in your wp-content/mu-plugins folder and upload it to your website.
3. The plugin will be automatically activated and available for each user.

== Frequently Asked Questions ==

#### I've activated the plugin, and entered in a few sermons, but they are not showing up to my website users. Where are they?

SermonBrowser only displays your sermons where you choose. You need to create the page/post where you want the sermons to appear (or edit an existing one), and add [sermons] to the page/post. You can also add some explantory text if you wish. If you do so, the text will appear on all your sermons pages. If you want your text to only appear on the list of sermons, not on individual sermon pages, you need to edit the SermonBrowser templates (see below).

#### What does the error message "Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777." mean?

SermonBrowser tries to set the correct permissions on your folders for you, but sometimes restrictions mean that you have to do it yourself. You need to make sure that SermonBrowser is able to write to your sermons upload folder (usually /wp-content/uploads/sermons/). Your FTP software should be able to do this for you - consult the help files that came with that program.

#### SermonBrowser spends a long time attempting to upload files, but the file is never uploaded. What's happening?

The most likely cause is that you're reaching either the maximum filesize that can be uploaded, or the maximum time a PHP script can run for. Editing your php.ini may help overcome these problems - but if you're on shared hosting, it's possible your host has set maximum limits you cannot change. If that's the case, you should upload your files via FTP, which is generally a better option than using your browser, particularly if you have several files to upload. If you do edit your php.ini file, these settings should be adequate:

    file_uploads = On
    upload_max_filesize = 15M
    post_max_size = 15M
    max_execution_time = 600
    max_input_time = 600
    memory_limit = 16M

#### Why are my MP3 files are appearing as an icon, rather than as a player, as I've seen on other SermonBrowser sites?

You need to install and activate the 1pixelout audio player plugin. You can also customise the plugin so that its colours match your site.

#### How do I change the Bible version from the ESV?

Six Bible versions are supported by Sermon Browser: the English Standard Version, American Standard Version, King James Version, Young's Literal Translation, the World English Bible and the Spanish-language Reina Valera. To change to one of these other versions, go to Options, and edit the single template. Replace [esvtext] with [asvtext], [kjvtext], [ylttext], [webtext] or [lbrvtext]. Thanks go to Crossway for providing access to the ESV, and Living Stones Ministries for the other versions.

If you're desperate to use other versions not currently supported, you can manage it using other Wordpress plugins (albeit with reduced functionality). However, if you're desperate to use other versions, you can manage it using other Wordpress plugins (albeit with reduced functionality). The eBibleicious plugin allows for NASB, MSG, KJV, NKJV, ESV, HCSB, and NCV (use it in 'snippet' mode). However, there are three disadvantages. (1) To use it, you'll need to register for an API key (although it is free). (2) It uses Javascript so search engines won't see the Bible text, and nor will users with javascript turned off. (3) Most importantly, it only shows a maximum of four verses (the ESV shows up to 500 verses!).

You can also use the RefTagger plugin, though this shows even fewer verses. Even worse (for our purposes) the bible passage only shows when you hover over a special link with your mouse. It does, however, provide an even longer list of translations. Please be aware that both RefTagger and eBibleicious will add bible text to bible references across your whole website, not just your sermons pages.

To use either of these alternatives, just download, install and activate them as you would for any other plugin. Check their settings (make sure you enter get an API key if you're using eBiblicious). You then need to make one change to your SermonBrowser options. In the *Single Sermon form*, look for **[esvtext]** and replace it with **[biblepassage]**. (By default it's right at the end of the code.)

#### When using the 1pixelout audio player, my pastor sounds like a chipmunk! What's going on?

This 'feature' is caused by a well-known bug in Adobe flash. In order for the files to play correctly, when they are saved, the sample rate needs to be set at a multiple of 11.025kHz (i.e. 11.025, 22.05 or 44.1).
How do I get recent sermons to display in my sidebar?

If your WordPress theme supports widgets, just go to Design and choose Widgets. There you easily can add the Sermons widget to your sidebar. If your theme doesn't support widgets, you'll need to edit your theme manually. Usually, you'll be editing a file called sidebar.php, but your theme may give it a different name. Add the following code:

    <?php if (function_exists('sb_display_sermons')) sb_display_sermons(array('display_preacher' => 0, 'display_passage' => 1, 'display_date' => 1, 'display_player' => 1, 'preacher' => 0, 'service' => 0, 'series' => 0, 'limit' => 5)) ?>

Each of the numbers in that line can be changed. display\_preacher, display\_passage, display\_date, and display\_player affect what is displayed (0 is off, 1 is on). preacher, service and series allow you to limit the output to a particular preacher, service or series. Simply change the number of the ID of the preacher/services/series you want to display. You can get the ID from the Preachers page, or the Series & Services page. 0 shows all preachers/services/series. limit is simply the maximum number of sermons you want displayed.

#### My host only allows me a certain amount of disk space, and I have so many sermons uploaded, I've run out of space! What can I do?

You could, of course, change your host to someone a little more generous! I use VortechHosting for low traffic sites (5Gb of disk space for less than $10 a month), and LiquidWeb VPS for higher traffic sites (20Gb disk space for $60 a month). You should also make sure you encode your sermons at a medium to high compression. Usually, 22.05kHz, 48kbps mono is more than adequate (you could probably go down to 32kbps for even higher compression). 48kbps means every minute of recording takes up 360kb of disk space, so a thirty minute sermon will just over 10Mb. At this setting, 5Gb would be enough for over 450 sermons.

If you can't change your host, you can still use SermonBrowser. You'll just have to upload your sermon files to another site - preferably a free one! We recommend Odeo. If you want to use Odeo's audio player on your website, copy the embed code they give you, and when you add your sermon to SermonBrowser, select "Enter embed code:" and paste it in. If you want to use the standard 1pixelout audio player, copy the "Download MP3? link Odeo give you, and when you add your sermon to SermonBrowser, select "Enter an URL" and paste it in.

#### How do I upload videos to SermonBrowser?

You can't - but you can upload videos to other sites, then embed them in your sermons. You can use any site that allows you to embed your video in other websites, including YouTube, but we recommend GoogleVideo as the most suitable for sermons. That's because most video-sharing sites are designed for relatively short clips of 10 minutes or so, but GoogleVideo will accept videos of any length - and there are no quotas for the maximum size of a video, nor the number of videos you can store. Once your video is uploaded and available on Google Video, you can copy the embed code it gives you, edit your sermon, select "Enter embed code" and paste it in.

#### Can I turn off the "Powered by Sermonbrowser" link?

The link is there so that people from other churches who listen to your sermons can find out about SermonBrowser themselves. But if you'd like to remove the link, just remove [creditlink] from the templates in SermonBrowser Options.

#### What is the difference between the public and private podcast feeds?

In SermonBrowser options, you are able to change the address of the public podcast feed. This is the feed that is shown on your sermons page, and is usually the same as your private feed. However, if you use a service such as FeedBurner, you can use your public feed to send data to feedburner, and change your private feed to your Feedburner address. If you do not use a service like Feedburner, just make sure your public and private feeds are the same.

#### On the sermons page, what is the difference between subscribing to a full podcast, and subscribing to a custom podcast?

The link called **subscribe to full podcast** gives a podcast of *all* sermons that you add to your site through SermonBrowser. But it may be that some people may just want to subscribe to a feed for certain speakers, or for a certain service. If they wish to do this, they should set the search filters and perform their search, then click on the **Subscribe to custom podcast** link. This will give them a podcast according to the filter they selected. You could also copy this link, and display it elsewhere on the site - for example to provide separate feeds for morning and evening services.

#### Can I change the default sort order of the sermons?

Unfortunately not. Unless the viewer specified otherwise, Sermonbrowser always displays the most recent sermons at the top.

#### Why do I get a page not found error when I click on my podcast feed?

You've probably changed the address of your public feed. Try changing it back to the same value as your private feed in Sermon Options.

#### Can I change the way sermons are displayed?

Yes, definately, although you need to know a little HTML and/or CSS. SermonBrowser has a powerful templating function, so you can exclude certain parts of the output (e.g. if you don't want the links to other sermons preached on the same day to be displayed). To edit the templates, go to SermonBrowser Options. Below is a reference for all the template tags you need. If you just want to change the way the output looks, without changing what is displayed, you need to edit the CSS stylesheet, also in SermonBrowser Options. (See one example, below).

#### The search form is too big/too small for my layout. How do I make it narrower/wider?

The search form is set to roughly 500 pixels, which should be about right for most WordPress templates. To change it, look for a line in the CSS stylesheet that begins table.sermonbrowser td.field input, and change the width specified after it. To make the form narrower, reduce the width. To make it bigger, increase the width. You'll also need to change the width of the date fields on the line below, which should be 20 pixels smaller.

#### Why is sometimes the Bible text missing?

This usually happens for one of three reasons: (1) If the website providing the service is down. They're rarely down for long. (2) If you specify an invalid bible passage (e.g. Romans 22). If this is the case your sermon page will display <em>ERROR: No results were found for your search.</em> (3) If you never get the bible passages for any bible version, it's probably your webhost has disabled <strong>allow\_url\_fopen</strong> and cURL. Some cheaper webhosts have these essential features switched off. If they have, you won't be able to use this facility.

#### Why does my sermon page say I have exceeded my quota for ESV lookups?

The ESV website only allows 5,000 lookups per day from each IP address. That should be enough for most users of SermonBrowser. However, if you are using a shared host, there will be hundreds (perhaps thousands) of other websites on the same IP address as you. If any are also using the ESV API, they also get counted towards that total. If you are using less than 5,000 lookups per day (i.e. you are having less than 5,000 pageviews of your sermon pages), and you receive the error message you'll need to do two things in order to continue to display the text. (1) Sign up for an ESV API key. (2) Edit frontend.php (one of the SermonBrowser files). Look for line 66, and replace
`...passageQuery?key=**IP**&passage=...`
with
`...passageQuery?key=**YOURAPIKEY**&passage=...`.

If you are having more than 5,000 page views per day, then this won't help. Instead, leave a message in the SermonBrowser comments explaining your problem. SermonBrowser could probably be modified to provide a caching mechanism to reduce the likelihood of this error occurring, if there is demand.

#### How can I change the file icons that Sermon Browser uses, or add new icons?

You'll need to edit the filetypes.php file that comes with Sermon Browser. The icon is chosen on the basis of the file extension (or in the case of URLs the file extension then the site address). If you do create new icons for other filetypes, consider sending them to the author so they can be included in future versions of the plugin.

== Screenshots ==

1. **Displaying sermons on your website:** This first screen shot shows how Sermon Browser looks to your readers. This particular search shows only those sermons preached from 1 Corinthians. Each of the sermons has audio files, but one also has an embedded video. On the left-hand side you can see the widget showing the most recent sermons preached. At the top you can see a link to both the full podcast, and lower down to a custom podcast.
2. **The single sermon page (with Bible text):** This next screenshot shows the detail for one sermon. You can see additional links to other sermons preached around that time, plus the complete ESV text of the passage being preached on.
3. **Editing a sermon:** The third screenshot shows the main editing window. You can see a wide variety of information can be entered, including the bible passage being preached on, and any files linked to the sermon. Any number of Bible passages can be included, and any number of files (e.g. you could attach an mp3 file for the audio recording, a powerpoint file, and a word document of PDF of the sermon text).
4. **Using the template facility:** The final screenshot shows the options screen with its powerful templating facility. With a little knowledge of HTML and CSS and the Sermon Browser template tags, you can easily adapt Sermon Browser's output to suit your own requirements. SermonBrowser produces valid XHTML code, using semantically correct markup and is fully standards compliant.

== Template tags ==

If you want to change the output of Sermon Browser, you'll need to edit the templates. You'll need to understand the basics of HTML and CSS, and to know the special SermonBrowser template tags. There are two templates, one (called "results page") is used to produce the search results on the main sermons page. The other template (called sermon page) is used to produce the page for single sermon. Most tags can be used in both templates, but some are specific.

#### Results page only

* **[filters\_form]** - The search form which allows filtering by preacher, series, date, etc. multi-sermons page only
* **[sermons\_count]** - The number of sermons which match the current search critera.
* **[sermons\_loop][/sermons\_loop]** - These two tags should be placed around the output for one sermon. (That is all of the tags that return data about sermons should come between these two tags.)
* **[first\_passage]** - The main bible passage for this sermon
* **[previous\_page]** - Displays the link to the previous page of search results (if needed)
* **[next\_page]** - Displays the link to the next page of search results (if needed)
* **[podcast]** - Link to the podcast of all sermons
* **[podcast\_for\_search]** - Link to the podcast of sermons that match the current search
* **[itunes\_podcast]** - iTunes (itpc://) link to the podcast of all sermons
* **[itunes\_podcast\_for\_search]** - iTunes (itpc://) link to the podcast of sermons that match the current search
* **[podcasticon]** - Displays the icon used for the main podcast
* **[podcasticon\_for\_search]** - Displays the icon used for the custom podcast

#### Both results page and sermon page

* **[sermon\_title]** - The title of the sermon
* **[preacher\_link]** - The name of the preacher (hyperlinked to his search results)
* **[series\_link]** - The name of the series (hyperlinked to search results)
* **[service\_link]** - The name of the service (hyperlinked to search results)
* **[date]** - The date of the sermon
* **[files\_loop][/files\_loop]** - These two tags should be placed around the [file] tag if you want to display all the files linked with to sermon. They are not needed if you only want to display the first file.
* **[file]** - Displays the files and external URLs
* **[file_with_download]** - As above, but also adds a download link if the AudioPlayer is displayed
* **[embed\_loop][/embed\_loop]** - These two tags should be placed around the [embed] tag if you want to display all the embedded objects linked to this sermon. They are not needed if you only want to display the first embedded object.
* **[embed]** - Displays an embedded object (e.g. video)
* **[editlink]** - displays an "Edit Sermon" link if currently logged-in user has edit rights.
* **[creditlink]** - displays a "Powered by Sermon Browser" link.

#### Sermon page only

* **[preacher\_description]** - The description of the preacher.
* **[preacher\_image]** - The photo of the preacher.
* **[sermon\_description]** - The description of the sermon.
* **[passages\_loop][/passages\_loop]** - These two tags should be placed around the [passage] tag if you want to display all the passages linked with to sermon.
* **[passage]** - Displays the reference of the bible passage with the book name hyperlinked to search results.
* **[next\_sermon]** - Displays a link to the next sermon preached (excluding ones preached on the same day)
* **[prev\_sermon]** - Displays a link to the previous sermon preached
* **[sameday\_sermon]** - Displays a link to other sermons preached on that day
* **[tags]** - Displays the tags for that sermons
* **[esvtext]** - Displays the full text of the ESV Bible for all passages linked to that sermon.
* **[asvtext]** - Displays the full text of the ASV Bible for all passages linked to that sermon.
* **[kjvtext]** - Displays the full text of the KJV Bible for all passages linked to that sermon.
* **[ylttext]** - Displays the full text of the YLT Bible for all passages linked to that sermon.
* **[webtext]** - Displays the full text of the WEB Bible for all passages linked to that sermon.
* **[akjvtext]** - Displays the full text of the AKJV Bible for all passages linked to that sermon.
* **[hnvtext]** - Displays the full text of the HNV Bible for all passages linked to that sermon.
* **[biblepassage]** - Displays the reference of the bible passages for that sermon. Useful for utilising other bible plugins (see FAQ).