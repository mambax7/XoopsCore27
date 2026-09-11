<!DOCTYPE html>
<html lang="<{$xoops_langcode}>">
<head>
    <meta charset="<{$xoops_charset}>"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="robots" content="noindex,nofollow"/>
    <title><{$xoops_sitename}> - <{$title|escape}></title>
    <link rel="stylesheet" type="text/css" media="screen" href="<{xoAppUrl 'browse.php?xoops.css'}>"/>
    <link rel="stylesheet" type="text/css" media="screen" href="<{$xoops_themecss}>"/>
</head>
<body>
<div class="width60 txtcenter" style="margin: 3em auto;">
    <h2><{$title|escape}></h2>
    <{if $start_again}>
        <p class="errorMsg"><{$message|escape}></p>
        <p><a href="<{$login_url}>"><{$lang_startagain}></a></p>
    <{else}>
        <p><{$message|escape}></p>
        <{if $error}><p class="errorMsg"><{$error|escape|nl2br}></p><{/if}>
        <form action="<{$action_url}>" method="post" autocomplete="off">
            <p>
                <label for="xo-2fa-code"><{$lang_code}></label><br>
                <input type="text" id="xo-2fa-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" dir="ltr" pattern="[0-9]{6}" autofocus/>
            </p>
            <details>
                <summary><{$lang_recovery}></summary>
                <p>
                    <label for="xo-2fa-recovery"><{$lang_recovery}></label><br>
                    <input type="text" id="xo-2fa-recovery" name="recovery" inputmode="text" autocomplete="off" maxlength="40" dir="ltr"/><br>
                    <small><{$lang_recovery_hint}></small>
                </p>
            </details>
            <input type="hidden" name="op" value="2fa"/>
            <input type="hidden" name="xoops_2fa" value="1"/>
            <{$token_html}>
            <p><input type="submit" value="<{$lang_submit}>"/></p>
        </form>
        <p><a href="<{$login_url}>"><{$lang_startagain}></a></p>
    <{/if}>
</div>
</body>
</html>
