<?php
/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

declare(strict_types=1);

namespace modulessystem;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verifies system menu install wiring and schema expectations.
 *
 * @category  Xoops
 * @package   System
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversNothing]
final class SystemMenuInstallationTest extends TestCase
{
    private static bool $seedLoaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$seedLoaded) {
            require_once XOOPS_ROOT_PATH . '/modules/system/include/menu_seed.php';
            require_once XOOPS_ROOT_PATH . '/install/include/makedata.php';
            self::$seedLoaded = true;
        }
    }

    private function readSourceFile(string $relativePath): string
    {
        $fullPath = XOOPS_ROOT_PATH . '/' . $relativePath;
        $this->assertFileExists($fullPath, "Source file not found: {$relativePath}");

        $contents = file_get_contents($fullPath);
        $this->assertNotFalse($contents, "Unable to read source file: {$relativePath}");

        return $contents;
    }

    #[Test]
    public function sharedSeedDefinitionsExposeExpectedProtectedMenus(): void
    {
        $seed = system_menu_get_seed_definitions();

        $this->assertArrayHasKey('categories', $seed);
        $this->assertArrayHasKey('items', $seed);
        $this->assertArrayHasKey('home', $seed['categories']);
        $this->assertArrayHasKey('account', $seed['categories']);
        $this->assertArrayHasKey('admin', $seed['categories']);
        $this->assertNotEmpty($seed['items']);
        $this->assertSame('MENUS_HOME', $seed['categories']['home']['title']);
        $this->assertSame('MENUS_ACCOUNT', $seed['categories']['account']['title']);
        $this->assertSame('MENUS_ADMIN', $seed['categories']['admin']['title']);
        $this->assertCount(7, $seed['items']);
        $titles = array_column($seed['items'], 'title');
        $this->assertContains('MENUS_ACCOUNT_EDIT', $titles);
        $this->assertContains('MENUS_ACCOUNT_LOGIN', $titles);
        $this->assertContains('MENUS_ACCOUNT_REGISTER', $titles);
        $this->assertContains('MENUS_ACCOUNT_MESSAGES', $titles);
        $this->assertContains('MENUS_ACCOUNT_NOTIFICATIONS', $titles);
        $this->assertContains('MENUS_ACCOUNT_TOOLBAR', $titles);
        $this->assertContains('MENUS_ACCOUNT_LOGOUT', $titles);
        $this->assertContains('anonymous', $seed['categories']['account']['group_keys']);
        $this->assertContains('admin', $seed['categories']['admin']['group_keys']);
    }

    #[Test]
    public function sharedGroupKeyMapperResolvesKnownGroupsOnly(): void
    {
        $groupIds = system_menu_map_group_keys(
            ['admin', 'users', 'anonymous', 'missing'],
            [
                'admin' => 1,
                'users' => 2,
                'anonymous' => 3,
            ]
        );

        $this->assertSame([1, 2, 3], $groupIds);
    }

    #[Test]
    public function installerSchemaDeclaresMenuTablesUsingUnsignedIds(): void
    {
        $source = $this->readSourceFile('install/sql/mysql.structure.sql');
        preg_match('/CREATE TABLE menusitems \(.*?\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;/s', $source, $menusItemsBlock);

        $this->assertMatchesRegularExpression(
            '/CREATE TABLE menuscategory \\(.*?category_id int unsigned NOT NULL auto_increment/s',
            $source
        );
        $this->assertMatchesRegularExpression(
            '/CREATE TABLE menusitems \\(.*?items_id int unsigned NOT NULL auto_increment.*?items_cid int unsigned NOT NULL default \\\'0\\\'/s',
            $source
        );
        $this->assertNotEmpty($menusItemsBlock, 'menusitems DDL block not found');
        $this->assertStringNotContainsString('FOREIGN KEY', $menusItemsBlock[0]);
    }

    #[Test]
    public function updateScriptCreateTablesMatchInstallerSignedness(): void
    {
        $source = $this->readSourceFile('modules/system/include/update.php');

        $this->assertMatchesRegularExpression(
            '/`category_id`\\s+INT UNSIGNED\\s+NOT NULL\\s+AUTO_INCREMENT/i',
            $source
        );
        $this->assertMatchesRegularExpression(
            '/`items_id`\\s+INT UNSIGNED\\s+NOT NULL\\s+AUTO_INCREMENT/i',
            $source
        );
        $this->assertMatchesRegularExpression(
            '/`items_cid`\\s+INT UNSIGNED\\s+NOT NULL\\s+DEFAULT 0/i',
            $source
        );
    }

    #[Test]
    public function updateScriptNormalizesExistingMenuTableTypesForUpgrades(): void
    {
        $source = $this->readSourceFile('modules/system/include/update.php');

        $this->assertStringContainsString(
            "ALTER TABLE `{\$catTable}` MODIFY `category_id` INT UNSIGNED NOT NULL AUTO_INCREMENT",
            $source
        );
        $this->assertStringContainsString(
            "ALTER TABLE `{\$itemTable}` MODIFY `items_id` INT UNSIGNED NOT NULL AUTO_INCREMENT",
            $source
        );
        $this->assertStringContainsString(
            "ALTER TABLE `{\$itemTable}` MODIFY `items_cid` INT UNSIGNED NOT NULL DEFAULT 0",
            $source
        );
        $this->assertStringContainsString(
            "ALTER TABLE `{\$catTable}` MODIFY `category_target` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0",
            $source
        );
        $this->assertStringContainsString(
            "ALTER TABLE `{\$itemTable}` MODIFY `items_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1",
            $source
        );
        $this->assertStringContainsString(
            'FOREIGN KEY (`items_cid`) REFERENCES `{$catTable}` (`category_id`)',
            $source
        );
    }

    #[Test]
    public function installerUsesSharedSeedDefinitionsAndSeedsMenusAfterSystemModuleInsert(): void
    {
        $source = $this->readSourceFile('install/include/makedata.php');

        $this->assertStringContainsString(
            'if (!system_menu_install_seed_defaults($dbm, $groups, 1)) {',
            $source
        );
    }

    #[Test]
    public function installerInvokesForeignKeyHelperAfterSuccessfulMenuSeeding(): void
    {
        $source = $this->readSourceFile('install/include/makedata.php');

        $this->assertMatchesRegularExpression(
            '/if\\s*\\(\\s*!system_menu_install_seed_defaults\\(\\$dbm, \\$groups, 1\\)\\s*\\).*?'
            . 'system_menu_install_add_category_fk\\(\\$dbm\\);/s',
            $source,
            'make_data() must call system_menu_install_add_category_fk() after the seed-success guard'
        );
    }

    #[Test]
    public function installerForeignKeyHelperBuildsPrefixedAlterTableStatement(): void
    {
        $source = $this->readSourceFile('install/include/makedata.php');

        $this->assertStringContainsString(
            'function system_menu_install_add_category_fk($dbm): bool',
            $source,
            'Helper must be defined in install/include/makedata.php'
        );
        $this->assertStringContainsString(
            "\$db->prefix('menusitems')",
            $source
        );
        $this->assertStringContainsString(
            "\$db->prefix('menuscategory')",
            $source
        );
        $this->assertStringContainsString(
            "\$db->prefix('fk_items_category')",
            $source
        );
        $this->assertMatchesRegularExpression(
            '/ALTER TABLE `[^`]*` ADD CONSTRAINT `[^`]*`/',
            $source
        );
        $this->assertStringContainsString(
            'FOREIGN KEY (`items_cid`) REFERENCES',
            $source
        );
        $this->assertStringContainsString(
            'ON DELETE CASCADE',
            $source
        );
    }

    #[Test]
    public function installerForeignKeyHelperIsIdempotentAndNonFatal(): void
    {
        $source = $this->readSourceFile('install/include/makedata.php');

        $this->assertStringContainsString(
            'INFORMATION_SCHEMA.TABLE_CONSTRAINTS',
            $source,
            'Helper must check INFORMATION_SCHEMA to stay idempotent'
        );
        $this->assertMatchesRegularExpression(
            '/getRowsNum\\(\\$result\\)\\s*>\\s*0\\s*\\)\\s*\\{\\s*return true;/',
            $source,
            'Helper must short-circuit when the FK already exists'
        );
        $this->assertMatchesRegularExpression(
            '/trigger_error\\(\\s*[\'"][^\'"]*foreign key[^\'"]*[\'"]\\s*,\\s*E_USER_WARNING\\s*\\)/i',
            $source,
            'Helper must surface exec failure via trigger_error(E_USER_WARNING)'
        );
        $this->assertMatchesRegularExpression(
            '/false === \\$db->exec\\(\\$sql\\)\\)\\s*\\{[^}]*return false;/s',
            $source,
            'Helper must return false on exec failure without aborting install'
        );
    }

    #[Test]
    public function foreignKeyHelperEmitsPrefixedAlterTableWhenFkMissing(): void
    {
        $dbm = new class {
            public object $db;
            public function __construct()
            {
                // Stubs declare no parameters; PHP accepts extra positional args silently.
                // This keeps the helper's $db->query($sql) etc. call sites working while
                // not declaring (therefore not leaving unused) the args the stub ignores.
                $this->db = new class {
                    public array $execCalls = [];
                    public function prefix(string $name): string
                    {
                        return 'xo_' . $name;
                    }
                    public function quote(string $value): string
                    {
                        return "'" . addslashes($value) . "'";
                    }
                    public function query(): bool
                    {
                        // Returning false makes the existence-short-circuit fall through
                        // to the ALTER branch (the path being tested).
                        return false;
                    }
                    public function isResultSet(): bool
                    {
                        return false;
                    }
                    public function getRowsNum(): int
                    {
                        return 0;
                    }
                    public function exec(string $sql)
                    {
                        $this->execCalls[] = $sql;
                        return 1;
                    }
                };
            }
        };

        $result = system_menu_install_add_category_fk($dbm);

        $this->assertTrue($result, 'Helper must return true when the ALTER succeeds');
        $this->assertCount(1, $dbm->db->execCalls, 'Helper must issue exactly one ALTER');
        $alter = $dbm->db->execCalls[0];
        $this->assertStringContainsString('ALTER TABLE `xo_menusitems`', $alter);
        $this->assertStringContainsString('ADD CONSTRAINT `xo_fk_items_category`', $alter);
        $this->assertStringContainsString('FOREIGN KEY (`items_cid`)', $alter);
        $this->assertStringContainsString('REFERENCES `xo_menuscategory` (`category_id`)', $alter);
        $this->assertStringContainsString('ON DELETE CASCADE', $alter);
    }

    #[Test]
    public function foreignKeyHelperWarnsAndReturnsFalseWhenAlterFails(): void
    {
        $dbm = new class {
            public object $db;
            public function __construct()
            {
                $this->db = new class {
                    public int $execCallCount = 0;
                    public function prefix(string $name): string
                    {
                        return 'xo_' . $name;
                    }
                    public function quote(string $value): string
                    {
                        return "'" . addslashes($value) . "'";
                    }
                    public function query(): bool
                    {
                        return false;
                    }
                    public function isResultSet(): bool
                    {
                        return false;
                    }
                    public function getRowsNum(): int
                    {
                        return 0;
                    }
                    public function exec(): bool
                    {
                        $this->execCallCount++;
                        return false;
                    }
                };
            }
        };

        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = [$errno, $errstr];
            return true;
        });

        try {
            $result = system_menu_install_add_category_fk($dbm);
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($result, 'Helper must return false when the ALTER fails');
        $this->assertSame(1, $dbm->db->execCallCount, 'Helper must attempt the ALTER exactly once');
        $this->assertCount(1, $warnings, 'Helper must emit exactly one E_USER_WARNING on failure');
        $this->assertSame(E_USER_WARNING, $warnings[0][0]);
        $this->assertStringContainsString('foreign key', $warnings[0][1]);
    }

    #[Test]
    public function systemModuleRegistersInstallAndUpdateHooksToSameScript(): void
    {
        $modversion = [];
        require_once XOOPS_ROOT_PATH . '/modules/system/language/english/modinfo.php';
        include XOOPS_ROOT_PATH . '/modules/system/xoops_version.php';

        $this->assertSame('include/update.php', $modversion['onInstall'] ?? null);
        $this->assertSame('include/update.php', $modversion['onUpdate'] ?? null);
    }

    #[Test]
    public function updateScriptExposesMenuLifecycleFunctions(): void
    {
        require_once XOOPS_ROOT_PATH . '/modules/system/include/update.php';

        $this->assertTrue(function_exists('xoops_module_install_system'));
        $this->assertTrue(function_exists('xoops_module_update_system'));
        $this->assertTrue(function_exists('system_menu_seed_defaults'));
    }

    #[Test]
    public function installerSeedingStopsWhenCategoryInsertFails(): void
    {
        $dbm = new class {
            public array $calls = [];

            public function insert($table, $values)
            {
                $this->calls[] = $table;
                if ($table === 'menuscategory') {
                    return false;
                }

                return count($this->calls);
            }
        };

        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = [$errno, $errstr];
            return true;
        });

        try {
            $result = system_menu_install_seed_defaults(
                $dbm,
                [
                    'XOOPS_GROUP_ADMIN' => 1,
                    'XOOPS_GROUP_USERS' => 2,
                    'XOOPS_GROUP_ANONYMOUS' => 3,
                ],
                1
            );
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($result);
        $this->assertSame(['menuscategory'], $dbm->calls);
        $this->assertCount(1, $warnings);
        $this->assertSame(E_USER_WARNING, $warnings[0][0]);
        $this->assertStringContainsString('Failed to seed menu category', $warnings[0][1]);
    }

    #[Test]
    public function installerSeedsExpectedRowsAndRespectsCategoryBeforeItemOrdering(): void
    {
        $dbm = new class {
            public array $inserts = [];
            /** @var array<string, int> */
            private array $ids = [];

            public function insert($table, $values)
            {
                $nextId = ($this->ids[$table] ?? 0) + 1;
                $this->ids[$table] = $nextId;
                $this->inserts[] = [
                    'table' => $table,
                    'values' => $values,
                    'id' => $nextId,
                ];

                return $nextId;
            }
        };

        $result = system_menu_install_seed_defaults(
            $dbm,
            [
                'XOOPS_GROUP_ADMIN' => 1,
                'XOOPS_GROUP_USERS' => 2,
                'XOOPS_GROUP_ANONYMOUS' => 3,
            ],
            1
        );

        $this->assertTrue($result);
        $byTable = array_count_values(array_column($dbm->inserts, 'table'));
        $this->assertSame(3, $byTable['menuscategory'] ?? 0);
        $this->assertSame(7, $byTable['menusitems'] ?? 0);
        $this->assertGreaterThan(0, $byTable['group_permission'] ?? 0);

        $categoryInsertIndexes = [];
        $itemInsertIndexes = [];
        foreach ($dbm->inserts as $index => $insert) {
            if ($insert['table'] === 'menuscategory') {
                $categoryInsertIndexes[] = $index;
            }
            if ($insert['table'] === 'menusitems') {
                $itemInsertIndexes[] = $index;
            }
        }

        $this->assertNotEmpty($categoryInsertIndexes);
        $this->assertNotEmpty($itemInsertIndexes);
        $this->assertLessThan(
            min($itemInsertIndexes),
            max($categoryInsertIndexes),
            'All menu categories should be inserted before the first menu item'
        );

        $categoryInserts = array_values(array_filter(
            $dbm->inserts,
            static fn(array $insert): bool => $insert['table'] === 'menuscategory'
        ));
        $accountCategoryId = null;
        foreach ($categoryInserts as $insert) {
            if (str_contains($insert['values'], "'MENUS_ACCOUNT'")) {
                $accountCategoryId = $insert['id'];
                break;
            }
        }

        $this->assertNotNull($accountCategoryId, 'Expected to find the Account category insert');

        $itemInserts = array_values(array_filter(
            $dbm->inserts,
            static fn(array $insert): bool => $insert['table'] === 'menusitems'
        ));
        foreach ($itemInserts as $insert) {
            $this->assertMatchesRegularExpression(
                "/VALUES \\(0, {$accountCategoryId}, 'MENUS_ACCOUNT_[A-Z_]+'/",
                $insert['values'],
                'Each seeded menu item should reference the inserted Account category id'
            );
        }
    }
}
