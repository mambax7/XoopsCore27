<{foreach item=item from=$blocks|default:null}>
    <{if $item.side == $side}>
        <div id="blk_<{$item.bid}>" bid="<{$item.bid}>" side="<{$item.side}>" order="<{$item.weight}>"
             class="xo-block ui-widget ui-widget-content ui-corner-all">
            <div class="xo-blocktitle ui-corner-all">
        <span class="spacer">
            <img class="xo-imgmini" src="<{xoAdminIcons url='block.png'}>" alt="<{$smarty.const._AM_SYSTEM_BLOCKS_DRAG}>"
                 title="<{$smarty.const._AM_SYSTEM_BLOCKS_DRAG}>"/>
        </span>
<{$item.title}><{if $item.block_type == 'D'}> (<{$item.bid}>)<{/if}>
            </div>
            <div class="xo-blockaction xo-actions"><img id="loading_img<{$item.bid}>" src="./images/mimetypes/spinner.gif" style="display:none;"
                                                        title="<{$smarty.const._AM_SYSTEM_LOADING}>" alt="<{$smarty.const._AM_SYSTEM_LOADING}>"/><img
                        class="tooltip" id="img<{$item.bid}>"
                        onclick="system_setStatus( { fct: 'blocksadmin', op: 'display', bid: <{$item.bid}>, visible: <{if $item.visible}>0<{else}>1<{/if}> }, 'img<{$item.bid}>', 'admin.php' )"
                        src="<{if $item.visible}><{xoAdminIcons url='success.png'}><{else}><{xoAdminIcons url='cancel.png'}><{/if}>"
                        alt="<{if $item.visible}><{$smarty.const._AM_SYSTEM_BLOCKS_HIDE}><{else}><{$smarty.const._AM_SYSTEM_BLOCKS_DISPLAY}><{/if}><{$item.name}>"
                        title="<{if $item.visible}><{$smarty.const._AM_SYSTEM_BLOCKS_HIDE}><{else}><{$smarty.const._AM_SYSTEM_BLOCKS_DISPLAY}><{/if}><{$item.name}>"/>
                <a class="tooltip" href="admin.php?fct=blocksadmin&amp;op=edit&amp;bid=<{$item.bid}>" title="<{$smarty.const._EDIT}>">
                    <img src="<{xoAdminIcons url='edit.png'}>" alt="<{$smarty.const._EDIT}>"/></a>
                <{if $item.block_type != 'S'}>
                    <a class="tooltip" href="admin.php?fct=blocksadmin&amp;op=delete&amp;bid=<{$item.bid}>" title="<{$smarty.const._DELETE}>">
                        <img src="<{xoAdminIcons url='delete.png'}>" alt="<{$smarty.const._DELETE}>"/></a>
                <{/if}>
                <a class="tooltip" href="admin.php?fct=blocksadmin&amp;op=clone&amp;bid=<{$item.bid}>" title="<{$smarty.const._AM_SYSTEM_BLOCKS_CLONE}>">
                    <img src="<{xoAdminIcons url='clone.png'}>" alt="<{$smarty.const._AM_SYSTEM_BLOCKS_CLONE}>"/></a>
            </div>
        </div>
    <{/if}>
<{/foreach}>
