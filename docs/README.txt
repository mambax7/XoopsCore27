XOOPS 2.7.4 RC 2

The XOOPS Development Team is pleased to announce XOOPS 2.7.4 RC 2, the
second release candidate for XOOPS 2.7.4. This release brings two-factor
authentication into the core, makes SCEditor a full visual editor and the
default for new sites, adds Markdown support through EasyMDE, and continues the
security hardening of the 2.7 line. XOOPS 2.7.4 runs on PHP 8.2 through 8.5.

This is a release candidate: the feature set is final. RC 2 adds no features;
it collects the security and bug fixes made since RC 1 (see "Changes since
RC 1" below). Please test it on a staging copy of your site and report anything
you find before the final release.

Two-factor authentication: members can protect their account with a second
step at login, using an authenticator app or a six-digit code sent by e-mail.
Each member gets one-time recovery codes, wrong codes lock the second step for
fifteen minutes, and administrators can reset a member's factor. It is off by
default; set it to "Optional" in System Preferences to let members enrol.

Editors: SCEditor now edits visually with its full toolbar while keeping XOOPS
BBCode intact, its emoticons are registered as XOOPS smileys, and the new
System > Preferences > Editors page configures it. EasyMDE stores Markdown in
the existing text columns and XOOPS renders it in safe mode. The bundled
TinyMCE 7 is updated to 7.9.3.

Security work in this cycle includes remember-me tokens that are revoked when
the password changes, sessions that end for a deactivated account, group rights
read from the database on every request, stricter checks on registration,
comment editing and the TinyMCE image manager, a webmaster-only upgrade wizard,
and LDAP connections that refuse to continue without the TLS they asked for.

Download XOOPS 2.7.4 RC 2 from GitHub: https://github.com/XOOPS/XoopsCore27/releases

For full documentation on installing or upgrading XOOPS please see:
https://xoops.github.io/xoops-docs/

Changes since RC 1
-----------------------------------
- CSRF tokens are random values, no longer tied to the browser's User-Agent,
  so a browser update or a "desktop site" toggle no longer rejects open forms.
  Protector's admin pages use the same core token instead of their own GTicket.
- Login, post-login and theme-switch redirects share one same-site check.
- Confirmation pages, object and login error lists, and the admin news-feed
  error are escaped; image, smiley, rank and avatar files are deleted only
  inside the upload directory.
- Cache ids are keyed with a site key in xoops_data/data instead of the
  database credentials; the installer uses random_bytes() for its rename
  suffix and cleanup-script name.
- Database dumps are written only under XOOPS_VAR_PATH into a protected
  directory; reCAPTCHA v2 verifies over POST; xoops_lib/.htaccess works on
  Apache 2.4 without mod_access_compat.
- The bundled XMF library is updated to 1.3.2, which tightens the filtering
  of request input.
- Protector writes its ban files atomically and rejects uploads it cannot
  inspect. See docs/changelog.270.txt for the full list.

Upgrading from 2.7.4 RC 1
-----------------------------------
Copy the new files over the web root. There are no database changes since
RC 1; running the upgrade wizard is harmless but not required. Forms opened
before the upgrade still submit; a Protector admin form opened before the
upgrade must be reloaded once. xoops_data/data must stay writable: the new
cache-id key is created there on first use.

Upgrading from 2.7.3
-----------------------------------
Copy the new files over the web root and run the upgrade wizard. It creates the
user_2fa table and the two-factor preference, registers the SCEditor emoticons
as smileys, and adds the Editors preferences. No mainfile.php changes are needed.

- Two-factor authentication needs the PHP sodium extension; the wizard reports
  whether it is available. Operations notes, including key backup and the
  locked-out administrator procedure, are in docs/2fa-operations.md.
- Every remembered device logs in once more after the upgrade: remember-me
  tokens now carry a fingerprint of the password, and older tokens have none.
- An upgraded site keeps its current editor settings; SCEditor becomes the
  default editor only on a fresh install. To get SCEditor's MP3 button, set
  'mp3' => 1 in xoops_data/configs/textsanitizer/config.php.
- If you copied extras/login.php (the SSL popup login) somewhere on your site,
  replace or remove that copy: updating the core does not patch it.
- A site running a custom template set should re-import it, as for any core
  template change.

Debugbar module
-----------------------------------
The Debugbar module is not included in the XOOPS Core download. The
php-debugbar/php-debugbar library remains bundled in xoops_lib so the standalone
module works without a separate Composer installation.

Download the current Debugbar module release from:
https://github.com/XoopsModules27x/debugbar/releases/latest

Copy the included debugbar directory to htdocs/modules/debugbar, then install or
update it from System Admin -> Modules. Existing Debugbar users should replace
the module files and run Update; uninstalling first is not required.

Languages
-----------------------------------
XOOPS is available in 37 community translations, maintained at:
https://github.com/XoopsLanguages

See docs/TRANSLATIONS.md for the full list of languages and the current release
page for each language pack. Language packs are published independently, so
check each release page for its declared XOOPS compatibility.

XOOPS 2.7.4 adds new English language files and constants for two-factor
authentication, the Editors preferences, the SCEditor toolbar and the upgrade
wizard. The front-end labels of the default system menu moved to their own file,
and one profile constant was renamed (the old name is still read as a fallback).
See docs/lang_diff.txt for the exact definitions.

Help wanted: please help us find and fix translation errors, and help us add
and review more languages. Every correction makes XOOPS better worldwide.

How to contribute
-----------------------------------
Bug reports and feature requests: https://github.com/XOOPS/XoopsCore27/issues
Patch and enhancement: https://github.com/XOOPS/XoopsCore27/blob/master/CONTRIBUTING.md
Documentation: https://xoops.github.io/xoops-docs/
Support Forums: https://xoops.org/modules/newbb/

Thank you
-----------------------------------
Thank you to everyone who submitted pull requests, reported issues, tested the
beta packages, translated strings, reviewed security findings, and kept the
conversation going on the forums and on GitHub throughout the 2.7.4 cycle.

* And a standing THANK-YOU to **[JetBrains](https://www.jetbrains.com/)** for the complimentary [PhpStorm](https://www.jetbrains.com/phpstorm/) licenses that power the core team's development.


XOOPS Development Team
October 2026
