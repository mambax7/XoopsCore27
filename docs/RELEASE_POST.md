# XOOPS 2.7.0 is here — a new chapter for the CMS that refuses to quit

The XOOPS Development Team is pleased to announce **XOOPS 2.7.0 Final**. This is the largest step forward the 2.x line has taken in years, and the version number makes that official: what began as "2.5.12" during its long beta cycle is now shipping as **2.7.0**.

> **Why the version jump?** The cumulative changes since 2.5.11 — PHP 8.2 as the new baseline, Smarty 4, a brand-new admin theme, a clean-room system menu rewrite, aggressive security hardening, and a rebuilt dependency chain — added up to far more than a patch release. Promoting the number to 2.7.0 reflects what actually changed under the hood. Betas 1–8 that were published as 2.5.12 remain in the changelog under their original numbers for historical accuracy.

Download XOOPS 2.7.0: **[https://github.com/XOOPS/XoopsCore27/releases](https://github.com/XOOPS/XoopsCore27/releases)**

---

## The headline changes

### Modern PHP, everywhere

XOOPS 2.7.0 drops all PHP 7.x support. **PHP 8.2 is the new minimum**, and continuous integration exercises the core against **PHP 8.2, 8.3, 8.4, and 8.5** on every commit. The dead code branches for PHP < 7.3 and < 8.0 are gone. Session handlers have been consolidated into a single file. The installer's PHP version check now refuses anything older than 8.2.0.

If you're on PHP 8.2 or newer you can upgrade; if you're still on 7.4, this is your cue to upgrade PHP first.

### Smarty 4

The template engine has moved from the ancient forked Smarty 2 to **Smarty 4.5.5**. This is a major win for security, maintainability, and plugin compatibility — but it does mean old templates with deprecated Smarty 2 syntax need a quick review before upgrading.

To make this painless, the release bundles **`/upgrade/preflight.php`** — a scanner that identifies outdated themes and module templates before you begin the upgrade. Run it, fix the flagged files, and run it again until it's clean.

### A new "Modern" admin theme

XOOPS 2.7.0 introduces **Modern**, a new system admin theme. It's the first major refresh of the admin UI in years. Alongside it, the existing **Transition** theme continues to work for sites that prefer the familiar look.

Theme developers get a new **template overload capability** in system admin themes, making it much easier to customise admin templates without patching core files.

### System menu administration — rebuilt from scratch

Custom site navigation is now a first-class admin feature. The entire system menu module, based on ideas from Trabis and Mage, was **rewritten clean-room**: new tables, new controller, new templates, new validation (with cycle detection and depth limits), and proper CSRF and permission handling throughout.

You can manage menu categories and items, control display order, add icons, and grant per-group permissions — all from System Admin.

### Four new front-end theme platforms

- **xSwatch5** — Built on **Bootstrap 5.3.8**, the successor to xSwatch4. Drop in, switch the stylesheet, pick a Bootswatch variant, done.
- **xBootstrap5** — Pure Bootstrap 5 reference theme, kept in sync with the upstream release.
- **xTailwind** — A Tailwind CSS + DaisyUI theme with 35 DaisyUI palettes and Alpine.js for lightweight interactivity, including a new **`XoopsFormRendererTailwind`** form renderer so forms render natively in Tailwind projects without manual overrides.
- **xTailwind2** — An art-directed sibling of xTailwind with curated palettes, stronger visual hierarchy, and refined module styling.

---

## Security hardening — the quiet half of the release

A large chunk of the work since Beta 8 was security-focused. Highlights:

- **CSRF tokens on all module admin AJAX requests** — previously, some AJAX toggle handlers accepted GET parameters without token validation. Fixed.
- **SameSite and Secure session cookies are now a first-class preference.** Admins can set `SameSite=Lax|Strict|None` and toggle `Secure` from System → Preferences → General. Defaults lean secure-by-default.
- **`eval()` removed from core.** PHP blocks that used `eval()` to execute database-stored PHP have been retired. File-based PHP blocks still work. The Protector module's lifecycle files no longer use `eval()` either.
- **`unserialize()` audit.** Every call in core now uses `['allowed_classes' => false]`, blocking PHP object injection attacks.
- **`Protector` module hardened** — proper `exec()` override for `dblayertrap`, input validation on table prefixes (instead of silent rewriting), safe `badips` file handling, and failure-aware admin actions.
- **XSS sweep.** All SonarCloud-flagged reflected-user-data XSS paths have been escaped.
- **Open redirect and URL scheme whitelist.** The URL scheme check now decodes HTML entities before matching, checks only the scheme portion (colons in query strings are valid), and is whitelist-based (not blacklist).
- **Directory traversal.** Filename allowlists now call `basename()` *before* the character check, defeating encoded-path tricks.
- **Multibyte-aware validation.** Form length checks use `mb_strlen()` throughout — CJK, Arabic, and emoji no longer over-count.
- **Password comparisons use strict comparison** (`===`) and `hash_equals()` where appropriate.
- **Request precedence fixes.** `Request::getCmd()` case handling for custom block type codes, and the Elvis operator pitfall in `Request::getInt(...) ?: fallback` where `0` silently became the fallback — both fixed.

None of these are exotic zero-days. Each one is a small thing that had been living in the code for years. Added up, they make 2.7.0 materially safer than any 2.5.x release.

---

## Form and UI fixes you'll feel

- `XoopsFormTextDateSelect` now renders a genuinely empty field when the stored value is `0`, instead of silently showing today's date. Fixes a long-standing data-loss trap in edit forms.
- DHTML editor image width no longer defaults to `300px` when the user pastes a real width — a strict regex validates the input instead of a permissive `parseInt`.
- Module admin forms correctly render the required-field asterisk (`*`).
- Breadcrumbs and `xoAdminIcons` are now consistent across every system admin page.
- PM (Private Messaging) recipient pickers filter by module access permission — you can no longer message a user who has no access to PM.
- PM delete confirmation UX improved, with centred popups and xBootstrap5 templates.

---

## Developers: what changed under the hood

### Libraries, inlined

The external `xoops/base-requires25` metapackage is **gone**. All dependencies are now listed directly in `htdocs/xoops_lib/composer.dist.json`. No more indirection, no more surprise constraint bumps from a package you didn't know you depended on.

### Updated libraries

| Library | Version                                          |
|---|--------------------------------------------------|
| Bootstrap | 5.3.8                                            |
| Font Awesome | 7.1.0                                            |
| Smarty | 4.5.5                                            |
| HTML Purifier | 4.19.0                                           |
| PhpMailer | 6.12.0                                           |
| jQuery UI | 1.14.1                                           |
| TinyMCE | 7.9.2 (new default) + 5.10.9 (legacy, retained) |
| tablesorter | 2.32.0                                           |
| jquery.form | 4.3.1                                            |
| jGrowl | 1.4.10                                           |

PhpMailer and HTML Purifier are now in `/xoops_lib/` rather than bundled loose in `class/`.

### Database layer modernisation

- `queryF()` is deprecated — use `exec()` for writes and DDL, and `query()` for `SELECT`s.
- `quoteString()` is deprecated — use `quote()`.
- `XoopsDatabase` declares `error()`, `errorno()`, and `query()` as abstract methods.
- `Criteria` IN clauses now take arrays safely instead of string-concatenated `implode(',', $ids)`.
- All fetch calls now require the two-part `isResultSet()` + `instanceof \mysqli_result` guard for proper static analysis narrowing.

### Observability

`XoopsLogger` now supports a **composite logger pattern** — PSR-3 and Debugbar receive raw messages and format them from context, rather than being handed pre-formatted strings. This opens the door to structured logging in future releases.

### Legacy cleanup

- PSR-12 throughout: legacy `@package` / `@subpackage` / `@category` PHPDoc tags removed.
- The obsolete `pda.php` handler is deleted.
- Direct-access guards now use `http_response_code(404)` instead of a bare `exit('Restricted access')`.
- `$myts->htmlSpecialChars()` calls in modulesadmin replaced with the native `htmlspecialchars()`.
- Asset proxying via `browse.php` now serves source maps for JS and CSS files, fixing a long-standing devtools inconvenience.

### Tests and CI

- **PHPUnit 11** with the attribute syntax (`#[Test]`, `#[CoversClass]`) across the suite.
- **SonarCloud**, **Qodana**, **Scrutinizer**, and **CodeRabbit** all integrated into the PR workflow.
- The CI workflow now runs the real XOOPS test suite — the placeholder test that was silently passing for months is gone.

---

## RTL support

XOOPS 2.7.0 adds **right-to-left language support** at the core level, with initial contributions from @mambax7 and عبدالعزيز الجهني. Arabic, Hebrew, Persian, and Urdu sites now get proper directional rendering in admin and theme templates.

---

## System requirements

| | |
|---|---|
| **PHP** | >= 8.2.0 (8.4+ strongly recommended) |
| **MySQL / MariaDB** | MySQL >= 5.7.8 (8.4 LTS recommended) — or MariaDB >= 10.5 (10.11 LTS / 11.4 LTS recommended). Note: MySQL 5.7 reached end-of-life in October 2023 — upgrade to MySQL 8.0+ when you can. |
| **Web server** | Apache 2.4+ or nginx |

---

## Upgrading from 2.5.x

XOOPS 2.7.0 has a supported upgrade path from 2.5.11. The process:

1. **Back up your site files and database.** Always.
2. Enable debugging and turn your site off via *System Options → Preferences → General Settings*.
3. Copy `/upgrade/` into your XOOPS root directory.
4. Run `/upgrade/preflight.php` to scan for outdated themes and module templates that need attention before the main upgrade.
5. Address any flagged items, then run `preflight.php` again until it's clean.
6. Copy the new `htdocs/` contents over your web root.
7. Copy `htdocs/xoops_lib/` and `htdocs/xoops_data/` contents to your relocated/renamed directories as applicable.
8. Point your browser at `/upgrade/` and step through the prompts.
9. Update the **system**, **pm**, **profile**, and **protector** modules from System → Modules.
10. Turn your site back on.

The upgrade script handles: removing the obsolete HTMLPurifier and PhpMailer bundled locations, creating the new `tokens` table, widening `bannerclient.passwd` to fit modern hashes, and inserting the new `session_cookie_samesite` / `session_cookie_secure` preferences.

Sites older than 2.5.11 should upgrade to 2.5.11 first, then proceed.

---

## Install Protector

Once the upgrade is complete, **install or update the Protector module**. It adds intrusion detection, SQL inspection, and request logging on top of the core, and the 2.7.0 release includes material hardening to its internals.

---

## What changed since RC1

The RC1 → Final window was used for targeted hardening rather than new features. Notable fixes:

- **Profile avatar uploads** now use a handler-based save with rollback, null guards, and proper `strip_tags` casting (#47).
- **PM `readpmsg` save / delete flow** hardened against malformed input and edge-case state (#49).
- **PM admin** — tightened form handling and template output (#41).
- **System** — module update now preserves `mid` + `catid`; fixed an `exec()` / `isResultSet()` bug in `users.php` (#40); defensive fallback when `theme_set` config rows are missing (#44); safer theme-switch context handling (#33); System menu no longer auto-expands (#34); deprecated by-reference uploader-error pattern dropped (#43).
- **Mailer** constructor hardened for missing/incomplete config (#50).
- **Locale** — `number_format()` defaults now come from the active locale rather than hard-coded constants (#37); `money_format` intl branch aligned with fail-fast; cast `$number` to float only when it's a numeric string (#46).
- **Atomic write / cleanup helpers** — replaced silent `@unlink` / `@chmod` with helper-based cleanup; pinned the null-byte contract in tests (#52, #53).
- **Upgrade language files** — stripped a stray leading space before `<?php` in language index files that broke output buffering on some hosts (#51).
- **Protector** — tighter admin output and entry-point checks (#42).
- **TinyMCE 3.x** removed entirely due to unpatched security vulnerabilities; TinyMCE 7.9.x (default) and 5.10.x (legacy) remain.
- **CI / dev tooling** — Qodana removed and XML-RPC disabled by default (#39); `.githooks/` pre-commit + commit-msg sniffs added for documented antipatterns (#48).

For the complete list of fixes that landed between RC1 and Final, see [`docs/changelog.270.txt`](changelog.270.txt).

## Reporting issues

- **Bug reports:** [https://github.com/XOOPS/XoopsCore27/issues](https://github.com/XOOPS/XoopsCore27/issues)
- **Support forums:** [https://xoops.org/modules/newbb/](https://xoops.org/modules/newbb/)
- **Contributing:** [https://github.com/XOOPS/XoopsCore27/blob/master/CONTRIBUTING.md](https://github.com/XOOPS/XoopsCore27/blob/master/CONTRIBUTING.md)

If you're upgrading a production site, testing on a staging copy of your database first remains the single most valuable thing you can do — it's how we catch the edge cases the test matrix can't.

---

## Thank you

A release this size doesn't happen without contributors. Thank you to everyone who submitted pull requests, reported issues, tested beta packages, translated strings, reviewed security findings, and kept the conversation going on the forums and on GitHub through the long beta cycle.

Special thanks to new contributors since Beta 8: **@koreus**, **@CHCCD**, and **عبدالعزيز الجهني**.

A big Thank You to Gaurang Maheta for his help with security improvements.

And a standing thank-you to **[JetBrains](https://www.jetbrains.com/)** for the complimentary [PhpStorm](https://www.jetbrains.com/phpstorm/) licenses that power the core team's development.

For a complete list of changes, see [`docs/changelog.270.txt`](changelog.270.txt). For the language-constant diff, see [`docs/lang_diff.txt`](lang_diff.txt).

**Download XOOPS 2.7.0:** [https://github.com/XOOPS/XoopsCore27/releases](https://github.com/XOOPS/XoopsCore27/releases)

---

**The XOOPS Development Team**

May 2026
