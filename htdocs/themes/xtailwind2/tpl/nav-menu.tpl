<{* xTailwind2 navigation *}>
<header class="sticky top-0 z-40 px-3 pt-3 sm:px-5">
    <div class="shell-container">
        <div class="glass-chrome rounded-shell px-4 py-3 sm:px-5">
            <div class="flex items-center gap-3">
                <a href="<{$xoops_url}>" class="flex min-w-0 flex-1 items-center gap-3" title="<{$xoops_sitename}>">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/15 text-primary shadow-float">
                        <span class="h-3 w-3 rounded-full bg-primary"></span>
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate font-display text-lg font-semibold leading-none"><{$xoops_sitename}></span>
                        <span class="mt-1 block truncate text-[0.68rem] uppercase tracking-[0.28em] text-base-content/45">XOOPS Tailwind Frontend</span>
                    </span>
                </a>

                <nav class="hidden xl:block">
                    <ul class="flex items-center gap-1">
                        <{if isset($xoMenuCategories) && $xoMenuCategories}>
                            <{foreach from=$xoMenuCategories item=cat}>
                                <li>
                                    <{if $cat.items}>
                                        <details class="dropdown dropdown-end">
                                            <summary class="btn btn-ghost rounded-full px-4 text-sm font-medium"><{$cat.category_title|escape}></summary>
                                            <ul class="menu dropdown-content z-50 mt-3 w-72 rounded-3xl border border-base-300/80 bg-base-100/95 p-3 text-base-content shadow-2xl backdrop-blur-xl">
                                                <{foreach from=$cat.items item=subItem}>
                                                    <li>
                                                        <a href="<{if $subItem.url neq ''}><{$subItem.url|escape}><{else}>#<{/if}>"
                                                           target="<{$subItem.target}>"
                                                           <{if $subItem.target == '_blank'}> rel="noopener noreferrer"<{/if}>
                                                            class="rounded-2xl px-3 py-2">
                                                            <{$subItem.title|escape}>
                                                        </a>
                                                    </li>
                                                <{/foreach}>
                                            </ul>
                                        </details>
                                    <{else}>
                                        <a class="btn btn-ghost rounded-full px-4 text-sm font-medium" href="<{if $cat.category_url neq ''}><{$cat.category_url|escape}><{else}>#<{/if}>" target="<{$cat.category_target}>">
                                            <{$cat.category_title|escape}>
                                        </a>
                                    <{/if}>
                                </li>
                            <{/foreach}>
                        <{else}>
                            <li><a class="btn btn-ghost rounded-full px-4 text-sm font-medium" href="<{$xoops_url}>"><{$smarty.const.THEME_HOME}></a></li>
                        <{/if}>
                    </ul>
                </nav>

                <div class="hidden lg:flex items-center gap-2">
                    <{if !empty($xoops_search)}>
                    <form class="hidden items-center gap-2 xl:flex" role="search" action="<{xoAppUrl 'search.php'}>" method="get">
                        <label class="input input-bordered flex items-center gap-2 rounded-full border-base-300/80 bg-base-100/80 pr-2 shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="h-4 w-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" name="query" class="grow bg-transparent text-sm" placeholder="<{$smarty.const.THEME_SEARCH_TEXT|default:'Search'}>">
                        </label>
                        <input type="hidden" name="action" value="results">
                    </form>
                    <{/if}>

                    <button class="btn btn-ghost rounded-full border border-base-300/70 bg-base-100/70 px-4" type="button" onclick="xtailwind2ToggleMode()">
                        <span id="xtailwind2-mode-label">Switch to dark</span>
                    </button>

                    <details class="dropdown dropdown-end">
                        <summary class="btn btn-primary rounded-full px-5"><{$smarty.const.THEME_SWITCHER|default:'Themes'}></summary>
                        <div id="xtailwind2-theme-menu" class="dropdown-content z-50 mt-3 w-80 rounded-shell border border-base-300/80 bg-base-100/95 p-4 shadow-2xl backdrop-blur-xl">
                            <div class="mb-3 flex items-center justify-between">
                                <div>
                                    <p class="surface-label mb-1">Palette switcher</p>
                                    <p class="text-sm text-base-content/65">Choose one of the curated light or dark moods.</p>
                                </div>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <{foreach from=$xtailwindThemes item=theme}>
                                <button type="button" data-theme-name="<{$theme.name}>" class="flex items-center justify-between rounded-3xl border border-base-300/70 px-3 py-3 text-left transition hover:border-primary/40 hover:bg-base-200/70">
                                    <span>
                                        <span class="block text-sm font-semibold"><{$theme.label}></span>
                                        <span class="text-xs uppercase tracking-[0.2em] text-base-content/45"><{$theme.mode}></span>
                                    </span>
                                    <span class="flex gap-1.5">
                                        <span class="h-3 w-3 rounded-full bg-primary"></span>
                                        <span class="h-3 w-3 rounded-full bg-secondary"></span>
                                        <span class="h-3 w-3 rounded-full bg-accent"></span>
                                    </span>
                                </button>
                                <{/foreach}>
                            </div>
                        </div>
                    </details>
                </div>

                <button id="xtailwind2-mobile-toggle" class="btn btn-ghost rounded-full xl:hidden" type="button" aria-expanded="false" aria-controls="xtailwind2-mobile-panel">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
    </div>
