<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Extension;

use Xoops\SmartyExtensions\AbstractExtension;

/**
 * Asset management Smarty functions — deduplicated CSS and JS inclusion.
 *
 * Prevents duplicate <link> and <script> tags when multiple templates or
 * blocks request the same stylesheet or script within a single page render.
 *
 * Pure PHP — no XOOPS dependencies.
 *
 * Usage:
 *   <{require_css file="modules/news/assets/news.css"}>
 *   <{require_js file="modules/news/assets/news.js"}>
 *   <{require_js file="https://cdn.example.com/lib.js" defer=true}>
 *
 * At the end of the page (typically in theme footer):
 *   <{flush_css}>
 *   <{flush_js}>
 *
 * Or collect them as structured arrays for custom rendering:
 *   <{flush_css assign="styles"}>
 *   <{flush_js assign="scripts"}>
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
final class AssetExtension extends AbstractExtension
{
    /** @var array<string, array{file: string, media: string}> */
    private array $css = [];

    /** @var array<string, array{file: string, defer: bool, async: bool}> */
    private array $js = [];

    public function getFunctions(): array
    {
        return [
            'require_css' => $this->requireCss(...),
            'require_js'  => $this->requireJs(...),
            'flush_css'   => $this->flushCss(...),
            'flush_js'    => $this->flushJs(...),
        ];
    }

    /**
     * Register a CSS stylesheet for inclusion.
     *
     * If the same file is registered again with different attributes,
     * the later registration wins (last-write-wins).
     *
     * Parameters:
     *   file  — URL or path to the stylesheet (required)
     *   media — media attribute (default: "all")
     */
    public function requireCss(array $params, object $template): string
    {
        $file = $this->sanitizeAssetUrl($params['file'] ?? '');
        if ($file === '') {
            return '';
        }

        $this->css[$file] = [
            'file'  => $file,
            'media' => $params['media'] ?? 'all',
        ];

        return '';
    }

    /**
     * Register a JavaScript file for inclusion.
     *
     * If the same file is registered again with different attributes,
     * the later registration wins (last-write-wins).
     *
     * Parameters:
     *   file  — URL or path to the script (required)
     *   defer — add defer attribute (default: false)
     *   async — add async attribute (default: false)
     */
    public function requireJs(array $params, object $template): string
    {
        $file = $this->sanitizeAssetUrl($params['file'] ?? '');
        if ($file === '') {
            return '';
        }

        $this->js[$file] = [
            'file'  => $file,
            'defer' => !empty($params['defer']),
            'async' => !empty($params['async']),
        ];

        return '';
    }

    /**
     * Output all registered CSS <link> tags and reset the queue.
     *
     * With assign: stores a list of entry arrays (file, media) for custom rendering.
     */
    public function flushCss(array $params, object $template): string
    {
        if (!empty($params['assign'])) {
            $template->assign($params['assign'], \array_values($this->css));
            $this->css = [];
            return '';
        }

        $html = '';
        foreach ($this->css as $entry) {
            $safeFile = \htmlspecialchars($entry['file'], ENT_QUOTES, 'UTF-8');
            $safeMedia = \htmlspecialchars($entry['media'], ENT_QUOTES, 'UTF-8');
            $html .= '<link rel="stylesheet" href="' . $safeFile . '" media="' . $safeMedia . '">' . "\n";
        }

        $this->css = [];

        return $html;
    }

    /**
     * Output all registered JS <script> tags and reset the queue.
     *
     * With assign: stores a list of entry arrays (file, defer, async) for custom rendering.
     */
    public function flushJs(array $params, object $template): string
    {
        if (!empty($params['assign'])) {
            $template->assign($params['assign'], \array_values($this->js));
            $this->js = [];
            return '';
        }

        $html = '';
        foreach ($this->js as $entry) {
            $safeFile = \htmlspecialchars($entry['file'], ENT_QUOTES, 'UTF-8');
            $attrs = '';
            if ($entry['defer']) {
                $attrs .= ' defer';
            }
            if ($entry['async']) {
                $attrs .= ' async';
            }
            $html .= '<script src="' . $safeFile . '"' . $attrs . '></script>' . "\n";
        }

        $this->js = [];

        return $html;
    }

    /**
     * Decode HTML entities and validate the URL scheme.
     *
     * Accepts: http://, https://, relative paths, protocol-relative (//cdn...).
     * Rejects: javascript:, data:, and any other unsafe scheme.
     *
     * Returns the decoded URL on success, or empty string on rejection.
     */
    private function sanitizeAssetUrl(string $url): string
    {
        $decoded = \html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Absolute with safe scheme
        if (\preg_match('#^https?://#i', $decoded)) {
            return $decoded;
        }

        // Protocol-relative
        if (\str_starts_with($decoded, '//')) {
            return $decoded;
        }

        // Relative path: only reject if a scheme-like colon appears in the
        // path portion before any / or ? (colons in query strings are fine)
        $schemeEnd = \strcspn($decoded, '/?');
        if (!\str_contains(\substr($decoded, 0, $schemeEnd), ':')) {
            return $decoded;
        }

        return '';
    }
}
