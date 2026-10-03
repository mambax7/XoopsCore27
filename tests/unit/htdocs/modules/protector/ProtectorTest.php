<?php

declare(strict_types=1);

namespace modulesprotector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Protector::class)]
class ProtectorTest extends TestCase
{
    private static bool $loaded = false;
    private \Protector $protector;

    public static function setUpBeforeClass(): void
    {
        if (!self::$loaded) {
            // Ensure Xmf autoloader is available
            if (file_exists(XOOPS_PATH . '/vendor/autoload.php')) {
                require_once XOOPS_PATH . '/vendor/autoload.php';
            }
            // Ensure protector var directory constant path exists conceptually
            require_once XOOPS_PATH . '/modules/protector/class/protector.php';
            require_once __DIR__ . '/ProtectorRenameFails.php';
            self::$loaded = true;
        }
    }

    /** @var array<string, mixed> Protector properties the tests assign, as found before each test */
    private array $savedProtectorState = [];

    /** @var array<string, array<mixed>> superglobals the tests assign, as found before each test */
    private array $savedSuperglobals = [];

    private const ASSIGNED_PROPERTIES = ['_conn', '_done_badext', '_done_intval', '_safe_badext', '_safe_contami', 'last_error_type', 'message'];

    protected function setUp(): void
    {
        $this->protector = \Protector::getInstance();
        // The singleton and the superglobals outlive each test: record what the
        // tests below change so tearDown() can put it back, whatever the order.
        foreach (self::ASSIGNED_PROPERTIES as $property) {
            $this->savedProtectorState[$property] = $this->protector->{$property};
        }
        $this->savedSuperglobals = ['_FILES' => $_FILES, '_GET' => $_GET, '_REQUEST' => $_REQUEST];
    }

    protected function tearDown(): void
    {
        foreach ($this->savedProtectorState as $property => $value) {
            $this->protector->{$property} = $value;
        }
        $_FILES   = $this->savedSuperglobals['_FILES'];
        $_GET     = $this->savedSuperglobals['_GET'];
        $_REQUEST = $this->savedSuperglobals['_REQUEST'];
    }

    // ---------------------------------------------------------------
    // Singleton
    // ---------------------------------------------------------------

    #[Test]
    public function getInstanceReturnsProtector(): void
    {
        $this->assertInstanceOf(\Protector::class, $this->protector);
    }

    #[Test]
    public function getInstanceReturnsSameInstance(): void
    {
        $a = \Protector::getInstance();
        $b = \Protector::getInstance();
        $this->assertSame($a, $b);
    }

    // ---------------------------------------------------------------
    // Default property values
    // ---------------------------------------------------------------

    #[Test]
    public function mydirnameIsProtector(): void
    {
        $this->assertSame('protector', $this->protector->mydirname);
    }

    #[Test]
    public function confIsArray(): void
    {
        $this->assertIsArray($this->protector->_conf);
    }

    #[Test]
    public function lastErrorTypeDefaultsToUnknown(): void
    {
        // Reset to default
        $this->protector->last_error_type = 'UNKNOWN';
        $this->assertSame('UNKNOWN', $this->protector->last_error_type);
    }

    #[Test]
    public function messageIsString(): void
    {
        $this->assertIsString($this->protector->message);
    }

    #[Test]
    public function warningDefaultsFalse(): void
    {
        $this->assertFalse($this->protector->warning);
    }

    #[Test]
    public function errorDefaultsFalse(): void
    {
        $this->assertFalse($this->protector->error);
    }

    #[Test]
    public function loggedDefaultsFalse(): void
    {
        $this->assertFalse($this->protector->_logged);
    }

    #[Test]
    public function shouldBeBannedDefaultsFalse(): void
    {
        $this->assertFalse($this->protector->_should_be_banned);
    }

    #[Test]
    public function shouldBeBannedTime0DefaultsFalse(): void
    {
        $this->assertFalse($this->protector->_should_be_banned_time0);
    }

    #[Test]
    public function doubtfulRequestsIsArray(): void
    {
        $this->assertIsArray($this->protector->_doubtful_requests);
    }

    #[Test]
    public function bigumbrellaDoubtfulsIsArray(): void
    {
        $this->assertIsArray($this->protector->_bigumbrella_doubtfuls);
    }

