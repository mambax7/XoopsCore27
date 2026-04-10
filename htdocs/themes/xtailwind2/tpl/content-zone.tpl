<section class="min-w-0 flex-1 space-y-6">
    <{if isset($xoops_contents)}>
        <article class="content-panel xoops-content">
            <{$xoops_contents}>
        </article>
    <{/if}>

    <{if $xoBlocks.page_topcenter || $xoBlocks.page_topleft || $xoBlocks.page_topright}>
    <section class="grid gap-4 md:grid-cols-2">
        <{foreach item=block from=$xoBlocks.page_topcenter|default:[]}>
            <section class="content-panel md:col-span-2">
                <{if $block.title}><p class="surface-label"><{$block.title}></p><{/if}>
                <{include file="$theme_name/tpl/blockContent.tpl"}>
            </section>
        <{/foreach}>
        <{foreach item=block from=$xoBlocks.page_topleft|default:[]}>
            <section class="content-panel">
                <{if $block.title}><p class="surface-label"><{$block.title}></p><{/if}>
                <{include file="$theme_name/tpl/blockContent.tpl"}>
            </section>
        <{/foreach}>
        <{foreach item=block from=$xoBlocks.page_topright|default:[]}>
            <section class="content-panel">
                <{if $block.title}><p class="surface-label"><{$block.title}></p><{/if}>
                <{include file="$theme_name/tpl/blockContent.tpl"}>
            </section>
        <{/foreach}>
    </section>
    <{/if}>
</section>
