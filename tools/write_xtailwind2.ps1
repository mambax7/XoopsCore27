$base = 'C:\wamp64\www\2512menus\themes\xtailwind2'

@'
{
    "name": "xtailwind2",
    "version": "0.2.0",
    "description": "Art-directed Tailwind CSS + DaisyUI theme for XOOPS 2.7",
    "private": true,
    "scripts": {
        "build": "tailwindcss -i ./css/input.css -o ./css/styles.css --minify",
        "watch": "tailwindcss -i ./css/input.css -o ./css/styles.css --watch"
    },
    "devDependencies": {
        "@tailwindcss/typography": "^0.5.10",
        "daisyui": "^4.12.0",
        "tailwindcss": "^3.4.0"
    }
}
'@ | Set-Content -Path (Join-Path $base 'package.json') -Encoding UTF8

@'
/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './theme.tpl',
        './tpl/**/*.tpl',
        './modules/**/*.tpl',
    ],
    theme: {
        extend: {
            fontFamily: {
                display: ['"Space Grotesk"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                body: ['"Inter"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            boxShadow: {
                velvet: '0 24px 80px -30px rgba(15, 23, 42, 0.45)',
                float: '0 20px 40px -24px rgba(14, 116, 144, 0.45)',
            },
            borderRadius: {
                shell: '1.75rem',
            },
        },
    },
    plugins: [
        require('@tailwindcss/typography'),
        require('daisyui'),
    ],
    daisyui: {
        themes: [
            {
                atelier: {
                    primary: '#0f766e',
                    'primary-content': '#f2fffd',
                    secondary: '#d97706',
                    'secondary-content': '#fff8ef',
                    accent: '#7c3aed',
                    'accent-content': '#f8f4ff',
                    neutral: '#1f2937',
                    'neutral-content': '#f8fafc',
                    'base-100': '#fcfbf7',
                    'base-200': '#f4efe4',
                    'base-300': '#e8dfcf',
                    'base-content': '#1b2431',
                    info: '#0ea5e9',
                    success: '#15803d',
                    warning: '#d97706',
                    error: '#b91c1c',
                },
            },
            {
                fjord: {
                    primary: '#1d4ed8',
                    'primary-content': '#eff6ff',
                    secondary: '#0f766e',
                    'secondary-content': '#ecfeff',
                    accent: '#9333ea',
                    'accent-content': '#faf5ff',
                    neutral: '#1e293b',
                    'neutral-content': '#f8fafc',
                    'base-100': '#f7fafc',
                    'base-200': '#edf3f8',
                    'base-300': '#dbe6ee',
                    'base-content': '#172033',
                    info: '#0284c7',
                    success: '#15803d',
                    warning: '#ca8a04',
                    error: '#b91c1c',
                },
            },
            {
                pulsefire: {
                    primary: '#c2410c',
                    'primary-content': '#fff7ed',
                    secondary: '#7c2d12',
                    'secondary-content': '#fff7ed',
                    accent: '#be185d',
                    'accent-content': '#fff1f2',
                    neutral: '#292524',
                    'neutral-content': '#fef7ed',
                    'base-100': '#fff8f1',
                    'base-200': '#fdebd8',
                    'base-300': '#f5d6b8',
                    'base-content': '#2f2218',
                    info: '#0f766e',
                    success: '#15803d',
                    warning: '#d97706',
                    error: '#b91c1c',
                },
            },
            {
                noctis: {
                    primary: '#67e8f9',
                    'primary-content': '#0b1120',
                    secondary: '#f59e0b',
                    'secondary-content': '#0b1120',
                    accent: '#c084fc',
                    'accent-content': '#0b1120',
                    neutral: '#101826',
                    'neutral-content': '#e5eef7',
                    'base-100': '#0b1120',
                    'base-200': '#111b2e',
                    'base-300': '#18243b',
                    'base-content': '#d7e3f4',
                    info: '#38bdf8',
                    success: '#4ade80',
                    warning: '#fbbf24',
                    error: '#f87171',
                },
            },
            {
                velvet: {
                    primary: '#f472b6',
                    'primary-content': '#250714',
                    secondary: '#8b5cf6',
                    'secondary-content': '#f5f3ff',
                    accent: '#f59e0b',
                    'accent-content': '#1f1300',
                    neutral: '#190d1f',
                    'neutral-content': '#f8f0ff',
                    'base-100': '#120a18',
                    'base-200': '#1a1023',
                    'base-300': '#261431',
                    'base-content': '#efe4fb',
                    info: '#38bdf8',
                    success: '#4ade80',
                    warning: '#fbbf24',
                    error: '#fb7185',
                },
            },
            {
                graphite: {
                    primary: '#e2e8f0',
                    'primary-content': '#0f172a',
                    secondary: '#38bdf8',
                    'secondary-content': '#082f49',
                    accent: '#f59e0b',
                    'accent-content': '#111827',
                    neutral: '#111827',
                    'neutral-content': '#f8fafc',
                    'base-100': '#0f172a',
                    'base-200': '#172033',
                    'base-300': '#243146',
                    'base-content': '#dbe4f0',
                    info: '#38bdf8',
                    success: '#4ade80',
                    warning: '#fbbf24',
                    error: '#f87171',
                },
            },
        ],
        darkTheme: 'noctis',
        logs: false,
    },
};
'@ | Set-Content -Path (Join-Path $base 'tailwind.config.js') -Encoding UTF8

@'
[Theme]
Name="xTailwind2 Theme"

Description="Art-directed Tailwind CSS + DaisyUI theme for XOOPS 2.7.0 with curated palettes, stronger hierarchy, and refined module styling"

Version="0.2.0"

Author="Various"

Demo=""

Url="https://github.com/mambax7/xtailwind2"

Download="https://github.com/mambax7/xtailwind2"

W3C="HTML5"

Licence="MIT/GPL v3"

thumbnail="thumbnail.png"

screenshot="screenshot.png"
'@ | Set-Content -Path (Join-Path $base 'theme.ini') -Encoding UTF8

@'
<?php
/**
 * xTailwind2 theme autorun
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license         GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */

xoops_load('XoopsFormRendererTailwind');
XoopsFormRenderer::getInstance()->set(new XoopsFormRendererTailwind());

/** @var XoopsTpl $xoopsTpl */
global $xoopsTpl;
if (!empty($xoopsTpl)) {
    $xoopsTpl->addConfigDir(__DIR__);

    $themes = [
        ['name' => 'atelier',   'label' => 'Atelier',   'mode' => 'light'],
        ['name' => 'fjord',     'label' => 'Fjord',     'mode' => 'light'],
        ['name' => 'pulsefire', 'label' => 'Pulsefire', 'mode' => 'light'],
        ['name' => 'noctis',    'label' => 'Noctis',    'mode' => 'dark'],
        ['name' => 'velvet',    'label' => 'Velvet',    'mode' => 'dark'],
        ['name' => 'graphite',  'label' => 'Graphite',  'mode' => 'dark'],
    ];

    $xoopsTpl->assign('xtailwindThemes', $themes);
}
'@ | Set-Content -Path (Join-Path $base 'theme_autorun.php') -Encoding UTF8

@'
@tailwind base;
@tailwind components;
@tailwind utilities;

[x-cloak] {
    display: none !important;
}

@layer base {
    html {
        scroll-behavior: smooth;
    }

    body {
        @apply font-body;
        background-color: hsl(var(--b1));
        background-image:
            radial-gradient(circle at 12% 12%, hsl(var(--p) / 0.15), transparent 26%),
            radial-gradient(circle at 85% 0%, hsl(var(--s) / 0.12), transparent 24%),
            radial-gradient(circle at 50% 100%, hsl(var(--a) / 0.09), transparent 30%),
            linear-gradient(180deg, hsl(var(--b1)) 0%, hsl(var(--b1)) 52%, hsl(var(--b2)) 100%);
    }

    h1, h2, h3, h4, h5, h6 {
        @apply font-display tracking-tight;
    }

    ::selection {
        background: hsl(var(--p) / 0.22);
        color: hsl(var(--bc));
    }
}

@layer components {
    .shell-container {
        @apply mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8;
    }

    .glass-chrome {
        @apply border border-base-300/70 bg-base-100/70 backdrop-blur-xl;
        box-shadow: 0 24px 80px -34px rgba(15, 23, 42, 0.38);
    }

    .editorial-kicker {
        @apply inline-flex items-center gap-2 rounded-full border border-base-300/80 bg-base-100/80 px-3 py-1 text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-base-content/70;
    }

    .hero-panel {
        @apply relative overflow-hidden rounded-shell border border-base-300/70 bg-base-100/80;
        box-shadow: 0 28px 120px -48px rgba(15, 23, 42, 0.5);
    }

    .hero-panel::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at top right, hsl(var(--p) / 0.2), transparent 28%),
            radial-gradient(circle at bottom left, hsl(var(--s) / 0.16), transparent 26%);
        pointer-events: none;
    }

    .hero-metric {
        @apply rounded-3xl border border-base-300/70 bg-base-100/80 p-4 backdrop-blur-md;
        box-shadow: 0 18px 40px -28px rgba(15, 23, 42, 0.35);
    }

    .content-panel {
        @apply rounded-shell border border-base-300/70 p-5 sm:p-7;
        background-color: hsl(var(--b1) / 0.82);
        box-shadow: 0 24px 70px -38px rgba(15, 23, 42, 0.32);
    }

    .rail-panel {
        @apply rounded-3xl border border-base-300/70 p-4 backdrop-blur-md;
        background-color: hsl(var(--b1) / 0.72);
        box-shadow: 0 20px 50px -34px rgba(15, 23, 42, 0.3);
    }

    .surface-label {
        @apply mb-3 text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-base-content/50;
    }

    .block-content {
        @apply text-sm leading-6 text-base-content/80;
    }

    .block-content ul,
    .block-content ol {
        @apply m-0 list-none p-0;
    }

    .block-content li {
        @apply block;
    }

    .block-content a {
        @apply block rounded-2xl px-3 py-2 text-sm font-medium text-base-content transition duration-200;
        background: linear-gradient(180deg, transparent, transparent);
    }

    .block-content a:hover {
        @apply text-base-content;
        background: linear-gradient(90deg, hsl(var(--p) / 0.12), transparent 80%);
        transform: translateX(2px);
    }

    .block-content p {
        @apply my-2;
    }

    .block-content img {
        @apply h-auto max-w-full rounded-2xl;
    }

    .block-content br {
        display: none;
    }

    .xoops-content {
        @apply text-[0.98rem] leading-8 text-base-content/85;
    }

    .xoops-content :where(h1, h2, h3, h4) {
        @apply mt-10 mb-4 font-display font-semibold tracking-tight text-base-content;
    }

    .xoops-content :where(p, ul, ol, table, blockquote) {
        @apply mb-5;
    }

    .xoops-content :where(a) {
        @apply font-medium text-primary underline decoration-primary/35 underline-offset-4 transition-colors;
    }

    .xoops-content :where(a:hover) {
        @apply text-secondary decoration-secondary/50;
    }

    .xoops-content :where(table) {
        @apply w-full overflow-hidden rounded-3xl border border-base-300/80 bg-base-100/70 text-sm;
    }

    .xoops-content :where(th) {
        @apply bg-base-200/80 px-4 py-3 text-left font-semibold text-base-content;
    }

    .xoops-content :where(td) {
        @apply border-t border-base-300/70 px-4 py-3 align-top;
    }

    .xoops-content :where(blockquote) {
        @apply rounded-r-3xl border-l-4 border-primary/60 bg-base-200/55 px-5 py-4 italic text-base-content/75;
    }

    .xoops-content :where(img) {
        @apply rounded-3xl shadow-lg;
    }

    .toolbar-block-edit {
        display: none;
    }

    .toolbar-edit-on .toolbar-block-edit {
        @apply inline-flex;
    }
}

