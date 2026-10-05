<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

use Xmf\Request;
use Xmf\IPAddress;

/**
 * CAPTCHA for Recaptcha mode
 *
 * @package     class
 * @subpackage  CAPTCHA
 * @author      Grégory Mage
 * @copyright   2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link        https://xoops.org
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Class XoopsCaptchaRecaptcha2
 */
class XoopsCaptchaRecaptcha2 extends XoopsCaptchaMethod
{
    /** Google's verification endpoint; the request is a POST, never a GET with a query string. */
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * XoopsCaptchaRecaptcha2::isActive()
     *
     * @return bool
     */
    public function isActive()
    {
        return true;
    }

    /**
     * XoopsCaptchaRecaptcha2::render()
     *
     * @return string
     */
    public function render()
    {
        $form = '<script src="https://www.google.com/recaptcha/api.js"></script>';
        $form .= '<div class="form-group"><div class="g-recaptcha" data-sitekey="'
            . $this->config['website_key'] . '"></div></div>';
        return $form;
    }

    /**
     * XoopsCaptchaRecaptcha2::verify()
     *
     * Asks Google whether the user's reCAPTCHA response is valid. The secret
     * key, the response and the client IP are sent URL-encoded in the body of
     * a POST, as Google documents siteverify: never in a URL, where the secret
     * would reach proxy and server logs, and where an unencoded response could
     * add parameters. No answer, or an answer that is not a success, fails.
     *
     * @param string|null $sessionName unused for recaptcha
     *
     * @return bool
     */
    public function verify($sessionName = null)
    {
        $body = http_build_query([
            'secret'   => (string) ($this->config['secret_key'] ?? ''),
            'response' => Request::getString('g-recaptcha-response', ''),
            'remoteip' => IPAddress::fromRequest()->asReadable(),
        ], '', '&');

        $answer = $this->postVerification(self::VERIFY_URL, $body);
        $check  = null === $answer ? null : json_decode($answer, true);
        if (is_array($check) && true === ($check['success'] ?? null)) {
            return true;
        }
        $codes = is_array($check) && is_array($check['error-codes'] ?? null) ? $check['error-codes'] : [];
        $this->reportErrors(array_values(array_map('strval', $codes)));

        return false;
    }

    /**
     * POST the verification request and return the response body, or null
     * when there is no answer. Uses cURL when available, otherwise a stream
     * context; the secret is only ever in the body.
     *
     * @param string $url  verification endpoint
     * @param string $body URL-encoded form fields
     *
     * @return string|null
     */
    protected function postVerification(string $url, string $body): ?string
    {
        if (function_exists('curl_init') && false !== ($curlHandle = curl_init())) {
            curl_setopt_array($curlHandle, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_FAILONERROR    => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 10,
            ]);
            $curlReturn = curl_exec($curlHandle);
            if (is_string($curlReturn)) {
                return $curlReturn;
            }
            trigger_error(curl_error($curlHandle));
        }

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'timeout' => 10,
            ],
        ]);
        // The warning of a failed request is not needed: no answer fails the check.
        set_error_handler(static fn (): bool => true);
        try {
            $answer = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        return false === $answer ? null : $answer;
    }

    /**
     * Hand Google's error codes to the CAPTCHA's message list.
     *
     * @param list<string> $codes error codes from the verification answer
     *
     * @return void
     */
    protected function reportErrors(array $codes): void
    {
        if ([] === $codes) {
            return;
        }
        /** @var \XoopsCaptcha $captchaInstance */
        $captchaInstance = \XoopsCaptcha::getInstance();
        foreach ($codes as $code) {
            $captchaInstance->message[] = $code;
        }
    }
}
