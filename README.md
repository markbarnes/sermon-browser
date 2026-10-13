# Sermon Browser

[Sermon Browser](https://wordpress.org/plugins/sermon-browser/) is a WordPress plugin for uploading, searching and podcasting sermons. WordPress.org closed the plugin on 4 February 2022 for a security issue ([CVE-2022-0499](https://www.wordfence.com/threat-intel/vulnerabilities/wordpress-plugins/sermon-browser/sermon-browser-04522-cross-site-request-forgery)). Version 0.46.0 fixes that and the other problems found in a full review; see the changelog in `readme.txt`.

## Installing

Until WordPress.org reopens the plugin, install it from the zip attached to the latest [GitHub release](https://github.com/markbarnes/sermon-browser/releases):

1. Download `sermon-browser.zip` from the release (not GitHub's green Code → Download ZIP button: that unpacks to a folder called `sermon-browser-master`, and the plugin only works in a folder called `sermon-browser`).
2. In WordPress, go to Plugins → Add New → Upload Plugin and upload the zip. If an older version is installed, WordPress offers to replace it; sermons, files and settings are kept.
3. Templates are recompiled on the first page load after the update.

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
- Run WP-CLI with `npx wp-env run cli wp <command>`.
- WordPress.org's [Plugin Check](https://wordpress.org/plugins/plugin-check/): install it once with `npx wp-env run cli wp plugin install plugin-check --activate`, then run `npx wp-env run cli wp plugin check sermon-browser`. It checks the whole folder, so ignore what it reports in `tests/`, dotfiles and other files the zip leaves out.

## Tests

`npm test` runs the behaviour tests (about 15 seconds) against the wp-env test site at http://localhost:8891, which has its own database, so it never touches the development site. Run `npm start` first.

The tests work from the outside: they make HTTP requests as visitors and as logged-in users of each role, and check responses, rendered output and database state. That way they keep passing through restructuring, as long as behaviour is unchanged.

| File | Covers |
|---|---|
| `security-ajax.test.js` | Every admin AJAX action by role and nonce |
| `security-uploads.test.js` | File types, nonces and folders for every upload path (CVE-2022-0499) |
| `security-downloads.test.js` | `?download` and `?show`: no open redirect, open proxy or path traversal |
| `security-sql.test.js` | Hostile values in ID3 tags, sermon fields, filters and the feed |
| `security-xss.test.js` | Script payloads in every field and request parameter, on every view; PHP in templates |
| `frontend.test.js` | The sermon list, filters, sermon pages, widgets, feed and stylesheet, with output snapshots |
| `live-settings.test.js` | The same views with the live site's templates, stylesheet and options (sermons without attachments hidden, files with download links, widgets set up), with snapshots in `tests/snapshots/live/` |
| `bible-text.test.js` | Bible text from the ESV and NET APIs (stubbed) on sermon pages |

How it fits together:

- `tests/fixtures/seed.php` resets the plugin's tables, options and upload folder before each test file. Its data copies awkward shapes found in the live site's data (escaped apostrophes, typographic punctuation, entities, sermons without passages), plus a sermon with script payloads in every field. Test users' passwords are in the seed; they exist only on the test site.
- `tests/mu-plugins/` is mapped into the test site only. It blocks outside HTTP requests (stubbing `example.org`) so output is the same on every run, provides a test-only endpoint for seeding, queries and PHP, and registers a widget area, so the widgets are tested the way a theme shows them (the seed's `/sermon-widgets/` page renders each with the Legacy Widget block).
- `tests/snapshots/` holds the plugin's output for the main views. If a change to output is intended, run `npm run test:update-snapshots` and review the diff in git before committing.
- Each test file ends by checking that the debug log is free of PHP errors, warnings, notices and deprecations. `.wp-env.json` turns on `WP_DEBUG` for the test site; wp-env turns it off there otherwise, and WordPress then writes nothing to the log.
- To test another PHP version, put `{ "phpVersion": "8.5" }` in `.wp-env.override.json` (git-ignored) and run `npm start` again. The tests pass on PHP 7.4 to 8.5. With older PHP versions (at least 7.4 to 8.1), wp-env can't build its images if your user or group id is above 256000 (common on Macs managed by an organisation), because their Alpine Linux rejects such ids.

To see which parts of the plugin the tests never reach, restart the environment with Xdebug's coverage mode and run the tests with coverage:

```bash
npx wp-env start --xdebug=coverage && npm run test:coverage
```

`tests/mu-plugins/sb-test-coverage.php` records the plugin lines each request runs, and `tests/coverage/run.js` merges them into `coverage/summary.md` (lines covered per file, functions never or partly run, template tags no test renders) and `coverage/files/`, the source with uncovered lines marked `!!`. Template tags run inside `eval()`, which line coverage can't see, so they're reported by whether a rendered template contained them. Pass test files to `npm run test:coverage --` to cover only those. Xdebug roughly doubles the time the tests take; `npm start` turns it off again (restarting takes a couple of minutes either way).

To check that the security tests catch what they should, run them against the original code. In a clean working tree:

```bash
git checkout 297ef9f -- sermon.php sb-includes && node --test --test-concurrency=1 'tests/behaviour/security-*.test.js'; git checkout HEAD -- sermon.php sb-includes && git rm -q --cached sb-includes/widget.php && rm sb-includes/widget.php
```

Against 0.45.22, 96 of the 128 security tests fail (including the debug log check at the end of each file). Those that pass cover behaviour that was already correct, including the SQL cases, which were hardening rather than exploitable.

## Security model

- Admin AJAX requests (`ajax.php`, any POST with `sermon=1`) need a logged-in user, the `sb_ajax` nonce (`sbAjaxNonce` in the page) and the capability of the matching admin page.
- Uploads only accept file types WordPress allows the user to upload (`sb_check_uploaded_file()`), checked by name and content.
- `?download` and `?show` only serve files and URLs recorded in the `sb_stuff` table.
- Templates are compiled to PHP and run with `eval()`. `sb_compile_template()` turns any PHP tags in a template into text, so only the template tags in `dictionary.php` produce code, and those escape what they print.
- Escape on output; use `$wpdb->prepare()` for every value in SQL.
