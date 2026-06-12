// Read the control-panel request token so block AJAX actions can submit it.
function xoBlocksToken() {
    var $t = $("input[name='XOOPS_TOKEN_REQUEST']").first();
    return $t.length ? $t.val() : '';
}

$(document).ready(
    function(){
        // Controls Drag + Drop
        $('.xo-blocksection').sortable({
                accept: 'xo-block',
                cancel: '.xo-title',
                items: '.xo-block',
                connectWith: '.xo-blocksection',
                update: function(event, ui) {
                    var list = $(this).sortable( 'serialize');
                    $.post( 'admin.php?fct=blocksadmin&op=order', list + '&XOOPS_TOKEN_REQUEST=' + encodeURIComponent(xoBlocksToken()) );
                },
                receive: function(event, ui) {
                    var side = $(this).attr('side');
                    var bid = $(ui.item).attr('bid');
                    var list = $(this).sortable( 'serialize');

                    $.post( 'admin.php', { fct: 'blocksadmin', op: 'drag', bid: bid, side: side, XOOPS_TOKEN_REQUEST: xoBlocksToken() } );

                    $.post( 'admin.php?fct=blocksadmin&op=order', list + '&XOOPS_TOKEN_REQUEST=' + encodeURIComponent(xoBlocksToken()) );

                }
            }
        );
        $(".xo-blocksection").disableSelection();

        $('.xo-blockhide').sortable({
                accept: 'xo-block',
                cancel: '.xo-title',
                items: '.xo-block',
                connectWith: '.xo-blocksection'/*,
                update: function(event, ui) {
                    var list = $(this).sortable( 'serialize');
                    $.post( 'admin.php?fct=blocksadmin&op=order', list + '&XOOPS_TOKEN_REQUEST=' + encodeURIComponent(xoBlocksToken()) );
                },
                receive: function(event, ui) {
                    var side = $(this).attr('side');
                    var bid = $(ui.item).attr('bid');
                    var list = $(this).sortable( 'serialize');

                    $.post( 'admin.php', { fct: 'blocksadmin', op: 'drag', bid: bid, side: side, XOOPS_TOKEN_REQUEST: xoBlocksToken() } );

                    $.post( 'admin.php?fct=blocksadmin&op=order', list + '&XOOPS_TOKEN_REQUEST=' + encodeURIComponent(xoBlocksToken()) );

                }*/
            }
        );
        $(".xo-blockhide").disableSelection();
    }
);
