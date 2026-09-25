<?php
/**
 * Messages logged by the 2.7.3 to 2.7.4 upgrade patch (upd_2.7.3-to-2.7.4/index.php).
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */

// Task: user2fatable
define('_XOOPS_UPGRADE_274_2FA_TABLE_CHECK', 'Could not read information_schema to check for the user_2fa table');
define('_XOOPS_UPGRADE_274_2FA_TABLE_NOT_CREATED', 'Could not read information_schema; the user_2fa table was not created');

// Task: twofactormode
define('_XOOPS_UPGRADE_274_MODE_CHECK', 'Could not read the config table to check for the twofactor_mode preference');
define('_XOOPS_UPGRADE_274_MODE_OPTIONS_CHECK', 'Could not read the configoption table to check the twofactor_mode options');
define('_XOOPS_UPGRADE_274_MODE_NOT_INSERTED', 'Could not read the config table; the twofactor_mode preference was not inserted');
define('_XOOPS_UPGRADE_274_MODE_NOT_FOUND', 'The twofactor_mode preference row was not found after it was inserted');
define('_XOOPS_UPGRADE_274_MODE_OPTIONS_NOT_INSERTED', 'Could not read the configoption table; the twofactor_mode options were not inserted');

// Task: emoticons
define('_XOOPS_UPGRADE_274_EMOTICONS_CHECK', 'Could not read the smiles table to check the SCEditor emoticons');

// Task: editorprefs
define('_XOOPS_UPGRADE_274_EDITORS_CHECK', 'The Editors preferences could not be checked');
define('_XOOPS_UPGRADE_274_EDITORS_NOT_INSERTED', 'The Editors preferences were not inserted');
// %s is the preference name
define('_XOOPS_UPGRADE_274_EDITORS_OPTION_PARENT', 'Could not find the %s preference for its options');
// %d is the preference category ID
define('_XOOPS_UPGRADE_274_EDITORS_CATEGORY_TAKEN', 'Preference category %d is already used by another category; the Editors preferences need that ID');

// Migration locks; %s is the task being locked (twofactor_mode, emoticons, editorprefs)
define('_XOOPS_UPGRADE_274_LOCK_ACQUIRE', 'Could not acquire the %s migration lock; retry the upgrade');
define('_XOOPS_UPGRADE_274_LOCK_RELEASE', 'Could not release the %s migration lock');
