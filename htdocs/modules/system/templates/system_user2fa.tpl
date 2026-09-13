<{if $standalone}><!DOCTYPE html>
<html lang="<{$xoops_langcode|escape}>">
<head>
    <meta charset="<{$xoops_charset|escape}>"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="robots" content="noindex,nofollow"/>
    <title><{$xoops_sitename}> - <{$title|escape}></title>
    <link rel="stylesheet" type="text/css" media="screen" href="<{xoAppUrl 'browse.php?xoops.css'}>"/>
    <link rel="stylesheet" type="text/css" media="screen" href="<{$xoops_themecss|escape}>"/>
</head>
<body><{/if}>
<div class="xo-2fa-challenge<{if $standalone}> width60 txtcenter" style="margin: 3em auto;<{/if}>">
    <h2><{$title|escape}></h2>
    <{if $start_again}>
        <p class="errorMsg"><{$message|escape}></p>
        <p><a href="<{$login_url|escape}>"><{$lang_startagain|escape}></a></p>
    <{else}>
        <p><{$message|escape}></p>
        <{if $error}><p class="errorMsg"><{$error|escape|nl2br}></p><{/if}>
        <{$form}>
        <{if $send_form}><{$send_form}><{/if}>
        <p><a href="<{$login_url|escape}>"><{$lang_startagain|escape}></a></p>
    <{/if}>
</div>
<{if $standalone}></body>
</html><{/if}>
