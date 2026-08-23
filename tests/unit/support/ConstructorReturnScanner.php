<?php
/**
 * Shared constructor value-return scanner for the convention tests
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package   core
 * @since     2.7.3
 */

declare(strict_types=1);

namespace testsupport;

/**
 * ConstructorReturnScanner - token-scan PHP source for value-carrying
 * returns inside __construct(), the pattern PHP 8.6 deprecates and 9.0
 * forbids.
 *
 * ONE implementation, consumed by both convention tests - two private
 * copies had already diverged once (review catch). The scanner keeps an
 * explicit stack of function frames instead of the earlier
 * inCtor/ctorDepth/closures flag set: every function body (constructor
 * or not, nested to any depth, including an anonymous class declaring
 * its own __construct inside another constructor) is a frame, and a
 * return statement is a violation exactly when the INNERMOST enclosing
 * frame is a constructor. That makes the nested cases correct by
 * construction rather than by coincidence of statement terminators.
 *
 * Recognized and handled:
 * - bodyless declarations (interface / abstract): the pending function
 *   is discarded at ';' - the next brace belongs to something else
 * - by-reference functions and doc-commented closures: trivia and the
 *   ampersand between "function" and the name are skipped
 * - case-insensitive __CONSTRUCT
 * - bare "return;" stays legal everywhere
 *
 * @category  Test
 * @package   core
 * @author    XOOPS Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class ConstructorReturnScanner
{
    /**
     * @param string $source PHP source code
     *
     * @return array<int, int> source line numbers of value-carrying
     *                         constructor returns, empty when clean
     */
    public static function scan(string $source): array
    {
        $tokens     = token_get_all($source);
        $count      = count($tokens);
        $violations = [];
        $depth      = 0;      // current brace depth
        $frames     = [];     // function bodies: ['ctor' => bool, 'depth' => int]
        $pending    = null;   // 'ctor'|'fn' after a function keyword, awaiting { or ;

        foreach ($tokens as $index => $token) {
            if (is_array($token) && T_FUNCTION === $token[0]) {
                $pending = self::isConstructor($tokens, $index, $count) ? 'ctor' : 'fn';
                continue;
            }
            if (';' === $token && null !== $pending) {
                // Bodyless declaration - there is no body to enter.
                $pending = null;
                continue;
            }
            if ('{' === $token
                || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
                if (null !== $pending) {
                    $frames[] = ['ctor' => 'ctor' === $pending, 'depth' => $depth];
                    $pending  = null;
                }
                continue;
            }
            if ('}' === $token) {
                $depth--;
                while ($frames && end($frames)['depth'] > $depth) {
                    array_pop($frames);
                }
                continue;
            }
            if ($frames
                && end($frames)['ctor']
                && is_array($token)
                && T_RETURN === $token[0]
                && self::returnCarriesValue($tokens, $index, $count)) {
                $violations[] = (int) $token[2];
            }
        }

        return $violations;
    }

    /**
     * Is the function declared at $index named __construct (any case)?
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @param int                                                 $index position of T_FUNCTION
     * @param int                                                 $count token count
     */
    private static function isConstructor(array $tokens, int $index, int $count): bool
    {
        for ($j = $index + 1; $j < $count; $j++) {
            $next = $tokens[$j];
            // Skip trivia AND the by-ref ampersand of "function &name()".
            if ('&' === $next
                || (is_array($next) && in_array($next[0], [
                    T_WHITESPACE,
                    T_COMMENT,
                    T_DOC_COMMENT,
                    T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
                    T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
                ], true))) {
                continue;
            }

            return is_array($next) && T_STRING === $next[0] && '__construct' === strtolower($next[1]);
        }

        return false;
    }

    /**
     * Does the return statement at $index carry a value ("return expr;",
     * not the always-legal bare "return;")?
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @param int                                                 $index position of T_RETURN
     * @param int                                                 $count token count
     */
    private static function returnCarriesValue(array $tokens, int $index, int $count): bool
    {
        for ($j = $index + 1; $j < $count; $j++) {
            $next = $tokens[$j];
            if (is_array($next)
                && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return ';' !== $next;
        }

        return false;
    }
}
