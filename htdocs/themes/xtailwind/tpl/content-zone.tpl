<{* Content zone — flex-1 fills the space next to sidebars *}>
<section class="flex-1 min-w-0">

    <{if isset($xoops_contents)}>
        <div class="prose max-w-none"><{$xoops_contents}></div>
    <{/if}>

    <{if $xoBlocks.page_topcenter || $xoBlocks.page_topleft || $xoBlocks.page_topright}>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
        <{foreach item=block from=$xoBlocks.page_topcenter|default:[]}>
            <div class="card bg-base-200 shadow col-span-full">
                <{if $block.title}><div class="card-body"><h4 class="card-title"><{$block.title}></h4><{/if}>
                <{include file="$theme_name/tpl/blockContent.tpl"}>
                <{if $block.title}></div><{/if}>
            </div>
        <{/foreach}>
        <{foreach item=block from=$xoBlocks.page_topleft|default:[]}>
            <div class="card bg-base-200 shadow">
                <{if $block.title}><div class="card-body"><h4 class="card-title"><{$block.title}></h4><{/if}>
                <{include file="$theme_name/tpl/blockContent.tpl"}>
                <{if $block.title}></div><{/if}>
            </div>
        <{/foreach}>
        <{foreach item=block from=$xoBlocks.page_topright|default:[]}>
            <div class="card bg-base-200 shadow">
                <{if $block.title}><div class="card-body"><h4 class="card-title"><{$block.title}></h4><{/if}>
                <{include file="$theme_name/tpl/blockContent.tpl"}>
                <{if $block.title}></div><{/if}>
            </div>
        <{/foreach}>
    </div>
    <{/if}>
</section>
