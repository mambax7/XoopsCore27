<?php

declare(strict_types=1);

namespace xoopsclass;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the Smarty debug-console gate in htdocs/class/template.php.
 *
 * XoopsTpl used to set `debugging_ctrl = 'URL'` for every non-zero debug mode.
 * That makes Smarty look for SMARTY_DEBUG in the query string and, on finding
 * it, render its debug console — every assigned template variable with its
 * value — with no authentication of any kind. `SMARTY_DEBUG=on` also set a bare
 * SMARTY_DEBUG cookie which kept the console on for subsequent requests.
 *
 * Debug modes 1 and 2 select XOOPS's own logger output, not Smarty's, so any
 * site running them handed anonymous visitors a disclosure the administrator
 * never enabled. Mode 3, the mode that does want the console, sets `debugging`
 * directly and never consults the URL, so the setting only ever had an effect
 * where it should not have been active.
 *
 * The constructor cannot be exercised without a booted Smarty, so this asserts
 * against the source, in the manner of the other source-inspecting tests here.
 *
 * @category  Test
 * @package   XOOPS
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
class XoopsTplDebugControlTest extends TestCase
{
    private string $source = '';

    protected function setUp(): void
    {
        $path = XOOPS_ROOT_PATH . '/class/template.php';
        if (! is_file($path)) {
            self::markTestSkipped('class/template.php is not reachable from XOOPS_ROOT_PATH');
        }
        $this->source = (string) file_get_contents($path);
    }

    #[Test]
    public function urlDrivenDebuggingIsNeverEnabled(): void
    {
        // The assignment, not the word: the explanatory comment above the gate
        // names the setting and must not trip this.
        self::assertDoesNotMatchRegularExpression(
            '/\$this->debugging_ctrl\s*=/',
            $this->source,
            'XoopsTpl must not set debugging_ctrl: it lets any visitor open the Smarty debug console with a URL parameter'
        );
    }

    #[Test]
    public function smartyDebuggingIsEnabledOnlyForDebugModeThree(): void
    {
        // Scoped to the constructor: xoops_setDebugging() further down is a
        // deprecated public setter and is not part of this gate.
        $constructor = $this->constructorBody();

        preg_match_all('/\$this->debugging\s*=\s*([^;]+);/', $constructor, $matches);

        self::assertCount(
            1,
            $matches[0],
            'expected exactly one assignment to $this->debugging in the XoopsTpl constructor'
        );
        self::assertSame('true', trim($matches[1][0]));

        // And it must sit behind a comparison against mode 3, in either order.
        self::assertMatchesRegularExpression(
            '/if\s*\(\s*(?:3\s*==+\s*\$xoopsConfig\[.debug_mode.\]|\$xoopsConfig\[.debug_mode.\]\s*==+\s*3)/',
            $constructor,
            'the Smarty debug console must be gated on debug_mode 3, the mode that selects it'
        );
    }

    /**
     * Source of __construct(), from its signature to the start of the next
     * method declaration.
     */
    private function constructorBody(): string
    {
        $start = strpos($this->source, 'public function __construct(');
        self::assertNotFalse($start, 'XoopsTpl::__construct() not found');

        $next = strpos($this->source, 'public function ', $start + 1);

        return false === $next
            ? substr($this->source, $start)
            : substr($this->source, $start, $next - $start);
    }

    #[Test]
    public function debuggingIsNotEnabledForTheLoggerDebugModes(): void
    {
        // Modes 1 and 2 are the XOOPS logger. Nothing in this file may turn the
        // Smarty console on for them, directly or via a truthiness check on
        // debug_mode that would catch every non-zero value.
        self::assertDoesNotMatchRegularExpression(
            '/if\s*\(\s*\$xoopsConfig\[.debug_mode.\]\s*\)/',
            $this->source,
            'a truthiness test on debug_mode would re-enable Smarty debugging for the logger modes'
        );
    }
}
