<aside class="modern-sidebar" id="sidebar">
    <nav class="sidebar-nav">
        <!-- Nav section control panel -->
        <div class="nav-section">
            <div class="nav-section-title"><{$smarty.const._MODERN_CONTROL_PANEL}></div>
            <{foreach item=item from=$control_menu}>
                <a href="<{$item.link|escape:'html'}>" class="nav-item" <{if $item.absolute}>target="_parent"<{/if}>>
                    <span class="nav-icon">
                        <{if $item.icon == 'home' || $item.icon == 'dashboard'}>&#x1F3E0;<{elseif $item.icon == 'logout'}>&#x1F6AA;<{else}>&#x1F4CA;<{/if}>
                    </span>
                    <span class="nav-text"><{$item.title|strip_tags}></span>
                </a>
            <{/foreach}>
        </div>

        <!-- Nav section with sytem module -->
        <details class="nav-section nav-section-collapsible" <{if $showSystemServices}>open<{/if}>>
            <summary class="nav-section-title">
                <span><{$smarty.const._MODERN_SYSTEM}></span>
                <span class="nav-section-arrow">&#9654;</span>
            </summary>
            <div class="nav-section-items">
                <{foreach item=item from=$sys_options}>
                <a href="<{$item.link|escape:'html'}>" class="nav-item">
                    <{if $item.icon|default:''|escape:'html'}>
                        <img src="<{$item.icon|escape:'html'}>" class="nav-icon-img" alt="">
                    <{else}>
                        <span class="nav-icon">⚙️</span>
                    <{/if}>
                    <span class="nav-text"><{$item.title|escape:'html'}></span>
                </a>
                <{/foreach}>
            </div>
        </details>

        <!-- Nav section all other modules -->
        <{if $module_menu}>
            <details class="nav-section nav-section-collapsible" open>
                <summary class="nav-section-title">
                    <span><{$smarty.const._MODERN_MODULES}></span>
                    <span class="nav-section-arrow">&#9654;</span>
                </summary>
                <div class="nav-section-items">
                <{foreach item=module from=$module_menu}>
                    <{if $module.link}>
                        <a href="<{$module.link|escape:'html'}>" class="nav-item">
                            <{if $module.icon|default:''|escape:'html'}>
                                <img src="<{$module.icon|escape:'html'}>" class="nav-icon-img" alt="">
                            <{else}>
                                <span class="nav-icon">&#x1F4E6;</span>
                            <{/if}>
                            <span class="nav-text"><{$module.name|escape:'html'}></span>
                        </a>
                    <{/if}>
                <{/foreach}>
                </div>
            </details>
        <{/if}>
    </nav>
</aside>
