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
        <{$form}>
        <{if $send_form}><{$send_form}><{/if}>
    <{/if}>
    <p><a href="<{$back_url|escape}>"><{$labels.back|escape}></a></p>
</div>
