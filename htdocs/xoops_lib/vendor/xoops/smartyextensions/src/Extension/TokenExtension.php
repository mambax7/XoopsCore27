<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Extension;

use Xoops\SmartyExtensions\AbstractExtension;

/**
 * AJAX request-token exposure for the XOOPS fragment client layer.
 *
 * Emits a `<meta name="xoops-token">` tag once in the theme `<head>` so
 * `xoops-fragments.js` (and Alpine AJAX/htmx handlers) can read the token
 * and attach it as the `X-XOOPS-TOKEN` header on mutating fragment requests.
 * Retry forms should still use `form_open`'s injected `getTokenHTML()`
 * hidden input, not this bare token — this plugin is for the AJAX header
 * path only.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
final class TokenExtension extends AbstractExtension
{
    public function __construct(private readonly ?\XoopsSecurity $security = null)
    {
    }

    public function getFunctions(): array
    {
        return [
            'xoToken' => $this->xoToken(...),
        ];
    }

    /**
     * Renders a meta tag exposing the current XOOPS request token.
     *
     * Usage: <{xoToken}>
     *
     * @param array<string, mixed> $params
     */
    public function xoToken(array $params, object $template): string
    {
        if ($this->security === null) {
            return '';
        }

        // \XoopsSecurity::createToken() is untyped in core, so guard the return
        // rather than casting blind.
        $token = $this->security->createToken();

        if (!\is_scalar($token)) {
            return '';
        }

        $escaped = \htmlspecialchars((string) $token, ENT_QUOTES, 'UTF-8');

        return '<meta name="xoops-token" content="' . $escaped . '">';
    }
}
