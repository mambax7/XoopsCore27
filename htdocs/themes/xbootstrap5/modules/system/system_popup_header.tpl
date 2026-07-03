<!doctype html>
<html lang="<{$xoops_langcode}>" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="<{$xoops_charset}>">
    <meta name="robots" content="noindex, nofollow"/>
    <script>
        (function() {
            var stored = null;
            try { stored = localStorage.getItem('theme'); } catch (e) {}
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            var theme = stored || (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    <title><{$xoops_sitename|escape:'html':'UTF-8'}></title>
    <{section name=item loop=$headItems}>
        <{$headItems[item]}>
    <{/section}>
    <link rel="stylesheet" type="text/css" href="<{$themeUrl}>css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="<{$themeUrl}>css/xoops.css">
    <link rel="stylesheet" type="text/css" href="<{$themeUrl}>css/reset.css">
    <link rel="stylesheet" type="text/css" href="<{$themeUrl}>css/dark-mode.css">
    <script src="<{$xoops_url}>/browse.php?Frameworks/jquery/jquery.js"></script>
    <script src="<{$themeUrl}>js/bootstrap.min.js"></script>

    <{if $closeHead|default:false}>
</head>
<body style="margin:1em;">
<{/if}>
