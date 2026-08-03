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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * There must be exactly one definition of each form renderer, and the global renderer must
 * always be one of them.
 *
 * Three copies of core renderers had accumulated under the TinyMCE image-manager plugins. Each
 * was a stale snapshot of core -- diffed against core with the escaping patch stripped out, the
 * only differences were core changes the fork never received: the tab interface, the escape
 * trait, the shared toolbar. Nothing bespoke, so nothing to port.
 *
 * Two things made that worse than "an out-of-date file on one screen":
 *
 *  1. `xoopsimagemanager.php` included a fork and installed it via
 *     `XoopsFormRenderer::getInstance()->set()`, so every form rendered during that request used
 *     the unpatched copy -- the security patch was switched off for the endpoint.
 *
 *  2. Both files declared `class XoopsFormRendererBootstrap5`, and `include_once` keys on PATH,
 *     not class name. On a request that included a fork, that name was bound to the fork for the
 *     whole request -- so the five themes that call `set(new XoopsFormRendererBootstrap5())` in
 *     their own `theme_autorun.php` also silently got the fork. The theme thought it had selected
 *     core.
 *
 * Copy-and-fork is how this returns, and a diff review will not catch it -- the copy looks like a
 * new file, not a change. These assertions catch it.
 *
 * SCOPE: this protects CORE. Contributed modules are not in a core checkout and can do exactly
 * the same thing; this test cannot see them. Making `XoopsFormRenderer::set()` reject a renderer
 * whose class file lives outside `class/xoopsform/renderer/` would cover that, but it is an API
 * change -- `set(XoopsFormRendererInterface $renderer)` advertises custom renderers as a
 * supported extension point -- and belongs with the value-provenance work, not here.
 */
final class XoopsFormRendererUniquenessTest extends TestCase
{
    private const RENDERER_DIR = '/class/xoopsform/renderer';

    /**
     * Directories walked. Deliberately not all of XOOPS_ROOT_PATH: `modules/` on a real install
     * is tens of thousands of files, and reading every one of them to find a handful would make
     * this test slow enough that someone eventually deletes it. These are the trees core ships
     * and controls -- which is the same scope the class docblock claims, no more.
     */
    private const SCANNED_DIRS = ['/class', '/include', '/kernel', '/themes', '/modules/system'];

    /** @return iterable<\SplFileInfo> every .php file under the scanned directories */
    private function phpFiles(): iterable
    {
        foreach (self::SCANNED_DIRS as $dir) {
            $path = XOOPS_ROOT_PATH . $dir;
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && 'php' === strtolower($file->getExtension())) {
                    yield $file;
                }
            }
        }
    }

    private function relativePath(\SplFileInfo $file): string
    {
        return str_replace('\\', '/', substr($file->getPathname(), strlen(XOOPS_ROOT_PATH)));
    }

    /**
     * @return array<string, array<int, string>> class name => paths that declare it
     */
    private function rendererDeclarations(): array
    {
        $found = [];

        foreach ($this->phpFiles() as $file) {
            // Prefilter on filename: a renderer class always lives in a same-named file, and
            // this keeps the scan from reading every PHP file in core twice.
            if (!str_starts_with($file->getFilename(), 'XoopsFormRenderer')) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            if (preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+(XoopsFormRenderer\w*)/m', $source, $m)) {
                $found[$m[1]][] = $this->relativePath($file);
            }
        }

        return $found;
    }

    #[Test]
    public function eachRendererClassIsDeclaredExactlyOnce(): void
    {
        foreach ($this->rendererDeclarations() as $class => $paths) {
            self::assertCount(
                1,
                $paths,
                sprintf(
                    '%s is declared in %d files: %s. A second declaration shadows the first for the '
                    . 'whole request (include_once keys on path, not class name), so a stale copy can '
                    . 'silently replace the patched core class -- including for callers that believe '
                    . 'they selected core. Delete the copy and xoops_load() the core class instead.',
                    $class,
                    count($paths),
                    implode(', ', $paths)
                )
            );
        }
    }

    #[Test]
    public function everyRendererClassLivesInTheCoreRendererDirectory(): void
    {
        foreach ($this->rendererDeclarations() as $class => $paths) {
            foreach ($paths as $path) {
                self::assertStringStartsWith(
                    self::RENDERER_DIR . '/',
                    $path,
                    "$class is declared at $path, outside " . self::RENDERER_DIR
                        . '. Core renderers belong in one place; anything else is a fork waiting to drift.'
                );
            }
        }
    }

    /**
     * Nothing in core may install a renderer it loaded from outside the core renderer directory.
     *
     * The failure this catches is not "a renderer was set" -- themes legitimately do that in
     * `theme_autorun.php`. It is a `set()` paired with an `include`/`require` of a renderer file
     * from somewhere else, which is precisely what the image-manager endpoints did.
     */
    #[Test]
    public function noCoreFileIncludesARendererFromOutsideTheRendererDirectory(): void
    {
        $offenders = [];

        foreach ($this->phpFiles() as $file) {
            $relative = $this->relativePath($file);

            // The renderers themselves legitimately require their sibling interface and trait by
            // __DIR__; they ARE the renderer directory. Only look at everything else.
            if (str_starts_with($relative, self::RENDERER_DIR . '/')) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            if (!str_contains($source, 'XoopsFormRenderer')) {
                continue;
            }

            // A relative include of a concrete renderer CLASS file -- not the interface or the
            // trait, which a legitimate custom renderer may well need to pull in.
            $pattern = '/(include|require)(_once)?\s*[^;]*(__DIR__|\.\/|\.\.\/)[^;]*'
                . 'XoopsFormRenderer(?!Interface|TabRendererInterface|ValueEscapeTrait)\w*\.php/';

            if (preg_match($pattern, $source)) {
                $offenders[] = $relative;
            }
        }

        self::assertSame(
            [],
            $offenders,
            'These files include a renderer by relative path instead of xoops_load()ing the core '
            . 'class, which is how a forked copy gets installed as the global renderer: '
            . implode(', ', $offenders)
        );
    }
}
