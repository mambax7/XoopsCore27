<?php
/**
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  XoopsForm
 * @package   XoopsFormRendererValueEscapeTrait
 * @author    Richard Griffith <richard@geekwright.com>
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 * @since     2.7.3
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

trait XoopsFormRendererValueEscapeTrait
{
    /**
     * Escape a form element value for HTML text/attribute context, idempotently.
     *
     * XOOPS callers historically hand elements an ALREADY-escaped value
     * (getVar($k, 'e'|'E'), MyTextSanitizer::htmlSpecialChars()), and roughly 95% of
     * core does. Escaping again here would double-escape them and rewrite stored text
     * on the next save. Decoding first makes this a no-op for those callers while
     * still neutralising the ones that pass raw user input -- which core itself does
     * in at least two places (kernel/menusitems.php fetches with 'n', and
     * include/comment_form.php re-displays raw $_POST on preview).
     *
     * Flags match kernel/object.php:478 so the round-trip is exact.
     *
     * @param mixed $value
     * @return string
     */
    protected function escapeElementValue($value): string
    {
        return htmlspecialchars(
            htmlspecialchars_decode((string) $value, ENT_QUOTES | ENT_HTML5),
            ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
