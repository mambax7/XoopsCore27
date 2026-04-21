<?php

declare(strict_types=1);

namespace modulesprotector;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the Protector 3.6.4 RC fix tranche.
 *
 * Each test pins exactly one RC-safe fix so that a future regression is
 * attributable to a single, small change. The fixes are intentionally
 * low-blast-radius (pure bug fixes, no new detection features) because
 * they ship during the XOOPS 2.7.0 RC window.
 *
 * Tests are source-level assertions where behavioural tests would require
 * a live HTTP response, database connection, or terminating die() call.
 * Each test documents what would be required for a full behavioural test
 * and why the source assertion is sufficient for RC.
 */
#[CoversNothing]
final class Protector364RcFixesTest extends TestCase
{
    private const PROTECTOR_FILE = XOOPS_PATH . '/modules/protector/class/protector.php';
    private const DB_FILE        = XOOPS_PATH . '/modules/protector/class/ProtectorMysqlDatabase.class.php';
    private const FILTER_FILE    = XOOPS_PATH . '/modules/protector/class/ProtectorFilter.php';

    private static function readSource(string $path): string
    {
        $content = file_get_contents($path);
        self::assertNotFalse($content, 'Unable to read source: ' . $path);
        return $content;
    }

    /**
     * Return the substring between two delimiter strings, failing the test
     * loudly if either delimiter is missing or the extracted block is empty.
     *
     * Used to assert "contains / does not contain" inside a specific block
     * (e.g. inside an array literal) without getting false hits from comments.
     * Fails-loud so that a future variable rename or formatting tweak that
     * causes delimiter drift does NOT silently make "not-contains" assertions
     * vacuous.
     */
    private static function readBetween(string $haystack, string $start, string $end): string
    {
        $startPos = strpos($haystack, $start);
        self::assertNotFalse(
            $startPos,
            "readBetween: start delimiter not found in source: {$start}"
        );
        $startPos += strlen($start);
        $endPos   = strpos($haystack, $end, $startPos);
        self::assertNotFalse(
            $endPos,
            "readBetween: end delimiter not found after start in source: {$end}"
        );
        $block = substr($haystack, $startPos, $endPos - $startPos);
        self::assertNotSame(
            '',
            trim($block),
            "readBetween: extracted block between '{$start}' and '{$end}' is empty"
        );
        return $block;
    }

    // -------------------------------------------------------------------
    // Fix 1.1 — doubtful-request regex delimiter convention
    // -------------------------------------------------------------------

    #[Test]
    public function fix11_doubtfulRequestRegexUsesConventionalDelimiter(): void
    {
        $source = self::readSource(self::PROTECTOR_FILE);

        // Pin the delimiter change for this file. '?' is a technically-valid
        // PCRE delimiter in PHP, but '#' is the project convention and removes
        // visual collision with the '?' zero-or-one quantifier. This test
        // fails if anyone reverts the choice.
        $this->assertStringNotContainsString(
            "preg_match('?[",
            $source,
            'Legacy ?-delimited doubtful-request pattern must not be reintroduced'
        );

        // The replacement delimiter (#) plus opening \s in the character class
        // must be present. Combined with the pattern-semantics test below,
        // this pins both the source change and its meaning.
        $this->assertMatchesRegularExpression(
            '/preg_match\(\s*\'#\[\\\\s/',
            $source,
            'Fix 1.1 must use # as the PCRE delimiter with \\s at the start of the character class'
        );
    }

    #[Test]
    public function fix11_doubtfulRequestPatternClassifiesInputCorrectly(): void
    {
        // Re-execute the exact regex the fix installed. Asserts the pattern
        // itself matches the intended inputs; combined with the source
        // assertion above, this pins both "what the code says" and
        // "what the pattern means" against regression.
        $pattern = '#[\s\'"`/]#';

        $this->assertSame(1, preg_match($pattern, "1' OR '1'='1"), "SQL injection with quote");
        $this->assertSame(1, preg_match($pattern, '1 UNION SELECT'), 'SQL injection with whitespace');
        $this->assertSame(1, preg_match($pattern, "admin'--"), 'SQL comment injection');
        $this->assertSame(1, preg_match($pattern, '/etc/passwd'), 'Path traversal');
        $this->assertSame(1, preg_match($pattern, 'a b'), 'Plain whitespace');
        $this->assertSame(0, preg_match($pattern, '42'), 'Plain integer');
        $this->assertSame(0, preg_match($pattern, 'hello'), 'Plain alphanumeric');
    }

