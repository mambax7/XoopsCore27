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
/* Run with: node browser-dark-mode.cjs /path/to/playwright-or-playwright-core */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const {chromium} = require(process.argv[2] || 'playwright');
const theme = path.resolve(__dirname, '..');
const htdocs = path.resolve(theme, '../../../..');
const assets = {
    '/jquery.js': path.join(htdocs, 'xoops_lib/Frameworks/jquery/jquery.js'),
    '/admin.css': path.join(htdocs, 'modules/system/css/admin.css'),
    '/xoops.css': path.join(htdocs, 'xoops.css')
};
for (const name of ['style', 'dark', 'silver', 'orange', 'dark-mode']) {
    assets['/' + name + '.css'] = path.join(theme, 'css', name + '.css');
}
for (const name of ['styleswitch', 'dark-mode']) {
    assets['/' + name + '.js'] = path.join(theme, 'js', name + '.js');
}
const html = `<!doctype html><html><head>
<link rel="stylesheet" href="/xoops.css"><link rel="stylesheet" href="/admin.css">
<link rel="stylesheet" href="/style.css">
<link rel="stylesheet" href="/dark.css" title="dark">
<link rel="stylesheet" href="/silver.css" title="silver">
<link rel="stylesheet" href="/orange.css" title="orange">
<link rel="stylesheet" href="/dark-mode.css">
<script>var tplUrl = '';</script><script src="/jquery.js"></script>
<script src="/styleswitch.js"></script><script src="/dark-mode.js"></script>
</head><body><div id="xo-logo-head"><div id="main-logo">XOOPS Transition</div>
<ul id="xo-headnav"><li><button type="button" id="darkModeToggle" aria-pressed="false">Dark mode</button></li><li><a href="#">Home</a></li></ul></div>
<div id="xo_globalnav"><div id="choosestyle">
<a href="#" rel="dark" class="styleswitch">Dark accent</a>
<a href="#" rel="silver" class="styleswitch">Silver</a>
<a href="#" rel="orange" class="styleswitch">Orange</a></div></div>
<div id="xo-nav-options"><div id="xo-modname">System administration</div><div style="clear:both"></div></div>
<div id="xo-body"><div id="xo-content"><h2 class="xo-title">Preferences</h2>
<ul id="xo-breadcrumb" style="background-image:url('/breadcrumb.png')"><li><a href="#">Home</a></li><li><a href="#">System</a></li><li>Preferences</li></ul>
<div class="tips">Help and tips for administrators</div>
<table class="outer"><tr><th colspan="2">General settings</th></tr>
<tr class="head"><td>Setting</td><td>Value</td></tr>
<tr class="even"><td><a href="#">Site name</a></td><td><input type="text" value="XOOPS"></td></tr>
<tr class="odd"><td>Editor</td><td><select><option>Default editor</option></select></td></tr></table>
<table class="outer"><tfoot><tr class="txtcenter foot"><td>Confirm module changes</td></tr></tfoot></table>
<fieldset><legend>Options</legend><label><input type="checkbox" checked> Enabled</label><textarea>Sample content</textarea>
<input type="submit" value="Save"><button type="button">Cancel</button></fieldset>
<ul class="tabs"><li class="current"><a class="current" href="#">Overview</a></li><li><a href="#">Details</a></li></ul>
<div class="panes"><p>Module overview with <a href="#">documentation</a>.</p></div>
<div id="xo-icon"><a href="#">Modules</a></div><div style="clear:both"></div>
<div id="help-template"><div class="even">Help content</div><div class="odd">More help</div></div>
<div class="errorMsg">Error message <a href="#">details</a></div>
<div class="confirmMsg">Confirmation</div><div class="resultMsg">Success</div>
<div class="ui-dialog">Dialog content</div>
</div></div><div id="xo-footer">Powered by <a href="#">XOOPS</a></div></body></html>`;
const server = http.createServer((req, res) => {
    if (req.url === '/') {
        res.setHeader('Content-Type', 'text/html');
        res.end(html);
    } else if (assets[req.url]) {
        res.setHeader('Content-Type', req.url.endsWith('.css') ? 'text/css' : 'text/javascript');
        res.end(fs.readFileSync(assets[req.url]));
    } else {
        res.writeHead(404).end();
    }
});
(async function () {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    let browser;
    try {
        browser = await chromium.launch({channel: 'chrome', headless: true});
        const page = await browser.newPage({viewport: {width: 1280, height: 1000}});
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto('http://127.0.0.1:' + server.address().port);
        const color = selector => page.locator(selector).first().evaluate(el => getComputedStyle(el).backgroundColor);
        assert.equal(await color('#xo-body'), 'rgb(255, 255, 255)');
        const toggle = page.locator('#darkModeToggle');
        await toggle.focus();
        await page.keyboard.press('Space');
        assert.equal(await toggle.getAttribute('aria-pressed'), 'true');
        assert.equal(await color('#xo-body'), 'rgb(3, 3, 3)');
        assert.equal(await color('tr.even td'), 'rgb(34, 37, 43)');
        assert.equal(await color('.ui-dialog'), 'rgb(34, 37, 43)');
        assert.equal(await color('.tabs .current a'), 'rgb(51, 55, 64)');
        assert.equal(await color('tr.foot td'), 'rgb(51, 55, 64)');
        for (const accent of ['orange', 'silver', 'dark']) {
            await page.locator('.styleswitch[rel="' + accent + '"]').click();
            assert.equal(await color('#xo-body'), 'rgb(3, 3, 3)');
            assert.equal(await page.locator('body').getAttribute('data-admin-style'), accent);
            assert.equal(await color('.confirmMsg'), 'rgb(56, 47, 24)');
            assert.equal(await page.locator('.xo-title').evaluate(el => getComputedStyle(el).color), 'rgb(222, 222, 222)');
            assert.equal(await page.locator('tr.even a').evaluate(el => getComputedStyle(el).color), 'rgb(157, 204, 255)');
        }
        await page.locator('.styleswitch[rel="orange"]').click();
        await page.waitForFunction(() => getComputedStyle(document.querySelector('input[type="submit"]')).backgroundColor === 'rgb(255, 118, 0)');
        assert.equal(await color('input[type="submit"]'), 'rgb(255, 118, 0)');
        for (const selector of ['#darkModeToggle', 'input[type="submit"]']) {
            await page.locator(selector).hover();
            await page.waitForFunction(selector => getComputedStyle(document.querySelector(selector)).backgroundColor === 'rgb(255, 146, 46)', selector);
            const contrast = await page.locator(selector).evaluate(el => {
                const luminance = color => color.match(/[\d.]+/g).slice(0, 3).map(Number).map(c => c / 255).map(c => c <= .04045 ? c / 12.92 : ((c + .055) / 1.055) ** 2.4).reduce((sum, c, i) => sum + c * [.2126, .7152, .0722][i], 0);
                const style = getComputedStyle(el), fg = luminance(style.color), bg = luminance(style.backgroundColor);
                return (Math.max(fg, bg) + .05) / (Math.min(fg, bg) + .05);
            });
            assert.ok(contrast >= 4.5, selector + ' orange hover contrast: ' + contrast);
        }
        await page.mouse.move(0, 0);
        await page.reload();
        assert.equal(await toggle.getAttribute('aria-pressed'), 'true');
        assert.equal(await page.locator('body').getAttribute('data-admin-style'), 'orange');
        // A site customizer can target the selected accent without dark mode replacing it.
        await page.addStyleTag({content: 'body[data-admin-style="orange"] { --transition-accent: #8844cc; --transition-accent-text: white; }'});
        await page.waitForFunction(() => getComputedStyle(document.querySelector('input[type="submit"]')).backgroundColor === 'rgb(136, 68, 204)');
        assert.equal(await color('input[type="submit"]'), 'rgb(136, 68, 204)');
        await page.screenshot({path: path.join(require('node:os').tmpdir(), 'transition-dark-mode.png'), fullPage: true});
        await toggle.click();
        assert.equal(await color('#xo-body'), 'rgb(255, 255, 255)');
        assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
        await page.reload();
        assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
        await page.context().addCookies([{name: 'xoadmstyle', value: 'invalid', url: page.url()}]);
        await page.reload();
        assert.equal(await page.locator('body').getAttribute('data-admin-style'), 'dark');
        assert.deepEqual(errors, []);
        console.log('PASS: browser colors, accent switching, custom colors, reload, keyboard toggle and invalid accent');
    } finally {
        if (browser) await browser.close();
        await new Promise(resolve => server.close(resolve));
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
