<div class="usermenu">
    <ul class="nav flex-column">
        <{if isset($xoops_isadmin)}>
            <li class="nav-item"><a class="nav-link" href="<{xoAppUrl url='admin.php'}>" title="<{$block.lang_adminmenu}>"><span class="fa fa-wrench"></span><{$block.lang_adminmenu}></a>
            </li>
            <li class="nav-item"><a class="nav-link" href="<{xoAppUrl url='user.php'}>" title="<{$block.lang_youraccount}>"><span class="fa fa-user-o"></span><{$block.lang_youraccount}></a>
            </li>
        <{else}>
            <li class="nav-item"><a class="nav-link menuTop" href="<{xoAppUrl url='user.php'}>" title="<{$block.lang_youraccount}>"><span class="fa fa-user-o"></span><{$block.lang_youraccount}></a>
            </li>
        <{/if}>
        <li class="nav-item"><a class="nav-link" href="<{xoAppUrl url='edituser.php'}>" title="<{$block.lang_editaccount}>"><span class="fa fa-pencil"></span><{$block.lang_editaccount}></a>
        </li>
        <li class="nav-item"><a class="nav-link" href="<{xoAppUrl url='notifications.php'}>" title="<{$block.lang_notifications}>"><span class="fa fa-info"></span><{$block.lang_notifications}></a>
        </li>
        <{xoInboxCount assign='unread_count'}>
        <{if isset($unread_count) && $unread_count > 0}>
            <li class="nav-item"><a class="nav-link info" href="<{xoAppUrl url='viewpmsg.php'}>" title="<{$block.lang_inbox}>"><span class="fa fa-envelope-o"></span><{$block.lang_inbox}>
                    <span class="badge badge-primary badge-pill"><{$unread_count}></span></a></li>
        <{else}>
            <li class="nav-item"><a class="nav-link" href="<{xoAppUrl url='viewpmsg.php'}>" title="<{$block.lang_inbox}>"><span class="fa fa-envelope-open-o"></span><{$block.lang_inbox}></a>
            </li>
        <{/if}>
        <li class="nav-item"><a class="nav-link" href="<{xoAppUrl url='user.php?op=logout'}>" title="<{$block.lang_logout}>"><span class="fa fa-sign-out"></span><{$block.lang_logout}></a>
        </li>
    </ul>
</div>