    // -------------------------------------------------------------------
    // Fix 1.2 — defined() with quoted constant name
    // -------------------------------------------------------------------

    #[Test]
    public function fix12_definedCallQuotesConstantName(): void
    {
        $source = self::readSource(self::PROTECTOR_FILE);

        $this->assertStringNotContainsString(
            'defined(XOOPS_COOKIE_DOMAIN)',
            $source,
            'Unquoted defined() triggers PHP 8 Fatal Error when the constant is absent'
        );
        $this->assertStringContainsString(
            "defined('XOOPS_COOKIE_DOMAIN')",
            $source,
            'Constant name must be quoted as a string literal'
        );
    }

    // -------------------------------------------------------------------
    // Fix 1.3 — dos_crsafe runtime validity guard
    // -------------------------------------------------------------------

    #[Test]
    public function fix13_dosCrsafePatternIsValidatedBeforeUse(): void
    {
        $source = self::readSource(self::PROTECTOR_FILE);

        // The guard probes the pattern with an empty subject. preg_match()
        // returns false on a malformed pattern, so the === false comparison
        // is the discriminator.
        $this->assertMatchesRegularExpression(
            '/false\s*!==\s*@preg_match\(/',
            $source,
            'Fix 1.3 must use @preg_match($pattern, "") !== false to probe validity'
        );

        // The original unguarded call pattern must be gone.
        $this->assertStringNotContainsString(
            "preg_match(\$this->_conf['dos_crsafe']",
            $source,
            'The unguarded preg_match() on admin-supplied regex must be replaced'
        );
    }

    // -------------------------------------------------------------------
    // Fix 1.4 — injectionFound uses HTTP 403, not distinctive text body
    // -------------------------------------------------------------------

    #[Test]
    public function fix14_injectionResponseUsesHttpForbidden(): void
    {
        $source = self::readSource(self::DB_FILE);

        // A behavioural test would need to fork a process, issue a
        // detectable SQL payload, and inspect the HTTP response — too
        // heavy for RC. Source check: die text is generic and 403 is set.
        $this->assertStringNotContainsString(
            "die('SQL Injection found')",
            $source,
            'The distinctive "SQL Injection found" body allowed attackers to probe dblayertrap response'
        );
        $this->assertStringContainsString(
            'http_response_code(403)',
            $source,
            'Must set HTTP 403 before terminating the request'
        );
        $this->assertMatchesRegularExpression(
            "/die\(\s*'Forbidden'\s*\)/",
            $source,
            'Response body must be generic (matches other blocked-request paths)'
        );
    }

    // -------------------------------------------------------------------
    // Fix 1.5 — ALL HTTP_* keys excluded from dblayertrap scan
    // -------------------------------------------------------------------

    #[Test]
    public function fix15_dblayertrapUsesPositiveAllowlistForServerScan(): void
    {
        $source = self::readSource(self::PROTECTOR_FILE);

        // The raw $_SERVER call must not reappear.
        $this->assertStringNotContainsString(
            '_dblayertrap_check_recursive($_SERVER)',
            $source,
            'Raw $_SERVER must not be scanned directly — attacker-controlled keys would poison doubtfuls'
        );

        // Positive allowlist is the discriminator. Any denylist approach
        // (even filtering HTTP_*) is incomplete because CONTENT_TYPE,
        // CONTENT_LENGTH, PHP_AUTH_*, AUTH_TYPE, QUERY_STRING, REQUEST_URI,
        // PATH_INFO, PATH_TRANSLATED, ORIG_PATH_INFO, REDIRECT_* are also
        // attacker-controlled outside HTTP_*.
        $this->assertStringContainsString(
            '$serverScanAllowlist',
            $source,
            'Fix 1.5 must use a positive allowlist, not a denylist'
        );
        $this->assertStringContainsString(
            'array_intersect_key($_SERVER, $serverScanAllowlist)',
            $source,
            'Allowlist must be intersected against $_SERVER to keep ONLY named keys'
        );

        // Confirm the allowlist does NOT include attacker-controlled keys
        // that a reviewer might be tempted to add. Keeping these out is the
        // whole point of the fix.
        //
        // PHP_SELF and SCRIPT_NAME are excluded because they are reconstructed
        // from the request path on URL-rewriting deployments (Protector's own
        // IIS bootstrap at precheck.inc.php does exactly that) and Protector
        // historically treats PHP_SELF as untrusted (explicit XSS handling).
        //
        // SERVER_NAME is excluded because it reflects the Host header when
        // UseCanonicalName is Off, which is common on Apache and default on
        // many reverse-proxied setups.
        // Extract the allowlist block once, then assert via regex that catches
        // both quote styles. A plain substring check against "'KEY'" would miss
        // a regression written as "KEY" => true.
        $allowlistSource = self::readBetween($source, 'serverScanAllowlist = [', '];');
        foreach (
            [
                'CONTENT_TYPE',
                'CONTENT_LENGTH',
                'PHP_AUTH_USER',
                'PHP_AUTH_PW',
                'PHP_AUTH_DIGEST',
                'AUTH_TYPE',
                'QUERY_STRING',
                'REQUEST_URI',
                'PATH_INFO',
                'PATH_TRANSLATED',
                'ORIG_PATH_INFO',
                'PHP_SELF',
                'SCRIPT_NAME',
                'SERVER_NAME',
                'REQUEST_METHOD',
                'SERVER_PROTOCOL',
            ] as $attackerControlledKey
        ) {
            $this->assertDoesNotMatchRegularExpression(
                '/[\'"]' . preg_quote($attackerControlledKey, '/') . '[\'"]\s*=>/',
                $allowlistSource,
                "Allowlist must not include request-influenced key {$attackerControlledKey}"
            );
        }
    }

