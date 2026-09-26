XOOPS 2.7.4 RC 1

The XOOPS Development Team is pleased to announce XOOPS 2.7.4 RC 1, the release
candidate for XOOPS 2.7.4. This release brings two-factor authentication into
the core, makes SCEditor a full visual editor and the default for new sites,
adds Markdown support through EasyMDE, and continues the security hardening of
the 2.7 line. XOOPS 2.7.4 runs on PHP 8.2 through 8.5.

This is a release candidate: the feature set is final. Please test it on a
staging copy of your site and report anything you find before the final release.

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

Download XOOPS 2.7.4 RC 1 from GitHub: https://github.com/XOOPS/XoopsCore27/releases

For full documentation on installing or upgrading XOOPS please see:
https://xoops.github.io/xoops-docs/

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
September 2026
