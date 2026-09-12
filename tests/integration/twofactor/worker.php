<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

try {
    $job = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $db = testDatabase();
    $handler = new XoopsUser2faHandler($db, crypto: testCrypto(), installed: $job['installed'] ?? true);
    // Each worker connects before the parent releases the barrier. A shared
    // PHP session would serialize requests, so these workers have no session.
    fwrite(STDOUT, "ready\n");
    check(trim((string) fgets(STDIN)) === 'go', 'Missing start barrier');
    if ($job['action'] === 'request') {
        require __DIR__ . '/request.php';
        exit;
    }
    if ($job['action'] === 'migrate_mode') {
        $upgradeRoot = dirname(XOOPS_ROOT_PATH) . '/upgrade';
        require $upgradeRoot . '/class/autoload.php';
        require $upgradeRoot . '/upd_2.7.3-to-2.7.4/index.php';
        $upgrade = new Upgrade_274($db, new Xoops\Upgrade\UpgradeControl($db));
        echo json_encode(['connection' => $db->conn->thread_id, 'result' => $upgrade->apply_twofactormode()], JSON_THROW_ON_ERROR) . "\n";
        exit;
    }
    if ($job['action'] === 'enrol') {
        $enrolCrypto = testCrypto('first-enrolment');
        $enrolHandler = new XoopsUser2faHandler($db, crypto: $enrolCrypto, installed: true);
        check($enrolCrypto->provisionKey(fn (): bool => $enrolHandler->hasEncryptedSecrets()), 'First enrolment key provisioning');
        try {
            $enrolled = $enrolHandler->enrol($job['uid'], $job['secret'], $job['step'], $job['now'], '');
        } catch (mysqli_sql_exception $e) {
            // MySQL may pick a concurrent absent-row INSERT as its deadlock
            // victim. Only this explicit retryable refusal is acceptable.
            if ($e->getCode() !== 1213) {
                throw $e;
            }
            $enrolled = false;
        }
        $enrolKey = $enrolCrypto->loadKey();
        echo json_encode(['connection' => $db->conn->thread_id, 'result' => ['enrolled' => $enrolled, 'key' => null === $enrolKey ? false : hash('sha256', $enrolKey)]], JSON_THROW_ON_ERROR) . "\n";
        exit;
    }
    $result = match ($job['action']) {
        'totp' => $handler->acceptTotp($job['uid'], $job['step'], $job['generation'], $job['now']),
        'recovery' => $handler->acceptRecovery($job['uid'], $job['code'], $job['generation']),
        'failure' => $handler->recordFailure($job['uid'], $job['now'], $job['generation']),
        'reset' => $handler->disable($job['uid']),
        'key' => (static function (): string|false {
            $crypto = testCrypto('concurrent');
            $key    = $crypto->provisionKey(false) ? $crypto->loadKey() : null;

            return null === $key ? false : hash('sha256', $key);
        })(),
        default => throw new RuntimeException('Unknown worker action'),
    };
    echo json_encode(['connection' => $db->conn->thread_id, 'result' => $result], JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
