<div class="tdmdownloads">

    <div class="breadcrumb"><{$navigation|replace:'<img src="assets/images/deco/arrow.gif" alt="arrow">':'&nbsp;/&nbsp;'}></div>

    <div class="alert alert-warning">
        <ul>
            <li><{$smarty.const._MD_TDMDOWNLOADS_BROKENFILE_FORSECURITY}></li>
            <li><{$smarty.const._MD_TDMDOWNLOADS_BROKENFILE_THANKSFORHELP}></li>
        </ul>
    </div>

    <{if $message_erreur != ''}>
        <div class="alert alert-error"><{$message_erreur}></div>
    <{/if}>
    <div class="mb-3"><{$themeForm}></div>
</div>
