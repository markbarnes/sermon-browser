# Sermon Browser

[Sermon Browser](https://wordpress.org/plugins/sermon-browser/) is a WordPress plugin for uploading, searching and podcasting sermons. WordPress.org closed the plugin on 4 February 2022 for a security issue ([CVE-2022-0499](https://www.wordfence.com/threat-intel/vulnerabilities/wordpress-plugins/sermon-browser/sermon-browser-04522-cross-site-request-forgery)). Version 0.46.0 fixes that and the other problems found in a full review; see the changelog in `readme.txt`.

`297ef9f` is the 0.45.22 release (identical to WordPress.org SVN), so `git diff 297ef9f` shows every change since.

## Installing

1. `npm run zip` builds `sermon-browser.zip` from the committed code (commit first).
2. In WordPress, go to Plugins → Add New → Upload Plugin and upload the zip. If the old version is installed, WordPress offers to replace it; sermons, files and settings are kept.
3. Templates are recompiled on the first page load after the update.

The plugin folder must be named `sermon-browser`. The `Update URI: false` header stops WordPress from replacing this version with the closed 0.45.22 from WordPress.org.

On servers other than Apache, also stop the web server running scripts from the sermon upload folder (normally `wp-content/uploads/sermons/`), since the `.htaccess` the plugin writes there only works on Apache.

## Development

Requires Docker and Node.

- `npm start` runs WordPress with this plugin at http://localhost:8890 (admin: `admin` / `password`), on PHP 8.3 with debug logging to `wp-content/debug.log`. `npm stop` stops it.
- `npm run lint` checks every PHP file's syntax.
- Run WP-CLI with `npx wp-env run cli wp <command>`.

## Security model

- Admin AJAX requests (`ajax.php`, any POST with `sermon=1`) need a logged-in user, the `sb_ajax` nonce (`sbAjaxNonce` in the page) and the capability of the matching admin page.
- Uploads only accept file types WordPress allows the user to upload (`sb_check_uploaded_file()`), checked by name and content.
- `?download` and `?show` only serve files and URLs recorded in the `sb_stuff` table.
- Templates are compiled to PHP and run with `eval()`. `sb_compile_template()` turns any PHP tags in a template into text, so only the template tags in `dictionary.php` produce code, and those escape what they print.
- Escape on output; use `$wpdb->prepare()` for every value in SQL.
