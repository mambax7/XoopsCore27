// Run: node tests/sceditor-grow.cjs (requires playwright-core, Microsoft Edge and PHP).
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');
const root = path.join(__dirname, '../htdocs');

(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        for (const [mode, height] of [['grow', '400px'], ['grow', '25rem'], ['grow', '50vh'], ['fixed', '400px']]) {
            const html = execFileSync('php', [path.join(__dirname, 'fixtures/sceditor-startup.php'), mode, height], { encoding: 'utf8' });
            const page = await browser.newPage();
            await page.route('http://xoops.test/**', route => {
                const url = new URL(route.request().url());
                if (url.pathname === '/edit') return route.fulfill({ contentType: 'text/html', body: '<!doctype html><link rel="stylesheet" href="http://xoops.test/class/xoopseditor/layout.css"><form style="width:720px"><div class="xoops-editor">' + html + '</div></form>' });
                const file = path.join(root, url.pathname);
                return route.fulfill({ contentType: file.endsWith('.css') ? 'text/css' : 'text/javascript', body: fs.readFileSync(file) });
            });
            await page.goto('http://xoops.test/edit');
            if (mode === 'grow') {
                await page.waitForFunction(() => document.querySelector('.sceditor-container').getBoundingClientRect().height > 800, null, { timeout: 3000 });
                const before = await page.evaluate(() => sceditor.instance(document.getElementById('message')).height());
                await page.frameLocator('.sceditor-container iframe').locator('body').press('Control+End');
                for (let i = 0; i < 6; i++) await page.frameLocator('.sceditor-container iframe').locator('body').press('Enter');
                await page.waitForFunction(before => sceditor.instance(document.getElementById('message')).height() > before, before, { timeout: 3000 });
                assert.ok(await page.evaluate(() => {
                    const frame = document.querySelector('iframe');
                    return frame.contentDocument.body.scrollHeight <= frame.contentDocument.documentElement.clientHeight + 3;
                }), 'growing editor must fit its visual content');
            } else {
                // Wait beyond the upstream 200ms auto-expand debounce before checking disabled mode.
                await page.waitForTimeout(350);
                assert.equal(await page.evaluate(() => document.querySelector('.sceditor-container').getBoundingClientRect().height), 400);
            }
            await page.close();
        }
        console.log('PASS: SCEditor grows for saved and typed content with px/rem/vh heights; disabled preference stays fixed.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
