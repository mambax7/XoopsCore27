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

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use XoopsFormTextArea;

require_once XOOPS_ROOT_PATH . '/class/xoopsform/formelement.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formbutton.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formtext.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formtextarea.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formcolorpicker.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formpassword.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRendererInterface.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRendererLegacy.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRendererBootstrap3.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRendererBootstrap4.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRendererBootstrap5.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRendererTailwind.php';

/**
 * A form element's value must never escape the context it is rendered into --
 * neither a quoted HTML attribute, nor a <textarea> body.
 *
 * Every renderer interpolated $element->getValue() straight into a quoted attribute (button,
 * color picker, password, text) and straight into a <textarea> body (plain textarea and the
 * DHTML textarea), so a value containing that quote, or the literal string "</textarea>",
 * broke out of the element and the rest parsed as markup. Any field whose value can come
 * from user input -- a search box redisplaying its term, a profile field after a validation
 * failure, a comment preview -- was an injection point.
 *
 * WHAT IS ASSERTED
 * ----------------
 * Attribute sites: containment, measured against a benign baseline render of the same
 * element. A hostile value may not ADD an element, a <script>, an <img>, or an event-handler
 * attribute that the benign render did not also produce. The assertions are deliberately
 * directional -- only increases fail. Injection can only add nodes, and some renderers emit
 * their assets once per request (XoopsFormRendererTailwind guards its colour-picker <script>),
 * so a strict equality assertion would report the SECOND render of a colour picker as a
 * regression. A warm-up render burns that one-shot emission before the baseline is taken.
 *
 * Textarea sites: the literal breakout payload `a</textarea><script>x</script>` must not
 * reach the output as `</textarea><script>`.
 *
 * WHAT IS NOT ASSERTED
 * --------------------
 * Byte-identity of the value against arbitrary input. XoopsFormRendererValueEscapeTrait
 * still decodes before it encodes, so a raw value containing a literal `&quot;` is
 * normalised to `"`. That is a deliberate transitional trade -- the decode is what stops
 * the ~95% of core that hands renderers an ALREADY-escaped value from being
 * double-escaped -- and it is the ambiguity the provenance work removes. Pinning
 * byte-identity for every input here would fail for a reason this change is not
 * responsible for and cannot fix without an API change. What IS pinned is that a value
 * carrying no bare entities survives the attribute (or textarea body) unchanged, which is
 * the part this change owns.
 *
 * OUT OF SCOPE, DELIBERATELY
 * --------------------------
 * Element-TEXT contexts. renderFormLabel emits its value as markup on every renderer
 * because XoopsFormLabel is documented as carrying markup, and Bootstrap4/Bootstrap5 emit a
 * button's value as <button> text. The latter IS injectable -- see
 * {@see self::KNOWN_TEXT_CONTEXT_GAPS} and the pin test at the bottom of this class -- but
 * closing it changes behaviour for callers that put icon markup in a caption, so it is
 * tracked rather than folded into a fix that has no compatibility question attached.
 */
final class XoopsFormRendererEscapingTest extends TestCase
{
    /** Values that close an attribute and start something else. */
    private const PAYLOADS = [
        'single quote breakout' => "x' onfocus='alert(1)' autofocus x",
        'double quote breakout' => 'x" onfocus="alert(1)" autofocus x',
        'tag breakout'          => '"><script>alert(1)</script>',
        'entity then image'     => '`&quot;&gt;<img src=x onerror=alert(1)>',
        'whitespace and amp'    => "a\nb\tc & d",
    ];

    /**
     * Payloads carrying no '<', so they are inert in element TEXT but still close a quoted
     * attribute.
     *
     * KNOWN_TEXT_CONTEXT_GAPS excludes a METHOD; the gap it documents is a CONTEXT. Bootstrap4
     * and Bootstrap5 renderFormButton also write value= and title= attributes, so skipping the
     * whole method dropped the containment assertion from those too -- reverting just those two
     * attribute sites to raw getValue() left this suite green with a live breakout reintroduced.
     * These payloads restore attribute coverage on the pinned methods without asserting anything
     * about the text gap they legitimately document.
     */
    private const ATTRIBUTE_ONLY_PAYLOADS = [
        'single quote breakout' => "x' onfocus='alert(1)' autofocus x",
        'double quote breakout' => 'x" onfocus="alert(1)" autofocus x',
    ];