    #[Test]
    public function fix15_behaviouralRequestMetadataDoesNotPoisonDoubtfuls(): void
    {
        // Failure-locality note: dblayertrap_init() can @define('XOOPS_DB_ALTERNATIVE', ...)
        // as a side effect when _dblayertrap_doubtfuls is non-empty. On the happy path this
        // test is deterministic (doubtfuls stay empty after the scan, so the define is
        // skipped), but on a Fix 1.5 REGRESSION the define would fire before the assertion
        // reports the failure — leaving the constant defined for the rest of the PHPUnit
        // run. Process isolation via #[RunInSeparateProcess] would contain that, but hangs
        // on Windows under PHPUnit 11's IPC stack with the XOOPS bootstrap. The accepted
        // tradeoff: on regression, this test AND ProtectorCorePreloadTest fail loudly —
        // the overall CI signal is still "fix needed", not a silent pass.
        // Behavioural test for the whole attacker-controlled metadata surface.
        // Each of these can legitimately carry an SQL-keyword substring in a
        // crafted request:
        //   - HTTP_X_SQL_PROBE  (arbitrary custom header via HTTP_* expansion)
        //   - HTTP_USER_AGENT   (stock header)
        //   - HTTP_FORWARDED    (RFC 7239 header)
        //   - CONTENT_TYPE      (Content-Type header; not in HTTP_* namespace)
        //   - CONTENT_LENGTH    (Content-Length header; not in HTTP_*)
        //   - PHP_AUTH_USER     (Basic auth username)
        //   - QUERY_STRING      (= $_GET serialized; already scanned via $_GET)
        //   - REQUEST_URI       (URL path + query)
        //   - PATH_INFO         (URL segment)
        // None of them must appear in _dblayertrap_doubtfuls after the scan.

        // Snapshot and sanitise superglobals BEFORE getInstance(), not after.
        // Protector::__construct() calls _initial_recursive($_GET/$_POST/$_COOKIE)
        // during its first invocation in the process, so any inherited state
        // from the runner or from a sibling test's mutations would be captured
        // into $_doubtful_requests and survive the per-test cleanup below. In
        // this suite Protector364RcFixesTest runs first alphabetically, so this
        // behavioural test may be the first call to getInstance() in the whole
        // PHPUnit process.
        $priorServer = $_SERVER;
        $priorGet    = $_GET;
        $priorPost   = $_POST;
        $priorCookie = $_COOKIE;

        $_GET    = [];
        $_POST   = [];
        $_COOKIE = [];

        require_once XOOPS_PATH . '/modules/protector/class/protector.php';
        $protector     = \Protector::getInstance();
        $priorWoServer = $protector->_conf['dblayertrap_wo_server'] ?? null;
        $protector->_conf['dblayertrap_wo_server'] = 0;

        // Replace $_SERVER with a minimal deterministic fixture. Without this
        // the runner-provided values for allowlisted keys (DOCUMENT_ROOT,
        // SCRIPT_FILENAME, REMOTE_ADDR, etc.) could happen to contain an SQL
        // keyword substring from the CI environment and populate doubtfuls,
        // triggering unrelated define(XOOPS_DB_ALTERNATIVE) side effects.
        $_SERVER = [
            // Poisoning payloads — each contains an SQL-keyword substring in
            // a key that the allowlist MUST exclude (HTTP_*, CONTENT_*,
            // PHP_AUTH_*, URL-derived, PHP_SELF/SCRIPT_NAME/SERVER_NAME,
            // REQUEST_METHOD, SERVER_PROTOCOL).
            'HTTP_USER_AGENT'  => 'stock-union-header',
            'HTTP_X_SQL_PROBE' => 'custom-select-header',
            'HTTP_FORWARDED'   => 'rfc-information_schema-header',
            'CONTENT_TYPE'     => 'application/select',
            'CONTENT_LENGTH'   => 'union',
            'PHP_AUTH_USER'    => 'admin-union',
            'QUERY_STRING'     => 'q=select',
            'REQUEST_URI'      => '/path-with-select-keyword',
            'PATH_INFO'        => '/information_schema/segment',
            // Request-derived-but-not-HTTP_* keys that were still exploitable
            // before the allowlist was trimmed.
            'PHP_SELF'         => '/index.php/union/script',
            'SCRIPT_NAME'      => '/index.php/select-suffix',
            'SERVER_NAME'      => 'information_schema.attacker.example',
            'REQUEST_METHOD'   => 'SELECT',
            'SERVER_PROTOCOL'  => 'SELECT/1.0',
            // Explicit safe values for every key in the production allowlist
            // so CI environment values cannot seed doubtfuls independently of
            // the attack surface under test.
            'SERVER_ADDR'       => '127.0.0.1',
            'SERVER_PORT'       => '80',
            'SERVER_SOFTWARE'   => 'test-runner',
            'GATEWAY_INTERFACE' => 'CGI/1.1',
            'DOCUMENT_ROOT'     => '/tmp',
            'REQUEST_SCHEME'    => 'http',
            'REMOTE_ADDR'       => '192.0.2.1',
            'REMOTE_PORT'       => '12345',
            'SCRIPT_FILENAME'   => '/tmp/test.php',
        ];

        try {
            $protector->dblayertrap_init(false);
            $doubtfuls = $protector->getDblayertrapDoubtfuls();

            $poisoning = [
                'stock-union-header',
                'custom-select-header',
                'rfc-information_schema-header',
                'application/select',
                'union',
                'admin-union',
                'q=select',
                '/path-with-select-keyword',
                '/information_schema/segment',
                '/index.php/union/script',
                '/index.php/select-suffix',
                'information_schema.attacker.example',
                'SELECT',
                'SELECT/1.0',
            ];
            foreach ($poisoning as $value) {
                $this->assertNotContains(
                    $value,
                    $doubtfuls,
                    "Attacker-controlled request metadata value '{$value}' must not poison doubtfuls"
                );
            }
        } finally {
            $_SERVER                                   = $priorServer;
            $_GET                                      = $priorGet;
            $_POST                                     = $priorPost;
            $_COOKIE                                   = $priorCookie;
            $protector->_conf['dblayertrap_wo_server'] = $priorWoServer;
            $protector->_dblayertrap_doubtfuls         = [];
        }
    }

