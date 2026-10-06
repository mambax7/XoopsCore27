<?php

// GIJOE's Ticket Class (based on Marijuana's Oreteki XOOPS)
// nobunobu's suggestions are applied
//
// Since 2.7.4 this class is a thin adapter over XoopsSecurity, the token
// system every core form uses. New code should call
// $GLOBALS['xoopsSecurity']->check() and getTokenHTML() directly.

use Xmf\Request;

if (!class_exists('XoopsGTicket')) {

    /**
     * Class XoopsGTicket
     *
     * @deprecated 2.7.4 Use $GLOBALS['xoopsSecurity'] (XoopsSecurity). Kept so
     *             modules that include this file from the trust path keep working.
     *             A ticket is bound to the session that issued it, single-use,
     *             and expires after the requested timeout. The salt is ignored
     *             and the area no longer scopes a ticket (the old check accepted
     *             a matching area OR referer, so it was never a boundary between
     *             modules).
     */
    class XoopsGTicket
    {
        /** Name of the hidden field, and of the XoopsSecurity token set behind it. */
        public const FIELD = 'XOOPS_G_TICKET';

        public $_errors       = [];
        public $_latest_token = '';
        public $messages      = [];

        /**
         * XoopsGTicket constructor.
         */
        public function __construct()
        {
            global $xoopsConfig;

            // language file
            if (defined('XOOPS_ROOT_PATH') && !empty($xoopsConfig['language']) && false === strpos($xoopsConfig['language'], '/')) {
                if (file_exists(dirname(__DIR__) . '/language/' . $xoopsConfig['language'] . '/gticket_messages.phtml')) {
                    include dirname(__DIR__) . '/language/' . $xoopsConfig['language'] . '/gticket_messages.phtml';
                }
            }

            // default messages
            if (empty($this->messages)) {
                $this->messages = [
                    'err_general'       => 'GTicket Error',
                    'err_nostubs'       => 'No stubs found',
                    'err_noticket'      => 'No ticket found',
                    'err_nopair'        => 'No valid ticket-stub pair found',
                    'err_timeout'       => 'Time out',
                    'err_areaorref'     => 'Invalid area or referer',
                    'fmt_prompt4repost' => 'error(s) found:<br><span style="background-color:red;font-weight:bold;color:white;">%s</span><br>Confirm it.<br>And do you want to post again?',
                    'btn_repost'        => 'repost',
                ];
            }
        }

        /**
         * The XoopsSecurity instance the tickets are issued and checked with.
         *
         * @return XoopsSecurity
         */
        private function security()
        {
            if (!isset($GLOBALS['xoopsSecurity']) || !($GLOBALS['xoopsSecurity'] instanceof XoopsSecurity)) {
                $GLOBALS['xoopsSecurity'] = new XoopsSecurity();
            }

            return $GLOBALS['xoopsSecurity'];
        }

        // render form as plain html
        /**
         * @param string $salt
         * @param int    $timeout
         * @param string $area
         *
         * @return string
         */
        public function getTicketHtml($salt = '', $timeout = 1800, $area = '')
        {
            return '<input type="hidden" name="' . self::FIELD . '" value="' . $this->issue($salt, $timeout, $area) . '" />';
        }

        // returns an object of XoopsFormHidden including theh ticket
        /**
         * @param string $salt
         * @param int    $timeout
         * @param string $area
         *
         * @return XoopsFormHidden
         */
        public function getTicketXoopsForm($salt = '', $timeout = 1800, $area = '')
        {
            return new XoopsFormHidden(self::FIELD, $this->issue($salt, $timeout, $area));
        }

        // add a ticket as Hidden Element into XoopsForm
        /**
         * @param        $form
         * @param string $salt
         * @param int    $timeout
         * @param string $area
         */
        public function addTicketXoopsFormElement($form, $salt = '', $timeout = 1800, $area = '')
        {
            $form->addElement(new XoopsFormHidden(self::FIELD, $this->issue($salt, $timeout, $area)));
        }

        // returns an array for xoops_confirm() ;
        /**
         * @param string $salt
         * @param int    $timeout
         * @param string $area
         *
         * @return array
         */
        public function getTicketArray($salt = '', $timeout = 1800, $area = '')
        {
            return [self::FIELD => $this->issue($salt, $timeout, $area)];
        }

        // return GET parameter string.
        /**
         * @param string $salt
         * @param bool   $noamp
         * @param int    $timeout
         * @param string $area
         *
         * @return string
         */
        public function getTicketParamString($salt = '', $noamp = false, $timeout = 1800, $area = '')
        {
            return ($noamp ? '' : '&amp;') . self::FIELD . '=' . $this->issue($salt, $timeout, $area);
        }

        // issue a ticket
        /**
         * A XoopsSecurity token in its own set (XOOPS_G_TICKET_SESSION), so it
         * does not consume the page's XOOPS_TOKEN set. $salt and $area are
         * accepted for callers and ignored.
         *
         * @param string $salt    unused since 2.7.4
         * @param int    $timeout seconds the ticket stays valid
         * @param string $area    unused since 2.7.4
         *
         * @return string
         */
        public function issue(/** @scrutinizer ignore-unused */ $salt = '', $timeout = 1800, /** @scrutinizer ignore-unused */ $area = '')
        {
            // XoopsSecurity reads 0 as "session lifetime"; GTicket read it as
            // "expires now" (valid within the same second). Keep that meaning.
            // A negative timeout passes through: both store time() + timeout,
            // which is already expired.
            $timeout = (int) $timeout;
            if (0 === $timeout) {
                $timeout = 1;
            }
            $this->_latest_token = (string) $this->security()->createToken($timeout, self::FIELD);

            return $this->_latest_token;
        }

        // check a ticket
        /**
         * @param bool   $post         read the ticket from POST (true) or GET
         * @param string $area         unused since 2.7.4
         * @param bool   $allow_repost show the repost form on failure instead of returning false
         *
         * @return bool
         */
        public function check($post = true, $area = '', $allow_repost = true)
        {
            $this->_errors = [];

            $ticket = Request::getString(self::FIELD, '', $post ? 'POST' : 'GET');
            if ('' === $ticket) {
                $this->_errors[] = $this->messages['err_noticket'];
            } else {
                // Looked up before the check: a failed check garbage-collects
                // expired entries, and the message must still say "time out".
                $expired  = $this->isExpired($ticket);
                $security = $this->security();
                // GTicket reports through its own messages; leave the shared
                // XoopsSecurity error list as it was for the page's own checks.
                $coreErrors = $security->errors ?? [];
                $valid      = $security->check(true, $ticket, self::FIELD);
                $security->errors = $coreErrors;
                if (!$valid) {
                    $this->_errors[] = $this->messages[$expired ? 'err_timeout' : 'err_nopair'];
                }
            }

            if (!empty($this->_errors)) {
                if ($allow_repost) {
                    // repost form
                    $this->draw_repost_form($area);
                    exit;
                }
                // failed
                $this->clear();

                return false;
            }

            // all green
            return true;
        }

        /**
         * Whether the ticket is in the set but past its expiry (XoopsSecurity
         * entry shape: 'token' and 'expire').
         *
         * @param string $ticket submitted ticket
         *
         * @return bool
         */
        private function isExpired(string $ticket): bool
        {
            foreach ($_SESSION[self::FIELD . '_SESSION'] ?? [] as $entry) {
                if (is_array($entry) && isset($entry['token']) && hash_equals((string) $entry['token'], $ticket)) {
                    return !empty($entry['expire']) && $entry['expire'] < time();
                }
            }

            return false;
        }

        // draw form for repost
        /**
         * @param string $area
         */
        public function draw_repost_form($area = '')
        {
            // Notify which file is broken
            if (headers_sent()) {
                restore_error_handler();
                set_error_handler([&$this, 'errorHandler4FindOutput']);
                header('Dummy: for warning');
                restore_error_handler();
                exit;
            }

            error_reporting(0);
            while (ob_get_level()) {
                ob_end_clean();
            }

            $table = '<table>';
            $form = '<form action="?' . htmlspecialchars($_SERVER['QUERY_STRING'] ?? '', ENT_QUOTES | ENT_HTML5) . '" method="post">';

            foreach ($_POST as $key => $val) {
                if (self::FIELD === $key) {
                    continue;
                }

                if (is_array($val)) {
                    [$tmp_table, $tmp_form] = $this->extract_post_recursive(htmlspecialchars($key, ENT_QUOTES | ENT_HTML5), $val);
                    $table .= $tmp_table;
                    $form .= $tmp_form;
                } else {
                    $table .= '<tr><th>' . htmlspecialchars($key, ENT_QUOTES | ENT_HTML5) . '</th><td>' . htmlspecialchars($val, ENT_QUOTES | ENT_HTML5) . '</td></tr>' . "\n";
                    $form .= '<input type="hidden" name="' . htmlspecialchars($key, ENT_QUOTES | ENT_HTML5) . '" value="' . htmlspecialchars($val, ENT_QUOTES | ENT_HTML5) . '" />' . "\n";
                }
            }
            $table .= '</table>';
            $form .= $this->getTicketHtml(__LINE__, 300, $area) . '<input type="submit" value="' . $this->messages['btn_repost'] . '" /></form>';

            echo '<html><head><title>' . $this->messages['err_general'] . '</title><style>table,td,th {border:solid black 1px; border-collapse:collapse;}</style></head><body>' . sprintf($this->messages['fmt_prompt4repost'], $this->getErrors()) . $table . $form . '</body></html>';
        }

        /**
         * @param $key_name
         * @param $tmp_array
         *
         * @return array
         */
        public function extract_post_recursive($key_name, $tmp_array)
        {
            $table = '';
            $form  = '';
            foreach ($tmp_array as $key => $val) {
                if (is_array($val)) {
                    [$tmp_table, $tmp_form] = $this->extract_post_recursive($key_name . '[' . htmlspecialchars($key, ENT_QUOTES | ENT_HTML5) . ']', $val);
                    $table .= $tmp_table;
                    $form .= $tmp_form;
                } else {
                    $table .= '<tr><th>' . $key_name . '[' . htmlspecialchars($key, ENT_QUOTES | ENT_HTML5) . ']</th><td>' . htmlspecialchars($val, ENT_QUOTES | ENT_HTML5) . '</td></tr>' . "\n";
                    $form .= '<input type="hidden" name="' . $key_name . '[' . htmlspecialchars($key, ENT_QUOTES | ENT_HTML5) . ']" value="' . htmlspecialchars($val, ENT_QUOTES | ENT_HTML5) . '" />' . "\n";
                }
            }

            return [$table, $form];
        }

        // clear all tickets
        public function clear()
        {
            // what XoopsSecurity::clearTokens(self::FIELD) does
            $_SESSION[self::FIELD . '_SESSION'] = [];
        }

        // Ticket Using
        /**
         * @return bool
         */
        public function using()
        {
            return !empty($_SESSION[self::FIELD . '_SESSION']);
        }

        // return errors
        /**
         * @param bool $ashtml
         *
         * @return array|string
         */
        public function getErrors($ashtml = true)
        {
            if ($ashtml) {
                $ret = '';
                foreach ($this->_errors as $msg) {
                    $ret .= "$msg<br>\n";
                }
            } else {
                $ret = $this->_errors;
            }

            return $ret;
        }

        /**
         * @param $errNo
         * @param $errStr
         * @param $errFile
         * @param $errLine
         * @return null
         */
        public function errorHandler4FindOutput($errNo, $errStr, $errFile, $errLine)
        {
            if (preg_match('#' . preg_quote(XOOPS_ROOT_PATH, '#') . '([^:]+)\:(\d+)?#', $errStr, $regs)) {
                echo 'Irregular output! check the file ' . htmlspecialchars($regs[1], ENT_QUOTES | ENT_HTML5) . ' line ' . htmlspecialchars($regs[2], ENT_QUOTES | ENT_HTML5);
            } else {
                echo 'Irregular output! check language files etc.';
            }

            return null;
        }
        // end of class
    }

    // create a instance in global scope: the compatibility surface for modules
    $GLOBALS['xoopsGTicket'] = /** @scrutinizer ignore-deprecated */ new XoopsGTicket();
}

if (!function_exists('admin_refcheck')) {

    //Admin Referer Check By Marijuana(Rev.011)
    /**
     * @param string $chkref
     *
     * @return bool
     */
    function admin_refcheck($chkref = '')
    {
        if (empty($_SERVER['HTTP_REFERER'])) {
            return true;
        } else {
            $ref = $_SERVER['HTTP_REFERER'];
        }
        $cr = XOOPS_URL;
        if ('' != $chkref) {
            $cr .= $chkref;
        }
        return !(0 !== strpos($ref, $cr));
    }
}
