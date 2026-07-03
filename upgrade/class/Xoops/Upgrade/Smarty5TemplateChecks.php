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

namespace Xoops\Upgrade;

use SplFileInfo;

/**
 * XOOPS Upgrade Smarty5TemplateChecks
 *
 * Report-only scanner that classifies Smarty 4 -> 5 readiness issues in existing
 * templates. It is the Smarty-5 counterpart of {@see Smarty4TemplateChecks} and
 * shares the same ScannerProcess/ScannerOutput contracts so it plugs into the
 * existing preflight ScannerWalker.
 *
 * Every rule lives in a single registry ({@see self::rules()}) carrying its
 * severity tier, whether it is forward-compatible (auto-fixable by
 * {@see Smarty5TemplateRepair}), and the XOOPS-delimiter (`<{ }>`) pattern that
 * detects it. The repair class consumes the same registry, so checks, repair,
 * and the upgrade patch's verify task never drift apart.
 *
 * Severity tiers:
 *  - S4-BLOCK : will not compile on Smarty 4 (`<{php}>`, `<{include_php}>`); ASP
 *               tags are tier S4-BLOCK but treated as a warning (often literal JS).
 *  - S4-WARN  : works on Smarty 4 but deprecated on PHP 8.1+ (`date_format` strftime).
 *  - S5-PREP  : valid on Smarty 4, needs rework/registration for Smarty 5.
 *
 * @category  Xoops\Upgrade
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
class Smarty5TemplateChecks extends ScannerProcess
{
    /** Severity tiers (see class docblock). */
    public const TIER_BLOCK = 'S4-BLOCK';
    public const TIER_WARN  = 'S4-WARN';
    public const TIER_PREP  = 'S5-PREP';

    /** Per-issue disposition — how the preflight/patch should treat a match. */
    public const FIX_AUTO   = 'autofix';   // forward-compatible, repair rewrites it
    public const FIX_MANUAL = 'manual';    // human rework required before Smarty 5
    public const FIX_BLOCK  = 'blocker';   // Smarty-4 blocker (gates the upgrade)
    public const FIX_WARN   = 'warn';      // flagged, may be a false positive (ASP)

    /**
     * strftime -> date() token map for the auto-fixable date_format rule.
     *
     * `%M` (strftime minutes) maps to `i`, NOT `M` (date month name) — the classic
     * gotcha. Locale-sensitive tokens (`%A %B %j %U` …) are deliberately absent: a
     * format containing any unmapped token is left for manual review, never rewritten.
     *
     * @var array<string,string>
     */
    public const DATE_FORMAT_MAP = [
        '%Y' => 'Y', '%y' => 'y', '%m' => 'm', '%d' => 'd', '%e' => 'j',
        '%H' => 'H', '%M' => 'i', '%S' => 's', '%p' => 'A', '%%' => '%',
    ];

    /**
     * Detects a `%` token NOT covered by DATE_FORMAT_MAP, e.g. `%A`, `%B`, `%j`.
     * Used to decide whether a date_format match is auto-fixable or manual.
     */
    public const UNMAPPED_TOKEN_PATTERN = '/%(?![YymdeHMSp%])/';

    /**
     * Matches a Smarty comment block `<{* … *}>` (non-greedy, spans lines).
     *
     * Smarty comments are stripped at compile time and never executed, so any
     * tag inside one (e.g. a commented-out `<{php}>`) is NOT a real issue. The
     * scanner blanks these regions before matching to avoid false positives.
     */
    public const COMMENT_PATTERN = '/<\{\*.*?\*\}>/s';

    /**
     * @var Smarty5ScannerOutput
     */
    private $output;

    /**
     * @param Smarty5ScannerOutput $output
     */
    public function __construct(Smarty5ScannerOutput $output)
    {
        $this->output = $output;
    }

    /**
     * The single rule registry shared by checks, repair, and output.
     *
     * Each entry: tier (TIER_*), autofix (bool — forward-compatible), pattern
     * (XOOPS `<{ }>` delimited regex). Optional: blocker (bool — gates upgrade),
     * warn (bool — flag only). The `date_format_strftime` rule is autofix only
     * when every `%` token is mapped; that decision is made per-match below.
     *
     * @return array<string, array{tier:string, autofix:bool, pattern:string, blocker?:bool, warn?:bool}>
     */
    public static function rules(): array
    {
        return [
            // --- Forward-compatible (auto-fixable by Smarty5TemplateRepair) ---
            'block_parent' => [
                'tier' => self::TIER_PREP, 'autofix' => true,
                'pattern' => '/<\{\s*block_parent\s*\}>/',
            ],
            'block_child' => [
                'tier' => self::TIER_PREP, 'autofix' => true,
                'pattern' => '/<\{\s*block_child\s*\}>/',
            ],
            // date_format strftime "%…" — auto-fix only when all tokens are mapped.
            // Group 1 = quote char, group 2 = the format string (used to classify).
            'date_format_strftime' => [
                'tier' => self::TIER_WARN, 'autofix' => true,
                'pattern' => '/\|\s*date_format\s*:\s*([\'"])([^\'"]*%[^\'"]*)\1/',
            ],

            // --- Smarty 4 blockers (report-only — will not compile on Smarty 4) ---
            'php_tag' => [
                'tier' => self::TIER_BLOCK, 'autofix' => false, 'blocker' => true,
                'pattern' => '/<\{\s*\/?\s*php\s*\}>|<\{\s*include_php\b[^}]*\}>/',
            ],
            'asp_tag' => [
                'tier' => self::TIER_BLOCK, 'autofix' => false, 'warn' => true,
                'pattern' => '/<%=?.*?%>/s',
            ],

            // --- Smarty 5 prep (report-only — manual rework / registration) ---
            'bare_parent' => [
                'tier' => self::TIER_PREP, 'autofix' => false,
                'pattern' => '/<\{\s*parent\s*\}>/',
            ],
            'bare_child' => [
                'tier' => self::TIER_PREP, 'autofix' => false,
                'pattern' => '/<\{\s*child\s*\}>/',
            ],
            'insert' => [
                'tier' => self::TIER_PREP, 'autofix' => false,
                'pattern' => '/<\{\s*insert\b[^}]*\}>/',
            ],
            'make_nocache' => [
                'tier' => self::TIER_PREP, 'autofix' => false,
                'pattern' => '/<\{\s*make_nocache\b[^}]*\}>/',
            ],
            'reserved_block' => [
                'tier' => self::TIER_PREP, 'autofix' => false,
                'pattern' => '/<\{\s*block\b[^}]*\}>/',
            ],
            'config_load_scope' => [
                'tier' => self::TIER_PREP, 'autofix' => false,
                'pattern' => '/<\{\s*config_load\b[^}]*\bscope\s*=/',
            ],
            'native_modifier' => [
                'tier' => self::TIER_PREP, 'autofix' => false,
                'pattern' => '/\|\s*(?:substr|str_replace|strlen|trim|strtolower|strtoupper|ucfirst|reset)\b/',
            ],
        ];
    }

    /**
     * Classify a date_format format string as auto-fixable or manual.
     *
     * @param string $format the quoted format body (without surrounding quotes)
     *
     * @return bool true when every `%` token is mapped (safe to rewrite)
     */
    public static function dateFormatIsAutoFixable(string $format): bool
    {
        return 0 === preg_match(self::UNMAPPED_TOKEN_PATTERN, $format);
    }

    /**
     * @param SplFileInfo $fileInfo
     *
     * @return void
     */
    public function inspectFile(SplFileInfo $fileInfo)
    {
        $output   = $this->output;
        $writable = $fileInfo->isWritable();
        $length   = $fileInfo->getSize();
        if ($length <= 0) {
            return;
        }
        $file     = $fileInfo->openFile();
        $contents = $file->fread($length);
        $relative = self::relativeToRoot($fileInfo->getPathname());

        // Blank out Smarty comments so commented-out tags (a commented <{php}>,
        // for example) are not reported. Replaced with a newline rather than ''
        // so removing a comment cannot splice adjacent text into a spurious tag.
        $contents = preg_replace(self::COMMENT_PATTERN, "\n", $contents) ?? $contents;

        foreach (self::rules() as $ruleId => $rule) {
            $found = preg_match_all($rule['pattern'], $contents, $matches, PREG_PATTERN_ORDER);
            if (0 === (int) $found) {
                continue;
            }

            $count = (int) $found;
            for ($i = 0; $i < $count; $i++) {
                $match = $matches[0][$i] ?? null;
                if (null === $match) {
                    continue;
                }

                $disposition = $this->disposition($ruleId, $rule, $matches[2][$i] ?? '');

                $output->outputIssue(
                    $output->makeOutputIssue($ruleId, $relative, $match, $writable, $rule['tier'], $disposition)
                );
            }
            unset($matches);
        }
    }

    /**
     * Resolve the disposition for a single match.
     *
     * @param string                                                          $ruleId    rule identifier
     * @param array{tier:string, autofix:bool, pattern:string, blocker?:bool, warn?:bool} $rule registry entry
     * @param string                                                          $dateBody  date_format body (rule-specific)
     *
     * @return string one of the FIX_* constants
     */
    private function disposition(string $ruleId, array $rule, string $dateBody): string
    {
        if (!empty($rule['blocker'])) {
            return self::FIX_BLOCK;
        }
        if (!empty($rule['warn'])) {
            return self::FIX_WARN;
        }
        if ('date_format_strftime' === $ruleId) {
            return self::dateFormatIsAutoFixable($dateBody) ? self::FIX_AUTO : self::FIX_MANUAL;
        }
        if (!empty($rule['autofix'])) {
            return self::FIX_AUTO;
        }

        return self::FIX_MANUAL;
    }
}
