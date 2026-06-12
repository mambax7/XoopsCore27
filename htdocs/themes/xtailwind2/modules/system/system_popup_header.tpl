<!doctype html>
<html lang="<{$xoops_langcode}>" dir="<{$xoops_text_direction|default:'ltr'}>" data-theme="atelier">
<head>
    <meta charset="<{$xoops_charset}>">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (function() {
            var stored = null;
            try { stored = localStorage.getItem('xtailwind2-theme'); } catch (e) {}
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            var theme = stored || (prefersDark ? 'noctis' : 'atelier');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <title><{$xoops_sitename|escape:'html':'UTF-8'}></title>
    <{section name=item loop=$headItems}>
        <{$headItems[item]}>
    <{/section}>
    <link rel="stylesheet" type="text/css" href="<{$themeUrl}>css/styles.css">
    <link rel="stylesheet" type="text/css" media="all" href="<{$xoops_themecss}>">
    <link rel="stylesheet" type="text/css" href="<{$themeUrl}>css/dark-mode.css">
    <script src="<{$xoops_url}>/browse.php?Frameworks/jquery/jquery.js"></script>

    <{if $closeHead|default:false}>
</head>
<body class="min-h-screen bg-base-100 p-4 text-base-content antialiased">
<{/if}>
