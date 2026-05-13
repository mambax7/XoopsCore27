<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/include/file_safety.php';

/**
 * Coverage for the four file-safety helpers in
 * htdocs/include/file_safety.php: xoops_safe_basename(),
 * xoops_chmod_quietly(), xoops_remove_file_quietly(), and
 * xoops_file_label(). These helpers are tested as defensive /
 * non-propagating, but with different failure contracts:
 * xoops_safe_basename() returns a fixed placeholder for invalid
 * paths, while xoops_chmod_quietly() and
 * xoops_remove_file_quietly() may either emit a single
 * E_USER_WARNING or return without warning for guarded / no-op
 * cases. These tests pin that behavior specifically against
 * null-byte payloads, which trigger ValueError in underlying
 * filesystem calls on PHP 8+ and motivated the explicit
 * catch(\Throwable) wrappers and xoops_safe_basename()'s
 * defensive shape.
 */
class FileSafetyTest extends TestCase
{
    public function testXoopsSafeBasenameReturnsPlaceholderForNullBytePath(): void
    {
        $this->assertSame(
            'invalid-path',
            xoops_safe_basename("bad\0path"),
            'null-byte payload must collapse to the fixed placeholder'
        );
    }

    public function testXoopsSafeBasenameNormalisesBackslashes(): void
    {
        $this->assertSame(
            'foo.png',
            xoops_safe_basename('avatars\\sub\\foo.png'),
            'Windows-style separators must be normalised before basename()'
        );
    }

    public function testXoopsSafeBasenamePassesThroughOrdinaryPath(): void
    {
        $this->assertSame('foo.png', xoops_safe_basename('avatars/foo.png'));
    }

    public function testXoopsChmodQuietlyDoesNotPropagateOnNullBytePath(): void
    {
        // chmod() raises ValueError on PHP 8+ when its $filename
        // argument contains "\0". xoops_chmod_quietly() must catch that
        // and report failure via its boolean return + a single
        // E_USER_WARNING, never letting the exception escape. Use a
        // local error handler instead of the @ operator to swallow the
        // warning — this codebase forbids @-suppression and we want to
        // assert the warning still fires.
        $captured            = [];
        $unexpectedWarnings  = [];
        set_error_handler(static function (int $level, string $msg) use (&$captured, &$unexpectedWarnings): bool {
            if (E_USER_WARNING === $level) {
                $captured[] = $msg;
                return true;
            }
            $unexpectedWarnings[] = [$level, $msg];
            return true;
        });

        try {
            $result = xoops_chmod_quietly("bad\0path", 0644, 'test');
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($result, 'chmod on a null-byte path must report failure');
        $this->assertSame([], $unexpectedWarnings, 'native chmod warnings must be suppressed');
        $this->assertCount(1, $captured, 'helper must emit exactly one E_USER_WARNING on failure');
        $this->assertStringContainsString('invalid-path', $captured[0]);
    }

    public function testXoopsRemoveFileQuietlyDoesNotPropagateOnNullBytePath(): void
    {
        // The pre-check file_exists() / is_link() may return false or
        // throw for a "\0"-bearing path depending on PHP/runtime
        // behavior. The helper wraps both in catch(\Throwable) for
        // forward-compat and userland error handlers that may throw.
        // The contract being tested is simply that no exception escapes.
        $this->expectNotToPerformAssertions();
        xoops_remove_file_quietly("bad\0path");
    }

    public function testXoopsRemoveFileQuietlyIsNoOpForMissingPath(): void
    {
        // A non-existent path must NOT emit a warning — only paths that
        // still exist after a failed unlink() should. Wire up an error
        // handler to assert no E_USER_WARNING fires.
        $warningFired = false;
        set_error_handler(static function (int $level) use (&$warningFired): bool {
            if (E_USER_WARNING === $level) {
                $warningFired = true;
                return true;
            }
            return false;
        });

        try {
            xoops_remove_file_quietly(
                sys_get_temp_dir() . '/xoops_definitely_missing_' . bin2hex(random_bytes(16)) . '.tmp'
            );
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($warningFired, 'no warning should fire for an already-absent path');
    }

    public function testXoopsFileLabelStripsXoopsRootPrefix(): void
    {
        // xoops_file_label() keeps install-relative context (unlike
        // xoops_safe_basename()) for atomic-write call sites that
        // benefit from knowing WHICH file failed. Spot-check the
        // strip-prefix branch and the basename fallback.
        $absUnderRoot = rtrim(XOOPS_ROOT_PATH, '/\\') . '/uploads/foo.png';
        $this->assertSame('uploads/foo.png', xoops_file_label($absUnderRoot));

        // Backslash separators under XOOPS_ROOT_PATH must collapse to
        // forward slashes so the relative label looks the same on
        // Windows and *nix. The outside-root branch falls through to
        // basename(), which is platform-dependent on '\\' — not
        // asserted here.
        $absUnderRootBackslash = rtrim(str_replace('/', '\\', XOOPS_ROOT_PATH), '\\') . '\\uploads\\bar.png';
        $this->assertSame('uploads/bar.png', xoops_file_label($absUnderRootBackslash));

        $absOutside = sys_get_temp_dir() . '/foo.png';
        $this->assertSame('foo.png', xoops_file_label($absOutside));
    }
}
