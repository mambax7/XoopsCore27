<?php

declare(strict_types=1);

namespace xoopsauth;

use kernel\KernelTestCase;
use PHPUnit\Framework\Attributes\Test;

require_once XOOPS_ROOT_PATH . '/class/auth/auth.php';
require_once XOOPS_ROOT_PATH . '/class/auth/auth_ldap.php';

/**
 * LDAP login input must be escaped before it is placed in a search filter or a
 * bind DN (finding H-6), so metacharacters cannot alter the directory query.
 */
final class AuthLdapEscapeTest extends KernelTestCase
{
    private function ldap(): \XoopsAuthLdap
    {
        $o = (new \ReflectionClass(\XoopsAuthLdap::class))->newInstanceWithoutConstructor();
        $this->setProtectedProperty($o, 'ldap_filter_person', '');
        $this->setProtectedProperty($o, 'ldap_loginldap_attr', 'uid');
        $this->setProtectedProperty($o, 'ldap_base_dn', 'dc=x,dc=y');
        $this->setProtectedProperty($o, 'ldap_loginname_asdn', true);
        return $o;
    }

    #[Test]
    public function filterEscapesMetacharacters(): void
    {
        if (!function_exists('ldap_escape')) {
            self::markTestSkipped('ext-ldap not available');
        }
        $filter = strtolower($this->ldap()->getFilter('a*)(uid=*'));
        self::assertStringNotContainsString('*', $filter);
        self::assertStringNotContainsString(')(', $filter);
        // every metacharacter is escaped to its \HEX form
        self::assertStringContainsString('\2a', $filter); // *
        self::assertStringContainsString('\28', $filter); // (
        self::assertStringContainsString('\29', $filter); // )
    }

    #[Test]
    public function userDnEscapesMetacharacters(): void
    {
        if (!function_exists('ldap_escape')) {
            self::markTestSkipped('ext-ldap not available');
        }
        $dn = strtolower($this->ldap()->getUserDN('a,b+c'));
        self::assertStringNotContainsString('a,b+c', $dn);
        // metacharacters escaped to their \HEX forms
        self::assertStringContainsString('\2c', $dn); // ,
        self::assertStringContainsString('\2b', $dn); // +
    }

    #[Test]
    public function sourceUsesContextSpecificLdapEscape(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/class/auth/auth_ldap.php');
        self::assertNotFalse($src);
        self::assertStringContainsString('LDAP_ESCAPE_FILTER', $src);
        self::assertStringContainsString('LDAP_ESCAPE_DN', $src);
    }
}
