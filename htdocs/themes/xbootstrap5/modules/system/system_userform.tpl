<div class="container-fluid">
    <div class="row">
        <legend class="bold"><{$lang_login|default:''}></legend>

        <form action="user.php" method="post">
            <label for="profile-uname"><{$lang_username|default:''}></label>
            <div class="input-group">
                <span class="input-group-addon"><i class="fa-solid fa-user"></i></span>
                <input class="form-control" type="text" name="uname" id="profile-uname" value="" placeholder="<{$smarty.const.THEME_LOGIN}>">
            </div>

            <label for="profile-pass"><{$lang_password|default:''}></label>
            <div class="input-group">
                <span class="input-group-addon"><i class="fa-solid fa-lock"></i></span>
                <input class="form-control" type="password" name="pass" id="profile-pass" placeholder="<{$smarty.const.THEME_PASS}>">
            </div>
            <div class="checkbox">
                <label>
                    <{if isset($lang_rememberme)}>
                        <input type="checkbox" name="rememberme">
                        <{$lang_rememberme|default:''}>
                    <{/if}>
                </label>
            </div>

            <input type="hidden" name="op" value="login"/>
            <input type="hidden" name="xoops_redirect" value="<{$redirect_page|default:''}>"/>
            <button type="submit" class="btn btn-secondary"><{$lang_login|default:''}></button>
        </form>
        <br>
        <a name="lost"></a>

        <div><{$lang_notregister|default:''}><br></div>
    </div>

    <br>
    <div class="row">
        <legend class="bold"><{$lang_lostpassword|default:''}></legend>
        <p><{$lang_noproblem|default:''}></p>
        <form action="lostpass.php" method="post">
            <label for="profile-lostpass"><{$lang_youremail|default:''}></label>
            <div class="input-group">
                <span class="input-group-addon"><i class="fa-solid fa-envelope"></i></span>
                <input class="form-control" type="text" name="email" id="profile-lostpass">
            </div>
            <input type="hidden" name="op" value="mailpasswd"/>
            <input type="hidden" name="t" value="<{$mailpasswd_token|default:''}>"/>
            <button type="submit" class="btn btn-secondary"><{$lang_sendpassword|default:''}></button>
        </form>
    </div>
</div>
