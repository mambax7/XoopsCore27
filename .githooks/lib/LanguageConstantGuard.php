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

/**
 * Pre-commit rule 18 (warning only): a language constant added to an existing
 * English file is undefined on every translated pack that predates it
 * (G-2026-109), so each newly added read must use a canonical guard form.
 *
 * This is a whitelist. It accepts exactly three token shapes and reports
 * every other read, instead of trying to prove that arbitrary PHP guards it:
 *
 *  1. Inline: `defined('_X') ? _X : <expr>`, where the call is the whole
 *     condition and the read is the exact true branch.
 *  2. Block: `if (defined('_X')) { ... }`, where the call is the entire
 *     condition; reads inside that brace block (nested blocks included) are
 *     covered. `elseif`, `else` and the alternative syntax are not.
 *  3. File-level fallback: a statement at file scope that is exactly
 *     `defined('_X') || define('_X', '<literal>');`. It covers later reads at
 *     file scope only, never inside function, method or other blocks.
 *
 * `\defined()` and `\_X` are accepted wherever `defined()` and `_X` are.
 *
 * @category  Xoops
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class LanguageConstantGuard
{
    /** Tokens after which an identifier is not a read of a global constant. */
    private const NOT_A_READ_AFTER = [
        T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION,
        T_CONST, T_NEW, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_EXTENDS,
        T_IMPLEMENTS, T_INSTANCEOF, T_USE, T_NAMESPACE, T_GOTO,
    ];

    /**
     * Tokens before an inline `defined('_X') ? ...` that leave the call as the
     * whole ternary condition. '.' is not one: concatenation binds first. ':'
     * is one: it ends a named-argument label, a case label or an
     * alternative-syntax condition (a nested ternary without parentheses
     * does not compile on PHP 8).
     */
    private const INLINE_BEFORE = [
        '=', ',', '[', '(', ';', '{', '}', ':', T_CASE, T_RETURN, T_ECHO, T_PRINT, T_YIELD, T_DOUBLE_ARROW,
        T_OPEN_TAG_WITH_ECHO, T_CONCAT_EQUAL, T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL,
        T_DIV_EQUAL, T_MOD_EQUAL, T_POW_EQUAL, T_COALESCE_EQUAL, T_AND_EQUAL, T_OR_EQUAL,
        T_XOR_EQUAL, T_SL_EQUAL, T_SR_EQUAL,
    ];

    /**
     * Constants define()d in PHP code with a whole string literal as the
     * name, positional or as `constant_name:`: any upper-case name, with or
     * without a leading underscore.
     *
     * @return list<string>
     */
    public static function definedConstants(string $source): array
    {
        $tokens = self::codeTokens($source);
        $names  = [];
        foreach (array_keys($tokens) as $i) {
            if (!self::isNamed($tokens, $i, 'define') || '(' !== ($tokens[$i + 1][0] ?? null)) {
                continue;
            }
            $arg = self::defineNameArgument($tokens, $i + 2);
            if (null !== $arg && in_array($tokens[$arg + 1][0] ?? null, [',', ')'], true)) {
                $name = self::literal($tokens, $arg);
                if (null !== $name && 1 === preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
                    $names[$name] = true;
                }
            }
        }

        return array_keys($names);
    }

    /**
     * Index of the name argument of a define() whose first argument token is
     * at $i: the first positional argument, or the one labelled
     * `constant_name:` wherever it stands. Null when neither exists.
     *
     * @param list<array{0: int|string, 1: string, 2: int}> $tokens
     */
    private static function defineNameArgument(array $tokens, int $i): ?int
    {
        $depth = 0;
        $first = true;
        for ($j = $i; isset($tokens[$j]); $j++) {
            $type = $tokens[$j][0];
            if (0 === $depth && ($first || ',' === $tokens[$j - 1][0])) {
                $labelled = T_STRING === $type && ':' === ($tokens[$j + 1][0] ?? null);
                if ($labelled && 'constant_name' === $tokens[$j][1]) {
                    return $j + 2;
                }
                if ($first && !$labelled) {
                    return $j;
                }
                $first = false;
            }
            if (in_array($type, ['(', '[', T_CURLY_OPEN, '{'], true)) {
                $depth++;
            } elseif (in_array($type, [')', ']', '}'], true)) {
                if (0 === $depth) {
                    return null;
                }
                $depth--;
            }
        }

        return null;
    }

    /**
     * Constants the staged file defines and the HEAD version does not.
     *
     * @return list<string>
     */
    public static function newConstants(string $base, string $staged): array
    {
        return array_values(array_diff(self::definedConstants($staged), self::definedConstants($base)));
    }

    /**
     * English PHP language files to compare, as [HEAD path, staged path],
     * from `git diff --cached --name-status -z -M --diff-filter=MR`.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function languageFilePairs(string $nameStatus): array
    {
        $parts = explode("\0", $nameStatus);
        $pairs = [];
        for ($k = 0; $k < count($parts) && '' !== $parts[$k];) {
            if ('R' === $parts[$k][0]) {
                [$base, $staged] = [$parts[$k + 1] ?? '', $parts[$k + 2] ?? ''];
                $k += 3;
            } else {
                $base = $staged = $parts[$k + 1] ?? '';
                $k += 2;
            }
            if (str_ends_with($staged, '.php')) {
                $pairs[] = [$base, $staged];
            }
        }

        return $pairs;
    }

    /**
     * Added line numbers per file from a zero-context diff made with
     * `--src-prefix=a/ --dst-prefix=b/`. A "+++ " line is a header only in the
     * header section of a `diff --git` record, before its first hunk: a
     * changed pair "-- $x;" / "++ $x;" prints as "--- $x;" / "+++ $x;" inside
     * a hunk, and an added "++$n;" also starts with "+++".
     *
     * @return array<string, array<int, true>>
     */
    public static function addedLinesByFile(string $diff): array
    {
        $files    = [];
        $current  = null;
        $inHeader = false;
        foreach (preg_split('/\R/', $diff) ?: [] as $line) {
            if (str_starts_with($line, 'diff --git ')) {
                $current  = null;
                $inHeader = true;
            } elseif ($inHeader && str_starts_with($line, '+++ ')) {
                // git ends a header path that contains a space with a tab.
                $path    = rtrim(substr($line, 4), "\t");
                $path    = str_starts_with($path, '"') ? stripcslashes(substr($path, 1, -1)) : $path;
                $current = str_starts_with($path, 'b/') ? substr($path, 2) : null;
            } elseif (1 === preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@/', $line, $m)) {
                $inHeader = false;
                if (null === $current) {
                    continue;
                }
                $count = isset($m[2]) && '' !== $m[2] ? (int) $m[2] : 1;
                for ($n = (int) $m[1]; $n < (int) $m[1] + $count; $n++) {
                    $files[$current][$n] = true;
                }
            }
        }

        return $files;
    }

    /**
     * Reads of $constant on added lines that no canonical guard form covers.
     *
     * @param array<int, true> $addedLines
     * @return list<array{line: int, text: string}>
     */
    public static function unguardedReads(string $source, string $constant, array $addedLines): array
    {
        $tokens    = self::codeTokens($source);
        $lines     = preg_split('/\R/', $source) ?: [];
        $stack     = []; // 'guard', 'block' or 'string' per open brace
        $fallback  = false;
        $stmtStart = 0;
        $arrow     = null;  // brace depth at which an arrow function's statement began
        $signature = false; // inside a function or closure signature, before its body
        $paren     = 0;     // open parentheses: a ';' inside a for header ends no statement
        $found     = [];

        foreach ($tokens as $i => [$type]) {
            if ('(' === $type) {
                $paren++;
                continue;
            }
            if (')' === $type) {
                $paren = max(0, $paren - 1);
                continue;
            }
            if ('{' === $type) {
                $stack[]   = self::opensGuard($tokens, $i, $constant) ? 'guard' : 'block';
                $stmtStart = $i + 1;
                $signature = false;
                continue;
            }
            if (T_CURLY_OPEN === $type || T_DOLLAR_OPEN_CURLY_BRACES === $type) {
                $stack[] = 'string'; // interpolation inside a string
                continue;
            }
            if ('}' === $type) {
                if ('string' !== array_pop($stack)) {
                    $stmtStart = $i + 1;
                }
                continue;
            }
            // Alternative syntax: `if (...):` ... `endif;` is a block too, so a
            // fallback inside it is not at file scope.
            if (':' === $type && self::opensAlternativeBlock($tokens, $i)) {
                $stack[]   = 'block';
                $stmtStart = $i + 1;
                continue;
            }
            if (in_array($type, [T_ENDIF, T_ENDWHILE, T_ENDFOR, T_ENDFOREACH, T_ENDSWITCH, T_ENDDECLARE], true)) {
                if ('block' === end($stack)) {
                    array_pop($stack);
                }
                continue;
            }
            if (';' === $type) {
                if (0 === $paren) {
                    $stmtStart = $i + 1;
                    $signature = false;
                    if (null !== $arrow && count($stack) <= $arrow) {
                        $arrow = null; // the statement holding the arrow function ended
                    }
                }
                continue;
            }
            if (T_FN === $type) {
                // An arrow function's body runs to the end of its expression;
                // the rest of the statement (a ';' inside a nested anonymous
                // class does not end it) is treated as inside it, so the
                // file-level fallback never covers a read there.
                $arrow ??= count($stack);
                continue;
            }
            if (T_FUNCTION === $type) {
                $signature = true; // a default value in the signature is not file scope
                continue;
            }
            $atFileScope = null === $arrow && !$signature
                && !in_array('guard', $stack, true) && !in_array('block', $stack, true);
            if ($atFileScope && $i === $stmtStart && self::isFileFallback($tokens, $i, $constant)) {
                $fallback = true;
                continue;
            }
            if (!self::isRead($tokens, $i, $constant)) {
                continue;
            }
            $line = $tokens[$i][2];
            if (!isset($addedLines[$line])) {
                continue;
            }
            $covered = in_array('guard', $stack, true)
                || ($fallback && $atFileScope)
                || self::isInlineGuarded($tokens, $i, $constant);
            if (!$covered) {
                $found[] = ['line' => $line, 'text' => trim($lines[$line - 1] ?? '')];
            }
        }

        return $found;
    }

    /**
     * Run rule 18 on the index of the repository in the working directory.
     *
     * @param resource $out
     * @return int 0 clean, 1 reads reported, 2 the rule could not run
     */
    public static function runAgainstIndex($out): int
    {
        try {
            $constants = [];
            $status    = self::git(['diff', '--cached', '--name-status', '-z', '-M', '--diff-filter=MR', '--', ':(glob)**/language/english/**']);
            foreach (self::languageFilePairs($status) as [$base, $staged]) {
                $constants = array_merge($constants, self::newConstants(self::show('HEAD:' . $base), self::show(':' . $staged)));
            }
            $constants = array_values(array_unique($constants));
            if ([] === $constants) {
                return 0;
            }

            $diff = self::git([
                '-c', 'core.quotepath=false', 'diff', '--cached', '-U0', '-M', '--no-color', '--no-ext-diff',
                '--src-prefix=a/', '--dst-prefix=b/', '--diff-filter=ACMR', '--', '*.php',
                ':(exclude,glob)**/vendor/**', ':(exclude,glob)**/language/**', ':(exclude,glob)**/tests/**',
                ':(exclude,glob)docs/**', ':(exclude,glob).githooks/**',
            ]);
            $reported = 0;
            foreach (self::addedLinesByFile($diff) as $file => $added) {
                $source = self::show(':' . $file);
                foreach ($constants as $constant) {
                    foreach (self::unguardedReads($source, $constant, $added) as $read) {
                        fwrite($out, sprintf("%s:%d: %s\n", $file, $read['line'], $read['text']));
                        $reported++;
                    }
                }
            }

            return $reported > 0 ? 1 : 0;
        } catch (\RuntimeException $e) {
            fwrite($out, $e->getMessage() . "\n");

            return 2;
        }
    }

    /**
     * Code tokens as [type, text, line]: no whitespace, comments, inline HTML
     * or open tags; a closing tag ends a statement like ';'.
     *
     * @return list<array{0: int|string, 1: string, 2: int}>
     */
    private static function codeTokens(string $source): array
    {
        $tokens = [];
        $line   = 1;
        foreach (token_get_all($source) as $token) {
            if (!is_array($token)) {
                $tokens[] = [$token, $token, $line];
                continue;
            }
            [$type, $text, $line] = $token;
            if (T_CLOSE_TAG === $type) {
                $tokens[] = [';', ';', $line];
            } elseif (!in_array($type, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML, T_OPEN_TAG], true)) {
                $tokens[] = [$type, $text, $line];
            }
        }

        return $tokens;
    }

    /**
     * Whether the token at $i is a call target named $function, such as
     * `defined` or `\defined`, and not a method or declaration.
     *
     * @param list<array{0: int|string, 1: string, 2: int}> $tokens
     */
    private static function isNamed(array $tokens, int $i, string $function): bool
    {
        $type = $tokens[$i][0] ?? null;
        if (T_STRING !== $type && T_NAME_FULLY_QUALIFIED !== $type) {
            return false;
        }

        return 0 === strcasecmp(ltrim($tokens[$i][1], '\\'), $function)
            && !in_array($tokens[$i - 1][0] ?? null, self::NOT_A_READ_AFTER, true);
    }

    /**
     * The value of a plain string literal at $i, or null.
     *
     * @param list<array{0: int|string, 1: string, 2: int}> $tokens
     */
    private static function literal(array $tokens, int $i): ?string
    {
        if (T_CONSTANT_ENCAPSED_STRING !== ($tokens[$i][0] ?? null)) {
            return null;
        }
        $value = substr($tokens[$i][1], 1, -1);

        return str_contains($value, '\\') ? null : $value;
    }

    /**
     * Whether `defined('$constant')` occupies $i .. $i + 3.
     *
     * @param list<array{0: int|string, 1: string, 2: int}> $tokens
     */
    private static function isDefinedCall(array $tokens, int $i, string $constant): bool
    {
        return self::isNamed($tokens, $i, 'defined')
            && '(' === ($tokens[$i + 1][0] ?? null)
            && $constant === self::literal($tokens, $i + 2)
            && ')' === ($tokens[$i + 3][0] ?? null);
    }

    /**
     * Whether the '{' at $i opens `if (defined('$constant')) {`.
     *
     * @param list<array{0: int|string, 1: string, 2: int}> $tokens
     */
    private static function opensGuard(array $tokens, int $i, string $constant): bool
    {
        return $i >= 7
            && ')' === $tokens[$i - 1][0]
            && self::isDefinedCall($tokens, $i - 5, $constant)
            && '(' === $tokens[$i - 6][0]
            && T_IF === $tokens[$i - 7][0]
            && T_ELSE !== ($tokens[$i - 8][0] ?? null); // `else if (...)` is an else branch
    }

    /**
     * Whether the ':' at $i opens an alternative-syntax block: the ')' before
     * it closes the condition of `if`, `while`, `for`, `foreach`, `switch` or
     * `declare`. `elseif (...):` and `else:` continue a block; a ternary's ':'
     * and a `case` label do not qualify.
     *
     * @param list<array{0: int|string, 1: string, 2: int}> $tokens
     */
    private static function opensAlternativeBlock(array $tokens, int $i): bool
    {
        if (')' !== ($tokens[$i - 1][0] ?? null)) {
            return false;
        }
        $depth = 0;
        for ($j = $i - 1; $j >= 0; $j--) {
            if (')' === $tokens[$j][0]) {
                $depth++;
            } elseif ('(' === $tokens[$j][0] && 0 === --$depth) {
                return in_array($tokens[$j - 1][0] ?? null, [T_IF, T_WHILE, T_FOR, T_FOREACH, T_SWITCH, T_DECLARE], true);
            }
        }

        return false;
    }

    /**
     * Whether the statement starting at $i is exactly
     * `defined('$constant') || define('$constant', '<literal>');`.
     *
     * @param list<array{0: int|string, 1: string, 2: int}> $tokens
     */
    private static function isFileFallback(array $tokens, int $i, string $constant): bool
    {
        return self::isDefinedCall($tokens, $i, $constant)
            && T_BOOLEAN_OR === ($tokens[$i + 4][0] ?? null)
            && self::isNamed($tokens, $i + 5, 'define')
            && '(' === ($tokens[$i + 6][0] ?? null)
            && $constant === self::literal($tokens, $i + 7)
            && ',' === ($tokens[$i + 8][0] ?? null)
            && T_CONSTANT_ENCAPSED_STRING === ($tokens[$i + 9][0] ?? null)
            && ')' === ($tokens[$i + 10][0] ?? null)
            && ';' === ($tokens[$i + 11][0] ?? null);
    }

    /**
     * Whether the token at $i reads $constant: `_X`, `\_X`, `constant('_X')`
     * or `constant(name: '_X')`.
     *
     * @param list<array{0: int|string, 1: string, 2: int}> $tokens
     */
    private static function isRead(array $tokens, int $i, string $constant): bool
    {
        [$type, $text] = $tokens[$i];
        if ((T_STRING === $type && $constant === $text) || (T_NAME_FULLY_QUALIFIED === $type && '\\' . $constant === $text)) {
            $prev = $tokens[$i - 1][0] ?? null;
            $next = $tokens[$i + 1][0] ?? null;
            if (':' === $next && in_array($prev, ['(', ','], true)) {
                return false; // a named-argument label, `f(_X: 1)`
            }

            return !in_array($prev, self::NOT_A_READ_AFTER, true)
                && '(' !== $next && T_DOUBLE_COLON !== $next;
        }

        if (!self::isNamed($tokens, $i, 'constant') || '(' !== ($tokens[$i + 1][0] ?? null)) {
            return false;
        }
        $arg = $i + 2;
        if (T_STRING === ($tokens[$arg][0] ?? null) && 'name' === $tokens[$arg][1] && ':' === ($tokens[$arg + 1][0] ?? null)) {
            $arg += 2; // named argument
        }

        return $constant === self::literal($tokens, $arg) && ')' === ($tokens[$arg + 1][0] ?? null);
    }

    /**
     * Whether the read at $i is the exact true branch of
     * `defined('$constant') ? _X : ...` with the call as the whole condition.
     *
     * @param list<array{0: int|string, 1: string, 2: int}> $tokens
     */
    private static function isInlineGuarded(array $tokens, int $i, string $constant): bool
    {
        if ($i < 5 || T_STRING !== $tokens[$i][0] && T_NAME_FULLY_QUALIFIED !== $tokens[$i][0]) {
            return false; // constant('_X') is not the inline form
        }

        return '?' === $tokens[$i - 1][0]
            && ':' === ($tokens[$i + 1][0] ?? null)
            && self::isDefinedCall($tokens, $i - 5, $constant)
            && ($i < 6 || in_array($tokens[$i - 6][0], self::INLINE_BEFORE, true));
    }

    /**
     * @param list<string> $args
     */
    private static function git(array $args): string
    {
        $output = self::run($args);
        if (null === $output) {
            throw new \RuntimeException('rule 18: git ' . $args[0] . ' failed');
        }

        return $output;
    }

    /**
     * Contents of a blob such as ":path" or "HEAD:path". Every caller names a
     * blob the index diff just listed, so a failure is a git failure and ends
     * the run with status 2 rather than passing for an empty file.
     *
     * @throws \RuntimeException
     */
    private static function show(string $object): string
    {
        return self::git(['show', $object]);
    }

    /**
     * @param list<string> $args
     */
    private static function run(array $args): ?string
    {
        // stderr goes to the null device: an unread pipe could fill and block git.
        $null    = '\\' === DIRECTORY_SEPARATOR ? 'NUL' : '/dev/null';
        $process = proc_open(array_merge(['git'], $args), [1 => ['pipe', 'w'], 2 => ['file', $null, 'w']], $pipes);
        if (!is_resource($process)) {
            return null;
        }
        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return 0 === proc_close($process) ? $output : null;
    }
}
