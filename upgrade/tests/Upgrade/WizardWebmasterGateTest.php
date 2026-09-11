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

namespace Xoops\Upgrade\Tests\Upgrade;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * login.php required webmaster-group membership, but index.php and
 * preflight.php let an already authenticated session through on
 * XoopsUser::isAdmin(), a module-level right that can be delegated. All three
 * entry points must use the one shared check defined in checkmainfile.php.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class WizardWebmasterGateTest extends TestCase
{
    private function source(string $file): string
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        self::assertNotFalse($content, "$file must be readable");

        return $content;
    }

    /**
     * A refused session rotation must stop the wizard login before any state is written.
     *
     * @return void
     */
    #[Test]
    public function sessionRotationMustSucceedBeforeTheWizardWritesLoginState(): void
    {
        $source = $this->source('login.php');
        $guard = strpos($source, "if (!\$GLOBALS['sess_handler']->regenerate_id(true))");
        $write = strpos($source, "\$user->setVar('last_login'");
        self::assertNotFalse($guard);
        self::assertNotFalse($write);
        self::assertLessThan($write, $guard);
        $failure = substr($source, $guard, $write - $guard);
        self::assertStringContainsString('$_SESSION = [];', $failure);
        self::assertStringContainsString('exit();', $failure);
        self::assertStringNotContainsString("\$_SESSION['xoopsUserId']", $failure);
    }

    #[Test]
    public function theSharedCheckIsDefinedInTheBootstrapAndRequiresAnActiveWebmaster(): void
    {
        $bootstrap = $this->source('checkmainfile.php');

        self::assertStringContainsString('function xoops_upgrade_user_is_webmaster($user): bool', $bootstrap);
        $body = substr($bootstrap, strpos($bootstrap, 'function xoops_upgrade_user_is_webmaster'));
        $body = substr($body, 0, strpos($body, '// we have what we need so continue'));
        self::assertStringContainsString("(int) \$user->getVar('level') <= 0", $body);
        self::assertStringContainsString("in_array((int) XOOPS_GROUP_ADMIN, array_map('intval', \$groups), true)", $body);
    }

    #[Test]
    public function everyEntryPointUsesTheSharedCheckAndNotIsAdmin(): void
    {
        foreach (['index.php', 'preflight.php', 'login.php'] as $file) {
            $source = $this->source($file);
            self::assertStringContainsString('xoops_upgrade_user_is_webmaster(', $source, $file);
            self::assertStringNotContainsString('->isAdmin()', $source, "$file must not gate on module-level admin rights");
        }
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function userCases(): array
    {
        return [
            'anonymous (empty string)'          => ['', false],
            'anonymous (null)'                  => [null, false],
            'inactive webmaster'                => [self::user(level: 0, groups: [1]), false],
            'active member, not a webmaster'    => [self::user(level: 1, groups: [2, 3]), false],
            'groups unavailable'                => [self::user(level: 1, groups: null), false],
            'active webmaster'                  => [self::user(level: 1, groups: [1]), true],
            'active webmaster, string group id' => [self::user(level: 1, groups: ['1', '2']), true],
        ];
    }

    #[Test]
    #[DataProvider('userCases')]
    public function theSharedCheckAdmitsOnlyAnActiveWebmaster(mixed $user, bool $expected): void
    {
        self::assertSame($expected, $this->runSharedCheck($user));
    }

    /**
     * Executes the helper as defined in checkmainfile.php, which is otherwise
     * a bootstrap with side effects, by evaluating just its definition in an
     * isolated namespace.
     */
    private function runSharedCheck(mixed $user): bool
    {
        $namespace = __NAMESPACE__ . '\\WizardGate';
        $function  = $namespace . '\\xoops_upgrade_user_is_webmaster';
        if (!function_exists($function)) {
            $bootstrap = $this->source('checkmainfile.php');
            $start     = strpos($bootstrap, 'function xoops_upgrade_user_is_webmaster');
            $end       = strpos($bootstrap, '// we have what we need so continue', (int) $start);
            self::assertNotFalse($start);
            self::assertNotFalse($end);
            if (!defined('XOOPS_GROUP_ADMIN')) {
                define('XOOPS_GROUP_ADMIN', 1);
            }
            eval('namespace ' . $namespace . ";\n" . substr($bootstrap, $start, $end - $start));
        }

        return $function($user);
    }

    /**
     * @param int[]|string[]|null $groups
     */
    private static function user(int $level, ?array $groups): object
    {
        return new class($level, $groups) {
            /** @param int[]|string[]|null $groups */
            public function __construct(private int $level, private ?array $groups)
            {
            }

            public function getVar(string $key): mixed
            {
                return 'level' === $key ? $this->level : null;
            }

            public function getGroups(): mixed
            {
                return $this->groups;
            }
        };
    }
}
