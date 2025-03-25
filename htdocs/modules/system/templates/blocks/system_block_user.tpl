<div id="usermenu">
    <{if isset($xoops_isadmin)}>
        <a class="menuTop" href="<{xoAppUrl url='admin.php'}>" title="<{$block.lang_adminmenu}>"><{$block.lang_adminmenu}></a>
        <a href="<{xoAppUrl url='user.php'}>" title="<{$block.lang_youraccount}>"><{$block.lang_youraccount}></a>
    <{else}>
        <a class="menuTop" href="<{xoAppUrl url='user.php'}>" title="<{$block.lang_youraccount}>"><{$block.lang_youraccount}></a>
    <{/if}>
    <a href="<{xoAppUrl url='edituser.php'}>" title="<{$block.lang_editaccount}>"><{$block.lang_editaccount}></a>
    <a href="<{xoAppUrl url='notifications.php'}>" title="<{$block.lang_notifications}>"><{$block.lang_notifications}></a>
    <{if $block.new_messages > 0}>
        <a class="highlight" href="<{xoAppUrl url='viewpmsg.php'}>" title="<{$block.lang_inbox}>"><{$block.lang_inbox}>
            (<strong><{$block.new_messages}></strong>)</a>
    <{else}>
        <a href="<{xoAppUrl url='viewpmsg.php'}>" title="<{$block.lang_inbox}>"><{$block.lang_inbox}></a>
    <{/if}>
    <a href="<{xoAppUrl url='user.php?op=logout'}>" title="<{$block.lang_logout}>"><{$block.lang_logout}></a>
</div>
