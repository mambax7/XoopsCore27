<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Extension;

use Xoops\SmartyExtensions\AbstractExtension;

/**
 * Security-related Smarty functions, modifiers, and block handlers.
 *
 * Provides CSRF token generation/validation, permission checks,
 * string/URL/filename sanitization, email masking, text obfuscation, and hashing.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
final class SecurityExtension extends AbstractExtension
{
    public function __construct(
        private readonly ?\XoopsSecurity $security = null,
        private readonly ?\XoopsGroupPermHandler $grouppermHandler = null,
    ) {
    }

    /** @return array<string, callable> */
    public function getFunctions(): array
    {
        return [
            'generate_csrf_token' => $this->generateCsrfToken(...),
            'validate_csrf_token' => $this->validateCsrfToken(...),
            'has_user_permission' => $this->hasUserPermission(...),
            'is_user_logged_in' => $this->isUserLoggedIn(...),
            'user_has_role' => $this->userHasRole(...),
        ];
    }

    /** @return array<string, callable> */
    public function getModifiers(): array
    {
        return [
            'sanitize_string' => $this->sanitizeString(...),
            'sanitize_url' => $this->sanitizeUrl(...),
            'sanitize_filename' => $this->sanitizeFilename(...),
            'sanitize_string_for_xml' => $this->sanitizeStringForXml(...),
            'mask_email' => $this->maskEmail(...),
            'obfuscate_text' => $this->obfuscateText(...),
            'hash_string' => $this->hashString(...),
        ];
    }

    /** @return array<string, callable> */
    public function getBlockHandlers(): array
    {
        return [
            'xo_permission' => $this->xoPermission(...),
        ];
    }

    // ──────────────────────────────────────────────
    // Functions
    // ──────────────────────────────────────────────

    /**
     * Generate a CSRF token using the XOOPS security system.
     */
    public function generateCsrfToken(array $params, object $template): string
    {
        if ($this->security === null) {
            return '';
        }
        $html = $this->security->getTokenHTML();

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $html);
            return '';
        }

        return $html;
    }

    /**
     * Validate a CSRF token using the XOOPS security system.
     */
    public function validateCsrfToken(array $params, object $template): string
    {
        if ($this->security === null) {
            return '';
        }
        $result = $this->security->check();

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return $result ? '1' : '';
    }

    /**
     * Check if the current user has a specific permission.
     *
     * Uses the injected XoopsGroupPermHandler to check group permissions
     * via the XOOPS permission API, falling back to admin check only.
     */
    public function hasUserPermission(array $params, object $template): string
    {
        global $xoopsUser;

        $permission = $params['permission'] ?? '';
        $moduleId = (int) ($params['module_id'] ?? 1);
        $itemId = (int) ($params['item_id'] ?? 0);
        $result = false;

        if ($xoopsUser instanceof \XoopsUser && $permission !== '') {
            if ($xoopsUser->isAdmin($moduleId)) {
                $result = true;
            } elseif ($this->grouppermHandler !== null) {
                $groups = $xoopsUser->getGroups();
                $result = $this->grouppermHandler->checkRight($permission, $itemId, $groups, $moduleId);
            }
        }

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return $result ? '1' : '';
    }

    /**
     * Check if a XOOPS user is currently logged in.
     */
    public function isUserLoggedIn(array $params, object $template): string
    {
        global $xoopsUser;

        $result = isset($xoopsUser) && $xoopsUser instanceof \XoopsUser;

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return $result ? '1' : '';
    }

    /**
     * Check if the current user belongs to a specific group (by group ID).
     */
    public function userHasRole(array $params, object $template): string
    {
        global $xoopsUser;

        $role = $params['role'] ?? '';
        $result = false;

        if ($xoopsUser instanceof \XoopsUser && $role !== '') {
            $groups = $xoopsUser->getGroups();
            $result = \in_array($role, $groups, false);
        }

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return $result ? '1' : '';
    }

    // ──────────────────────────────────────────────
    // Modifiers
    // ──────────────────────────────────────────────

    /**
     * Escape a string for safe HTML output (XSS protection).
     */
    public function sanitizeString(string $string): string
    {
        return \htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize a URL, removing illegal characters and blocking unsafe schemes.
     *
     * Decodes HTML entities before checking the scheme so that entity-encoded
     * bypass attempts like "javascript&#58;alert(1)" are correctly blocked.
     */
    public function sanitizeUrl(string $url): string
    {
        $sanitized = \filter_var($url, FILTER_SANITIZE_URL);

        // Decode HTML entities so encoded colons/schemes cannot bypass checks
        $decoded = \html_entity_decode($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Only allow safe schemes (mailto: uses : not ://)
        if (\preg_match('#^(https?|ftp)://#i', $decoded) || \preg_match('#^mailto:#i', $decoded) || \str_starts_with($decoded, '/') || \str_starts_with($decoded, '#')) {
            return $decoded;
        }

        // Relative URLs without scheme are OK
        if (!\str_contains($decoded, ':')) {
            return $decoded;
        }

        return '';
    }

    /**
     * Remove unsafe characters from a filename.
     *
     * - Strips directory components via basename() first (eliminates traversal)
     * - Removes all characters outside the safe allowlist
     * - Trims leading dots to prevent hidden-file names (.htaccess, etc.)
     */
    public function sanitizeFilename(string $filename): string
    {
        // Strip any directory component before character filtering
        $filename = \basename($filename);

        // Keep only safe characters
        $filename = (string) \preg_replace('/[^A-Za-z0-9\-_.]/', '', $filename);

        // Trim leading dots (prevents .htaccess-style hidden files from surviving)
        return \ltrim($filename, '.');
    }

    /**
     * Escape a string for safe inclusion in XML content.
     */
    public function sanitizeStringForXml(string $string): string
    {
        return \htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Partially hide an email address for privacy.
     */
    public function maskEmail(string $email): string
    {
        $parts = \explode('@', $email, 2);
        if (\count($parts) !== 2) {
            return $email;
        }

        $local = $parts[0];
        $domain = $parts[1];

        $visibleChars = \max(1, (int) \ceil(\strlen($local) / 3));
        $masked = \substr($local, 0, $visibleChars) . \str_repeat('*', \max(3, \strlen($local) - $visibleChars));

        return $masked . '@' . $domain;
    }

    /**
     * Convert characters to HTML entities to defeat email harvesters.
     */
    public function obfuscateText(string $string): string
    {
        $result = '';
        $len = \strlen($string);
        for ($i = 0; $i < $len; $i++) {
            $result .= '&#' . \ord($string[$i]) . ';';
        }

        return $result;
    }

    /**
     * Hash a string using the specified algorithm.
     */
    public function hashString(string $string, string $algo = 'sha256'): string
    {
        if (!\in_array($algo, \hash_algos(), true)) {
            return '';
        }

        return \hash($algo, $string);
    }

    // ──────────────────────────────────────────────
    // Block handlers
    // ──────────────────────────────────────────────

    /**
     * Conditionally render content based on user permissions or group membership.
     */
    public function xoPermission(array $params, ?string $content, object $template, bool &$repeat): string
    {
        // Only process on the closing tag
        if ($repeat || $content === null) {
            return '';
        }

        global $xoopsUser;

        // Check logged-in requirement
        if (!empty($params['logged_in'])) {
            if (!($xoopsUser instanceof \XoopsUser)) {
                return '';
            }
        }

        // Check permission requirement
        if (isset($params['require']) && $params['require'] !== '') {
            if (!($xoopsUser instanceof \XoopsUser)) {
                return '';
            }
            $moduleId = (int) ($params['module_id'] ?? 1);
            $itemId = (int) ($params['item_id'] ?? 0);
            if (!$xoopsUser->isAdmin($moduleId)) {
                if ($this->grouppermHandler === null) {
                    return '';
                }
                $groups = $xoopsUser->getGroups();
                if (!$this->grouppermHandler->checkRight($params['require'], $itemId, $groups, $moduleId)) {
                    return '';
                }
            }
        }

        // Check group requirement
        if (isset($params['group']) && $params['group'] !== '') {
            if (!($xoopsUser instanceof \XoopsUser)) {
                return '';
            }
            $groups = $xoopsUser->getGroups();
            if (!\in_array($params['group'], $groups, false)) {
                return '';
            }
        }

        return $content;
    }
}
