<?php

declare(strict_types=1);

namespace modulesprotector;

/**
 * Protector whose final rename always fails: the failure-path fixture for
 * ProtectorTest::writeFileAtomicKeepsTheOldFileAndCleansUpWhenTheRenameFails().
 */
class ProtectorRenameFails extends \Protector
{
    protected static function moveIntoPlace(string $tmp, string $path): bool
    {
        return false;
    }
}