    #[Test]
    public function dblayertrapDoubtfulsIsArray(): void
    {
        $this->assertIsArray($this->protector->_dblayertrap_doubtfuls);
    }

    #[Test]
    public function dblayertrapDoubtfulNeedlesContainsExpectedValues(): void
    {
        $needles = $this->protector->_dblayertrap_doubtful_needles;
        $this->assertContains('information_schema', $needles);
        $this->assertContains('select', $needles);
        $this->assertContains("'", $needles);
        $this->assertContains('"', $needles);
    }

    #[Test]
    public function badGlobalsContainsExpectedEntries(): void
    {
        $bad = $this->protector->_bad_globals;
        $this->assertContains('GLOBALS', $bad);
        $this->assertContains('_SESSION', $bad);
        $this->assertContains('_GET', $bad);
        $this->assertContains('_POST', $bad);
        $this->assertContains('_COOKIE', $bad);
        $this->assertContains('_SERVER', $bad);
        $this->assertContains('_REQUEST', $bad);
        $this->assertContains('_ENV', $bad);
        $this->assertContains('_FILES', $bad);
        $this->assertContains('xoopsDB', $bad);
        $this->assertContains('xoopsUser', $bad);
        $this->assertContains('xoopsConfig', $bad);
        $this->assertContains('xoopsModule', $bad);
    }

    // ---------------------------------------------------------------
    // Done flags
    // ---------------------------------------------------------------

    #[Test]
    public function doneBadextDefaultsFalse(): void
    {
        $this->assertFalse($this->protector->_done_badext);
    }

    #[Test]
    public function doneIntvalDefaultsFalse(): void
    {
        $this->assertFalse($this->protector->_done_intval);
    }

    #[Test]
    public function doneDotdotDefaultsFalse(): void
    {
        $this->assertFalse($this->protector->_done_dotdot);
    }

    #[Test]
    public function doneNullbyteDefaultsFalse(): void
    {
        $this->assertFalse($this->protector->_done_nullbyte);
    }

    #[Test]
    public function doneContamiDefaultsFalse(): void
    {
        $this->assertFalse($this->protector->_done_contami);
    }

    #[Test]
    public function doneIsocomDefaultsFalse(): void
    {
        $this->assertFalse($this->protector->_done_isocom);
    }

    #[Test]
    public function doneUnionDefaultsFalse(): void
    {
        $this->assertFalse($this->protector->_done_union);
    }

    #[Test]
    public function doneDosDefaultsFalse(): void
    {
        $this->assertFalse($this->protector->_done_dos);
    }

    // ---------------------------------------------------------------
    // Safe flags
    // ---------------------------------------------------------------

    #[Test]
    public function safeBadextDefaultsTrue(): void
    {
        $this->assertTrue($this->protector->_safe_badext);
    }

    #[Test]
    public function safeIsocomDefaultsTrue(): void
    {
        $this->assertTrue($this->protector->_safe_isocom);
    }

    #[Test]
    public function safeUnionDefaultsTrue(): void
    {
        $this->assertTrue($this->protector->_safe_union);
    }

    // ---------------------------------------------------------------
    // getConf / setConn
    // ---------------------------------------------------------------

    #[Test]
    public function getConfReturnsArray(): void
    {
        $this->assertIsArray($this->protector->getConf());
    }

    #[Test]
    public function setConnSetsConnection(): void
    {
        $mockConn = 'fake_connection';
        $this->protector->setConn($mockConn);
        $this->assertSame($mockConn, $this->protector->_conn);
        // Clean up
        $this->protector->setConn(null);
    }

    // ---------------------------------------------------------------
    // getDblayertrapDoubtfuls
    // ---------------------------------------------------------------

    #[Test]
    public function getDblayertrapDoubtfulsReturnsArray(): void
    {
        $this->assertIsArray($this->protector->getDblayertrapDoubtfuls());
    }

    // ---------------------------------------------------------------
    // Static file path methods
    // ---------------------------------------------------------------

    #[Test]
    public function getFilepath4bwlimitContainsProtectorDir(): void
    {
        $path = \Protector::get_filepath4bwlimit();
        $this->assertStringContainsString('protector/bwlimit', $path);
    }

