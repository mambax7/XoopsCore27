<?php

declare(strict_types=1);

/**
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * @copyright    2000-2026 XOOPS Project (https://xoops.org/)
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author       XOOPS Development Team
 */

namespace Xoops\Helpers\Service;

use Xoops\Helpers\Utility\Filesystem;

/**
 * Shared HTMLPurifier front-end with a correctly configured definition cache.
 *
 * Replaces the per-module copy-paste that either disabled the definition
 * cache ('Cache.DefinitionImpl' => null — a ~40-60ms HTML-definition rebuild
 * on EVERY request) or let the default serializer try to write inside the
 * vendor tree. Definitions are serialized once into a writable cache
 * directory and load in ~1ms afterwards; purifier instances are memoized
 * per configuration for the rest of the request.
 *
 * HTMLPurifier is an optional dependency — every entry point degrades to null
 * when it is absent, so callers can fall back to their own sanitizer.
 *
 * Usage:
 *   $safe = HtmlSanitizer::purify($userHtml);                          // default allowlist
 *   $safe = HtmlSanitizer::purify($userHtml, [
 *       'HTML.Allowed'            => 'p,br,strong,em,a[href|title]',
 *       'Attr.AllowedFrameTargets' => ['_blank'],
 *   ]);
 *   if (null === $safe) { ...fallback, HTMLPurifier not installed... }
 */
final class HtmlSanitizer
{
    /** Baseline rich-text allowlist used when no HTML.Allowed override is given */
    public const DEFAULT_ALLOWED = 'p,br,strong,b,em,i,u,s,blockquote,span[class|style],div[class|style],'
        . 'ul,ol,li,a[href|title|target|rel],h3,h4';

    /** @var array<string, \HTMLPurifier> memoized instances keyed by config signature */
    private static array $purifiers = [];

    /**
     * Whether the HTMLPurifier library is installed and usable.
     */
    public static function isAvailable(): bool
    {
        return class_exists(\HTMLPurifier::class) && class_exists(\HTMLPurifier_Config::class);
    }

    /**
     * Purify an HTML fragment; null when HTMLPurifier is not installed so the
     * caller can apply its own fallback (e.g. MyTextSanitizer). Never throws.
     *
     * @param string               $html   untrusted HTML fragment
     * @param array<string, mixed> $config HTMLPurifier directive overrides
     *                                     (key => value, applied over the defaults)
     */
    public static function purify(string $html, array $config = []): ?string
    {
        if ('' === trim($html)) {
            return '';
        }
        $purifier = self::purifier($config);
        if (null === $purifier) {
            return null;
        }
        try {
            return (string) $purifier->purify($html);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Memoized purifier for one directive set; null when unavailable.
     *
     * @param array<string, mixed> $config HTMLPurifier directive overrides
     */
    public static function purifier(array $config = []): ?\HTMLPurifier
    {
        if (!self::isAvailable()) {
            return null;
        }
        $key = md5(serialize($config));
        if (isset(self::$purifiers[$key])) {
            return self::$purifiers[$key];
        }
        try {
            $purifierConfig = \HTMLPurifier_Config::createDefault();
            if (!\array_key_exists('HTML.Allowed', $config)) {
                $purifierConfig->set('HTML.Allowed', self::DEFAULT_ALLOWED);
            }
            $cacheDir = self::cacheDir();
            if ('' !== $cacheDir) {
                $purifierConfig->set('Cache.SerializerPath', $cacheDir);
            } else {
                // No writable cache dir: rebuild in-memory rather than letting
                // the default serializer attempt writes inside the vendor tree.
                $purifierConfig->set('Cache.DefinitionImpl', null);
            }
            foreach ($config as $directive => $value) {
                $purifierConfig->set($directive, $value);
            }

            return self::$purifiers[$key] = new \HTMLPurifier($purifierConfig);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Drop every memoized purifier instance.
     *
     * Only needed when the cache directory or directive defaults change within
     * a single request — chiefly in tests.
     */
    public static function flush(): void
    {
        self::$purifiers = [];
    }

    /**
     * Writable definition-cache directory, secured with an index.html guard;
     * empty string when none can be provided.
     */
    private static function cacheDir(): string
    {
        try {
            $dir = Path::storage('caches/htmlpurifier');
        } catch (\Throwable $e) {
            $dir = \defined('XOOPS_VAR_PATH') ? XOOPS_VAR_PATH . '/caches/htmlpurifier' : '';
        }
        if ('' === $dir || !Filesystem::secureDir($dir) || !is_writable($dir)) {
            return '';
        }

        return $dir;
    }
}
