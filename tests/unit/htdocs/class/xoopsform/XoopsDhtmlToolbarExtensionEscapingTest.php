<?php
/**
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright   2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author      XOOPS Project
 */

declare(strict_types=1);

namespace xoopsform;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use XoopsDhtmlToolbar;
use XoopsFormDhtmlTextArea;

require_once XOOPS_ROOT_PATH . '/class/xoopseditor/dhtmltextarea/XoopsDhtmlToolbar.php';

xoops_load('XoopsFormDhtmlTextArea');
xoops_load('XoopsFormTextArea');
xoops_load('XoopsFormElement');

/**
 * A value crossing from PHP into a JavaScript string literal must be JSON-encoded, not
 * HTML-escaped.
 *
 * The toolbar and every TextSanitizer extension build an `onclick` handler by interpolating a
 * value into a JS string literal inside an HTML attribute. The browser decodes HTML entities
 * BEFORE the JS parser runs, so `htmlspecialchars()` is the wrong tool: `&quot;` is a real `"`
 * by the time JavaScript sees it, and a textarea id of `x");alert(1);//` closes the literal and
 * executes. `json_encode()` with the four HEX flags emits `\uXXXX` escapes, which survive the
 * attribute decode as data.
 *
 * THREE THINGS ARE ASSERTED, AND THE LAST TWO ARE EASY TO MISS
 * -----------------------------------------------------------
 * 1. Per extension, INDIVIDUALLY: the payload is not callable after an attribute decode.
 *
 *    Individually matters. `textsanitizer/config.dist.php` enables `youtube` and `image` and
 *    disables `mp3`, `wmp`, `mms`, `rtsp` and `soundcloud`; `wiki` is conditional on the
 *    mediawiki module. A test that renders the whole toolbar and scans whatever appears is
 *    satisfied by the always-present core buttons, so six of the seven extension patches could
 *    be reverted with the suite still green. Each extension is therefore loaded and called
 *    directly, bypassing the config gate.
 *
 * 2. The emitted handler PARSES. A handler that renders perfectly and only fails on click is
 *    the failure mode this area keeps producing, and a pattern match cannot see it.
 *
 * 3. Every emitted `onclick` is SINGLE-quote delimited.
 *
 *    The safety does not come from JSON_HEX_QUOT, which is the easy assumption. `json_encode`
 *    wraps its own output in literal double quotes. Inside `onclick='...'` those are harmless
 *    and the delimiter is protected by JSON_HEX_APOS. Inside `onclick="..."` the wrapping
 *    quotes close the attribute on the first argument, and JSON_HEX_QUOT does not help -- it
 *    escapes quotes in the VALUE, not the pair `json_encode` adds around it. The design rests
 *    on an invariant nothing else enforces: an extension author writing `onclick="` would
 *    reintroduce the injection while `jsCall()` looks untouched.
 */
final class XoopsDhtmlToolbarExtensionEscapingTest extends TestCase
{
    /** A textarea id that closes a JS string literal and starts a new statement. */
    private const MALICIOUS_NAME = 'x");alert(1);//';

    /**
     * Every extension that hand-builds an onclick handler, whether or not config.dist.php
     * enables it. Adding a new extension here is cheaper than discovering it was never covered.
     *
     * @return array<string, array{0: string, 1: string}> label => [directory, class]
     */
    public static function extensionProvider(): array
    {
        return [
            'youtube'    => ['youtube', 'MytsYoutube'],
            'mp3'        => ['mp3', 'MytsMp3'],
            'wmp'        => ['wmp', 'MytsWmp'],
            'mms'        => ['mms', 'MytsMms'],
            'rtsp'       => ['rtsp', 'MytsRtsp'],
            'soundcloud' => ['soundcloud', 'MytsSoundcloud'],
            'wiki'       => ['wiki', 'MytsWiki'],
        ];
    }

