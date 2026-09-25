/*
 * Runs the inline script FormEasyMDE generates (file path in argv[2]) against a
 * minimal fake form and checks which submits count as Save.
 * Called by XoopsMarkdownEditorTest; no browser or npm packages needed.
 * Copyright (c) 2000-2026 XOOPS Project (https://xoops.org). GNU GPL 2 or later.
 */
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {runInNewContext} = require('node:vm');

const script = readFileSync(process.argv[2], 'utf8');
const listeners = {};
const controls = [{value: '0'}, {value: '0'}];
const form = {
    addEventListener(type, listener) { (listeners[type] ??= []).push(listener); },
    querySelectorAll() { return controls; }
};
const field = {form};
const context = {
    document: {
        getElementById() { return field; },
        addEventListener(type, listener) { assert.equal(type, 'DOMContentLoaded'); listener(); }
    }
};
runInNewContext(script, context);
const emit = (type, event = {}) => (listeners[type] ?? []).forEach(listener => listener(event));
const values = () => controls.map(input => input.value);

assert.deepEqual(values(), ['0', '0'], 'Opening/programmatic editor-switch submission does not signal Save');
for (const name of ['preview', 'contents_upload', 'cancel']) {
    emit('submit', {submitter: {name, id: '', getAttribute: () => null}});
    assert.deepEqual(values(), ['0', '0'], name + ' is not Save');
}
emit('submit', {submitter: {name: 'contents_submit', id: '', getAttribute: () => null}});
assert.deepEqual(values(), ['1', '1'], 'NewBB Save marks intent for all editor fields');
emit('click');
assert.deepEqual(values(), ['0', '0'], 'Preview or switch after canceled submission clears stale intent');
emit('submit', {defaultPrevented: true});
assert.deepEqual(values(), ['0', '0'], 'Failed validation is not Save');
emit('submit', {});
assert.deepEqual(values(), ['1', '1'], 'Keyboard native submit is Save');
emit('change');
assert.deepEqual(values(), ['0', '0'], 'Keyboard editor switch clears a canceled Save intent');
emit('submit', {submitter: {name: 'custom', id: '', getAttribute: () => 'preview'}});
assert.deepEqual(values(), ['0', '0'], 'Explicit custom preview action');
runInNewContext(script, context);
assert.equal(listeners.submit.length, 1, 'Multiple editors share one form submit handler');
