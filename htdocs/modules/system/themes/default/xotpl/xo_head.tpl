<div id="xo-logo-head">
    <div id="main-logo">
        <a class="tooltip" href="<{xoAppUrl 'admin.php'}>" title="<{$smarty.const._OXYGEN_ADMINISTRATION}>"></a>
    </div>
    <div id="xo-headnav">
        <button type="button" id="darkModeToggle" aria-pressed="<{if $dark_mode|default:false}>true<{else}>false<{/if}>" aria-label="<{$smarty.const._OXYGEN_DARK_MODE|default:'Dark mode'|escape}>" title="<{$smarty.const._OXYGEN_DARK_MODE|default:'Dark mode'|escape}>">
            <span class="icon-moon" aria-hidden="true">&#x1F319;</span>
            <span class="icon-sun" aria-hidden="true">&#x2600;&#xFE0F;</span>
        </button>
        <a class="tooltip" href="<{xoAppUrl}>" title="<{$smarty.const._YOURHOME}>"><img src="<{xoImgUrl 'img/home.png'}>" alt="<{$smarty.const._YOURHOME}>"></a>
        <a class="tooltip" href="<{xoAppUrl 'user.php?op=logout'}>" title="<{$smarty.const._LOGOUT}>"><img src="<{xoImgUrl 'img/logout.png'}>" alt="<{$smarty.const._LOGOUT}>"></a>
    </div>
</div>
