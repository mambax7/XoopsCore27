<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

/**
 * Login handler for the XOOPS upgrade wizard.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @since     2.7.0
 * @author    XOOPS Development Team
 */

defined('XOOPS_ROOT_PATH') or exit();

$uname = \Xmf\Request::getString('uname', '', 'POST');
$pass  = \Xmf\Request::getString('pass', '', 'POST');

if ('' === $uname || '' === $pass) {
    ?>
    <h2><?php echo _USER_LOGIN; ?></h2>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES | ENT_HTML5); ?>" method="post">
        <label for="uname"><?php echo _USERNAME; ?></label>
        <div class="input-group">
            <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
            <input class="form-control" type="text" name="uname" id="uname" value="" placeholder="<?php echo _USERNAME_PLACEHOLDER; ?>" autocomplete="current-password">
        </div>

        <label for="pass"><?php echo _PASSWORD; ?></label>
        <div class="input-group">
            <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
            <input class="form-control" type="password" name="pass" id="pass" placeholder="<?php echo _PASSWORD_PLACEHOLDER; ?>">
        </div>
        <div class="input-group">
            <br>
            <button type="submit" class="btn btn-default"><?php echo _LOGIN; ?></button>
        </div>
    </form>
    <?php
} else {
    $member_handler = xoops_getHandler('member');

    include_once XOOPS_ROOT_PATH . '/class/auth/authfactory.php';
    $language = isset($upgradeControl) && $upgradeControl instanceof \Xoops\Upgrade\UpgradeControl
        ? $upgradeControl->normalizeLanguage($upgradeControl->upgradeLanguage)
        : 'english';
    $languageRoot = realpath(XOOPS_ROOT_PATH . '/language');
    $authFile = false !== $languageRoot
        ? realpath($languageRoot . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . 'auth.php')
        : false;

    if (false !== $languageRoot && false !== $authFile && str_starts_with($authFile, $languageRoot . DIRECTORY_SEPARATOR)) {
        include_once $authFile;
    } else {
        include_once XOOPS_ROOT_PATH . '/language/english/auth.php';
    }
    $xoopsAuth = XoopsAuthFactory::getAuthConnection($uname);
    $user      = $xoopsAuth->authenticate($uname, $pass);

    // For XOOPS 2.2*
    if (!is_object($user)) {
        try {
            $criteria = new CriteriaCompo(new Criteria('loginname', $uname));
            $criteria->add(new Criteria('pass', md5($pass)));
            [$user] = $member_handler->getUsers($criteria);
        } catch (\Throwable $e) {
            $user = false;
        }
    }

    $isAllowed = false;
    if (is_object($user) && $user->getVar('level') > 0) {
        $isAllowed = true;
        if ($xoopsConfig['closesite'] == 1) {
            $groups = $user->getGroups();
            if (in_array(XOOPS_GROUP_ADMIN, $groups) || array_intersect($groups, $xoopsConfig['closesite_okgrp'])) {
                $isAllowed = true;
            } else {
                $isAllowed = false;
            }
        }
    }
    if ($isAllowed) {
        $user->setVar('last_login', time());
        if (!$member_handler->insertUser($user)) {
            $errors = method_exists($user, 'getErrors') ? $user->getErrors() : [];
            $errorText = is_array($errors) ? implode('; ', $errors) : (string) $errors;
            trigger_error(
                sprintf(
                    'insertUser failed for uid %d during upgrade login%s',
                    (int) $user->getVar('uid'),
                    '' !== $errorText ? ': ' . $errorText : ''
                ),
                E_USER_WARNING
            );
        }
        // Regenerate a new session id and destroy old session
        $GLOBALS['sess_handler']->regenerate_id(true);
        $_SESSION                    = [];
        $_SESSION['xoopsUserId']     = $user->getVar('uid');
        $_SESSION['xoopsUserGroups'] = $user->getGroups();
        $user_theme                  = $user->getVar('theme');
        if (in_array($user_theme, $xoopsConfig['theme_set_allowed'])) {
            $_SESSION['xoopsUserTheme'] = $user_theme;
        }
    }

    header('location: ' . XOOPS_URL . '/upgrade/index.php');
    exit();
}
