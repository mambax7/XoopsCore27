<{include file="db:system_header.tpl"}>
<div class="row">
    <div class="col-3">
        <{if !empty($help)}>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><{$modname}></h3>
            </div>
            <div class="card-body">
                <{foreach item=helpitem from=$help|default:null}>
                <div class="<{cycle values='odd, even'}>"><a href="<{$helpitem.link}>"><{$helpitem.name}></a></div>
                <{/foreach}>
            </div>
        </div>
        <{/if}>
        <{if !empty($list_mods)}>
        <div class="card">
            <{foreach item=row from=$list_mods|default:null}>
            <div class="card-header">
                <h3 class="card-title"><{$row.name}></h3>
            </div>
            <div class="card-body">
                <{foreach item=list from=$row.help_page|default:null}>
                <div class="<{cycle values='odd, even'}>" title="<{$list.name}>"><a href="<{$list.link}>"><{$list.name}></a></div>
                <{/foreach}>
            </div>
            <{/foreach}>
        </div>
        <{/if}>   
    </div>
    <div class="col-9">
        <{$helpcontent}>
    </div>    
</div>