    #[Test]
    public function getFilepath4bwlimitStartsWithVarPath(): void
    {
        $path = \Protector::get_filepath4bwlimit();
        $this->assertStringStartsWith(XOOPS_VAR_PATH, $path);
    }

    #[Test]
    public function getFilepath4badipsContainsProtectorDir(): void
    {
        $path = \Protector::get_filepath4badips();
        $this->assertStringContainsString('protector/badips', $path);
    }

    #[Test]
    public function getFilepath4badipsStartsWithVarPath(): void
    {
        $path = \Protector::get_filepath4badips();
        $this->assertStringStartsWith(XOOPS_VAR_PATH, $path);
    }

    #[Test]
    public function getFilepath4group1ipsContainsProtectorDir(): void
    {
        $path = \Protector::get_filepath4group1ips();
        $this->assertStringContainsString('protector/group1ips', $path);
    }

    #[Test]
    public function getFilepath4group1ipsStartsWithVarPath(): void
    {
        $path = \Protector::get_filepath4group1ips();
        $this->assertStringStartsWith(XOOPS_VAR_PATH, $path);
    }

    #[Test]
    public function getFilepath4confighcacheContainsProtectorDir(): void
    {
        $path = $this->protector->get_filepath4confighcache();
        $this->assertStringContainsString('protector/configcache', $path);
    }

    #[Test]
    public function getFilepath4confighcacheStartsWithVarPath(): void
    {
        $path = $this->protector->get_filepath4confighcache();
        $this->assertStringStartsWith(XOOPS_VAR_PATH, $path);
    }

    #[Test]
    public function filePathsContainMd5Hash(): void
    {
        $path = \Protector::get_filepath4bwlimit();
        // Path should end with a 6-char hex hash
        $this->assertMatchesRegularExpression('/[a-f0-9]{6}$/', $path);
    }

    #[Test]
    public function filePathHashesAreConsistent(): void
    {
        $bw1 = \Protector::get_filepath4bwlimit();
        $bw2 = \Protector::get_filepath4bwlimit();
        $this->assertSame($bw1, $bw2);
    }

    // ---------------------------------------------------------------
    // check_contami_systemglobals
    // ---------------------------------------------------------------

    #[Test]
    public function checkContamiReturnsBoolean(): void
    {
        $result = $this->protector->check_contami_systemglobals();
        $this->assertIsBool($result);
    }

    #[Test]
    public function checkContamiReturnsSafeContamiFlag(): void
    {
        // By default _safe_contami is true (no contamination detected)
        $this->protector->_safe_contami = true;
        $this->assertTrue($this->protector->check_contami_systemglobals());
    }

    // ---------------------------------------------------------------
    // bigumbrella_outputcheck
    // ---------------------------------------------------------------

    #[Test]
    public function bigumbrellaOutputcheckReturnsInputWhenDisabled(): void
    {
        if (!defined('BIGUMBRELLA_DISABLED')) {
            define('BIGUMBRELLA_DISABLED', true);
        }
        $input = '<html><body>Hello World</body></html>';
        $result = $this->protector->bigumbrella_outputcheck($input);
        $this->assertSame($input, $result);
    }

    // ---------------------------------------------------------------
    // updateConfFromDb (returns false when no connection)
    // ---------------------------------------------------------------

    #[Test]
    public function updateConfFromDbReturnsFalseWithoutConnection(): void
    {
        $this->protector->_conn = null;
        $this->assertFalse($this->protector->updateConfFromDb());
    }

    // ---------------------------------------------------------------
    // get_ref_from_base64index
    // ---------------------------------------------------------------

    #[Test]
    public function getRefFromBase64IndexReturnsValue(): void
    {
        $data = ['key1' => ['key2' => 'found']];
        $indexes = [base64_encode('key1'), base64_encode('key2')];
        $result = $this->protector->get_ref_from_base64index($data, $indexes);
        $this->assertSame('found', $result);
    }

    #[Test]
    public function getRefFromBase64IndexReturnsFalseForNonArray(): void
    {
        $data = 'not_an_array';
        $indexes = [base64_encode('key1')];
        $result = $this->protector->get_ref_from_base64index($data, $indexes);
        $this->assertFalse($result);
    }

