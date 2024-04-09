<div class="card">
    <div class="card-header text-center">
        <a href="<{$xoops_url}>" target="_blank" class="text-dark font-weight-bold text-decoration-none">
            <{$lang_modulename}> - <{$smarty.const._MD_LEXIKON_TERMOFTHEDAY}>
        </a>
    </div>
    <div class="card-body">
    <{if isset($multicats) && $multicats == 1}>
            <div class="mb-3 small">
                <{$smarty.const._MD_LEXIKON_ENTRYCATEGORY}>
                <a href="<{$xoops_url}>/modules/<{$lang_moduledirname}>/category.php?categoryID=<{$syndication.id}>" target="_blank" class="text-dark font-weight-bold text-decoration-none">
                    <{$syndication.categoryname}>
                </a>
        </div>
    <{/if}>
        <h4 class="mb-0"><{$syndication.term}></h4>
        <p class="small"><{$syndication.definition}></p>
    </div>
    <div class="card-footer text-right small">
        <a href="javascript:location.reload()"><{$smarty.const._MD_LEXIKON_RANDOMIZE}></a><br>
        <{$smarty.const._MD_LEXIKON_POWER}> <a href="<{$xoops_url}>" target="_blank"><{$lang_sitename}></a>
    </div>
</div>
