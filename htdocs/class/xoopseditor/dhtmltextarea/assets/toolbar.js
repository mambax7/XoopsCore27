/*
 * Shared DHTML editor toolbar behaviour.
 *
 * The dropdowns are native <details>/<summary>, and each toolbar's set shares a name= so modern
 * browsers make them mutually exclusive on their own. This file supplies the rest:
 *
 *   1. the same exclusivity for browsers that predate the name= "exclusive accordion" attribute
 *      (name= on <details> is only supported in newer engines);
 *   2. closing the open menu when the user clicks anywhere outside it;
 *   3. closing it on Escape, and returning focus to the toggle.
 *
 * Everything is delegated from document, so toolbars added to the page later (AJAX forms, a
 * second editor) are handled without re-initialising anything.
 */
(function () {
    'use strict';

    if (window.xoopsEditorToolbarInit) {
        return;
    }
    window.xoopsEditorToolbarInit = true;

    var DROPDOWN = 'details.xo-edtb-dropdown';

    /**
     * Close every open dropdown except the one passed in.
     *
     * @param {Element|null} keepOpen dropdown to leave alone
     * @param {Element|null} scope    toolbar to limit the sweep to, or null for the document
     */
    function closeOthers(keepOpen, scope) {
        var root = scope || document;
        var open = root.querySelectorAll(DROPDOWN + '[open]');
        for (var i = 0; i < open.length; i++) {
            if (open[i] !== keepOpen) {
                open[i].removeAttribute('open');
            }
        }
    }

    // 1 + 2: opening one closes the others. 'toggle' fires on open AND close, so only act on open.
    document.addEventListener('toggle', function (event) {
        var details = event.target;
        if (!details || !details.matches || !details.matches(DROPDOWN) || !details.hasAttribute('open')) {
            return;
        }
        // Limit to the owning toolbar so a second editor on the page is unaffected.
        closeOthers(details, details.closest('.xo-edtb-toolbar'));
    }, true); // capture: 'toggle' does not bubble

    // 2: click anywhere outside an open dropdown closes it.
    document.addEventListener('click', function (event) {
        var inside = event.target.closest ? event.target.closest(DROPDOWN) : null;
        closeOthers(inside, null);
    });

    // 3: Escape closes the open dropdown and puts focus back on its toggle.
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' && event.keyCode !== 27) {
            return;
        }
        var open = document.querySelectorAll(DROPDOWN + '[open]');
        for (var i = 0; i < open.length; i++) {
            var summary = open[i].querySelector('summary');
            open[i].removeAttribute('open');
            if (summary && typeof summary.focus === 'function') {
                summary.focus();
            }
        }
    });
})();