    /** Load an extension class directly, sidestepping MyTextSanitizer's config gate. */
    private function loadExtension(string $dir, string $class): object
    {
        $file = XOOPS_ROOT_PATH . '/class/textsanitizer/' . $dir . '/' . $dir . '.php';
        self::assertFileExists($file, "Extension $dir is missing from the tree.");

        require_once XOOPS_ROOT_PATH . '/class/module.textsanitizer.php';
        require_once $file;

        self::assertTrue(class_exists($class, false), "Extension $dir did not declare $class.");

        // MyTextSanitizerExtension::__construct() takes the sanitizer; core builds
        // extensions as `new $class($this)` from inside MyTextSanitizer.
        return new $class(\MyTextSanitizer::getInstance());
    }

    /** `encode()` returns [buttonHtml, javascript]; only the button markup carries handlers. */
    private function buttonHtmlFor(string $dir, string $class): string
    {
        $encoded = $this->loadExtension($dir, $class)->encode(self::MALICIOUS_NAME);
        $html    = is_array($encoded) ? (string) ($encoded[0] ?? '') : (string) $encoded;

        self::assertNotSame('', $html, "$dir::encode() produced no button markup.");

        return $html;
    }

    /**
     * Strip JS string literals, leaving the code around them.
     *
     * This is what separates "the payload is present" from "the payload is executable". A
     * correctly escaped handler still CONTAINS the characters `alert(1)` -- inside a string
     * literal, as inert data:
     *
     *     xoopsCodeYoutube("x\u0022);alert(1);\/\/","Enter YouTube URL","Height:","Enter Width");
     *
     * So asserting that `alert(1)` is absent from the raw handler FAILS ON CORRECT OUTPUT, and
     * the shortest way to make such an assertion pass is to weaken the escaping. It is the same
     * mistake as grepping rendered HTML for `onfocus=` and calling it a breakout when the output
     * was `value='x&apos; onfocus=&apos;...'` -- correctly escaped and inert.
     *
     * Removing the literals first asks the question that matters: is the payload CODE? A payload
     * that closed its literal leaves `);alert(1);//` behind here; one that did not leaves an
     * empty argument list.
     *
     * Deliberately small: it tracks the opening quote and honours backslash escapes, which is all
     * a one-line call expression needs. An unterminated literal swallows the rest of the string,
     * which is itself the signature of a break-out and shows up as a missing `)`.
     */
    private function codeOutsideStringLiterals(string $js): string
    {
        $out    = '';
        $quote  = null;
        $length = strlen($js);

        for ($i = 0; $i < $length; ++$i) {
            $char = $js[$i];

            if (null !== $quote) {
                if ('\\' === $char) {
                    ++$i;                 // skip the escaped character, whatever it is
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ('"' === $char || "'" === $char) {
                $quote = $char;
                continue;
            }

            $out .= $char;
        }

        return $out;
    }

    /** @return array<int, string> the onclick attribute bodies in the given markup */
    private function onclickBodies(string $html): array
    {
        preg_match_all("/onclick='([^']*)'/", $html, $matches);

        return $matches[1];
    }

    #[Test]
    #[DataProvider('extensionProvider')]
    public function eachExtensionEmitsInertJavaScriptForAHostileTextareaId(string $dir, string $class): void
    {
        $handlers = $this->onclickBodies($this->buttonHtmlFor($dir, $class));
        self::assertNotEmpty($handlers, "$dir::encode() produced no single-quoted onclick handler.");

        foreach ($handlers as $handler) {
            // What the JS parser actually receives: the HTML attribute decode happens first.
            $decoded = html_entity_decode($handler, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            self::assertDoesNotMatchRegularExpression(
                '/"\s*\)\s*;\s*alert\s*\(/',
                $decoded,
                "$dir: the injected payload closed its JS string literal, making alert( callable"
            );
            self::assertStringNotContainsString(
                'alert',
                $this->codeOutsideStringLiterals($decoded),
                "$dir: the injected payload escaped its JS string literal and became executable "
                . "code. The payload is EXPECTED to appear inside the literal -- that is what "
                . "correct escaping looks like -- so this fires only when it appears outside one."
            );
        }
    }

    #[Test]
    #[DataProvider('extensionProvider')]
    public function eachExtensionEmitsAWellFormedCallExpression(string $dir, string $class): void
    {
        foreach ($this->onclickBodies($this->buttonHtmlFor($dir, $class)) as $handler) {
            $decoded = html_entity_decode($handler, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            self::assertMatchesRegularExpression(
                '/^\s*[A-Za-z_$][\w$]*\s*\(.*\)\s*;?\s*$/s',
                $decoded,
                "$dir: the onclick handler is not a single well-formed call expression"
            );
            // Counted OUTSIDE string literals. The hostile textarea id carries its own
            // parentheses, so a naive count over the whole handler reports an imbalance on
            // perfectly correct output -- and the shortest way to make that pass is to stop
            // escaping.
            $code = $this->codeOutsideStringLiterals($decoded);

            self::assertSame(
                substr_count($code, '('),
                substr_count($code, ')'),
                "$dir: unbalanced parentheses in the onclick handler -- it would throw on click"
            );
        }
    }

    /** The delimiter invariant the json_encode design silently depends on. See the class docblock. */
    #[Test]
    #[DataProvider('extensionProvider')]
    public function eachExtensionUsesSingleQuotedOnclickAttributes(string $dir, string $class): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/onclick\s*=\s*"/',
            $this->buttonHtmlFor($dir, $class),
            "$dir: onclick is double-quoted. json_encode wraps its output in double quotes, so the "
            . "first argument closes the attribute and JSON_HEX_QUOT does not help. Use onclick='...'."
        );
    }

    /** The same invariant for the toolbar's own buttons and dropdowns. */
    #[Test]
    public function theToolbarItselfUsesSingleQuotedOnclickAttributes(): void
    {
        $element = new XoopsFormDhtmlTextArea('Caption', self::MALICIOUS_NAME, 'value', 5, 50, 'xoopsHiddenText');
        $toolbar = new XoopsDhtmlToolbar();
        $html    = $toolbar->renderCodeButtons($element) . $toolbar->renderTypography($element);

        self::assertDoesNotMatchRegularExpression('/onclick\s*=\s*"/', $html, 'Toolbar emitted a double-quoted onclick.');
        self::assertNotEmpty($this->onclickBodies($html), 'Toolbar emitted no single-quoted onclick handlers.');
    }

    /**
     * Whole-toolbar smoke test. Kept because it exercises the core buttons the per-extension
     * tests do not reach -- but it is NOT the coverage guarantee; see the class docblock.
     */
    #[Test]
    public function toolbarOnclickHandlersSurviveAttributeDecodeWithoutExecutingInjectedCode(): void
    {
        $element  = new XoopsFormDhtmlTextArea('Caption', self::MALICIOUS_NAME, 'value', 5, 50, 'xoopsHiddenText');
        $toolbar  = new XoopsDhtmlToolbar();
        $handlers = $this->onclickBodies($toolbar->renderCodeButtons($element));

        self::assertNotEmpty($handlers, 'No onclick handlers were found in the rendered toolbar markup.');

        foreach ($handlers as $handler) {
            $decoded = html_entity_decode($handler, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            preg_match('/^([A-Za-z0-9_]+)\(/', $decoded, $fnMatch);
            $handlerName = $fnMatch[1] ?? $handler;

            self::assertDoesNotMatchRegularExpression(
                '/"\s*\)\s*;\s*alert\s*\(/',
                $decoded,
                "{$handlerName}: injected payload closed its JS string literal, letting alert( become callable"
            );
            self::assertStringNotContainsString(
                'alert',
                $this->codeOutsideStringLiterals($decoded),
                "{$handlerName}: the injected payload escaped its JS string literal and became "
                . "executable code"
            );
        }
    }
}