    /** Value that closes a <textarea> and injects a <script>. */
    private const TEXTAREA_BREAKOUT_PAYLOAD = 'a</textarea><script>x</script>';

    /**
     * renderer short name => render method, for text contexts still unescaped.
     *
     * These are asserted to STILL be injectable, so that closing the gap fails
     * this test and forces the exclusion to be removed rather than leaving a
     * silently over-broad skip behind.
     */
    private const KNOWN_TEXT_CONTEXT_GAPS = [
        'Bootstrap4' => ['renderFormButton', 'renderFormButtonTray'],
        'Bootstrap5' => ['renderFormButton', 'renderFormButtonTray'],
    ];

    /**
     * Element factories for the methods listed in {@see self::KNOWN_TEXT_CONTEXT_GAPS}, keyed
     * by render method name, used only by the pin test below (these methods are not exercised
     * by {@see self::aHostileValueCannotLeaveItsAttribute} because their value lands in
     * element TEXT, not an attribute).
     *
     * @return array<string, callable(string): object>
     */
    private function knownGapFactories(): array
    {
        return [
            'renderFormButton'      => static fn (string $v) => new \XoopsFormButton('caption', 'fld', $v, 'submit'),
            'renderFormButtonTray'  => static fn (string $v) => new \XoopsFormButtonTray('fld', $v, 'submit'),
        ];
    }

    public static function rendererProvider(): array
    {
        return [
            'Legacy'     => ['Legacy'],
            'Bootstrap3' => ['Bootstrap3'],
            'Bootstrap4' => ['Bootstrap4'],
            'Bootstrap5' => ['Bootstrap5'],
            'Tailwind'   => ['Tailwind'],
        ];
    }

    /**
     * The elements whose value lands in an HTML attribute on every renderer.
     *
     * @return array<string, callable(string): object>
     */
    private function elementFactories(): array
    {
        return [
            'renderFormButton'      => static fn (string $v) => new \XoopsFormButton('caption', 'fld', $v, 'submit'),
            'renderFormColorPicker' => static fn (string $v) => new \XoopsFormColorPicker('caption', 'fld', $v),
            'renderFormPassword'    => static fn (string $v) => new \XoopsFormPassword('caption', 'fld', 30, 255, $v),
            'renderFormText'        => static fn (string $v) => new \XoopsFormText('caption', 'fld', 30, 255, $v),
        ];
    }

    #[Test]
    #[DataProvider('rendererProvider')]
    public function aHostileValueCannotLeaveItsAttribute(string $shortName): void
    {
        $class    = 'XoopsFormRenderer' . $shortName;
        $renderer = new $class();
        $skip     = self::KNOWN_TEXT_CONTEXT_GAPS[$shortName] ?? [];
        $checked  = 0;

        foreach ($this->elementFactories() as $method => $make) {
            if (!method_exists($renderer, $method)) {
                continue;
            }

            // A pinned method still writes attributes; only its TEXT context is excluded, so it
            // gets the payloads that cannot express themselves in text. See ATTRIBUTE_ONLY_PAYLOADS.
            $payloads = in_array($method, $skip, true) ? self::ATTRIBUTE_ONLY_PAYLOADS : self::PAYLOADS;

            // Burn any one-shot asset emission so it is not counted as injection.
            $this->render($renderer, $method, $make('warmup'));
            $baseline = $this->shapeOf($this->render($renderer, $method, $make('benign')));

            foreach ($payloads as $label => $payload) {
                $shape = $this->shapeOf($this->render($renderer, $method, $make($payload)));
                $where = sprintf('%s::%s with the "%s" payload', $class, $method, $label);

                self::assertLessThanOrEqual($baseline['elements'], $shape['elements'], "An element was injected by $where");
                self::assertLessThanOrEqual($baseline['scripts'], $shape['scripts'], "A <script> was injected by $where");
                self::assertLessThanOrEqual($baseline['images'], $shape['images'], "An <img> was injected by $where");
                self::assertSame([], array_values(array_diff($shape['handlers'], $baseline['handlers'])), "An event handler was injected by $where");

                // Node counts and handler names miss an injection that adds an attribute to the
                // element that is already there -- formaction, style, src, formtarget. Compare the
                // attribute NAME SET per element instead; a payload may change attribute values,
                // never which attributes exist.
                self::assertSame(
                    [],
                    array_values(array_diff($shape['attributes'], $baseline['attributes'])),
                    "An attribute was injected onto an existing element by $where"
                );

                $checked++;
            }
        }

        self::assertGreaterThan(0, $checked, "No attribute sites were exercised for $class.");
    }

