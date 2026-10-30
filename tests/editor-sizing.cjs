// Run: node tests/editor-sizing.cjs (requires playwright-core, Microsoft Edge and PHP).
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');
const root = path.join(__dirname, '../htdocs');
const field = execFileSync('php', [path.join(__dirname, 'fixtures/editor-sizing.php')], { encoding: 'utf8' });

(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        const page = await browser.newPage();
        await page.route('**/*', async route => {
            const url = new URL(route.request().url());
            if (url.hostname !== 'xoops.test') return route.abort();
            if (url.pathname === '/test-image.svg') {
                return route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="800"><rect width="1600" height="800" fill="blue"/></svg>' });
            }
            const file = path.join(root, url.pathname);
            if (!fs.existsSync(file)) return route.fulfill({ status: 404, body: '' });
            await route.fulfill({ contentType: file.endsWith('.css') ? 'text/css' : 'text/javascript', body: fs.readFileSync(file) });
        });
        const cases = [
            ['default', 360], ['default', 720], ['default', 1080],
            ...['crumb5', 'claudedesign6', 'codex6', 'fluenta3', 'fluentcx3', 'mytheme5', 'mytheme6', 'xtailwind', 'xtailwind2', 'xoops2020', 'xoops2026'].filter(theme => fs.existsSync(path.join(root, 'themes', theme, 'theme.tpl'))).map(theme => [theme, 720])
        ];
        for (const [theme, width] of cases) {
            const template = fs.readFileSync(path.join(root, 'themes', theme, 'theme.tpl'), 'utf8');
            const styles = [...template.matchAll(/<link\b[^>]*href="([^"]+)"[^>]*>/gi)]
                .map(match => match[1]
                    .replace(/<\{xoImgUrl\s+'([^']+)'\}>/g, `http://xoops.test/themes/${theme}/$1`)
                    .replace(/<\{xoImgUrl\}>|<\{\$xoops_imageurl[^}]*\}>/g, `http://xoops.test/themes/${theme}/`)
                    .replace(/<\{\$xoops_url[^}]*\}>/g, 'http://xoops.test'))
                .filter(url => url.startsWith('http://xoops.test/') && url.endsWith('.css') && !url.includes('.rtl.'))
                .map(url => '<link rel="stylesheet" href="' + url + '">').join('');
            await page.setContent('<!doctype html><head>' + styles + '</head><body class="c6-site"><main id="column" class="c6-module c6-newbb" style="width:' + width + 'px">'
                + '<form class="c6-newbb-form"><table width="100%"><tr><td>Subject</td><td style="white-space:nowrap"><input size="150"></td></tr>'
                + '<tr><td>Icons</td><td style="white-space:nowrap">' + '<div class="form-check form-check-inline"><input type="radio" name="icon"><label class="form-check-label"><img src="http://xoops.test/test-image.svg" width="16" height="16"></label></div>'.repeat(7) + '</td></tr>'
                + '<tr><td>Options</td><td style="white-space:nowrap">' + '<label>Option <input type="checkbox"></label> '.repeat(10) + '</td></tr>'
                + '<tr><td>Message</td><td style="white-space:nowrap">' + field + '</td></tr></table></form></main>');
            await page.evaluate(() => {
                const table = document.createElement('table');
                table.id = 'unrelated-table';
                table.innerHTML = '<tbody><tr><td style="white-space:nowrap">Other</td><td>Table</td></tr></tbody>';
                document.querySelector('form').appendChild(table);
            });
            const unrelated = await page.evaluate(() => {
                const table = document.getElementById('unrelated-table');
                return { layout: getComputedStyle(table).tableLayout, whiteSpace: getComputedStyle(table.rows[0].cells[0]).whiteSpace };
            });
            assert.equal(unrelated.layout, 'auto', `${theme}: unrelated tables keep auto layout`);
            assert.equal(unrelated.whiteSpace, 'nowrap', `${theme}: unrelated table cell wrapping is unchanged`);
            const fits = () => page.evaluate(() => {
                const column = document.getElementById('column').getBoundingClientRect();
                return [...document.querySelectorAll('form, form > table, #message, .sceditor-container')]
                    .every(el => el.getBoundingClientRect().right <= column.right + 1);
            });
            assert.ok(await fits(), `${theme}: plain editor and form controls must fit a ${width}px content column`);
            assert.ok(await page.evaluate(() => new Set([...document.querySelectorAll('.form-check-inline')].map(el => el.getBoundingClientRect().x)).size > 1),
                `${theme}: inline message icons must share rows`);
            const editorRoot = 'http://xoops.test/class/xoopseditor/sceditor';
            await page.addStyleTag({ url: editorRoot + '/minified/themes/default.min.css' });
            await page.addStyleTag({ url: editorRoot + '/css/xoops-icons.css' });
            for (const src of ['/minified/sceditor.min.js', '/minified/formats/bbcode.js', '/js/xoops-bbcode.js']) {
                await page.addScriptTag({ url: editorRoot + src });
            }
            await page.evaluate(editorRoot => {
                document.getElementById('message').value = '[img width=800]http://xoops.test/test-image.svg[/img]\n\n[b]Heading[/b]';
                sceditor.create(document.getElementById('message'), {
                    format: 'bbcode', width: '100%', height: '400px', emoticonsEnabled: false,
                    style: editorRoot + '/css/content.css', toolbar: 'bold,italic|source'
                });
            }, editorRoot);
            await page.frameLocator('.sceditor-container iframe').locator('img').waitFor();
            await page.waitForFunction(() => document.querySelector('.sceditor-container iframe').contentDocument.readyState === 'complete');
            assert.equal(await page.evaluate(() => sceditor.instance(document.getElementById('message')).sourceMode()), false,
                `${theme}: visual mode must be the default`);
            await page.evaluate(() => sceditor.instance(document.getElementById('message')).sourceMode(true));
            const sourceView = await page.evaluate(() => {
                const container = document.querySelector('.sceditor-container');
                const textarea = container.querySelector('textarea');
                return { weight: getComputedStyle(textarea).fontWeight, visible: textarea.getBoundingClientRect().height > 0,
                    frameHidden: container.querySelector('iframe').getBoundingClientRect().height === 0 };
            });
            assert.equal(sourceView.weight, '400', `${theme}: source text must use normal weight`);
            assert.ok(sourceView.visible && sourceView.frameHidden, `${theme}: source mode must display only the source textarea`);
            await page.evaluate(() => sceditor.instance(document.getElementById('message')).sourceMode(false));
            assert.ok(await page.evaluate(() => document.querySelector('.sceditor-container textarea').getBoundingClientRect().height === 0),
                `${theme}: visual mode must hide the source textarea`);
            assert.ok(await fits(), `visual editor must fit a ${width}px content column`);
            const image = await page.evaluate(() => {
                const frame = document.querySelector('.sceditor-container iframe');
                const img = frame.contentDocument.querySelector('img');
                return { width: img.getBoundingClientRect().width, available: frame.contentDocument.documentElement.clientWidth,
                    source: sceditor.instance(document.getElementById('message')).val() };
            });
            assert.ok(image.width > 0 && image.width <= image.available, 'image preview must shrink to fit the editing area');
            assert.ok(image.width <= 800, 'small images must not be enlarged');
            assert.ok(image.source.startsWith('[img width=800]'), 'fitting the preview must preserve the saved width');
        }
        for (const version of ['5', '7']) {
            await page.goto('about:blank');
            const config = JSON.parse(execFileSync('php', [path.join(__dirname, 'fixtures/tinymce-content.php'), version], { encoding: 'utf8' }));
            assert.ok(String(config.content_css).includes('/class/xoopseditor/content.css'), `TinyMCE ${version} must load shared content sizing`);
            await page.setContent('<!doctype html><main id="column" style="width:360px"><form>' + field + '</form></main>');
            await page.addScriptTag({ url: `http://xoops.test/class/xoopseditor/tinymce${version}/js/tinymce/tinymce.min.js` });
            await page.evaluate(async config => {
                document.getElementById('message').value = '<p><img src="http://xoops.test/test-image.svg" width="800" height="400"></p>';
                await tinymce.init({ selector: '#message', license_key: 'gpl', skin: false, toolbar: false, menubar: false,
                    width: '100%', content_css: config.content_css });
            }, config);
            await page.waitForFunction(() => document.querySelector('iframe').contentDocument.readyState === 'complete');
            const image = await page.evaluate(() => {
                const editor = tinymce.get('message');
                const img = editor.getBody().querySelector('img');
                return { width: img.getBoundingClientRect().width, available: editor.getDoc().documentElement.clientWidth, source: editor.getContent() };
            });
            assert.ok(image.width > 0 && image.width <= image.available, `TinyMCE ${version} image must fit its frame`);
            assert.ok(image.source.includes('width="800"'), `TinyMCE ${version} must keep stored image width`);
        }
        console.log('PASS: form sizing and inline icons with available theme styles; SCEditor and TinyMCE 5/7 image previews preserve stored widths.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
