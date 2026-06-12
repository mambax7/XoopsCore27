<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Extension;

use Xoops\SmartyExtensions\AbstractExtension;

/**
 * Format and display Smarty functions and modifiers.
 *
 * Pure PHP — no XOOPS dependencies.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
final class FormatExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            'datetime_diff' => $this->datetimeDiff(...),
            'get_current_year' => $this->getCurrentYear(...),
        ];
    }

    public function getModifiers(): array
    {
        return [
            'format_date' => $this->formatDate(...),
            'relative_time' => $this->relativeTime(...),
            'format_currency' => $this->formatCurrency(...),
            'number_format' => $this->numberFormat(...),
            'bytes_format' => $this->bytesFormat(...),
            'format_phone_number' => $this->formatPhoneNumber(...),
            'gravatar' => $this->gravatar(...),
        ];
    }

    /**
     * Calculate the difference between two dates.
     *
     * @param array  $params   ['start' => string, 'end' => string, 'format' => string, 'assign' => string]
     * @param object $template Smarty_Internal_Template|Smarty\Template
     */
    public function datetimeDiff(array $params, object $template): string
    {
        $start = $params['start'] ?? '';
        $end = $params['end'] ?? '';
        $format = $params['format'] ?? '%y years, %m months, %d days';

        if ($start === '' || $end === '') {
            return '';
        }

        try {
            $startDate = new \DateTimeImmutable($start);
            $endDate = new \DateTimeImmutable($end);
            $diff = $startDate->diff($endDate);
            $result = $diff->format($format);
        } catch (\Exception) {
            return '';
        }

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return $result;
    }

    /**
     * Return the current year.
     *
     * @param array  $params   ['assign' => string]
     * @param object $template Smarty_Internal_Template|Smarty\Template
     */
    public function getCurrentYear(array $params, object $template): string
    {
        $year = \date('Y');

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $year);
            return '';
        }

        return $year;
    }

    /**
     * Format a date string using PHP date() format characters.
     *
     * @param string|int $date   Date string or Unix timestamp
     * @param string     $format PHP date format (default 'Y-m-d H:i:s')
     */
    public function formatDate(string|int $date, string $format = 'Y-m-d H:i:s'): string
    {
        try {
            if (\is_numeric($date)) {
                $dateObj = new \DateTimeImmutable('@' . (int) $date);
            } else {
                $dateObj = new \DateTimeImmutable($date);
            }

            return $dateObj->format($format);
        } catch (\Exception) {
            return (string) $date;
        }
    }

    /**
     * Convert a timestamp or date string to a human-readable relative time.
     *
     * @param string|int $timestamp Date string or Unix timestamp
     */
    public function relativeTime(string|int $timestamp): string
    {
        try {
            if (\is_numeric($timestamp)) {
                $date = new \DateTimeImmutable('@' . (int) $timestamp);
            } else {
                $date = new \DateTimeImmutable($timestamp);
            }
        } catch (\Exception) {
            return (string) $timestamp;
        }

        $now = new \DateTimeImmutable();
        $diff = $now->diff($date);
        $isFuture = (bool) $diff->invert === false && ($diff->y + $diff->m + $diff->d + $diff->h + $diff->i + $diff->s) > 0;
        $suffix = $isFuture ? ' from now' : ' ago';

        return match (true) {
            $diff->y > 0 => $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . $suffix,
            $diff->m > 0 => $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . $suffix,
            $diff->d > 0 => $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . $suffix,
            $diff->h > 0 => $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . $suffix,
            $diff->i > 0 => $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . $suffix,
            default       => 'Just now',
        };
    }

    /**
     * Format an amount as currency using ICU NumberFormatter (with fallback).
     *
     * @param float|int|string $amount
     * @param string           $currency ISO 4217 code (default 'USD')
     * @param string           $locale   ICU locale (default 'en_US')
     * @param string           $symbol   Fallback symbol if intl unavailable (default '$')
     */
    public function formatCurrency(
        float|int|string $amount,
        string $currency = 'USD',
        string $locale = 'en_US',
        string $symbol = '$',
    ): string {
        $amount = (float) $amount;

        if (\class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $result = $formatter->formatCurrency($amount, $currency);
            if ($result !== false) {
                return $result;
            }
        }

        // Fallback without intl extension
        return $symbol . \number_format($amount, 2);
    }

    /**
     * Format a number with grouped thousands and decimal places.
     *
     * @param float|int|string $number
     * @param int              $decimals     Number of decimal places (default 2)
     * @param string           $decPoint     Decimal separator (default '.')
     * @param string           $thousandsSep Thousands separator (default ',')
     */
    public function numberFormat(
        float|int|string $number,
        int $decimals = 2,
        string $decPoint = '.',
        string $thousandsSep = ',',
    ): string {
        return \number_format((float) $number, $decimals, $decPoint, $thousandsSep);
    }

    /**
     * Format a byte count into a human-readable size string.
     *
     * @param int|float|string $bytes
     * @param int              $precision Decimal places (default 2)
     */
    public function bytesFormat(int|float|string $bytes, int $precision = 2): string
    {
        $bytes = (float) $bytes;

        if ($bytes < 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = 0;

        while ($bytes >= 1024 && $i < \count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return \round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Format a 10-digit phone number as (XXX) XXX-XXXX.
     *
     * @param string $phoneNumber
     */
    public function formatPhoneNumber(string $phoneNumber): string
    {
        // Strip non-digits
        $digits = \preg_replace('/\D/', '', $phoneNumber);

        if (\strlen($digits) === 10) {
            return '(' . \substr($digits, 0, 3) . ') ' . \substr($digits, 3, 3) . '-' . \substr($digits, 6);
        }

        if (\strlen($digits) === 11 && $digits[0] === '1') {
            return '+1 (' . \substr($digits, 1, 3) . ') ' . \substr($digits, 4, 3) . '-' . \substr($digits, 7);
        }

        return $phoneNumber;
    }

    /**
     * Generate a Gravatar URL from an email address.
     *
     * @param string $email
     * @param int    $size    Image size in pixels (default 64)
     * @param string $default Default image type (default 'mp')
     */
    public function gravatar(string $email, int $size = 64, string $default = 'mp'): string
    {
        $hash = \md5(\strtolower(\trim($email)));
        $safeDefault = \urlencode($default);

        return 'https://www.gravatar.com/avatar/' . $hash . '?s=' . $size . '&d=' . $safeDefault;
    }
}
