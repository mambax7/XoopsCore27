<?php

declare(strict_types=1);

// A new PHP request, real file session and database handler. Presentation,
// account loading, mail and events are boundary doubles; auth code is included
// verbatim, with no namespace rewriting or replacement completion helper.
define('XOOPS_COOKIE_DOMAIN', 'localhost');
define('XOOPS_PROT', 'http://');
define('XOOPS_GROUP_ADMIN', 1);
define('XOOPS_CONF_AUTH', 7);
define('_DB_QUERY_ERROR', 'Database query failed: %s');
session_save_path(XOOPS_VAR_PATH . '/sessions');
session_id($job['session']);
session_start();
$_SERVER['REQUEST_METHOD'] = $job['method'] ?? 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_POST = $job['post'] ?? [];
$_COOKIE = [];
if (isset($job['seed'])) {
    $_SESSION = $job['seed'];
}
$GLOBALS['xoopsConfig'] = [
    'twofactor_mode' => $job['policy'] ?? 'optional', 'closesite' => 0,
    'closesite_okgrp' => [], 'theme_set_allowed' => [], 'theme_set' => 'default',
    'usercookie' => 'remember', 'sitename' => 'Test', 'adminmail' => 'test@example.invalid',
];
$GLOBALS['factorHandler'] = $handler;
$GLOBALS['xoopsDB'] = $db;
$GLOBALS['cookies'] = [];
$GLOBALS['testUid'] = $job['uid'];
$GLOBALS['testRehash'] = $job['rehash'] ?? false;
$GLOBALS['testRehashPersist'] = $job['rehash_persist'] ?? true;
class XoopsUser
{
    private readonly string $passwordHash;
    public function __construct(private readonly int $uid, ?string $passwordHash = null)
    {
        $file = XOOPS_VAR_PATH . '/accounts/' . $uid;
        $this->passwordHash = $passwordHash ?? (is_file($file) ? file_get_contents($file) : 'test-password-hash');
    }
    public function getVar(string $name, string $format = 's'): mixed
    {
        return ['uid' => $this->uid, 'pass' => $this->passwordHash, 'level' => 1, 'uname' => 'tester', 'theme' => '', 'last_login' => time()][$name] ?? '';
    }
    public function setVar(string $name, mixed $value): void {}
    public function getGroups(): array { return [2]; }
    public function isActive(): bool { return true; }
    public function isAdmin(): bool { return false; }
}
class XoopsPreload
{
    public static function getInstance(): self { return new self(); }
    public function triggerEvent(string $event, mixed $args = null): void {}
}
class XoopsDatabaseFactory
{
    public static function getDatabaseConnection(): XoopsMySQLDatabase { return $GLOBALS['xoopsDB']; }
}
require XOOPS_ROOT_PATH . '/class/userutility.php';
function xoops_getHandler(string $name): object
{
    return match ($name) {
        'user2fa' => $GLOBALS['factorHandler'],
        'member' => new class {
            public function getUser(int $uid): XoopsUser { return new XoopsUser($uid); }
            public function insertUser(XoopsUser $user): bool { return true; }
            public function loginUser(string $name, string $password): XoopsUser|false
            {
                if ($password !== 'correct-password') {
                    return false;
                }
                $uid = $GLOBALS['testUid'];
                if ($GLOBALS['testRehash']) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    if ($GLOBALS['testRehashPersist']) {
                        file_put_contents(XOOPS_VAR_PATH . '/accounts/' . $uid, $hash);
                    }
                    return new XoopsUser($uid, $hash);
                }
                return new XoopsUser($uid);
            }
        },
        'config' => new class { public function getConfigsByCat(int $category): array { return ['auth_method' => 'xoops', 'ldap_users_bypass' => []]; } },
        'notification' => new class { public function doLoginMaintenance(int $uid): void {} },
        default => throw new RuntimeException('Unexpected handler: ' . $name),
    };
}
function xoops_loadLanguage(string $name): void { require_once XOOPS_ROOT_PATH . '/language/english/' . $name . '.php'; }
function xoops_load(string $name): void {}
function xoops_validateThemeName(string $name): string { return ''; }
function xoops_setcookie(string $name, mixed $value, mixed ...$arguments): void { $GLOBALS['cookies'][] = [$name, $value]; }
function requestResult(array $result): never
{
    $result += ['session' => $_SESSION, 'cookies' => $GLOBALS['cookies'], 'session_id' => session_id()];
    session_write_close();
    echo json_encode(['connection' => $GLOBALS['xoopsDB']->conn->thread_id, 'result' => $result], JSON_THROW_ON_ERROR) . "\n";
    exit;
}
function redirect_header(string $url, int $seconds, string $message, bool $addRedirect = true): never
{
    requestResult(['redirect' => $url, 'message' => $message]);
}
function xoops_2fa_render(array $vars): never { requestResult(['render' => $vars]); }
function xoops_getMailer(): object
{
    return new class { public function __call(string $method, array $args): bool { return true; } };
}
$GLOBALS['xoops'] = new class { public function path(string $path): string { return XOOPS_ROOT_PATH . '/' . $path; } };
$GLOBALS['sess_handler'] = new class { public function regenerate_id(bool $delete): void { session_regenerate_id($delete); } };
$GLOBALS['xoopsSecurity'] = new class {
    public function check(): bool { return ($_POST['csrf'] ?? '') === 'valid'; }
    public function getErrors(): array { return ['Invalid CSRF token']; }
    public function getTokenHTML(): string { return '<input name="csrf" value="valid">'; }
};
xoops_loadLanguage('user');
xoops_loadLanguage('user2fa');
$user = new XoopsUser($job['uid']);
$GLOBALS['xoopsUser'] = $user;
if ($job['request'] === 'manage') {
    require XOOPS_VAR_PATH . '/manage-controller.php';
    requestResult(['manage' => ['error' => $error, 'message' => $message, 'codes' => $codes, 'secret' => $setupSecret, 'confirm' => $confirm, 'enrolled' => $enrolled]]);
}
if (isset($job['cookie'])) {
    $key = XoopsUserUtility::rememberKey();
    $claims = ['uid' => $job['uid'], 'pfp' => XoopsUserUtility::rememberFingerprint($user, $key->getSigning())] + $job['cookie'];
    $_COOKIE['remember'] = Xmf\Jwt\TokenFactory::build($key, $claims, 3600);
}
if ($job['request'] === 'common') {
    $member_handler = xoops_getHandler('member');
    $xoopsDB = $db;
    $xoopsConfig = $GLOBALS['xoopsConfig'];
    $xoopsUser = '';
    include XOOPS_VAR_PATH . '/common-auth.php';
    requestResult(['authenticated' => is_object($xoopsUser)]);
}
if ($job['request'] === 'challenge') {
    // Do not pre-load loginsession.php: this catches the missing-include bug
    // when the challenge is reached in a different request from the password.
    require XOOPS_ROOT_PATH . '/include/checklogin2fa.php';
}
require XOOPS_ROOT_PATH . '/include/loginsession.php';
if ($job['request'] === 'begin') {
    $row = $handler->getRow($job['uid']);
    xoops_login_begin_challenge($user, $handler->stateOfRow($row), $row['generation'], true, '');
}
if ($job['request'] === 'complete') {
    xoops_login_establish_session($user, true, '', $job['generation'] ?? null);
}
throw new RuntimeException('Unknown request');
