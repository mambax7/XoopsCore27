<{include file="db:system_header.tpl"}>
<{if !empty($install_mods)}>
    <script type="text/javascript">
        IMG_ON = "<{xoAdminIcons url='success.png'}>";
        IMG_OFF = "<{xoAdminIcons url='cancel.png'}>";
    </script>
    <div class="card">
        <div class="card-header">
            <img class="cursorpointer" onclick="system_moduleLargeView();" src="<{xoAdminIcons url='view_large.png'}>"
                alt="<{$smarty.const._AM_SYSTEM_MODULES_VIEWLARGE}>"
                title="<{$smarty.const._AM_SYSTEM_MODULES_VIEWLARGE}>" />
            <img class="cursorpointer" onclick="system_moduleListView();" src="<{xoAdminIcons url='view_small.png'}>"
                alt="<{$smarty.const._AM_SYSTEM_MODULES_VIEWLINE}>"
                title="<{$smarty.const._AM_SYSTEM_MODULES_VIEWLINE}>" />
            <div class="card-tools">
                <a class="btn btn-sm btn-secondary" href="admin.php?fct=modulesadmin&amp;op=installlist"
                    title="<{$smarty.const._AM_SYSTEM_MODULES_TOINSTALL}>">
                    <i class="fa fa-plus-circle ic-w mr-1"></i>
                    <{$smarty.const._AM_SYSTEM_MODULES_TOINSTALL}>&nbsp;<span class="badge badge-light"><{$toinstall_nb}></span>
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <form action="admin.php" method="post" name="moduleadmin">
                <table id="xo-module-sort" class="table table-bordered table-striped" cellspacing="1">
                    <thead class="table-head">
                        <tr class="text-center">
                            <th>
                                <{$smarty.const._AM_SYSTEM_MODULES_MODULE}>
                            </th>
                            <th>
                                <{$smarty.const._AM_SYSTEM_MODULES_VERSION}>
                            </th>
                            <th>
                                <{$smarty.const._AM_SYSTEM_MODULES_LASTUP}>
                            </th>
                            <th>
                                <{$smarty.const._AM_SYSTEM_MODULES_ACTIVE}>
                            </th>
                            <th>
                                <{$smarty.const._AM_SYSTEM_MODULES_MENU}>
                            </th>
                            <th>
                                <{$smarty.const._AM_SYSTEM_MODULES_ACTION}>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <{foreach item=row from=$install_mods|default:null}>
                                <{if isset($row.dirname) && $row.dirname == 'system'}>
                                        <tr class="text-center mdb-color lighten-5">
                                            <td>
                                                <div class="row justify-content-center mb-1">
                                                    <div class="col">
                                                        <a class="xo-logonormal"
                                                            href="<{$xoops_url}>/modules/<{$row.dirname}>/<{$row.adminindex}>"
                                                            title="<{$row.name}>">
                                                            <img src="<{$xoops_url}>/modules/<{$row.dirname}>/<{$row.image}>"
                                                                alt="<{$row.name}>" />
                                                        </a>
                                                    </div>
                                                </div>
                                                <div class="row justify-content-center">
                                                    <div class="xo-modsimages">
                                                        <div class="row justify-content-center">
                                                            <div class="col">

                                                                <div class="input-group">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text xo-mods" display="none">
                                                                            <a class=""
                                                                                href="<{$xoops_url}>/modules/<{$row.dirname}>/<{$row.adminindex}>"
                                                                                title="<{$row.name}>">
                                                                                <img class="xo-mods hide"
                                                                                    src="<{xoAdminIcons url='applications.png'}>"
                                                                                    alt="<{$row.name}>"
                                                                                    title="<{$row.name}>" />
                                                                            </a>
                                                                        </span>
                                                                    </div>
                                                                    <input class="form-control" type="text"
                                                                        name="newname[<{$row.mid}>]" value="<{$row.name}>"
                                                                        maxlength="150" size="20" />
                                                                    <input type="hidden" name="oldname[<{$row.mid}>]"
                                                                        value="<{$row.name}>" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <{if  isset($row.warning_update) && $row.warning_update == 1}>
                                                        <strong class="text-red">
                                                            <{$row.version}>
                                                        </strong>
                                                    <{else}>
                                                            <{$row.version}>
                                                                <{$row.module_status|default:''}>
                                                                <{/if}>
                                            </td>
                                            <td>
                                                <{$row.last_update}>
                                            </td>
                                            <td class="xo-modsimages"></td>
                                            <td class="xo-modsimages"></td>
                                            <td class="xo-modsimages">
                                                <{if isset($row.isactive) && $row.isactive == 1}>
                                                        <a class=""
                                                            href="<{$xoops_url}>/modules/system/admin.php?fct=modulesadmin&amp;op=update&amp;module=<{$row.dirname}>"
                                                            title="<{$smarty.const._AM_SYSTEM_MODULES_UPDATE}>">
                                                            <{if isset($row.warning_update) && $row.warning_update == 1}>
                                                                    <img src="<{xoAdminIcons url='messagebox_warning.png'}>"
                                                                        alt="<{$smarty.const._AM_SYSTEM_MODULES_UPDATE}>" />
                                                                <{else}>
                                                                        <img src="<{xoAdminIcons url='reload.png'}>"
                                                                            alt="<{$smarty.const._AM_SYSTEM_MODULES_UPDATE}>" />
                                                                    <{/if}>
                                                        </a>
                                                    <{/if}>
                                                        <img class="cursorpointer " data-toggle="modal"
                                                            data-target="#modal-<{$row.mid}>"
                                                            src="<{xoAdminIcons url='info.png'}>" alt="<{$smarty.const._INFO}>"
                                                            title="<{$smarty.const._INFO}>" />
                                                        <input type="hidden" name="module[]" value="<{$row.mid}>" />
                                            </td>
                                        </tr>
                                    <{/if}>
                                    <{/foreach}>
                                        <tr class="table-head">
                                            <td colspan="6"></td>
                                        </tr>
                    </tbody>
                    <tbody class="xo-module">
                        <{foreach item=row from=$install_mods|default:null}>
                                <{if isset($row.dirname) && $row.dirname != 'system' && $row.hasmain}>
                                        <tr id="mod_<{$row.mid}>"
                                            class="<{if isset($row.dirname) && $row.dirname == 'system'}>xo-system <{/if}>text-center <{cycle values='odd, even'}>">
                                            <td>
                                                <div class="row justify-content-center mb-1">
                                                    <div class="col">
                                                        <{if (isset($row.hasadmin) && $row.hasadmin == 1) && (isset($row.isactive) && $row.isactive == 1)}>
                                                                <a class="xo-logonormal "
                                                                    href="<{$xoops_url}>/modules/<{$row.dirname}>/<{$row.adminindex}>"
                                                                    title="<{$row.name}>">
                                                                    <img src="<{$xoops_url}>/modules/<{$row.dirname}>/<{$row.image}>"
                                                                        alt="<{$row.name}>" />
                                                                </a>
                                                            <{else}>
                                                                    <img class="xo-logonormal "
                                                                        src="<{$xoops_url}>/modules/<{$row.dirname}>/<{$row.image}>"
                                                                        alt="<{$row.name}>" title="<{$row.name}>" />
                                                                <{/if}>
                                                    </div>
                                                </div>

                                                <div class="row justify-content-center mb-1">
                                                    <div class="xo-modsimages">
                                                        <div class="col">
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text xo-mods">
                                                                         <{if (isset($row.hasadmin) && $row.hasadmin == 1) && (isset($row.isactive) && $row.isactive == 1)}>
                                                                                <a class=""
                                                                                    href="<{$xoops_url}>/modules/<{$row.dirname}>/<{$row.adminindex}>"
                                                                                    title="<{$row.name}>">
                                                                                    <img class="xo-mods hide"
                                                                                        src="<{xoAdminIcons url='applications.png'}>"
                                                                                        alt="<{$row.name}>"
                                                                                        title="<{$row.name}>" />
                                                                                </a>
                                                                            <{else}>
                                                                                    <img class="xo-mods hide"
                                                                                        src="<{xoAdminIcons url='applications.png'}>"
                                                                                        alt="<{$row.name}>"
                                                                                        title="<{$row.name}>" />
                                                                                <{/if}>
                                                                    </span>
                                                                </div>
                                                                <input class="form-control" type="text"
                                                                    name="newname[<{$row.mid}>]" value="<{$row.name}>"
                                                                    maxlength="150" size="20" />
                                                                <input type="hidden" name="oldname[<{$row.mid}>]"
                                                                    value="<{$row.name}>" />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <{if isset($row.warning_update) && $row.warning_update == 1}>
                                                        <a class=" maxi" style="color:red;"
                                                            href="<{$xoops_url}>/modules/system/admin.php?fct=modulesadmin&amp;op=update&amp;module=<{$row.dirname}>"
                                                            title="<{$smarty.const._AM_SYSTEM_MODULES_UPDATE}>">
                                                            <{$row.version}>
                                                        </a>
                                                        <br>
                                                        <{$smarty.const._AM_SYSTEM_MODULES_UPDATE}>
                                                        <{else}>
                                                                <{$row.version}>
                                                                    <{$row.module_status|default:''}>
                                                                    <{/if}>
                                            </td>
                                            <td>
                                                <{$row.last_update}>
                                            </td>
                                            <td class="xo-modsimages">
                                                <img id="loading_active<{$row.mid}>" src="images/spinner.gif"
                                                    style="display:none;" alt="<{$smarty.const._AM_SYSTEM_LOADING}>" />
                                                <img class="cursorpointer " id="active<{$row.mid}>"
                                                    onclick="system_setStatus( { fct: 'modulesadmin', op: 'display', mid: <{$row.mid}> }, 'active<{$row.mid}>', 'admin.php' )"
                                                    src="<{if !empty($row.isactive)}><{xoAdminIcons url='success.png'}><{else}><{xoAdminIcons url='cancel.png'}><{/if}>"
                                                    alt="<{if !empty($row.isactive)}><{$smarty.const._AM_SYSTEM_MODULES_DEACTIVATE}><{else}><{$smarty.const._AM_SYSTEM_MODULES_ACTIVATE}><{/if}>"
                                                    title="<{if !empty($row.isactive)}><{$smarty.const._AM_SYSTEM_MODULES_DEACTIVATE}><{else}><{$smarty.const._AM_SYSTEM_MODULES_ACTIVATE}><{/if}>" />
                                            </td>
                                            <td class="xo-modsimages">
                                                <img id="loading_menu<{$row.mid}>" src="images/spinner.gif"
                                                    style="display:none;" title="<{$smarty.const._AM_SYSTEM_LOADING}>"
                                                    alt="<{$smarty.const._AM_SYSTEM_LOADING}>" />
                                                <img class="cursorpointer " id="menu<{$row.mid}>"
                                                    onclick="system_setStatus( { fct: 'modulesadmin', op: 'display_in_menu', mid: <{$row.mid}> }, 'menu<{$row.mid}>', 'admin.php' )"
                                                    src="<{if isset($row.weight) && $row.weight != 0}><{xoAdminIcons url='success.png'}><{else}><{xoAdminIcons url='cancel.png'}><{/if}>"
                                                    alt="<{if isset($row.weight) && $row.weight != 0}><{$smarty.const._AM_SYSTEM_MODULES_HIDE}><{else}><{$smarty.const._AM_SYSTEM_MODULES_SHOW}><{/if}>"
                                                    title="<{if isset($row.weight) && $row.weight != 0}><{$smarty.const._AM_SYSTEM_MODULES_HIDE}><{else}><{$smarty.const._AM_SYSTEM_MODULES_SHOW}><{/if}>" />
                                            </td>
                                            <td class="xo-modsimages">
                                                <{if isset($row.isactive) && $row.isactive == 1}>
                                                        <a class=""
                                                            href="<{$xoops_url}>/modules/system/admin.php?fct=modulesadmin&amp;op=update&amp;module=<{$row.dirname}>"
                                                            title="<{$smarty.const._AM_SYSTEM_MODULES_UPDATE}>">
                                                            <{if isset($row.warning_update) && $row.warning_update == 1}>
                                                                    <img src="<{xoAdminIcons url='messagebox_warning.png'}>"
                                                                        alt="<{$smarty.const._AM_SYSTEM_MODULES_UPDATE}>" />
                                                                <{else}>
                                                                        <img src="<{xoAdminIcons url='reload.png'}>"
                                                                            alt="<{$smarty.const._AM_SYSTEM_MODULES_UPDATE}>" />
                                                                    <{/if}>
                                                        </a>
                                                    <{/if}>
                                                        <a class="cursorpointer"
                                                            href="<{$xoops_url}>/modules/system/admin.php?fct=modulesadmin&amp;op=uninstall&amp;module=<{$row.dirname}>"
                                                            title="<{$smarty.const._AM_SYSTEM_MODULES_UNINSTALL}>">
                                                            <img src="<{xoAdminIcons url='uninstall.png'}>"
                                                                alt="<{$smarty.const._AM_SYSTEM_MODULES_UNINSTALL}>" />
                                                        </a>
                                                        <img class="cursorpointer " data-toggle="modal"
                                                            data-target="#modal-<{$row.mid}>"
                                                            src="<{xoAdminIcons url='info.png'}>" alt="<{$smarty.const._INFO}>"
                                                            title="<{$smarty.const._INFO}>" />
                                                        <input type="hidden" name="module[]" value="<{$row.mid}>" />
                                            </td>
                                        </tr>
                                    <{/if}>
                                    <{/foreach}>
                    </tbody>
                    <tbody>
                        <tr class="table-head">
                            <td colspan="6"></td>
                        </tr>
                        <{foreach item=row from=$install_mods|default:null}>
                                <{if (isset($row.dirname) && $row.dirname != 'system') && empty($row.hasmain)}>
                                        <tr class="text-center mdb-color lighten-5">
                                            <td>
                                                <a class="xo-logonormal "
                                                    href="<{$xoops_url}>/modules/<{$row.dirname}>/<{$row.adminindex}>"
                                                    title="<{$row.name}>">
                                                    <img src="<{$xoops_url}>/modules/<{$row.dirname}>/<{$row.image}>"
                                                        alt="<{$row.name}>" />
                                                </a>
                                                <div class="row justify-content-center">
                                                    <div class="xo-modsimages">
                                                        <div class="col">
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text xo-mods">

                                                                        <a class=""
                                                                            href="<{$xoops_url}>/modules/<{$row.dirname}>/<{$row.adminindex}>"
                                                                            title="<{$row.name}>">
                                                                            <img class="xo-mods hide"
                                                                                src="<{xoAdminIcons url='applications.png'}>"
                                                                                alt="<{$row.name}>" title="<{$row.name}>" />
                                                                        </a>
                                                                    </span>
                                                                </div>
                                                                <input class="form-control" type="text"
                                                                    name="newname[<{$row.mid}>]" value="<{$row.name}>"
                                                                    maxlength="150" size="20" />
                                                                <input type="hidden" name="oldname[<{$row.mid}>]"
                                                                    value="<{$row.name}>" />
                                                            </div>
                                                        </div>
                                                    </div>
                                            </td>
                                            <td>
                                                <{if isset($row.warning_update) && $row.warning_update == 1}>
                                                        <a class=" maxi" style="color:red;"
                                                            href="<{$xoops_url}>/modules/system/admin.php?fct=modulesadmin&amp;op=update&amp;module=<{$row.dirname}>"
                                                            title="<{$smarty.const._AM_SYSTEM_MODULES_UPDATE}>">
                                                            <{$row.version}>
                                                        </a>
                                                        <br>
                                                        <{$smarty.const._AM_SYSTEM_MODULES_UPDATE}>
                                                        <{else}>
                                                                <{$row.version}>
                                                    <{$row.module_status|default:''}>
                                                                    <{/if}>
                                            </td>
                                            <td>
                                                <{$row.last_update}>
                                            </td>
                                            <td class="xo-modsimages"><img id="loading_mid<{$row.mid}>"
                                                    src="images/spinner.gif" style="display:none;"
                                                    title="<{$smarty.const._AM_SYSTEM_LOADING}>"
                                                    alt="<{$smarty.const._AM_SYSTEM_LOADING}>" /><img class="cursorpointer "
                                                    id="mid<{$row.mid}>"
                                                    onclick="system_setStatus( { fct: 'modulesadmin', op: 'display', mid: <{$row.mid}> }, 'mid<{$row.mid}>', 'admin.php' )"
                                                    src="<{if !empty($row.isactive)}><{xoAdminIcons url='success.png'}><{else}><{xoAdminIcons url='cancel.png'}><{/if}>"
                                                    alt="<{if !empty($row.isactive)}><{$smarty.const._AM_SYSTEM_MODULES_DEACTIVATE}><{else}><{$smarty.const._AM_SYSTEM_MODULES_ACTIVATE}><{/if}>"
                                                    title="<{if !empty($row.isactive)}><{$smarty.const._AM_SYSTEM_MODULES_DEACTIVATE}><{else}><{$smarty.const._AM_SYSTEM_MODULES_ACTIVATE}><{/if}>" />
                                            </td>
                                            <td class="xo-modsimages"></td>
                                            <td class="xo-modsimages">
                                                <{if isset($row.isactive) && $row.isactive == 1}>
                                                        <a class=""
                                                            href="<{$xoops_url}>/modules/system/admin.php?fct=modulesadmin&amp;op=update&amp;module=<{$row.dirname}>"
                                                            title="<{$smarty.const._AM_SYSTEM_MODULES_UPDATE}>">
                                                            <img src="<{xoAdminIcons url='reload.png'}>"
                                                                alt="<{$smarty.const._AM_SYSTEM_MODULES_UPDATE}>" />
                                                        </a>
                                                    <{/if}>
                                                        <a class=""
                                                            href="<{$xoops_url}>/modules/system/admin.php?fct=modulesadmin&amp;op=uninstall&amp;module=<{$row.dirname}>"
                                                            title="<{$smarty.const._AM_SYSTEM_MODULES_UNINSTALL}>">
                                                            <img src="<{xoAdminIcons url='uninstall.png'}>"
                                                                alt="<{$smarty.const._AM_SYSTEM_MODULES_UNINSTALL}>" />
                                                        </a>
                                                        <img class="cursorpointer " data-toggle="modal"
                                                        data-target="#modal-<{$row.mid}>"
                                                            src="<{xoAdminIcons url='info.png'}>" alt="<{$smarty.const._INFO}>"
                                                            title="<{$smarty.const._INFO}>" />
                                                        <input type="hidden" name="module[]" value="<{$row.mid}>" />
                                            </td>
                                        </tr>
                                    <{/if}>
                                    <{/foreach}>
                    </tbody>
                    <tfoot>
                        <tr class="text-center foot">
                            <td colspan="6">
                                <input type="hidden" name="fct" value="modulesadmin" />
                                <input type="hidden" name="op" value="confirm" />
                                <button class="btn btn-primary" type="submit" name="submit">
                                    <i class=" fas fa-check ic-w mx-1"></i>
                                    <{$smarty.const._AM_SYSTEM_MODULES_SUBMIT}>
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <{securityToken}>
            </form>
        </div>
    </div>

