<!doctype html>
<html lang="<{$xoops_langcode}>" dir="<{$xoops_text_direction|default:'ltr'}>" data-theme="atelier">
<head>
<{assign var=theme_name value=$xoTheme->folderName}>
    <meta charset="<{$xoops_charset}>">
    <meta name="keywords" content="<{$xoops_meta_keywords}>">
    <meta name="description" content="<{$xoops_meta_description}>">
    <meta name="robots" content="<{$xoops_meta_robots}>">
    <meta name="rating" content="<{$xoops_meta_rating}>">
    <meta name="author" content="<{$xoops_meta_author}>">
    <meta name="copyright" content="<{$xoops_meta_copyright}>">
    <meta name="generator" content="XOOPS">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="<{$xoops_url}>/favicon.ico" rel="shortcut icon">
    <link rel="stylesheet" type="text/css" href="<{xoImgUrl 'css/styles.css'}>">

    <script>
    (function() {
        const stored = localStorage.getItem('xtailwind2-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = stored || (prefersDark ? 'noctis' : 'atelier');
        document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>

    <script src="<{$xoops_url}>/browse.php?Frameworks/jquery/jquery.js"></script>
    <link rel="alternate" type="application/rss+xml" title="" href="<{xoAppUrl 'backend.php'}>">

    <title><{if isset($xoops_dirname) && $xoops_dirname == "system"}><{$xoops_sitename}><{if !empty($xoops_pagetitle)}> - <{$xoops_pagetitle}><{/if}><{else}><{if !empty($xoops_pagetitle)}><{$xoops_pagetitle}> - <{$xoops_sitename}><{/if}><{/if}></title>

<{$xoops_module_header}>
    <link rel="stylesheet" type="text/css" media="all" href="<{$xoops_themecss}>">
</head>

<body id="<{$xoops_dirname}>" class="min-h-screen text-base-content antialiased">
<div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
    <div class="absolute left-[-12rem] top-[-10rem] h-[28rem] w-[28rem] rounded-full bg-primary/12 blur-3xl"></div>
    <div class="absolute right-[-8rem] top-[8rem] h-[24rem] w-[24rem] rounded-full bg-secondary/12 blur-3xl"></div>
    <div class="absolute bottom-[-12rem] left-1/3 h-[30rem] w-[30rem] rounded-full bg-accent/10 blur-3xl"></div>
</div>

<{include file="$theme_name/tpl/nav-menu.tpl"}>

<main class="pb-10 pt-4 sm:pt-6">
    <{if isset($xoops_page) && $xoops_page == "index"}>
    <section class="shell-container mb-8 sm:mb-10">
        <div class="hero-panel px-5 py-8 sm:px-8 sm:py-10 lg:px-10 lg:py-12">
            <div class="relative grid gap-8 lg:grid-cols-[minmax(0,1.1fr)_minmax(20rem,0.9fr)] lg:items-center">
                <div class="space-y-6">
                    <div class="editorial-kicker">
                        <span class="inline-block h-2 w-2 rounded-full bg-primary"></span>
                        Tailwind Edition for XOOPS
                    </div>
                    <div class="space-y-4">
                        <p class="max-w-xl text-sm uppercase tracking-[0.28em] text-base-content/45">Curated surfaces. Better rhythm. Faster scan.</p>
                        <h1 class="max-w-3xl text-balance text-4xl font-semibold leading-none sm:text-5xl lg:text-6xl"><{$xoops_sitename}></h1>
                        <p class="max-w-2xl text-base leading-8 text-base-content/70"><{if !empty($xoops_meta_description)}><{$xoops_meta_description}><{else}>A sharper XOOPS front end with warmer light themes, richer dark themes, and a calmer layout system that keeps content first.<{/if}></p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="<{$xoops_url}>/" class="btn btn-primary btn-lg rounded-full px-8"><{$smarty.const.THEME_HOME|default:'Explore'}></a>
                        <{if !empty($xoops_search)}>
                        <a href="<{xoAppUrl 'search.php'}>" class="btn btn-ghost btn-lg rounded-full border border-base-300/80 bg-base-100/70 px-8">Search the site</a>
                        <{/if}>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="hero-metric">
                            <div class="surface-label">Feel</div>
                            <p class="text-sm font-semibold text-base-content">Editorial, airy, and less dashboard-like.</p>
                        </div>
                        <div class="hero-metric">
                            <div class="surface-label">Systems</div>
                            <p class="text-sm font-semibold text-base-content">Curated palettes instead of endless defaults.</p>
                        </div>
                        <div class="hero-metric">
                            <div class="surface-label">Modules</div>
                            <p class="text-sm font-semibold text-base-content">Cleaner rails and quieter content framing.</p>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <div class="content-panel relative overflow-hidden p-6 sm:p-7">
                        <div class="absolute inset-x-6 top-0 h-px ambient-line"></div>
                        <div class="mb-6 flex items-start justify-between gap-4">
                            <div>
                                <p class="surface-label">Theme Atmosphere</p>
                                <h2 class="text-2xl font-semibold">Layered depth without heavy chrome</h2>
                            </div>
                            <span class="rounded-full border border-base-300/80 bg-base-100/80 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-base-content/55">Live</span>
                        </div>
                        <div class="space-y-4">
                            <div class="rounded-3xl border border-base-300/70 bg-base-200/70 p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <span class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/45">Current palette</span>
                                    <span class="text-sm font-medium text-primary">Theme aware</span>
                                </div>
                                <div class="flex gap-3">
                                    <span class="h-12 w-12 rounded-2xl bg-primary shadow-float"></span>
                                    <span class="h-12 w-12 rounded-2xl bg-secondary shadow-float"></span>
                                    <span class="h-12 w-12 rounded-2xl bg-accent shadow-float"></span>
                                    <span class="h-12 w-12 rounded-2xl bg-neutral shadow-float"></span>
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="rounded-3xl border border-base-300/70 bg-base-100/70 p-4">
                                    <p class="surface-label">Navigation</p>
                                    <p class="text-sm leading-7 text-base-content/70">A lighter top bar, stronger brand lockup, and a theme switcher that feels intentional instead of utility-only.</p>
                                </div>
                                <div class="rounded-3xl border border-base-300/70 bg-base-100/70 p-4">
                                    <p class="surface-label">Content canvas</p>
                                    <p class="text-sm leading-7 text-base-content/70">Main content reads as the centerpiece while sidebars behave like supporting rails, not equal-weight cards.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <{/if}>

    <section class="shell-container">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
            <{include file="$theme_name/tpl/leftBlock.tpl"}>
            <{include file="$theme_name/tpl/content-zone.tpl"}>
            <{include file="$theme_name/tpl/rightBlock.tpl"}>
        </div>
    </section>
</main>

<{if $xoBlocks.footer_center || $xoBlocks.footer_right || $xoBlocks.footer_left}>
<footer class="mt-12 border-t border-base-300/70 bg-base-200/55">
    <div class="shell-container py-10">
        <div class="grid gap-5 md:grid-cols-3">
            <{foreach item=block from=$xoBlocks.footer_left|default:[]}>
                <section class="rail-panel">
                    <p class="surface-label"><{$block.title|default:'Footer'}></p>
                    <{include file="$theme_name/tpl/blockContent.tpl"}>
                </section>
            <{/foreach}>
            <{foreach item=block from=$xoBlocks.footer_center|default:[]}>
                <section class="rail-panel">
                    <p class="surface-label"><{$block.title|default:'Footer'}></p>
                    <{include file="$theme_name/tpl/blockContent.tpl"}>
                </section>
            <{/foreach}>
            <{foreach item=block from=$xoBlocks.footer_right|default:[]}>
                <section class="rail-panel">
                    <p class="surface-label"><{$block.title|default:'Footer'}></p>
                    <{include file="$theme_name/tpl/blockContent.tpl"}>
                </section>
            <{/foreach}>
        </div>
    </div>
</footer>
<{/if}>

<footer class="shell-container pb-6 pt-4">
    <div class="glass-chrome flex flex-col items-center justify-between gap-3 rounded-3xl px-5 py-4 text-sm text-base-content/60 sm:flex-row">
        <div><{$xoops_footer}></div>
        <a href="https://xoops.org" class="font-medium text-primary transition hover:text-secondary" target="_blank" rel="noopener">Built on XOOPS</a>
    </div>
</footer>

<{if !empty($xoops_isuser)}><{include file="$theme_name/tpl/inboxAlert.tpl"}><{/if}>

<script>
const XTAILWIND2_THEME_MODES = {
    <{foreach from=$xtailwindThemes item=t name=tm}>'<{$t.name}>': '<{$t.mode}>'<{if !$smarty.foreach.tm.last}>,<{/if}><{/foreach}>
};

function xtailwind2CurrentTheme() {
    return document.documentElement.getAttribute('data-theme') || 'atelier';
}

function xtailwind2CurrentMode() {
    return XTAILWIND2_THEME_MODES[xtailwind2CurrentTheme()] || 'light';
}

function xtailwind2RememberTheme(theme) {
    localStorage.setItem('xtailwind2-theme', theme);
    const mode = XTAILWIND2_THEME_MODES[theme] || 'light';
    localStorage.setItem('xtailwind2-' + mode + '-theme', theme);
}

function xtailwind2SetTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    xtailwind2RememberTheme(theme);
    xtailwind2UpdateUI();
}

function xtailwind2ToggleMode() {
    const currentMode = xtailwind2CurrentMode();
    const nextMode = currentMode === 'dark' ? 'light' : 'dark';
    const fallback = nextMode === 'dark' ? 'noctis' : 'atelier';
    const nextTheme = localStorage.getItem('xtailwind2-' + nextMode + '-theme') || fallback;
    xtailwind2SetTheme(nextTheme);
}

function xtailwind2UpdateUI() {
    const currentTheme = xtailwind2CurrentTheme();
    const currentMode = xtailwind2CurrentMode();
    const label = document.getElementById('xtailwind2-mode-label');
    if (label) {
        label.textContent = currentMode === 'dark' ? 'Switch to light' : 'Switch to dark';
    }
    document.querySelectorAll('[data-theme-name]').forEach(function(item) {
        const active = item.getAttribute('data-theme-name') === currentTheme;
        item.classList.toggle('ring-2', active);
        item.classList.toggle('ring-primary', active);
        item.classList.toggle('bg-base-200', active);
    });
}

function xtailwind2ToggleMobileMenu() {
    const panel = document.getElementById('xtailwind2-mobile-panel');
    const button = document.getElementById('xtailwind2-mobile-toggle');
    if (!panel || !button) {
        return;
    }
    const isHidden = panel.classList.contains('hidden');
    panel.classList.toggle('hidden', !isHidden);
    button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
}

document.addEventListener('DOMContentLoaded', function() {
    xtailwind2UpdateUI();

    const menu = document.getElementById('xtailwind2-theme-menu');
    if (menu) {
        menu.addEventListener('click', function(e) {
            const item = e.target.closest('[data-theme-name]');
            if (!item) {
                return;
            }
            e.preventDefault();
            xtailwind2SetTheme(item.getAttribute('data-theme-name'));
        });
    }

    const mobileToggle = document.getElementById('xtailwind2-mobile-toggle');
    if (mobileToggle) {
        mobileToggle.addEventListener('click', xtailwind2ToggleMobileMenu);
    }
});
</script>
</body>
</html>
