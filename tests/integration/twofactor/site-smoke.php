<?php
/**
 * Two-factor site smoke: installed HTTP flows against a seeded site
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package   core
 * @since     2.7.4
 */
/** Full installed-site HTTP smoke, using only explicitly disposable database settings. */
declare(strict_types=1);

if (!getenv('XOOPS_2FA_TEST_DATABASE')) {
    echo "SKIP: set XOOPS_2FA_TEST_DATABASE to an explicitly disposable database.\n";
    exit(0);
}
foreach (['mysqli', 'sodium', 'dom'] as $extension) {
    if (!extension_loaded($extension)) {
        throw new RuntimeException("Missing extension: $extension");
    }
}
function siteCheck(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
function siteCopy(string $source, string $target): void
{
    mkdir($target, 0700);
    foreach (new DirectoryIterator($source) as $file) {
        if ($file->isDot() || $file->isLink()) { continue; }
        $name = $file->getFilename();
        if (in_array($name, ['xoops_lib', 'xoops_data', 'upgrade', '.git', 'node_modules', 'vendor', 'mainfile.php'], true)) { continue; }
        if ($file->isDir()) {
            siteCopy($file->getPathname(), $target . '/' . $name);
        } elseif (in_array(strtolower($file->getExtension()), ['php', 'tpl', 'html', 'css', 'sql', 'json', 'txt'], true)) {
            siteCheck(copy($file->getPathname(), $target . '/' . $name), 'Cannot copy fixture file');
        }
    }
}
function siteRemove(string $path, string $root): void
{
    $real = realpath($path);
    siteCheck($real !== false && ($real === $root || str_starts_with($real, $root . DIRECTORY_SEPARATOR)), 'Cleanup escaped temporary root');
    foreach (new DirectoryIterator($path) as $file) {
        if ($file->isDot()) { continue; }
        if ($file->isDir() && !$file->isLink()) { siteRemove($file->getPathname(), $root); }
        else { chmod($file->getPathname(), 0600); unlink($file->getPathname()); }
    }
    rmdir($path);
}
function siteRequest(string $url, array &$cookies, ?array $post = null): array
{
    $headers = ['Connection: close', 'Referer: ' . $GLOBALS['siteUrl'] . '/user.php'];
    if ($cookies !== []) { $headers[] = 'Cookie: ' . implode('; ', array_map(static fn ($k, $v) => $k . '=' . $v, array_keys($cookies), $cookies)); }
    if ($post !== null) { $headers[] = 'Content-Type: application/x-www-form-urlencoded'; }
    $context = stream_context_create(['http' => ['method' => $post === null ? 'GET' : 'POST', 'header' => implode("\r\n", $headers), 'content' => $post === null ? '' : http_build_query($post), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 20]]);
    $body = file_get_contents($url, false, $context);
    siteCheck(is_string($body), 'HTTP request failed');
    foreach ($http_response_header as $header) {
        if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i', $header, $match)) { $cookies[$match[1]] = $match[2]; }
    }
    siteCheck(!preg_match('/^HTTP\/\S+ 5\d\d/', $http_response_header[0]), 'HTTP server error: ' . strip_tags(substr($body, 0, 1000)));
    return ['body' => $body, 'headers' => implode("\n", $http_response_header)];
}
function siteToken(array $page): array
{
    $dom = new DOMDocument();
    @$dom->loadHTML($page['body']);
    $token = [];
    foreach ($dom->getElementsByTagName('input') as $input) {
        $name = $input->getAttribute('name');
        if (str_starts_with($name, 'XOOPS_TOKEN')) { $token[$name] = $input->getAttribute('value'); }
    }
    siteCheck($token !== [], 'Real CSRF fields missing from rendered page: ' . substr(strip_tags($page['body']), 0, 500));
    return $token;
}
function siteSession(mysqli $db, string $prefix, array $cookies): array
{
    $id = $cookies['PHPSESSID'] ?? '';
    $stmt = $db->prepare("SELECT sess_data FROM `{$prefix}_session` WHERE sess_id=?");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    siteCheck(is_array($row), 'HTTP session was not persisted by XOOPS');
    $data = unserialize($row['sess_data'], ['allowed_classes' => false]);
    siteCheck(is_array($data), 'Unexpected PHP session encoding');
    return $data;
}

$source = dirname(__DIR__, 3) . '/htdocs';
$prefix = 'twofactor_http_' . bin2hex(random_bytes(8));
$temp = str_replace('\\', '/', sys_get_temp_dir()) . '/' . $prefix;
$server = null;
$db = null;
$tables = [];
$status = 0;
try {
    mkdir($temp, 0700);
    $docroot = $temp . '/htdocs';
    siteCopy($source, $docroot);
    foreach (['data', 'data/data', 'data/configs', 'data/caches', 'data/caches/smarty_compile', 'data/caches/smarty_cache', 'data/caches/xoops_cache', 'data/logs'] as $dir) { mkdir($temp . '/' . $dir, 0700); }
    file_put_contents($temp . '/data/configs/xoopsconfig.php', '<?php return [];');
    copy($source . '/install/include/license.dist.php', $temp . '/data/data/license.php');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    siteCheck(is_resource($socket), 'Cannot reserve HTTP port');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $siteUrl = 'http://' . $address;
    $main = file_get_contents($source . '/mainfile.dist.php');
    foreach (['XOOPS_ROOT_PATH' => $docroot, 'XOOPS_PATH' => str_replace('\\', '/', realpath($source . '/xoops_lib')), 'XOOPS_VAR_PATH' => $temp . '/data', 'XOOPS_URL' => $siteUrl] as $key => $value) {
        $main = preg_replace('/define\(\'' . $key . '\', \'[^\']*\'\);/', 'define(' . var_export($key, true) . ', ' . var_export($value, true) . ');', $main);
    }
    file_put_contents($docroot . '/mainfile.php', $main);
    $secure = "<?php\n";
    foreach (['XOOPS_DB_TYPE' => 'mysql', 'XOOPS_DB_CHARSET' => 'utf8mb4', 'XOOPS_DB_PREFIX' => $prefix, 'XOOPS_DB_HOST' => (getenv('XOOPS_2FA_TEST_HOST') ?: '127.0.0.1') . ':' . (getenv('XOOPS_2FA_TEST_PORT') ?: '3306'), 'XOOPS_DB_USER' => getenv('XOOPS_2FA_TEST_USER') ?: 'root', 'XOOPS_DB_PASS' => getenv('XOOPS_2FA_TEST_PASSWORD') ?: '', 'XOOPS_DB_NAME' => getenv('XOOPS_2FA_TEST_DATABASE'), 'XOOPS_DB_PCONNECT' => 0] as $key => $value) {
        $secure .= 'define(' . var_export($key, true) . ', ' . var_export($value, true) . ");\n";
    }
    file_put_contents($temp . '/data/data/secure.php', $secure);
    putenv('XOOPS_2FA_HTTP_ROOT=' . $docroot);
    $db = new mysqli(getenv('XOOPS_2FA_TEST_HOST') ?: '127.0.0.1', getenv('XOOPS_2FA_TEST_USER') ?: 'root', getenv('XOOPS_2FA_TEST_PASSWORD') ?: '', (string) getenv('XOOPS_2FA_TEST_DATABASE'), (int) (getenv('XOOPS_2FA_TEST_PORT') ?: 3306));
    $db->set_charset('utf8mb4');
    preg_match_all('/CREATE TABLE (\w+)\s*\(/', file_get_contents($source . '/install/sql/mysql.structure.sql'), $ddl);
    $tables = array_map(static fn ($name) => $prefix . '_' . $name, $ddl[1]);
    $seed = proc_open([PHP_BINARY, __DIR__ . '/site/seed.php'], [['pipe', 'r'], ['file', $temp . '/seed.log', 'w'], ['file', $temp . '/seed.log', 'a']], $pipes, $docroot, null, ['create_no_window' => true]);
    siteCheck(is_resource($seed), 'Could not start installer seed');
    fclose($pipes[0]);
    $seedExit = proc_close($seed);
    siteCheck($seedExit === 0 && str_contains(file_get_contents($temp . '/seed.log'), 'Installed shipped schema and seed data'), 'Seed failed: ' . file_get_contents($temp . '/seed.log'));
    // Mail is intentionally unavailable; the real best-effort notice code runs.
    $server = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'session.name=PHPSESSID', '-d', 'session.serialize_handler=php_serialize', '-d', 'disable_functions=mail,popen', '-d', 'SMTP=127.0.0.1', '-d', 'smtp_port=1', '-S', $address, '-t', $docroot], [['pipe', 'r'], ['file', $temp . '/server.log', 'a'], ['file', $temp . '/server.log', 'a']], $pipes, $docroot, null, ['create_no_window' => true]);
    siteCheck(is_resource($server), 'Could not start HTTP server');
    fclose($pipes[0]);
    for ($i = 0; $i < 100; ++$i) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($ready) { fclose($ready); break; }
        usleep(50000);
    }
    $cookies = [];
    $page = siteRequest($siteUrl . '/user.php', $cookies);
    siteCheck(str_contains($page['body'], 'uname'), 'Installed login page did not render: ' . substr(strip_tags($page['body']), 0, 1500));
    $page = siteRequest($siteUrl . '/user.php', $cookies, ['op' => 'login', 'uname' => 'smokeadmin', 'pass' => 'test-password-only']);
    $page = siteRequest($siteUrl . '/user.php?op=2fa_manage', $cookies);
    siteCheck(str_contains($page['body'], 'Your current password'), 'Management labels did not render');
    siteCheck(str_contains(strtolower($page['headers']), 'cache-control: no-store') && str_contains(strtolower($page['headers']), 'x-frame-options: deny'), 'Missing sensitive-page headers');
    $page = siteRequest($siteUrl . '/user.php', $cookies, ['op' => '2fa_setup', 'action' => 'begin', 'password' => 'test-password-only']);
    siteCheck(!isset(siteSession($db, $prefix, $cookies)['xoops2faSetup']), 'Missing CSRF began enrolment');
    $page = siteRequest($siteUrl . '/user.php', $cookies, siteToken($page) + ['op' => '2fa_setup', 'action' => 'begin', 'password' => 'wrong-password']);
    siteCheck(str_contains($page['body'], 'password was not accepted') && !isset(siteSession($db, $prefix, $cookies)['xoops2faSetup']), 'Wrong password began enrolment');
    $page = siteRequest($siteUrl . '/user.php', $cookies, siteToken($page) + ['op' => '2fa_setup', 'action' => 'begin', 'password' => 'test-password-only']);
    siteCheck(preg_match('/<code[^>]*>([A-Z2-7]{32})<\/code>/', $page['body'], $match) === 1, 'Manual enrolment secret did not render');
    $secret = $match[1];
    $pending = siteSession($db, $prefix, $cookies);
    siteCheck(isset($pending['xoops2faSetup']['blob']) && !str_contains(serialize($pending), $secret), 'Setup session contains plaintext secret');
    $oldSessionId = $cookies['PHPSESSID'];
    define('XOOPS_ROOT_PATH', $source);
    require $source . '/class/XoopsTotp.php';
    $page = siteRequest($siteUrl . '/user.php', $cookies, siteToken($page) + ['op' => '2fa_setup', 'action' => 'confirm', 'code' => XoopsTotp::codeAt($secret, XoopsTotp::stepAt(time()))]);
    siteCheck(str_contains($page['body'], 'Save these recovery codes now'), 'Confirmation did not display recovery codes');
    preg_match_all('/<code[^>]*>([A-Z2-7 ]{26,40})<\/code>/', $page['body'], $matches);
    $codes = array_map(static fn ($code) => str_replace(' ', '', $code), $matches[1]);
    siteCheck(count($codes) === 10, 'Expected ten rendered recovery codes');
    $session = siteSession($db, $prefix, $cookies);
    siteCheck($cookies['PHPSESSID'] !== $oldSessionId && ($session['xoops2faVerified'] ?? false) === true && !isset($session['xoops2faSetup']), 'Confirmation did not rotate and verify the session');
    $page = siteRequest($siteUrl . '/user.php?op=2fa_manage', $cookies);
    siteCheck(!str_contains($page['body'], $codes[0]) && str_contains($page['body'], 'An authenticator is enrolled'), 'Recovery codes replayed or enrolled session lost');
    echo "PASS: full mainfile/common boot, password login, real CSRF, setup/confirmation and rendered codes\n";
    // Activate Profile and clear only this site's generated preload cache.
    $db->query("UPDATE `{$prefix}_modules` SET isactive=1 WHERE dirname='profile'");
    foreach (glob($temp . '/data/caches/xoops_cache/*') as $file) { if (is_file($file)) { unlink($file); } }
    $page = siteRequest($siteUrl . '/modules/profile/user.php?op=2fa_manage', $cookies);
    siteCheck(str_contains($page['body'], 'An authenticator is enrolled'), 'Profile management route failed');
    $page = siteRequest($siteUrl . '/user.php?op=2fa_manage', $cookies);
    siteCheck(str_contains($page['body'], 'An authenticator is enrolled'), 'Profile preload hijacked core management');
    echo "PASS: Profile enabled route and core preload exemption\n";
    // A separate client must cross the real pending/challenge session boundary.
    $challengeCookies = [];
    siteRequest($siteUrl . '/user.php', $challengeCookies, ['op' => 'login', 'uname' => 'smokeadmin', 'pass' => 'test-password-only']);
    $session = siteSession($db, $prefix, $challengeCookies);
    siteCheck(isset($session['xoops2faPending']) && !isset($session['xoopsUserId']), 'Password step authenticated before second factor');
    $challenge = siteRequest($siteUrl . '/user.php?op=2fa', $challengeCookies);
    $challenge = siteRequest($siteUrl . '/user.php?op=2fa', $challengeCookies, siteToken($challenge) + ['xoops_2fa' => '1', 'recovery' => $codes[0]]);
    $page = siteRequest($siteUrl . '/user.php?op=2fa_manage', $challengeCookies);
    siteCheck(str_contains($page['body'], 'An authenticator is enrolled'), 'Separate real HTTP recovery challenge failed');
    $db->query("UPDATE `{$prefix}_config` SET conf_value='1' WHERE conf_name='closesite'");
    $closedCookies = [];
    siteRequest($siteUrl . '/user.php', $closedCookies, ['xoops_login' => '1', 'uname' => 'smokeadmin', 'pass' => 'test-password-only']);
    $page = siteRequest($siteUrl . '/user.php?op=2fa', $closedCookies);
    siteCheck(str_contains($page['body'], 'name="recovery"'), 'Closed site blocked administrator challenge');
    $db->query("UPDATE `{$prefix}_config` SET conf_value='0' WHERE conf_name='closesite'");
    echo "PASS: separate HTTP challenge, real session transport, closed-site challenge\n";
    $page = siteRequest($siteUrl . '/modules/system/admin.php?fct=users&op=users_2fa_reset&uid=1', $cookies);
    siteCheck(str_contains($page['body'], 'administrator password'), 'Users-admin reset did not render');
    $page = siteRequest($siteUrl . '/modules/system/admin.php?fct=users', $cookies, siteToken($page) + ['op' => 'users_2fa_reset', 'uid' => 1, 'action' => 'reset', 'password' => 'test-password-only']);
    siteCheck(str_contains($page['body'], 'authentication was reset'), 'Password-confirmed administrator reset failed');
    $factor = $db->query("SELECT state FROM `{$prefix}_user_2fa` WHERE uid=1")->fetch_assoc();
    siteCheck($factor['state'] === 'disabled', 'Admin reset did not persist');
    $page = siteRequest($siteUrl . '/user.php?op=2fa_manage', $challengeCookies);
    siteCheck(str_contains($page['body'], 'Set up an authenticator'), 'Reset unexpectedly logged out an existing session');
    $sessions = $db->query("SELECT COUNT(*) AS n FROM `{$prefix}_session`")->fetch_assoc();
    siteCheck((int) $sessions['n'] > 0, 'Real XOOPS database sessions were not persisted');
    echo "PASS: real users-admin permissions/password/CSRF/reset and MySQL session persistence\n";
} catch (Throwable $e) {
    $status = 1;
    fwrite(STDERR, $e->getMessage() . "\n");
    if (is_file($temp . '/server.log')) { fwrite(STDERR, substr(file_get_contents($temp . '/server.log'), -6000)); }
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($db instanceof mysqli) {
        $db->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) { $db->query('DROP TABLE IF EXISTS `' . $table . '`'); }
        $db->close();
    }
    if (is_dir($temp)) { siteRemove($temp, realpath($temp)); }
}
exit($status);
