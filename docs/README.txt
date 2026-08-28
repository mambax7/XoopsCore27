XOOPS 2.7.3 FINAL RELEASE

The XOOPS Development Team is pleased to announce the release of XOOPS 2.7.3.
This release hardens security across the core, prepares XOOPS for PHP 8.6 while
remaining fully supported on PHP 8.2 through 8.5, adds SCEditor as an optional
BBCode editor, and introduces file-based debug configuration with a rotating,
redacting file logger. It also carries a series of reliability fixes proven in
production on xoops.org — search, comments, caching, and database error
handling among them.

Security work in this cycle includes escaping in all five form renderers, a
shared PathGuard path-containment class behind the template-set browser and
editor, a completed session save-handler contract, a CSRF-protected logout,
filtered redirect query strings, and the removal of image.php's never-functional
remote-image branch.

Download XOOPS 2.7.3 from GitHub: https://github.com/XOOPS/XoopsCore27/releases

For full documentation on installing or upgrading XOOPS please see:
https://xoops.github.io/xoops-docs/

Upgrading from 2.7.2
-----------------------------------
XOOPS 2.7.3 includes schema changes (among them a comments index that took a
listing query from 541ms to 0.5ms), so after copying the new files over the web
root, run the upgrade wizard. Existing sites need no mainfile.php changes: the
new debug configuration, error screen, and developer gate all read
xoops_data/data/debug.php directly.

Debugbar module
-----------------------------------
The Debugbar module is no longer included in the XOOPS Core download. The
php-debugbar/php-debugbar library remains bundled in xoops_lib so the standalone
module works without a separate Composer installation.

Download the current Debugbar module release from:
https://github.com/XoopsModules27x/debugbar/releases/latest

Copy the included debugbar directory to htdocs/modules/debugbar, then install or
update it from System Admin -> Modules. Existing Debugbar users should replace
the module files and run Update; uninstalling first is not required.

Languages
-----------------------------------
XOOPS 2.7.3 is available in 37 community translations, maintained at:
https://github.com/XoopsLanguages

See docs/TRANSLATIONS.md for the full list of languages and the current release
page for each language pack. Language packs are published independently, so
check each release page for its declared XOOPS compatibility.

XOOPS 2.7.3 adds new English language constants: the SCEditor editor strings
introduced in RC 1 and one logout-confirmation string added in Final. See
docs/lang_diff.txt for their exact definitions.

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
A release this size doesn't happen without contributors. Thank you to everyone
who submitted pull requests, reported issues, tested the beta and RC packages,
translated strings, reviewed security findings, and kept the conversation going
on the forums and on GitHub throughout the 2.7.3 cycle.

A special thank-you to CHCCD for testing the release candidates and reporting
the search and browse bugs fixed in this release (issues #161, #162, #163):
the "Show all" search results that could never render, the search.php fatal
errors from unvalidated module ids and the module_read bypass, and the
malformed browse.php Cache-Control header.

* And a standing THANK-YOU to **[JetBrains](https://www.jetbrains.com/)** for the complimentary [PhpStorm](https://www.jetbrains.com/phpstorm/) licenses that power the core team's development.


XOOPS Development Team
August 2026