    #[Test]
    public function getRefFromBase64IndexEmptyIndexesReturnsData(): void
    {
        $data = ['key' => 'value'];
        $indexes = [];
        $result = $this->protector->get_ref_from_base64index($data, $indexes);
        $this->assertSame(['key' => 'value'], $result);
    }

    // ---------------------------------------------------------------
    // check_uploaded_files (with empty $_FILES)
    // ---------------------------------------------------------------

    #[Test]
    public function checkUploadedFilesReturnsTrueWhenNoFiles(): void
    {
        // Reset the done flag so the check actually runs
        $this->protector->_done_badext = false;
        $this->protector->_safe_badext = true;
        $_FILES = [];
        $result = $this->protector->check_uploaded_files();
        $this->assertTrue($result);
    }

    #[Test]
    public function checkUploadedFilesRejectsPHPExtension(): void
    {
        $this->protector->_done_badext = false;
        $this->protector->_safe_badext = true;
        $_FILES = [
            'upload' => [
                'name' => 'evil.php',
                'tmp_name' => '/tmp/phpXXXXXX',
                'error' => 0,
                'size' => 100,
                'type' => 'text/plain',
            ],
        ];
        $result = $this->protector->check_uploaded_files();
        $this->assertFalse($result);
        $this->assertStringContainsString('evil.php', $this->protector->message);
        // Clean up
        $_FILES = [];
        $this->protector->_safe_badext = true;
        $this->protector->message = '';
    }

    #[Test]
    public function checkUploadedFilesRejectsPhtmlExtension(): void
    {
        $this->protector->_done_badext = false;
        $this->protector->_safe_badext = true;
        $_FILES = [
            'upload' => [
                'name' => 'shell.phtml',
                'tmp_name' => '/tmp/phpXXXXXX',
                'error' => 0,
                'size' => 100,
                'type' => 'text/plain',
            ],
        ];
        $result = $this->protector->check_uploaded_files();
        $this->assertFalse($result);
        $_FILES = [];
        $this->protector->_safe_badext = true;
        $this->protector->message = '';
    }

    #[Test]
    public function checkUploadedFilesRejectsMultipleDotFiles(): void
    {
        $this->protector->_done_badext = false;
        $this->protector->_safe_badext = true;
        $_FILES = [
            'upload' => [
                'name' => 'evil.php.jpg',
                'tmp_name' => '/tmp/phpXXXXXX',
                'error' => 0,
                'size' => 100,
                'type' => 'image/jpeg',
            ],
        ];
        $result = $this->protector->check_uploaded_files();
        $this->assertFalse($result);
        $_FILES = [];
        $this->protector->_safe_badext = true;
        $this->protector->message = '';
    }

    #[Test]
    public function checkUploadedFilesSkipsErroredUploads(): void
    {
        $this->protector->_done_badext = false;
        $this->protector->_safe_badext = true;
        $_FILES = [
            'upload' => [
                'name' => 'evil.php',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => 0,
                'type' => '',
            ],
        ];
        $result = $this->protector->check_uploaded_files();
        $this->assertTrue($result);
        $_FILES = [];
    }

    #[Test]
    public function checkUploadedFilesRejectsCgiExtension(): void
    {
        $this->protector->_done_badext = false;
        $this->protector->_safe_badext = true;
        $_FILES = [
            'upload' => [
                'name' => 'script.cgi',
                'tmp_name' => '/tmp/phpXXXXXX',
                'error' => 0,
                'size' => 100,
                'type' => 'text/plain',
            ],
        ];
        $result = $this->protector->check_uploaded_files();
        $this->assertFalse($result);
        $_FILES = [];
        $this->protector->_safe_badext = true;
        $this->protector->message = '';
    }

    /**
     * Run check_uploaded_files() for one upload, restoring every value it
     * touches afterwards: $_FILES and the shared Protector instance's
     * _done_badext, _safe_badext, message and last_error_type.
     *
     * @param array<string, mixed> $file one $_FILES entry
     * @return array{0: bool, 1: string} check result and Protector's log message
     */
    private function checkOneUpload(array $file): array
    {
        $savedFiles = $_FILES;
        $saved      = [
            '_done_badext'    => $this->protector->_done_badext,
            '_safe_badext'    => $this->protector->_safe_badext,
            'message'         => $this->protector->message,
            'last_error_type' => $this->protector->last_error_type,
        ];
        $this->protector->_done_badext = false;
        $this->protector->_safe_badext = true;
        $this->protector->message      = '';
        $_FILES = ['upload' => $file];
        try {
            return [$this->protector->check_uploaded_files(), $this->protector->message];
        } finally {
            $_FILES = $savedFiles;
            foreach ($saved as $property => $value) {
                $this->protector->{$property} = $value;
            }
        }
    }

