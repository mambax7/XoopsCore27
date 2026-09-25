/**
 * SCEditor XOOPS BBCode round-trip check (visual <-> source).
 * Run in the browser console on any page with an SCEditor field named "message"
 * (for example a newbb edit page). Nothing is saved; the field is restored.
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license GNU GPL 2 or later
 */
(function () {
    var inst = sceditor.instance(document.getElementById('message'));
    var original = inst.val();
    // null = must come back unchanged. Other expected values: SCEditor quotes
    // values containing spaces or '='; a bare [url] becomes the attribute form the
    // server decodes; image-manager captions lose characters image.php rejects.
    var cases = {
        '[mp3]https://example.test/a.mp3[/mp3]': null,
        '[img id=123]caption[/img]': null,
        '[img align=left id=5]My photo[/img]': null,
        '[img align=center id=7]Centered[/img]': null,
        '[img]https://example.test/p.png[/img]': null,
        '[img align=right]https://example.test/p.png[/img]': null,
        '[img width=300]https://example.test/p.png[/img]': null,
        '[img align=center width=200]https://example.test/p.png[/img]': null,
        '[email]a@example.test[/email]': null,
        '[siteurl=modules/news/]News[/siteurl]': null,
        '[url=https://xoops.org]XOOPS[/url]': null,
        '[url]https://xoops.org[/url]': '[url=https://xoops.org]https://xoops.org[/url]',
        '[url]https://x.test/?a=1&b=2[/url]': '[url="https://x.test/?a=1&b=2"]https://x.test/?a=1&b=2[/url]',
        '[img]https://x.test/p.png?a=1&b=2[/img]': null,
        '[siteurl=javascript:alert(1)]x[/siteurl]': null,
        '[img id=9]Tom & "Jerry" (1)?[/img]': '[img id=9]Tom  Jerry 1[/img]',
        '[url=https://x.test/?a=1&b=2]q[/url]': '[url="https://x.test/?a=1&b=2"]q[/url]',
        '[youtube]dQw4w9WgXcQ[/youtube]': null,
        '[youtube=640,360]dQw4w9WgXcQ[/youtube]': null,
        '[youtube=16,9]dQw4w9WgXcQ[/youtube]': null,
        '[youtube]https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=5[/youtube]': null,
        '[youtube]not a video[/youtube]': null,
        '[youtube]abc.def:ghi[/youtube]': null,
        '[youtube]https://youtu.be/s4I4zaY5B6sX[/youtube]': null,
        '[youtube]https://notyoutube.com/watch?v=s4I4zaY5B6s[/youtube]': null,
        '[quote]hi[/quote]': null,
        '[code]<b>x</b>[/code]': null,
        '[color=FF0000]r[/color]': null,
        '[size=x-large]big[/size]': null,
        '[font=Arial]f[/font]': null,
        '[font=Times New Roman]t[/font]': '[font="Times New Roman"]t[/font]',
        '[left]l[/left]': null,
        '[center]c[/center]': null,
        '[rtl]r[/rtl]': null,
        '[ltr]l[/ltr]': null,
        '[right]r[/right]': null,
        '[u]u[/u] [s]s[/s] [d]d[/d]': null,
        '[iframe=400]https://example.test/?a=1&b=2[/iframe]': null,
        '[soundcloud]https://soundcloud.com/x/y[/soundcloud]': null,
        '[wmp=400,300]https://example.test/a.wmv[/wmp]': null,
        '[mms=400,300]mms://example.test/a[/mms]': null,
        '[rtsp=400,300]rtsp://example.test/a[/rtsp]': null,
        '[b]:)[/b] :-D :sick: <3 :devil:': null,
        '[[WikiPage]]': null,
        '[unknowntag]x[/unknowntag]': null,
        '[mp3]https://x.test/a"onerror="alert(1).mp3[/mp3]': null,
        '[img id=1]x" onerror="alert(2)[/img]': '[img id=1]x onerror=alert2[/img]'
    };
    var failures = [];
    Object.keys(cases).forEach(function (input) {
        var expected = cases[input] === null ? input : cases[input];
        inst.sourceMode(true);
        inst.val(input);
        inst.sourceMode(false);
        if (inst.getBody().querySelector('[onerror], a[href^="javascript"]')) {
            failures.push('markup or script link injected in visual view: ' + input);
        }
        if (/^\[youtube[^\]]*\]dQw4w9WgXcQ/.test(input) && !inst.getBody().querySelector('iframe[data-youtube-id="dQw4w9WgXcQ"]')) {
            failures.push('no video player in visual view: ' + input);
        }
        if (/^\[youtube\](abc\.def|https:\/\/youtu\.be\/s4I4zaY5B6sX|https:\/\/notyoutube\.com)/.test(input) && inst.getBody().querySelector('iframe')) {
            failures.push('video player for an id the server rejects: ' + input);
        }
        inst.sourceMode(true);
        var actual = inst.val().trim();
        if (actual !== expected) {
            failures.push(input + '  =>  ' + actual);
        }
    });
    // The autoyoutube plugin inserts an iframe instead of going through [youtube].
    inst.sourceMode(false);
    inst.val('');
    inst.wysiwygEditorInsertHtml('<iframe data-youtube-id="dQw4w9WgXcQ" src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"></iframe>');
    inst.sourceMode(true);
    if (inst.val().trim() !== '[youtube]dQw4w9WgXcQ[/youtube]') {
        failures.push('autoyoutube iframe  =>  ' + inst.val().trim());
    }
    inst.val(original);
    inst.updateOriginal();
    console.log(failures.length ? 'FAIL\n' + failures.join('\n') : 'PASS: ' + Object.keys(cases).length + ' SCEditor round trips');
    return failures;
}());
