<?php
/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once dirname(XOOPS_ROOT_PATH) . '/.githooks/lib/LanguageConstantGuard.php';

/**
 * Pre-commit rule 18 (warning only): a constant added to an existing English
 * language file must be read in one of three canonical guard forms.
 *
 * The rule is a whitelist on purpose: it accepts exact token shapes and
 * reports everything else, instead of trying to prove that arbitrary PHP
 * guards a read. Accepted: the inline ternary `defined('_X') ? _X : ...`, the
 * block `if (defined('_X')) { ... }`, and a file-level fallback
 * `defined('_X') || define('_X', 'text');` that covers later file-level reads.
 *
 * @category  Xoops
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class LanguageConstantGuardTest extends TestCase
{
    private const C = '_ZZ_PROBE';

    /**
     * Reported lines of _ZZ_PROBE in a snippet ("<?php" becomes line 1, so
     * snippet line n is file line n + 1), reporting on the given snippet
     * lines, or on all of them.
     *
     * @param list<int>|null $addedSnippetLines
     * @return list<int> snippet line numbers reported
     */
    private static function reported(string $code, ?array $addedSnippetLines = null): array
    {
        $added = [];
        foreach ($addedSnippetLines ?? range(1, substr_count($code, "\n") + 1) as $line) {
            $added[$line + 1] = true;
        }

        return array_map(
            static fn (array $r): int => $r['line'] - 1,
            LanguageConstantGuard::unguardedReads("<?php\n" . $code, self::C, $added)
        );
    }

    /** @return array<string, array{string}> */
    public static function accepted(): array
    {
        return [
            'inline, returned'              => ["return defined('_ZZ_PROBE') ? _ZZ_PROBE : 'x';"],
            'inline, assigned'              => ["\$v = defined('_ZZ_PROBE') ? _ZZ_PROBE : 'x';"],
            'inline, echoed'                => ["echo defined('_ZZ_PROBE') ? _ZZ_PROBE : '';"],
            'inline, short echo tag'        => ["?><?= defined('_ZZ_PROBE') ? _ZZ_PROBE : '' ?><?php"],
            'inline, array value and key'   => ["\$a = ['k' => defined('_ZZ_PROBE') ? _ZZ_PROBE : '', defined('_ZZ_PROBE') ? _ZZ_PROBE : ''];"],
            'inline, function argument'     => ["f(defined('_ZZ_PROBE') ? _ZZ_PROBE : 'x');"],
            'inline, named argument'        => ["f(value: defined('_ZZ_PROBE') ? _ZZ_PROBE : 'x');"],
            'inline, case label'            => ["switch (\$a) {\n    case defined('_ZZ_PROBE') ? _ZZ_PROBE : 'x':\n        break;\n}"],
            'named-argument label'          => ["f(_ZZ_PROBE: 1);\ng(\$a, _ZZ_PROBE: 2);"],
            'inline, compound assignment'   => ["\$s .= defined('_ZZ_PROBE') ? _ZZ_PROBE : '';"],
            'inline, fully qualified'       => ["\$v = \\defined('_ZZ_PROBE') ? \\_ZZ_PROBE : 'x';"],
            'inline over three lines'       => ["\$v = defined('_ZZ_PROBE')\n    ? _ZZ_PROBE\n    : 'x';"],
            'block'                         => ["if (defined('_ZZ_PROBE')) {\n    echo _ZZ_PROBE;\n}"],
            'block, nested inner block'     => ["if (defined('_ZZ_PROBE')) {\n    if (\$a) {\n        echo _ZZ_PROBE;\n    }\n    echo _ZZ_PROBE;\n}"],
            'block, constant()'             => ["if (defined('_ZZ_PROBE')) {\n    echo constant('_ZZ_PROBE');\n}"],
            'block, fully qualified call'   => ["if (\\defined('_ZZ_PROBE')) {\n    echo _ZZ_PROBE;\n}"],
            'file-level fallback'           => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\necho _ZZ_PROBE;"],
            'fallback, then a later block'  => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', \"text\");\n\$x = 1;\necho _ZZ_PROBE;"],
            'fallback, read beside an arrow function' => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\n\$v = [fn (\$a, \$b) => 1, _ZZ_PROBE];\nf(fn () => 1, _ZZ_PROBE);\n\$w = (fn () => 1) . _ZZ_PROBE;"],
            'fallback, read after a called arrow function' => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\n\$v = (fn () => 1)() . _ZZ_PROBE;"],
            'quoted name, comments, PHPDoc' => ["\$k = '_ZZ_PROBE'; // _ZZ_PROBE\n/** @see _ZZ_PROBE */\n/* _ZZ_PROBE */"],
            'property, method, class const' => ["\$o->_ZZ_PROBE;\n\$o->_ZZ_PROBE();\nFoo::_ZZ_PROBE;\n_ZZ_PROBE();"],
        ];
    }

    #[Test]
    #[DataProvider('accepted')]
    public function aCanonicalGuardOrANonReadIsNotReported(string $code): void
    {
        self::assertSame([], self::reported($code));
    }

    /** @return array<string, array{string, list<int>}> */
    public static function reportedShapes(): array
    {
        return [
            'bare read'                              => ['echo _ZZ_PROBE;', [1]],
            'fully qualified bare read'              => ['echo \\_ZZ_PROBE;', [1]],
            'constant() outside a guard'             => ["echo constant('_ZZ_PROBE');", [1]],
            'inline, wrong branch'                   => ["\$v = defined('_ZZ_PROBE') ? 'x' : _ZZ_PROBE;", [1]],
            'inline, negated'                        => ["\$v = !defined('_ZZ_PROBE') ? 'x' : _ZZ_PROBE;", [1]],
            'inline, compound condition'             => ["\$v = defined('_ZZ_PROBE') && \$a ? _ZZ_PROBE : '';", [1]],
            'inline, concatenation binds first'      => ["echo 'a' . defined('_ZZ_PROBE') ? _ZZ_PROBE : '';", [1]],
            'inline, read is not the whole branch'   => ["\$v = defined('_ZZ_PROBE') ? _ZZ_PROBE . 'x' : '';", [1]],
            'inline, constant() in the branch'       => ["\$v = defined('_ZZ_PROBE') ? constant('_ZZ_PROBE') : '';", [1]],
            'block, compound condition'              => ["if (defined('_ZZ_PROBE') && \$a) {\n    echo _ZZ_PROBE;\n}", [2]],
            'block, negated condition'               => ["if (!defined('_ZZ_PROBE')) {\n    echo _ZZ_PROBE;\n}", [2]],
            'block, else branch'                     => ["if (defined('_ZZ_PROBE')) {\n    \$x = 1;\n} else {\n    echo _ZZ_PROBE;\n}", [4]],
            'block, elseif is not a canonical form'  => ["if (\$a) {\n    \$x = 1;\n} elseif (defined('_ZZ_PROBE')) {\n    echo _ZZ_PROBE;\n}", [4]],
            'block, else if is not a canonical form' => ["if (\$a) {\n    \$x = 1;\n} else if (defined('_ZZ_PROBE')) {\n    echo _ZZ_PROBE;\n}", [4]],
            'block, alternative syntax'              => ["if (defined('_ZZ_PROBE')):\n    echo _ZZ_PROBE;\nendif;", [2]],
            'fallback inside alternative syntax'     => ["if (\$flag):\n    \$x = 1;\n    defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'x');\nendif;\necho _ZZ_PROBE;", [5]],
            'fallback inside a ternary-free foreach' => ["foreach (\$a as \$b):\n    defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'x');\nendforeach;\n\$v = \$c ? (\$d) : _ZZ_PROBE;", [4]],
            'constant() with a named argument'       => ["echo constant(name: '_ZZ_PROBE');", [1]],
            'read after the block'                   => ["if (defined('_ZZ_PROBE')) {\n    \$x = 1;\n}\necho _ZZ_PROBE;", [4]],
            'read before the fallback'               => ["echo _ZZ_PROBE;\ndefined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');", [1]],
            'read in a function after the fallback'  => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\nfunction f() {\n    return _ZZ_PROBE;\n}", [3]],
            'fallback inside a function'             => ["function f() {\n    defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\n}\necho _ZZ_PROBE;", [4]],
            'read in an arrow function after the fallback' => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\n\$f = fn () => _ZZ_PROBE;\necho _ZZ_PROBE;", [2]],
            'arrow function holding an anonymous class' => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\n\$f = fn () => [new class { public int \$v = 1; }, _ZZ_PROBE];", [2]],
            'arrow function, read in a nested call argument' => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\n\$f = fn () => g(1, _ZZ_PROBE);", [2]],
            'arrow function, read after a nested arrow' => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\n\$f = fn () => [fn () => 1, _ZZ_PROBE];", [2]],
            'arrow function, body with a low-precedence or' => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\n\$f = fn () => \$a or _ZZ_PROBE;", [2]],
            'second arrow function in an array'      => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\n\$a = [fn () => 1, fn () => _ZZ_PROBE];", [2]],
            'arrow function after an attribute'      => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\n\$f = #[A] fn () => _ZZ_PROBE;", [2]],
            'ternary else after an arrow function'   => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\n\$v = \$c ? fn () => 1 : _ZZ_PROBE;", [2]],
            'default value in a function signature'  => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\nfunction f(\$value = _ZZ_PROBE) {}", [2]],
            'default value in a closure signature'   => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text');\n\$c = function (\$value = _ZZ_PROBE) {};", [2]],
            'fallback with a non-literal value'      => ["defined('_ZZ_PROBE') || define('_ZZ_PROBE', \$text);\necho _ZZ_PROBE;", [2]],
            'fallback for another constant'          => ["defined('_ZZ_PROBE') || define('_ZZ_OTHER', 'text');\necho _ZZ_PROBE;", [2]],
            'fallback behind a condition'            => ["\$a && (defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'text'));\necho _ZZ_PROBE;", [2]],
            'fallback inside a conditional for header' => ["if (\$flag) for (; defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'x'); ) {}\necho _ZZ_PROBE;", [2]],
            'fallback as a braceless if body'        => ["if (\$flag) defined('_ZZ_PROBE') || define('_ZZ_PROBE', 'x');\necho _ZZ_PROBE;", [2]],
            'guard text inside a string'             => ["echo \"defined('_ZZ_PROBE')\", _ZZ_PROBE;", [1]],
        ];
    }

    /**
     * @param list<int> $expected
     */
    #[Test]
    #[DataProvider('reportedShapes')]
    public function everyOtherShapeIsReported(string $code, array $expected): void
    {
        self::assertSame($expected, self::reported($code));
    }

    #[Test]
    public function onlyReadsOnAddedLinesAreReported(): void
    {
        self::assertSame([3], self::reported("echo _ZZ_PROBE;\n\$x = 1;\necho _ZZ_PROBE;", [3]));
    }

    #[Test]
    public function aBlockGuardOnAnUnchangedLineCoversAnAddedRead(): void
    {
        self::assertSame([], self::reported("if (defined('_ZZ_PROBE')) {\n    \$a = 1;\n    echo _ZZ_PROBE;\n}", [3]));
    }

    // -----------------------------------------------------------------
    // Which constants are new, and which lines changed
    // -----------------------------------------------------------------

    #[Test]
    public function definitionsCountOnlyInCodeWithAWholeLiteralName(): void
    {
        $source = "<?php\n"
            . "define('_ZZ_A', 'a');\n"
            . "define('THEME_ZZ_B', 'b');\n"
            . "define(constant_name: '_ZZ_NAMED', value: 'c');\n"
            . "define(value: f(1, 'x'), constant_name: '_ZZ_REORDERED');\n"
            . "// define('_ZZ_COMMENTED', 'x');\n"
            . "\$help = \"define('_ZZ_STRING', 'x')\";\n"
            . "define('_ZZ_PART' . '_SUFFIX', 'x');\n"
            . "define('lowercase_zz', 'x');\n";

        self::assertSame(['_ZZ_A', 'THEME_ZZ_B', '_ZZ_NAMED', '_ZZ_REORDERED'], LanguageConstantGuard::definedConstants($source));
    }

    #[Test]
    public function aRewordedDefinitionIsNotNew(): void
    {
        self::assertSame([], LanguageConstantGuard::newConstants("<?php\ndefine('_ZZ_A', 'Old');\n", "<?php\ndefine('_ZZ_A', 'New');\n"));
        self::assertSame(['_ZZ_B'], LanguageConstantGuard::newConstants("<?php\ndefine('_ZZ_A', 'a');\n", "<?php\ndefine('_ZZ_A', 'a');\ndefine('_ZZ_B', 'b');\n"));
    }

    #[Test]
    public function modifiedAndRenamedLanguageFilesArePairedWithTheirHeadPath(): void
    {
        $status = "M\0htdocs/language/english/global.php\0"
            . "R095\0htdocs/language/english/old.php\0htdocs/language/english/new.php\0"
            . "M\0htdocs/language/english/readme.txt\0";

        self::assertSame(
            [
                ['htdocs/language/english/global.php', 'htdocs/language/english/global.php'],
                ['htdocs/language/english/old.php', 'htdocs/language/english/new.php'],
            ],
            LanguageConstantGuard::languageFilePairs($status)
        );
    }

    #[Test]
    public function addedLinesAreReadPerFileFromAZeroContextDiff(): void
    {
        $diff = implode("\n", [
            'diff --git a/htdocs/old.php b/htdocs/moved.php',
            'similarity index 100%',
            'rename from htdocs/old.php',
            'rename to htdocs/moved.php',
            'diff --git a/htdocs/a.php b/htdocs/a.php',
            '--- a/htdocs/a.php',
            '+++ b/htdocs/a.php',
            '@@ -7,0 +8,2 @@ function x()',
            '+echo _X;',
            '+++$n;', // an added line "++$n;", not a header
            '@@ -12 +13 @@',
            '--- $x;', // a removed line "-- $x;" ...
            '+++ $x;', // ... and an added line "++ $x;": not a header pair
            '@@ -20,0 +21 @@',
            '+echo _X;', // this hunk must still count for a.php
            "diff --git a/htdocs/with space.php b/htdocs/with space.php",
            "--- a/htdocs/with space.php\t",
            "+++ b/htdocs/with space.php\t", // git ends a path containing a space with a tab
            '@@ -1 +1 @@',
            '-a',
            '+b',
            'diff --git a/htdocs/gone.php b/htdocs/gone.php',
            'deleted file mode 100644',
            '--- a/htdocs/gone.php',
            '+++ /dev/null',
            '@@ -1 +0,0 @@',
            '-x',
            '',
        ]);

        self::assertSame(
            ['htdocs/a.php' => [8 => true, 9 => true, 13 => true, 21 => true], 'htdocs/with space.php' => [1 => true]],
            LanguageConstantGuard::addedLinesByFile($diff)
        );
    }

    // -----------------------------------------------------------------
    // runAgainstIndex() in a throwaway repository
    // -----------------------------------------------------------------

    /**
     * @param list<string> $args
     */
    private static function gitIn(string $dir, array $args): void
    {
        $process = proc_open(
            array_merge(['git', '-C', $dir, '-c', 'user.name=Test', '-c', 'user.email=test@example.com', '-c', 'commit.gpgsign=false'], $args),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), 'git ' . $args[0] . ' failed: ' . $error);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private static function runInThrowawayRepository(string $readCode): array
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xoops-rule18-' . bin2hex(random_bytes(6));
        mkdir($dir . '/htdocs/language/english', 0777, true);
        mkdir($dir . '/htdocs/include', 0777, true);
        $cwd = (string) getcwd();
        // Inside a git hook or CI step these point at the real repository.
        $inherited = [];
        foreach (['GIT_DIR', 'GIT_WORK_TREE', 'GIT_INDEX_FILE'] as $name) {
            $inherited[$name] = getenv($name);
            putenv($name);
        }
        try {
            self::gitIn($dir, ['init', '-q']);
            file_put_contents($dir . '/htdocs/language/english/global.php', "<?php\ndefine('_ZZ_OLD', 'old');\n");
            file_put_contents($dir . '/htdocs/include/functions.php', "<?php\n");
            self::gitIn($dir, ['add', '-A']);
            self::gitIn($dir, ['commit', '-q', '--no-verify', '-m', 'base']);

            file_put_contents($dir . '/htdocs/language/english/global.php', "define('_ZZ_PROBE', 'new');\n", FILE_APPEND);
            file_put_contents($dir . '/htdocs/include/functions.php', $readCode . "\n", FILE_APPEND);
            self::gitIn($dir, ['add', '-A']);

            chdir($dir);
            $out    = fopen('php://memory', 'w+');
            $status = LanguageConstantGuard::runAgainstIndex($out);
            rewind($out);
            $output = (string) stream_get_contents($out);
            fclose($out);

            return [$status, $output];
        } finally {
            chdir($cwd);
            foreach ($inherited as $name => $value) {
                false === $value ? putenv($name) : putenv($name . '=' . $value);
            }
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                if (!$item->isDir()) {
                    chmod($item->getPathname(), 0666); // git marks its objects read-only
                }
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($dir);
        }
    }

    #[Test]
    public function anUnguardedReadInTheIndexIsReportedWithItsFileAndLine(): void
    {
        [$status, $output] = self::runInThrowawayRepository('function zz(): string { return _ZZ_PROBE; }');

        self::assertSame(1, $status);
        self::assertSame("htdocs/include/functions.php:2: function zz(): string { return _ZZ_PROBE; }\n", $output);
    }

    #[Test]
    public function aCanonicalGuardInTheIndexIsClean(): void
    {
        [$status, $output] = self::runInThrowawayRepository("function zz(): string { return defined('_ZZ_PROBE') ? _ZZ_PROBE : 'x'; }");

        self::assertSame(0, $status, $output);
        self::assertSame('', $output);
    }
}
