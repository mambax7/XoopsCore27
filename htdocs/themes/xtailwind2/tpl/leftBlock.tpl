<{if $xoBlocks.canvas_left}>
<aside class="w-full xl:sticky xl:top-28 xl:w-72 xl:flex-shrink-0">
    <div class="space-y-4">
        <{foreach item=block from=$xoBlocks.canvas_left}>
        <section class="rail-panel">
            <{if $block.title}><p class="surface-label"><{$block.title}></p><{/if}>
            <{include file="$theme_name/tpl/blockContent.tpl"}>
        </section>
        <{/foreach}>
    </div>
</aside>
<{/if}>
