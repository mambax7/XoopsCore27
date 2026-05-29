<?php
/**
 * Side-effect-free theme-config helpers.
 *
 * Single source of truth for normalising the two theme entries in
 * $xoopsConfig:
 *
 *  - xoops_validateThemeName()  — fail-empty validation of one theme
 *                                  directory name (path + HTML safety)
 *  - xoops_resolveThemeConfig() — returns a normalised pair
 *                                  ['theme_set' => string,
 *                                   'theme_set_allowed' => list<string>]
 *
 * Why this file exists: prior to issue #45, only the System theme-switch
 * block (b_system_themes_show) normalised these values; every other
 * runtime reader trusted the raw config entries. A corrupted xoops_config
 * row (mid-upgrade state, direct override in xoopsconfig.php, manual
 * edit) could feed unvalidated strings straight into <link href=>
 * attributes, theme-factory folder paths, and in_array() membership
 * checks. Consolidating the normalisation here lets common.php apply it
 * once at the config-merge boundary and lets every decision-point caller
 * (login, remember-me, theme selector, factory) reach the same answer.
 *
 * Validation contract: fail-empty. xoops_validateThemeName() returns ''
 * for any rejected input — never raises, never sanitises by stripping.
 * XOOPS theme discovery (XoopsLists::getDirListAsArray) and the theme
 * factory accept arbitrary directory names including spaces and
 * non-ASCII characters, so the validator does NOT enforce a
 * conservative [A-Za-z0-9_.-] alphabet (which would silently drop
 * legitimate `My Theme` / `テーマ` directories). Instead two safety
 * classes are enforced:
 *
 *  - Path safety: reject empty, leading dot, path separators (/, \),
 *    null bytes, and `..` appearing as a path segment.
 *  - HTML safety: reject the five HTML metacharacters (< > & " ').
 *    No legitimate theme directory contains those, and rejecting them
 *    at the boundary lets the option label and screenshot URL be
 *    embedded without per-renderer escaping logic.
 *
 * Each function is wrapped in a function_exists() guard so the file is
 * safe to require_once multiple times across mixed load orders (the
 * helpers are referenced from include/common.php before functions.php
 * has finished loading on some preload paths).
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category     kernel
 * @package      core
 * @author       XOOPS Development Team
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license      GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link         https://xoops.org
 * @since        2.7.0
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

if (!function_exists('xoops_validateThemeName')) {
    /**
     * Validate a theme directory name for path AND HTML safety.
     *
     * Returns the trimmed input when it passes both safety classes,
     * '' otherwise. Spaces and non-ASCII characters are preserved
     * unchanged — XOOPS theme discovery accepts them.
     *
     * @param string $name Untrusted theme directory name.
     * @return string Trimmed valid name, or '' on any rejection.
     */
    function xoops_validateThemeName(string $name): string
    {
        $name = trim($name);
        if (
            '' === $name
            || str_starts_with($name, '.')
            || str_contains($name, '/')
            || str_contains($name, '\\')
            || str_contains($name, "\0")
            || preg_match('~(?:^|[\\\\/])\.\.(?:[\\\\/]|$)~', $name)
            || preg_match('/[<>"\'&]/', $name)
        ) {
            return '';
        }

        return $name;
    }
}

if (!function_exists('xoops_resolveThemeConfig')) {
    /**
     * Resolve the safe (current, allowed) theme pair from $xoopsConfig.
     *
     * - Casts and validates each value through xoops_validateThemeName().
     * - Treats a string theme_set_allowed as pipe-separated (defends
     *   against direct overrides in mainfile.php / xoopsconfig.php).
     * - Skips non-scalar entries (object / array / null / resource)
     *   without casting, so a corrupted xoops_config row cannot trigger
     *   an Error or string-conversion Warning.
     * - Falls back to 'default' when no usable current theme survives.
     * - Falls back to [$currentTheme] when no usable allowed theme
     *   survives.
     * - Ensures $currentTheme is in the allowed list (otherwise the
     *   rendered <select>'s `selected` value would not match any of
     *   its <option>s, and runtime membership checks would reject the
     *   active theme).
     *
     * @param array<string, mixed> $xoopsConfig Raw config map; only
     *                                          theme_set and
     *                                          theme_set_allowed are
     *                                          read.
     * @return array{theme_set: string, theme_set_allowed: list<string>}
     */
    function xoops_resolveThemeConfig(array $xoopsConfig): array
    {
        $rawCurrentTheme = $xoopsConfig['theme_set'] ?? 'default';
        $currentTheme    = is_string($rawCurrentTheme)
            ? xoops_validateThemeName($rawCurrentTheme)
            : '';
        if ('' === $currentTheme) {
            $currentTheme = 'default';
        }

        $rawAllowedThemes = $xoopsConfig['theme_set_allowed'] ?? [];
        if (is_string($rawAllowedThemes)) {
            // Filter on empty-string explicitly — default array_filter()
            // drops any falsy entry, including a theme literally named "0".
            $rawAllowedThemes = array_filter(
                array_map('trim', explode('|', $rawAllowedThemes)),
                static fn(string $theme): bool => '' !== $theme
            );
        } elseif (!is_array($rawAllowedThemes)) {
            $rawAllowedThemes = [];
        }
        $allowedThemes = array_values(array_filter(
            array_map(
                static fn($name): string => is_scalar($name)
                    ? xoops_validateThemeName((string) $name)
                    : '',
                $rawAllowedThemes
            ),
            static fn(string $name): bool => '' !== $name
        ));
        if ([] === $allowedThemes) {
            $allowedThemes = [$currentTheme];
        }
        if (!in_array($currentTheme, $allowedThemes, true)) {
            array_unshift($allowedThemes, $currentTheme);
        }

        return [
            'theme_set'         => $currentTheme,
            'theme_set_allowed' => array_values(array_unique($allowedThemes)),
        ];
    }
}
