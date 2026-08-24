# XOOPS 2.7.3 Final — security hardening and PHP 8.6 readiness

The XOOPS Development Team is pleased to announce **XOOPS 2.7.3 Final**. This
release hardens security across the core, prepares XOOPS for PHP 8.6 while
remaining fully supported on PHP 8.2 through 8.5, adds SCEditor as an optional
BBCode editor, introduces file-based debug configuration with a rotating file
logger, and folds in a series of reliability fixes proven in production on
xoops.org.

Download XOOPS 2.7.3:
**[https://github.com/XOOPS/XoopsCore27/releases](https://github.com/XOOPS/XoopsCore27/releases)**

---

## Security hardening

Security received sustained attention across the whole 2.7.3 cycle:

- **Form renderer escaping.** Element values are escaped in all five form
  renderers through a shared trait, and JavaScript arguments are built with
  `json_encode()` — the correct encoder for a JS string literal inside an HTML
  attribute.
- **Template-set browser and editor contained by PathGuard.** The tplsets
  browse, edit, restore, and save endpoints were hardened end-to-end —
  double-decode removed, NUL bytes rejected, symlink escapes refused, backups
  written atomically — and the path-containment contract now lives in a shared
  `PathGuard` class pinned by a truth-table test suite against a real fixture
  tree.
- **Logout requires a session token.** A bare GET could previously end a
  visitor's session from any third-party page (forced-logout CSRF). A tokenless
  request now renders a POST confirmation instead of acting immediately, so
  every existing logout link keeps working at the cost of one extra click.
- **Redirect query strings are rebuilt, not reflected.** Eight redirect sites
  appended the raw `QUERY_STRING` verbatim to their `Location` headers. The
  string is now parsed and re-emitted via a shared, unit-tested helper
  (`xoops_rebuildQueryString()`), so every reflected byte is RFC 3986-safe by
  construction.
- **image.php's remote-image branch is closed.** With `ONLY_LOCAL_IMAGES`
  disabled, the raw request URL previously reached `getimagesize()` and
  `file_get_contents()` — an SSRF and phar-deserialization surface. The branch
  had never worked (it truncated every rooted URL to a single character), so
  nothing real is lost: it now fails closed.
- **Module manifests and the image manager.** Manifest values are escaped at
  the point of output on the module admin log pages, the image category
  handlers now enforce authorization and not just CSRF, and three stale
  renderer copies under TinyMCE plugins were replaced with the core class.

## Ready for PHP 8.6

XOOPS 2.7.3 runs on PHP 8.2 through 8.5 and is prepared for PHP 8.6:

- The session save-handler contract is complete — `create_sid()` is
  implemented ahead of its PHP 9.0 requirement, brand-new sessions survive
  8.6's `updateTimestamp()` routing, and `session.use_strict_mode` is pinned
  to 1 (the 8.6 default) on today's PHP versions as well.
- Deprecations are cleared ahead of time: constructor value-returns (compile-time
  deprecated in 8.6) are gone and guarded by a repository-wide test,
  `is_long()` is replaced, and the deprecated `curl_close()` and
  `imagedestroy()` calls are dropped.

## Editors

- **SCEditor 3.2.1** ships bundled as an optional BBCode editor, selectable
  from the editor preference dropdown. It is deliberately locked to source
  mode so existing content never passes through a WYSIWYG round-trip that
  could rewrite XOOPS-specific BBCode.
- **One shared dhtml toolbar.** The same editor used to show a different
  toolbar in the control panel than on the front end; all five renderers now
  delegate to a single toolbar implementation with framework-neutral classes.

## Debugging and logging

- **File-based debug configuration.** Error display, error_reporting, and
  query logging are set in one place — `xoops_data/data/debug.php` — instead
  of being edited into mainfile.php. Nothing changes until an administrator
  creates the file.
- **A rotating, redacting file logger** records notices, warnings, errors and
  SQL with backtraces, so a blank page or an error on a redirect can still be
  read afterwards. Server paths, session ids, and session rows are redacted;
  control characters are stripped so log lines cannot be forged.
- **The error screen has one declared owner**, resolved deterministically, so
  error-screen providers such as Whoops or Tracy no longer compete for the
  seat, and a contested registration is detected and reported.

## Reliability fixes from xoops.org production

- One module with PHP4-style constructors no longer takes global search down
  for every visitor; failing modules are contained and logged.
- The search "Show all" / "Show all by user" pages render results (and the
  "no match" message) again, `search.php` no longer fatals on unvalidated
  module ids, `showall` respects `module_read` permissions, and `browse.php`
  sends a well-formed `Cache-Control` header — all reported by
  [CHCCD](https://github.com/CHCCD) in
  [#161](https://github.com/XOOPS/XoopsCore27/issues/161),
  [#162](https://github.com/XOOPS/XoopsCore27/issues/162), and
  [#163](https://github.com/XOOPS/XoopsCore27/issues/163).
- `xoops_getrank()` no longer fatals when no rank row matches.
- A failed query handed to a result-set method returns the documented failure
  value instead of blanking the page with a TypeError.
- `Criteria` renders an empty `IN ()` list as a constant predicate instead of
  invalid SQL, and preserves the caller's PHP type in IN lists.
- The unfiltered group list is memoised per request, removing roughly 48
  identical queries per page; comment listings gained an index that took one
  query from 541ms to 0.5ms.
- The login redirect no longer accumulates escaped ampersands hop by hop.

## Deprecations

The `XOBJ_DTYPE_UNICODE_*` object datatypes are deprecated in 2.7.3 — they
url-encode on write and url-decode on read, a pre-UTF-8 workaround that bloats
storage and breaks `LIKE`/`FULLTEXT` search on modern utf8mb4 installs. This
release only reports their use; behavior is unchanged. Data migration is
planned for 2.8 and constant removal for 4.0.

---

## Upgrading

### From XOOPS 2.7.2

XOOPS 2.7.3 includes schema changes, so after copying the new `htdocs/` files
over the web root, **run the upgrade wizard**. No mainfile.php changes are
needed: the debug configuration, error screen, and developer gate all read
`xoops_data/data/debug.php` directly, so creating that file is sufficient on
its own.

### Installing fresh or from older versions

Follow the standard installation and upgrade guidance:
[https://xoops.github.io/xoops-docs/](https://xoops.github.io/xoops-docs/)

---

## System requirements

| | |
|---|---|
| **PHP** | >= 8.2.0; PHP 8.4 or 8.5 recommended, prepared for 8.6 |
| **MySQL / MariaDB** | MySQL >= 5.7.8 or MariaDB >= 10.5; a supported MySQL 8.x or MariaDB LTS release is recommended |
| **Web server** | Apache 2.4+ or nginx |

---

## Translations

XOOPS 2.7.3 adds new English language constants: the SCEditor editor strings
introduced in RC 1 and one logout-confirmation string added in Final — see
[`docs/lang_diff.txt`](lang_diff.txt) for their exact definitions. XOOPS
remains maintained in **37 community translations** under the
[XoopsLanguages](https://github.com/XoopsLanguages) organization; see
[`docs/TRANSLATIONS.md`](TRANSLATIONS.md) for the current release page of each
language.

---

## Reporting issues

- **Bug reports:** [https://github.com/XOOPS/XoopsCore27/issues](https://github.com/XOOPS/XoopsCore27/issues)
- **Support forums:** [https://xoops.org/modules/newbb/](https://xoops.org/modules/newbb/)
- **Contributing:** [https://github.com/XOOPS/XoopsCore27/blob/master/CONTRIBUTING.md](https://github.com/XOOPS/XoopsCore27/blob/master/CONTRIBUTING.md)

---

## Thank you

A release this size doesn't happen without contributors. Thank you to everyone
who submitted pull requests, reported issues, tested the beta and RC packages,
translated strings, reviewed security findings, and kept the conversation going
on the forums and on GitHub throughout the 2.7.3 cycle.

A special thank-you to [CHCCD](https://github.com/CHCCD) for testing the
release candidates and reporting the search and browse bugs fixed in this
release ([#161](https://github.com/XOOPS/XoopsCore27/issues/161),
[#162](https://github.com/XOOPS/XoopsCore27/issues/162),
[#163](https://github.com/XOOPS/XoopsCore27/issues/163)). Bug reports like
these make XOOPS better for everyone.

We also thank [JetBrains](https://www.jetbrains.com/) for supporting the project
with [PhpStorm](https://www.jetbrains.com/phpstorm/) licenses.

**Download XOOPS 2.7.3:**
[https://github.com/XOOPS/XoopsCore27/releases](https://github.com/XOOPS/XoopsCore27/releases)

---

**The XOOPS Development Team**

August 2026