    /**
     * Escaped as markup, but still readable back as data.
     *
     * The payload carries quotes but no bare entities, so the escape trait's
     * decode step is a no-op on it and the attribute must round-trip exactly.
     */
    #[Test]
    #[DataProvider('rendererProvider')]
    public function anEscapedValueStillParsesBackAsTheValue(string $shortName): void
    {
        $class    = 'XoopsFormRenderer' . $shortName;
        $renderer = new $class();

        if (!method_exists($renderer, 'renderFormText')) {
            self::markTestSkipped("$class has no renderFormText");
        }

        $payload = "x' onfocus='alert(1)\" \"";
        $html    = $this->render($renderer, 'renderFormText', new \XoopsFormText('caption', 'fld', 30, 255, $payload));
        $node    = (new DOMXPath($this->parse($html)))->query('//input[@value]')->item(0);

        self::assertNotNull($node, "$class::renderFormText produced no input carrying a value.");
        self::assertSame(
            $payload,
            $node->getAttribute('value'),
            "$class::renderFormText did not round-trip the value through the attribute."
        );
    }

    /**
     * Pins the excluded text contexts so they stay a tracked gap, not an oversight.
     *
     * Bootstrap4 and Bootstrap5 render a button's (and a button tray's submit button's)
     * value as <button> TEXT. When that is escaped, this test fails -- which is the
     * intended signal to delete the relevant entry (and, once the last one for a
     * renderer is gone, this test) and the KNOWN_TEXT_CONTEXT_GAPS entry, so the
     * containment test above starts covering the method.
     */
    #[Test]
    public function buttonCaptionRemainsAKnownUnescapedTextContext(): void
    {
        $factories = $this->knownGapFactories();
        $payload   = '<span class="fa fa-check"></span>';
        $checked   = 0;

        foreach (self::KNOWN_TEXT_CONTEXT_GAPS as $shortName => $methods) {
            $class    = 'XoopsFormRenderer' . $shortName;
            $renderer = new $class();

            foreach ($methods as $method) {
                $html = $this->render($renderer, $method, $factories[$method]($payload));

                self::assertStringContainsString(
                    $payload,
                    $html,
                    "$class::$method no longer emits raw markup in its caption. If that was intentional, "
                    . "remove '$method' from its KNOWN_TEXT_CONTEXT_GAPS entry so the containment test covers it."
                );

                $checked++;
            }
        }

        self::assertGreaterThan(0, $checked, 'No known-gap methods were exercised.');
    }

    // ---------------------------------------------------------------- textarea sites

    /**
     * XoopsFormTextArea::getValue() defaults to raw content (BC), and every renderer used
     * to interpolate that value directly between <textarea> tags, allowing a value
     * containing "</textarea>" to break out of the element and inject markup.
     */
    #[Test]
    #[DataProvider('rendererProvider')]
    public function textAreaBreakoutPayloadCannotEscapeTheElement(string $shortName): void
    {
        $class    = 'XoopsFormRenderer' . $shortName;
        $renderer = new $class();
        $element  = new XoopsFormTextArea('Bio', 'bio', self::TEXTAREA_BREAKOUT_PAYLOAD);

        $html = $this->render($renderer, 'renderFormTextArea', $element);

        self::assertStringNotContainsString(
            '</textarea><script>',
            $html,
            "$class: raw </textarea><script> must not reach the output unescaped"
        );
    }

    /**
     * The fix must be idempotent decode-then-encode: XOOPS callers overwhelmingly hand
     * elements an already-escaped value, so plain htmlspecialchars() would double-escape
     * nearly every form.
     *
     * Asserted against the PARSED textarea value, not the source inner HTML. The contract that
     * matters is `input -> HTML -> textarea.value -> submitted value`; comparing source text
     * asserts what PHP emitted, which can stay identical while the value a form actually
     * submits changes. (DOMDocument is closer to a browser than a string compare, but it is not
     * a browser -- the click/submit half of this contract still needs a real one.)
     */
    #[Test]
    #[DataProvider('rendererProvider')]
    public function alreadyEscapedTextAreaValueRoundTripsIdempotently(string $shortName): void
    {
        $class    = 'XoopsFormRenderer' . $shortName;
        $renderer = new $class();

        $original = "<b>bold</b> it's \"fine\" & ok";
        $escaped  = htmlspecialchars($original, ENT_QUOTES | ENT_HTML5);

        $element = new XoopsFormTextArea('Bio', 'bio', $escaped);
        $html    = $this->render($renderer, 'renderFormTextArea', $element);

        self::assertSame(
            $original,
            $this->parsedTextareaValue($html),
            "$class: an already-escaped value must reach the browser as the value the caller meant"
        );
    }

