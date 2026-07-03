<?php

// _LANGCODE: en
// _CHARSET : UTF-8
// Translator: XOOPS Translation Team

define('_XOOPS_SMARTY5_MIGRATION', 'XOOPS Smarty 5 Readiness');
define('_XOOPS_SMARTY5_BADTOKEN', 'Security token mismatch — the request was ignored. Please rescan and try again.');
define('_XOOPS_SMARTY5_SCAN_SKIPPED', 'No templates were scanned — the directory or extension was not valid. Nothing was recorded; please correct it and rescan.');

// --- Scanner report (Smarty5ScannerOutput) ---
define('_XOOPS_SMARTY5_SCANNER_RESULTS', 'Smarty 5 Readiness — Scan Results');
define('_XOOPS_SMARTY5_SCANNER_RULE', 'Rule');
define('_XOOPS_SMARTY5_SCANNER_TIER', 'Tier');
define('_XOOPS_SMARTY5_SCANNER_MATCH', 'Match');
define('_XOOPS_SMARTY5_SCANNER_FILE', 'File');
define('_XOOPS_SMARTY5_SCANNER_FIXED', 'Fix Count');
define('_XOOPS_SMARTY5_SCANNER_NOT_WRITABLE', 'Not writable — fix file permissions to auto-repair.');

// Per-issue notes
define('_XOOPS_SMARTY5_NOTE_AUTOFIX', 'Forward-compatible — can be auto-fixed (valid on Smarty 4 and 5).');
define('_XOOPS_SMARTY5_NOTE_BLOCKER', 'Smarty 4 blocker — will not compile. Move PHP logic to the module controller and assign() the result, or register a plugin.');
define('_XOOPS_SMARTY5_NOTE_WARN', 'Possible literal markup (e.g. inline JS/ASP) — review; not auto-fixed.');
define('_XOOPS_SMARTY5_NOTE_MANUAL', 'Manual rework required before Smarty 5 — not auto-fixed.');

// Readiness summary
define('_XOOPS_SMARTY5_SUMMARY', 'Smarty 5 Readiness Summary');
define('_XOOPS_SMARTY5_SUMMARY_CHECKED', 'Files checked');
define('_XOOPS_SMARTY5_SUMMARY_BLOCKERS', 'Smarty 4 blockers (must fix)');
define('_XOOPS_SMARTY5_SUMMARY_AUTOFIX', 'Forward-compatible (auto-fixable)');
define('_XOOPS_SMARTY5_SUMMARY_MANUAL', 'Manual rework (plan for Smarty 5)');

// --- Repair report (Smarty5RepairOutput) ---
define('_XOOPS_SMARTY5_REPAIR_RESULTS', 'Smarty 5 Readiness — Repairs Applied');
define('_XOOPS_SMARTY5_REPAIR_BACKUP', 'Backup');

// --- Preflight form / scan modes ---
define('_XOOPS_SMARTY5_RESCAN_OPTIONS', 'Rescan Options');
define('_XOOPS_SMARTY5_SCANNER_RUN', 'Run Scan');
define('_XOOPS_SMARTY5_SCANNER_END', 'End scan / proceed');
define('_XOOPS_SMARTY5_SCAN_MODE', 'Scan mode');
define('_XOOPS_SMARTY5_MODE_S4', 'Smarty 4 (3→4 prerequisites)');
define('_XOOPS_SMARTY5_MODE_S5', 'Smarty 5 readiness');
define('_XOOPS_SMARTY5_MODE_BOTH', 'Both (recommended)');
define('_XOOPS_SMARTY5_FIX_BUTTON', 'Tick "Yes" and Run Scan to apply the forward-compatible auto-fixes (block inheritance and date_format). User-customised templates are backed up to *.preflight-bak first.');

// --- Blocker gate ---
define('_XOOPS_SMARTY5_GATE_RUN_SCAN_FIRST', 'Run the Smarty 5 readiness scan before proceeding with the upgrade.');
define('_XOOPS_SMARTY5_GATE_BLOCKED', 'The upgrade cannot start while templates contain Smarty-4-incompatible tags that will not render.');
define('_XOOPS_SMARTY5_GATE_BLOCKER_INTRO', 'The following template(s) contain &lt;{php}&gt; / &lt;{include_php}&gt; tags removed in Smarty 4:');
define('_XOOPS_SMARTY5_GATE_GUIDANCE', 'Move the PHP logic into the module controller and assign() the result to the template, or register a Smarty plugin. Then re-scan.');
define('_XOOPS_SMARTY5_GATE_RESCAN', 'Re-scan');
define('_XOOPS_SMARTY5_GATE_OVERRIDE_LABEL', 'I understand that the template(s) listed above contain Smarty-4-incompatible tags that will not render, and I choose to proceed anyway.');
define('_XOOPS_SMARTY5_GATE_OVERRIDE_BUTTON', 'Proceed despite blockers');
define('_XOOPS_SMARTY5_GATE_PROCEED', 'No blockers found — you may proceed with the upgrade.');

// --- Scanner offer (welcome panel) ---
define(
    '_XOOPS_SMARTY5_SCANNER_OFFER',
    <<<'EOT'
<h3>Preparing your templates for Smarty 5</h3>

<p>XOOPS 2.7.x runs on <strong>Smarty 4</strong>. This readiness scan keeps your site working on
Smarty 4 today while preparing it for a future Smarty 5 engine, with no surprises later:</p>
<ul>
<li><strong>Blockers</strong> (<code>&lt;{php}&gt;</code>, <code>&lt;{include_php}&gt;</code>) are reported in red — they must be removed before the upgrade can start.</li>
<li><strong>Forward-compatible</strong> items (block inheritance, <code>date_format</code>) are auto-fixed on request — backed up first, identical output on Smarty 4.</li>
<li><strong>Manual rework</strong> items (<code>{insert}</code>, <code>{make_nocache}</code>, locale date formats, native modifiers) are reported for you to plan — never silently rewritten.</li>
</ul>
EOT,
);
