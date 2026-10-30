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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once XOOPS_ROOT_PATH . '/class/captcha/xoopscaptcha.php';

/**
 * The text challenge can ask "0 + 0", so a code of 0 must be stored by
 * setCode() and accepted by the handler-side verify(); only an absent or
 * empty code rejects every answer.
 *
 * @category  Xoops
 * @package   Captcha
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class XoopsCaptchaZeroAnswerTest extends TestCase
{
    private const NAME = 'zero_test';

    /** @var array<string, mixed> */
    private array $savedSession = [];
    /** @var array<string, mixed> */
    private array $savedPost = [];

    protected function setUp(): void
    {
        $this->savedSession = $_SESSION ?? [];
        $this->savedPost    = $_POST;
        $_SESSION           = [];
        $_POST              = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->savedSession;
        $_POST    = $this->savedPost;
    }

    private function captcha(): \XoopsCaptcha
    {
        $captcha = (new ReflectionClass(\XoopsCaptcha::class))->newInstanceWithoutConstructor();
        $captcha->name   = self::NAME;
        $captcha->config = ['casesensitive' => false];

        return $captcha;
    }

    /** The handler-side verify() that compares the posted answer with the stored code. */
    private function method(): \XoopsCaptchaMethod
    {
        $method = (new ReflectionClass(\XoopsCaptchaMethod::class))->newInstanceWithoutConstructor();
        $method->config = ['casesensitive' => false];

        return $method;
    }

    /**
     * @return array<string, array{int|string}>
     */
    public static function codes(): array
    {
        return ['zero' => [0], 'zero string' => ['0'], 'seven' => [7], 'word' => ['abc']];
    }

    #[Test]
    #[DataProvider('codes')]
    public function setCodeStoresTheCodeAndVerifyAcceptsTheSameAnswer(int|string $code): void
    {
        $captcha = $this->captcha();

        self::assertTrue($captcha->setCode($code));
        self::assertSame((string) $code, $_SESSION[self::NAME . '_code']);

        $_POST[self::NAME] = ' ' . $code . ' ';
        self::assertTrue($this->method()->verify(self::NAME));
        $_POST[self::NAME] = $code . 'x';
        self::assertFalse($this->method()->verify(self::NAME));
    }

    #[Test]
    public function anEmptyCodeIsNeitherStoredNorAccepted(): void
    {
        $captcha = $this->captcha();

        self::assertFalse($captcha->setCode(''));
        self::assertArrayNotHasKey(self::NAME . '_code', $_SESSION);

        $_SESSION[self::NAME . '_code'] = '';
        $_POST[self::NAME]              = '';
        self::assertFalse($this->method()->verify(self::NAME));
    }
}
