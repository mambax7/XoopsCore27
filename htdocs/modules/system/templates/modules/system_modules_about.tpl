<div class="row">
    <div class="col">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <table class="table table-borderless">
                        <tr>
                            <td class="fit"><img src="<{$module_img}>" alt="<{$module_name}>" title="<{$module_name}>" /></td>
                            <td>
                                <div class="h4"><{$module_name}></div>
                                <div class="text-bold">
                                    <{$smarty.const._AM_MODULEADMIN_ABOUT_BY}><{$author}>
                                </div>
                                <div class="text-muted"><{$module_version}></div>
                                <div class="text-muted"><a href="<{$license_url}>"><{$license}></a></div>
                            </td>
                        </tr>
                    </table>
                    
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col">
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h4><{$smarty.const._AM_MODULEADMIN_ABOUT_MODULEINFO}></h4>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-3"><{$smarty.const._AM_MODULEADMIN_ABOUT_DESCRIPTION}></dt>
                            <dd class="col-sm-9"><{$module_description}></dd>
                            <dt class="col-sm-3"><{$smarty.const._AM_MODULEADMIN_ABOUT_UPDATEDATE}></dt>
                            <dd class="col-sm-9"><{$module_last_update}></dd>
                            <dt class="col-sm-3"><{$smarty.const._AM_MODULEADMIN_ABOUT_STATUS}></dt>
                            <dd class="col-sm-9"><{$module_status}></dd>
                            <dt class="col-sm-3"><{$smarty.const._AM_MODULEADMIN_ABOUT_WEBSITE}></dt>
                            <dd class="col-sm-9"><a href="<{$module_website_url}>"><{$module_website_name}></a></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
        <{if isset($business)}>
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h4>Donation</h4>
                    </div>
                    <div class="card-body">
                        <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank" rel="external">
                            <input name="cmd" type="hidden" value="_donations">
                            <input name="business" type="hidden" value="<{$business}>">
                            <input name="currency_code" type="hidden" value="<{$smarty.const._AM_MODULEADMIN_ABOUT_AMOUNT_CURRENCY}>">
                            <label class="label_after" for="amount"><{$smarty.const._AM_MODULEADMIN_ABOUT_AMOUNT}></label>
                            <div class="input-group">
                                <input class="form-control donate_amount" type="text" name="amount" value="<{$smarty.const._AM_MODULEADMIN_ABOUT_AMOUNT_SUGGESTED}>" title="<{$smarty.const._AM_MODULEADMIN_ABOUT_AMOUNT_TTL}>" pattern="<{$smarty.const._AM_MODULEADMIN_ABOUT_AMOUNT_PATTERN}>">
                                <div class="input-group-append">
                                    <span class="input-group-text"><{$smarty.const._AM_MODULEADMIN_ABOUT_AMOUNT_CURRENCY}></span>
                                </div>
                            </div>
                            <input type="image" name="submit" class="donate_button" src="<{$xoops_url}>/images/btn_donate_LG.png" alt="">
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <{/if}>
    </div>
    <div class="col">
        <div class="card">
            <div class="card-header">
                <h4><{$smarty.const._AM_MODULEADMIN_ABOUT_CHANGELOG}></h4>
            </div>
            <div class="card-body">
                <div class="text-muted">
                    <{$changelog}>
                </div>
            </div>
        </div>
    </div>
</div>