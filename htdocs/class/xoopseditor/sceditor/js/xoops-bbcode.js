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
 * SCEditor starts visually and exposes its built-in source switch (see
 * ../sceditor.php render()). The format definitions below cover the XOOPS
 * tags rendered by the server. A tag without a definition (`[[Wiki]]`, an
 * unknown `[tag]`) or a smiley code SCEditor does not know is kept as text by
 * SCEditor's BBCode parser and saved unchanged (tests/sceditor-roundtrip.browser.js
 * round-trips [unknowntag] and [[WikiPage]]).
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
        var value = String(str || '');
        var colon = value.indexOf(':');
        // Relative reference (no scheme at all, or the first colon appears after a
        // path/query/fragment delimiter, e.g. "/page?a=b:c") — safe as-is.
        var delimiter = value.search(/[\/?#]/);
        if (-1 === colon || (-1 !== delimiter && delimiter < colon)) {
            return value;
        }
        // Absolute URI: allow only approved schemes; anything else (javascript:,
        // data:, vbscript:, ...) is replaced by a dead fragment reference.
        return /^(?:https?|ftp|mailto):/i.test(value) ? value : '#';
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
     * Convert a WYSIWYG CSS font-size value to a named XOOPS size: an exact keyword
     * match passes through, anything else falls back to 'medium'. Best-effort only —
     * see file header, this path is not exercised while the editor stays in source mode.
     */
    function toXoopsSize(cssSize, htmlSize) {
        var numeric = parseInt(htmlSize, 10);
        if (numeric >= 1 && numeric <= 7) {
            return XOOPS_SIZES[numeric - 1];
        }
        var value = String(cssSize).toLowerCase().trim();
        var i = XOOPS_SIZES.indexOf(value);
        if (i !== -1) {
            return XOOPS_SIZES[i];
        }
        var percent = parseFloat(value);
        if (value.indexOf('%') !== -1 && isFinite(percent)) {
            return percent <= 50 ? 'xx-small' : percent <= 75 ? 'x-small'
                : percent <= 90 ? 'small' : percent <= 110 ? 'medium'
                : percent <= 140 ? 'large' : percent <= 175 ? 'x-large' : 'xx-large';
        }
        return 'medium';
    }

    /**
     * Localized label lookup: sceditor.php publishes window.xoopsSCEditorLang from the
     * editor language file before this script loads; missing keys fall back to English.
     */
    function L(key, fallback) {
        var lang = window.xoopsSCEditorLang;
        return (lang && typeof lang[key] === 'string' && lang[key]) ? lang[key] : fallback;
    }

    /**
     * bbcode.set() MERGES into an existing definition, so a stock tags/styles claim
     * survives next to ours and the element is serialised twice
     * ([center][center]x[/center][/center], growing on every save). Tags whose stock
     * claims conflict with the XOOPS dialect are replaced, not merged.
     */
    function define(name, definition) {
        bbcode.remove(name);
        bbcode.set(name, definition);
    }

    /** Site root, derived from this script's own URL (for image.php?id= previews). */
    var SITE_URL = ((document.currentScript && document.currentScript.src) || '')
        .replace(/\/class\/xoopseditor\/sceditor\/js\/xoops-bbcode\.js.*$/, '');

    /**
     * Anchors that belong to a XOOPS tag ([siteurl], [youtube], media tags) also match
     * the generic 'url' claim; 'url' must leave those to their own definition.
     */
    function isXoopsOwned(element) {
        return !!(element.hasAttribute && (element.hasAttribute('data-xoops-tag')
            || element.hasAttribute('data-siteurl') || element.hasAttribute('data-youtube')));
    }

    /** Tag content is already entity-encoded text; only '"' still needs escaping for an attribute. */
    function quoteAttr(value) {
        return String(value || '').replace(/"/g, '&quot;');
    }

    /**
     * XOOPS writes colours as bare hex ([color=FF0000]; the server adds the '#').
     * Browsers report rgb(); convert back so DHTML and SCEditor posts look alike.
     */
    function toXoopsColor(value) {
        var rgb = /^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i.exec(String(value || ''));
        if (!rgb) {
            return String(value || '').replace(/^#/, '');
        }
        return [rgb[1], rgb[2], rgb[3]].map(function (n) {
            return ('0' + parseInt(n, 10).toString(16)).slice(-2);
        }).join('').toUpperCase();
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
        tags: { del: null, strike: null },
        format: '[d]{0}[/d]',
        html: '<del>{0}</del>'
    });
    // The stock [s] also claims <strike>; left alone, every strike element became
    // [d][s]..[/s][/d]. [s] keeps only its own <s> so typed [s] round-trips.
    define('s', {
        tags: { s: null },
        format: '[s]{0}[/s]',
        html: '<s>{0}</s>'
    });

    // --- Alignment: [center] [left] [right], not [align=] --------------
    // module.textsanitizer.php:427-432. Registered explicitly (rather than
    // relying on whatever SCEditor's own default alignment dialect happens to
    // be) so this is correct regardless of upstream default drift.
    define('left', {
        tags: { div: { style: { 'text-align': ['left'] } } },
        format: '[left]{0}[/left]',
        html: '<div style="text-align: left;">{0}</div>'
    });
    define('center', {
        tags: { div: { style: { 'text-align': ['center'] } } },
        format: '[center]{0}[/center]',
        html: '<div style="text-align: center;">{0}</div>'
    });
    define('right', {
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
        quoteType: QuoteType.auto,
        format: function (element, content) {
            if (isXoopsOwned(element)) {
                return content;
            }
            var href = element.getAttribute ? element.getAttribute('href') || '' : '';
            // The server has only the bare [email]address[/email] form; a
            // [url=mailto:] would render as http://mailto:...
            if (/^mailto:/i.test(href)) {
                return '[email]' + href.slice(7) + '[/email]';
            }
            // Always the attribute form: xoopsCodeDecode() has no bare [url]x[/url].
            return '[url=' + href + ']' + content + '[/url]';
        },
        html: function (token, attrs, content) {
            // Scheme-check before use, so [url=javascript:...] never becomes a live
            // link. The attribute arrives raw and is escaped once; a bare [url] uses
            // the content, which is already entity-encoded.
            var href = attrs && attrs.defaultattr
                ? escapeEntities(escapeUriScheme(attrs.defaultattr))
                : quoteAttr(escapeUriScheme(content));
            return '<a href="' + href + '">' + content + '</a>';
        }
    });

    // --- [siteurl=...]...[/siteurl] — XOOPS-specific, no SCEditor default ---
    // module.textsanitizer.php:402-403. html() stores the path on data-siteurl
    // (the attribute format() reads back) so the tag round-trips without losing
    // its target.
    bbcode.set('siteurl', {
        // The data-siteurl attribute claim makes the converter route these
        // anchors here instead of to the generic 'url' handler.
        tags: { a: { 'data-siteurl': null } },
        quoteType: QuoteType.auto,
        format: function (element, content) {
            return '[siteurl=' + (element.getAttribute('data-siteurl') || '') + ']' + content + '[/siteurl]';
        },
        html: function (token, attrs, content) {
            var path = (attrs && attrs.defaultattr) || '';
            // The server always prefixes XOOPS_URL, so the visual link does too: a
            // stored [siteurl=javascript:...] stays a harmless site path here.
            // format() reads the raw path back from data-siteurl.
            return '<a data-siteurl="' + escapeEntities(path) + '"'
                + ' href="' + escapeEntities(SITE_URL + '/' + path.replace(/^\/+/, '')) + '">' + content + '</a>';
        }
    });

    // --- [email]address[/email] — bare, address IS the content ---------
    // module.textsanitizer.php:416-417 — no `=address` attribute form exists
    // server-side, so the default SCEditor `[email=addr]label[/email]` shape
    // (if that is what upstream ships) must not be used here.
    // Deliberately NO tags: claim — SCEditor's attribute constraints accept
    // only null or an array of values, and a RegExp here makes the converter
    // call .includes() on it and throw for EVERY anchor. A mailto anchor is
    // instead claimed by 'url', whose format() writes it back as [email].
    bbcode.set('email', {
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
            // Store the parsed language on the attribute format() reads back (same
            // pattern as siteurl/data-siteurl) so [code=lang] round-trips.
            var lang = (attrs && attrs.defaultattr) || '';
            return '<code' + (lang ? ' data-lang="' + escapeEntities(lang) + '"' : '') + '>' + content + '</code>';
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
        quoteType: QuoteType.auto,
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
        quoteType: QuoteType.auto,
        format: function (element, content) {
            var color = (element.getAttribute && element.getAttribute('color'))
                || (element.style && element.style.color)
                || '';
            return '[color=' + toXoopsColor(color) + ']' + content + '[/color]';
        },
        html: function (token, attrs, content) {
            var color = (attrs && attrs.defaultattr) || '';
            // Bare hex is the XOOPS form; CSS needs the '#' or the colour is dropped.
            if (/^[0-9a-f]{3}(?:[0-9a-f]{3})?$/i.test(color)) {
                color = '#' + color;
            }
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
        quoteType: QuoteType.auto,
        format: function (element, content) {
            var size = element.style ? element.style.fontSize : '';
            var htmlSize = element.getAttribute ? element.getAttribute('size') : '';
            return '[size=' + toXoopsSize(size, htmlSize) + ']' + content + '[/size]';
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
        quoteType: QuoteType.auto,
        format: function (element, content) {
            // An emoticon image also matches this img claim. The stock 'emoticon'
            // definition turns it back into its code (:sick:); returning [img]url
            // here stored every inserted emoticon as an image link.
            if (element.hasAttribute && element.hasAttribute('data-sceditor-emoticon')) {
                return content;
            }
            var src = element.getAttribute ? element.getAttribute('src') : '';
            var width = element.getAttribute ? element.getAttribute('width') : '';
            var id = element.getAttribute ? element.getAttribute('data-xoops-id') : '';
            // center has no float, so it travels on data-xoops-align.
            var align = (element.getAttribute && element.getAttribute('data-xoops-align'))
                || (element.style ? element.style.float : '');
            var attrs = '';
            if (align) {
                attrs += ' align=' + align;
            }
            // [img id=N]caption[/img]: an image-manager image; the body is its
            // caption, not a URL, and the server has no width= variant for it.
            if (id) {
                // image.php only decodes a caption without " ' ( ) ? & < >.
                var caption = (element.getAttribute('alt') || '').replace(/["'()?&<>]/g, '');
                return '[img' + attrs + ' id=' + id + ']' + caption + '[/img]';
            }
            if (width) {
                attrs += ' width=' + width;
            }
            return '[img' + attrs + ']' + src + '[/img]';
        },
        html: function (token, attrs, content) {
            // Re-emit width/align so format() (which reads the width attribute and
            // the float style) can rebuild the original tag instead of a bare [img].
            var extra = '';
            var align = attrs && attrs.align ? String(attrs.align).toLowerCase() : '';
            if (/^(left|center|right)$/.test(align)) {
                extra += ' data-xoops-align="' + align + '"';
                if (align !== 'center') {
                    extra += ' style="float: ' + align + ';"';
                }
            }
            if (attrs && attrs.id && /^\d+$/.test(attrs.id)) {
                return '<img src="' + escapeEntities(SITE_URL + '/image.php?id=' + attrs.id) + '"'
                    + ' data-xoops-id="' + attrs.id + '"' + extra + ' alt="' + quoteAttr(content) + '" />';
            }
            if (attrs && attrs.width) {
                extra += ' width="' + escapeEntities(attrs.width) + '"';
            }
            // content is already entity-encoded; escaping it again would add an
            // &amp; on every visual/source round trip.
            return '<img src="' + quoteAttr(escapeUriScheme(content)) + '"' + extra + ' alt="" />';
        }
    });

    // [youtube=WIDTHxHEIGHT_OR_W,H]videoIdOrUrl[/youtube] — class/textsanitizer/youtube/youtube.php:77.
    bbcode.set('youtube', {
        // data-youtube claim + data-width/data-height carry the tag identity and
        // dimensions through a conversion, so format() rebuilds the original tag
        // instead of the anchor being claimed by 'url'.
        // The iframe claim is the stock one: the autoyoutube plugin inserts
        // <iframe data-youtube-id>, and this definition replaced the stock tags.
        tags: { a: { 'data-youtube': null }, iframe: { 'data-youtube-id': null } },
        quoteType: QuoteType.auto,
        format: function (element, content) {
            var get = function (name) {
                return (element.getAttribute && element.getAttribute(name)) || '';
            };
            var width = get('data-width');
            var height = get('data-height');
            // A bare [youtube] must not come back as [youtube=,].
            var dims = (width || height) ? '=' + width + ',' + height : '';
            if (get('data-youtube-id')) {
                // The player: data-youtube-src keeps the URL or id as written;
                // an autoyoutube iframe has only the id.
                return '[youtube' + dims + ']' + (get('data-youtube-src') || get('data-youtube-id')) + '[/youtube]';
            }
            return '[youtube' + dims + ']' + content + '[/youtube]';
        },
        html: function (token, attrs, content) {
            var dims = String((attrs && attrs.defaultattr) || '').split(',');
            // Same id rules as MytsYoutube::decode(); content is entity-encoded, so
            // '&' arrives as '&amp;' and still ends the id. The host is anchored
            // like the PHP pattern, so the preview never shows a player the saved
            // post will not (YoutubeTagTest checks the two patterns match).
            var match = /^(?:https?:)?(?:\/\/)?(?:[a-z0-9-]+\.)?(?:youtube(?:-nocookie)?\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([A-Za-z0-9_-]{11})(?![\w-])/i.exec(content)
                || /^([A-Za-z0-9_-]{11})$/.exec(content);
            if (match) {
                // Show the player. Sizes below 17 are an aspect ratio (16,9), not pixels.
                var width = parseInt(dims[0], 10) > 16 ? parseInt(dims[0], 10) : 560;
                var height = parseInt(dims[1], 10) > 9 ? parseInt(dims[1], 10) : Math.round(width * 9 / 16);
                return '<iframe width="' + width + '" height="' + height + '" frameborder="0" allowfullscreen'
                    + ' src="https://www.youtube-nocookie.com/embed/' + match[1] + '?wmode=opaque"'
                    + ' data-youtube-id="' + match[1] + '"'
                    + ' data-youtube-src="' + quoteAttr(content) + '"'
                    + ' data-width="' + escapeEntities(dims[0] || '') + '"'
                    + ' data-height="' + escapeEntities(dims[1] || '') + '"></iframe>';
            }
            // Not a recognisable video: keep it as a link so nothing is lost.
            return '<a data-youtube="1"'
                + ' data-width="' + escapeEntities(dims[0] || '') + '"'
                + ' data-height="' + escapeEntities(dims[1] || '') + '"'
                + ' href="https://www.youtube.com/watch?v=' + escapeEntities(content) + '">' + content + '</a>';
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
    // SCEditorConfig::TOOLBAR). Module/admin configuration decides whether
    // these are actually offered to users; this plugin does not second-guess
    // that here.
    // ------------------------------------------------------------------

    // Media tags — class/textsanitizer/{iframe,mp3,soundcloud,mms,rtsp,wmp}/*.php:
    // [iframe=height]url[/iframe], [mp3]url[/mp3], [soundcloud]url[/soundcloud],
    // [mms=w,h]url[/mms] (deprecated since 2.5.9), [rtsp=w,h]url[/rtsp] (deprecated
    // since 2.5.9), [wmp=w,h]url[/wmp].
    // Shown as links in the visual view: SCEditor's sanitizer strips <iframe> and
    // empties <audio>. The link carries the tag name, the raw attribute and the raw
    // URL (escapeUriScheme() rewrites mms:/rtsp: into page-relative links), so
    // format() never reconstructs them. Without the tags: claim these came back
    // empty or as [url].
    function mediaTag(name) {
        define(name, {
            tags: { a: { 'data-xoops-tag': [name] } },
            format: function (element) {
                var attr = element.getAttribute('data-xoops-attr') || '';
                return '[' + name + (attr ? '=' + attr : '') + ']'
                    + (element.getAttribute('data-xoops-src') || '') + '[/' + name + ']';
            },
            html: function (token, attrs, content) {
                var attr = (attrs && attrs.defaultattr) || '';
                return '<a data-xoops-tag="' + name + '"'
                    + (attr ? ' data-xoops-attr="' + escapeEntities(attr) + '"' : '')
                    + ' data-xoops-src="' + quoteAttr(content) + '"'
                    + ' href="' + quoteAttr(escapeUriScheme(content)) + '">' + content + '</a>';
            }
        });
    }
    ['iframe', 'mp3', 'soundcloud', 'mms', 'rtsp', 'wmp'].forEach(mediaTag);

    // ------------------------------------------------------------------
    // Toolbar command overrides (txtExec) — these are what actually run while
    // the editor stays in source mode; they insert/wrap text at the caret via
    // the public instance.insertText(before, after) API and never touch the
    // rest of the document, so pre-existing unknown BBCode is never at risk.
    // Only commands for tags XOOPS can actually render are defined/kept.
    // ------------------------------------------------------------------
    sceditor.command.set('strike', {
        txtExec: ['[d]', '[/d]'],
        tooltip: L('strike', 'Strikethrough')
    });

    // The stock email command inserts [email=address]label[/email]; XOOPS only
    // decodes the bare [email]address[/email] form (module.textsanitizer.php:
    // 416-417), so the attribute form would publish as literal BBCode.
    sceditor.command.set('email', {
        txtExec: function (caller) {
            var addr = window.prompt(L('emailPrompt', 'Email address:'), '');
            if (addr) {
                this.insertText('[email]' + addr + '[/email]');
            }
        },
        tooltip: L('email', 'Email')
    });

    sceditor.command.set('left', { txtExec: ['[left]', '[/left]'], tooltip: L('left', 'Align left') });
    sceditor.command.set('center', { txtExec: ['[center]', '[/center]'], tooltip: L('center', 'Align center') });
    sceditor.command.set('right', { txtExec: ['[right]', '[/right]'], tooltip: L('right', 'Align right') });

    sceditor.command.set('size', {
        txtExec: function (caller) {
            var choice = window.prompt(L('sizePrompt', 'Size (%s):').replace('%s', XOOPS_SIZES.join(', ')), 'medium');
            choice = choice ? choice.trim().toLowerCase() : choice;
            if (choice && XOOPS_SIZES.indexOf(choice) !== -1) {
                this.insertText('[size=' + choice + ']', '[/size]');
            }
        },
        tooltip: L('size', 'Font Size')
    });

    // XOOPS-only commands have no stock exec, so they need one for the visual
    // view too. insert() runs the BBCode through the format in visual mode and
    // inserts it verbatim in source mode.
    function both(fn) {
        return { exec: fn, txtExec: fn };
    }

    sceditor.command.set('siteurl', Object.assign(both(function () {
        var path = window.prompt(L('siteurlPrompt', 'Site-relative path:'), '');
        if (path) {
            this.insert('[siteurl=' + path + ']', '[/siteurl]');
        }
    }), { tooltip: L('siteurl', 'Site URL') }));

    // [mp3]url[/mp3] — class/textsanitizer/mp3/mp3.php; same URL rule as its own button.
    // sceditor.php drops the button while that extension is off.
    sceditor.command.set('mp3', Object.assign(both(function () {
        var url = window.prompt(L('mp3Prompt', 'MP3 URL (https://.../file.mp3):'), 'https://');
        url = url ? url.trim() : '';
        if (/^https?:\/\/[\w\-.]+(:\d+)?\/.+\.mp3(\?.*)?$/i.test(url)) {
            this.insert('[mp3]' + url + '[/mp3]');
        }
    }), { tooltip: L('mp3', 'MP3 audio') }));

    sceditor.command.set('quote', {
        txtExec: ['[quote]', '[/quote]'],
        tooltip: L('quote', 'Quote')
    });

    sceditor.command.set('code', {
        txtExec: ['[code]', '[/code]'],
        tooltip: L('code', 'Code')
    });

    sceditor.command.set('bulletlist', {
        txtExec: ['[ul]\n[li]', '[/li]\n[/ul]'],
        tooltip: L('list', 'Bulleted list')
    });

    sceditor.command.set('image', {
        txtExec: function (caller) {
            var url = window.prompt(L('imagePrompt', 'Image URL:'), 'https://');
            if (url) {
                this.insertText('[img]' + url + '[/img]');
            }
        },
        tooltip: L('image', 'Image')
    });

    sceditor.command.set('youtube', {
        txtExec: function (caller) {
            var url = window.prompt(L('youtubePrompt', 'YouTube URL or video ID:'), '');
            if (!url) {
                return;
            }
            var width = window.prompt(L('widthPrompt', 'Width:'), '16') || '16';
            var height = window.prompt(L('heightPrompt', 'Height:'), '9') || '9';
            this.insertText('[youtube=' + width + ',' + height + ']' + url + '[/youtube]');
        },
        tooltip: L('youtube', 'YouTube')
    });

    sceditor.command.set('wikipage', Object.assign(both(function () {
        var term = window.prompt(L('wikiPrompt', 'Wiki page:'), '');
        if (term) {
            this.insertText('[[' + term + ']]');
        }
    }), { tooltip: L('wiki', 'Wiki link') }));

    // The toolbar is built server-side from SCEditorConfig::TOOLBAR and the
    // System > Preferences > Editors settings (see ../class/SCEditorConfig.php).
}());
