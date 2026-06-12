<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions;

use Xoops\SmartyExtensions\Adapter\Smarty5Adapter;

/**
 * Central registry for all XOOPS Smarty extensions.
 *
 * Detects Smarty version and uses the appropriate registration path:
 * - Smarty 4: calls register() on each extension (registerPlugin)
 * - Smarty 5: wraps each extension in Smarty5Adapter and calls addExtension()
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
final class ExtensionRegistry
{
    /** @var list<AbstractExtension> */
    private array $extensions = [];

    public function add(AbstractExtension $extension): void
    {
        $this->extensions[] = $extension;
    }

    /**
     * Register all extensions with a Smarty instance.
     *
     * @param \Smarty|\Smarty\Smarty $smarty
     */
    public function registerAll(object $smarty): void
    {
        if (class_exists(\Smarty\Extension\Base::class)) {
            foreach ($this->extensions as $ext) {
                $smarty->addExtension(new Smarty5Adapter($ext));
            }
        } else {
            foreach ($this->extensions as $ext) {
                $ext->register($smarty);
            }
        }
    }
}