<{/if}>

    <{if !empty($toinstall_mods)}>
            <div class="card">
                <div class="card-header">
                    <img class="cursorpointer" onclick="system_moduleLargeView();"
                        src="<{xoAdminIcons url='view_large.png'}>" alt="<{$smarty.const._AM_SYSTEM_MODULES_VIEWLARGE}>"
                        title="<{$smarty.const._AM_SYSTEM_MODULES_VIEWLARGE}>" />
                    <img class="cursorpointer" onclick="system_moduleListView();"
                        src="<{xoAdminIcons url='view_small.png'}>" alt="<{$smarty.const._AM_SYSTEM_MODULES_VIEWLINE}>"
                        title="<{$smarty.const._AM_SYSTEM_MODULES_VIEWLINE}>" />
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" cellspacing="1">
                        <thead class="table-head">
                            <tr class="text-center">
                                <th>
                                    <{$smarty.const._AM_SYSTEM_MODULES_MODULE}>
                                </th>
                                <th>
                                    <{$smarty.const._AM_SYSTEM_MODULES_VERSION}>
                                </th>
                                <th>
                                    <{$smarty.const._AM_SYSTEM_MODULES_ACTION}>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <{foreach item=row from=$toinstall_mods|default:null}>
                                    <tr class="text-center">
                                        <td>
                                            <img class="xo-logonormal"
                                                src="<{$xoops_url}>/modules/<{$row.dirname}>/<{$row.image}>"
                                                alt="<{$row.name}>" title="<{$row.name}>" />

                                            <div class="xo-modsimages">
                                                <{if (isset($row.hasadmin) && $row.hasadmin == 1) && $row.isactive == 1}>
                                                        <a class="tooltip"
                                                            href="<{$xoops_url}>/modules/<{$row.dirname}>/<{$row.adminindex}>"
                                                            title="<{$row.name}>">
                                                            <img class="xo-mods hide"
                                                                src="<{xoAdminIcons url='applications.png'}>" alt="<{$row.name}>"
                                                                title="<{$row.name}>" />
                                                        </a>
                                                    <{else}>
                                                            <img class="xo-mods hide"
                                                                src="<{xoAdminIcons url='applications.png'}>" alt="<{$row.name}>"
                                                                title="<{$row.name}>" />
                                                        <{/if}>
                                                            <span class="spacer bold">
                                                                <{$row.name}>
                                                            </span>
                                            </div>
                                        </td>
                                        <td>
                                            <{$row.version}>
                                            <{$row.module_status|default:''}>
                                        </td>
                                        <td class="xo-modsimages">
                                            <a class="" data-toggle="tooltip"
                                                href="<{$xoops_url}>/modules/system/admin.php?fct=modulesadmin&amp;op=install&amp;module=<{$row.dirname}>"
                                                title="<{$smarty.const._AM_SYSTEM_MODULES_INSTALL}>">
                                                <img src="<{xoAdminIcons url='install.png'}>"
                                                    alt="<{$smarty.const._AM_SYSTEM_MODULES_INSTALL}>" />
                                            </a>
                                            <img class="cursorpointer" data-toggle="modal"
                                                data-target="#modal-<{$row.mid}>" src="<{xoAdminIcons url='info.png'}>"
                                                alt="<{$smarty.const._INFO}>" title="<{$smarty.const._INFO}>" />
                                        </td>
                                    </tr>
                                <{/foreach}>
                        </tbody>
                    </table>
                </div>
            </div>
        <{/if}>
            <!--Pop-pup-->
            <{foreach item=row from=$mods_popup|default:null}>
                    <div class="modal fade" id="modal-<{$row.mid}>" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <{$row.name}>
                                    </h5>
                                    <button type="button" class="close white-text" data-dismiss="modal"
                                        aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <table>
                                        <tr>
                                            <td class="width10 aligntop">
                                                <img src="<{$xoops_url}>/modules/<{$row.dirname}>/<{$row.image}>"
                                                    alt="<{$row.name}>" title="<{$row.name}>" />
                                            </td>
                                            <td>
                                                <ul class="xo-moduleinfos">
                                                    <li><span class="bold">
                                                            <{$smarty.const._VERSION}>
                                                        </span>&nbsp;:&nbsp;<{$row.version}>
                                                            <{$row.module_status|default:''}>
                                                    </li>
                                                    <li><span class="bold">
                                                            <{$smarty.const._AUTHOR}>
                                                        </span>&nbsp;:&nbsp;<{$row.author}>
                                                    </li>
                                                    <li><span class="bold">
                                                            <{$smarty.const._CREDITS}>
                                                        </span>&nbsp;:&nbsp;<{$row.credits}>
                                                    </li>
                                                    <li><span class="bold">
                                                            <{$smarty.const._LICENCE}>
                                                        </span>&nbsp;:&nbsp;<{$row.license}>
                                                    </li>
                                                </ul>
                                            </td>
                                        </tr>
                                    </table>
                                    <p>
                                        <{$row.description}>
                                    </p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                        <{$smarty.const._CLOSE}>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <{/foreach}>
<!--Pop-pup-->
