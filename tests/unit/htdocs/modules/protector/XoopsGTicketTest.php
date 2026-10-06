<?php

declare(strict_types=1);

namespace modulesprotector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * XoopsGTicket is a deprecated adapter over XoopsSecurity since 2.7.4: a
 * ticket is a XoopsSecurity token in the XOOPS_G_TICKET set, issued with
 * createToken() and checked with check(). The suite bootstrap stubs
 * XoopsSecurity, so these tests install a small in-session double for it
 * that records what the adapter asks of it.
 */
#[CoversClass(\XoopsGTicket::class)]
#[CoversFunction('admin_refcheck')]
class XoopsGTicketTest extends TestCase
{
    private static bool $loaded = false;

    /** @var mixed */
    private $securityBackup;

    /** @var array<string, mixed> exact copies of the superglobals this test touches */
    private array $globalsBackup = [];

    public static function setUpBeforeClass(): void
    {
        if (!self::$loaded) {
            if (!class_exists('XoopsGTicket', false)) {
                require_once XOOPS_PATH . '/modules/protector/class/gtickets.php';
            }
            self::$loaded = true;
        }
    }

    protected function setUp(): void
    {
        $this->securityBackup = $GLOBALS['xoopsSecurity'] ?? null;
        $this->globalsBackup  = ['_SESSION' => $_SESSION ?? null, '_SERVER' => $_SERVER, '_POST' => $_POST, '_GET' => $_GET];
        $GLOBALS['xoopsSecurity'] = new class extends \XoopsSecurity {
            /** @var array<int, array{name: string, timeout: int}> */
            public array $created = [];
            /** @var array<int, array{token: string, name: string}> */
            public array $checked = [];
            /** @var string[] what the real class accumulates in setErrors() */
            public $errors = [];

            public function createToken($timeout = 0, $name = 'XOOPS_TOKEN')
            {
                $token = bin2hex(random_bytes(16));
                $this->created[] = ['name' => $name, 'timeout' => (int) $timeout];
                $_SESSION[$name . '_SESSION'][] = ['token' => $token, 'expire' => time() + (int) $timeout];

                return $token;
            }

            public function check($clearIfValid = true, $token = false, $name = 'XOOPS_TOKEN')
            {
                $this->checked[] = ['token' => (string) $token, 'name' => $name];
                $valid = false;
                foreach ($_SESSION[$name . '_SESSION'] ?? [] as $i => $entry) {
                    if (hash_equals($entry['token'], (string) $token) && $entry['expire'] >= time()) {
                        if ($clearIfValid) {
                            unset($_SESSION[$name . '_SESSION'][$i]);
                        }
                        $valid = true;
                    }
                }
                // like the real class: expired entries are garbage-collected after the check
                $_SESSION[$name . '_SESSION'] = array_filter($_SESSION[$name . '_SESSION'] ?? [], static fn ($e) => $e['expire'] >= time());
                if (!$valid) {
                    $this->errors[] = 'No valid token found';
                }

                return $valid;
            }
        };
        $_SESSION['XOOPS_G_TICKET_SESSION'] = [];
        unset($_SERVER['HTTP_REFERER'], $_POST['XOOPS_G_TICKET'], $_GET['XOOPS_G_TICKET']);
    }

