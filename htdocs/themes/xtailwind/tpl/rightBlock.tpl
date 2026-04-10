<{if $xoBlocks.canvas_right}>
<aside class="w-full lg:w-64 lg:flex-shrink-0 space-y-4">
    <{foreach item=block from=$xoBlocks.canvas_right}>
    <div class="card bg-base-200 shadow">
        <div class="card-body p-4">
            <{if $block.title}><h4 class="card-title text-base"><{$block.title}></h4><{/if}>
            <{include file="$theme_name/tpl/blockContent.tpl"}>
        </div>
    </div>
    <{/foreach}>
</aside>
<{/if}>
