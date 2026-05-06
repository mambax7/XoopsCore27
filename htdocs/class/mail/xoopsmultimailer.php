<?php
/**
 * Xoops MultiMailer Base Class
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             Kernel
 * @subpackage          mail
 * @since               2.0.0
 * @author              Author: Jochen Büînagel (job@buennagel.com)
 */

/**
 *
 * @package    class
 * @subpackage mail
 * @filesource
 * @author     Jochen Büînagel <jb@buennagel.com>
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

use Xmf\Mail\SendmailRunner;
use PHPMailer\PHPMailer\PHPMailer;

// bridge class for PhpMailer 6.x PHPMailer\PHPMailer\Exception

/**
 * load the base class
 */


/**
 * Mailer Class.
 *
 * At the moment, this does nothing but send email through PHP "mail()" function,
 * but it has the ability to do much more.
 *
 * If you have problems sending mail with "mail()", you can edit the member variables
 * to suit your setting. Later this will be possible through the admin panel.
 *
 * @todo       Make a page in the admin panel for setting mailer preferences.
 * @package    class
 * @subpackage mail
 * @author     Jochen Buennagel <job@buennagel.com>
 */
class XoopsMultiMailer extends PHPMailer
{
    /**
     * 'from' address
     *
     * @var string
     * @access private
     */
    public $From = '';

    /**
     * 'from' name
     *
     * @var string
     * @access private
     */
    public $FromName = '';

    // can be 'smtp', 'sendmail', or 'mail'
    /**
     * Method to be used when sending the mail.
     *
     * This can be:
     * <li>mail (standard PHP function 'mail()') (default)
     * <li>smtp    (send through any SMTP server, SMTPAuth is supported.
     * You must set {@link $Host}, for SMTPAuth also {@link $SMTPAuth},
     * {@link $Username}, and {@link $Password}.)
     * <li>sendmail (manually set the path to your sendmail program
     * to something different than 'mail()' uses in {@link $Sendmail})
     *
     * @var string
     * @access private
     */
    public $Mailer = 'mail';

    /**
     * set if $Mailer is 'sendmail'
     *
     * Only used if {@link $Mailer} is set to 'sendmail'.
     * Contains the full path to your sendmail program or replacement.
     *
     * @var string
     * @access private
     */
    public $Sendmail = '/usr/sbin/sendmail';

    /**
     * SMTP Host.
     *
     * Only used if {@link $Mailer} is set to 'smtp'
     *
     * @var string
     * @access private
     */
    public $Host = '';

    /**
     * Does your SMTP host require SMTPAuth authentication?
     *
     * @var boolean
     * @access private
     */
    public $SMTPAuth = false;

    /**
     * Username for authentication with your SMTP host.
     *
     * Only used if {@link $Mailer} is 'smtp' and {@link $SMTPAuth} is TRUE
     *
     * @var string
     * @access private
     */
    public $Username = '';

    /**
     * Password for SMTPAuth.
     *
     * Only used if {@link $Mailer} is 'smtp' and {@link $SMTPAuth} is TRUE
     *
     * @var string
     * @access private
     */
    public $Password = '';

