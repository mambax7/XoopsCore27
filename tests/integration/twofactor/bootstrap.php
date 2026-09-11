<?php

declare(strict_types=1);

// Intentionally independent of mainfile.php: never read a site's credentials.
define('XOOPS_ROOT_PATH', dirname(__DIR__, 3) . '/htdocs');
define('XOOPS_VAR_PATH', (string) getenv('XOOPS_2FA_TEST_DIR'));
define('XOOPS_URL', 'http://localhost');
require_once XOOPS_ROOT_PATH . '/xoops_lib/vendor/autoload.php';
require_once XOOPS_ROOT_PATH . '/class/database/mysqldatabase.php';
require_once XOOPS_ROOT_PATH . '/kernel/user2fa.php';

function testDatabase(): XoopsMySQLDatabase
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new XoopsMySQLDatabaseSafe();
    $db->conn = new mysqli(
        getenv('XOOPS_2FA_TEST_HOST') ?: '127.0.0.1',
        getenv('XOOPS_2FA_TEST_USER') ?: 'root',
        getenv('XOOPS_2FA_TEST_PASSWORD') ?: '',
        (string) getenv('XOOPS_2FA_TEST_DATABASE'),
        (int) (getenv('XOOPS_2FA_TEST_PORT') ?: 3306),
    );
    $db->conn->set_charset('utf8mb4');
    $prefix = (string) getenv('XOOPS_2FA_TEST_PREFIX');
    if (!preg_match('/^twofactor_test_[a-f0-9]{16}$/D', $prefix)) {
        throw new RuntimeException('Invalid disposable test prefix');
    }
    $db->setPrefix($prefix);
    return $db;
}

function testCrypto(string $subdirectory = 'data'): XoopsTwoFactorCrypto
{
    $directory = XOOPS_VAR_PATH . '/' . $subdirectory;
    $systemSecret = substr(md5((string) getenv('XOOPS_2FA_TEST_PREFIX')), 8, 8);
    return new XoopsTwoFactorCrypto(new Xmf\Key\FileStorage($directory, $systemSecret), $directory . '/twofactor.lock');
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
