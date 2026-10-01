<?php
/**
 * Side-effect-free HTML output helpers.
 *
 *  - xoops_confirm_fields()       — the hidden/radio fields of a confirmation form
 *  - xoops_confirm_submit_label() — the submit-button label of a confirmation form
 *
 * Kept apart from include/functions.php so the rendering can be required
 * and tested on its own; the file has no module-level side effects. Each
 * function is wrapped in a function_exists() guard so the file is safe to
 * require_once across mixed load orders.
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             kernel
 * @since               2.7.4
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

if (!function_exists('xoops_confirm_fields')) {
    /**
     * Render the fields of a confirmation form built by xoops_confirm().
     *
     * Same shape as before 2.7.4:
     *  - a scalar value renders one hidden input;
     *  - an array value renders one radio input per caption => value pair,
     *    followed by <br>.
     * Every field name, value and radio caption is HTML-escaped exactly once.
     * null and false render an empty value, true renders "1". A value that is
     * neither scalar nor Stringable (for example a nested array inside a radio
     * group) cannot be represented and is skipped rather than cast.
     *
     * To carry nested request data through a confirmation step, flatten it
     * into bracketed names, e.g. ['del_not[3][0]' => 10].
     *
     * @param array<int|string, mixed> $hiddens field name => value
     * @return string HTML
     */
    function xoops_confirm_fields(array $hiddens): string
    {
        $escape = static function (string $text): string {
            return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
        };
        $scalar = static function ($value): ?string {
            if (null === $value || false === $value) {
                return '';
            }
            if (true === $value) {
                return '1';
            }
            if (is_scalar($value) || $value instanceof \Stringable) {
                return (string) $value;
            }

            return null;
        };

        $html = '';
        foreach ($hiddens as $name => $value) {
            $name = $escape((string) $name);
            if (is_array($value)) {
                foreach ($value as $caption => $option) {
                    $option = $scalar($option);
                    if (null === $option) {
                        continue;
                    }
                    $html .= '<input type="radio" name="' . $name . '" value="' . $escape($option) . '" /> '
                        . $escape((string) $caption);
                }
                $html .= '<br>';
                continue;
            }

            $value = $scalar($value);
            if (null === $value) {
                continue;
            }
            $html .= '<input type="hidden" name="' . $name . '" value="' . $escape($value) . '" />';
        }

        return $html;
    }
}

if (!function_exists('xoops_confirm_submit_label')) {
    /**
     * Escape the submit-button label of a confirmation form built by xoops_confirm().
     *
     * The label lands in value="" and title="" attributes, both in
     * system_confirm.tpl (and its theme copies) and in the fallback output,
     * so it is escaped once here for both. Entities a language constant
     * already carries are kept (double_encode off), and an invalid UTF-8
     * byte is replaced rather than blanking the label.
     *
     * @param mixed  $submit  caller's label; empty, whitespace-only or non-scalar selects $default
     * @param string $default label used instead (normally _SUBMIT)
     * @return string label escaped for an HTML attribute
     */
    function xoops_confirm_submit_label($submit, string $default): string
    {
        $label = is_scalar($submit) ? trim((string) $submit) : '';
        if ('' === $label) {
            $label = $default;
        }

        return htmlspecialchars($label, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}
