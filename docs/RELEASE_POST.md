# XOOPS 2.7.2 Final — installer fixes

The XOOPS Development Team is pleased to announce **XOOPS 2.7.2 Final**. This is
a focused patch release that repairs the installer so fresh installations and
re-installations complete reliably on current PHP versions. It contains no
database, template, or API changes.

Existing XOOPS 2.7.1 sites do not need to upgrade unless they intend to run the
installer again; 2.7.2 changes only the `install/` wizard and the version
string.

Download XOOPS 2.7.2:
**[https://github.com/XOOPS/XoopsCore27/releases](https://github.com/XOOPS/XoopsCore27/releases)**

---

## Why 2.7.2

Shortly after 2.7.1 Final, a fresh install on PHP 8.2 was reported to fail
during the "Creating tables" step with a fatal `mysqli_sql_exception`
([#126](https://github.com/XOOPS/XoopsCore27/issues/126)). Investigating it
surfaced a small cluster of related installer problems affecting fresh installs,
re-installs, and the front page of a newly installed site. 2.7.2 fixes all of
them.

---

## What's fixed

### Fresh install no longer fatals on PHP 8.2+

`install_isInstalled()` probed for the users table before it existed. Since PHP
8.1, mysqli defaults to exception mode — and XOOPS runs on PHP 8.2 and later —
where the `@` silence operator does **not** suppress those exceptions, so the
probe threw a fatal `mysqli_sql_exception` ("Table '…_users' doesn't exist") on
every fresh install. The probe is now guarded so that only a confirmed missing
table means "not installed", and any other database error keeps the installer
locked.

### The wizard no longer locks itself out mid-install

The site-configuration, theme, and module-installation pages boot the full XOOPS
environment, which swaps the installer's session for XOOPS's own session store.
That hid the in-progress installation flags and caused the "This site is already
installed" lock to fire in the middle of a legitimate install. The lock now also
recognises the authenticated in-progress administrator — already established on
those pages via a signed one-time token — so the wizard runs to completion.

### Default theme is a shipped theme

A new install set its default theme to `xswatch4`, which is not shipped, so the
front page failed with "Theme not found". The default is now `xbootstrap5`.

### Re-installing over an existing site works

- `license.php`, left read-only by a previous install, is made writable again
  before it is rewritten, instead of failing with "Make … Writable".
- A stale one-time install cookie from an earlier attempt is cleared and
  re-authenticated, instead of aborting the wizard with "Init Error".
- Per-attempt installer key files are cleaned up at the start of a run so they
  no longer accumulate.

### Cleaner install log

The initial-settings page ran its "does an administrator already exist?" check
on a connection that had not selected the database, logging a spurious
"No database selected" error. It now uses the database-aware query path.

---

## Upgrading

### From XOOPS 2.7.1

2.7.2 has no database or template changes and does not require the upgrade
wizard. To move an existing 2.7.1 site to 2.7.2, copy the new `htdocs/` files
over the web root. There is nothing else to do.

### Installing fresh or from older versions

Follow the standard installation and upgrade guidance:
[https://xoops.github.io/xoops-docs/](https://xoops.github.io/xoops-docs/)

---

## System requirements

| | |
|---|---|
| **PHP** | >= 8.2.0; PHP 8.4 or 8.5 recommended |
| **MySQL / MariaDB** | MySQL >= 5.7.8 or MariaDB >= 10.5; a supported MySQL 8.x or MariaDB LTS release is recommended |
| **Web server** | Apache 2.4+ or nginx |

---

## Translations

XOOPS 2.7.2 introduces no new or changed English language constants, so no
translation updates are required. XOOPS remains maintained in **37 community
translations** under the [XoopsLanguages](https://github.com/XoopsLanguages)
organization; see [`docs/TRANSLATIONS.md`](TRANSLATIONS.md) for the current
release page of each language.

---

## Reporting issues

- **Bug reports:** [https://github.com/XOOPS/XoopsCore27/issues](https://github.com/XOOPS/XoopsCore27/issues)
- **Support forums:** [https://xoops.org/modules/newbb/](https://xoops.org/modules/newbb/)
- **Contributing:** [https://github.com/XOOPS/XoopsCore27/blob/master/CONTRIBUTING.md](https://github.com/XOOPS/XoopsCore27/blob/master/CONTRIBUTING.md)

---

## Thank you

Thank you to everyone who reported the installation problems, tested the fixes,
and helped confirm the release. Bug reports like
[#126](https://github.com/XOOPS/XoopsCore27/issues/126) make XOOPS better for
everyone.

We also thank [JetBrains](https://www.jetbrains.com/) for supporting the project
with [PhpStorm](https://www.jetbrains.com/phpstorm/) licenses.

**Download XOOPS 2.7.2:**
[https://github.com/XOOPS/XoopsCore27/releases](https://github.com/XOOPS/XoopsCore27/releases)

---

**The XOOPS Development Team**

July 2026
