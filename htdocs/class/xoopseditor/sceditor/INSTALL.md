# Installing SCEditor for XOOPS

This directory contains only the XOOPS-side integration for
[SCEditor](https://github.com/samclarke/SCEditor). The SCEditor library
itself is **not** included with XOOPS and must be installed manually. Until
it is, `FormSCEditor::isActive()` returns `false` and this editor simply does
not appear in the editor selection list — it is inert, not broken.

## What to download

Get a release of SCEditor from https://github.com/samclarke/SCEditor
(releases page or npm package `sceditor`). SCEditor is MIT licensed; its
license notice must be preserved in whatever file(s) you copy in below (the
upstream `LICENSE.md` / minified header comment).

## Where to put the files

From the release archive's `minified/` directory, copy:

| Source (in the SCEditor release)     | Destination in this directory   |
|---------------------------------------|----------------------------------|
| `minified/sceditor.min.js`            | `js/sceditor.min.js`             |
| `minified/themes/default.min.css`     | `css/sceditor.min.css`           |

Only `js/sceditor.min.js` is required for `isActive()` to return `true`
(together with `js/xoops-bbcode.js`, which already ships with XOOPS). The
CSS file is optional — `sceditor.php` includes it only if present.

Resulting layout:

```
class/xoopseditor/sceditor/
├── js/
│   ├── sceditor.min.js       <- you provide this (MIT licensed, from upstream)
│   └── xoops-bbcode.js       <- ships with XOOPS (this plugin's BBCode dialect)
├── css/
│   └── sceditor.min.css      <- you provide this (optional)
├── language/
│   └── english.php
├── sceditor.php
├── editor_registry.php
└── INSTALL.md
```

## After installing

No further configuration is required. Once both JS files above are
readable, `isActive()` returns `true` and "SCEditor (BBCode)" appears
in the editor preference dropdown alongside the other editors. It always
opens in BBCode source mode — this plugin never switches SCEditor into its
WYSIWYG mode, which is what keeps XOOPS-specific and unrecognised BBCode
(including arbitrary smilie text codes) from being rewritten or dropped.

## License note

SCEditor is © Sam Clarke and contributors, MIT licensed. The XOOPS
integration files in this directory (`sceditor.php`, `editor_registry.php`,
`language/english.php`, `js/xoops-bbcode.js`) are XOOPS project code under
the GNU GPL 2, same as the rest of this repository. Do not remove or alter
the MIT license notice bundled with the SCEditor distribution files you copy
into `js/` and `css/`.
