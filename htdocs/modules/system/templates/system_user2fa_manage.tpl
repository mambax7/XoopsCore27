<!DOCTYPE html>
<html lang="<{$langcode|escape}>" dir="<{$direction|default:'ltr'|escape}>">
<head>
    <meta charset="<{$charset|escape}>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><{$labels.title|escape}></title>
    <link rel="stylesheet" href="<{xoAppUrl 'browse.php?xoops.css'}>">
</head>
<body>
<main style="max-width: 42em; margin: 2em auto; padding: 1em;">
    <h1><{$labels.title|escape}></h1>
    <p><{$account|escape}></p>
    <{if $message}><p role="status"><{$message|escape}></p><{/if}>
    <{if $error}><p role="alert"><{$error|escape}></p><{/if}>
    <{if $paused}><p><{$labels.paused|escape}></p><{/if}>
    <{if $codes}>
        <h2><{$labels.codes|escape}></h2>
        <p><{$labels.codes_help|escape}></p>
        <ul dir="ltr"><{foreach $codes as $recovery_code}><li><code><{$recovery_code|escape}></code></li><{/foreach}></ul>
    <{elseif $installed}>
        <{if $admin_reset}>
            <p><{$labels.reset_help|escape}></p>
        <{elseif $enrolled}>
            <p><{$labels.enabled|escape}></p>
        <{else}>
            <{if $http_warning}><p role="alert"><{$labels.http|escape}></p><{/if}>
            <{if $secret}>
                <p><{$labels.scan|escape}></p>
                <{if $qr}><img src="<{$qr|escape}>" alt="<{$labels.scan|escape}>" width="256" height="256"><{/if}>
                <p><{$labels.manual|escape}>: <code dir="ltr"><{$secret|escape}></code></p>
            <{/if}>
        <{/if}>
        <form action="<{$action_url|escape}>" method="post" autocomplete="off">
            <{$token_html}>
            <input type="hidden" name="op" value="<{if $admin_reset}>users_2fa_reset<{elseif $enrolled}>2fa_manage<{else}>2fa_setup<{/if}>">
            <{if $admin_reset}><input type="hidden" name="uid" value="<{$uid|escape}>"><{/if}>
            <{if !$confirm_setup || $admin_reset}>
                <p><label for="factor-password"><{$labels.password|escape}></label><br>
                <input id="factor-password" name="password" type="password" autocomplete="current-password" required></p>
            <{/if}>
            <{if !$admin_reset && ($enrolled || $confirm_setup)}>
                <p><label for="factor-code"><{$lang_code|escape}></label><br>
                <input id="factor-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" dir="ltr"></p>
                <{if $enrolled}>
                    <details><summary><{$lang_recovery|escape}></summary>
                        <p><label for="factor-recovery"><{$lang_recovery|escape}></label><br>
                        <input id="factor-recovery" name="recovery" type="text" inputmode="text" autocomplete="off" maxlength="40" dir="ltr"></p>
                    </details>
                <{/if}>
            <{/if}>
            <p>
            <{if $admin_reset}>
                <button type="submit" name="action" value="reset"><{$labels.reset|escape}></button>
            <{elseif $enrolled}>
                <button type="submit" name="action" value="regenerate"><{$labels.regenerate|escape}></button>
                <button type="submit" name="action" value="disable"><{$labels.disable|escape}></button>
            <{elseif $confirm_setup}>
                <button type="submit" name="action" value="confirm"><{$labels.confirm|escape}></button>
            <{else}>
                <button type="submit" name="action" value="begin"><{$labels.enable|escape}></button>
            <{/if}>
            </p>
        </form>
    <{/if}>
    <p><a href="<{$back_url|escape}>"><{$labels.back|escape}></a></p>
</main>
</body>
</html>