    private function writePixelPng(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'protector-png-');
        // 1x1 transparent PNG
        file_put_contents($path, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));

        return $path;
    }

    #[Test]
    public function checkOneUploadLeavesTheSharedStateAsItFoundIt(): void
    {
        $original = [$_FILES, $this->protector->_done_badext, $this->protector->_safe_badext, $this->protector->message, $this->protector->last_error_type];
        $before = ['sentinel' => ['name' => 'x.txt', 'tmp_name' => '', 'error' => 4, 'size' => 0, 'type' => '']];
        $_FILES = $before;
        $this->protector->_done_badext    = true;
        $this->protector->_safe_badext    = false;
        $this->protector->message         = 'earlier message';
        $this->protector->last_error_type = 'EARLIER';
        try {
            $this->checkOneUpload(['name' => 'photo.jpg', 'tmp_name' => '/no/such/file', 'error' => 0, 'size' => 1, 'type' => 'image/jpeg']);

            $this->assertSame($before, $_FILES);
            $this->assertTrue($this->protector->_done_badext);
            $this->assertFalse($this->protector->_safe_badext);
            $this->assertSame('earlier message', $this->protector->message);
            $this->assertSame('EARLIER', $this->protector->last_error_type);
        } finally {
            [$_FILES, $this->protector->_done_badext, $this->protector->_safe_badext, $this->protector->message, $this->protector->last_error_type] = $original;
        }
    }

    #[Test]
    public function checkUploadedFilesRejectsAnImageThatCannotBeInspectedAndSaysWhy(): void
    {
        [$result, $message] = $this->checkOneUpload([
            'name'     => 'photo.jpg',
            'tmp_name' => sys_get_temp_dir() . '/protector-missing-' . bin2hex(random_bytes(4)),
            'error'    => 0,
            'size'     => 100,
            'type'     => 'image/jpeg',
        ]);

        $this->assertFalse($result);
        $this->assertStringContainsString('photo.jpg', $message);
        $this->assertStringContainsString('could not be inspected', $message);
    }

    #[Test]
    public function checkUploadedFilesAcceptsARealImageWithItsOwnExtension(): void
    {
        $png = $this->writePixelPng();
        try {
            [$result, $message] = $this->checkOneUpload(['name' => 'pixel.png', 'tmp_name' => $png, 'error' => 0, 'size' => (int) filesize($png), 'type' => 'image/png']);
        } finally {
            unlink($png);
        }

        $this->assertTrue($result);
        $this->assertSame('', $message);
    }

    #[Test]
    public function checkUploadedFilesStillReportsACamouflagedImage(): void
    {
        $png = $this->writePixelPng();
        try {
            [$result, $message] = $this->checkOneUpload(['name' => 'pixel.gif', 'tmp_name' => $png, 'error' => 0, 'size' => (int) filesize($png), 'type' => 'image/gif']);
        } finally {
            unlink($png);
        }

        $this->assertFalse($result);
        $this->assertStringContainsString('camouflaged image file pixel.gif', $message);
    }

    /**
     * Option B: an image that cannot be inspected is rejected. The old
     * open_basedir fallback moved the upload into the public uploads/
     * directory under a predictable name (md5(time())) to look at it, and
     * left it there whenever the unlink failed. move_uploaded_file() cannot
     * run under the CLI test runner, so this is pinned on the source.
     */
    #[Test]
    public function checkUploadedFilesNeverMovesAnUploadToInspectIt(): void
    {
        $src = (string) file_get_contents(XOOPS_PATH . '/modules/protector/class/protector.php');
        $start = strpos($src, 'public function check_uploaded_files(');
        $this->assertNotFalse($start);
        $end  = strpos($src, 'public function ', $start + 1);
        $body = substr($src, $start, false === $end ? null : $end - $start);

        $this->assertStringNotContainsString('move_uploaded_file(', $body);
        $this->assertStringNotContainsString('protector_upload_temporary', $body);
        $this->assertStringNotContainsString('@unlink(', $body);
    }

    // ---------------------------------------------------------------
    // writeFileAtomic — complete, atomic replacement of Protector's files
    // ---------------------------------------------------------------

    /**
     * Call Protector::writeFileAtomic() and fail the test if any PHP warning
     * escapes it (PHP's file warnings name the full server path).
     */
    private function writeAtomic(string $path, string $content, string $class = \Protector::class): bool
    {
        $leaked = [];
        set_error_handler(static function (int $errno, string $message) use (&$leaked): bool {
            $leaked[] = $message;

            return true;
        });
        try {
            $result = (new \ReflectionMethod($class, 'writeFileAtomic'))->invoke(null, $path, $content);
        } finally {
            restore_error_handler();
        }
        $this->assertSame([], $leaked, 'writeFileAtomic() let a PHP warning escape.');

        return $result;
    }

    #[Test]
    public function writeFileAtomicWritesANewFile(): void
    {
        $path = sys_get_temp_dir() . '/protector-write-' . bin2hex(random_bytes(6));
        try {
            $this->assertTrue($this->writeAtomic($path, "1700000000\n"));
            $this->assertSame("1700000000\n", file_get_contents($path));
        } finally {
            is_file($path) && unlink($path);
        }
    }

    #[Test]
    public function writeFileAtomicReplacesLongerContentCompletely(): void
    {
        $path = sys_get_temp_dir() . '/protector-write-' . bin2hex(random_bytes(6));
        file_put_contents($path, str_repeat('old ban list ', 50));
        try {
            $this->assertTrue($this->writeAtomic($path, "short\n"));
            $this->assertSame("short\n", file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function writeFileAtomicFailsWithoutAWarningWhenTheDirectoryIsMissing(): void
    {
        $path = sys_get_temp_dir() . '/protector-missing-' . bin2hex(random_bytes(6)) . '/badips.serial';

        $this->assertFalse($this->writeAtomic($path, 'x'));
    }

    #[Test]
    public function writeFileAtomicFailsWithoutAWarningWhenThePathIsADirectory(): void
    {
        $dir = sys_get_temp_dir() . '/protector-dir-' . bin2hex(random_bytes(6));
        mkdir($dir);
        try {
            $this->assertFalse($this->writeAtomic($dir, 'x'));
        } finally {
            rmdir($dir);
        }
    }

    #[Test]
    public function writeFileAtomicLeavesNoTemporaryFileBehind(): void
    {
        $parent = sys_get_temp_dir() . '/protector-atomic-' . bin2hex(random_bytes(6));
        mkdir($parent);
        try {
            $this->assertTrue($this->writeAtomic($parent . '/badips.serial', "a:0:{}\n"));
            mkdir($parent . '/target-is-a-directory');
            $this->assertFalse($this->writeAtomic($parent . '/target-is-a-directory', 'x'));

            $this->assertSame(
                ['badips.serial', 'target-is-a-directory'],
                array_values(array_diff(scandir($parent) ?: [], ['.', '..'])),
                'Only the target and the directory may remain: no temporary file after success or failure.'
            );
        } finally {
            is_file($parent . '/badips.serial') && unlink($parent . '/badips.serial');
            is_dir($parent . '/target-is-a-directory') && rmdir($parent . '/target-is-a-directory');
            rmdir($parent);
        }
    }

    #[Test]
    public function writeFileAtomicKeepsTheTargetsPermissions(): void
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('chmod() only toggles the read-only flag on Windows.');
        }
        $path = sys_get_temp_dir() . '/protector-perms-' . bin2hex(random_bytes(6));
        file_put_contents($path, 'old');
        chmod($path, 0640);
        try {
            $this->assertTrue($this->writeAtomic($path, 'new'));
            clearstatcache();
            $this->assertSame(0640, fileperms($path) & 0777);
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function writeFileAtomicFallsBackToALockedInPlaceWriteWhenTheDirectoryIsReadOnly(): void
    {
        if ('\\' === DIRECTORY_SEPARATOR || (function_exists('posix_geteuid') && 0 === posix_geteuid())) {
            $this->markTestSkipped('A read-only directory cannot refuse new files here (Windows, or running as root).');
        }
        // A document root PHP may not write to, holding a writable .htaccess.
        $dir  = sys_get_temp_dir() . '/protector-ro-' . bin2hex(random_bytes(6));
        $path = $dir . '/.htaccess';
        mkdir($dir);
        file_put_contents($path, "old rules\n");
        chmod($path, 0644);
        chmod($dir, 0555);
        try {
            $this->assertTrue($this->writeAtomic($path, "deny from 192.0.2.1\n"));
            $this->assertSame("deny from 192.0.2.1\n", file_get_contents($path));
            $this->assertSame(['.htaccess'], array_values(array_diff(scandir($dir) ?: [], ['.', '..'])));
        } finally {
            chmod($dir, 0755);
            unlink($path);
            rmdir($dir);
        }
    }

    #[Test]
    public function writeFileAtomicRefusesAnExistingFileItCannotWrite(): void
    {
        if (function_exists('posix_geteuid') && 0 === posix_geteuid()) {
            $this->markTestSkipped('root can write any file.');
        }
        // A writable directory holding a .htaccess PHP may neither read nor
        // write: fopen('w') refused it, and so must the rename.
        $parent = sys_get_temp_dir() . '/protector-rofile-' . bin2hex(random_bytes(6));
        $path   = $parent . '/.htaccess';
        mkdir($parent);
        file_put_contents($path, "RewriteEngine On\n");
        chmod($path, 0444);
        try {
            $this->assertFalse($this->writeAtomic($path, "deny from 192.0.2.1\n"));
            chmod($path, 0644);
            $this->assertSame("RewriteEngine On\n", file_get_contents($path), 'The file it cannot write must keep its rules.');
            $this->assertSame(['.htaccess'], array_values(array_diff(scandir($parent) ?: [], ['.', '..'])), 'No temporary file may be left.');
        } finally {
            chmod($path, 0644);
            unlink($path);
            rmdir($parent);
        }
    }

    #[Test]
    public function writeFileAtomicKeepsTheOldFileAndCleansUpWhenTheRenameFails(): void
    {
        $parent = sys_get_temp_dir() . '/protector-renamefail-' . bin2hex(random_bytes(6));
        $path   = $parent . '/badips.serial';
        mkdir($parent);
        file_put_contents($path, "old\n");
        try {
            $this->assertFalse($this->writeAtomic($path, "new\n", ProtectorRenameFails::class));
            $this->assertSame("old\n", file_get_contents($path), 'A failure after the temporary file exists must leave the target untouched.');
            $this->assertSame(['badips.serial'], array_values(array_diff(scandir($parent) ?: [], ['.', '..'])), 'The temporary file must be removed.');
        } finally {
            unlink($path);
            rmdir($parent);
        }
    }

    #[Test]
    public function writeFileAtomicDoesNotTruncateTheTargetWhenTheDirectoryIsWritable(): void
    {
        // A temporary file that cannot be opened in a writable directory (disk
        // full, quota) must not fall through to the truncating in-place write.
        $body = self::methodBody(self::protectorSource('class/protector.php'), 'writeFileAtomic');

        $this->assertSame(1, preg_match('/!is_writable\(\s*dirname\(\s*\$path\s*\)\s*\)\s*&&[\s\S]*?writeFileInPlace\(/', $body), 'The in-place fallback must require a directory that refuses new files.');
    }

    #[Test]
    public function writeFileAtomicRenamesACompleteFileIntoPlace(): void
    {
        $src  = self::protectorSource('class/protector.php');
        $body = self::methodBody($src, 'writeFileAtomic');

        $this->assertSame(1, preg_match('/static::moveIntoPlace\(\s*\$tmp\s*,\s*\$path\s*\)/', $body), 'The complete temporary file must be moved over the target.');
        $this->assertSame(1, preg_match('/\brename\(\s*\$tmp\s*,\s*\$path\s*\)/', self::methodBody($src, 'moveIntoPlace')), 'moveIntoPlace() must be a rename().');
        $this->assertSame(1, preg_match('/\$complete\s*=\s*fclose\(\s*\$fp\s*\)\s*&&\s*\$complete/', $body), 'A failure reported by fclose() must keep the old file in place.');
        $this->assertSame(0, preg_match('/\bftruncate\(|fopen\(\s*\$path\b/', $body), 'The live file must never be opened or truncated in place.');
    }

    private static function protectorSource(string $relative): string
    {
        $src = file_get_contents(XOOPS_PATH . '/modules/protector/' . $relative);
        self::assertNotFalse($src);

        return $src;
    }

    private static function methodBody(string $src, string $method): string
    {
        $start = strpos($src, 'function ' . $method . '(');
        self::assertNotFalse($start, "$method() not found");
        // The method ends where the next method of any visibility begins.
        $end = 1 === preg_match('/\n    (?:(?:public|protected|private|static|final|abstract)\s+)*function\s/', $src, $m, PREG_OFFSET_CAPTURE, $start + 1)
            ? $m[0][1]
            : false;

        return substr($src, $start, false === $end ? null : $end - $start);
    }

    #[Test]
    public function banListWritersGoThroughTheAtomicWriter(): void
    {
        $src = self::protectorSource('class/protector.php');

        foreach (['write_file_bwlimit', 'write_file_badips'] as $method) {
            $body = self::methodBody($src, $method);
            $this->assertSame(1, preg_match('/return\s+static::writeFileAtomic\(/', $body), "$method() must return the result of writeFileAtomic().");
            $this->assertSame(0, preg_match('/@\s*(fopen|flock)\(/', $body), "$method() still suppresses errors.");
        }
    }

    #[Test]
    public function denyByHtaccessReportsAFailedWrite(): void
    {
        $body = self::methodBody(self::protectorSource('class/protector.php'), 'deny_by_htaccess');

        $this->assertSame(1, preg_match('/return\s+static::writeFileAtomic\(\s*\$target_htaccess\s*,/', $body), 'The ban must report whether .htaccess was written.');
        $this->assertSame(1, preg_match('/static::writeFileAtomic\(\s*\$backup_htaccess\s*,/', $body), 'The backup must go through the checked writer.');
        $this->assertSame(0, preg_match('/\bfopen\(|@\s*flock\(/', $body), 'deny_by_htaccess() still opens files directly.');
    }

    #[Test]
    public function noProtectorConstantIsDefinedWithErrorSuppression(): void
    {
        $this->assertSame(0, preg_match('/@\s*define\(/', self::protectorSource('class/protector.php')));
        $this->assertSame(0, preg_match('/@\s*define\(/', self::protectorSource('include/precheck_functions.php')));
        $this->assertSame(1, preg_match("/defined\\('XOOPS_DB_ALTERNATIVE'\\)\\s*\\|\\|\\s*define\\('XOOPS_DB_ALTERNATIVE'/", self::protectorSource('class/protector.php')));
    }

    // ---------------------------------------------------------------
    // intval_allrequestsendid — sanitizes *id keys
    // ---------------------------------------------------------------

    #[Test]
    public function intvalAllrequestsendidSanitizesIdKeys(): void
    {
        $this->protector->_done_intval = false;
        $val = "123'; DROP TABLE--";
        $_GET['catid'] = $val;
        $_REQUEST['catid'] = $val;
        $this->protector->intval_allrequestsendid();
        // regex /[^0-9a-zA-Z_-]/ strips ', ;, and spaces
        $this->assertSame('123DROPTABLE--', $_GET['catid']);
        // Clean up
        unset($_GET['catid'], $_REQUEST['catid']);
    }

    #[Test]
    public function intvalAllrequestsendidLeavesNonIdKeysAlone(): void
    {
        $this->protector->_done_intval = false;
        $_GET['name'] = "test value with spaces";
        $this->protector->intval_allrequestsendid();
        $this->assertSame("test value with spaces", $_GET['name']);
        unset($_GET['name']);
    }

    #[Test]
    public function intvalAllrequestsendidReturnsTrueWhenAlreadyDone(): void
    {
        $this->protector->_done_intval = true;
        $this->assertTrue($this->protector->intval_allrequestsendid());
    }

    // ---------------------------------------------------------------
    // stopForumSpamLookup — returns false when curl is not available
    // or builds proper query string
    // ---------------------------------------------------------------

    #[Test]
    public function stopForumSpamLookupReturnsFalseForEmptyParams(): void
    {
        $result = $this->protector->stopForumSpamLookup('', '', '');
        $this->assertFalse($result);
    }
}
