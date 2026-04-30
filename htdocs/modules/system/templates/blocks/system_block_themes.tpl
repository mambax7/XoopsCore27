<div class="txtcenter">
    <form action="<{xoAppUrl 'index.php'}>" method="post" onsubmit="this.elements['xoops_theme_redirect'].value=location.pathname+location.search+location.hash">
        <input type="hidden" name="xoops_theme_redirect" value="">
        <{$block.theme_select}>
    </form>
</div>
