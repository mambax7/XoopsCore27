<{* xTailwind navigation — Tailwind + DaisyUI + Alpine.js *}>
<div x-data="{ mobileOpen: false }">
<div class="navbar bg-primary text-primary-content shadow-lg">
    <div class="container mx-auto flex items-center">
        <{* Brand *}>
        <div class="flex-1">
            <a href="<{$xoops_url}>" class="btn btn-ghost text-xl normal-case" title="<{$xoops_sitename}>">
                <{$xoops_sitename}>
            </a>
        </div>

        <{* Mobile toggle *}>
        <button class="btn btn-ghost lg:hidden" @click="mobileOpen = !mobileOpen" aria-label="Toggle navigation">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        <{* Desktop navigation *}>
        <div class="hidden lg:flex items-center gap-2">
            <ul class="menu menu-horizontal gap-1">
                <{if isset($xoMenuCategories) && $xoMenuCategories}>
                    <{foreach from=$xoMenuCategories item=cat}>
                        <{if $cat.items}>
                            <li>
                                <details>
                                    <summary>
                                        <{$cat.category_prefix|default:''}> <{$cat.category_title|escape}> <{$cat.category_suffix|default:''}>
                                    </summary>
                                    <ul class="bg-primary text-primary-content rounded-t-none p-2 z-50 min-w-max">
                                        <{foreach from=$cat.items item=subItem}>
                                        <li>
                                            <a href="<{if $subItem.url neq ''}><{$subItem.url|escape}><{else}>#<{/if}>"
                                               target="<{$subItem.target}>"
                                               <{if $subItem.target == '_blank'}> rel="noopener noreferrer"<{/if}>>
                                                <{$subItem.prefix|default:''}> <{$subItem.title|escape}> <{$subItem.suffix|default:''}>
                                            </a>
                                        </li>
                                        <{/foreach}>
                                    </ul>
                                </details>
                            </li>
                        <{else}>
                            <li>
                                <a href="<{if $cat.category_url neq ''}><{$cat.category_url|escape}><{else}>#<{/if}>"
                                   target="<{$cat.category_target}>">
                                    <{$cat.category_prefix|default:''}> <{$cat.category_title|escape}> <{$cat.category_suffix|default:''}>
                                </a>
                            </li>
                        <{/if}>
                    <{/foreach}>
                <{else}>
                    <{* Fallback when menu system disabled *}>
                    <li><a href="<{$xoops_url}>"><{$smarty.const.THEME_HOME}></a></li>
                    <{if $xoops_isuser|default:false}>
                        <li><a href="<{$xoops_url}>/edituser.php"><{$smarty.const.THEME_ACCOUNT}></a></li>
                        <li><a href="<{$xoops_url}>/user.php?op=logout"><{$smarty.const._LOGOUT}></a></li>
                    <{else}>
                        <li><a href="<{$xoops_url}>/user.php"><{$smarty.const._LOGIN}></a></li>
                        <li><a href="<{$xoops_url}>/register.php"><{$smarty.const._REGISTER}></a></li>
                    <{/if}>
                <{/if}>
            </ul>

            <{* Search form *}>
            <{if !empty($xoops_search)}>
            <form class="flex items-center gap-1" role="search" action="<{xoAppUrl 'search.php'}>" method="get">
                <input type="text" name="query" placeholder="<{$smarty.const.THEME_SEARCH_TEXT}>" class="input input-sm input-bordered w-32 text-base-content">
                <button class="btn btn-sm btn-secondary" type="submit">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
                <input type="hidden" name="action" value="results">
            </form>
            <{/if}>

            <{* Dark/light toggle *}>
            <button class="btn btn-sm btn-ghost gap-2" type="button" onclick="xtailwindToggleMode()">
                <svg id="xtailwind-mode-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                <span id="xtailwind-mode-label"><{$smarty.const.THEME_DARK_MODE}></span>
            </button>

            <{* Theme dropdown *}>
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="btn btn-sm btn-ghost gap-1">
                    <{$smarty.const.THEME_SWITCHER}>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
                <ul id="xtailwind-theme-menu" tabindex="0" class="dropdown-content menu bg-base-100 text-base-content rounded-box z-50 p-2 shadow max-h-96 overflow-y-auto flex-nowrap">
                    <{foreach from=$xtailwindThemes item=theme}>
                    <li><a href="#" data-theme-name="<{$theme.name}>"><{$theme.label}></a></li>
                    <{/foreach}>
                </ul>
            </div>
        </div>
    </div>
</div>

<{* Mobile navigation drawer — shares Alpine scope with navbar for mobileOpen state *}>
<div x-cloak x-show="mobileOpen" x-transition class="lg:hidden bg-primary text-primary-content">
    <ul class="menu p-4 gap-1">
        <{if isset($xoMenuCategories) && $xoMenuCategories}>
            <{foreach from=$xoMenuCategories item=cat}>
            <li>
                <a href="<{if $cat.category_url neq ''}><{$cat.category_url|escape}><{else}>#<{/if}>">
                    <{$cat.category_title|escape}>
                </a>
            </li>
            <{/foreach}>
        <{/if}>
    </ul>
</div>
</div><{* /x-data mobileOpen scope *}>

<script>
const XTAILWIND_DARK_LABEL = '<{$smarty.const.THEME_DARK_MODE|escape:"javascript"}>';
const XTAILWIND_LIGHT_LABEL = '<{$smarty.const.THEME_LIGHT_MODE|escape:"javascript"}>';

/* DaisyUI themes are classified as light or dark via theme_autorun.php.
   We mirror that into a lookup table so the dark/light toggle knows which
   opposite theme to switch to and which label to show. */
const XTAILWIND_THEME_MODES = {
    <{foreach from=$xtailwindThemes item=t name=tm}>'<{$t.name}>': '<{$t.mode}>'<{if !$smarty.foreach.tm.last}>,<{/if}><{/foreach}>
};

function xtailwindCurrentMode() {
    const theme = document.documentElement.getAttribute('data-theme');
    return XTAILWIND_THEME_MODES[theme] || 'light';
}

function xtailwindUpdateModeLabel() {
    const label = document.getElementById('xtailwind-mode-label');
    if (label) {
        label.textContent = xtailwindCurrentMode() === 'dark' ? XTAILWIND_LIGHT_LABEL : XTAILWIND_DARK_LABEL;
    }
}

function xtailwindSetTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('xtailwind-theme', theme);
    xtailwindUpdateModeLabel();
    xtailwindHighlightActiveTheme(theme);
}

function xtailwindToggleMode() {
    /* Pick a sensible default for the opposite mode.
       If you want to remember the last light/dark theme separately, extend with a second localStorage key. */
    const current = xtailwindCurrentMode();
    xtailwindSetTheme(current === 'dark' ? 'light' : 'dark');
}

function xtailwindHighlightActiveTheme(theme) {
    const menu = document.getElementById('xtailwind-theme-menu');
    if (!menu) { return; }
    menu.querySelectorAll('a[data-theme-name]').forEach(function(item) {
        if (item.getAttribute('data-theme-name') === theme) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    xtailwindUpdateModeLabel();
    const menu = document.getElementById('xtailwind-theme-menu');
    if (menu) {
        menu.addEventListener('click', function(e) {
            const item = e.target.closest('[data-theme-name]');
            if (item) {
                e.preventDefault();
                xtailwindSetTheme(item.getAttribute('data-theme-name'));
            }
        });
    }
    xtailwindHighlightActiveTheme(document.documentElement.getAttribute('data-theme'));
});
</script>
