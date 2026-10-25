/* node browser-module-dark-mode.cjs <playwright-package> <htdocs> */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const {chromium} = require(process.argv[2] || 'playwright');
const root = path.resolve(process.argv[3]);
const theme = path.resolve(__dirname, '..');
const styles = [
    '/theme/css/style.css', '/theme/css/dark-mode.css', '/theme/css/module-dark-mode.css',
    '/htdocs/modules/xstyles/assets/css/gallery.css',
    '/htdocs/modules/moduleinstaller/assets/css/admin.css',
    '/htdocs/modules/debugbar/assets/admin-diagnostics.css',
    '/htdocs/modules/xthemes/css/themes.css',
    '/htdocs/Frameworks/moduleclasses/moduleadmin/css/admin.css',
    '/htdocs/xoops_lib/packages/xmf/src/Permissions/assets/workbench.css',
    '/htdocs/class/xoopseditor/sceditor/minified/themes/default.min.css'
];
const html = `<!doctype html><html><head><meta charset="utf-8">
${styles.map(url => '<link rel="stylesheet" href="' + url + '">').join('')}
<script src="/theme/js/dark-mode.js"></script><script src="/theme/js/editor-dark-mode.js"></script>
<script src="/htdocs/class/xoopseditor/sceditor/minified/sceditor.min.js"></script>
<script src="/htdocs/class/xoopseditor/sceditor/minified/formats/bbcode.js"></script>
</head><body><button id="darkModeToggle" type="button">Dark mode</button><div id="xo-content">
<div class="CPbigTitle">Feedback</div><textarea id="editor">[b]Keep bold[/b] [color=#ff0000]Keep color[/color]</textarea>
<div class="xmfwb"><details class="xmfwb-section" open><summary>Permissions <span class="xmfwb-section__count">2 granted</span></summary><div class="xmfwb-section__body"><table class="xmfwb-grid"><thead><tr><th>Item</th><th>Users</th></tr></thead><tbody><tr><th>Submit</th><td><input type="checkbox" checked></td></tr><tr><th>Approve</th><td class="is-changed">Changed</td></tr></tbody></table></div></details></div>
<div id="xs-gallery" class="xs-workbench"><header class="xs-header"><h1>Admin theme styles</h1><div class="xs-view-switch"><button aria-pressed="true">Single preview</button><button>Compare</button></div></header><div class="xs-sidebar"><nav class="xs-sections"><a href="#" aria-current="page">Typography</a></nav><input id="xs-filter" placeholder="Find an example"></div><section class="xs-example"><header class="xs-example-header"><h2>Heading hierarchy</h2></header><div class="xs-specimen"><h2>Sample heading</h2></div><details class="xs-source"><summary>Rendered preview HTML</summary><pre><code>Example</code></pre></details></section></div>
<div class="installer-dash-card"><h4>Installed modules</h4><span class="num">24</span></div><div id="installer-set-toolbar"><label>Apply set</label><span class="installer-set-toolbar-hint">Select a set</span></div>
<div class="installer-module-list"><table class="module"><tr class="even"><td>Quotes</td></tr><tr class="odd"><td>Private Messaging</td></tr><tr class="even installer-row-selected"><td>Selected module</td></tr></table></div><div class="installer-sticky-bar">Selection actions</div>
<div class="debugbar-diagnostics"><div class="debugbar-diagnostics__header"><h2>System diagnostics</h2><p>Runtime information</p><span class="debugbar-diagnostics__privacy">Read only</span></div><table class="debugbar-diagnostics__table"><thead><tr><th>Check</th><th>Value</th></tr></thead><tbody><tr><th>Runtime</th><td><span class="debugbar-diagnostics__status debugbar-diagnostics__status--ok">OK</span></td></tr><tr><th>Debug</th><td><span class="debugbar-diagnostics__status debugbar-diagnostics__status--warning">Review</span></td></tr></tbody></table></div>
<div id="px-menu-options"><a href="#" class="themes">Themes</a></div><div id="it-current-theme"><div class="theme_data"><strong>Version:</strong> 1.0</div></div><div class="theme_buttons"><a href="#"><span>Install</span></a></div><div class="it_config_container" style="display:block">Theme configuration</div>
</div><script>document.addEventListener('DOMContentLoaded', function () { sceditor.create(document.getElementById('editor'), {format:'bbcode', style:'/htdocs/class/xoopseditor/sceditor/minified/themes/content/default.min.css', width:'100%', height:180, toolbar:'bold,italic,underline|source|font,color'}); });</script></body></html>`;
const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost').pathname;
    if (url === '/') { res.setHeader('Content-Type', 'text/html'); return res.end(html); }
    const base = url.startsWith('/theme/') ? theme : root;
    const prefix = url.startsWith('/theme/') ? '/theme/' : '/htdocs/';
    const file = path.resolve(base, url.slice(prefix.length));
    if (!url.startsWith(prefix) || !file.startsWith(base + path.sep) || !/\.(css|js|png|gif)$/.test(file) || !fs.existsSync(file)) return res.writeHead(404).end();
    res.setHeader('Content-Type', file.endsWith('.css') ? 'text/css' : file.endsWith('.js') ? 'text/javascript' : 'image/' + path.extname(file).slice(1));
    res.end(fs.readFileSync(file));
});
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    let browser;
    try {
        browser = await chromium.launch({channel:'chrome',headless:true});
        const page = await browser.newPage({viewport:{width:1280,height:1000}});
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        await page.goto('http://127.0.0.1:' + server.address().port);
        await page.waitForSelector('.sceditor-container iframe');
        const initial = await page.evaluate(() => sceditor.instance(document.getElementById('editor')).val());
        const style = (selector, prop = 'backgroundColor') => page.locator(selector).first().evaluate((el, prop) => getComputedStyle(el)[prop], prop);
        const surfaces = ['.xmfwb-section', '.xmfwb-grid tbody td', '.xs-header', '.xs-sidebar', '.xs-example-header', '.xs-source', '#xs-filter', '.installer-dash-card', '#installer-set-toolbar', '.installer-module-list tr.even td', '.installer-module-list tr.odd td', '.installer-module-list .installer-row-selected td', '.installer-sticky-bar', '.debugbar-diagnostics__privacy', '.debugbar-diagnostics__table tbody th', '.debugbar-diagnostics__table tbody tr:nth-child(even) td', '#px-menu-options', '.theme_buttons a', '.it_config_container', '.sceditor-container', '.sceditor-toolbar', '.sceditor-group'];
        const light = {};
        for (const selector of surfaces) light[selector] = await style(selector);
        await page.locator('#darkModeToggle').click();
        const failures = [];
        for (const selector of surfaces) {
            const bg = await style(selector);
            if (bg === light[selector]) failures.push(selector + ': unchanged ' + bg);
        }
        assert.deepEqual(failures, [], 'Module surfaces must respond to dark mode');
        assert.equal(await style('.xmfwb-grid td.is-changed'), 'rgb(57, 51, 41)');
        assert.equal(await style('.installer-row-selected td'), 'rgb(44, 65, 55)');
        for (const selector of ['.CPbigTitle', '.xs-header h1', '.installer-dash-card h4', '.installer-set-toolbar-hint', '.debugbar-diagnostics__header h2', '.debugbar-diagnostics__table tbody th', '.debugbar-diagnostics__status--warning', '#it-current-theme strong']) {
            const ratio = await page.locator(selector).first().evaluate(el => {
                const rgb = value => value.match(/[\d.]+/g).slice(0, 3).map(Number);
                const luminance = value => rgb(value).map(c => c / 255).map(c => c <= .04045 ? c / 12.92 : ((c + .055) / 1.055) ** 2.4).reduce((sum, c, i) => sum + c * [.2126, .7152, .0722][i], 0);
                let surface = el;
                while (surface.parentElement && getComputedStyle(surface).backgroundColor === 'rgba(0, 0, 0, 0)') surface = surface.parentElement;
                const fg = luminance(getComputedStyle(el).color), bg = luminance(getComputedStyle(surface).backgroundColor);
                return (Math.max(fg, bg) + .05) / (Math.min(fg, bg) + .05);
            });
            assert.ok(ratio >= 4.5, selector + ' contrast: ' + ratio);
        }
        const frame = page.frameLocator('.sceditor-container iframe');
        await page.waitForFunction(() => document.querySelector('.sceditor-container iframe').contentDocument.documentElement.classList.contains('xoops-admin-dark'));
        assert.equal(await frame.locator('body').evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(28, 34, 42)');
        assert.equal(await page.evaluate(() => sceditor.instance(document.getElementById('editor')).val()), initial);
        await page.locator('.sceditor-button-source').click();
        assert.equal(await style('.sceditor-container textarea'), 'rgb(28, 34, 42)');
        await page.locator('.sceditor-button-source').click();
        await page.screenshot({path:path.join(require('node:os').tmpdir(),'default-module-dark-mode.png'),fullPage:true});
        await page.locator('#darkModeToggle').click();
        for (const selector of surfaces) assert.equal(await style(selector), light[selector], 'Light restoration: ' + selector);
        assert.equal(await page.evaluate(() => sceditor.instance(document.getElementById('editor')).val()), initial);
        assert.equal(await frame.locator('html').evaluate(el => el.classList.contains('xoops-admin-dark')), false);
        await page.locator('#darkModeToggle').click();
        await page.evaluate(() => {
            const input = document.createElement('textarea'); input.id = 'late-editor'; document.body.appendChild(input);
            sceditor.create(input, {format:'bbcode',style:'/htdocs/class/xoopseditor/sceditor/minified/themes/content/default.min.css',width:500,height:100});
        });
        await page.waitForFunction(() => [...document.querySelectorAll('.sceditor-container iframe')].every(f => f.contentDocument.documentElement.classList.contains('xoops-admin-dark')));
        assert.deepEqual(errors, []);
        console.log('PASS: five module stylesheets, real SCEditor/BBCode, source mode, late editor creation and light-mode restoration');
    } finally { if (browser) await browser.close(); await new Promise(resolve => server.close(resolve)); }
})().catch(e => { console.error(e); process.exitCode = 1; });
