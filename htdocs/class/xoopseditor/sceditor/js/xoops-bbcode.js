/**
 * XOOPS BBCode dialect for SCEditor
 *
 * This file is XOOPS-authored integration glue (GNU GPL 2, matching the rest of
 * this repository) — it is NOT part of the SCEditor library itself (MIT licensed,
 * bundled under ../minified/, see INSTALL.md in this directory). It teaches
 * SCEditor (https://github.com/samclarke/SCEditor) the exact BBCode dialect that
 * MyTextSanitizer::xoopsCodeDecode() and the class/textsanitizer/* extensions
 * decode on the server (htdocs/class/module.textsanitizer.php:398-474 and
 * htdocs/class/textsanitizer/{image,youtube,ul,li,wiki,iframe,mp3,soundcloud,
 * mms,rtsp,wmp}/*.php).
 *
 * IMPORTANT — this plugin only ever runs SCEditor in permanent BBCode SOURCE
 * mode (see ../sceditor.php render()). That is deliberate and is the actual
 * mechanism that keeps unrecognised BBCode intact:
 *
 *   SCEditor only rewrites text when it converts between its BBCode source and
 *   its WYSIWYG HTML representation (using the `format`/`html`/`tags`
 *   definitions below). In source mode that conversion never runs — the
 *   textarea's value is the literal BBCode the user is editing, untouched. A
 *   toolbar button in source mode only inserts/wraps text at the caret via
 *   insertText(); it never re-serialises the rest of the document. So a tag
 *   this file does not know about (`[siteurl]` chained oddly, `[img id=]`,
 *   `[[Wiki]]`, or — the case that matters most — an arbitrary smilie text
 *   code from the `smiles` DB table such as `:wink:` or a custom multi-word
 *   code) is never parsed, never matched against a format definition, and
 *   therefore can never be dropped.
 *
 *   SCEditor has NO separate "pass unknown BBCode through verbatim" switch to
 *   turn on — there is nothing to configure for that. The safety instead comes
 *   entirely from never leaving source mode (enforced in sceditor.php), so the
 *   format/html functions below are effectively inert in normal operation.
 *   They are still declared correctly (best-effort) below for API completeness
 *   and so a future maintainer who adds a preview/WYSIWYG toggle does not
 *   inherit a silently-wrong conversion table.
 */
