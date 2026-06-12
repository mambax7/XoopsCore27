<!doctype html>
<html lang="<{$xoops_langcode}>" dir="<{$xoops_text_direction|default:'ltr'}>" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="<{$xoops_charset}>">
    <meta name="robots" content="noindex, nofollow" />
    <title><{$xoops_sitename|escape:'html':'UTF-8'}></title>
    <{section name=item loop=$headItems}>
    <{$headItems[item]}>
    <{/section}>

    <link href="<{$xoops_url}>/favicon.ico" rel="shortcut icon">

    <{include file="$themePath/tpl/xswatchCss.tpl" assign="xswatchCss"}>
    <link rel="stylesheet" type="text/css" href="<{$themeUrl}><{$xswatchCss}>/xoops.css">
    <link rel="stylesheet" type="text/css" href="<{$themeUrl}><{$xswatchCss}>/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="<{$themeUrl}>css/dark-mode.css">

    <script>
    (function() {
        let stored = null;
        try { stored = localStorage.getItem('xswatch-theme'); } catch (e) {}
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = stored || (prefersDark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-bs-theme', theme);
    })();
    </script>
    <script src="<{$xoops_url}>/browse.php?Frameworks/jquery/jquery.js"></script>
    <script src="<{$themeUrl}>js/bootstrap.bundle.min.js"></script>

    <{if !empty($closeHead)}>
</head>
<body id="xswatch-popup-body">
<{/if}>
