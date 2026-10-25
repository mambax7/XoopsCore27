/* SCEditor uses a separate document; keep display styles outside editable content. */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        const watched = new WeakSet();
        const selector = '.sceditor-container iframe';
        function sync() {
            const dark = document.body.classList.contains('dark-mode');
            const palette = getComputedStyle(document.body);
            document.querySelectorAll(selector).forEach(function (frame) {
                if (!watched.has(frame)) {
                    watched.add(frame);
                    frame.addEventListener('load', sync);
                }
                let doc;
                try { doc = frame.contentDocument; } catch (error) {
                    if (error.name === 'SecurityError') return;
                    throw error;
                }
                if (!doc || !doc.head || !doc.body) return;
                if (!doc.getElementById('xoops-admin-editor-colors')) {
                    const style = doc.createElement('style');
                    style.id = 'xoops-admin-editor-colors';
                    style.textContent = 'html.xoops-admin-dark, html.xoops-admin-dark body {background:#1c222a;color:var(--xoops-editor-text);color-scheme:dark;}'
                        + 'html.xoops-admin-dark a {color:var(--xoops-editor-link);}'
                        + 'html.xoops-admin-dark :is(code,pre,blockquote) {background:#242a32;color:var(--xoops-editor-text);border-color:#788797;}';
                    doc.head.appendChild(style);
                }
                doc.documentElement.style.setProperty('--xoops-editor-text', palette.getPropertyValue('--admin-text'));
                doc.documentElement.style.setProperty('--xoops-editor-link', palette.getPropertyValue('--admin-link'));
                doc.documentElement.classList.toggle('xoops-admin-dark', dark);
            });
        }
        sync();
        new MutationObserver(function (records) {
            const changed = records.some(function (record) {
                if (record.type === 'attributes') return record.target === document.body;
                return Array.from(record.addedNodes).some(function (node) {
                    return node.nodeType === 1 && (node.matches('.sceditor-container, .sceditor-container iframe') || node.querySelector(selector));
                });
            });
            if (changed) sync();
        }).observe(document.body, {attributes: true, attributeFilter: ['class'], childList: true, subtree: true});
    });
})();