(function () {
    'use strict';

    if (typeof sceditor === 'undefined' || !sceditor.formats || !sceditor.formats.bbcode) {
        // Library not loaded (or too old to expose the bbcode format registry).
        // sceditor.php already guards its init call the same way; bail quietly.
        return;
    }

    var bbcode = sceditor.formats.bbcode;
    var QuoteType = (sceditor.BBCodeParser && sceditor.BBCodeParser.QuoteType) || {};

    /**
     * Escape helpers exposed by the SCEditor core (lib/escape.js), used so the
     * html() definitions below behave like the stock format's: attribute values
     * are entity-escaped and URI values are scheme-checked. Fallbacks keep this
     * file loadable against a stripped build that omits the exports.
     */
    var escapeEntities = sceditor.escapeEntities || function (str) {
        return String(str).replace(/[&<>"'`]/g, function (ch) {
            return '&#' + ch.charCodeAt(0) + ';';
        });
    };
    var escapeUriScheme = sceditor.escapeUriScheme || function (str) {
        return str;
    };

    /**
     * Named XOOPS [size=] values, in smallest-to-largest order.
     * Source: htdocs/language/english/formdhtmltextarea.php:39-47 ($GLOBALS['formtextdhtml_sizes']).
     * XOOPS does NOT use SCEditor's default numeric 1-7 scale for [size=]; using
     * numeric sizes here would silently corrupt every existing post's [size=medium]
     * etc. into a value MyTextSanitizer does not decode.
     */
    var XOOPS_SIZES = ['xx-small', 'x-small', 'small', 'medium', 'large', 'x-large', 'xx-large'];

    /**
     * Map a WYSIWYG CSS font-size keyword/length back to the nearest named XOOPS
     * size. Best-effort only — see file header, this path is not exercised while
     * the editor stays in source mode.
     */
    function nearestXoopsSize(cssSize) {
        var i = XOOPS_SIZES.indexOf(String(cssSize).toLowerCase());
        return i !== -1 ? XOOPS_SIZES[i] : 'medium';
    }

    // ------------------------------------------------------------------
    // Core tags — module.textsanitizer.php:419-424 (xoopsCodeDecode()).
    // NOTE on registry keys: bbcode.set()'s first argument is the literal
    // BBCode tag name as it appears between the brackets ([b], [url], ...),
    // matching the keys the stock formats/bbcode.js registers — NOT the
    // toolbar command name ('bold', 'link', ...). A wrong key would register
    // a new bogus tag instead of overriding the real one.
    // [b] [i] [u] already match SCEditor's own defaults; declared explicitly
    // anyway so this file is the single source of truth for the XOOPS dialect
    // rather than relying on upstream defaults not changing.
    // ------------------------------------------------------------------
    bbcode.set('b', {
        tags: { b: null, strong: null },
        format: '[b]{0}[/b]',
        html: '<strong>{0}</strong>'
    });
    bbcode.set('i', {
        tags: { i: null, em: null },
        format: '[i]{0}[/i]',
        html: '<em>{0}</em>'
    });
    bbcode.set('u', {
        tags: { u: null },
        format: '[u]{0}[/u]',
        html: '<span style="text-decoration: underline;">{0}</span>'
    });

    // --- Explicit override #1: strikethrough --------------------------
    // module.textsanitizer.php:425-426 — XOOPS uses [d]...[/d], NOT SCEditor's
    // default [s]...[/s]. Registered under the 'd' tag name so any conversion
    // path recognises existing [d] content; the HTML elements (del/s/strike)
    // are claimed here after the stock file loads, so they serialise to [d],
    // not to the [s] tag MyTextSanitizer never decodes.
    bbcode.set('d', {
        tags: { del: null, s: null, strike: null },
        format: '[d]{0}[/d]',
        html: '<del>{0}</del>'
    });

    // --- Alignment: [center] [left] [right], not [align=] --------------
    // module.textsanitizer.php:427-432. Registered explicitly (rather than
    // relying on whatever SCEditor's own default alignment dialect happens to
    // be) so this is correct regardless of upstream default drift.
    bbcode.set('left', {
        tags: { div: { style: { 'text-align': ['left'] } } },
        format: '[left]{0}[/left]',
        html: '<div style="text-align: left;">{0}</div>'
    });
    bbcode.set('center', {
        tags: { div: { style: { 'text-align': ['center'] } } },
        format: '[center]{0}[/center]',
        html: '<div style="text-align: center;">{0}</div>'
    });
    bbcode.set('right', {
        tags: { div: { style: { 'text-align': ['right'] } } },
        format: '[right]{0}[/right]',
        html: '<div style="text-align: right;">{0}</div>'
    });

    // --- [url=...]...[/url] --------------------------------------------
    // module.textsanitizer.php:404-409. Matches SCEditor's own default dialect
    // (registered upstream under the 'url' tag name); declared explicitly for
    // completeness.
    bbcode.set('url', {
        tags: { a: { href: null } },
        quoteType: QuoteType.always,
        format: function (element, content) {
            var href = element.getAttribute ? element.getAttribute('href') : '';
            return '[url=' + href + ']' + content + '[/url]';
        },
        html: function (token, attrs, content) {
            var href = (attrs && attrs.defaultattr) || '';
            // Match the stock format's handling: scheme-check then entity-escape,
            // so a [url=javascript:...] can never become a live link if the
            // conversion path ever runs.
            return '<a href="' + escapeEntities(escapeUriScheme(href)) + '">' + content + '</a>';
        }
    });

    // --- [siteurl=...]...[/siteurl] — XOOPS-specific, no SCEditor default ---
    // module.textsanitizer.php:402-403. html() stores the path on data-siteurl
    // (the attribute format() reads back) so the tag round-trips without losing
    // its target.
    bbcode.set('siteurl', {
        quoteType: QuoteType.always,
        format: function (element, content) {
            return '[siteurl=' + (element.getAttribute('data-siteurl') || '') + ']' + content + '[/siteurl]';
        },
        html: function (token, attrs, content) {
            var path = escapeEntities((attrs && attrs.defaultattr) || '');
            return '<a data-siteurl="' + path + '" href="' + path + '">' + content + '</a>';
        }
    });

    // --- [email]address[/email] — bare, address IS the content ---------
    // module.textsanitizer.php:416-417 — no `=address` attribute form exists
    // server-side, so the default SCEditor `[email=addr]label[/email]` shape
    // (if that is what upstream ships) must not be used here.
    bbcode.set('email', {
        tags: { a: { href: /^mailto:/ } },
        format: '[email]{0}[/email]',
        html: '<a href="mailto:{0}">{0}</a>'
    });

    // --- [quote]...[/quote] — bare, recursive, no author attribute -----
    // module.textsanitizer.php:460-474 (quoteConv()) — XOOPS quote has no
    // author/date attribute and is decoded left-to-right, which already makes
    // nesting safe; no special handling beyond the bare tag is required.
    bbcode.set('quote', {
        tags: { blockquote: null },
        format: '[quote]{0}[/quote]',
        html: '<blockquote>{0}</blockquote>'
    });

    // --- [code] and [code=lang] -----------------------------------------
    // module.textsanitizer.php:685 codePreConv(): /\[code([^\]]*?)\](.*)\[\/code\]/sU
    bbcode.set('code', {
        tags: { code: null },
        isInline: false,
        format: function (element, content) {
            var lang = element.getAttribute ? element.getAttribute('data-lang') : '';
            return '[code' + (lang ? '=' + lang : '') + ']' + content + '[/code]';
        },
        html: function (token, attrs, content) {
            return '<code>' + content + '</code>';
        }
    });

    // --- [font=Name]...[/font] ------------------------------------------
    // module.textsanitizer.php:414-415. Matches SCEditor's own default dialect.
    bbcode.set('font', {
        // Both shapes are claimed so format() sees the <span> its own html()
        // emits (styles:) as well as legacy <font face=> markup (tags:) —
        // without the styles claim the tag would not round-trip.
        tags: { font: { face: null } },
        styles: { 'font-family': null },
        quoteType: QuoteType.always,
        format: function (element, content) {
            var face = (element.getAttribute && element.getAttribute('face'))
                || (element.style && element.style.fontFamily)
                || '';
            return '[font=' + face + ']' + content + '[/font]';
        },
        html: function (token, attrs, content) {
            var face = (attrs && attrs.defaultattr) || '';
            return '<span style="font-family: ' + escapeEntities(face) + ';">' + content + '</span>';
        }
    });

    // --- [color=hex|name]...[/color] -------------------------------------
    // module.textsanitizer.php:410-411. Matches SCEditor's own default dialect.
    bbcode.set('color', {
        // Both shapes claimed for the same round-trip reason as 'font' above.
        tags: { font: { color: null } },
        styles: { color: null },
        quoteType: QuoteType.always,
        format: function (element, content) {
            var color = (element.getAttribute && element.getAttribute('color'))
                || (element.style && element.style.color)
                || '';
            return '[color=' + color + ']' + content + '[/color]';
        },
        html: function (token, attrs, content) {
            var color = (attrs && attrs.defaultattr) || '';
            return '<span style="color: ' + escapeEntities(color) + ';">' + content + '</span>';
        }
    });

    // --- Explicit override #2: [size=named], not [size=1-7] --------------
    // module.textsanitizer.php:412-413; named list from
    // htdocs/language/english/formdhtmltextarea.php:39-47. This is the other
    // tag that silently corrupts existing posts if the numeric SCEditor
    // default is used instead.
    bbcode.set('size', {
        // The styles claim is what lets format() ever run: without it no HTML
        // element maps back to [size=] and the tag would not round-trip.
        styles: { 'font-size': null },
        quoteType: QuoteType.always,
        format: function (element, content) {
            var size = element.style ? element.style.fontSize : '';
            return '[size=' + nearestXoopsSize(size) + ']' + content + '[/size]';
        },
        html: function (token, attrs, content) {
            var size = (attrs && attrs.defaultattr) || 'medium';
            return '<span style="font-size: ' + escapeEntities(size) + ';">' + content + '</span>';
        }
    });

    // ------------------------------------------------------------------
    // Extension tags — htdocs/class/textsanitizer/*/*.php
    // ------------------------------------------------------------------

    // [img], [img width=], [img align=], [img align= width=], [img id=],
    // [img align= id=] — class/textsanitizer/image/image.php:38-44.
    // One format definition covers the bare/width/align cases (id= variant is
    // a distinct, non-URL form used by the image manager and is only produced
    // by the image-manager picker, not by hand-wrapping selected text).
    bbcode.set('img', {
        tags: { img: { src: null } },
        allowsEmpty: true,
        quoteType: QuoteType.always,
        format: function (element, content) {
            var src = element.getAttribute ? element.getAttribute('src') : '';
            var width = element.getAttribute ? element.getAttribute('width') : '';
            var align = element.style ? element.style.float : '';
            var attrs = '';
            if (align) {
                attrs += ' align=' + align;
            }
            if (width) {
                attrs += ' width=' + width;
            }
            return '[img' + attrs + ']' + src + '[/img]';
        },
        html: function (token, attrs, content) {
            return '<img src="' + escapeEntities(escapeUriScheme(content)) + '" alt="" />';
        }
    });

    // [youtube=WIDTHxHEIGHT_OR_W,H]videoIdOrUrl[/youtube] — class/textsanitizer/youtube/youtube.php:77.
    bbcode.set('youtube', {
        quoteType: QuoteType.always,
        format: function (element, content) {
            var width = element.getAttribute ? element.getAttribute('data-width') : '';
            var height = element.getAttribute ? element.getAttribute('data-height') : '';
            return '[youtube=' + (width || '') + ',' + (height || '') + ']' + content + '[/youtube]';
        },
        html: function (token, attrs, content) {
            return '<a href="https://www.youtube.com/watch?v=' + escapeEntities(content) + '">' + content + '</a>';
        }
    });

    // [ul]...[/ul] / [li]...[/li] — class/textsanitizer/ul/ul.php, li/li.php.
    // Deliberately distinct from SCEditor's default 'bulletlist' (<ul><li>
    // auto-generated per line with no matching XOOPS closing tags); XOOPS
    // treats [ul] and [li] as two independent, hand-nested tags.
    bbcode.set('ul', {
        tags: { ul: null },
        format: '[ul]{0}[/ul]',
        html: '<ul>{0}</ul>'
    });
    bbcode.set('li', {
        tags: { li: null },
        format: '[li]{0}[/li]',
        html: '<li>{0}</li>'
    });

    // [[WikiPage]] — class/textsanitizer/wiki/wiki.php:84. NOT registered with
    // bbcode.set(): the registry keys are literal tag names, and the wiki
    // syntax has no tag name — [[...]] uses doubled brackets as both open and
    // close delimiter, which SCEditor's BBCode grammar cannot express. A
    // registration would only have invented a bogus [wikipage] tag. The
    // 'wikipage' toolbar command below inserts the [[...]] form directly,
    // which is all source mode needs.

    // ------------------------------------------------------------------
    // Default-off extension tags — registered so the BBCode is understood if
    // present in existing content, but NOT added to the default toolbar (see
    // xoopsBBCodeToolbar below). Module/admin configuration decides whether
    // these are actually offered to users; this plugin does not second-guess
    // that here.
    // ------------------------------------------------------------------

    // [iframe=height]https://...[/iframe] — class/textsanitizer/iframe/iframe.php:38.
    bbcode.set('iframe', {
        quoteType: QuoteType.always,
        format: function (element, content) {
            var height = element.getAttribute ? element.getAttribute('height') : '';
            return '[iframe=' + (height || '') + ']' + content + '[/iframe]';
        },
        html: function (token, attrs, content) {
            return '<iframe src="' + escapeEntities(escapeUriScheme(content)) + '"></iframe>';
        }
    });

    // [mp3]url[/mp3] — class/textsanitizer/mp3/mp3.php:59.
    bbcode.set('mp3', {
        format: '[mp3]{0}[/mp3]',
        html: function (token, attrs, content) {
            return '<audio controls><source src="' + escapeEntities(escapeUriScheme(content)) + '"></audio>';
        }
    });

    // [soundcloud]url[/soundcloud] — class/textsanitizer/soundcloud/soundcloud.php:47.
    bbcode.set('soundcloud', {
        format: '[soundcloud]{0}[/soundcloud]',
        html: function (token, attrs, content) {
            return '<a href="' + escapeEntities(escapeUriScheme(content)) + '">' + content + '</a>';
        }
    });

    // [mms=w,h]url[/mms] — class/textsanitizer/mms/mms.php:80 (deprecated since 2.5.9).
    bbcode.set('mms', {
        quoteType: QuoteType.always,
        format: function (element, content) {
            var width = element.getAttribute ? element.getAttribute('data-width') : '';
            var height = element.getAttribute ? element.getAttribute('data-height') : '';
            return '[mms=' + (width || '') + ',' + (height || '') + ']' + content + '[/mms]';
        },
        html: function (token, attrs, content) {
            return '<a href="' + escapeEntities(escapeUriScheme(content)) + '">' + content + '</a>';
        }
    });

    // [rtsp=w,h]url[/rtsp] — class/textsanitizer/rtsp/rtsp.php:74 (deprecated since 2.5.9).
    bbcode.set('rtsp', {
        quoteType: QuoteType.always,
        format: function (element, content) {
            var width = element.getAttribute ? element.getAttribute('data-width') : '';
            var height = element.getAttribute ? element.getAttribute('data-height') : '';
            return '[rtsp=' + (width || '') + ',' + (height || '') + ']' + content + '[/rtsp]';
        },
        html: function (token, attrs, content) {
            return '<a href="' + escapeEntities(escapeUriScheme(content)) + '">' + content + '</a>';
        }
    });

    // [wmp=w,h]url[/wmp] — class/textsanitizer/wmp/wmp.php:77.
    bbcode.set('wmp', {
        quoteType: QuoteType.always,
        format: function (element, content) {
            var width = element.getAttribute ? element.getAttribute('data-width') : '';
            var height = element.getAttribute ? element.getAttribute('data-height') : '';
            return '[wmp=' + (width || '') + ',' + (height || '') + ']' + content + '[/wmp]';
        },
        html: function (token, attrs, content) {
            return '<a href="' + escapeEntities(escapeUriScheme(content)) + '">' + content + '</a>';
        }
    });

    // ------------------------------------------------------------------
    // Toolbar command overrides (txtExec) — these are what actually run while
    // the editor stays in source mode; they insert/wrap text at the caret via
    // the public instance.insertText(before, after) API and never touch the
    // rest of the document, so pre-existing unknown BBCode is never at risk.
    // Only commands for tags XOOPS can actually render are defined/kept.
    // ------------------------------------------------------------------
    sceditor.command.set('strike', {
        txtExec: ['[d]', '[/d]'],
        tooltip: 'Strikethrough'
    });

    // The stock email command inserts [email=address]label[/email]; XOOPS only
    // decodes the bare [email]address[/email] form (module.textsanitizer.php:
    // 416-417), so the attribute form would publish as literal BBCode.
    sceditor.command.set('email', {
        txtExec: function (caller) {
            var addr = window.prompt('Email address:', '');
            if (addr) {
                this.insertText('[email]' + addr + '[/email]');
            }
        },
        tooltip: 'Email'
    });

    sceditor.command.set('left', { txtExec: ['[left]', '[/left]'], tooltip: 'Align left' });
    sceditor.command.set('center', { txtExec: ['[center]', '[/center]'], tooltip: 'Align center' });
    sceditor.command.set('right', { txtExec: ['[right]', '[/right]'], tooltip: 'Align right' });

    sceditor.command.set('size', {
        txtExec: function (caller) {
            var choice = window.prompt('Size (' + XOOPS_SIZES.join(', ') + '):', 'medium');
            if (choice && XOOPS_SIZES.indexOf(choice) !== -1) {
                this.insertText('[size=' + choice + ']', '[/size]');
            }
        },
        tooltip: 'Font Size'
    });

    sceditor.command.set('siteurl', {
        txtExec: function (caller) {
            var path = window.prompt('Site-relative path:', '');
            if (path) {
                this.insertText('[siteurl=' + path + ']', '[/siteurl]');
            }
        },
        tooltip: 'Site URL'
    });

    sceditor.command.set('quote', {
        txtExec: ['[quote]', '[/quote]'],
        tooltip: 'Quote'
    });

    sceditor.command.set('code', {
        txtExec: ['[code]', '[/code]'],
        tooltip: 'Code'
    });

    sceditor.command.set('bulletlist', {
        txtExec: ['[ul]\n[li]', '[/li]\n[/ul]'],
        tooltip: 'Bulleted list'
    });

    sceditor.command.set('image', {
        txtExec: function (caller) {
            var url = window.prompt('Image URL:', 'https://');
            if (url) {
                this.insertText('[img]' + url + '[/img]');
            }
        },
        tooltip: 'Image'
    });

    sceditor.command.set('youtube', {
        txtExec: function (caller) {
            var url = window.prompt('YouTube URL or video ID:', '');
            if (!url) {
                return;
            }
            var width = window.prompt('Width:', '16') || '16';
            var height = window.prompt('Height:', '9') || '9';
            this.insertText('[youtube=' + width + ',' + height + ']' + url + '[/youtube]');
        },
        tooltip: 'YouTube'
    });

    sceditor.command.set('wikipage', {
        txtExec: function (caller) {
            var term = window.prompt('Wiki page:', '');
            if (term) {
                this.insertText('[[' + term + ']]');
            }
        },
        tooltip: 'Wiki link'
    });

    /**
     * Default toolbar: only groups/commands for tags XOOPS can actually
     * render (per the registrations above). The default-off extension tags
     * (iframe/mp3/soundcloud/mms/rtsp/wmp) are intentionally NOT here — they
     * are understood if already present in content, but not offered by
     * default. sceditor.php reads this global when creating the instance.
     */
    window.xoopsBBCodeToolbar =
        'bold,italic,underline,strike|' +
        'left,center,right|' +
        'font,size,color|' +
        'link,siteurl,email|' +
        'image,youtube|' +
        'bulletlist|' +
        'quote,code|' +
        'wikipage';
}());
