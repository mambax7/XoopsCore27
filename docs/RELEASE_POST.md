# XOOPS 2.7.1 Final — safer upgrades, stronger foundations

The XOOPS Development Team is pleased to announce **XOOPS 2.7.1 Final**. This
maintenance release builds on XOOPS 2.7.0 with another security-hardening pass,
more reliable upgrade tooling, form and theme improvements, and refreshed
dependencies for current PHP environments.

Download XOOPS 2.7.1:
**[https://github.com/XOOPS/XoopsCore27/releases](https://github.com/XOOPS/XoopsCore27/releases)**

---

## Highlights

### Security and upgrade hardening

- The upgrade wizard now requires administrator-group membership, and the
  apply-patch path requires a valid security token.
- Upgrade SQL dumps are stored outside the public web root and their download
  path is access-controlled.
- SQL identifier and `ORDER BY` handling was tightened across Criteria, kernel
  blocks, and system administration.
- Protector's database wrapper now inspects `queryF()` calls, language-file
  loading is constrained to safe basenames, and module-administration output is
  escaped more consistently.
- The tell-a-friend form now uses CAPTCHA and persistent per-IP rate limiting.

These changes continue the secure-by-default work begun in XOOPS 2.7.0 while
preserving compatibility with existing modules.

### Forms, templates, and themes

- `XoopsFormTabTray` provides a native tabbed form container with theme-aware
  renderers.
- `XoopsFormContainerInterface` formalises the contract shared by form
  containers.
- SmartyExtensions are registered across the supplied themes.
- xBootstrap5, xSwatch5, and xTailwind2 include dark-mode support.
- TinyMCE 7 language mapping now preserves locale casing such as `zh_TW` and
  resolves the correct language-pack filename.
- Cloned blocks retain their module binding and validated callback fields.

### Refreshed dependencies

The bundled dependency set has been refreshed, including php-debugbar 3.8.0,
Smarty 4.5.7, Webmozart Assert 2.4.1, and Symfony VarDumper and YAML 7.4.14.
XOOPS Helpers and TCPDF are now included directly, and SmartyExtensions was
updated for QR-code support.

---

## Debugbar is now a standalone module

The **Debugbar module is no longer bundled in the XOOPS Core download**. Moving
the module to its own repository lets it publish fixes and improvements without
waiting for a core release.

The underlying `php-debugbar/php-debugbar` library remains bundled in
`xoops_lib`, so users do not need to run a separate Composer installation.

To install Debugbar:

1. Download the current release from the
   [XOOPS Debugbar releases page](https://github.com/XoopsModules27x/debugbar/releases/latest).
2. Copy the included `debugbar` directory to `htdocs/modules/debugbar`.
3. Install the module from **System Admin → Modules**.

If Debugbar is already installed, replace its module files with the standalone
release and run **Update** in System Admin. Do not uninstall it first unless you
intentionally want to remove its saved settings and profiles.

An overlay upgrade does not delete the old `htdocs/modules/debugbar` directory.
Existing sites must replace those files explicitly so they do not continue
running the older copy that was previously bundled with core.

The core Monolog adapter now exposes `isActive()` and accepts a fourth
constructor argument for the default file handler's minimum log level, matching
the standalone module's integration contract. It also supports Monolog 2 and 3
level handling and bounds or redacts sensitive file-log context.

---

## Upgrading

### From XOOPS 2.7.0

1. Back up the database and site files.
2. Test the upgrade on a staging copy first.
3. Turn the site off from **System Options → Preferences → General Settings**.
4. Copy the new `htdocs/` files over the web root, including the updated
   `xoops_lib` and `xoops_data` contents for relocated directories.
5. Copy `/upgrade/` to the XOOPS root and run the upgrade wizard.
6. Update the **system**, **pm**, **profile**, and **protector** modules from
   **System Admin → Modules**.
7. Restore or update the standalone Debugbar module if the site uses it.
8. Turn the site back on and review the logs.

### From XOOPS 2.5.x

Sites older than XOOPS 2.5.11 should upgrade to 2.5.11 first. Run
`/upgrade/preflight.php` before copying the 2.7.1 files; it identifies Smarty 4
template incompatibilities and other items that need attention before the main
upgrade.

Full installation and upgrade documentation:
[https://xoops.github.io/xoops-docs/](https://xoops.github.io/xoops-docs/)

---

## System requirements

| | |
|---|---|
| **PHP** | >= 8.2.0; PHP 8.4 or 8.5 recommended |
| **MySQL / MariaDB** | MySQL >= 5.7.8 or MariaDB >= 10.5; a supported MySQL 8.x or MariaDB LTS release is recommended |
| **Web server** | Apache 2.4+ or nginx |

---

## Translations — 37 languages and counting

Beyond the English source, XOOPS is maintained in **37 community translations**
under the [XoopsLanguages](https://github.com/XoopsLanguages) organization.
Language packs are released independently; the current release page for every
language is listed in [`docs/TRANSLATIONS.md`](TRANSLATIONS.md).

XOOPS 2.7.1 adds one English language constant compared with 2.7.0:
`_AM_SYSTEM_BLOCKS_INVALID_CLONE`. Translators can find its exact definition in
[`docs/lang_diff.txt`](lang_diff.txt). No additional English constants changed
between 2.7.1-RC1 and Final.

Please help review existing translations, report mistakes, or add a missing
language. Every correction helps the whole community.

---

## What changed since 2.7.1-RC1

- The Debugbar module moved to its standalone distribution while its supporting
  PHP library remained in core.
- `XoopsMonologLogger` gained the standalone module's required activation and
  minimum-level APIs, with direct core tests.
- Module About pages now render the safe subset of HTML used in changelog text.
- The SonarQube scan action was updated from 8.2.0 to 8.2.1.

For the complete release history, see
[`docs/changelog.270.txt`](changelog.270.txt).

## Reporting issues

- **Bug reports:** [https://github.com/XOOPS/XoopsCore27/issues](https://github.com/XOOPS/XoopsCore27/issues)
- **Support forums:** [https://xoops.org/modules/newbb/](https://xoops.org/modules/newbb/)
- **Contributing:** [https://github.com/XOOPS/XoopsCore27/blob/master/CONTRIBUTING.md](https://github.com/XOOPS/XoopsCore27/blob/master/CONTRIBUTING.md)

---

## Thank you

Thank you to everyone who submitted pull requests, reported issues, tested the
Beta and RC packages, translated strings, reviewed security findings, and helped
other XOOPS users.

A special thank-you to the maintainers of the XOOPS modules, language packs, XMF,
RegDom, and the wider dependency ecosystem. 

We also thank [JetBrains](https://www.jetbrains.com/) for supporting the project with
[PhpStorm](https://www.jetbrains.com/phpstorm/) licenses.

**Download XOOPS 2.7.1:**
[https://github.com/XOOPS/XoopsCore27/releases](https://github.com/XOOPS/XoopsCore27/releases)

---

**The XOOPS Development Team**

July 2026
