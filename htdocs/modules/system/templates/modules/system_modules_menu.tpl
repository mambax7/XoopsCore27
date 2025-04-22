<div class="card">
    <div class="p-1 small ">
        <{foreach from=$menutop|default:null key=url item=name}>
        <a class="" href="<{$url}>"><{$name}></a>|
        <{/foreach}>
        <div class="float-right">
        <span class="text-bold"><{$module_name}></span>&nbsp;:&nbsp;<{$page}>
        </div>
    </div>
</div>
<div class="card card-primary card-outline card-tabs">
    <div class="card-header p-0 pt-1">
        <ul class="nav nav-tabs" id="custom-tabs-one-tab" role="tablist">
            <{foreach from=$menutabs|default:null key=url item=name}>
            <li class="nav-item">
            <a class="nav-link <{if $page == $name}>active<{/if}>" href="<{$xoops_url}>/modules/<{$module_dirname}>/<{$url}>" role="tab"><{$name}></a>
            </li>
            <{/foreach}>
        </ul>
    </div>
</div>