</header>

<div id="xtailwind2-mobile-panel" class="shell-container hidden xl:hidden">
    <div class="glass-chrome mt-3 rounded-shell px-4 py-4">
        <div class="space-y-4">
            <{if !empty($xoops_search)}>
            <form role="search" action="<{xoAppUrl 'search.php'}>" method="get" class="flex gap-2">
                <input type="text" name="query" class="input input-bordered w-full rounded-full" placeholder="<{$smarty.const.THEME_SEARCH_TEXT|default:'Search'}>">
                <input type="hidden" name="action" value="results">
                <button class="btn btn-primary rounded-full" type="submit">Go</button>
            </form>
            <{/if}>

            <div class="space-y-1">
                <{if isset($xoMenuCategories) && $xoMenuCategories}>
                    <{foreach from=$xoMenuCategories item=cat}>
                        <{if $cat.items}>
                        <details class="rounded-3xl border border-base-300/70 bg-base-100/65 px-4 py-3">
                            <summary class="cursor-pointer list-none font-semibold"><{$cat.category_title|escape}></summary>
                            <ul class="mt-3 space-y-1">
                                <{foreach from=$cat.items item=subItem}>
                                <li>
                                    <a class="block rounded-2xl px-3 py-2 text-sm text-base-content/80 hover:bg-base-200/70" href="<{if $subItem.url neq ''}><{$subItem.url|escape}><{else}>#<{/if}>" target="<{$subItem.target}>" <{if $subItem.target == '_blank'}> rel="noopener noreferrer"<{/if}>><{$subItem.title|escape}></a>
                                </li>
                                <{/foreach}>
                            </ul>
                        </details>
                        <{else}>
                        <a class="block rounded-3xl border border-base-300/70 bg-base-100/65 px-4 py-3 font-semibold" href="<{if $cat.category_url neq ''}><{$cat.category_url|escape}><{else}>#<{/if}>" target="<{$cat.category_target}>"><{$cat.category_title|escape}></a>
                        <{/if}>
                    <{/foreach}>
                <{/if}>
            </div>

            <div>
                <p class="surface-label">Curated themes</p>
                <div id="xtailwind2-theme-menu-mobile" class="grid grid-cols-2 gap-2">
                    <{foreach from=$xtailwindThemes item=theme}>
                    <button type="button" data-theme-name="<{$theme.name}>" onclick="xtailwind2SetTheme('<{$theme.name}>')" class="rounded-3xl border border-base-300/70 bg-base-100/70 px-3 py-3 text-left">
                        <span class="block text-sm font-semibold"><{$theme.label}></span>
                        <span class="text-xs uppercase tracking-[0.2em] text-base-content/45"><{$theme.mode}></span>
                    </button>
                    <{/foreach}>
                </div>
            </div>
        </div>
    </div>
</div>
