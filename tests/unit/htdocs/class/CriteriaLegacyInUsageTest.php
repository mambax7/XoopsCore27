<?php

declare(strict_types=1);

/**
 * Source-level guard against the legacy Criteria IN format.
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category        Tests
 * @package         kernel
 * @author          XOOPS Development Team
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license         GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link            https://xoops.org
 */

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `Criteria` accepts two shapes for IN / NOT IN:
 *
 *   new Criteria('uid', [1, 2, 3], 'IN');                          // modern, escaped per element
 *   new Criteria('uid', '(' . implode(',', $ids) . ')', 'IN');     // legacy, preformatted string
 *
 * The legacy shape is retained in `Criteria::render()` for third-party modules,
 * where it emits an E_USER_DEPRECATED notice under XOOPS_DEBUG. Core itself must
 * not use it: the caller hand-builds the SQL list, so escaping depends on every
 * caller remembering to cast, and a value that fails validation silently collapses
 * to a single quoted literal that matches nothing.
 *
 * Unit tests cannot catch this — each individual call site renders valid SQL. Only
 * a source-level scan finds them all, which is what this test does. It is mirrored
 * by the `.githooks/pre-commit` sniff so offenders are caught before they land.
 */
#[CoversNothing]
final class CriteriaLegacyInUsageTest extends TestCase
{
    /**
     * A Criteria construction whose value argument is a literal string opening
     * with a parenthesis, on a line that also names the IN / NOT IN operator.
     *
     * Catches: new Criteria('user_avatar', "('', 'blank.gif')", 'IN')
     */
    private const LEGACY_CRITERIA_ARG = '/Criteria\s*\(.*,\s*[\'"]\(.*[\'"](NOT )?IN[\'"]/i';

    /**
     * A variable assigned a parenthesised list built by string concatenation.
     *
     * On its own this is legitimate — raw-SQL EXISTS subqueries in member.php and
     * the Tailwind renderer's JS argument builder both do it. It only counts as an
     * offence when the same variable is then handed to a Criteria as an IN value,
     * which #INDIRECT_CRITERIA_USE checks for.
     *
     * Catches: $in = '(' . implode(',', $ids) . ')'   and   $in = "('" . implode("', '", $x) . "')"
     */
    private const HANDBUILT_LIST_ASSIGNMENT = '/(\$\w+)\s*=\s*[^;]*[\'"]\(.{0,3}\s*\.\s*implode\s*\(/';

    /** Consumes a hand-built list variable as a Criteria IN value. Sprintf slot: the variable. */
    private const INDIRECT_CRITERIA_USE = '/Criteria\s*\([^;]*%s[^;]*[\'"](NOT )?IN[\'"]/i';

    /** Source roots that ship as core. */
    private const SOURCE_ROOTS = ['htdocs', 'upgrade'];

    /** Bundled third-party code we neither own nor convert. */
    private const EXCLUDED_PATTERNS = [
        'htdocs/xoops_lib/vendor/',
        'htdocs/class/libraries/vendor/',
        'htdocs/class/xoopseditor/',
    ];

    /** Must always be in the scan set; its absence means the scan is not seeing core. */
    private const SENTINEL_FILE = 'htdocs/class/criteria.php';

