/**
 * Options for SCEditor's dragdrop plugin: an image dropped or pasted into the
 * editor is stored in the XOOPS image manager through ajaxfineupload.php and
 * inserted as [img id=N]caption[/img]. sceditor.php loads this file only when
 * it has minted an upload token for the current user (dragdropConfig()).
 *
 * The server re-checks type, size and dimensions; the checks here only save a
 * pointless upload.
 */
(function () {
    'use strict';

    var TYPES = ['image/gif', 'image/jpeg', 'image/png'];
    var EXTENSIONS = { 'image/gif': 'gif', 'image/jpeg': 'jpg', 'image/png': 'png' };

    function L(key, fallback) {
        var lang = window.xoopsSCEditorLang || {};
        return lang[key] || fallback;
    }

    /** qquuid: the handler accepts [A-Za-z0-9_-]{1,64}. */
    function uploadId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
    }

    /**
     * @param {{endpoint: string, token: string, maxSize: number}} config from sceditor.php
     * @return {Object} the `dragdrop` option for sceditor.create()
     */
    window.xoopsSCEditorDragdrop = function (config) {
        return {
            allowedTypes: TYPES,
            handlePaste: true,
            handleFile: function (file, createPlaceholder) {
                var placeholder = createPlaceholder();
                if (config.maxSize > 0 && file.size > config.maxSize) {
                    placeholder.cancel();
                    window.alert(L('uploadTooBig', 'The image is larger than this image category allows.'));
                    return;
                }
                // A pasted screenshot has no name; the server needs an allowed extension.
                var name = file.name || ('pasted.' + EXTENSIONS[file.type]);
                var body = new FormData();
                body.append('Authorization', config.token);
                body.append('qquuid', uploadId());
                body.append('qqfilename', name);
                body.append('qqtotalfilesize', String(file.size));
                body.append('qqfile', file, name);

                fetch(config.endpoint, { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (result) {
                        var id = String((result && result.image_id) || '');
                        if (!result || !result.success || !/^[1-9]\d*$/.test(id)) {
                            throw new Error((result && result.error) || '');
                        }
                        // Same markup the [img id=] definition renders, so the
                        // source view and the saved post show [img id=N]caption[/img].
                        placeholder.insert(sceditor.formats.bbcode.get('img')
                            .html(null, { id: id }, sceditor.escapeEntities(String(result.name || ''))));
                    })
                    .catch(function (error) {
                        placeholder.cancel();
                        // alert() shows text, so a server message cannot inject markup.
                        window.alert(L('uploadFailed', 'The image could not be uploaded:')
                            + ' ' + ((error && error.message) || ''));
                    });
            }
        };
    };
}());
