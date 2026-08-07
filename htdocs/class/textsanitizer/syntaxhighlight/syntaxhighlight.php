<?php
/**
 * TextSanitizer extension
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
 * @package             class
 * @subpackage          textsanitizer
 * @since               2.3.0
 * @author              Taiwen Jiang <phppp@users.sourceforge.net>
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Class MytsSyntaxhighlight
 */
class MytsSyntaxhighlight extends MyTextSanitizerExtension
{
    /**
     * @param MyTextSanitizer $myts
     * @param $source
     * @param $language
     *
     * @return bool|mixed|string
     */
    public function load($myts, $source, $language)
    {
        $config = parent::loadConfig(__DIR__);
        if (empty($config['highlight'])) {
            return "<pre>{$source}</pre>";
        }
        $source = $myts->undoHtmlSpecialChars($source);
        $source = stripslashes($source);
        $source = $this->php($source);

        return $source;
    }

    /**
     * @param $text
     *
     * @return mixed|string
     */
    public function php($text)
    {
        $text          = trim($text);
        $addedtag_open = 0;
        if (!strpos($text, '<?php') && (substr($text, 0, 5) !== '<?php')) {
            $text          = '<?php ' . $text;
            $addedtag_open = 1;
        }
        $addedtag_close = 0;
        if (!strpos($text, '?>')) {
            $text .= '?>';
            $addedtag_close = 1;
        }
        $oldlevel = error_reporting(0);

        //There is a bug in the highlight function(php < 5.3) that it doesn't render
        //backslashes properly like in \s. So here we replace any backslashes
        $text = str_replace("\\", 'XxxX', $text);

        $buffer = highlight_string($text, true); // Require PHP 4.20+

        //Placing backspaces back again
        $buffer = str_replace('XxxX', "\\", $buffer);

        error_reporting($oldlevel);

        // PHP 8.3 compatibility. Up to 8.2 highlight_string() returned
        //   <code><span style="...">…<br />…</span></code>
        // using <br /> for line breaks. From 8.3 it returns
        //   <pre><code style="...">…\n…</code></pre>
        // relying on the <pre> to preserve real newlines. Everything downstream of this
        // extension — notably the nl2Br() pass in MyTextSanitizer::displayTarea() — was built
        // for the older contract and normalises those raw newlines away, which collapsed a
        // whole [code] block onto a single line. Convert the 8.3+ shape back to the contract
        // the rest of the pipeline expects, rather than trying to teach every consumer about
        // <pre>.
        if (str_starts_with($buffer, '<pre>')) {
            $buffer = preg_replace('#^<pre>#', '', $buffer);
            $buffer = preg_replace('#</pre>\s*$#', '', $buffer);
            $buffer = str_replace(["\r\n", "\n"], '<br />', $buffer);

            // Whitespace too. Having no <pre> to rely on, 8.2 encoded EVERY space in the
            // source as &nbsp; and every tab as four of them; 8.3+ emits real whitespace and
            // lets the <pre> hold it. With that <pre> now removed, each run would collapse to
            // a single rendered space — losing not just indentation but any alignment within
            // a line, so `$a   = 1;` over `$bb  = 2;` would come out ragged.
            //
            // Only the text between tags is rewritten. The spaces inside an attribute such as
            // `<span style="color: #007700">` have to survive untouched: rewriting those is
            // what corrupts the markup itself. preg_split with DELIM_CAPTURE puts the tags at
            // the odd offsets and the text at the even ones, which keeps the two apart without
            // a pattern that has to reason about quoting.
            $parts = preg_split('/(<[^>]*>)/', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE);
            if (is_array($parts)) {
                foreach ($parts as $i => $part) {
                    if (0 === $i % 2) {
                        $parts[$i] = str_replace(
                            [' ', "\t"],
                            ['&nbsp;', '&nbsp;&nbsp;&nbsp;&nbsp;'],
                            $part
                        );
                    }
                }
                $buffer = implode('', $parts);
            }
        }
        $pos_open = $pos_close = 0;
        // Length of the opening marker actually found, so no magic number is assumed.
        $len_open_marker = 0;
        if ($addedtag_open) {
            // PHP 8.3 changed highlight_string() output: before it, spaces were encoded as
            // &nbsp; and the wrapper was "<code><span style=...>"; from 8.3 the marker is a
            // plain space and the wrapper is "<pre><code style=...>". Matching only
            // '&lt;?php&nbsp;' therefore returned false on 8.3+, and the old code then computed
            // $length_open = false + 14, blindly cutting the first 14 characters of the real
            // output — which is why a [code] block rendered as: le="color: #000000">...
            if (preg_match('/&lt;\?php(?:&nbsp;|\s)/', $buffer, $m, PREG_OFFSET_CAPTURE)) {
                $pos_open        = $m[0][1];
                $len_open_marker = strlen($m[0][0]);
            } else {
                // Marker genuinely absent: keep the buffer intact rather than guessing an offset.
                $pos_open        = 0;
                $len_open_marker = 0;
                $addedtag_open   = 0;
            }
        }
        if ($addedtag_close) {
            $pos_close = strrpos($buffer, '?&gt;');
        }

        $str_open  = $addedtag_open ? substr($buffer, 0, $pos_open) : '';
        $str_close = $pos_close ? substr($buffer, $pos_close + 5) : '';

        $length_open  = $addedtag_open ? $pos_open + $len_open_marker : 0;
        $length_text  = $pos_close ? $pos_close - $length_open : 0;
        $str_internal = $length_text ? substr($buffer, $length_open, $length_text) : substr($buffer, $length_open);

        $buffer = $str_open . $str_internal . $str_close;

        return $buffer;
    }
}
