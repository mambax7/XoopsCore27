<?php

declare(strict_types=1);

namespace modulessystem;

use kernel\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(\SystemMaintenance::class)]
class SystemMaintenanceTest extends KernelTestCase
{
    private static bool $loaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$loaded) {
            if (!isset($GLOBALS['xoopsLogger'])) {
                $GLOBALS['xoopsLogger'] = \XoopsLogger::getInstance();
            }
            self::ensureMaintenanceConstants();
            require_once XOOPS_ROOT_PATH . '/modules/system/class/maintenance.php';
            self::$loaded = true;
        }
    }

    /**
     * Define language constants required by the SystemMaintenance class.
     */
    private static function ensureMaintenanceConstants(): void
    {
        $constants = [
            '_AM_SYSTEM_MAINTENANCE_TABLES1'            => 'Table',
            '_AM_SYSTEM_MAINTENANCE_TABLES_OPTIMIZE'    => 'Optimize',
            '_AM_SYSTEM_MAINTENANCE_TABLES_CHECK'       => 'Check',
            '_AM_SYSTEM_MAINTENANCE_TABLES_REPAIR'      => 'Repair',
            '_AM_SYSTEM_MAINTENANCE_TABLES_ANALYZE'     => 'Analyze',
            '_AM_SYSTEM_MAINTENANCE_DUMP_TABLES'        => 'Tables',
            '_AM_SYSTEM_MAINTENANCE_DUMP_STRUCTURES'    => 'Structures',
            '_AM_SYSTEM_MAINTENANCE_DUMP_NB_RECORDS'    => 'Records',
            '_AM_SYSTEM_MAINTENANCE_DUMP_RECORDS'       => 'records',
            '_AM_SYSTEM_MAINTENANCE_DUMP_FILE_CREATED'  => 'File created',
            '_AM_SYSTEM_MAINTENANCE_DUMP_RESULT'        => 'Result',
            '_AM_SYSTEM_MAINTENANCE_DUMP_NO_TABLES'     => 'No tables',
        ];
        foreach ($constants as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    /**
     * Create a SystemMaintenance instance with a mock database injected.
     *
     * @param \XoopsMySQLDatabase|\PHPUnit\Framework\MockObject\MockObject $db
     *
     * @return \SystemMaintenance
     */
    private function createMaintenance($db): \SystemMaintenance
    {
        $ref = new \ReflectionClass(\SystemMaintenance::class);
        $obj = $ref->newInstanceWithoutConstructor();
        $this->setProtectedProperty($obj, 'db', $db);
        $this->setProtectedProperty($obj, 'prefix', 'xoops_');

        return $obj;
    }

    /**
     * Stub SHOW TABLES to return a known table list for validation.
     *
     * @param \XoopsMySQLDatabase|\PHPUnit\Framework\MockObject\MockObject $db
     * @param array $tables  Unprefixed table names
     */
    private function stubShowTables($db, array $tables): void
    {
        $rows = [];
        foreach ($tables as $t) {
            $rows[] = ['Tables_in_test' => XOOPS_DB_PREFIX . '_' . $t];
        }
        $rows[] = false; // End of result set

        $db->method('query')->willReturn('mock_result');
        $db->method('isResultSet')->willReturn(true);
        $db->method('fetchArray')->willReturnOnConsecutiveCalls(...$rows);
    }

    // ---------------------------------------------------------------
    // isValidTable / isValidPrefixedTable tests (via reflection)
    // ---------------------------------------------------------------

    #[Test]
    public function isValidTableReturnsTrueForExistingTable(): void
    {
        $db = $this->createMockDatabase();
        $this->stubShowTables($db, ['users', 'groups', 'session']);
        $maintenance = $this->createMaintenance($db);

        $method = new \ReflectionMethod($maintenance, 'isValidTable');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($maintenance, 'users'));
        $this->assertTrue($method->invoke($maintenance, 'groups'));
        $this->assertTrue($method->invoke($maintenance, 'session'));
    }

    #[Test]
    public function isValidTableReturnsFalseForNonExistentTable(): void
    {
        $db = $this->createMockDatabase();
        $this->stubShowTables($db, ['users', 'groups']);
        $maintenance = $this->createMaintenance($db);

        $method = new \ReflectionMethod($maintenance, 'isValidTable');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($maintenance, 'evil_table'));
    }

    #[Test]
    public function isValidTableReturnsFalseForSqlInjectionPayload(): void
    {
        $db = $this->createMockDatabase();
        $this->stubShowTables($db, ['users', 'groups']);
        $maintenance = $this->createMaintenance($db);

        $method = new \ReflectionMethod($maintenance, 'isValidTable');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($maintenance, "users; DROP TABLE users; --"));
        $this->assertFalse($method->invoke($maintenance, "users' OR '1'='1"));
    }

    #[Test]
    public function isValidPrefixedTableReturnsTrueForValidPrefixedTable(): void
    {
        $db = $this->createMockDatabase();
        $this->stubShowTables($db, ['users', 'groups']);
        $maintenance = $this->createMaintenance($db);

        $method = new \ReflectionMethod($maintenance, 'isValidPrefixedTable');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($maintenance, 'xoops_users'));
    }

    #[Test]
    public function isValidPrefixedTableReturnsFalseForInvalidPrefixedTable(): void
    {
        $db = $this->createMockDatabase();
        $this->stubShowTables($db, ['users', 'groups']);
        $maintenance = $this->createMaintenance($db);

        $method = new \ReflectionMethod($maintenance, 'isValidPrefixedTable');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($maintenance, 'xoops_evil_table'));
    }

    // ---------------------------------------------------------------
    // dump_table_structure validation tests
    // ---------------------------------------------------------------

    #[Test]
    public function dumpTableStructureRejectsInvalidTable(): void
    {
        $db = $this->createMockDatabase();
        $this->stubShowTables($db, ['users', 'groups']);
        $maintenance = $this->createMaintenance($db);

        $ret = ['', ''];
        $result = $maintenance->dump_table_structure($ret, 'xoops_evil_inject', 0, 'odd');

        // Should return unchanged $ret since the table is invalid
        $this->assertSame($ret, $result);
    }

    // ---------------------------------------------------------------
    // dump_table_datas validation tests
    // ---------------------------------------------------------------

    #[Test]
    public function dumpTableDatasRejectsInvalidTable(): void
    {
        $db = $this->createMockDatabase();
        $this->stubShowTables($db, ['users', 'groups']);
        $maintenance = $this->createMaintenance($db);

        $ret = ['', ''];
        $result = $maintenance->dump_table_datas($ret, 'xoops_evil_inject');

        // Should return unchanged $ret since the table is invalid
        $this->assertSame($ret, $result);
    }

    // ---------------------------------------------------------------
    // displayTables tests
    // ---------------------------------------------------------------

    #[Test]
    public function displayTablesReturnsArrayOfTableNames(): void
    {
        $db = $this->createMockDatabase();
        $this->stubShowTables($db, ['users', 'groups']);
        $maintenance = $this->createMaintenance($db);

        $tables = $maintenance->displayTables(true);

        $this->assertIsArray($tables);
        $this->assertArrayHasKey('users', $tables);
        $this->assertArrayHasKey('groups', $tables);
        $this->assertSame('users', $tables['users']);
    }

    #[Test]
    public function displayTablesReturnsStringWhenArrayIsFalse(): void
    {
        $db = $this->createMockDatabase();
        $this->stubShowTables($db, ['users', 'groups']);
        $maintenance = $this->createMaintenance($db);

        $result = $maintenance->displayTables(false);

        $this->assertIsString($result);
        $this->assertStringContainsString('users', $result);
        $this->assertStringContainsString('groups', $result);
    }

    // ---------------------------------------------------------------
    // Table validation caching test
    // ---------------------------------------------------------------

    #[Test]
    public function isValidTableCachesResults(): void
    {
        $db = $this->createMockDatabase();

        // Build the rows that SHOW TABLES would return
        $rows = [
            ['Tables_in_test' => XOOPS_DB_PREFIX . '_users'],
            false, // End of result set
        ];

        // query() must be called exactly once — subsequent isValidTable()
        // calls must use the cached result, not issue another query.
        $db->expects($this->once())->method('query')->willReturn('mock_result');
        $db->method('isResultSet')->willReturn(true);
        $db->method('fetchArray')->willReturnOnConsecutiveCalls(...$rows);

        $maintenance = $this->createMaintenance($db);

        $method = new \ReflectionMethod($maintenance, 'isValidTable');
        $method->setAccessible(true);

        // First call populates cache
        $this->assertTrue($method->invoke($maintenance, 'users'));
        // Second call uses cache (query() should not be called again)
        $this->assertTrue($method->invoke($maintenance, 'users'));
        // Invalid table still false from cache
        $this->assertFalse($method->invoke($maintenance, 'nonexistent'));
    }

    // ---------------------------------------------------------------
    // CleanAvatar() — orphan avatar file/row cleanup.
    //
    // The method scans the avatar table for custom-avatar rows whose
    // owning user has been deleted, removes the on-disk file (when it
    // is safely contained under XOOPS_UPLOAD_PATH), and deletes both
    // the avatar row and any leftover avatar_user_link rows.
    //
    // These tests cover the path-traversal-safe resolution introduced
    // when @unlink() was replaced by xoops_remove_file_quietly():
    //   - happy path: avatars/<file> under XOOPS_UPLOAD_PATH is removed
    //   - traversal / absolute / outside-root inputs are silently skipped
    //   - the avatar DB row is deleted regardless of file-removal outcome
    //   - empty avatar_file deletes the DB row and continues
    //
    // Filesystem fixtures live in a unique subdirectory under
    // XOOPS_UPLOAD_PATH/avatars/ so the tests do not collide with each
    // other or with anything else in the upload tree.
    // ---------------------------------------------------------------

    /**
     * Returns the per-test scratch subdirectory under XOOPS_UPLOAD_PATH/avatars/.
     * The path is RELATIVE to the upload root, formatted with forward slashes
     * so it can be embedded in avatar_file values verbatim.
     */
    private function avatarScratchRel(): string
    {
        return 'avatars/_test_' . getmypid() . '_' . uniqid();
    }

    /**
     * Build the absolute filesystem path that corresponds to a relative
     * avatar_file value (e.g. 'avatars/foo.png').
     */
    private function uploadAbs(string $rel): string
    {
        return XOOPS_UPLOAD_PATH . '/' . $rel;
    }

    /**
     * Create the scratch directory and place a fixture avatar file inside.
     * Returns [$relPath, $absPath] where $relPath is what would be stored
     * in the avatar_file column and $absPath is the on-disk location.
     */
    private function placeFixtureAvatar(string $scratchRel, string $filename): array
    {
        $scratchAbs = $this->uploadAbs($scratchRel);
        if (!is_dir($scratchAbs) && !mkdir($scratchAbs, 0755, true) && !is_dir($scratchAbs)) {
            $this->fail('Could not create test scratch dir: ' . $scratchAbs);
        }
        $absPath = $scratchAbs . '/' . $filename;
        // Assert the fixture write actually succeeded with the expected
        // byte count — otherwise a happy-path test could later "pass"
        // because CleanAvatar() found no file to delete
        // (assertFileDoesNotExist would succeed even though the cleanup
        // never ran on a real fixture). Checking the byte count also
        // catches partial-write failures, not just "false return".
        $bytesWritten = file_put_contents($absPath, 'fixture');
        $this->assertNotFalse($bytesWritten, 'Could not write fixture avatar: ' . $absPath);
        $this->assertSame(strlen('fixture'), $bytesWritten);
        $this->assertFileExists($absPath);
        $relPath = $scratchRel . '/' . $filename;

        return [$relPath, $absPath];
    }

    /**
     * Remove the per-test scratch directory and the files directly under
     * it. Fixtures are flat (single-level), so a single scandir + rmdir
     * pass is sufficient — no recursive descent.
     */
    private function removeScratchDir(string $scratchRel): void
    {
        $scratchAbs = $this->uploadAbs($scratchRel);
        if (!is_dir($scratchAbs)) {
            return;
        }
        foreach (scandir($scratchAbs) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $scratchAbs . '/' . $entry;
            if (is_file($path)) {
                $this->assertTrue(
                    unlink($path),
                    'Could not clean up scratch-dir entry: ' . $path
                );
            }
        }
        $this->assertTrue(
            rmdir($scratchAbs),
            'Could not remove scratch dir: ' . $scratchAbs
        );
    }

    /**
     * Stub the database mock so $db->query() returns a sentinel result and
     * $db->fetchArray() yields the supplied avatar rows once, then false.
     * The caller still holds the mock and can attach further expectations
     * directly (this helper does not return it).
     */
    private function stubAvatarSweep($db, array $rows): void
    {
        $db->method('query')->willReturn('mock_result');
        $db->method('isResultSet')->willReturn(true);
        $rows[] = false; // end-of-result sentinel
        $db->method('fetchArray')->willReturnOnConsecutiveCalls(...$rows);
    }

    #[Test]
    public function testCleanAvatarRemovesValidAvatarFileUnderUploadRoot(): void
    {
        $scratchRel       = $this->avatarScratchRel();
        [$rel, $abs]      = $this->placeFixtureAvatar($scratchRel, 'foo.png');

        $db = $this->createMockDatabase();
        $this->stubAvatarSweep($db, [
            ['avatar_id' => 42, 'avatar_file' => $rel],
        ]);
        // exec() called twice: once for DELETE FROM avatar, once for the
        // avatar_user_link cleanup at the end of CleanAvatar().
        $db->expects($this->exactly(2))->method('exec')->willReturn(true);

        $maintenance = $this->createMaintenance($db);
        try {
            $this->assertTrue($maintenance->CleanAvatar(), 'expected CleanAvatar() to report a clean sweep');
            $this->assertFileDoesNotExist($abs, 'fixture avatar should have been removed');
        } finally {
            $this->removeScratchDir($scratchRel);
        }
    }

    #[Test]
    public function testCleanAvatarSkipsTraversalPathButStillDeletesDbRow(): void
    {
        // To meaningfully exercise the upload-root containment check the
        // fixture has to live at the location the traversal string
        // ACTUALLY resolves to. dirname(realpath(XOOPS_UPLOAD_PATH)) is
        // the parent of the upload root, so a fixture placed there is
        // reachable via '../<basename>' relative to XOOPS_UPLOAD_PATH.
        // Result:
        //   - realpath() succeeds (the file exists at the parent dir)
        //   - the prefix check rejects it (not under uploads/)
        //   - the fixture survives — exercising the containment branch
        // Without this setup, realpath() would just return false on a
        // non-existent target and the test would pass for the wrong
        // reason.
        $uploadRoot = realpath(XOOPS_UPLOAD_PATH);
        $this->assertIsString($uploadRoot, 'XOOPS_UPLOAD_PATH must resolve via realpath()');
        $outside = dirname($uploadRoot) . DIRECTORY_SEPARATOR . 'xoops_avatar_traversal_target_' . uniqid() . '.png';
        $bytesWritten = file_put_contents($outside, 'must-not-be-removed');
        $this->assertNotFalse($bytesWritten, 'Could not write traversal fixture: ' . $outside);
        $this->assertSame(strlen('must-not-be-removed'), $bytesWritten);
        $traversalRel = '../' . basename($outside);

        $db = $this->createMockDatabase();
        $this->stubAvatarSweep($db, [
            ['avatar_id' => 99, 'avatar_file' => $traversalRel],
        ]);
        // DB row + avatar_user_link cleanup still execute.
        $db->expects($this->exactly(2))->method('exec')->willReturn(true);

        $maintenance = $this->createMaintenance($db);
        try {
            // Sanity: realpath of the traversal path resolves to the
            // fixture (i.e. realpath() succeeds) — proving this test
            // really exercises the prefix-check branch and not the
            // realpath()-returns-false short-circuit.
            $resolved = realpath(XOOPS_UPLOAD_PATH . '/' . $traversalRel);
            $this->assertSame(realpath($outside), $resolved, 'traversal must actually resolve to the fixture');

            $this->assertTrue($maintenance->CleanAvatar(), 'expected CleanAvatar() to report a clean sweep');
            $this->assertFileExists($outside, 'traversal target outside upload root must not be removed');
        } finally {
            if (file_exists($outside)) {
                $this->assertTrue(unlink($outside), 'Could not clean up fixture: ' . $outside);
            }
        }
    }

    #[Test]
    public function testCleanAvatarSkipsAbsolutePathButStillDeletesDbRow(): void
    {
        // An absolute path stored in avatar_file should not allow the
        // cleanup to escape XOOPS_UPLOAD_PATH. Use a temp fixture rather
        // than hard-coding /etc/hosts so the test runs on every OS
        // (Windows CI doesn't have /etc/hosts at the same location).
        // The ltrim('/') step turns '/abs/path/foo.png' into
        // 'abs/path/foo.png' which is then resolved relative to the
        // upload root — typically to a non-existent path, so realpath()
        // returns false and the cleanup is skipped.
        $outside = tempnam(sys_get_temp_dir(), 'xoops_avatar_absolute_');
        $this->assertNotFalse($outside, 'tempnam should succeed');
        $bytesWritten = file_put_contents($outside, 'must-not-be-removed');
        $this->assertNotFalse($bytesWritten, 'Could not write absolute-path fixture: ' . $outside);
        $this->assertSame(strlen('must-not-be-removed'), $bytesWritten);

        $db = $this->createMockDatabase();
        $this->stubAvatarSweep($db, [
            ['avatar_id' => 100, 'avatar_file' => $outside],
        ]);
        $db->expects($this->exactly(2))->method('exec')->willReturn(true);

        $maintenance = $this->createMaintenance($db);
        try {
            $this->assertTrue($maintenance->CleanAvatar(), 'expected CleanAvatar() to report a clean sweep');
            $this->assertFileExists($outside, 'absolute-path fixture must not be removed by avatar cleanup');
        } finally {
            if (file_exists($outside)) {
                $this->assertTrue(unlink($outside), 'Could not clean up fixture: ' . $outside);
            }
        }
    }

    #[Test]
    public function testCleanAvatarHandlesMissingFileAndStillDeletesDbRow(): void
    {
        $db = $this->createMockDatabase();
        $this->stubAvatarSweep($db, [
            // File reference that does not exist on disk — realpath() will
            // return false. The DB row cleanup must still run.
            ['avatar_id' => 200, 'avatar_file' => 'avatars/nonexistent_' . uniqid() . '.png'],
        ]);
        $db->expects($this->exactly(2))->method('exec')->willReturn(true);

        $maintenance = $this->createMaintenance($db);
        $this->assertTrue($maintenance->CleanAvatar(), 'expected CleanAvatar() to report a clean sweep');
    }

    #[Test]
    public function testCleanAvatarHandlesEmptyAvatarFileAndStillDeletesDbRow(): void
    {
        $db = $this->createMockDatabase();
        $this->stubAvatarSweep($db, [
            ['avatar_id' => 300, 'avatar_file' => ''],
        ]);
        $db->expects($this->exactly(2))->method('exec')->willReturn(true);

        $maintenance = $this->createMaintenance($db);
        $this->assertTrue($maintenance->CleanAvatar(), 'expected CleanAvatar() to report a clean sweep');
    }

    #[Test]
    public function testCleanAvatarHandlesNullByteAvatarFileAndStillDeletesDbRow(): void
    {
        // On PHP 8+ dirname()/realpath()/is_file()/is_link() raise
        // ValueError when the argument contains "\0". CleanAvatar() must
        // skip the filesystem work for such a row but still issue both
        // DELETEs — the per-row avatar DELETE and the trailing
        // avatar_user_link cleanup — so the malformed row doesn't block
        // the rest of the sweep.
        $db = $this->createMockDatabase();
        $this->stubAvatarSweep($db, [
            ['avatar_id' => 400, 'avatar_file' => "avatars/evil\0.png"],
        ]);
        $db->expects($this->exactly(2))->method('exec')->willReturn(true);

        $maintenance = $this->createMaintenance($db);
        $this->assertTrue($maintenance->CleanAvatar(), 'expected CleanAvatar() to report a clean sweep');
    }

    #[Test]
    public function testCleanAvatarSkipsNonAvatarsSubdirUnderUploadRoot(): void
    {
        // Defence-in-depth: even an avatar_file value that points to a
        // legitimate path UNDER XOOPS_UPLOAD_PATH but OUTSIDE the
        // avatars/ subtree must not be removed by the avatar sweep.
        // Place a fixture at uploads/files/<unique>.doc, set avatar_file
        // to that path, verify the file survives.
        $filesDir = XOOPS_UPLOAD_PATH . '/files';
        $created  = false;
        if (!is_dir($filesDir)) {
            if (!mkdir($filesDir, 0755, true) && !is_dir($filesDir)) {
                $this->fail('Could not create test files dir: ' . $filesDir);
            }
            $created = true;
        }
        $fixtureName = '_test_nonavatar_' . getmypid() . '_' . uniqid() . '.doc';
        $fixturePath = $filesDir . '/' . $fixtureName;
        $bytesWritten = file_put_contents($fixturePath, 'must-not-be-removed');
        $this->assertNotFalse($bytesWritten, 'Could not write non-avatar fixture: ' . $fixturePath);
        $this->assertSame(strlen('must-not-be-removed'), $bytesWritten);
        $rel = 'files/' . $fixtureName;

        $db = $this->createMockDatabase();
        $this->stubAvatarSweep($db, [
            ['avatar_id' => 500, 'avatar_file' => $rel],
        ]);
        $db->expects($this->exactly(2))->method('exec')->willReturn(true);

        $maintenance = $this->createMaintenance($db);
        try {
            // Sanity: the resolved path IS under uploads/ but NOT under
            // uploads/avatars/, so the broad upload-root check would
            // have allowed deletion. Asserting the resolution succeeds
            // proves the test really exercises the narrow prefix branch.
            $resolved = realpath($fixturePath);
            $this->assertIsString($resolved, 'fixture should resolve');
            $this->assertStringStartsWith(
                rtrim((string) realpath(XOOPS_UPLOAD_PATH), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
                $resolved,
                'fixture must be inside uploads/'
            );

            $this->assertTrue($maintenance->CleanAvatar(), 'expected CleanAvatar() to report a clean sweep');
            $this->assertFileExists($fixturePath, 'non-avatar file under uploads/ must not be removed');
        } finally {
            if (file_exists($fixturePath)) {
                $this->assertTrue(unlink($fixturePath), 'Could not clean up fixture: ' . $fixturePath);
            }
            if ($created && is_dir($filesDir)) {
                $this->assertTrue(rmdir($filesDir), 'Could not clean up files dir: ' . $filesDir);
            }
        }
    }

    #[Test]
    public function testCleanAvatarNormalisesBackslashesInAvatarFile(): void
    {
        // Windows-historic data may store 'avatars\foo.png'. The cleanup
        // should normalise it and remove the file under XOOPS_UPLOAD_PATH.
        $scratchRel       = $this->avatarScratchRel();
        [$rel, $abs]      = $this->placeFixtureAvatar($scratchRel, 'win.png');
        // Replace forward slashes with backslashes only in the segment
        // separator, NOT inside the scratch directory name (it has '_'
        // not '\\'). This is what a Windows-saved row looked like.
        $winRel = str_replace('/', '\\', $rel);

        $db = $this->createMockDatabase();
        $this->stubAvatarSweep($db, [
            ['avatar_id' => 400, 'avatar_file' => $winRel],
        ]);
        $db->expects($this->exactly(2))->method('exec')->willReturn(true);

        $maintenance = $this->createMaintenance($db);
        try {
            $this->assertTrue($maintenance->CleanAvatar(), 'expected CleanAvatar() to report a clean sweep');
            $this->assertFileDoesNotExist($abs, 'backslash-normalised avatar should be removed');
        } finally {
            $this->removeScratchDir($scratchRel);
        }
    }

    #[Test]
    public function testCleanAvatarReturnsFalseWhenAvatarDeleteFails(): void
    {
        // Locks in the boolean contract introduced for CleanAvatar(): a
        // failing DELETE must surface as a `false` return so callers can
        // distinguish a clean sweep from a partial one. The first exec()
        // (per-row avatar DELETE) returns false; the second (trailing
        // avatar_user_link cleanup) still returns true. Either failing
        // alone is enough to flip the return value.
        $db = $this->createMockDatabase();
        $this->stubAvatarSweep($db, [
            ['avatar_id' => 500, 'avatar_file' => ''],
        ]);
        $db->expects($this->exactly(2))
            ->method('exec')
            ->willReturnOnConsecutiveCalls(false, true);

        $maintenance = $this->createMaintenance($db);
        $this->assertFalse(
            $maintenance->CleanAvatar(),
            'CleanAvatar() must return false when any DELETE in the sweep fails'
        );
    }
}