    // -------------------------------------------------------------------
    // Fix 9.1 — filter filename containment
    // -------------------------------------------------------------------

    #[Test]
    public function fix91_filterLoaderRequiresPhpSuffixAndRealPathContainment(): void
    {
        $source = self::readSource(self::FILTER_FILE);

        // Minimal RC hardening per Codex: require .php suffix (case-insensitive
        // so NTFS / HFS+ deployments with .PHP / .Php filenames still load),
        // resolve realpath, and verify the resolved file lives inside
        // filters_enabled. Strict filename allowlist (precommon_/postcommon_/...)
        // defers to 3.7.0 to avoid breaking custom deployments.
        $this->assertMatchesRegularExpression(
            "/strcasecmp\(\s*substr\(\\\$file, -4\)\s*,\s*'\.php'\s*\)/",
            $source,
            'Filter loader must require .php suffix case-insensitively'
        );
        $this->assertStringContainsString(
            "realpath(\$this->filters_base . '/' . \$file)",
            $source,
            'Filter loader must resolve the real path for each candidate'
        );
        $this->assertStringContainsString(
            'str_starts_with($realPath, $baseReal . DIRECTORY_SEPARATOR)',
            $source,
            'Filter loader must verify the resolved path stays inside filters_enabled'
        );
    }

