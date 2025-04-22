<div class="x_toolbar">
    <{foreach item=op from=$modulenmenu|default:null}>
    <a href="<{$path}><{$op.link}>">
        <div class="x_tool float-left hoverable" data-toggle="tooltip" title="<{$op.title}>">
            <div class="x_toolicon">
            <img src="<{$path}><{$op.icon}>" alt="<{$op.title}>" title="<{$op.title}>">
            </div>
            <div class="x_tooltext">
                <{$op.title}>
            </div>
        </div>
    </a>
    <{/foreach}>
    <{if isset($help)}>
    <a href="<{$helpurl}>">
        <div class="x_tool float-left hoverable" data-toggle="tooltip" title="<{$smarty.const._AM_SYSTEM_HELP}>">
            <div class="x_toolicon">
            <img src="<{$helpicon}>" alt="<{$smarty.const._AM_SYSTEM_HELP}>" title="<{$smarty.const._AM_SYSTEM_HELP}>">
            </div>
            <div class="x_tooltext">
                <{$smarty.const._AM_SYSTEM_HELP}>
            </div>
        </div>
    </a>    
    <{/if}>
</div>
<div class="row">
    <div class="col">
        <{if isset($ret_info)}>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-cogs ic-w mr-1"></i><{$smarty.const._AM_MODULEADMIN_CONFIG}></h3>
                </div>
                <div class="card-body">
                    <{foreach from=$ret_info.items|default:null item=cfg}>
                        <{if $cfg.type == 'error'}>
                            <div class="text-error"><i class="fas fa-times ic-w mr-1"></i><{$cfg.msg}></div>
                        <{/if}>
                        <{if $cfg.type == 'success'}>
                            <div class="text-success"><i class="fas fa-check ic-w mr-1"></i><{$cfg.msg}></div>
                        <{/if}>
                    <{/foreach}>
                </div>
            </div>
        <{/if}>
    </div>
</div>