@layer utilities {
    .text-balance {
        text-wrap: balance;
    }

    .ambient-line {
        background: linear-gradient(90deg, transparent, hsl(var(--p) / 0.18), transparent);
    }
}
'@ | Set-Content -Path (Join-Path $base 'css\input.css') -Encoding UTF8

@'
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
'@ | Set-Content -Path (Join-Path $base 'theme.tpl') -Encoding UTF8

@'
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
'@ | Set-Content -Path (Join-Path $base 'tpl\nav-menu.tpl') -Encoding UTF8

@'
<section class="min-w-0 flex-1 space-y-6">
    <{if isset($xoops_contents)}>
        <article class="content-panel xoops-content">
            <{$xoops_contents}>
        </article>
    <{/if}>

    <{if $xoBlocks.page_topcenter || $xoBlocks.page_topleft || $xoBlocks.page_topright}>
    <section class="grid gap-4 md:grid-cols-2">
        <{foreach item=block from=$xoBlocks.page_topcenter|default:[]}>
            <section class="content-panel md:col-span-2">
                <{if $block.title}><p class="surface-label"><{$block.title}></p><{/if}>
                <{include file="$theme_name/tpl/blockContent.tpl"}>
            </section>
        <{/foreach}>
        <{foreach item=block from=$xoBlocks.page_topleft|default:[]}>
            <section class="content-panel">
                <{if $block.title}><p class="surface-label"><{$block.title}></p><{/if}>
                <{include file="$theme_name/tpl/blockContent.tpl"}>
            </section>
        <{/foreach}>
        <{foreach item=block from=$xoBlocks.page_topright|default:[]}>
            <section class="content-panel">
                <{if $block.title}><p class="surface-label"><{$block.title}></p><{/if}>
                <{include file="$theme_name/tpl/blockContent.tpl"}>
            </section>
        <{/foreach}>
    </section>
    <{/if}>
