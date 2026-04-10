<!doctype html>
<html lang="<{$xoops_langcode}>" dir="<{$xoops_text_direction|default:'ltr'}>" data-theme="light">
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

    <{* Compiled Tailwind + DaisyUI CSS. Run `npx tailwindcss -i css/input.css -o css/styles.css` to rebuild. *}>
    <link rel="stylesheet" type="text/css" href="<{xoImgUrl 'css/styles.css'}>">

    <{* Theme preference: localStorage > system preference > light. Runs before paint to prevent FOUC. *}>
    <script>
    (function() {
        const stored = localStorage.getItem('xtailwind-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = stored || (prefersDark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>

    <script src="<{$xoops_url}>/browse.php?Frameworks/jquery/jquery.js"></script>
    <{* Alpine.js powers interactive components (dropdowns, modals, mobile nav).
        Shared across all Tailwind themes via xoops_lib/Frameworks/alpine/ *}>
    <script defer src="<{$xoops_url}>/browse.php?Frameworks/alpine/alpine.min.js"></script>

    <link rel="alternate" type="application/rss+xml" title="" href="<{xoAppUrl 'backend.php'}>">

    <title><{if isset($xoops_dirname) && $xoops_dirname == "system"}><{$xoops_sitename}><{if !empty($xoops_pagetitle)}> - <{$xoops_pagetitle}><{/if}><{else}><{if !empty($xoops_pagetitle)}><{$xoops_pagetitle}> - <{$xoops_sitename}><{/if}><{/if}></title>

<{$xoops_module_header}>
    <link rel="stylesheet" type="text/css" media="all" href="<{$xoops_themecss}>">
</head>

<body id="<{$xoops_dirname}>" class="min-h-screen bg-base-100 text-base-content font-sans antialiased">

<{include file="$theme_name/tpl/nav-menu.tpl"}>

<main class="container mx-auto px-4 py-6">

    <{if isset($xoops_page) && $xoops_page == "index"}>
    <{* Hero section replaces Bootstrap jumbotron *}>
    <section class="hero bg-base-200 rounded-box mb-6">
        <div class="hero-content text-center py-12">
            <div class="max-w-md">
                <h1 class="text-4xl font-bold"><{$smarty.const.THEME_ABOUTUS}></h1>
                <p class="py-4"><{$xoops_meta_description}></p>
                <a href="<{$xoops_url}>/" class="btn btn-primary"><{$smarty.const.THEME_LEARNMORE}></a>
            </div>
        </div>
    </section>
    <{/if}>

    <div class="flex flex-col lg:flex-row gap-6">
        <{include file="$theme_name/tpl/leftBlock.tpl"}>
        <{include file="$theme_name/tpl/content-zone.tpl"}>
        <{include file="$theme_name/tpl/rightBlock.tpl"}>
    </div>

</main>

<{if $xoBlocks.footer_center || $xoBlocks.footer_right || $xoBlocks.footer_left}>
<footer class="bg-base-200 mt-8">
    <div class="container mx-auto px-4 py-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <{foreach item=block from=$xoBlocks.footer_left|default:[]}>
                <div>
                    <{if $block.title}><h4 class="font-bold mb-2"><{$block.title}></h4><{/if}>
                    <div class="prose prose-sm"><{$block.content}></div>
                </div>
            <{/foreach}>
            <{foreach item=block from=$xoBlocks.footer_center|default:[]}>
                <div>
                    <{if $block.title}><h4 class="font-bold mb-2"><{$block.title}></h4><{/if}>
                    <div class="prose prose-sm"><{$block.content}></div>
                </div>
            <{/foreach}>
            <{foreach item=block from=$xoBlocks.footer_right|default:[]}>
                <div>
                    <{if $block.title}><h4 class="font-bold mb-2"><{$block.title}></h4><{/if}>
                    <div class="prose prose-sm"><{$block.content}></div>
                </div>
            <{/foreach}>
        </div>
    </div>
</footer>
<{/if}>

<footer class="footer footer-center p-4 bg-base-300 text-base-content">
    <aside>
        <{$xoops_footer}>
        <a href="https://xoops.org" class="link link-hover" target="_blank" rel="noopener">XOOPS</a>
    </aside>
</footer>

<{if !empty($xoops_isuser)}><{include file="$theme_name/tpl/inboxAlert.tpl"}><{/if}>

</body>
</html>
