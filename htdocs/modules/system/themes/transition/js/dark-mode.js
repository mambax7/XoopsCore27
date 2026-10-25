/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

 @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 @license GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 */
/* XOOPS Transition theme — shares the Modern theme's display preference. */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.getElementById('darkModeToggle');
        if (!toggle) return;

        function applyMode(dark) {
            document.body.classList.toggle('dark-mode', dark);
            toggle.setAttribute('aria-pressed', String(dark));
        }

        applyMode(document.cookie.split(';').some(function (cookie) {
            return cookie.trim() === 'xoops_dark_mode=1';
        }));

        toggle.addEventListener('click', function () {
            const dark = !document.body.classList.contains('dark-mode');
            applyMode(dark);
            const expires = new Date(Date.now() + 365 * 86400000).toUTCString();
            document.cookie = 'xoops_dark_mode=' + (dark ? '1' : '0')
                + ';expires=' + expires + ';path=/;SameSite=Lax'
                + (location.protocol === 'https:' ? ';Secure' : '');
        });
    });
})();