</section>
'@ | Set-Content -Path (Join-Path $base 'tpl\content-zone.tpl') -Encoding UTF8

@'
<{if !empty($xoops_isadmin)}>
<a href="<{xoAppUrl '/modules/system/admin.php?fct=blocksadmin&op=edit&bid='}><{$block.id}>"
   class="toolbar-block-edit btn btn-xs btn-warning mb-3 rounded-full"
   title="<{$smarty.const.THEME_TOOLBAR_EDIT_THIS_BLOCK}>">
    Edit block
</a>
<{/if}>
<div class="block-content"><{$block.content}></div>
'@ | Set-Content -Path (Join-Path $base 'tpl\blockContent.tpl') -Encoding UTF8

@'
<{if $xoBlocks.canvas_left}>
<aside class="w-full xl:sticky xl:top-28 xl:w-72 xl:flex-shrink-0">
    <div class="space-y-4">
        <{foreach item=block from=$xoBlocks.canvas_left}>
        <section class="rail-panel">
            <{if $block.title}><p class="surface-label"><{$block.title}></p><{/if}>
            <{include file="$theme_name/tpl/blockContent.tpl"}>
        </section>
        <{/foreach}>
    </div>
</aside>
<{/if}>
'@ | Set-Content -Path (Join-Path $base 'tpl\leftBlock.tpl') -Encoding UTF8