    protected function tearDown(): void
    {
        $GLOBALS['xoopsSecurity'] = $this->securityBackup;
        if (null === $this->globalsBackup['_SESSION']) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->globalsBackup['_SESSION'];
        }
        $_SERVER = $this->globalsBackup['_SERVER'];
        $_POST   = $this->globalsBackup['_POST'];
        $_GET    = $this->globalsBackup['_GET'];
    }

    private function createFreshTicket(): \XoopsGTicket
    {
        return new \XoopsGTicket();
    }

    // ---------------------------------------------------------------
    // Constructor / Default messages
    // ---------------------------------------------------------------

    #[Test]
    public function constructorSetsDefaultMessages(): void
    {
        $ticket = $this->createFreshTicket();
        foreach (['err_general', 'err_noticket', 'err_nopair', 'err_timeout', 'fmt_prompt4repost', 'btn_repost'] as $key) {
            $this->assertArrayHasKey($key, $ticket->messages);
        }
        $this->assertSame([], $ticket->_errors);
        $this->assertSame('', $ticket->_latest_token);
    }

    // ---------------------------------------------------------------
    // issue(): a XoopsSecurity token in the XOOPS_G_TICKET set
    // ---------------------------------------------------------------

    #[Test]
    public function issueCreatesAXoopsSecurityTokenInTheTicketSet(): void
    {
        $ticket = $this->createFreshTicket();
        $value  = $ticket->issue('salt', 600, 'area');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $value);
        $this->assertSame($value, $ticket->_latest_token);
        $this->assertSame([['name' => 'XOOPS_G_TICKET', 'timeout' => 600]], $GLOBALS['xoopsSecurity']->created);
        $this->assertTrue($ticket->using());
    }

    #[Test]
    public function issueKeepsAZeroTimeoutShortInsteadOfSessionLong(): void
    {
        $ticket = $this->createFreshTicket();
        $ticket->issue('', 0);
        $ticket->issue('', -5);
        $this->assertSame(
            [['name' => 'XOOPS_G_TICKET', 'timeout' => 1], ['name' => 'XOOPS_G_TICKET', 'timeout' => -5]],
            $GLOBALS['xoopsSecurity']->created,
            'XoopsSecurity would read 0 as the session lifetime; a negative value is already expired there too'
        );
    }

    #[Test]
    public function issueReturnsDifferentTicketsEachTime(): void
    {
        $ticket = $this->createFreshTicket();
        $this->assertNotSame($ticket->issue(), $ticket->issue());
        $this->assertCount(2, $_SESSION['XOOPS_G_TICKET_SESSION']);
    }

    // ---------------------------------------------------------------
    // Form helpers keep the XOOPS_G_TICKET field
    // ---------------------------------------------------------------

    #[Test]
    public function getTicketHtmlReturnsHiddenInput(): void
    {
        $html = $this->createFreshTicket()->getTicketHtml('salt', 1800, 'area');
        $this->assertMatchesRegularExpression('/^<input type="hidden" name="XOOPS_G_TICKET" value="[a-f0-9]{32}" \/>$/', $html);
    }

    #[Test]
    public function getTicketArrayReturnsArrayWithKey(): void
    {
        $array = $this->createFreshTicket()->getTicketArray();
        $this->assertSame(['XOOPS_G_TICKET'], array_keys($array));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $array['XOOPS_G_TICKET']);
    }

    #[Test]
    public function getTicketParamStringWithAndWithoutAmp(): void
    {
        $ticket = $this->createFreshTicket();
        $this->assertMatchesRegularExpression('/^&amp;XOOPS_G_TICKET=[a-f0-9]{32}$/', $ticket->getTicketParamString());
        $this->assertMatchesRegularExpression('/^XOOPS_G_TICKET=[a-f0-9]{32}$/', $ticket->getTicketParamString('', true));
    }

    // ---------------------------------------------------------------
    // clear() / using()
    // ---------------------------------------------------------------

    #[Test]
    public function clearEmptiesTheTicketSet(): void
    {
        $ticket = $this->createFreshTicket();
        $ticket->issue();
        $this->assertTrue($ticket->using());
        $ticket->clear();
        $this->assertSame([], $_SESSION['XOOPS_G_TICKET_SESSION']);
        $this->assertFalse($ticket->using());
    }

    // ---------------------------------------------------------------
    // getErrors()
    // ---------------------------------------------------------------

    #[Test]
    public function getErrorsReturnsHtmlStringByDefaultAndArrayOnRequest(): void
    {
        $ticket = $this->createFreshTicket();
        $ticket->_errors = ['Error 1', 'Error 2'];
        $this->assertSame("Error 1<br>\nError 2<br>\n", $ticket->getErrors(true));
        $this->assertSame(['Error 1', 'Error 2'], $ticket->getErrors(false));
        $ticket->_errors = [];
        $this->assertSame('', $ticket->getErrors(true));
        $this->assertSame([], $ticket->getErrors(false));
    }

    // ---------------------------------------------------------------
    // extract_post_recursive()
    // ---------------------------------------------------------------

    #[Test]
    public function extractPostRecursiveFlatValues(): void
    {
        $ticket = $this->createFreshTicket();
        [$table, $form] = $ticket->extract_post_recursive('field', ['name' => 'John', 'email' => 'john@test.com']);

        $this->assertStringContainsString('field[name]', $table);
        $this->assertStringContainsString('John', $table);
        $this->assertStringContainsString('<input type="hidden"', $form);
        $this->assertStringContainsString('value="John"', $form);
    }

    #[Test]
    public function extractPostRecursiveNestedValues(): void
    {
        [$table] = $this->createFreshTicket()->extract_post_recursive('parent', ['sub' => ['key' => 'val']]);
        $this->assertStringContainsString('parent[sub][key]', $table);
        $this->assertStringContainsString('val', $table);
    }

    #[Test]
    public function extractPostRecursiveEscapesHtml(): void
    {
        [$table] = $this->createFreshTicket()->extract_post_recursive('field', ['xss' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>', $table);
        $this->assertStringContainsString('&lt;script&gt;', $table);
    }

    // ---------------------------------------------------------------
    // check(): no allow_repost, so it returns instead of exiting
    // ---------------------------------------------------------------

    #[Test]
    public function checkFailsWithEmptyTicket(): void
    {
        $ticket = $this->createFreshTicket();
        $this->assertFalse($ticket->check(true, 'area', false));
        $this->assertSame([$ticket->messages['err_noticket']], $ticket->_errors);
        $this->assertSame([], $GLOBALS['xoopsSecurity']->checked, 'nothing to check');
    }

    #[Test]
    public function checkSucceedsWithValidTicketAndConsumesIt(): void
    {
        $ticket = $this->createFreshTicket();
        $_POST['XOOPS_G_TICKET'] = $ticket->issue('salt', 1800, 'area');

        $this->assertTrue($ticket->check(true, 'area', false));
        $this->assertSame([], $ticket->_errors);
        $this->assertSame([['token' => $_POST['XOOPS_G_TICKET'], 'name' => 'XOOPS_G_TICKET']], $GLOBALS['xoopsSecurity']->checked);
        $this->assertFalse($ticket->check(true, 'area', false), 'single use');
    }

    #[Test]
    public function checkReadsTheTicketFromGetWhenAsked(): void
    {
        $ticket = $this->createFreshTicket();
        $_GET['XOOPS_G_TICKET'] = $ticket->issue();
        $this->assertTrue($ticket->check(false, '', false));
    }

    #[Test]
    public function checkFailsForAnUnknownTicketAndClearsTheSet(): void
    {
        $ticket = $this->createFreshTicket();
        $ticket->issue();
        $_POST['XOOPS_G_TICKET'] = str_repeat('0', 32);

        $this->assertFalse($ticket->check(true, '', false));
        $this->assertSame([$ticket->messages['err_nopair']], $ticket->_errors);
        $this->assertFalse($ticket->using(), 'a failed check clears the set, as before');
    }

    #[Test]
    public function aFailedCheckLeavesTheCoreErrorListAlone(): void
    {
        $GLOBALS['xoopsSecurity']->errors = ['earlier'];
        $ticket = $this->createFreshTicket();
        $ticket->issue();
        $_POST['XOOPS_G_TICKET'] = str_repeat('0', 32);

        $this->assertFalse($ticket->check(true, '', false));
        $this->assertSame(['earlier'], $GLOBALS['xoopsSecurity']->errors, 'GTicket reports through its own messages');
    }

    #[Test]
    public function checkReportsAnExpiredTicketAsTimeout(): void
    {
        $ticket = $this->createFreshTicket();
        $_POST['XOOPS_G_TICKET'] = $ticket->issue('', 1800);
        $_SESSION['XOOPS_G_TICKET_SESSION'][0]['expire'] = time() - 1;

        $this->assertFalse($ticket->check(true, '', false));
        $this->assertSame([$ticket->messages['err_timeout']], $ticket->_errors);
    }

    #[Test]
    public function aTicketIsNotAValidCoreToken(): void
    {
        $ticket = $this->createFreshTicket();
        $value  = $ticket->issue();
        $this->assertArrayNotHasKey('XOOPS_TOKEN_SESSION', $_SESSION);
        $this->assertFalse($GLOBALS['xoopsSecurity']->check(true, $value, 'XOOPS_TOKEN'));
    }

    // ---------------------------------------------------------------
    // admin_refcheck() function
    // ---------------------------------------------------------------

    #[Test]
    public function adminRefcheckReturnsTrueWhenNoReferer(): void
    {
        unset($_SERVER['HTTP_REFERER']);
        $this->assertTrue(admin_refcheck());
    }

    #[Test]
    public function adminRefcheckReturnsTrueForValidReferer(): void
    {
        $_SERVER['HTTP_REFERER'] = XOOPS_URL . '/admin/index.php';
        $this->assertTrue(admin_refcheck());
    }

    #[Test]
    public function adminRefcheckReturnsFalseForExternalReferer(): void
    {
        $_SERVER['HTTP_REFERER'] = 'http://evil.example.com/attack';
        $this->assertFalse(admin_refcheck());
    }

    #[Test]
    public function adminRefcheckWithPathRestriction(): void
    {
        $_SERVER['HTTP_REFERER'] = XOOPS_URL . '/modules/system/admin.php';
        $this->assertTrue(admin_refcheck('/modules/system/'));
    }

    #[Test]
    public function adminRefcheckFailsForWrongPath(): void
    {
        $_SERVER['HTTP_REFERER'] = XOOPS_URL . '/modules/other/admin.php';
        $this->assertFalse(admin_refcheck('/modules/system/'));
    }
}