    #[Test]
    public function noCoreSourceUsesTheLegacyCriteriaInFormat(): void
    {
        $root  = dirname(__DIR__, 4);
        $files = self::coreSourceFiles();

        // A guard test that scans nothing passes vacuously, which is worse than
        // no test at all: it reports success while protecting nothing. Both ways
        // of ending up with an empty scan — a wrong root, or git failing so the
        // fallback walks directories that are not there — are caught here.
        $this->assertContains(
            self::SENTINEL_FILE,
            $files,
            'Scan set does not contain ' . self::SENTINEL_FILE . ', so the scan is not '
            . 'running over core source. Check the repository root resolution.'
        );

        $offenders  = [];
        $unreadable = [];

        foreach ($files as $file) {
            $lines = file($root . '/' . $file, FILE_IGNORE_NEW_LINES);
            if (false === $lines) {
                // Never skip silently — an unread file is an unscanned file.
                $unreadable[] = $file;
                continue;
            }
            $source = implode("\n", $lines);

            foreach ($lines as $index => $line) {
                if (preg_match(self::LEGACY_CRITERIA_ARG, $line)) {
                    $offenders[] = sprintf('%s:%d  %s', $file, $index + 1, trim($line));
                    continue;
                }

                if (!preg_match(self::HANDBUILT_LIST_ASSIGNMENT, $line, $matches)) {
                    continue;
                }

                $consumed = sprintf(self::INDIRECT_CRITERIA_USE, preg_quote($matches[1], '/'));
                if (preg_match($consumed, $source)) {
                    $offenders[] = sprintf('%s:%d  %s', $file, $index + 1, trim($line));
                }
            }
        }

        $this->assertSame(
            [],
            $unreadable,
            "Core source files could not be read, so the scan is incomplete:\n"
            . implode("\n", $unreadable)
        );

        $this->assertSame(
            [],
            $offenders,
            sprintf(
                "%d core source line(s) build an IN list by hand. Pass an array instead —\n"
                . "  new Criteria('uid', \$ids, 'IN')  — and let Criteria quote each element:\n\n%s\n",
                count($offenders),
                implode("\n", $offenders)
            )
        );
    }

    /**
     * Every core PHP source file, relative to the repository root.
     *
     * Prefers git's index so untracked working copies (a locally extracted
     * upgrade tree, scratch files) are never scanned; falls back to a directory
     * walk when the tests run from an exported tarball with no git.
     *
     * @return list<string> repo-relative paths
     */
    private static function coreSourceFiles(): array
    {
        $root  = dirname(__DIR__, 4);
        $files = self::gitTrackedFiles($root) ?? self::walkSourceRoots($root);

        return array_values(array_filter($files, static function (string $path): bool {
            if (!str_ends_with($path, '.php')) {
                return false;
            }
            foreach (self::EXCLUDED_PATTERNS as $excluded) {
                if (str_contains($path, $excluded)) {
                    return false;
                }
            }
            foreach (self::SOURCE_ROOTS as $sourceRoot) {
                if (str_starts_with($path, $sourceRoot . '/')) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param  string $root repository root
     * @return list<string>|null repo-relative paths, or null when git is unavailable
     */
    private static function gitTrackedFiles(string $root): ?array
    {
        // Do NOT probe for a .git *directory*: in a linked worktree (git worktree
        // add) .git is a FILE pointing at the real gitdir, so that check would
        // silently downgrade this test to a filesystem walk and start scanning
        // untracked files. Just ask git — a non-zero status is the reliable
        // signal that there is no usable repository here.
        // function_exists() returns false when exec is in disabled_functions,
        // which is why no error suppression is needed on the call itself.
        if (!function_exists('exec')) {
            return null;
        }

        // No 2>&1: exec() collects stdout into $output, and merging stderr in
        // would let a git warning become an entry in the file list. A non-zero
        // status is already the signal that there is no usable repository, and
        // a bogus entry would now surface as an unreadable-file failure.
        $output = [];
        $status = 1;
        exec(sprintf('git -C %s ls-files -- "*.php"', escapeshellarg($root)), $output, $status);

        if (0 !== $status || [] === $output) {
            return null;
        }

        return array_values($output);
    }

    /**
     * @param  string $root repository root
     * @return list<string> repo-relative paths
     */
    private static function walkSourceRoots(string $root): array
    {
        $files = [];

        foreach (self::SOURCE_ROOTS as $sourceRoot) {
            $directory = $root . '/' . $sourceRoot;
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileInfo) {
                /** @var SplFileInfo $fileInfo */
                $files[] = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($root) + 1));
            }
        }

        return $files;
    }
}
