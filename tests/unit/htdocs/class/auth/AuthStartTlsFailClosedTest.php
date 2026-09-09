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

namespace Tests\Unit\ClassDir\Auth;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__, 2) . '/modules/system/SourceFileTestTrait.php';

/**
 * When a directory connection is configured to use StartTLS and the
 * negotiation fails, the LDAP and Active Directory adapters recorded the
 * error and went on to look up and bind with credentials over the plain
 * connection. Both must stop before any bind.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class AuthStartTlsFailClosedTest extends TestCase
{
    use SourceFileTestTrait;

    /**
     * @return array<string, array{string}>
     */
    public static function adapters(): array
    {
        return [
            'ldap' => ['htdocs/class/auth/auth_ldap.php'],
            'ads'  => ['htdocs/class/auth/auth_ads.php'],
        ];
    }

    #[Test]
    #[DataProvider('adapters')]
    public function failedStartTlsReturnsBeforeAnyBind(string $path): void
    {
        $this->loadSourceFile($path);

        $tls = strpos($this->sourceContent, 'if (!ldap_start_tls($this->_ds)) {');
        self::assertNotFalse($tls, 'StartTLS branch present');
        $block = substr($this->sourceContent, $tls, strpos($this->sourceContent, '}', $tls) - $tls);

        self::assertStringContainsString('_AUTH_LDAP_START_TLS_FAILED', $block);
        self::assertStringContainsString('return false;', $block, 'the failure branch must leave authenticate()');

        $bind = strpos($this->sourceContent, 'ldap_bind(', $tls);
        self::assertNotFalse($bind);
        self::assertGreaterThan($tls, $bind, 'the bind comes after the TLS branch, so the return guards it');
    }
}