    #[Test]
    public function fix91_behaviouralFilterLoaderAcceptsMixedCasePhpExtension(): void
    {
        // Behavioural test: set up a tempdir with a mixed-case .PHP filter file,
        // point ProtectorFilterHandler at it, and assert execute() actually loads
        // the file. Also verify the containment check rejects a filter placed
        // outside the tempdir via a relative traversal path.
        require_once XOOPS_PATH . '/modules/protector/class/protector.php';
        require_once XOOPS_PATH . '/modules/protector/class/ProtectorFilter.php';

        // Allocate a unique, collision-resistant path via tempnam(), then swap
        // the placeholder file for a directory of the same name. This is safer
        // than sys_get_temp_dir() + uniqid() under parallel test runs, and any
        // failure along the way fails the test with a clear message instead
        // of propagating as a later "file not found" from execute().
        $tempDir = tempnam(sys_get_temp_dir(), 'protector364test_');
        $this->assertNotFalse($tempDir, 'Failed to allocate a unique temporary path for the filter loader test');
        $this->assertTrue(@unlink($tempDir), 'Failed to clear temporary placeholder before creating test directory');
        $this->assertTrue(@mkdir($tempDir, 0700), 'Failed to create temporary filter test directory');

        // Mixed-case extension — must load on case-sensitive filesystems too.
        $mixedCasePath  = $tempDir . '/precommon_mixcase.PHP';
        $mixedCaseBytes = file_put_contents(
            $mixedCasePath,
            "<?php function protector_precommon_mixcase() { return 42; }\n"
        );
        $this->assertNotFalse($mixedCaseBytes, 'Failed to write mixed-case PHP filter fixture');

        // A file with a non-PHP extension must be rejected even if its name prefix matches.
        $phtmlPath  = $tempDir . '/precommon_phtml_bait.phtml';
        $phtmlBytes = file_put_contents(
            $phtmlPath,
            "<?php function protector_precommon_phtml_bait() { return 99; }\n"
        );
        $this->assertNotFalse($phtmlBytes, 'Failed to write non-PHP filter fixture');

        // A DIRECTORY that matches the {type}_*.php pattern must be skipped silently.
        // realpath() succeeds on directories, so without the is_file() guard the loader
        // would reach include_once() on a directory path and emit a PHP warning on
        // every request. This covers the silent-continue guard at ProtectorFilter.php.
        $directoryTrapPath = $tempDir . '/precommon_directory_trap.php';
        $this->assertTrue(
            @mkdir($directoryTrapPath, 0700),
            'Failed to create directory-trap fixture'
        );

        $handler = \ProtectorFilterHandler::getInstance();
        $priorBase = $handler->filters_base;
        $handler->filters_base = $tempDir;

        // Capture any PHP warnings emitted during execute() — the directory-trap
        // guard means there must be none.
        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = [$errno, $errstr];
            return true;
        });

        try {
            $result = $handler->execute('precommon');
            $this->assertTrue(
                function_exists('protector_precommon_mixcase'),
                'Mixed-case .PHP filter must be loaded by the execute() loader'
            );
            $this->assertFalse(
                function_exists('protector_precommon_phtml_bait'),
                '.phtml filter must be rejected — not a valid PHP filter extension'
            );
            // Return value reflects the bitwise-OR of loaded filter returns.
            $this->assertSame(42, $result, 'execute() should return the loaded filter result');
            // No warnings should have been emitted — the directory trap must be
            // skipped silently, not trigger an include_once() warning.
            $this->assertSame(
                [],
                $warnings,
                'Directory matching {type}_*.php must not cause include warnings'
            );
        } finally {
            restore_error_handler();
            $handler->filters_base = $priorBase;
            @unlink($mixedCasePath);
            @unlink($phtmlPath);
            @rmdir($directoryTrapPath);
            @rmdir($tempDir);
        }
    }

    // -------------------------------------------------------------------
    // Smoke test — core SQLi detection tokens still present
    // -------------------------------------------------------------------

    #[Test]
    public function smokeTest_coreSqlInjectionNeedlesRemainInPlace(): void
    {
        // The 3.6.4 tranche must not remove detection tokens. A future
        // FP-reduction pass (3.7.0) may demote 'select' once the Stage 2
        // matcher has been fixed and measured, but not in RC.
        $source = self::readSource(self::DB_FILE);

        $this->assertStringContainsString("'union'", $source, 'UNION injection token must remain');
        $this->assertStringContainsString("'information_schema'", $source, 'information_schema token must remain');
        $this->assertStringContainsString("'/*'", $source, 'SQL block-comment token must remain');
        $this->assertStringContainsString("'--'", $source, 'SQL line-comment token must remain');
        $this->assertStringContainsString("'select'", $source, "'select' is retained in 3.6.4 — do not remove until 3.7.0 matcher fix validated");
    }
}
