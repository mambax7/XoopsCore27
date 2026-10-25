/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

 @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 @license GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 */
/* XOOPS Default theme — dark-mode behavior regression checks. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const script = fs.readFileSync(path.join(__dirname, '../js/dark-mode.js'), 'utf8');

for (const protocol of ['http:', 'https:']) {
    for (const cookie of ['', 'xoops_dark_mode=0', 'xoops_dark_mode=1', 'xoops_dark_mode=invalid']) {
        const classes = new Set();
        const attributes = {};
        let click;
        let ready;
        const toggle = {
            setAttribute: (name, value) => { attributes[name] = value; },
            addEventListener: (event, handler) => { click = handler; }
        };
        const document = {
            cookie,
            body: {classList: {
                toggle(name, enabled) {
                    if (enabled === undefined) enabled = !classes.has(name);
                    if (enabled) classes.add(name); else classes.delete(name);
                    return enabled;
                },
                contains: name => classes.has(name)
            }},
            getElementById: () => toggle,
            addEventListener: (event, handler) => { ready = handler; }
        };
        vm.runInNewContext(script, {document, location: {protocol}, Date});
        ready();
        const initial = cookie === 'xoops_dark_mode=1';
        assert.equal(classes.has('dark-mode'), initial);
        assert.equal(attributes['aria-pressed'], String(initial));
        click();
        assert.equal(classes.has('dark-mode'), !initial);
        assert.equal(attributes['aria-pressed'], String(!initial));
        assert.match(document.cookie, new RegExp('^xoops_dark_mode=' + (initial ? '0' : '1') + ';'));
        assert.match(document.cookie, /;path=\/;SameSite=Lax/);
        assert.equal(document.cookie.includes(';Secure'), protocol === 'https:');
        click();
        assert.equal(classes.has('dark-mode'), initial);
        assert.equal(attributes['aria-pressed'], String(initial));
    }
}
console.log('PASS: light/dark toggle, persisted preference, invalid cookie, accessibility and HTTPS cookies');
