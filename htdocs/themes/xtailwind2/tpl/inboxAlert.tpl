<{if !$xoops_page|strstr:'viewpmsg' && !$xoops_page|strstr:'readpmsg'}>
    <{xoInboxCount assign='newPms'}>
    <{if isset($newPms) && $newPms > 0}>
    <{* DaisyUI toast + alert. Alpine.js drives auto-hide after 4 seconds. *}>
    <div x-data="{ show: true }"
         x-init="setTimeout(() => show = false, 4000)"
         x-show="show"
         x-transition
         class="toast toast-top toast-end z-50">
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <div>
                <strong><{$smarty.const.THEME_INBOX_ALERT}></strong>
                <span class="badge badge-primary ms-1"><{$newPms}></span>
                <div class="text-xs">
                    <a href="<{$xoops_url}>/viewpmsg.php" class="link"><{$smarty.const.THEME_INBOX_LINK}></a>
                </div>
            </div>
            <button class="btn btn-sm btn-ghost" @click="show = false" aria-label="Close">✕</button>
        </div>
    </div>
    <{/if}>
<{/if}>
