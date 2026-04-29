<div class="xoops-theme-select">
    <form class="form-horizontal" role="form" action="<{xoAppUrl 'index.php'}>" method="post">
        <input type="hidden" name="xoops_theme_redirect" value="<{$block.theme_redirect|default:''|escape:'html'}>">
        <{$block.theme_select}>
    </form>
</div>
