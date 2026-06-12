<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Extension;

use Xoops\SmartyExtensions\AbstractExtension;

/**
 * Ray debug Smarty plugins — send template data to the Ray desktop debugger.
 *
 * All methods silently no-op when the Debugbar RayLogger is unavailable or
 * disabled, or when the ray() helper function is not installed. This allows
 * templates to contain Ray tags without any runtime penalty in production.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
final class RayDebugExtension extends AbstractExtension
{
    private ?bool $enabled = null;

    /** @return array<string, callable> */
    public function getFunctions(): array
    {
        return [
            'ray' => $this->ray(...),
            'ray_context' => $this->rayContext(...),
            'ray_dump' => $this->rayDump(...),
            'ray_table' => $this->rayTable(...),
        ];
    }

    /** @return array<string, callable> */
    public function getModifiers(): array
    {
        return [
            'ray' => $this->rayModifier(...),
        ];
    }

    // ──────────────────────────────────────────────
    // Functions
    // ──────────────────────────────────────────────

    /**
     * Send a value or message to Ray with optional label and color.
     *
     * Usage: <{ray value=$variable}>
     *        <{ray msg="Reached this point" color="red"}>
     */
    public function ray(array $params, object $template): string
    {
        if (!$this->isRayEnabled()) {
            return '';
        }

        $value = $params['value'] ?? null;
        $msg = $params['msg'] ?? null;
        $data = $value ?? $msg;

        if ($data === null) {
            return '';
        }

        $r = ray($data);

        if (isset($params['label'])) {
            $r->label($params['label']);
        }
        if (isset($params['color'])) {
            $r->color($params['color']);
        }

        return '';
    }

    /**
     * Dump all template variables to Ray as a sorted table.
     *
     * Usage: <{ray_context}>
     *        <{ray_context label="Before Loop" exclude="xoops_*,smarty"}>
     */
    public function rayContext(array $params, object $template): string
    {
        if (!$this->isRayEnabled()) {
            return '';
        }

        $label = $params['label']
            ?? $this->lang('_MD_DEBUGBAR_RAY_TEMPLATE_CONTEXT', 'Template Context');
        $exclude = $params['exclude'] ?? '';

        $allVars = $template->getTemplateVars();
        if (!\is_array($allVars) || $allVars === []) {
            ray($this->lang('_MD_DEBUGBAR_RAY_NO_VARS', 'No template variables assigned'))
                ->label($label)
                ->color('gray');

            return '';
        }

        if ($exclude !== '') {
            $allVars = $this->applyExclusions($allVars, $exclude);
        }

        $display = $this->normalizeValues($allVars);
        \ksort($display, \SORT_NATURAL | \SORT_FLAG_CASE);

        ray()->table(
            $display,
            \sprintf(
                $this->lang('_MD_DEBUGBAR_RAY_VARS_COUNT', '%s (%d vars)'),
                $label,
                \count($display),
            ),
        )->color('blue');

        return '';
    }

    /**
     * Dump a variable's full structure to Ray.
     *
     * Usage: <{ray_dump value=$config}>
     *        <{ray_dump value=$user label="User Dump"}>
     */
    public function rayDump(array $params, object $template): string
    {
        if (!$this->isRayEnabled()) {
            return '';
        }

        $value = $params['value'] ?? null;
        if ($value === null) {
            return '';
        }

        $label = $params['label']
            ?? $this->lang('_MD_DEBUGBAR_RAY_DUMP', 'Variable Dump');

        ray($value)->label($label)->color('purple');

        return '';
    }

    /**
     * Send an array to Ray's table display.
     *
     * Usage: <{ray_table value=$users}>
     *        <{ray_table value=$config label="Module Config"}>
     */
    public function rayTable(array $params, object $template): string
    {
        if (!$this->isRayEnabled()) {
            return '';
        }

        $value = $params['value'] ?? null;
        if ($value === null || !\is_array($value)) {
            return '';
        }

        if (isset($params['label'])) {
            ray()->table($value, $params['label']);
        } else {
            ray()->table($value);
        }

        return '';
    }

    // ──────────────────────────────────────────────
    // Modifier
    // ──────────────────────────────────────────────

    /**
     * Pass-through modifier that sends the value to Ray for inspection.
     *
     * Usage: <{$user.name|ray}>
     *        <{$user.name|ray:"Username"}>
     */
    public function rayModifier(mixed $value, ?string $label = null): mixed
    {
        if ($this->isRayEnabled()) {
            $r = ray($value);
            if ($label !== null) {
                $r->label($label);
            }
        }

        return $value;
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    /**
     * Check whether Ray debugging is available and enabled.
     *
     * Result is cached for the lifetime of this instance (one request).
     */
    private function isRayEnabled(): bool
    {
        return $this->enabled ??= (
            \class_exists(\XoopsModules\Debugbar\RayLogger::class)
            && \XoopsModules\Debugbar\RayLogger::getInstance()->isEnabled()
            && \function_exists('ray')
        );
    }

    /**
     * Get a language constant with English fallback.
     */
    private function lang(string $constant, string $fallback): string
    {
        return \defined($constant) ? (string) \constant($constant) : $fallback;
    }

    /**
     * Apply comma-separated exclusion patterns to a variable array.
     *
     * Supports exact matches and wildcard prefix matches (e.g., "xoops_*").
     */
    private function applyExclusions(array $vars, string $exclude): array
    {
        $patterns = \array_map('trim', \explode(',', $exclude));
        foreach ($patterns as $pattern) {
            if (\str_ends_with($pattern, '*')) {
                $vars = $this->excludeByPrefix($vars, \substr($pattern, 0, -1));
            } else {
                unset($vars[$pattern]);
            }
        }

        return $vars;
    }

    /**
     * Remove all keys starting with the given prefix.
     */
    private function excludeByPrefix(array $vars, string $prefix): array
    {
        foreach (\array_keys($vars) as $key) {
            if (\str_starts_with($key, $prefix)) {
                unset($vars[$key]);
            }
        }

        return $vars;
    }

    /**
     * Normalize template variable values for display in Ray.
     */
    private function normalizeValues(array $vars): array
    {
        $display = [];
        foreach ($vars as $key => $value) {
            $display[$key] = $this->formatValue($value);
        }

        return $display;
    }

    /**
     * Format a single template variable value for display.
     */
    private function formatValue(mixed $value): mixed
    {
        if (\is_object($value)) {
            return '{' . $value::class . '}';
        }
        if (\is_array($value)) {
            return 'Array[' . \count($value) . ']';
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'NULL';
        }
        if (\is_string($value) && \strlen($value) > 200) {
            return \substr($value, 0, 200) . '...';
        }

        return $value;
    }
}
