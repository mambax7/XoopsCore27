<div id="xo-logo-head">
    <div id="main-logo">
        <a class="tooltip" href="<{xoAppUrl 'admin.php'}>" title="<{$smarty.const._OXYGEN_ADMINISTRATION}>">
            <img src="<{xoImgUrl 'images/logo-xoops.png'}>" alt="" id="xoops-logo">
        </a>
    </div>
    <ul id="xo-headnav">
        <li>
            <button type="button" id="darkModeToggle" aria-pressed="<{if $dark_mode|default:false}>true<{else}>false<{/if}>" title="<{$smarty.const._OXYGEN_DARK_MODE|default:'Dark mode'|escape}>">
                <span class="fa-solid fa-moon" aria-hidden="true"></span>
                <{$smarty.const._OXYGEN_DARK_MODE|default:'Dark mode'|escape}>
            </button>
        </li>
        <li>
            <a class="tooltip" href="<{xoAppUrl}>" title="<{$smarty.const._YOURHOME}>">
                <span class="fa-solid fa-home"></span>
                <{$smarty.const._YOURHOME}>
            </a>
        </li>
        <li>
            <a class="tooltip" href="<{xoAppUrl 'user.php?op=logout'}>" title="<{$smarty.const._LOGOUT}>">
                <span class="fa-solid fa-right-from-bracket"></span>
                <{$smarty.const._LOGOUT}>
            </a>
        </li>
    </ul>
</div>
