<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Extension;

use Xoops\SmartyExtensions\AbstractExtension;

/**
 * Data processing Smarty functions and modifiers.
 *
 * Pure PHP — no XOOPS dependencies.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
final class DataExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            'array_to_csv'         => $this->arrayToCsv(...),
            'base64_encode_file'   => $this->base64EncodeFile(...),
            'embed_pdf'            => $this->embedPdf(...),
            'generate_xml_sitemap' => $this->generateXmlSitemap(...),
            'generate_meta_tags'   => $this->generateMetaTags(...),
            'get_referrer'         => $this->getReferrer(...),
            'get_session_data'     => $this->getSessionData(...),
        ];
    }

    public function getModifiers(): array
    {
        return [
            'array_filter'       => $this->arrayFilter(...),
            'array_sort'         => $this->arraySort(...),
            'pretty_print_json'  => $this->prettyPrintJson(...),
            'get_file_size'      => $this->getFileSize(...),
            'get_mime_type'      => $this->getMimeType(...),
            'is_image'           => $this->isImage(...),
            'strip_html_comments' => $this->stripHtmlComments(...),
        ];
    }

    // ---------------------------------------------------------------
    //  Functions
    // ---------------------------------------------------------------

    public function arrayToCsv(array $params, object $template): string
    {
        $array = $params['array'] ?? [];
        $separator = $params['separator'] ?? ',';

        if (empty($array)) {
            return '';
        }

        $output = \fopen('php://temp', 'r+');
        foreach ($array as $row) {
            \fputcsv($output, (array) $row, $separator, '"', '\\');
        }
        \rewind($output);
        $result = \stream_get_contents($output);
        \fclose($output);

        if (!empty($params['assign'])) {
            // assign stores raw CSV; callers escape when embedding in HTML
            $template->assign($params['assign'], $result);
            return '';
        }

        return \htmlspecialchars($result, ENT_QUOTES, 'UTF-8');
    }

    public function base64EncodeFile(array $params, object $template): string
    {
        $filePath = $params['path'] ?? $params['filePath'] ?? '';

        if ($filePath === '' || !\is_file($filePath) || !\is_readable($filePath)) {
            return '';
        }

        $realPath = \realpath($filePath);
        if ($realPath === false) {
            return '';
        }

        // Enforce web root boundary: only allow files under XOOPS_ROOT_PATH
        // or the document root. Fail closed if neither is available.
        $webRoot = \defined('XOOPS_ROOT_PATH') ? (string) \constant('XOOPS_ROOT_PATH') : ($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($webRoot === '') {
            return '';
        }
        $resolvedRoot = \realpath($webRoot);
        if ($resolvedRoot === false || !\str_starts_with($realPath, $resolvedRoot . \DIRECTORY_SEPARATOR)) {
            return '';
        }

        $result = \base64_encode(\file_get_contents($realPath));

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return $result;
    }

    public function embedPdf(array $params, object $template): string
    {
        $url = $params['url'] ?? '';
        $width = $params['width'] ?? '100%';
        $height = $params['height'] ?? '600';

        if ($url === '') {
            return '';
        }

        $safeUrl = \htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $safeWidth = \htmlspecialchars($width, ENT_QUOTES, 'UTF-8');
        $safeHeight = \htmlspecialchars($height, ENT_QUOTES, 'UTF-8');

        $html = '<iframe src="' . $safeUrl . '" width="' . $safeWidth . '" height="' . $safeHeight . '" style="border:none;" loading="lazy" title="PDF Document"></iframe>';

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $html);
            return '';
        }

        return $html;
    }

    public function generateXmlSitemap(array $params, object $template): string
    {
        $pages = $params['pages'] ?? [];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($pages as $page) {
            $loc = \htmlspecialchars($page['url'] ?? '', ENT_XML1, 'UTF-8');
            $xml .= "  <url>\n    <loc>{$loc}</loc>\n";

            if (!empty($page['lastmod'])) {
                $xml .= '    <lastmod>' . \htmlspecialchars($page['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
            }
            if (!empty($page['changefreq'])) {
                $xml .= '    <changefreq>' . \htmlspecialchars($page['changefreq'], ENT_XML1, 'UTF-8') . "</changefreq>\n";
            }
            if (isset($page['priority'])) {
                $xml .= '    <priority>' . \htmlspecialchars((string) $page['priority'], ENT_XML1, 'UTF-8') . "</priority>\n";
            }

            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $xml);
            return '';
        }

        return $xml;
    }

    public function generateMetaTags(array $params, object $template): string
    {
        $config = $params['config'] ?? [];

        if (empty($config)) {
            return '';
        }

        $html = '';
        foreach ($config as $name => $content) {
            $safeName = \htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');
            $safeContent = \htmlspecialchars((string) $content, ENT_QUOTES, 'UTF-8');
            $html .= '<meta name="' . $safeName . '" content="' . $safeContent . '">' . "\n";
        }

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $html);
            return '';
        }

        return $html;
    }

    public function getReferrer(array $params, object $template): string
    {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';

        if (!empty($params['assign'])) {
            // assign stores raw value; callers escape when interpolating into HTML
            $template->assign($params['assign'], $referrer);
            return '';
        }

        return \htmlspecialchars($referrer, ENT_QUOTES, 'UTF-8');
    }

    public function getSessionData(array $params, object $template): string
    {
        $key = $params['key'] ?? '';
        $result = $_SESSION[$key] ?? null;

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return (string) ($result ?? '');
    }

    // ---------------------------------------------------------------
    //  Modifiers
    // ---------------------------------------------------------------

    public function arrayFilter(array $array, string $key, mixed $value): array
    {
        return \array_values(\array_filter($array, static function ($item) use ($key, $value): bool {
            return isset($item[$key]) && $item[$key] == $value;
        }));
    }

    public function arraySort(array $array, string $key = '', string $order = 'asc'): array
    {
        $sorted = $array;

        if ($key === '') {
            // Simple value sort
            $order === 'desc' ? \arsort($sorted) : \asort($sorted);
        } else {
            // Sort array of arrays by key
            $direction = \strtolower($order) === 'desc' ? SORT_DESC : SORT_ASC;
            $column = \array_column($sorted, $key);
            if (!empty($column)) {
                \array_multisort($column, $direction, $sorted);
            }
        }

        return $sorted;
    }

    public function prettyPrintJson(mixed $data): string
    {
        if (\is_string($data)) {
            $decoded = \json_decode($data);
            if (\json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            }
        }

        $result = \json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $result !== false ? $result : '{}';
    }

    public function getFileSize(string $file): string
    {
        if ($file === '' || !\file_exists($file)) {
            return '';
        }

        $size = \filesize($file);
        if ($size === false) {
            return '';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($size >= 1024 && $i < \count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return \round($size, 2) . ' ' . $units[$i];
    }

    public function getMimeType(string $file): string
    {
        if ($file === '' || !\file_exists($file)) {
            return '';
        }

        $mimeType = \mime_content_type($file);

        return $mimeType !== false ? $mimeType : '';
    }

    public function isImage(string $file): bool
    {
        $ext = \strtolower(\pathinfo($file, PATHINFO_EXTENSION));

        return \in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'bmp', 'ico'], true);
    }

    public function stripHtmlComments(string $html): string
    {
        return \preg_replace('/<!--.*?-->/s', '', $html);
    }
}
