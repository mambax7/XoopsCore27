<?php
/**
 * Fatal error and exception handler for XOOPS upgrade process.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */

function fatalPhpErrorHandler($e = null)
{
    $messageFormat = '<br><div>Fatal %s %s file: %s : %d </div>';
    $exceptionClass = '\Exception';
    $throwableClass = '\Throwable';
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if ($e === null) {
        $lastError = error_get_last();
        if (null !== $lastError && in_array($lastError['type'], $fatalTypes, true)) {
            printf(
                $messageFormat,
                'Error',
                htmlspecialchars((string) $lastError['message'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars(basename((string) $lastError['file']), ENT_QUOTES, 'UTF-8'),
                (int) $lastError['line']
            );
        }
    } elseif ($e instanceof $exceptionClass || $e instanceof $throwableClass) {
        /** @var \Exception $e */
        printf(
            $messageFormat,
            htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(basename($e->getFile()), ENT_QUOTES, 'UTF-8'),
            (int) $e->getLine()
        );
    }
}
register_shutdown_function('fatalPhpErrorHandler');
set_exception_handler('fatalPhpErrorHandler');
