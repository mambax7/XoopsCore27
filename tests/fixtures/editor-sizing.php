<?php

declare(strict_types=1);

// Exercise the real shared editor renderer without a database or a selected theme.
define('XOOPS_ROOT_PATH', dirname(__DIR__, 2) . '/htdocs');
define('XOOPS_URL', 'http://xoops.test');
function xoops_load(string $name): void
{
}
class XoopsFormTextArea
{
}
require XOOPS_ROOT_PATH . '/class/xoopsform/formeditor.php';
$element = (new ReflectionClass(XoopsFormEditor::class))->newInstanceWithoutConstructor();
$element->editor = new class {
    public function render(): string
    {
        return '<textarea id="message" style="width:1200px">[b]Heading[/b]' . "\n\nParagraph</textarea>";
    }
};
echo $element->render();
