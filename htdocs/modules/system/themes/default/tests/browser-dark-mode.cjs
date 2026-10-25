/* Usage: node browser-dark-mode.cjs <playwright-package> <htdocs> */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const http = require('node:http');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.argv[2] || 'playwright');
const root = path.resolve(process.argv[3] || path.join(__dirname, '../../../../../'));
const theme = path.resolve(__dirname, '..');
const fixture = path.join(os.tmpdir(), 'default-dark-fixture-' + process.pid + '.html');
execFileSync('php', [path.join(__dirname, 'template-dark-mode.php'), root, fixture], {stdio: 'inherit'});
const html = fs.readFileSync(fixture);
fs.unlinkSync(fixture);
const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost').pathname;
    if (url === '/') {
        res.setHeader('Content-Type', 'text/html');
        res.end(html);
        return;
    }
    let base;
    let relative;
    if (url.startsWith('/theme/')) {
        base = theme;
        relative = url.slice(7);
    } else if (url.startsWith('/icons/')) {
        base = path.join(root, 'modules/system/images/icons/default');
        relative = url.slice(7);
    } else if (['/admin.css', '/button.css', '/help.css'].includes(url)) {
        base = path.join(root, 'modules/system/css');
        relative = url.slice(1);
    } else if (url === '/jquery.js') {
        base = path.join(root, 'xoops_lib/Frameworks/jquery');
        relative = 'jquery.js';
    }
    const file = base && path.resolve(base, relative);
    if (!file || !file.startsWith(path.resolve(base) + path.sep)
        || !/\.(css|js|png|gif|jpg)$/.test(file) || !fs.existsSync(file)) {
        res.writeHead(404).end();
        return;
    }
    res.setHeader('Content-Type', file.endsWith('.css') ? 'text/css' : file.endsWith('.js') ? 'text/javascript' : 'image/' + path.extname(file).slice(1));
    res.end(fs.readFileSync(file));
});
function contrast(a, b) {
    const luminance = color => color.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => {
        v /= 255;
        return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4;
    }).reduce((sum, v, i) => sum + v * [.2126, .7152, .0722][i], 0);
    const x = luminance(a), y = luminance(b);
    return (Math.max(x, y) + .05) / (Math.min(x, y) + .05);
}
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    let browser;
    try {
        browser = await chromium.launch({channel: 'chrome', headless: true});
        const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        await page.goto('http://127.0.0.1:' + server.address().port);
        const style = (selector, prop) => page.locator(selector).first().evaluate((el, prop) => getComputedStyle(el)[prop], prop);
        assert.equal(await style('#xo-body', 'backgroundColor'), 'rgb(247, 247, 247)');
        assert.equal(await page.locator('#darkModeToggle .icon-moon').isVisible(), true);
        assert.equal(await page.locator('#darkModeToggle .icon-sun').isVisible(), false);
        await page.screenshot({path: path.join(os.tmpdir(), 'default-light-mode.png'), fullPage: true});
        await page.locator('#darkModeToggle').focus();
        assert.equal(await style('#darkModeToggle', 'outlineStyle'), 'solid');
        await page.keyboard.press('Space');
        assert.equal(await page.locator('#darkModeToggle').getAttribute('aria-pressed'), 'true');
        assert.equal(await page.locator('#darkModeToggle .icon-sun').isVisible(), true);
        assert.equal(await page.locator('#darkModeToggle .icon-moon').isVisible(), false);
        for (const accent of ['dark', 'silver', 'orange']) {
            await page.locator('.styleswitch[rel="' + accent + '"]').click();
            assert.equal(await style('body', 'backgroundColor'), 'rgb(25, 29, 35)');
            assert.equal(await style('#xo-body', 'backgroundColor'), 'rgb(32, 37, 44)');
            assert.equal(await style('tr.odd td', 'backgroundColor'), 'rgb(36, 42, 50)');
            assert.equal(await style('tr.even td', 'backgroundColor'), 'rgb(32, 37, 44)');
            assert.equal(await style('.confirmMsg', 'backgroundColor'), 'rgb(48, 45, 38)');
            for (const selector of ['#xo-body', 'tr.head td', 'tr.odd td', 'select', 'input[type="text"]', 'input[type="submit"]', '.tips', '.help', '#accordion h5', '.confirmMsg']) {
                const ratio = contrast(await style(selector, 'color'), await style(selector, 'backgroundColor'));
                assert.ok(ratio >= 4.5, accent + ' ' + selector + ' text contrast: ' + ratio);
            }
            for (const selector of ['select', 'input[type="text"]']) {
                const ratio = contrast(await style(selector, 'borderTopColor'), await style('fieldset', 'backgroundColor'));
                assert.ok(ratio >= 3, selector + ' control contrast: ' + ratio);
            }
            assert.ok(contrast(await style('tr.head a', 'color'), await style('tr.head td', 'backgroundColor')) >= 4.5);
            await page.reload();
            assert.equal(await page.locator('body').getAttribute('data-admin-style'), accent);
            assert.equal(await page.locator('#darkModeToggle').getAttribute('aria-pressed'), 'true');
        }
        await page.locator('.styleswitch[rel="dark"]').click();
        await page.screenshot({path: path.join(os.tmpdir(), 'default-dark-mode.png'), fullPage: true});
        await page.addStyleTag({content: 'body { --admin-action: #554477; }'});
        assert.equal(await style('input[type="submit"]', 'backgroundColor'), 'rgb(85, 68, 119)');
        await page.evaluate(() => { document.documentElement.dir = 'rtl'; document.body.classList.add('xoops-rtl'); });
        assert.equal(await style('#xo-headnav', 'float'), 'left');
        await page.screenshot({path: path.join(os.tmpdir(), 'default-dark-mode-rtl.png'), fullPage: true});
        await page.locator('#darkModeToggle').click();
        assert.equal(await style('#xo-body', 'backgroundColor'), 'rgb(247, 247, 247)');
        await page.reload();
        assert.equal(await page.locator('#darkModeToggle').getAttribute('aria-pressed'), 'false');
        await page.context().addCookies([{name: 'xoadmstyle', value: 'invalid', url: page.url()}]);
        await page.reload();
        assert.equal(await page.locator('body').getAttribute('data-admin-style'), 'dark');
        assert.deepEqual(errors, []);
        console.log('PASS: real templates, all accents, contrast, keyboard, icons, persistence, RTL, custom color and light-mode restoration');
    } finally {
        if (browser) await browser.close();
        await new Promise(resolve => server.close(resolve));
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
