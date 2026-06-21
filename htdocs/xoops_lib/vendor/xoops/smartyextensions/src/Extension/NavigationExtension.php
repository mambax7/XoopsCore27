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
    /**
     * External QR-code service used only as an explicit opt-in fallback
     * (render_qr_code externalFallback=true) when local generation is unavailable.
     */
    private const QR_FALLBACK_API = 'https://api.qrserver.com/v1/create-qr-code/';

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
     * Accepts either input shape (S2):
     *  - a `url => label` map (the original form), or
     *  - the XOOPS-native list of records `[ ['link'=>url, 'title'=>label], … ]`
     *    (also accepts `url`/`label` keys), as produced by `$xoBreadCrumb`.
     * The last crumb — and any crumb with an empty link — is rendered as the
     * non-linked active page.
     *
     * @param array  $params   ['items' => array, 'assign' => string]
     * @param object $template Smarty_Internal_Template|Smarty\Template
     */
    public function renderBreadcrumbs(array $params, object $template): string
    {
        $rawItems = $params['items'] ?? [];
        $crumbs = self::normalizeBreadcrumbs(\is_array($rawItems) ? $rawItems : []);

        if ($crumbs === []) {
            return '';
        }

        $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
        $lastIndex = \count($crumbs) - 1;

        foreach ($crumbs as $i => $crumb) {
            $safeLabel = \htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8');

            if ($i === $lastIndex || $crumb['url'] === '') {
                $html .= '<li class="breadcrumb-item active" aria-current="page">' . $safeLabel . '</li>';
            } else {
                $safeUrl = \htmlspecialchars($crumb['url'], ENT_QUOTES, 'UTF-8');
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
     * Normalize either breadcrumb input shape to a list of ['label'=>, 'url'=>] (S2).
     *
     * @param array<mixed> $items
     * @return list<array{label: string, url: string}>
     */
    private static function normalizeBreadcrumbs(array $items): array
    {
        if ($items === []) {
            return [];
        }

        // A list (sequential integer keys) is the XOOPS-native record shape; a
        // url => label map is associative. Non-array records are skipped.
        if (\array_is_list($items)) {
            $out = [];
            foreach ($items as $item) {
                if (!\is_array($item)) {
                    continue;
                }
                $out[] = [
                    'label' => (string) ($item['title'] ?? $item['label'] ?? ''),
                    'url'   => (string) ($item['link'] ?? $item['url'] ?? ''),
                ];
            }

            return $out;
        }

        // Original shape: url => label map (defensively skip any array values).
        $out = [];
        foreach ($items as $url => $label) {
            if (\is_array($label)) {
                continue;
            }
            $out[] = ['label' => (string) $label, 'url' => (string) $url];
        }

        return $out;
    }

    /**
     * Render Bootstrap 5 pagination controls.
     *
     * Two input modes (S1 — XOOPS compatibility):
     *  - Data-driven (preferred for XOOPS): pass XoopsPageNav's native values
     *    ['total' => int, 'limit' => int, 'start' => int(offset)]. totalPages and
     *    the current page are computed from them. Use the {start} placeholder in
     *    urlPattern for offset-based links (how XoopsPageNav paginates), e.g.
     *    urlPattern="index.php?start={start}".
     *  - Page-based (BC): ['totalPages' => int, 'currentPage' => int] with a
     *    {page} placeholder, e.g. urlPattern="?page={page}".
     *
     * @param array  $params   ['total','limit','start'] or ['totalPages','currentPage'] + ['urlPattern','assign']
     * @param object $template Smarty_Internal_Template|Smarty\Template
     */
    public function renderPagination(array $params, object $template): string
    {
        $limitForUrl = 1;

        if (isset($params['total'], $params['limit'])) {
            // XoopsPageNav-style: total rows + per-page limit (+ start offset).
            $total = \max(0, (int) $params['total']);
            $limitForUrl = \max(1, (int) $params['limit']);
            $start = \max(0, (int) ($params['start'] ?? 0));
            $totalPages = (int) \ceil($total / $limitForUrl);
            $currentPage = (int) \floor($start / $limitForUrl) + 1;
        } else {
            $totalPages = (int) ($params['totalPages'] ?? 1);
            $currentPage = (int) ($params['currentPage'] ?? 1);
        }

        $urlPattern = (string) ($params['urlPattern'] ?? '?page={page}');
        $window = \max(0, (int) ($params['window'] ?? 0));
        $totalPages = \max(1, $totalPages);
        $currentPage = \max(1, \min($currentPage, $totalPages));

        if ($totalPages <= 1) {
            // Clear any assigned variable so a one-page result doesn't leave stale
            // pagination from a previous iteration in loops/blocks.
            if (!empty($params['assign'])) {
                $template->assign($params['assign'], '');
            }

            return '';
        }

        // Build a link for a 1-based page number, supporting both {page} and {start}.
        $makeUrl = static function (int $pageNum) use ($urlPattern, $limitForUrl): string {
            $replaced = \str_replace(
                ['{page}', '{start}'],
                [(string) $pageNum, (string) (($pageNum - 1) * $limitForUrl)],
                $urlPattern,
            );

            return \htmlspecialchars($replaced, ENT_QUOTES, 'UTF-8');
        };

        $html = '<nav aria-label="Page navigation"><ul class="pagination">';

        // Previous button
        if ($currentPage > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $makeUrl($currentPage - 1) . '" aria-label="Previous">&laquo;</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
        }

        // Page numbers (optionally windowed with first/last + ellipses when $window > 0)
        foreach (self::pageList($currentPage, $totalPages, $window) as $i) {
            if ($i === 0) {
                $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                continue;
            }

            $activeClass = $i === $currentPage ? ' active' : '';
            $ariaCurrent = $i === $currentPage ? ' aria-current="page"' : '';
            $html .= '<li class="page-item' . $activeClass . '"' . $ariaCurrent . '><a class="page-link" href="' . $makeUrl($i) . '">' . $i . '</a></li>';
        }

        // Next button
        if ($currentPage < $totalPages) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $makeUrl($currentPage + 1) . '" aria-label="Next">&raquo;</a></li>';
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
     * Build the list of page numbers to render.
     *
     * window <= 0 (default): every page 1..total (unchanged behavior).
     * window > 0: first + last + current ± window, with 0 used as an ellipsis
     * marker — so large XOOPS result sets paginate like XoopsPageNav instead of
     * dumping hundreds of links.
     *
     * @return array<int> page numbers, with 0 marking an ellipsis
     */
    private static function pageList(int $current, int $total, int $window): array
    {
        if ($window <= 0 || $total <= ($window * 2 + 3)) {
            return \range(1, $total);
        }

        $pages = [1];
        $from = \max(2, $current - $window);
        $to = \min($total - 1, $current + $window);

        // Avoid a single-page gap rendered as an ellipsis ("1 … 3" reads worse than "1 2 3").
        if ($from === 3) {
            $from = 2;
        }
        if ($to === $total - 2) {
            $to = $total - 1;
        }

        if ($from > 2) {
            $pages[] = 0; // leading ellipsis
        }

        for ($i = $from; $i <= $to; $i++) {
            $pages[] = $i;
        }

        if ($to < $total - 1) {
            $pages[] = 0; // trailing ellipsis
        }

        $pages[] = $total;

        return $pages;
    }

    /**
     * Render a QR code image tag (S5).
     *
     * Generates the QR code LOCALLY via chillerlan/php-qrcode (an inline SVG
     * data-URI — no external request, no privacy leak, works without GD). The
     * external service is NOT used by default: pass externalFallback=true to allow
     * it only when local generation is unavailable (otherwise an unavailable local
     * generator yields no image rather than silently leaking the payload off-site).
     *
     * @param array  $params   ['text' => string, 'size' => int, 'externalFallback' => bool, 'assign' => string]
     * @param object $template Smarty_Internal_Template|Smarty\Template
     */
    public function renderQrCode(array $params, object $template): string
    {
        $text = (string) ($params['text'] ?? '');
        // Clamp to a scannable-yet-sane pixel range.
        $size = \max(32, \min(1024, (int) ($params['size'] ?? 150)));

        if ($text === '') {
            return '';
        }

        $src = self::localQrDataUri($text, $size);

        if ($src === null && !empty($params['externalFallback'])) {
            $src = self::QR_FALLBACK_API . '?size=' . $size . 'x' . $size . '&data=' . \rawurlencode($text);
        }

        if ($src === null) {
            return '';
        }

        $html = '<img src="' . \htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="QR Code" width="' . $size . '" height="' . $size . '" loading="lazy" decoding="async">';

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $html);
            return '';
        }

        return $html;
    }

    /**
     * Generate a local, dependency-light inline-SVG data-URI QR code.
     *
     * @return string|null data-URI on success, or null when chillerlan/php-qrcode is unavailable.
     */
    private static function localQrDataUri(string $text, int $size): ?string
    {
        // chillerlan/php-qrcode is a hard dependency, normally provided by the host
        // project's autoloader. As a fallback for bundled/zip distributions, load the
        // package's own vendor/ autoloader at most once if the class isn't visible yet.
        static $autoloadAttempted = false;

        if (!$autoloadAttempted && !\class_exists(\chillerlan\QRCode\QRCode::class)) {
            $autoloadAttempted = true;
            $bundled = \dirname(__DIR__, 2) . '/vendor/autoload.php';

            if (\is_file($bundled)) {
                require_once $bundled;
            }
        }

        if (!\class_exists(\chillerlan\QRCode\QRCode::class) || !\class_exists(\chillerlan\QRCode\QROptions::class)) {
            return null;
        }

        try {
            // chillerlan/php-qrcode v5/v6 API: SVG markup output, base64 data-URI.
            $options = new \chillerlan\QRCode\QROptions([
                'outputInterface'      => \chillerlan\QRCode\Output\QRMarkupSVG::class,
                'outputBase64'         => true,
                'eccLevel'             => \chillerlan\QRCode\Common\EccLevel::M,
                'scale'                => \max(3, \intdiv($size, 25)),
                'svgUseFillAttributes' => false,
            ]);

            // With outputBase64 = true, render() returns a data:image/svg+xml;base64,... URI.
            $dataUri = (new \chillerlan\QRCode\QRCode($options))->render($text);

            return \is_string($dataUri) && $dataUri !== '' ? $dataUri : null;
        } catch (\Throwable $e) {
            return null;
        }
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
