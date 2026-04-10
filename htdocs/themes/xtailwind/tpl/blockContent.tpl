<{if !empty($xoops_isadmin)}>
<a href="<{xoAppUrl '/modules/system/admin.php?fct=blocksadmin&op=edit&bid='}><{$block.id}>"
   class="btn btn-xs btn-warning mb-2"
   title="<{$smarty.const.THEME_TOOLBAR_EDIT_THIS_BLOCK}>">
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
</a>
<{/if}>
<div class="block-content"><{$block.content}></div>
