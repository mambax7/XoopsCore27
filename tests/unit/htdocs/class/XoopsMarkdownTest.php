<?php

declare(strict_types=1);

/**
 * Regression tests for XoopsMarkdown and the MyTextSanitizer display path.
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
require_once XOOPS_ROOT_PATH . '/class/xoopsmarkdown.php';


#[CoversClass(XoopsMarkdown::class)]
#[CoversClass(MyTextSanitizer::class)]
final class XoopsMarkdownTest extends TestCase
{
    private MyTextSanitizer $myts;

    protected function setUp(): void
    {
        // No constructor: skips extension/config loading, as tests/markdown.php does.
        $this->myts = (new ReflectionClass(MyTextSanitizer::class))->newInstanceWithoutConstructor();
        $this->myts->config = ['extensions' => []];
    }

    private function display(string $text): string
    {
        return $this->myts->displayTarea($text, 0, 0, 1, 1, 1);
    }

    #[Test]
    public function bracketedMarkupStaysEncodedWhenHtmlIsOff(): void
    {
        $out = $this->display('hi [<img src=x onerror=alert(1)>] and [&lt;b&gt;]');

        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringNotContainsString('<b>', $out);
        $this->assertStringContainsString('&lt;img', $out);
    }

    #[Test]
    public function quotedAttributesFromVisualEditorsStillDecode(): void
    {
        foreach (['[size="x-large"]big[/size]', '[size=&quot;x-large&quot;]big[/size]'] as $input) {
            $out = $this->display($input);
            $this->assertStringContainsString('x-large', $out, $input);
            $this->assertStringNotContainsString('[size', $out, $input);
            $this->assertStringNotContainsString('&quot;', $out, $input);
        }
    }

    #[Test]
    public function quotedAttributeValuesCannotCarryMarkup(): void
    {
        $out = $this->display('[color=&quot;red&lt;b&gt;&quot;]x[/color]');

        $this->assertStringNotContainsString('<b>', $out);
        $this->assertStringNotContainsString('<span', $out);
    }

    #[Test]
    public function markdownPlaceholderCannotBreakOutOfAGeneratedAttribute(): void
    {
        $out = $this->display("[url=[xoops:markdown=\"1\"]\n\" onmouseover=\"alert(1)//\n[/xoops:markdown]]hover[/url]");

        $this->assertStringNotContainsString('onmouseover="alert', $out);
        $this->assertStringNotContainsString('XOOPSMARKDOWN', $out);
        $this->assertDoesNotMatchRegularExpression('/<a [^>]*<p>/', $out);
    }

    #[Test]
    public function markdownPlaceholderCannotBreakOutOfAQuotedAttributeHoldingAGreaterThan(): void
    {
        $text = '<img title="a>' . XoopsMarkdown::wrap('[x](http://e.test)') . '">';
        $out  = $this->myts->displayTarea($text, 1, 0, 1, 1, 1);

        $this->assertStringNotContainsString('href=', $out);
        $this->assertStringNotContainsString('XOOPSMARKDOWN', $out);
    }

    #[Test]
    public function embeddedMarkdownInAQuotedReplyStillRenders(): void
    {
        $out = $this->display("[quote]\n" . XoopsMarkdown::wrap('**bold**') . "\n[/quote]\nreply");

        $this->assertStringContainsString('<strong>bold</strong>', $out);
        $this->assertStringNotContainsString('XOOPSMARKDOWN', $out);
    }

    #[Test]
    public function nestedMarkerDoesNotFatal(): void
    {
        $nested = "[xoops:markdown=\"1\"]\na [xoops:markdown=\"1\"] b\n[/xoops:markdown] tail";

        $this->assertIsString($this->display($nested));
        $this->assertIsString(XoopsMarkdown::editorSource('[quote]' . $nested . '[/quote]'));
    }

    #[Test]
    public function preparePostLeavesOtherFieldsAndUnchangedWhitespaceAlone(): void
    {
        $legacy = "legacy  \n";
        $post = [
            'message'  => $legacy,
            'password' => ' secret ',
            '_xoops_markdown' => ['message'],
            '_xoops_markdown_state' => [hash('sha256', 'message') => [
                'initial' => XoopsMarkdown::fingerprint($legacy),
                'marked'  => '0',
            ]],
            '_xoops_markdown_save' => '1',
        ];
        $request = $post;

        XoopsMarkdown::preparePost($post, $request);

        $this->assertSame($legacy, $post['message'], 'unchanged text must not be converted');
        $this->assertSame(' secret ', $post['password']);
    }

    #[Test]
    public function preparePostWrapsAnEditedFieldOnSave(): void
    {
        $post = [
            'message' => "# edited\n",
            '_xoops_markdown' => ['message'],
            '_xoops_markdown_state' => [hash('sha256', 'message') => [
                'initial' => XoopsMarkdown::fingerprint('original'),
                'marked'  => '0',
            ]],
            '_xoops_markdown_save' => '1',
        ];
        $request = $post;

        XoopsMarkdown::preparePost($post, $request);

        $this->assertSame("# edited\n", XoopsMarkdown::source($post['message']));
        $this->assertSame($post['message'], $request['message']);
    }

    #[Test]
    public function restoreWithNoPlaceholdersReturnsTheTextUnchanged(): void
    {
        $this->assertSame('<p>a</p> b', XoopsMarkdown::restore('<p>a</p> b', []));
    }

    #[Test]
    public function previewFindsTheDocumentByTrimmedTextToo(): void
    {
        $edited = "    indented code\n";
        $post = [
            'message' => $edited,
            '_xoops_markdown' => ['message'],
            '_xoops_markdown_state' => [hash('sha256', 'message') => [
                'initial' => XoopsMarkdown::fingerprint('original'),
                'marked'  => '0',
            ]],
            '_xoops_markdown_save' => '0',
        ];
        $request = $post;

        XoopsMarkdown::preparePost($post, $request);

        $this->assertSame($edited, $post['message'], 'a preview never wraps the submitted field');
        $this->assertSame($edited, XoopsMarkdown::source(XoopsMarkdown::previewSource($edited)));
        $this->assertSame($edited, XoopsMarkdown::source(XoopsMarkdown::previewSource(trim($edited))));
    }

    #[Test]
    public function aFieldsExactTextWinsOverAnotherFieldsTrimmedText(): void
    {
        $state = ['initial' => XoopsMarkdown::fingerprint('original'), 'marked' => '0'];
        foreach ([['a', 'b'], ['b', 'a']] as $order) {
            $post = [
                'a' => 'hello',
                'b' => "hello
",
                '_xoops_markdown' => $order,
                '_xoops_markdown_state' => [hash('sha256', 'a') => $state, hash('sha256', 'b') => $state],
            ];
            $request = $post;

            XoopsMarkdown::preparePost($post, $request);

            $this->assertSame('hello', XoopsMarkdown::source(XoopsMarkdown::previewSource('hello')));
            $this->assertSame("hello
", XoopsMarkdown::source(XoopsMarkdown::previewSource("hello
")));
        }
    }

    #[Test]
    public function aMalformedStateIsIgnored(): void
    {
        foreach (['bad', [hash('sha256', 'm') => 'bad']] as $state) {
            $post = ['m' => 'x', '_xoops_markdown' => ['m'], '_xoops_markdown_state' => $state];
            $request = $post;

            XoopsMarkdown::preparePost($post, $request);

            $this->assertSame('x', $post['m']);
        }
    }

    #[Test]
    public function aTrimmedTextSharedByTwoFieldsPreviewsAsItself(): void
    {
        $state = ['initial' => XoopsMarkdown::fingerprint('original'), 'marked' => '0'];
        $post = [
            'a' => '    **hello**',
            'b' => "**hello**
",
            '_xoops_markdown' => ['a', 'b'],
            '_xoops_markdown_state' => [hash('sha256', 'a') => $state, hash('sha256', 'b') => $state],
        ];
        $request = $post;

        XoopsMarkdown::preparePost($post, $request);

        $this->assertSame('**hello**', XoopsMarkdown::source(XoopsMarkdown::previewSource('**hello**')));
        $this->assertSame('    **hello**', XoopsMarkdown::source(XoopsMarkdown::previewSource('    **hello**')));
    }

    #[Test]
    public function renderFallsBackToEscapedTextWithoutParsedown(): void
    {
        // Parsedown is already loaded here, so render in a PHP whose trust path has no vendor tree.
        $code = 'define("XOOPS_ROOT_PATH", ' . var_export(XOOPS_ROOT_PATH, true) . ');'
              . 'define("XOOPS_TRUST_PATH", ' . var_export(sys_get_temp_dir() . '/no-vendor-' . getmypid(), true) . ');'
              . 'require XOOPS_ROOT_PATH . "/class/xoopsmarkdown.php";'
              . 'echo XoopsMarkdown::render("**<b>x</b>**");';
        $proc = proc_open([PHP_BINARY, '-d', 'display_errors=stdout', '-r', $code], [1 => ['pipe', 'w']], $pipes);
        $out  = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $status = proc_close($proc);

        $this->assertSame(0, $status, $out);
        $this->assertStringContainsString('Parsedown is not installed', $out);
        $this->assertStringContainsString('<pre>**&lt;b&gt;x&lt;/b&gt;**</pre>', $out);
    }
}
