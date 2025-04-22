<div class="d-flex flex-wrap pb-3">
<{foreach from=$adminMenuDomain item=domainBlock}>
    <div class="card card-primary card-outline m-1">
        <div class="card-header">
            <h3 class="card-title"><i class="<{$domainBlock.icon}>"></i> <{$domainBlock.domain}></h3>
        </div>
        <ul class="list-group list-group-flush">
        <{foreach from=$domainBlock.links item=link}>
            <li class="list-group-item">
                <a href="<{$xoops_url}>/modules/system/<{$link.url|escape}>"><{$link.title|escape}></a>
            </li>
        <{/foreach}>
        </ul>
    </div>
<{/foreach}>
</div>
<!--
<div class="x_toolbar">
    <{foreach item=op from=$adminmenu|default:null}>
    <a href="<{$op.link}>">
        <div class="x_tool float-left hoverable" data-toggle="tooltip" title="<{$op.desc}>">
            <div class="x_toolicon">
                <i class="<{$op.icon}>"></i>
            </div>
            <div class="x_tooltext">
                <{$op.title}>
            </div>
        </div>
    </a>
    <{/foreach}>
    <a href="<{xoAppUrl url='modules/system/admin.php'}>">
        <div class="x_tool float-left hoverable">
            <div class="x_toolicon">
                <i class="fa fa-cog"></i>
            </div>
            <div class="x_tooltext">
                <{$smarty.const._AM_SYSTEM_CONFIG}>
            </div>
        </div>
    </a>
    <a href="<{xoAppUrl url='modules/system/help.php'}>">
        <div class="x_tool float-left hoverable">
            <div class="x_toolicon">
                <i class="fa fa-question-circle"></i>
            </div>
            <div class="x_tooltext">
                <{$smarty.const._AM_SYSTEM_HELP}>
            </div>
        </div>
    </a>
</div>-->