    /**
     * Constructor
     *
     * @access public
     */
    public function __construct()
    {
        parent::__construct(true); // Enable exceptions in PHPMailer

        /** @var XoopsConfigHandler $config_handler */
        $config_handler    = xoops_getHandler('config');
        $xoopsMailerConfig = $config_handler->getConfigsByCat(XOOPS_CONF_MAILER);
        // Core XoopsConfigHandler::getConfigsByCat() returns an array
        // (possibly empty), but keep this guard defensive for custom
        // handlers, mocked/test bootstraps, or unexpected states. The
        // null-coalescing below then covers the case where rows for
        // individual keys are missing (mid-upgrade, fresh install before
        // first save, partial config), which would otherwise blank every
        // mail-touching request (notifications, password reset, contact
        // form).
        if (!is_array($xoopsMailerConfig)) {
            $xoopsMailerConfig = [];
        }

        $this->From = (string) ($xoopsMailerConfig['from'] ?? '');
        if ('' === $this->From) {
            $this->From = (string) ($GLOBALS['xoopsConfig']['adminmail'] ?? '');
        }
        $this->Sender = $this->From;

        // ?? alone doesn't catch an empty-string mailmethod (existing key,
        // blank value), which would set $this->Mailer = '' and PHPMailer
        // rejects that. Normalise null/missing/empty to 'mail'.
        $mailMethod = trim((string) ($xoopsMailerConfig['mailmethod'] ?? ''));
        if ('' === $mailMethod) {
            $mailMethod = 'mail';
        }
        // smtphost has historically been stored as either a flat string,
        // a separator-joined string, or an array (the underlying schema
        // inconsistency is still unresolved — see TODO below). Plain
        // (array) on a separated string yields one bogus host element,
        // so split string forms first. The split set covers all three
        // delimiters seen in the wild:
        //   - '|' is XOOPS' canonical array-form input delimiter; see
        //     XoopsConfigItem::setConfValueForInput in
        //     htdocs/kernel/configitem.php (explode('|', ...) before
        //     serialize). It can persist in 'text'-valuetype rows or in
        //     manually-edited config_value strings.
        //   - ';' / ',' are common admin-typed delimiters when the value
        //     was stored as text rather than an array.
        // (array) on the already-array form is a no-op.
        $smtpHosts = $xoopsMailerConfig['smtphost'] ?? [];
        if (is_string($smtpHosts)) {
            $smtpHosts = preg_split('/[|;,]+/', $smtpHosts, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        // Cast each element to string before trim — a corrupted stored
        // array could contain null or non-string elements, and trim(null)
        // is deprecated in PHP 8.1+.
        $smtpHosts = array_filter(
            array_map(static fn ($host) => trim((string) $host), (array) $smtpHosts),
            'strlen'
        );

        if ('smtpauth' === $mailMethod) {
            $this->Mailer   = 'smtp';
            $this->SMTPAuth = true;
            // TODO: normalise xoopsConfig 'smtphost' storage type
            //       (currently mixed string/array; see $smtpHosts above).
            $this->Host     = implode(';', $smtpHosts);
            $this->Username = (string) ($xoopsMailerConfig['smtpuser'] ?? '');
            $this->Password = (string) ($xoopsMailerConfig['smtppass'] ?? '');
        } else {
            $this->Mailer   = $mailMethod;
            $this->SMTPAuth = false;
            // Preserve the class-default $Sendmail ('/usr/sbin/sendmail')
            // when the config value is missing OR an empty/whitespace
            // string. ?? alone only catches null/missing — the existing-
            // but-blank case (admin cleared the field, or a partial save
            // left an empty string) would still overwrite with '' and
            // break sendmail delivery. Same shape as the mailmethod
            // normalisation above.
            $sendmailPath = trim((string) ($xoopsMailerConfig['sendmailpath'] ?? ''));
            if ('' !== $sendmailPath) {
                $this->Sendmail = $sendmailPath;
            }
            $this->Host     = implode(';', $smtpHosts);
        }
        $this->CharSet = strtolower(_CHARSET);
        $xoopsLanguage = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($GLOBALS['xoopsConfig']['language'] ?? 'english'));
        if (file_exists(XOOPS_ROOT_PATH . '/language/' . $xoopsLanguage . '/phpmailer.php')) {
            include XOOPS_ROOT_PATH . '/language/' . $xoopsLanguage . '/phpmailer.php';
            $this->language = $PHPMAILER_LANG;
        } else {
            $this->setLanguage('en', XOOPS_ROOT_PATH . '/class/mail/phpmailer/language/');
        }
        //$this->pluginDir = XOOPS_ROOT_PATH . '/class/mail/phpmailer/';
    }

    /**
     * Overrides PHPMailer's protected sendmailSend method to use XOOPS' hardened runner for sendmail delivery.
     *
     * Security enhancement: Instead of directly invoking the sendmail binary, this method uses XOOPS' SendmailRunner,
     * which applies additional security checks and policies to the delivery process.
     *
     * @param string $header RFC 5322-compliant message headers, with LF line endings.
     * @param string $body   Message body, with LF line endings.
     *                       Both parameters are expected to be formatted as provided by PHPMailer.
     * @return bool True on successful delivery, false otherwise.
     * @throws PHPMailer\PHPMailer\Exception when exceptions are enabled and delivery fails.
     */
    protected function sendmailSend($header, $body): bool
    {
        // Build a complete RFC 5322 message. PHPMailer gives LF; runner normalizes to CRLF.
        $rfc822 = rtrim($header, "\r\n") . "\n\n" . $body;

        // XOOPS config already set this in ctor from preferences:
        $path = (string)$this->Sendmail;
        // Prefer Sender (envelope-from), fall back to From if Sender is empty:
        $envelopeFrom = $this->Sender ?: $this->From ?: null;

        try {
            $runner = new SendmailRunner();           // optionally pass a custom allowlist/policy
            $runner->deliver($path, $rfc822, $envelopeFrom);
            return true;
        } catch (\RuntimeException $e) {
            // Preserve PHPMailer’s error/exception contract
            if ($this->exceptions) {
                throw new \PHPMailer\PHPMailer\Exception($e->getMessage(), 0, $e);
            }
            $this->setError($e->getMessage());
            return false;
        }
    }

}
