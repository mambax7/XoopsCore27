<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

/**
 * XOOPS Upgrade Pre-flight checks (Smarty migration).
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @since     2.3.0
 * @author    Skalpa Keo <skalpa@xoops.org>
 * @author    Taiwen Jiang <phppp@users.sourceforge.net>
 * @author    XOOPS Development Team
 */

use Xoops\Upgrade\ScannerOutput;
use Xoops\Upgrade\ScannerProcess;
use Xoops\Upgrade\ScannerWalker;
use Xoops\Upgrade\Smarty4ScannerOutput;
use Xoops\Upgrade\Smarty4TemplateChecks;
use Xoops\Upgrade\Smarty4TemplateRepair;
use Xoops\Upgrade\Smarty4RepairOutput;
use Xoops\Upgrade\Smarty5ScannerOutput;
use Xoops\Upgrade\Smarty5TemplateChecks;
use Xoops\Upgrade\Smarty5TemplateRepair;
use Xoops\Upgrade\Smarty5RepairOutput;
use Xoops\Upgrade\UpgradeControl;

require_once __DIR__ . '/class/fatal_error_handler.php';

/*
 * Before xoops 2.5.8 the table 'sess_ip' was of type varchar (15). This is a problem for IPv6
 * addresses because it is longer. The upgrade process would change the column to VARCHAR(45)
 * but it requires login, which is failing. If the user has an IPv6 address, it is converted to
 * short IP during the upgrade. At the end of the upgrade IPV6 works
 *
 * Here we save the current IP address if needed
 */
$ip = $_SERVER['REMOTE_ADDR'];
if (strlen($_SERVER['REMOTE_ADDR']) > 15) {
    //new IP for upgrade
    $_SERVER['REMOTE_ADDR'] = '::1';
}

include_once __DIR__ . '/checkmainfile.php';
defined('XOOPS_ROOT_PATH') or die('Bad installation: please add this folder to the XOOPS install you want to upgrade');

// The "End scan / proceed" transition (endscan=yes) is no longer unconditional.
// It is now gated by the Smarty-4 blocker check and handled below, after the
// admin is authenticated and language strings are loaded, so the gate can read
// the recorded scan tally ($_SESSION['smartyScan']) and render a localized panel.
// Until that transition succeeds, keep the preflight marked active.
$_SESSION['preflight'] = 'active'; // so that manually loading preflight.php forces to active

// Per-session CSRF token for the preflight's mutating POST actions (template
// repair, end-scan/complete, blocker override). $xoopsSecurity is unsuitable
// here: the wizard bootstraps the (possibly pre-2.7) site being upgraded, where
// the tokens table may not exist yet. Use a self-contained session token,
// compared with hash_equals().
if (empty($_SESSION['preflight_csrf'])) {
    $_SESSION['preflight_csrf'] = bin2hex(random_bytes(32));
}

/**
 * Hidden input carrying the preflight CSRF token, for any mutating form.
 *
 * @return string
 */
