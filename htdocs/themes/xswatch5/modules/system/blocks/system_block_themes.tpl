<div class="xoops-theme-select">
    <form action="<{xoAppUrl 'index.php'}>" method="post" onsubmit="this.elements['xoops_theme_redirect'].value=location.pathname+location.search+location.hash">
        <input type="hidden" name="xoops_theme_redirect" value="">
        <div class="form-group">
        <{$block.theme_select}>
        </div>
    </form>
</div>
