# Sermon Browser

[Sermon Browser](https://wordpress.org/plugins/sermon-browser/) is a WordPress plugin for uploading, searching and podcasting sermons. WordPress.org closed the plugin on 4 February 2022 for a security issue ([CVE-2022-0499](https://www.wordfence.com/threat-intel/vulnerabilities/wordpress-plugins/sermon-browser/sermon-browser-04522-cross-site-request-forgery)). Version 0.46.0 fixes that and the other problems found in a full review; see the changelog in `readme.txt`.

## Installing

Until WordPress.org reopens the plugin, install it from the zip attached to the latest [GitHub release](https://github.com/markbarnes/sermon-browser/releases):

1. Download `sermon-browser.zip` from the release (not GitHub's green Code → Download ZIP button: that unpacks to a folder called `sermon-browser-master`, and the plugin only works in a folder called `sermon-browser`).
2. In WordPress, go to Plugins → Add New → Upload Plugin and upload the zip. If an older version is installed, WordPress offers to replace it; sermons, files and settings are kept.
3. The first page load after the update finishes upgrading (it tidies up old settings).

On servers other than Apache, also stop the web server running scripts from the sermon upload folder (normally `wp-content/uploads/sermons/`), since the `.htaccess` the plugin writes there only works on Apache.

To build the zip yourself from a clone, without installing anything else:

```bash
git archive --format=zip --prefix=sermon-browser/ -o sermon-browser.zip HEAD
```

`.gitattributes` leaves the development files (tests, this README, the wp-env and npm files) out of the zip. It is built from committed code, so commit first.

## Development

Requires Docker and Node; npm is only used for development (wp-env and the tests), never by the plugin itself.

`297ef9f` is the 0.45.22 release (identical to WordPress.org SVN), so `git diff 297ef9f` shows every change since.

- `npm start` runs WordPress with this plugin at http://localhost:8890 (admin: `admin` / `password`), on PHP 8.3 with debug logging to `wp-content/debug.log`. `npm stop` stops it.
- `npm run lint` checks every PHP file's syntax.
- `npm run phpcs -- <files>` checks files against the WordPress Coding Standards (the classes in `sb-includes/classes/` follow them; the older procedural files don't yet), and `npm run phpcbf -- <files>` fixes what it can. They run in the test site's CLI container with the Composer packages `npm run test:unit` installs.
- Run WP-CLI with `npx wp-env run cli wp <command>`, or on the test site `npx wp-env run --config=.wp-env.tests.json cli wp <command>`.
- WordPress.org's [Plugin Check](https://wordpress.org/plugins/plugin-check/): install it once with `npx wp-env run cli wp plugin install plugin-check --activate`, then run `npx wp-env run cli wp plugin check sermon-browser`. It checks the whole folder, so ignore what it reports in `tests/`, dotfiles and other files the zip leaves out.

## Tests

`npm test` runs the behaviour tests (about 20 seconds) against the wp-env test site at http://localhost:8891. It is a separate wp-env environment (`.wp-env.tests.json`) with its own containers and database, so it never touches the development site. `npm start` starts both.

The tests work from the outside: they make HTTP requests as visitors and as logged-in users of each role, and check responses, rendered output and database state. That way they keep passing through restructuring, as long as behaviour is unchanged.

| File | Covers |
|---|---|
| `security-ajax.test.js` | Every admin AJAX action by role and nonce |
| `security-uploads.test.js` | File types, nonces and folders for every upload path (CVE-2022-0499) |
| `security-downloads.test.js` | `?download` and `?show`: no open redirect, open proxy or path traversal |
| `security-sql.test.js` | Hostile values in ID3 tags, sermon fields, filters and the feed |
| `security-xss.test.js` | Script payloads in every field and request parameter, on every view; PHP in templates |
| `frontend.test.js` | The sermon list, filters, sermon pages, widgets, every template tag, the admin bar menu, feed and stylesheet, with output snapshots |
| `filters.test.js` | Filters the seed alone doesn't reach (a long line folded behind "more", dates listed by month, several active filters) and what the sermons page adds to the head (feed links, the date picker) |
| `install.test.js` | Uninstalling (who may, the nonce, removing tables, options and files, deactivating) and installing afresh (tables, sample data, default settings, upload folder) |
| `customised-settings.test.js` | The same views with customised templates, stylesheet and options (sermons without attachments hidden, files with download links, widgets set up; built in `lib/customised.js`), with snapshots in `tests/snapshots/customised/` |
| `upgrade.test.js` | Updating from 0.45.21 with customised settings, stored as the old version stored them: the first request shows the same output as a fresh install, protects the upload folder and removes old options, including the templates older versions stored compiled to PHP; the database upgrade from 1.6 |
| `templates.test.js` | How templates are read: every tag on its own for each sermon (snapshots in `tests/snapshots/templates/`), the text around tags, tags that aren't the template's, PHP in templates, loops used twice or inside each other, the passages loop, and saving and resetting on the Templates page |
| `template-errors.test.js` | Tags where they can't work (outside or after their loop, loops not closed or not opened): left out for visitors, explained to administrators and on the Templates page; `[file]` and `[embed]` outside their loops show the first |
| `bible-text.test.js` | Bible text from the ESV and NET APIs (stubbed) on sermon pages |
| `themes.test.js` | Classic themes as well as block themes (the others use the block theme): the same output, and the sermon in the page title for classic themes that call `wp_title()`. The classic theme is `tests/themes/sb-classic`, mapped into the test site by `.wp-env.tests.json` |

How it fits together:

- `tests/fixtures/seed.php` resets the plugin's tables, options and upload folder before each test file. Its data copies awkward shapes found in real sermon data (escaped apostrophes, typographic punctuation, entities, sermons without passages), plus a sermon with script payloads in every field. Test users' passwords are in the seed; they exist only on the test site.
- `tests/mu-plugins/` is mapped into the test site only. It blocks outside HTTP requests (stubbing `example.org`) so output is the same on every run, provides a test-only endpoint for seeding, queries and PHP, and registers a widget area, so the widgets are tested the way a theme shows them (the seed's `/sermon-widgets/` page renders each with the Legacy Widget block).
- `tests/snapshots/` holds the plugin's output for the main views. If a change to output is intended, run `npm run test:update-snapshots` and review the diff in git before committing.
- Each test file ends by checking that the debug log is free of PHP errors, warnings, notices and deprecations. `.wp-env.tests.json` turns on `WP_DEBUG` and `WP_DEBUG_LOG`; without them WordPress writes nothing to the log.
- To test another PHP version, put `{ "phpVersion": "8.5" }` in `.wp-env.tests.override.json` (git-ignored; `.wp-env.override.json` does the same for the development site) and run `npm start` again. The tests pass on PHP 7.4 to 8.5. With older PHP versions (at least 7.4 to 8.1), wp-env can't build its images if your user or group id is above 256000 (common on Macs managed by an organisation), because their Alpine Linux rejects such ids.

`npm run test:unit` runs the PHPUnit tests in `tests/phpunit/` (files ending `-test.php`) inside the test site's CLI container, with WordPress's own test suite, which wp-env provides. They test PHP code directly: the repositories' contract tests, which every storage implementation must pass. WordPress's test bootstrap reinstalls WordPress in the database it is given, so they use a database of their own, `sb_phpunit`, created on the first run, and never touch the test site. The first run also installs PHPUnit with Composer into `vendor/` (git-ignored, and left out of the zip like the rest). Pass PHPUnit options after `--`, for example `npm run test:unit -- --filter Sermon_Repository`.

To see which parts of the plugin the tests never reach, restart the environment with Xdebug's coverage mode and run the tests with coverage:

```bash
npx wp-env start --config=.wp-env.tests.json --xdebug=coverage && npm run test:coverage
```

`tests/mu-plugins/sb-test-coverage.php` records the plugin lines each request runs, and `tests/coverage/run.js` merges them into `coverage/summary.md` (lines covered per file, functions never or partly run) and `coverage/files/`, the source with uncovered lines marked `!!`. Pass test files to `npm run test:coverage --` to cover only those. Xdebug roughly doubles the time the tests take; `npm start` turns it off again (restarting takes a couple of minutes either way).

## Security model

- Admin AJAX requests (`ajax.php`, any POST with `sermon=1`) need a logged-in user, the `sb_ajax` nonce (`sbAjaxNonce` in the page) and the capability of the matching admin page.
- Uploads only accept file types WordPress allows the user to upload (`sb_check_uploaded_file()`), checked by name and content.
- `?download` and `?show` only serve files and URLs recorded in the `sb_stuff` table.
- Templates are read when a page is shown (`SermonBrowser\Template`) and printed by `SermonBrowser\Template_Renderer`, whose tags escape what they print. Nothing in a template runs as PHP: PHP tags in it are shown as text.
- Escape on output; use `$wpdb->prepare()` for every value in SQL.
