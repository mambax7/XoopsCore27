# SCEditor for XOOPS

This directory contains the XOOPS integration for
[SCEditor](https://github.com/samclarke/SCEditor) **and** a bundled copy of
the SCEditor distribution itself, so no separate download or installation is
required. Once the files listed below are present (as shipped), "SCEditor
(BBCode)" appears in the editor preference dropdown alongside the other
editors.

## Directory layout

```
class/xoopseditor/sceditor/
├── minified/                     <- SCEditor distribution (MIT licensed, upstream
│   ├── sceditor.min.js              release layout — do not edit these files)
│   ├── formats/
│   │   ├── bbcode.js
│   │   └── xhtml.js
│   ├── themes/
│   │   ├── default.min.css
│   │   ├── content/default.min.css
│   │   └── ...
│   ├── icons/
│   └── plugins/
├── js/
│   └── xoops-bbcode.js           <- XOOPS code: the XOOPS BBCode dialect
├── language/
│   └── english.php
├── sceditor.php                  <- XOOPS code: the XoopsEditor plugin
├── editor_registry.php
└── INSTALL.md
```

## What the plugin actually loads

`FormSCEditor::isActive()` requires all three of these to be readable, so a
half-removed library never renders a broken editor — the editor is simply
absent from the selection list:

- `minified/sceditor.min.js` — SCEditor core
- `minified/formats/bbcode.js` — SCEditor's stock BBCode format
- `js/xoops-bbcode.js` — the XOOPS dialect layered on top

`render()` additionally links `minified/themes/default.min.css` for the
toolbar and `minified/themes/content/default.min.css` for the editing area.

The editor always starts and stays in BBCode source mode
(`startInSourceMode`) — this plugin never switches SCEditor into its WYSIWYG
mode, which is what keeps XOOPS-specific and unrecognised BBCode (including
arbitrary smilie text codes) from being rewritten or dropped.

## Upgrading the bundled library

To move to a newer SCEditor release, replace the contents of `minified/`
with the `minified/` directory from the upstream release archive
(https://github.com/samclarke/SCEditor releases, or the npm package
`sceditor`). Do not modify the files in `minified/`; XOOPS-specific
behaviour lives entirely in `js/xoops-bbcode.js` and `sceditor.php`, and
customisations placed inside `minified/` would be lost on the next upgrade.

## License note

SCEditor is © Sam Clarke and contributors, MIT licensed; the bundled files
under `minified/` retain their upstream license headers — do not remove or
alter them. The XOOPS integration files in this directory (`sceditor.php`,
`editor_registry.php`, `language/english.php`, `js/xoops-bbcode.js`) are
XOOPS project code under the GNU GPL 2, same as the rest of this repository.
