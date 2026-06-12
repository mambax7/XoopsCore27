<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Extension;

use Xoops\SmartyExtensions\AbstractExtension;

/**
 * Navigation, URL, and UI-component Smarty functions and modifiers.
 *
 * Functions use XOOPS_URL when available; modifiers are pure PHP.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
final class NavigationExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            'generate_url' => $this->generateUrl(...),
            'generate_canonical_url' => $this->generateCanonicalUrl(...),
            'url_segment' => $this->urlSegment(...),
            'social_share' => $this->socialShare(...),
            'render_breadcrumbs' => $this->renderBreadcrumbs(...),
            'render_pagination' => $this->renderPagination(...),
            'render_qr_code' => $this->renderQrCode(...),
            'render_alert' => $this->renderAlert(...),
        ];
    }

    public function getModifiers(): array
    {
        return [
            'parse_url' => $this->parseUrl(...),
            'strip_protocol' => $this->stripProtocol(...),
            'slugify' => $this->slugify(...),
            'youtube_id' => $this->youtubeId(...),
            'linkify' => $this->linkify(...),
        ];
    }

    // ── Functions ────────────────────────────────────────────

    /**
     * Generate a URL from a route and query parameters.
     *
     * @param array  $params   ['route' => string, 'params' => array, 'assign' => string]
     * @param object $template Smarty_Internal_Template|Smarty\Template
     */
    public function generateUrl(array $params, object $template): string
    {
        $route = $params['route'] ?? '';
        $queryParams = $params['params'] ?? [];

        $url = $route;
        if (!empty($queryParams) && \is_array($queryParams)) {
            $url .= '?' . \http_build_query($queryParams);
        }

        if (!empty($params['assign'])) {
            // assign stores the raw URL; callers escape when interpolating into HTML
            $template->assign($params['assign'], $url);
            return '';
        }

        return \htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate a canonical URL using XOOPS_URL when available.
     *
     * CRITICAL: assign stores the raw URL; return is htmlspecialchars-escaped.
     *
     * @param array  $params   ['path' => string, 'assign' => string]
     * @param object $template Smarty_Internal_Template|Smarty\Template
     */
    public function generateCanonicalUrl(array $params, object $template): string
    {
        $path = \ltrim($params['path'] ?? '', '/');

        // Use XOOPS_URL if available; refuse to build a canonical URL from
        // untrusted HTTP_HOST to prevent host-header poisoning.
        if (\defined('XOOPS_URL')) {
            $baseUrl = \rtrim(XOOPS_URL, '/');
        } else {
            return '';
        }

        $result = $baseUrl . '/' . $path;

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return \htmlspecialchars($result, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Return a specific segment of the current request URI.
     *
     * @param array  $params   ['index' => int, 'assign' => string]
     * @param object $template Smarty_Internal_Template|Smarty\Template
     */
    public function urlSegment(array $params, object $template): string
    {
        $index = (int) ($params['index'] ?? 0);
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = \parse_url($uri, PHP_URL_PATH) ?? '';
        $segments = \explode('/', \trim($path, '/'));
        $result = $segments[$index] ?? '';

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return \htmlspecialchars($result, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate social media share links or a share button bar.
     *
     * @param array  $params   ['url' => string, 'title' => string, 'platform' => string, 'assign' => string]
     * @param object $template Smarty_Internal_Template|Smarty\Template
     */
    public function socialShare(array $params, object $template): string
    {
        $url = \urlencode($params['url'] ?? '');
        $title = \urlencode($params['title'] ?? '');
        $platform = $params['platform'] ?? '';

        if ($url === '') {
            return '';
        }

        $platforms = [
            'twitter'  => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title,
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
            'linkedin' => 'https://www.linkedin.com/shareArticle?mini=true&url=' . $url . '&title=' . $title,
            'reddit'   => 'https://www.reddit.com/submit?url=' . $url . '&title=' . $title,
            'email'    => 'mailto:?subject=' . $title . '&body=' . $url,
        ];

        // Single platform link
        if ($platform !== '') {
            $result = $platforms[$platform] ?? '';

            if (!empty($params['assign'])) {
                $template->assign($params['assign'], $result);
                return '';
            }

            return \htmlspecialchars($result, ENT_QUOTES, 'UTF-8');
        }

        // Full share bar
        $labels = [
            'twitter'  => 'Twitter',
            'facebook' => 'Facebook',
            'linkedin' => 'LinkedIn',
            'reddit'   => 'Reddit',
            'email'    => 'Email',
        ];

        $html = '<div class="social-share">';
        foreach ($platforms as $name => $link) {
            $safeLink = \htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
            $target = $name === 'email' ? '' : ' target="_blank" rel="noopener noreferrer"';
            $html .= '<a href="' . $safeLink . '"' . $target . ' class="share-btn share-' . $name . '">' . $labels[$name] . '</a> ';
        }
        $html .= '</div>';

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $html);
            return '';
        }

        return $html;
    }

    /**
     * Render Bootstrap 5 breadcrumb navigation.
     *
     * @param array  $params   ['items' => array, 'assign' => string]
     * @param object $template Smarty_Internal_Template|Smarty\Template
     */
    public function renderBreadcrumbs(array $params, object $template): string
    {
        $items = $params['items'] ?? [];

        if (empty($items)) {
            return '';
        }

        $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
        $keys = \array_keys($items);
        $lastKey = \end($keys);

        foreach ($items as $url => $label) {
            $safeLabel = \htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
            if ($url === $lastKey) {
                $html .= '<li class="breadcrumb-item active" aria-current="page">' . $safeLabel . '</li>';
            } else {
                $safeUrl = \htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
                $html .= '<li class="breadcrumb-item"><a href="' . $safeUrl . '">' . $safeLabel . '</a></li>';
            }
        }

        $html .= '</ol></nav>';

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $html);
            return '';
        }

        return $html;
    }

    /**
     * Render Bootstrap 5 pagination controls.
     *
     * @param array  $params   ['totalPages' => int, 'currentPage' => int, 'urlPattern' => string, 'assign' => string]
     * @param object $template Smarty_Internal_Template|Smarty\Template
     */
    public function renderPagination(array $params, object $template): string
    {
        $totalPages = (int) ($params['totalPages'] ?? 1);
        $currentPage = (int) ($params['currentPage'] ?? 1);
        $urlPattern = $params['urlPattern'] ?? '?page={page}';

        if ($totalPages <= 1) {
            return '';
        }

        $html = '<nav aria-label="Page navigation"><ul class="pagination">';

        // Previous button
        if ($currentPage > 1) {
            $prevUrl = \htmlspecialchars(\str_replace('{page}', (string) ($currentPage - 1), $urlPattern), ENT_QUOTES, 'UTF-8');
            $html .= '<li class="page-item"><a class="page-link" href="' . $prevUrl . '" aria-label="Previous">&laquo;</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
        }

        // Page numbers
        for ($i = 1; $i <= $totalPages; $i++) {
            $url = \htmlspecialchars(\str_replace('{page}', (string) $i, $urlPattern), ENT_QUOTES, 'UTF-8');
            $activeClass = $i === $currentPage ? ' active' : '';
            $ariaCurrent = $i === $currentPage ? ' aria-current="page"' : '';
            $html .= '<li class="page-item' . $activeClass . '"' . $ariaCurrent . '><a class="page-link" href="' . $url . '">' . $i . '</a></li>';
        }

        // Next button
        if ($currentPage < $totalPages) {
            $nextUrl = \htmlspecialchars(\str_replace('{page}', (string) ($currentPage + 1), $urlPattern), ENT_QUOTES, 'UTF-8');
            $html .= '<li class="page-item"><a class="page-link" href="' . $nextUrl . '" aria-label="Next">&raquo;</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
        }

        $html .= '</ul></nav>';

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $html);
            return '';
        }

        return $html;
    }

    /**
     * Render a QR code image tag using the goqr.me API.
     *
     * @param array  $params   ['text' => string, 'size' => int, 'assign' => string]
     * @param object $template Smarty_Internal_Template|Smarty\Template
     */
    public function renderQrCode(array $params, object $template): string
    {
        $text = $params['text'] ?? '';
        $size = (int) ($params['size'] ?? 150);

        if ($text === '') {
            return '';
        }

        $encodedText = \urlencode($text);
        $html = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . $encodedText . '" alt="QR Code" width="' . $size . '" height="' . $size . '" loading="lazy">';

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $html);
            return '';
        }

        return $html;
    }

    /**
     * Render a Bootstrap 5 alert message.
     *
     * @param array  $params   ['message' => string, 'type' => string, 'dismissible' => bool, 'assign' => string]
     * @param object $template Smarty_Internal_Template|Smarty\Template
     */
    public function renderAlert(array $params, object $template): string
    {
        $message = $params['message'] ?? '';
        $type = $params['type'] ?? 'info';
        $dismissible = !empty($params['dismissible']);

        if ($message === '') {
            return '';
        }

        $safeType = \htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
        $safeMessage = \htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        $classes = 'alert alert-' . $safeType;
        $extra = '';

        if ($dismissible) {
            $classes .= ' alert-dismissible fade show';
            $extra = '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        }

        $html = '<div class="' . $classes . '" role="alert">' . $safeMessage . $extra . '</div>';

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $html);
            return '';
        }

        return $html;
    }

    // ── Modifiers ───────────────────────────────────────────

    /**
     * Parse a URL and return its components as an associative array.
     *
     * @return array<string, string|int>|false
     */
    public function parseUrl(string $url): array|false
    {
        return \parse_url($url);
    }

    /**
     * Remove the protocol (http:// or https://) from a URL.
     */
    public function stripProtocol(string $url): string
    {
        return \preg_replace('#^https?://#i', '', $url);
    }

    /**
     * Convert a string to a URL-friendly slug.
     */
    public function slugify(string $text): string
    {
        $text = \preg_replace('~[^\pL\d]+~u', '-', $text);
        if (\function_exists('transliterator_transliterate')) {
            $text = \transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        } else {
            $text = \iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            $text = \strtolower($text);
        }
        $text = \preg_replace('~[^-\w]+~', '', $text);
        $text = \trim($text, '-');
        $text = \preg_replace('~-+~', '-', $text);

        return $text;
    }

    /**
     * Extract the video ID from a YouTube URL.
     *
     * Supports: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID, youtube.com/shorts/ID
     */
    public function youtubeId(string $url): string
    {
        $pattern = '#(?:youtube\.com/(?:watch\?v=|embed/|v/|shorts/)|youtu\.be/)([\w\-]{11})#i';

        if (\preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Convert URLs in text to clickable links.
     */
    public function linkify(string $text): string
    {
        return \preg_replace(
            '~(https?://[^\s<>"\']+)~i',
            '<a href="$1" target="_blank" rel="noopener noreferrer nofollow">$1</a>',
            $text
        );
    }
}
