// Run: node tests/sceditor-layout.cjs (requires playwright-core and Microsoft Edge).
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const { chromium } = require('playwright-core');
const root = path.join(__dirname, '../htdocs/class/xoopseditor/sceditor');

(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        const page = await browser.newPage();
        await page.setContent('<!doctype html><div style="white-space:nowrap;width:360px"><textarea id="message">[b]Heading[/b]\n\nParagraph</textarea></div>');
        for (const file of ['stylev2.css', 'style2.css', 'edit.css']) {
            const themeFile = path.join(root, '../../../themes/xoops2020/css', file);
            if (fs.existsSync(themeFile)) await page.addStyleTag({ path: themeFile });
        }
        for (const file of ['minified/themes/default.min.css', 'css/xoops-icons.css']) {
            await page.addStyleTag({ path: path.join(root, file) });
        }
        for (const file of ['minified/sceditor.min.js', 'minified/formats/bbcode.js', 'js/xoops-bbcode.js']) {
            await page.addScriptTag({ path: path.join(root, file) });
        }
        await page.evaluate(() => sceditor.create(document.getElementById('message'), {
            format: 'bbcode', width: '100%', height: '400px', emoticonsEnabled: false,
            toolbar: 'bold,italic,underline,strike,subscript,superscript|left,center,right,justify,ltr,rtl|font,size,color,removeformat|cut,copy,paste,pastetext|bulletlist,orderedlist,indent,outdent,table|link,unlink,email,image,youtube|quote,code|horizontalrule,date,time,emoticon|print,maximize,source'
        }));
        for (const width of [360, 240, 1129]) {
            await page.evaluate(width => { document.querySelector('body > div').style.width = width + 'px'; }, width);
            const layout = await page.evaluate(() => {
                const container = document.querySelector('.sceditor-container').getBoundingClientRect();
                return [...document.querySelectorAll('.sceditor-toolbar .sceditor-button')].every(button => {
                    const box = button.getBoundingClientRect();
                    return box.right <= container.right && box.bottom <= document.querySelector('.sceditor-toolbar').getBoundingClientRect().bottom;
                });
            });
            assert.ok(layout, `all toolbar buttons must wrap inside an editor ${width}px wide`);
        }
        assert.equal(await page.evaluate(() => sceditor.instance(document.getElementById('message')).val()), '[b]Heading[/b]\n\nParagraph');
        console.log('PASS: toolbar wraps under inherited nowrap; BBCode blank lines survive.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
