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

namespace xoopscaptcha;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/captcha/xoopscaptcha.php';
require_once XOOPS_ROOT_PATH . '/class/captcha/recaptcha2.php';

/**
 * Records the verification request instead of sending it, and the error codes
 * instead of handing them to the XoopsCaptcha singleton.
 */
final class RecordingRecaptcha2 extends \XoopsCaptchaRecaptcha2
{
    public ?string $url = null;
    public ?string $body = null;
    public ?string $answer = null;

    /** @var list<string> */
    public array $reported = [];

    protected function postVerification(string $url, string $body): ?string
    {
        $this->url  = $url;
        $this->body = $body;

        return $this->answer;
    }

    protected function reportErrors(array $codes): void
    {
        $this->reported = array_merge($this->reported, $codes);
    }
}

/**
 * reCAPTCHA v2 verification is a POST with the secret in the body.
 *
 * verify() used to send the secret key, the user's response and the client
 * IP in the query string of a GET to Google's siteverify endpoint: the secret
 * then appeared in proxy and server logs, and in the PHP warning of a failed
 * file_get_contents(), and an unencoded response containing & could add query
 * parameters. Google documents siteverify as POST.
 *
 * @category  Xoops
 * @package   core
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class XoopsCaptchaRecaptcha2Test extends TestCase
{
    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        // Without the seams verify() would call Google; fail instead.
        if (!method_exists(\XoopsCaptchaRecaptcha2::class, 'postVerification')
            || !method_exists(\XoopsCaptchaRecaptcha2::class, 'reportErrors')
        ) {
            self::fail('XoopsCaptchaRecaptcha2 has no postVerification()/reportErrors() seam.');
        }
        $this->saved = ['request' => $_REQUEST, 'post' => $_POST, 'server' => $_SERVER];
        $_REQUEST['g-recaptcha-response'] = 'token&secret=injected';
        $_POST['g-recaptcha-response']    = 'token&secret=injected';
        $_SERVER['REMOTE_ADDR']           = '203.0.113.7';
    }

    protected function tearDown(): void
    {
        if ([] !== $this->saved) {
            $_REQUEST = $this->saved['request'];
            $_POST    = $this->saved['post'];
            $_SERVER  = $this->saved['server'];
        }
    }

    private static function captcha(?string $answer): RecordingRecaptcha2
    {
        $captcha         = new RecordingRecaptcha2();
        $captcha->config = ['secret_key' => 'pr1v&te key', 'website_key' => 'site-key'];
        $captcha->answer = $answer;

        return $captcha;
    }

    #[Test]
    public function theSecretTravelsInThePostBodyNotTheUrl(): void
    {
        $captcha = self::captcha('{"success":true}');
        $captcha->verify();

        self::assertSame('https://www.google.com/recaptcha/api/siteverify', $captcha->url);
        parse_str((string) $captcha->body, $fields);
        self::assertSame(
            ['secret' => 'pr1v&te key', 'response' => 'token&secret=injected', 'remoteip' => '203.0.113.7'],
            $fields,
            'Each field is encoded, so a response containing & cannot add parameters.'
        );
    }

    #[Test]
    public function anUnknownClientIpIsLeftOutNotSentAsZero(): void
    {
        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
        $captcha = self::captcha('{"success":true}');
        $captcha->verify();

        parse_str((string) $captcha->body, $fields);
        self::assertSame(
            ['secret' => 'pr1v&te key', 'response' => 'token&secret=injected'],
            $fields,
            'IPAddress::asReadable() is false for an unparseable address; http_build_query() would send it as 0.'
        );
    }

    #[Test]
    public function aSuccessfulAnswerVerifies(): void
    {
        $captcha = self::captcha('{"success":true,"hostname":"example.com"}');

        self::assertTrue($captcha->verify());
        self::assertSame([], $captcha->reported);
    }

    #[Test]
    public function aRejectionFailsAndReportsGooglesErrorCodes(): void
    {
        $captcha = self::captcha('{"success":false,"error-codes":["invalid-input-response","timeout-or-duplicate"]}');

        self::assertFalse($captcha->verify());
        self::assertSame(['invalid-input-response', 'timeout-or-duplicate'], $captcha->reported);
    }

    #[Test]
    public function noAnswerOrABadAnswerFailsQuietly(): void
    {
        $warnings = [];
        set_error_handler(static function (int $no, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });
        try {
            foreach ([null, '', 'not json', '{"success":"true"}', '[]'] as $answer) {
                $captcha = self::captcha($answer);
                self::assertFalse($captcha->verify(), var_export($answer, true));
                self::assertSame([], $captcha->reported, var_export($answer, true));
            }
        } finally {
            restore_error_handler();
        }
        self::assertSame([], $warnings);
    }

    #[Test]
    public function theSourceNeverBuildsTheSecretIntoAUrl(): void
    {
        $source = (string) file_get_contents(XOOPS_ROOT_PATH . '/class/captcha/recaptcha2.php');

        self::assertStringNotContainsString('siteverify?', $source);
        self::assertStringContainsString('CURLOPT_POST', $source);
        self::assertSame(1, preg_match("/'method'\s*=>\s*'POST'/", $source), 'The stream fallback must POST too.');
    }
}