@'
<{if $xoBlocks.canvas_right}>
<aside class="w-full xl:sticky xl:top-28 xl:w-72 xl:flex-shrink-0">
    <div class="space-y-4">
        <{foreach item=block from=$xoBlocks.canvas_right}>
        <section class="rail-panel">
            <{if $block.title}><p class="surface-label"><{$block.title}></p><{/if}>
            <{include file="$theme_name/tpl/blockContent.tpl"}>
        </section>
        <{/foreach}>
    </div>
</aside>
<{/if}>
'@ | Set-Content -Path (Join-Path $base 'tpl\rightBlock.tpl') -Encoding UTF8

@'
# xTailwind2

xTailwind2 is an art-directed Tailwind CSS + DaisyUI theme for XOOPS 2.7.0.
It keeps the flexibility of utility-first styling, but ships with a more curated
visual system than the original `xtailwind` experiment.

## Design direction

- stronger editorial hero on the home page
- quieter side rails, stronger main content canvas
- curated theme palettes instead of a long generic list
- softer glass navigation and richer light/dark mood handling
- better default styling for raw XOOPS content and system blocks

## Included palettes

- `atelier`
- `fjord`
- `pulsefire`
- `noctis`
- `velvet`
- `graphite`

## Build

```bash
npm run build
npm run watch
```

The Tailwind entry file is `css/input.css` and the compiled output is
`css/styles.css`.
'@ | Set-Content -Path (Join-Path $base 'README.md') -Encoding UTF8
