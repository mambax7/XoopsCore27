<?php

declare(strict_types=1);

/**
 * BBCode written by SCEditor's toolbar must render on display.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/module.textsanitizer.php';

#[CoversClass(MyTextSanitizer::class)]
final class MyTextSanitizerEditorTagsTest extends TestCase
{
    private function display(string $text): string
    {
        // No constructor: no extension or config loading. [li] belongs to the li
        // extension, so list items are covered by the markup checks, not here.
        $myts = (new ReflectionClass(MyTextSanitizer::class))->newInstanceWithoutConstructor();
        $myts->config = ['extensions' => []];

        return $myts->displayTarea($text, 0, 0, 1, 1, 1);
    }

    #[Test]
    public function toolbarTagsRender(): void
    {
        $out = $this->display("[sub]a[/sub] [sup]b[/sup] [s]c[/s]\n[justify]j[/justify]\n[hr]\n[ol]x[/ol]");

        $this->assertStringContainsString('<sub>a</sub> <sup>b</sup> <s>c</s>', $out);
        $this->assertStringContainsString('<div style="text-align: justify;">j</div>', $out);
        $this->assertStringContainsString('<hr><ol>x</ol>', $out, 'no line break after a rule');
        $this->assertStringNotContainsString('[', $out);
    }

    #[Test]
    public function textDirectionTagsRender(): void
    {
        $out = $this->display('[rtl]r[/rtl] [ltr]l[/ltr] [rtl]open');

        $this->assertStringContainsString('<div dir="rtl">r</div>', $out);
        $this->assertStringContainsString('<div dir="ltr">l</div>', $out);
        $this->assertStringContainsString('[rtl]open', $out, 'an unclosed tag stays text');
    }

    #[Test]
    public function tableSourceLineBreaksDoNotLeakIntoTheTable(): void
    {
        $out = $this->display("[table][tr][th]h[/th]\n[/tr]\n[tr][td]c[/td]\n[/tr]\n[/table]");

        $this->assertStringContainsString('<table class="table"><tr><th>h</th></tr><tr><td>c</td></tr></table>', $out);
    }

    #[Test]
    public function authoredBreaksSurviveWithoutLineBreakConversion(): void
    {
        $myts = (new ReflectionClass(MyTextSanitizer::class))->newInstanceWithoutConstructor();
        $myts->config = ['extensions' => []];
        $html = '<table class="table"><tr><td>a</td><br></tr></table><hr><br>b';

        $this->assertSame($html, $myts->displayTarea($html, 1, 0, 0, 0, 0));
    }

    #[Test]
    public function quotedUrlKeepsItsQueryString(): void
    {
        $out = $this->display('[url="https://x.test/?a=1&b=2"]q[/url]');

        $this->assertStringContainsString('<a href="https://x.test/?a=1&b=2"', $out);
    }

    #[Test]
    public function quotesAroundAnInjectedAttributeStayText(): void
    {
        $out = $this->display('[url=&quot;x&quot; onmouseover=&quot;alert(1)&quot;]q[/url]');

        $this->assertStringNotContainsString('<a', $out);
    }

    #[Test]
    public function encodedQuotesInsideAnHtmlAttributeStayEncoded(): void
    {
        $myts = (new ReflectionClass(MyTextSanitizer::class))->newInstanceWithoutConstructor();
        $myts->config = ['extensions' => []];
        $html = '<img src="x" title="[a=&quot; onerror=alert(1) x=&quot;]">';

        $this->assertSame($html, $myts->displayTarea($html, 1, 0, 1, 1, 0));
        $this->assertStringContainsString(
            '<span style="font-size: x-large;">s</span>',
            $myts->displayTarea('[size="x-large"]s[/size]', 1, 0, 1, 1, 0),
            'raw quotes still decode with HTML on',
        );
    }

    #[Test]
    public function aColorWithItsOwnHashIsNotDoubled(): void
    {
        $this->assertStringContainsString('<span style="color: #000000;">c</span>', $this->display('[color=#000000]c[/color]'));
        $this->assertStringContainsString('<span style="color: #FF0000;">c</span>', $this->display('[color=FF0000]c[/color]'));
    }

    #[Test]
    public function unclosedStructureStaysText(): void
    {
        $out = $this->display('[table]open [ol]list [sub]x');

        $this->assertStringNotContainsString('<table', $out);
        $this->assertStringNotContainsString('<ol', $out);
        $this->assertStringNotContainsString('<sub', $out);
    }

    #[Test]
    public function tagContentIsStillEscaped(): void
    {
        $out = $this->display('[td]<script>x</script>[/td] [sub]<b>[/sub]');

        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringNotContainsString('<b>', $out);
    }
}
