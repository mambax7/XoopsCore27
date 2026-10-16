<?php
/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

declare(strict_types=1);

namespace Tests\Unit\Htdocs\Kernel;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * XOBJ_DTYPE_UNICODE_ARRAY must survive the write path and a reload.
 *
 * Runs in a PHP subprocess so the real include/functions.encoding.php is
 * used: the suite bootstrap stubs xoops_convert_encode() as identity.
 *
 * @category  Xoops
 * @package   Kernel
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversNothing]
final class XoopsObjectUnicodeArrayTest extends TestCase
{
    #[Test]
    public function unicodeArrayRoundTripsThroughBothWritePathsAndAssignVar(): void
    {
        $root = str_replace('\\', '/', XOOPS_ROOT_PATH);
        $directory = sys_get_temp_dir() . '/xoops-unicode-array-' . bin2hex(random_bytes(4));
        mkdir($directory);
        $script = <<<PHP
<?php
define('XOOPS_ROOT_PATH', '{$root}');
define('XOOPS_PROT', 'http://');
define('_XOBJ_ERR_REQUIRED', '%s is required');
define('_XOBJ_ERR_SHORTERTHAN', '%s must be shorter than %d characters');
class MyTextSanitizer
{
    public static function getInstance() { return new self(); }
    public function stripSlashesGPC(\$text) { return \$text; }
    public function censorString(\$text) { return \$text; }
}
require XOOPS_ROOT_PATH . '/include/functions.encoding.php';
require XOOPS_ROOT_PATH . '/kernel/object.php';
require XOOPS_ROOT_PATH . '/class/model/xoopsmodel.php';
require XOOPS_ROOT_PATH . '/class/model/write.php';

\$values = ['café', 'a+b', "O'Reilly", 'back\\\\slash', '100%'];
\$object = new class () extends XoopsObject {
    public function __construct()
    {
        parent::__construct();
        \$this->initVar('tags', XOBJ_DTYPE_UNICODE_ARRAY, []);
    }
};
\$object->setVar('tags', \$values);

// XoopsObject::cleanVars(): what the legacy handlers store.
\$object->cleanVars();
\$storedByObject = \$object->cleanVars['tags'];

// XoopsModelWrite::cleanVars(): what XoopsPersistableObjectHandler stores, SQL-quoted.
\$db = new class () {
    public function quote(\$v)
    {
        return "'" . addslashes((string) \$v) . "'";
    }
};
\$write = new XoopsModelWrite();
\$write->handler = (object) ['db' => \$db];
\$write->cleanVars(\$object);
\$storedByModel = stripslashes(substr(\$object->cleanVars['tags'], 1, -1));

\$r = ['object' => \$storedByObject, 'model' => \$storedByModel, 'loaded' => []];
foreach (['object' => \$storedByObject, 'model' => \$storedByModel] as \$path => \$column) {
    \$loaded = clone \$object;
    \$loaded->assignVar('tags', \$column); // what the handler does on a database read
    \$r['loaded'][\$path] = @\$loaded->getVar('tags');
}
echo json_encode(\$r);
PHP;
        file_put_contents($directory . '/run.php', $script);
        try {
            $process = proc_open(
                [PHP_BINARY, '-d', 'output_buffering=0', $directory . '/run.php'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            self::assertIsResource($process);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $errors . $output);
            $r = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }

        $values = ['café', 'a+b', "O'Reilly", 'back\\slash', '100%'];
        self::assertSame($r['object'], $r['model'], 'both write paths store the same serialized value');
        self::assertSame($values, $r['loaded']['object'], 'XoopsObject::cleanVars() value reloads intact');
        self::assertSame($values, $r['loaded']['model'], 'XoopsModelWrite::cleanVars() value reloads intact');
    }
}
