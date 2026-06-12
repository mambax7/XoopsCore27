<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Adapter;

use Smarty\BlockHandler\BlockHandlerInterface;
use Smarty\FunctionHandler\FunctionHandlerInterface;
use Smarty\Template;
use Xoops\SmartyExtensions\AbstractExtension;

/**
 * Adapts an AbstractExtension for Smarty 5's Extension API.
 *
 * Wraps any AbstractExtension into a \Smarty\Extension\Base subclass by delegating
 * modifier/function/block lookups to the wrapped extension's getter arrays.
 *
 * One adapter class, instantiated once per extension.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
final class Smarty5Adapter extends \Smarty\Extension\Base
{
    /** @var array<string, callable> */
    private readonly array $modifiers;

    /** @var array<string, callable> */
    private readonly array $functions;

    /** @var array<string, callable> */
    private readonly array $blocks;

    public function __construct(AbstractExtension $extension)
    {
        $this->modifiers = $extension->getModifiers();
        $this->functions = $extension->getFunctions();
        $this->blocks = $extension->getBlockHandlers();
    }

    public function getModifierCallback(string $modifier): ?callable
    {
        return $this->modifiers[$modifier] ?? null;
    }

    public function getFunctionHandler(string $name): ?FunctionHandlerInterface
    {
        $callback = $this->functions[$name] ?? null;
        if ($callback === null) {
            return null;
        }

        return new class ($callback) implements FunctionHandlerInterface {
            /** @var \Closure */
            private readonly \Closure $callback;

            public function __construct(callable $callback)
            {
                $this->callback = $callback(...);
            }

            public function handle($params, Template $template): mixed
            {
                return ($this->callback)($params, $template);
            }

            public function isCacheable(): bool
            {
                return false;
            }
        };
    }

    public function getBlockHandler(string $name): ?BlockHandlerInterface
    {
        $callback = $this->blocks[$name] ?? null;
        if ($callback === null) {
            return null;
        }

        return new class ($callback) implements BlockHandlerInterface {
            /** @var \Closure */
            private readonly \Closure $callback;

            public function __construct(callable $callback)
            {
                $this->callback = $callback(...);
            }

            public function handle($params, $content, Template $template, &$repeat): mixed
            {
                return ($this->callback)($params, $content, $template, $repeat);
            }

            public function isCacheable(): bool
            {
                return false;
            }
        };
    }
}
