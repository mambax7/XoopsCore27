<?php

declare(strict_types=1);

// Render the real adapter, with only the surrounding XOOPS form/bootstrap stubbed.
define('XOOPS_ROOT_PATH', dirname(__DIR__, 2) . '/htdocs');
define('XOOPS_URL', 'http://xoops.test');
function xoops_load(string $name): void
{
}
class XoopsEditor
{
    public string $rootPath = '';
    private array $values;
    public function __construct(array $configs)
    {
        $this->values = $configs;
    }
    public function getName(): string
    {
        return $this->values['name'];
    }
    public function getValue(): string
    {
        return $this->values['value'];
    }
    public function getCols(): int
    {
        return 50;
    }
    public function getRows(): int
    {
        return 5;
    }
}
require XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor/sceditor.php';
$mode = $argv[1] ?? 'startup';
$value = $mode === 'startup' ? "[b]Saved post[/b]\n\nParagraph" : str_repeat("Paragraph with enough text to occupy a line.\n\n", 80);
$editor = new FormSCEditor(['name' => 'message', 'value' => $value]);
$editor->setHeight($argv[2] ?? '400px');
$settings = new ReflectionProperty(FormSCEditor::class, 'settings');
$values = $settings->getValue($editor);
$values['plugins'] = $mode === 'startup' ? ['autosave'] : [];
$values['autoexpand'] = $mode === 'grow';
$settings->setValue($editor, $values);
echo $editor->render();
