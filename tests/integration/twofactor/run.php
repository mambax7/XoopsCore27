<?php

declare(strict_types=1);

if (!getenv('XOOPS_2FA_TEST_DATABASE')) {
    echo "SKIP: set XOOPS_2FA_TEST_DATABASE to an explicitly disposable MySQL database (see README.md).\n";
    exit(0);
}
foreach (['mysqli', 'sodium'] as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, "Required extension missing: $extension\n");
        exit(1);
    }
}
$prefix = 'twofactor_test_' . bin2hex(random_bytes(8));
$directory = sys_get_temp_dir() . '/' . $prefix;
mkdir($directory, 0700);
mkdir($directory . '/data', 0700);
mkdir($directory . '/concurrent', 0700);
mkdir($directory . '/sessions', 0700);
mkdir($directory . '/first-enrolment', 0700);
mkdir($directory . '/accounts', 0700);
putenv('XOOPS_2FA_TEST_PREFIX=' . $prefix);
putenv('XOOPS_2FA_TEST_DIR=' . $directory);
require __DIR__ . '/bootstrap.php';
require XOOPS_ROOT_PATH . '/language/english/user2fa.php';

/** Independent PHP processes and MySQL connections, released together. */
function race(array $jobs): array
{
    $workers = [];
    try {
        foreach ($jobs as $job) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/worker.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            check(is_resource($process), 'Cannot start worker');
            $workers[] = [$process, $pipes];
            stream_set_blocking($pipes[1], false);
            fwrite($pipes[0], json_encode($job, JSON_THROW_ON_ERROR) . "\n");
        }
        // stream_set_timeout() does not bound fgets() on a proc_open() pipe.
        $readLine = static function ($pipe): string|false {
            $read   = [$pipe];
            $write  = $except = [];

            return stream_select($read, $write, $except, 20) > 0 ? fgets($pipe) : false;
        };
        foreach ($workers as [$process, $pipes]) {
            check(trim((string) $readLine($pipes[1])) === 'ready', 'Worker failed before start barrier');
        }
        foreach ($workers as [$process, $pipes]) {
            fwrite($pipes[0], "go\n");
            fclose($pipes[0]);
        }
        $results = [];
        $connections = [];
        foreach ($workers as [$process, $pipes]) {
            $line = $readLine($pipes[1]);
            if (!is_string($line)) {
                stream_set_blocking($pipes[2], false);
                throw new RuntimeException('Worker timed out or failed: ' . stream_get_contents($pipes[2]));
            }
            $reply = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $results[] = $reply['result'];
            $connections[] = $reply['connection'];
            fclose($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            check(proc_close($process) === 0, 'Worker failed: ' . $error);
        }
        check(count(array_unique($connections)) === count($jobs), 'Workers must use separate connections');
        return $results;
    } finally {
        foreach ($workers as [$process, $pipes]) {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }
}

$db = null;
$created = [];
$exitCode = 0;
try {
    $db = testDatabase();
    echo 'Server: ' . $db->getServerVersion() . "\n";
    // Exercise the shipped install DDL, not a test-specific schema facsimile.
    $schema = file_get_contents(XOOPS_ROOT_PATH . '/install/sql/mysql.structure.sql');
    foreach (['user_2fa', 'tokens', 'config', 'configoption'] as $table) {
        check(preg_match('/CREATE TABLE ' . $table . ' \(.*?\) ENGINE=[^;]+;/s', $schema, $match) === 1, 'Missing install DDL');
        $name = $db->prefix($table);
        check($db->exec(str_replace('CREATE TABLE ' . $table, 'CREATE TABLE `' . $name . '`', $match[0])), 'Create test table');
        $created[] = $name;
    }
    $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    $now = time();
    $step = XoopsTotp::stepAt($now);
    $enrolJob = ['action' => 'enrol', 'secret' => $secret, 'step' => $step, 'now' => $now];
    $first = race([['uid' => 60] + $enrolJob, ['uid' => 61] + $enrolJob]);
    check($first[0]['key'] === $first[1]['key'], 'Concurrent first enrolments use one key');
    $enrolHandler = new XoopsUser2faHandler($db, crypto: testCrypto('first-enrolment'), installed: true);
    foreach ([60, 61] as $index => $uid) {
        // A deadlock victim retries the user action; the committed neighbour
        // must remain decryptable using the same single provisioned key.
        $enrolled = $first[$index]['enrolled'];
        if (false === $enrolled) {
            $enrolled = $enrolHandler->enrol($uid, $secret, $step, $now, '');
        }
        check(is_array($enrolled) && count($enrolled['codes']) === 10, 'Both first enrolments eventually commit');
        check($enrolHandler->secretFor($uid) === $secret, 'First enrolment durable secret decrypts');
        check($enrolHandler->getRow($uid)['generation'] === $enrolled['generation'], 'First enrolment generation persisted');
        check($enrolHandler->acceptRecovery($uid, $enrolled['codes'][0], $enrolled['generation']), 'First enrolment recovery set persisted');
    }
    $sameUser = race(array_fill(0, 2, ['uid' => 62] + $enrolJob));
    $winners = array_values(array_filter($sameUser, static fn (array $result): bool => is_array($result['enrolled'])));
    check(count($winners) === 1, 'Concurrent enrolment of same account has exactly one winner');
    $winner = $winners[0]['enrolled'];
    check($enrolHandler->getRow(62)['generation'] === $winner['generation'] && $enrolHandler->secretFor(62) === $secret, 'Same-account winner is durable');
    $result = $db->query('SELECT COUNT(*) AS n FROM `' . $db->prefix('tokens') . '` WHERE uid = 62');
    check((int) $db->fetchArray($result)['n'] === 10, 'Losing enrolment leaves no extra recovery codes');
    check($enrolHandler->acceptRecovery(62, $winner['codes'][0], $winner['generation']), 'Winner recovery code usable');
    foreach ([60, 61, 62] as $uid) {
        check(is_string($enrolHandler->disable($uid)), 'Clear disposable concurrent enrolment secrets');
    }
    echo "PASS: concurrent first enrolments share one key; same account has one durable winner\n";
    $crypto = testCrypto();
    check($crypto->provisionKey(false), 'Provision initial key');
    $handler = new XoopsUser2faHandler($db, crypto: $crypto, installed: true);
    $enrol = static function (int $uid) use ($handler, $secret, $step, $now): array {
        $result = $handler->enrol($uid, $secret, $step - 2, $now);
        check(is_array($result) && count($result['codes']) === 10, 'Enrolment and ten recovery codes');
        return $result;
    };
    $account = $enrol(1);
    $job = ['uid' => 1, 'generation' => $account['generation'], 'now' => $now, 'step' => $step];
    $matched = XoopsTotp::matchStep($secret, XoopsTotp::codeAt($secret, $step), $now, $step - 2);
    check($matched === $step, 'Real TOTP verification');
    $results = race(array_fill(0, 2, ['action' => 'totp'] + $job));
    check(count(array_filter($results)) === 1, 'One TOTP step must grant exactly once');
    check(!$handler->acceptTotp(1, $step - 1, $account['generation'], $now), 'Older step refused');
    echo "PASS: concurrent TOTP and monotonic counter\n";

    $results = race(array_fill(0, 2, ['action' => 'recovery', 'code' => $account['codes'][0]] + $job));
    check(count(array_filter($results)) === 1, 'One recovery code must grant exactly once');
    echo "PASS: concurrent single-use recovery\n";

    $results = race(array_fill(0, 5, ['action' => 'failure'] + $job));
    check(count(array_filter($results, static fn ($result): bool => is_array($result) && $result['transitioned'])) === 1, 'Exactly one lock transition');
    $row = $handler->getRow(1);
    check($row['failed_attempts'] === 5 && $row['locked_until'] === $now + 900, 'All five concurrent failures counted');
    check(!$handler->acceptTotp(1, $step + 1, $account['generation'], $now), 'Lock refuses TOTP');
    check($handler->acceptRecovery(1, $account['codes'][1], $account['generation']), 'Recovery works during lock');
    check($handler->getRow(1)['failed_attempts'] === 0, 'Recovery clears throttle');
    echo "PASS: five concurrent failures, one lock transition, recovery unlock\n";

    // Repeat to exercise both possible serial orders under row locks.
    for ($uid = 2; $uid <= 11; ++$uid) {
        $account = $enrol($uid);
        $results = race([
            ['action' => 'recovery', 'uid' => $uid, 'code' => $account['codes'][0], 'generation' => $account['generation']],
            ['action' => 'reset', 'uid' => $uid],
        ]);
        check(is_string($results[1]), 'Reset succeeds during recovery race');
        check($handler->getRow($uid)['state'] === 'disabled', 'Reset wins final state');
        check(!$handler->acceptRecovery($uid, $account['codes'][0], $account['generation']), 'No recovery grant after reset');
        check(!(new XoopsTokenHandler($db))->verify($uid, XoopsUser2faHandler::RECOVERY_SCOPE, $account['codes'][1]), 'Reset revokes unused recovery token at storage boundary');
    }
    echo "PASS: recovery racing reset (10 independent pairs)\n";
    $results = race(array_fill(0, 5, ['action' => 'key']));
    check(is_string($results[0]) && count(array_unique($results)) === 1, 'Concurrent provisioning must preserve one key');
    echo "PASS: concurrent key provisioning\n";

    // Execute the complete cookie/session gate from common.php, keeping its
    // code unchanged. The enclosing site bootstrap is outside this fixture.
    $common = file_get_contents(XOOPS_ROOT_PATH . '/include/common.php');
    $start = strpos($common, '$rememberClaims = false;');
    check($start !== false, 'Common auth block start changed');
    $endMarker = 'unset($factorRow, $endSession);';
    $end = strpos($common, $endMarker, $start);
    check($end !== false, 'Common auth block end changed');
    file_put_contents($directory . '/common-auth.php', "<?php\n" . substr($common, $start, $end + strlen($endMarker) - $start));
    $manage = file_get_contents(XOOPS_ROOT_PATH . '/include/manage2fa.php');
    $end = strpos($manage, "require_once XOOPS_ROOT_PATH . '/class/template.php';");
    check($end !== false, 'Management rendering boundary changed');
    file_put_contents($directory . '/manage-controller.php', substr($manage, 0, $end));
    $request = static function (array $job): array {
        return race([$job + ['action' => 'request', 'session' => bin2hex(random_bytes(16))]])[0];
    };
    $account = $enrol(20);
    $begun = $request(['request' => 'begin', 'uid' => 20, 'seed' => ['xoopsUserId' => 999]]);
    check(!isset($begun['session']['xoopsUserId']) && isset($begun['session']['xoops2faPending']), 'Pending login stays anonymous');
    $challenge = race([['action' => 'request', 'request' => 'challenge', 'uid' => 20, 'session' => $begun['session_id'],
        'method' => 'POST', 'post' => ['xoops_2fa' => '1', 'csrf' => 'valid', 'code' => XoopsTotp::codeAt($secret, XoopsTotp::stepAt(time()))]]])[0];
    check(($challenge['session']['xoopsUserId'] ?? null) === 20 && $challenge['session']['xoops2faVerified'] === true, 'Separate challenge request loads completion helper and authenticates');
    check(!array_filter($challenge['cookies'], static fn (array $cookie): bool => $cookie[1] !== null), 'Enrolled login never receives remember cookie');
    echo "PASS: real pending session and separate challenge/completion request\n";

    $stale = $account['generation'];
    check($handler->acceptRecovery(20, $account['codes'][0], $stale), 'Consume before reset');
    check(is_string($handler->disable(20)), 'Reset after consume');
    $refused = $request(['request' => 'complete', 'uid' => 20, 'generation' => $stale]);
    check(!isset($refused['session']['xoopsUserId']) && $refused['message'] === _US_2FA_STARTAGAIN, 'Reset after consume refuses real completion');
    echo "PASS: reset between consumption and session completion\n";

    $account = $enrol(21);
    $generation = $account['generation'];
    foreach (['off', 'optional'] as $policy) {
        $result = $request(['request' => 'common', 'uid' => 21, 'policy' => $policy, 'seed' => ['xoopsUserId' => 21]]);
        check(!$result['authenticated'] && $result['session'] === [], 'Missing generation ends enrolled session under ' . $policy);
    }
    $result = $request(['request' => 'common', 'uid' => 21, 'seed' => ['xoopsUserId' => 21, 'xoops2faGeneration' => str_repeat('0', 32), 'xoops2faVerified' => true]]);
    check(!$result['authenticated'], 'Changed generation ends verified session');
    $result = $request(['request' => 'common', 'uid' => 99, 'seed' => ['xoopsUserId' => 99]]);
    check($result['authenticated'] && $result['session']['xoops2faGeneration'] === '', 'Absent row stamps and preserves session');
    $paused = $request(['request' => 'complete', 'uid' => 21, 'policy' => 'off']);
    check(($paused['session']['xoopsUserId'] ?? null) === 21 && !$paused['session']['xoops2faVerified'], 'Paused policy permits password session');
    check(!array_filter($paused['cookies'], static fn (array $cookie): bool => $cookie[1] !== null), 'Paused enrolled account gets no cookie');
    $resumed = $request(['request' => 'common', 'uid' => 21, 'seed' => $paused['session']]);
    check(!$resumed['authenticated'], 'Policy resume ends password-only enrolled session');
    $result = $request(['request' => 'common', 'uid' => 21, 'cookie' => []]);
    check(!$result['authenticated'] && !isset($result['session']['xoopsUserId']), 'Pre-enrolment cookie refuses without session seed');
    $result = $request(['request' => 'common', 'uid' => 99, 'cookie' => []]);
    check($result['authenticated'], 'Cookie missing fgen restores absent-row account');
    $result = $request(['request' => 'common', 'uid' => 99, 'cookie' => [], 'seed' => ['xoops2faPending' => ['uid' => 21]]]);
    check(!$result['authenticated'] && !isset($result['session']['xoopsUserId']), 'Cookie cannot bypass pending challenge');
    echo "PASS: common.php session generations, policy pause/resume, signed-cookie gates\n";

    $beforeKey = $crypto->loadKey();
    $setupGet = $request(['request' => 'manage', 'uid' => 30, 'seed' => ['xoopsUserId' => 30]]);
    check($handler->getRow(30) === null && $crypto->loadKey() === $beforeKey && !isset($setupGet['session']['xoops2faSetup']), 'Setup GET writes no row/key/setup state');
    $post = ['action' => 'begin', 'csrf' => 'valid', 'password' => 'correct-password'];
    $badPassword = $request(['request' => 'manage', 'uid' => 30, 'method' => 'POST', 'post' => ['password' => 'wrong'] + $post]);
    check(!isset($badPassword['session']['xoops2faSetup']) && $handler->getRow(30) === null, 'Setup requires current password');
    $badCsrf = $request(['request' => 'manage', 'uid' => 30, 'method' => 'POST', 'post' => ['csrf' => 'wrong'] + $post]);
    check(!isset($badCsrf['session']['xoops2faSetup']), 'Setup requires CSRF');
    $setup = $request(['request' => 'manage', 'uid' => 30, 'method' => 'POST', 'post' => $post]);
    check($setup['manage']['secret'] !== '' && $handler->getRow(30) === null, 'Begin produces pending secret without enrolment: ' . json_encode($setup['manage']));
    check(!str_contains(json_encode($setup['session']), $setup['manage']['secret']), 'Pending session stores encrypted secret only');
    $confirm = ['request' => 'manage', 'uid' => 30, 'method' => 'POST', 'post' => ['action' => 'confirm', 'csrf' => 'valid', 'code' => XoopsTotp::codeAt($setup['manage']['secret'], XoopsTotp::stepAt(time()))]];
    $confirmed = $request(['session' => $setup['session_id']] + $confirm);
    check(count($confirmed['manage']['codes']) === 10 && $confirmed['session']['xoops2faVerified'] === true, 'Confirmation issues ten codes and verifies session');
    check($confirmed['session_id'] !== $setup['session_id'] && !isset($confirmed['session']['xoops2faSetup']), 'Confirmation rotates session and drops pending setup');
    $secondTab = $request(['seed' => $setup['session']] + $confirm);
    check($secondTab['manage']['codes'] === [] && $handler->getRow(30)['generation'] === $confirmed['session']['xoops2faGeneration'], 'Second tab cannot replace confirmed factor');
    $refresh = $request(['request' => 'manage', 'uid' => 30, 'session' => $confirmed['session_id']]);
    check($refresh['manage']['codes'] === [], 'Recovery codes displayed only once');
    $otherSession = $request(['request' => 'common', 'uid' => 30, 'seed' => ['xoopsUserId' => 30]]);
    check(!$otherSession['authenticated'], 'Confirmation revokes prior unstamped session');
    echo "PASS: management GET, password/CSRF, encrypted setup, confirmation, second tab and one-time codes\n";

    foreach ([true, false] as $persistRehash) {
        $uid = $persistRehash ? 32 : 33;
        $rehashed = $request(['request' => 'manage', 'uid' => $uid, 'method' => 'POST', 'post' => $post, 'rehash' => true, 'rehash_persist' => $persistRehash]);
        check(isset($rehashed['session']['xoops2faSetup']), 'Reauthentication creates pending setup after rehash');
        if ($persistRehash) {
            $persistedHash = file_get_contents($directory . '/accounts/' . $uid);
            check(hash('sha256', $persistedHash) === $rehashed['session']['xoops2faSetup']['passdigest'], 'Setup digest uses reauthenticated object, not stale session actor');
        }
        $rehashedConfirm = $request(['request' => 'manage', 'uid' => $uid, 'session' => $rehashed['session_id'], 'method' => 'POST',
            'post' => ['action' => 'confirm', 'csrf' => 'valid', 'code' => XoopsTotp::codeAt($rehashed['manage']['secret'], XoopsTotp::stepAt(time()))]]);
        check(($handler->getRow($uid) !== null) === $persistRehash, 'Cross-request confirmation requires persisted rehash');
        if (!$persistRehash) {
            check($rehashedConfirm['manage']['codes'] === [] && !isset($rehashedConfirm['session']['xoops2faSetup']), 'Unpersisted rehash fails closed and clears stale setup');
        }
    }
    echo "PASS: reauthenticated password digest across requests, including failed persistence\n";

    $managePost = ['csrf' => 'valid', 'password' => 'correct-password', 'action' => 'regenerate', 'recovery' => $confirmed['manage']['codes'][0]];
    $replacement = $request(['request' => 'manage', 'uid' => 30, 'method' => 'POST', 'post' => $managePost]);
    check(count($replacement['manage']['codes']) === 10, 'Authenticated factor regenerates recovery set');
    $generation = $handler->getRow(30)['generation'];
    check(!$handler->acceptRecovery(30, $confirmed['manage']['codes'][1], $generation), 'Regeneration revokes old set');
    $disabled = $request(['request' => 'manage', 'uid' => 30, 'method' => 'POST', 'post' => ['action' => 'disable', 'recovery' => $replacement['manage']['codes'][0]] + $managePost]);
    check(!$disabled['manage']['enrolled'] && $handler->getRow(30)['state'] === 'disabled', 'Authenticated factor disables account');
    check(!$handler->acceptRecovery(30, $replacement['manage']['codes'][1], $generation), 'Disable revokes replacement recovery set');
    check(!(new XoopsTokenHandler($db))->verify(30, XoopsUser2faHandler::RECOVERY_SCOPE, $replacement['manage']['codes'][1]), 'Disable revokes unused recovery token at storage boundary');
    echo "PASS: transactional management recovery regeneration and disable\n";

    $account = $enrol(40);
    $keyStorage = new Xmf\Key\FileStorage($directory . '/data', substr(md5($prefix), 8, 8));
    $originalKey = $crypto->loadKey();
    check($keyStorage->delete('twofactor'), 'Remove only disposable encryption key');
    check(!$crypto->provisionKey(fn (): bool => $handler->hasEncryptedSecrets()), 'Cannot provision replacement key over encrypted rows');
    check($handler->stateFor(40) === 'unavailable', 'Missing key makes TOTP unavailable');
    check($handler->acceptRecovery(40, $account['codes'][0], $account['generation']), 'Recovery works with missing key');
    $setupGet = $request(['request' => 'manage', 'uid' => 41]);
    check(!$crypto->hasKey() && $handler->getRow(41) === null, 'Setup GET must not create first key');
    $keyStorage->save('twofactor', base64_encode(str_repeat('a', 31)));
    set_error_handler(static fn (): bool => true);
    try {
        check($handler->stateFor(40) === 'unavailable', '31-byte key makes TOTP unavailable');
        check($handler->acceptRecovery(40, $account['codes'][1], $account['generation']), 'Recovery works with malformed key');
    } finally {
        restore_error_handler();
        $keyStorage->save('twofactor', base64_encode($originalKey));
    }
    check($handler->stateFor(40) === 'enrolled', 'Restored original key decrypts enrolled row');
    $pendingBlob = $crypto->seal($secret, XoopsTwoFactorCrypto::pendingAad(40));
    check($crypto->open($pendingBlob, XoopsTwoFactorCrypto::rowAad(40, 'totp')) === null, 'Pending secret cannot become row ciphertext');
    $row = $handler->getRow(40);
    check($crypto->open($row['secret'], XoopsTwoFactorCrypto::rowAad(41, 'totp')) === null, 'Ciphertext cannot move between accounts');
    echo "PASS: missing/malformed encryption key, recovery independence, GET without key and AAD binding\n";

    $uninstalled = new XoopsUser2faHandler($db, crypto: $crypto, installed: false);
    check($db->exec('DROP TABLE `' . $db->prefix('user_2fa') . '`'), 'Remove disposable factor table to simulate old schema');
    check($uninstalled->getRow(1) === null && $uninstalled->stateFor(1) === 'none', 'File-first old schema lookup is none');
    $oldSession = $request(['request' => 'common', 'uid' => 99, 'installed' => false, 'seed' => ['xoopsUserId' => 99]]);
    check($oldSession['authenticated'], 'Old schema authenticated session survives file-first update');
    $upgradeRoot = dirname(XOOPS_ROOT_PATH) . '/upgrade';
    require $upgradeRoot . '/class/Xoops/Upgrade/XoopsUpgrade.php';
    require $upgradeRoot . '/class/Xoops/Upgrade/UpgradeControl.php';
    require $upgradeRoot . '/upd_2.7.3-to-2.7.4/index.php';
    $upgrade = new Upgrade_274($db, new Xoops\Upgrade\UpgradeControl($db));
    check(!$upgrade->check_user2fatable() && !$upgrade->check_twofactormode(), 'Old schema needs both upgrade tasks');
    check($upgrade->apply_user2fatable() && $upgrade->check_user2fatable(), 'Actual upgrade creates factor table');
    check(race(array_fill(0, 2, ['action' => 'migrate_mode'])) === [true, true], 'Concurrent upgrade preference creation succeeds');
    check($upgrade->check_twofactormode(), 'Actual upgrade creates preference and options');
    check($upgrade->apply_user2fatable() && $upgrade->apply_twofactormode(), 'Upgrade tasks rerun safely');
    $result = $db->query('SELECT COUNT(*) AS n FROM `' . $db->prefix('config') . '` WHERE conf_name = \'twofactor_mode\'');
    check((int) $db->fetchArray($result)['n'] === 1, 'Rerun does not duplicate preference');
    $result = $db->query('SELECT COUNT(*) AS n FROM `' . $db->prefix('configoption') . '`');
    check((int) $db->fetchArray($result)['n'] === 2, 'Rerun does not duplicate options');
    check($db->exec('DELETE FROM `' . $db->prefix('configoption') . '` ORDER BY confop_id LIMIT 1'), 'Simulate partially installed preference options');
    check(race(array_fill(0, 2, ['action' => 'migrate_mode'])) === [true, true], 'Concurrent option repair succeeds');
    $result = $db->query('SELECT COUNT(*) AS n FROM `' . $db->prefix('configoption') . '`');
    check((int) $db->fetchArray($result)['n'] === 2 && $upgrade->check_twofactormode(), 'Concurrent repair restores exactly two distinct options');
    $enrol(50);
    echo "PASS: absent-table file-first session, real 2.7.4 upgrade tasks, idempotency and upgraded enrolment\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    $exitCode = 1;
} finally {
    // Only exact names created by this run are eligible for deletion.
    foreach (array_reverse($created) as $name) {
        $db->exec('DROP TABLE IF EXISTS `' . $name . '`');
    }
    foreach (['data', 'concurrent', 'sessions', 'first-enrolment', 'accounts'] as $subdirectory) {
        foreach (glob($directory . '/' . $subdirectory . '/*') as $file) {
            unlink($file);
        }
        rmdir($directory . '/' . $subdirectory);
    }
    if (is_file($directory . '/common-auth.php')) {
        unlink($directory . '/common-auth.php');
    }
    if (is_file($directory . '/manage-controller.php')) {
        unlink($directory . '/manage-controller.php');
    }
    rmdir($directory);
}
exit($exitCode);