function preflightTokenField(): string
{
    return '<input type="hidden" name="preflight_token" value="'
        . htmlspecialchars((string) ($_SESSION['preflight_csrf'] ?? ''), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate the preflight CSRF token submitted with a mutating POST.
 *
 * @return bool
 */
function preflightTokenValid(): bool
{
    $sent = Xmf\Request::getString('preflight_token', '', 'POST');
    return '' !== $sent
        && !empty($_SESSION['preflight_csrf'])
        && hash_equals((string) $_SESSION['preflight_csrf'], $sent);
}

$reporting = 0;
if (isset($_GET['debug'])) {
    $reporting = -1;
}
error_reporting($reporting);
$xoopsLogger->activated = true;
$xoopsLogger->enableRendering();
xoops_loadLanguage('logger');
set_exception_handler('fatalPhpErrorHandler'); // should have been changed by now, reset to ours

require_once __DIR__ . '/class/autoload.php';

$error = false;
$upgradeControl = new UpgradeControl($GLOBALS['xoopsDB']);

$upgradeControl->determineLanguage();

$languageRoot = realpath(__DIR__ . '/language');
$language = $upgradeControl->normalizeLanguage($upgradeControl->upgradeLanguage);
$userFile = false !== $languageRoot
    ? realpath($languageRoot . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . 'user.php')
    : false;
if (
    false !== $languageRoot
    && false !== $userFile
    && str_starts_with($userFile, $languageRoot . DIRECTORY_SEPARATOR)
) {
    include_once $userFile;
} else {
    include_once XOOPS_ROOT_PATH . "/language/english/user.php";
}
$upgradeControl->loadLanguage('smarty4');
$upgradeControl->loadLanguage('smarty5');

/**
 * User options form for preflight
 *  template_dir  a directory relative to XOOPS_ROOT_PATH, i.e. /themes/ or /themes/xbootstrap/
 *  template_ext  a file extension to scan for. Typical values are tpl or html
 *  runfix        if checked, attempt to fix any issues found. Note not all possible issues can be automatically fixed
 *
 * @return string options form
 */
function tplScannerForm($parameters=null)
{
    $action = XOOPS_URL . '/upgrade/preflight.php';

    $form = '<h2>' . _XOOPS_SMARTY5_RESCAN_OPTIONS . '</h2>';
    $form .= '<form action="' . $action . '" method="post" class="form-horizontal">';
    $form .= preflightTokenField();

    $form .= '<div class="form-group">';
    $form .= '<input name="template_dir" class="form-control" type="text" placeholder="/themes/">';
    $form .= '<label for="template_dir">' . _XOOPS_SMARTY4_TEMPLATE_DIR  . '</label>';
    $form .= '</div>';

    $form .= '<div class="form-group">';
    $form .= '<input name="template_ext" class="form-control" type="text" placeholder="tpl">';
    $form .= '<label for="template_ext">' . _XOOPS_SMARTY4_TEMPLATE_EXT  . '</label>';
    $form .= '</div>';

    $currentMode = Xmf\Request::getString('scan_mode', 'both', 'POST');
    $modes = [
        'both'    => _XOOPS_SMARTY5_MODE_BOTH,
        'smarty4' => _XOOPS_SMARTY5_MODE_S4,
        'smarty5' => _XOOPS_SMARTY5_MODE_S5,
    ];
    $form .= '<div class="form-group">';
    $form .= '<label for="scan_mode">' . _XOOPS_SMARTY5_SCAN_MODE . '</label>';
    $form .= '<select name="scan_mode" id="scan_mode" class="form-control">';
    foreach ($modes as $value => $label) {
        $selected = ($value === $currentMode) ? ' selected' : '';
        $form .= '<option value="' . $value . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $form .= '</select>';
    $form .= '</div>';

    $form .= '<div class="form-group row">';
    $form .= '<div class="form-check">';
    $form .= '<legend class="col-form-label">' . _XOOPS_SMARTY5_FIX_BUTTON . '</legend>';
    $form .= '<input class="form-check-input" type="checkbox" name="runfix" >';
    $form .= '<label class="form-check-label" for="runfix">' . _YES . '</label>';
    $form .= '</div>';
    $form .= '</div>';

    $form .= '<div class="form-group">';
    $form .= '<button class="btn btn-lg btn-success" type="submit">' . _XOOPS_SMARTY5_SCANNER_RUN;
    $form .= '  <span class="fa-solid fa-caret-right"></span></button>';
    $form .= '</div>';

    $form .= '</form>';

    $form .= '<form action="' . $action . '" method="post" class="form-horizontal">';
    $form .= preflightTokenField();
    $form .= '<div class="form-group">';
    $form .= '<button class="btn btn-lg btn-danger" type="submit">' . _XOOPS_SMARTY5_SCANNER_END;
    $form .= '  <span class="fa-solid fa-caret-right"></span></button>';
    $form .= '<input type="hidden" name="endscan" value="yes">';
    // Carry the chosen scan mode through end-scan so the blocker panel's rescan
    // preserves it instead of falling back to the default.
    $form .= '<input type="hidden" name="scan_mode" value="' . htmlspecialchars($currentMode, ENT_QUOTES, 'UTF-8') . '">';
    $form .= '</div>';

    $form .= '</form>';

    return $form;
}

/**
 * Build, configure and run a scanner pass, returning its output object.
 *
 * @param \Xoops\Upgrade\ScannerProcess $process      checks or repair process
 * @param \Xoops\Upgrade\ScannerOutput  $output       paired output collector
 * @param string                        $template_dir directory relative to XOOPS_ROOT_PATH, or '' for themes/+modules/
 * @param string                        $template_ext extension to scan, or '' for tpl+html
 *
 * @return array{output: \Xoops\Upgrade\ScannerOutput, executed: bool} the $output
 *         after the scan, and whether a scan actually ran (false when the path or
 *         extension was rejected, or no scannable directory existed)
 */
function smartyRunScanner($process, $output, string $template_dir, string $template_ext)
{
    $scanner = new ScannerWalker($process, $output);

    $root = realpath(XOOPS_ROOT_PATH);
    if (false === $root) {
        return ['output' => $output, 'executed' => false]; // cannot resolve document root
    }

    $configured = false;
    if ('' === $template_dir) {
        // Only add roots that exist — ScannerWalker::addDirectory() asserts the
        // directory and would throw on a slim install missing one of them.
        foreach ([$root . '/themes/', $root . '/modules/'] as $dir) {
            if (is_dir($dir)) {
                $scanner->addDirectory($dir);
                $configured = true;
            }
        }
    } else {
        // Confine an admin-supplied directory to a real path under the template
        // roots (themes/ or modules/), matching the scanner's UI contract. This
        // rejects traversal ("/../outside"), the document root itself, and any
        // other tree — the scanner can rewrite files in repair mode.
        $target = realpath($root . '/' . ltrim($template_dir, '/\\'));
        $base   = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $allowedRoots = [$base . 'themes', $base . 'modules'];
        $within = false;
        if (false !== $target) {
            foreach ($allowedRoots as $allowed) {
                $allowedPrefix = $allowed . DIRECTORY_SEPARATOR;
                if (0 === strncmp($target . DIRECTORY_SEPARATOR, $allowedPrefix, strlen($allowedPrefix))) {
                    $within = true;
                    break;
                }
            }
        }
        if (!$within || !is_dir($target)) {
            return ['output' => $output, 'executed' => false]; // outside themes/ or modules/
        }
        $scanner->addDirectory($target);
        $configured = true;
    }

    // Only real template extensions may be scanned or repaired.
    $allowedExt = ['tpl', 'html'];
    if ('' === $template_ext) {
        foreach ($allowedExt as $ext) {
            $scanner->addExtension($ext);
        }
    } elseif (in_array(strtolower($template_ext), $allowedExt, true)) {
        $scanner->addExtension(strtolower($template_ext));
    } else {
        return ['output' => $output, 'executed' => false]; // disallowed extension
    }

    if (!$configured) {
        return ['output' => $output, 'executed' => false]; // nothing to scan
    }

    $scanner->runScan();

    return ['output' => $output, 'executed' => true];
}

/**
 * Compute a scan-bound token for the blocker gate.
 *
 * Combines the scan target, extension, the sorted blocker file list, the count,
 * and the newest blocker mtime, so that editing a blocker template and re-scanning
 * yields a different token — which invalidates any stale "proceed anyway" override.
 *
 * @param string   $template_dir
 * @param string   $template_ext
 * @param string[] $blockerFiles relative blocker paths
 *
 * @return string sha256 hex token
 */
function smartyScanToken(string $template_dir, string $template_ext, array $blockerFiles): string
{
    sort($blockerFiles);
    $maxMtime = 0;
    foreach ($blockerFiles as $relative) {
        $absolute = XOOPS_ROOT_PATH . $relative;
        if (is_file($absolute)) {
            $maxMtime = max($maxMtime, (int) filemtime($absolute));
        }
    }

    return hash('sha256', implode('|', [
        $template_dir,
        $template_ext,
        implode(',', $blockerFiles),
        count($blockerFiles),
        $maxMtime,
    ]));
}

/**
 * Record an audited acknowledgement that the admin proceeded past Smarty-4 blockers.
 *
 * Appends a JSON line to a persistent log under XOOPS_VAR_PATH and emits an
 * E_USER_WARNING so the override is visible in the upgrade report as well.
 *
 * @param array{token:string, blockers:int, files:string[], at:int} $scan recorded scan summary
 * @param int                                                        $uid  acting admin user id
 *
 * @return void
 */
function smartyLogOverride(array $scan, int $uid): void
{
    $record = [
        'event'    => 'smarty5_blocker_override',
        'uid'      => $uid,
        'blockers' => (int) ($scan['blockers'] ?? 0),
        'files'    => array_values((array) ($scan['files'] ?? [])),
        'token'    => (string) ($scan['token'] ?? ''),
    ];

    if (defined('XOOPS_VAR_PATH')) {
        $dir = XOOPS_VAR_PATH . '/data';
        // Ensure the audit dir exists (canonical create-or-already-exists idiom so
        // mkdir()'s result is consumed; @ keeps a native warning from leaking the
        // path), then require it writable.
        $ready = (is_dir($dir) || @mkdir($dir, 0775, true) || is_dir($dir)) && is_writable($dir);
        if ($ready) {
            $logged = @file_put_contents(
                $dir . '/smarty5_gate_override.log',
                json_encode($record, JSON_UNESCAPED_SLASHES) . "\n",
                FILE_APPEND | LOCK_EX
            );
            if (false === $logged) {
                // The override audit record matters; surface a failure to write it.
                trigger_error('Could not write the Smarty5 gate override audit log', E_USER_WARNING);
            }
        } else {
            // The directory is missing or not writable: the override proceeds with
            // no persistent audit trail — make that visible.
            trigger_error('Smarty5 gate override audit log directory is missing or not writable', E_USER_WARNING);
        }
    }

    // Reduce blocker paths to escaped basenames: a template filename could contain
    // HTML-special characters, and this message may be rendered into the upgrader
    // page by the error handler — escape it and drop the path.
    $safeFiles = implode(', ', array_map(
        static fn ($file): string => htmlspecialchars(basename((string) $file), ENT_QUOTES, 'UTF-8'),
        (array) $record['files']
    ));
    trigger_error(
        sprintf(
            'Smarty 5 readiness: admin uid %d proceeded past %d Smarty-4 blocker(s): %s',
            $uid,
            $record['blockers'],
            $safeFiles
        ),
        E_USER_WARNING
    );
}

/**
 * Render the red blocker panel: the offending files, guidance, a Re-scan button,
 * and the scan-bound "proceed anyway" override form.
 *
 * @param array{token:string, files:string[]} $scan         recorded scan summary
 * @param string                              $scan_mode    current scan mode (preserved on re-scan/proceed)
 * @param string                              $errorMessage gate error heading
 *
 * @return string HTML
 */
function smartyBlockerPanel(array $scan, string $scan_mode, string $errorMessage): string
{
    $action = XOOPS_URL . '/upgrade/preflight.php';
    $token  = (string) ($scan['token'] ?? '');
    $files  = array_values((array) ($scan['files'] ?? []));

    $html  = '<div class="panel panel-danger">';
    $html .= '<div class="panel-heading">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div>';
    $html .= '<div class="panel-body">';
    $html .= '<p>' . _XOOPS_SMARTY5_GATE_BLOCKER_INTRO . '</p>';
    $html .= '<ul>';
    foreach ($files as $file) {
        $html .= '<li><code>' . htmlspecialchars((string) $file, ENT_QUOTES, 'UTF-8') . '</code></li>';
    }
    $html .= '</ul>';
    $html .= '<p>' . _XOOPS_SMARTY5_GATE_GUIDANCE . '</p>';

    $modeAttr = htmlspecialchars($scan_mode, ENT_QUOTES, 'UTF-8');

    // Re-scan (normal scan submit, preserving mode)
    $html .= '<form action="' . $action . '" method="post" style="display:inline-block;margin-right:1em;">';
    $html .= preflightTokenField();
    $html .= '<input type="hidden" name="scan_mode" value="' . $modeAttr . '">';
    $html .= '<button class="btn btn-default" type="submit">' . _XOOPS_SMARTY5_GATE_RESCAN . '</button>';
    $html .= '</form>';

    // Proceed-anyway: ticking the box submits the scan-bound override token together
    // with endscan=yes, so a single click sets the override and completes the gate.
    $html .= '<form action="' . $action . '" method="post" style="display:inline-block;">';
    $html .= preflightTokenField();
    $html .= '<input type="hidden" name="scan_mode" value="' . $modeAttr . '">';
    $html .= '<input type="hidden" name="endscan" value="yes">';
    $html .= '<div class="form-check">';
    $html .= '<input class="form-check-input" type="checkbox" name="gate_override" id="gate_override" value="'
        . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    $html .= '<label class="form-check-label" for="gate_override">' . _XOOPS_SMARTY5_GATE_OVERRIDE_LABEL . '</label>';
    $html .= '</div>';
    $html .= '<button class="btn btn-danger" type="submit">' . _XOOPS_SMARTY5_GATE_OVERRIDE_BUTTON . '</button>';
    $html .= '</form>';

    $html .= '</div></div>';

    return $html;
}

ob_start();

global $xoopsUser;
if (!$xoopsUser || !$xoopsUser->isAdmin()) {
    include_once __DIR__ . '/login.php';
} else {
    // All form inputs are read from POST only: the forms submit via POST, and
    // reading from the default bag ($_REQUEST) would let a GET like
    // "?runfix=on" drive a mutating action without ever being a POST — and so
    // skip the token check below.
    $template_dir = Xmf\Request::getString('template_dir', '', 'POST');
    $template_ext = Xmf\Request::getString('template_ext', '', 'POST');
    $runfix       = Xmf\Request::getString('runfix', 'off', 'POST');
    $scan_mode    = Xmf\Request::getString('scan_mode', 'both', 'POST');
    $endscan      = Xmf\Request::getString('endscan', 'no', 'POST');
    $gateOverride = Xmf\Request::getString('gate_override', '', 'POST');

    // Reject forged mutating actions. The mutating flags are POST-only (above), so
    // this also covers the GET case; on an invalid token, neutralise the
    // repair/complete/override flags and fall back to a harmless read-only scan.
    $isMutating = ('on' === $runfix) || ('yes' === $endscan) || ('' !== $gateOverride);
    if ($isMutating && !preflightTokenValid()) {
        echo '<div class="alert alert-danger">'
            . htmlspecialchars(
                defined('_XOOPS_SMARTY5_BADTOKEN') ? _XOOPS_SMARTY5_BADTOKEN : 'Security token mismatch. Please rescan.',
                ENT_QUOTES,
                'UTF-8'
            ) . '</div>';
        $runfix       = 'off';
        $endscan      = 'no';
        $gateOverride = '';
    }

    $doS4 = in_array($scan_mode, ['smarty4', 'both'], true);
    $doS5 = in_array($scan_mode, ['smarty5', 'both'], true);

    // A submitted, scan-bound override is recorded BEFORE the gate is evaluated,
    // so ticking the box + clicking "proceed" completes in a single request.
    if ('' !== $gateOverride) {
        $scan = $_SESSION['smartyScan'] ?? null;
        if (is_array($scan) && !empty($scan['token']) && $gateOverride === $scan['token']) {
            $_SESSION['smartyGateOverride'] = $scan['token'];
        }
    }

    if ('yes' === $endscan) {
        // --- Smarty-4 blocker gate (replaces the old unconditional completion) ---
        $scan = $_SESSION['smartyScan'] ?? null;
        if (null === $scan || empty($scan['ran'])) {
            echo '<div class="alert alert-warning">'
                . htmlspecialchars(_XOOPS_SMARTY5_GATE_RUN_SCAN_FIRST, ENT_QUOTES, 'UTF-8')
                . '</div>';
            echo tplScannerForm();
        } elseif (($scan['blockers'] ?? 0) > 0
                  && (($_SESSION['smartyGateOverride'] ?? null) !== $scan['token'])
        ) {
            echo smartyBlockerPanel($scan, $scan_mode, _XOOPS_SMARTY5_GATE_BLOCKED);
        } else {
            if (($scan['blockers'] ?? 0) > 0) {
                smartyLogOverride($scan, (int) $xoopsUser->getVar('uid'));
            }
            $_SESSION['preflight'] = 'complete';
            header('Location: ./index.php');
            exit;
        }
    } else {
        // --- Scan / repair pass(es) ---
        echo _XOOPS_SMARTY5_SCANNER_OFFER;

        // Smarty 3->4 prerequisite layer (mode-gated). Only echo the output when a
        // scan actually ran — a skipped scan never closes its HTML table.
        if ($doS4) {
            if ('on' === $runfix) {
                $s4 = smartyRunScanner(new Smarty4TemplateRepair($o = new Smarty4RepairOutput()), $o, $template_dir, $template_ext);
            } else {
                $s4 = smartyRunScanner(new Smarty4TemplateChecks($o = new Smarty4ScannerOutput()), $o, $template_dir, $template_ext);
            }
            if ($s4['executed']) {
                echo $s4['output']->outputFetch();
            }
        }

        // Smarty 4->5 repair layer (mode-gated; mutating).
        if ($doS5 && 'on' === $runfix) {
            $s5repair = smartyRunScanner(new Smarty5TemplateRepair($o = new Smarty5RepairOutput()), $o, $template_dir, $template_ext);
            if ($s5repair['executed']) {
                echo $s5repair['output']->outputFetch();
            }
        }

        // The Smarty 4->5 checks pass ALWAYS runs: it produces the blocker tally the
        // completion gate depends on, so the gate never relies on a stale scan (e.g.
        // a later smarty4-only pass) or a missing one.
        $s5 = smartyRunScanner(new Smarty5TemplateChecks($o = new Smarty5ScannerOutput()), $o, $template_dir, $template_ext);
        /** @var Smarty5ScannerOutput $s5checks */
        $s5checks = $s5['output'];
        if ($doS5 && $s5['executed']) {
            echo $s5checks->outputFetch();
        }

        if ($s5['executed']) {
            $blockerFiles = $s5checks->getBlockerFiles();
            // Record the full tally so the upd_2.7.0-to-2.7.1 patch can VERIFY by
            // reading this scan instead of re-walking themes/+modules/ on every
            // upgrade page load (that full walk is ~20s over thousands of files).
            $_SESSION['smartyScan'] = [
                'ran'         => true,
                'token'       => smartyScanToken($template_dir, $template_ext, $blockerFiles),
                'blockers'    => $s5checks->countBlockers(),
                'files'       => $blockerFiles,
                'autofixable' => $s5checks->countAutoFixable(),
                'reportOnly'  => $s5checks->getReportOnlyIssues(),
                'at'          => time(),
            ];
            // A fresh scan invalidates any prior override (bound to an old token).
            unset($_SESSION['smartyGateOverride']);
        } else {
            // The scan did not run (bad path/extension, or missing template roots):
            // do NOT record a passing tally, and drop any prior one so the blocker
            // gate cannot be cleared by a no-op scan.
            echo '<div class="alert alert-warning">'
                . htmlspecialchars(_XOOPS_SMARTY5_SCAN_SKIPPED, ENT_QUOTES, 'UTF-8')
                . '</div>';
            unset($_SESSION['smartyScan'], $_SESSION['smartyGateOverride']);
        }

        echo tplScannerForm();
    }
}
$content = ob_get_contents();
ob_end_clean();

//echo $content;

$allSupportSites = [];
foreach ($upgradeControl->availableLanguages() as $lang) {
    $upgradeControl->supportSites = [];
    $upgradeControl->loadLanguage('support', $lang);
    $allSupportSites[$lang] = $upgradeControl->supportSites;
}

$viewModel = [
    'content'         => $content,
    'upgradeQueue'    => [],
    'upgradeLanguage' => $upgradeControl->upgradeLanguage,
    'patchCount'      => 0,
    'hasError'        => $error,
    'preflightDone'   => false,
    'languages'       => $upgradeControl->availableLanguages(),
    'supportSites'    => $allSupportSites,
];

include_once __DIR__ . '/upgrade_tpl.php';
