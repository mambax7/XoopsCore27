<{if !empty($xoops_isadmin)}>
<a href="<{xoAppUrl '/modules/system/admin.php?fct=blocksadmin&op=edit&bid='}><{$block.id}>"
   class="toolbar-block-edit btn btn-xs btn-warning mb-3 rounded-full"
   title="<{$smarty.const.THEME_TOOLBAR_EDIT_THIS_BLOCK}>">
    Edit block
</a>
<{/if}>
<div class="block-content"><{$block.content}></div>
