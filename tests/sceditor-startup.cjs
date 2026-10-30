// Run: node tests/sceditor-startup.cjs (requires playwright-core, Microsoft Edge and PHP).
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');
const root = path.join(__dirname, '../htdocs');
const editorHtml = execFileSync('php', [path.join(__dirname, 'fixtures/sceditor-startup.php')], { encoding: 'utf8' });
const draft = '[b]Unsaved draft[/b]\n\nKeep this paragraph';

(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        const page = await browser.newPage();
        await page.route('http://xoops.test/**', route => {
            const url = new URL(route.request().url());
            if (url.pathname === '/edit') return route.fulfill({ contentType: 'text/html', body: '<!doctype html><form>' + editorHtml + '</form>' });
            const file = path.join(root, url.pathname);
            return route.fulfill({ contentType: file.endsWith('.css') ? 'text/css' : 'text/javascript', body: fs.readFileSync(file) });
        });
        await page.goto('http://xoops.test/edit');
        assert.equal(await page.evaluate(() => sceditor.instance(document.getElementById('message')).sourceMode()), false,
            'fresh editor must start in visual mode');
        await page.evaluate(draft => {
            localStorage.setItem('sce-autodraft-/edit', JSON.stringify({
                value: draft, sourceMode: true, caret: { start: 0, end: 0 }, time: Date.now()
            }));
        }, draft);
        await page.reload();
        const state = await page.evaluate(() => {
            const editor = sceditor.instance(document.getElementById('message'));
            return { sourceMode: editor.sourceMode(), source: editor.val() };
        });
        assert.equal(state.sourceMode, false, 'restored drafts must start in visual mode');
        assert.equal(state.source, draft, 'starting visually must preserve the restored draft and paragraph breaks');
        await page.evaluate(() => sceditor.instance(document.getElementById('message')).sourceMode(true));
        assert.equal(await page.evaluate(() => sceditor.instance(document.getElementById('message')).sourceMode()), true,
            'the source switch must still work after initialization');
        console.log('PASS: actual SCEditor adapter restores draft text in visual mode; source switch remains available.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