    /**
     * PINS A KNOWN LIMITATION -- this is not the behaviour we want.
     *
     * escapeElementValue() decodes before it encodes, which is correct for the ~95% of core that
     * hands renderers an ALREADY-escaped value and wrong for the minority that pass raw text. A
     * raw value containing a literal entity is therefore rewritten on resubmission:
     *
     *     raw input      helper output      parsed textarea value
     *     &amp;          &amp;              &
     *     &#60;          &lt;               <
     *     caf&eacute;    caf&amp;eacute;    caf&eacute;
     *
     * There is no heuristic that fixes this: raw `&amp;` and escaped `&` are identical bytes.
     * The fix is value provenance on XoopsFormElement (see the provenance issue), after which
     * this test flips from recording current behaviour to asserting correct behaviour.
     *
     * Until then it exists so the limitation is pinned rather than described -- if the parsed
     * value changes for any reason, this fails and names the reason.
     */
    #[Test]
    public function rawValueCarryingAnEntityIsRewrittenOnResubmission(): void
    {
        $renderer = new \XoopsFormRendererLegacy();

        $cases = [
            '&amp;'       => '&',
            '&#60;'       => '<',
            'caf&eacute;' => 'caf&eacute;',
        ];

        foreach ($cases as $rawInput => $expectedParsedValue) {
            $element = new XoopsFormTextArea('Bio', 'bio', $rawInput);
            $html    = $this->render($renderer, 'renderFormTextArea', $element);

            self::assertSame(
                $expectedParsedValue,
                $this->parsedTextareaValue($html),
                "Raw input '$rawInput' no longer round-trips as before. If value provenance has "
                . 'landed, update this test to assert the raw input survives unchanged and delete '
                . 'this note; otherwise something regressed.'
            );
        }
    }

    // ---------------------------------------------------------------- helpers

    /** Some renderers echo asset tags as a side effect; capture those too. */
    private function render(object $renderer, string $method, object $element): string
    {
        ob_start();
        $returned = $renderer->{$method}($element);

        return (string) ob_get_clean() . (string) $returned;
    }

    private function parse(string $html): DOMDocument
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<!doctype html><meta charset="utf-8"><body>' . $html . '</body>', LIBXML_NOERROR);
        libxml_clear_errors();

        return $doc;
    }

    /**
     * @return array{elements:int, scripts:int, images:int, handlers:array<int,string>, attributes:array<int,string>}
     */
    private function shapeOf(string $html): array
    {
        $doc        = $this->parse($html);
        $xpath      = new DOMXPath($doc);
        $handlers   = [];
        $attributes = [];
        $index      = 0;

        foreach ($xpath->query('//body//*') as $node) {
            foreach ($node->attributes ?? [] as $attribute) {
                if (0 === stripos($attribute->name, 'on')) {
                    $handlers[] = $node->nodeName . '@' . $attribute->name;
                }
                // Positional so that an attribute moving between elements is also a change.
                $attributes[] = $index . ':' . $node->nodeName . '@' . $attribute->name;
            }
            $index++;
        }
        sort($handlers);
        sort($attributes);

        return [
            'elements'   => $xpath->query('//body//*')->length,
            'scripts'    => $doc->getElementsByTagName('script')->length,
            'images'     => $doc->getElementsByTagName('img')->length,
            'handlers'   => $handlers,
            'attributes' => $attributes,
        ];
    }

    /**
     * The value a browser would put in `textarea.value` and submit -- i.e. the source text with
     * character references resolved, which is what the form contract is actually about.
     */
    private function parsedTextareaValue(string $html): string
    {
        $node = (new DOMXPath($this->parse($html)))->query('//textarea')->item(0);
        self::assertNotNull($node, 'No <textarea> was rendered.');

        return $node->textContent;
    }

    private static function extractTextareaInnerText(string $html): string
    {
        self::assertMatchesRegularExpression('/<textarea[^>]*>(.*)<\/textarea>/s', $html);
        preg_match('/<textarea[^>]*>(.*)<\/textarea>/s', $html, $matches);

        return $matches[1];
    }
}
