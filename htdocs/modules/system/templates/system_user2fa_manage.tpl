<div class="xo-2fa-manage">
    <h1><{$labels.title|escape}></h1>
    <p><{$account|escape}></p>
    <{if $message}><p role="status"><{$message|escape}></p><{/if}>
    <{if $error}><p role="alert"><{$error|escape}></p><{/if}>
    <{if $paused}><p><{$labels.paused|escape}></p><{/if}>
    <{if $http_warning}><p role="alert"><{$labels.http|escape}></p><{/if}>
    <{if $codes}>
        <h2><{$labels.codes|escape}></h2>
        <p><{$labels.codes_help|escape}></p>
        <ul dir="ltr"><{foreach $codes as $recovery_code}><li><code><{$recovery_code|escape}></code></li><{/foreach}></ul>
    <{elseif $installed}>
        <{if $admin_reset}>
            <p><{$labels.reset_help|escape}></p>
        <{elseif $enrolled}>
            <p><{if $by_email}><{$labels.enabled_email|escape}><{else}><{$labels.enabled|escape}><{/if}></p>
        <{elseif $confirm_setup && $by_email}>
            <p><{$labels.email_step|escape}></p>
        <{else}>
            <{if $secret}>
                <ol>
                    <li><{$labels.step_app|escape}></li>
                    <li><{$labels.step_add|escape}></li>
                    <li><{$labels.step_code|escape}></li>
                </ol>
                <{if $qr}><p><img src="<{$qr|escape}>" alt="<{$labels.scan|escape}>" width="256" height="256"></p><{/if}>
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
            <{if !$admin_reset && !$enrolled && !$confirm_setup}>
                <p><{$labels.choose|escape}></p>
                <p><{$labels.email_help|escape}></p>
            <{/if}>
            <{if !$admin_reset && ($enrolled || $confirm_setup)}>
                <p><label for="factor-code"><{$lang_code|escape}></label><br>
                <input id="factor-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" dir="ltr"<{if $confirm_setup}> aria-describedby="factor-code-help"<{/if}>>
                <{if $confirm_setup}><br><small id="factor-code-help"><{if $by_email}><{$labels.code_help_email|escape}><{else}><{$labels.code_help|escape}><{/if}></small><{/if}></p>
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
                <button type="submit" name="action" value="confirm"><{if $by_email}><{$labels.confirm_email|escape}><{else}><{$labels.confirm|escape}><{/if}></button>
            <{else}>
                <button type="submit" name="action" value="begin"><{$labels.enable|escape}></button>
                <button type="submit" name="action" value="begin_email"><{$labels.enable_email|escape}></button>
            <{/if}>
            </p>
        </form>
        <{if $by_email && !$admin_reset}>
        <form action="<{$action_url|escape}>" method="post">
            <{$token_html}>
            <input type="hidden" name="op" value="<{if $enrolled}>2fa_manage<{else}>2fa_setup<{/if}>">
            <p><button type="submit" name="action" value="send"><{$labels.send|escape}></button></p>
        </form>
        <{/if}>
    <{/if}>
    <p><a href="<{$back_url|escape}>"><{$labels.back|escape}></a></p>
</div>
