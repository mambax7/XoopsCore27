XOOPS 2.7.2 FINAL RELEASE

The XOOPS Development Team is pleased to announce the release of XOOPS 2.7.2.
This patch release repairs the installer so fresh installations and
re-installations complete reliably across the supported PHP 8.2 through PHP 8.5
range.

Download XOOPS 2.7.2 from GitHub: https://github.com/XOOPS/XoopsCore27/releases

For full documentation on installing or upgrading XOOPS please see:
https://xoops.github.io/xoops-docs/

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
XOOPS 2.7.2 is available in 37 community translations, maintained at:
https://github.com/XoopsLanguages

See docs/TRANSLATIONS.md for the full list of languages and the current release
page for each language pack. Language packs are published independently, so
check each release page for its declared XOOPS compatibility.

XOOPS 2.7.2 adds no new English language constants. Translators coming directly
from XOOPS 2.7.0 still have the single 2.7.1 addition to apply — see
docs/lang_diff.txt for its exact definition.

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
A release this size doesn't happen without contributors. Thank you to everyone who submitted pull requests, reported issues, tested beta packages, translated strings, reviewed security findings, and kept the conversation going on the forums and on GitHub through the long beta cycle.

* And a standing THANK-YOU to **[JetBrains](https://www.jetbrains.com/)** for the complimentary [PhpStorm](https://www.jetbrains.com/phpstorm/) licenses that power the core team's development.


XOOPS Development Team
July 2026
