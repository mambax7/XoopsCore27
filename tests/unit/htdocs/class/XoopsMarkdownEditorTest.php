<?php

declare(strict_types=1);

/**
 * Central Markdown support end to end: rendering and security, request
 * transport, the EasyMDE adapter, editor selection, and legacy formats.
 * Ported from the standalone tests/markdown.php, tests/editor-roundtrip.php,
 * tests/list-spacing.php, tests/markdown-editor-selection.php and
 * tests/markdown-submit.cjs so CI runs them.
 *
 * Each test runs in its own process: they load real editor classes, replace
 * $_POST and the editor handler singleton.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(XoopsMarkdown::class)]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class XoopsMarkdownEditorTest extends TestCase
{
    private const SOURCE = "# Heading\n\n**bold**\n\n| A | B |\n| --- | --- |\n| one | two |\n\n```php\n[b]literal[/b] :)\n    echo '<tag>';\n```";

    private const ENTITIES = "`&lt;tag&gt;` & \"quoted\" <tag>";

    private MyTextSanitizer $myts;

    protected function setUp(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/module.textsanitizer.php';
        require_once XOOPS_ROOT_PATH . '/class/xoopsmarkdown.php';
        $this->myts = (new ReflectionClass(MyTextSanitizer::class))->newInstanceWithoutConstructor();
        $this->myts->config  = ['extensions' => []];
        $this->myts->smileys = [['code' => ':)', 'smile_url' => 'smile.png']];
        $_POST = [];
    }

    private static function stored(): string
    {
        return '[xoops:markdown="1"]' . "\n" . strtr(self::SOURCE, ['<' => '%3C', '>' => '%3E']) . "\n[/xoops:markdown]";
    }

    private static function loadEditors(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/xoopsform/formelement.php';
        require_once XOOPS_ROOT_PATH . '/class/xoopsform/formtextarea.php';
        require_once XOOPS_ROOT_PATH . '/class/xoopseditor/xoopseditor.php';
        require_once XOOPS_ROOT_PATH . '/class/xoopseditor/easymde/easymde.php';
    }

    private static function dom(string $html): DOMDocument
    {
        $document = new DOMDocument();
        $document->loadHTML('<!doctype html><html><body>' . $html . '</body></html>', LIBXML_NOERROR);

        return $document;
    }

    /** Submit an editor form as a browser would: its inputs plus the textarea. */
    private static function submitted(DOMDocument $document, string $message): array
    {
        $pairs = [];
        foreach ($document->getElementsByTagName('input') as $input) {
            $pairs[] = urlencode($input->getAttribute('name')) . '=' . urlencode($input->getAttribute('value'));
        }
        parse_str(implode('&', $pairs), $post);
        $post['message'] = $message;

        return $post;
    }

    #[Test]
    public function storedMarkdownRendersWithLiteralCode(): void
    {
        $html = $this->myts->displayTarea(self::stored(), 0, 1, 1, 1, 1);

        $this->assertStringContainsString('<h1>Heading</h1>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString("[b]literal[/b] :)\n    echo '&lt;tag&gt;';", $html, 'code whitespace, BBCode and smileys stay literal');
        $this->assertSame($html, $this->myts->previewTarea(self::stored(), 0, 1, 1, 1, 1), 'preview uses the same renderer');
    }

    #[Test]
    public function storageFormatRoundTrips(): void
    {
        $stored = self::stored();
        $this->assertSame($stored, XoopsMarkdown::wrap(self::SOURCE));
        $this->assertSame($stored, XoopsMarkdown::wrap($stored), 'no duplicate wrapper on resubmit');
        $this->assertSame('  ', XoopsMarkdown::wrap('  '), 'empty content stays empty for validation');
        $this->assertSame(self::SOURCE, XoopsMarkdown::source($stored));

        $encoded = htmlspecialchars(XoopsMarkdown::wrap(self::ENTITIES), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertSame(self::ENTITIES, XoopsMarkdown::source($encoded), "getVar('e') escaping is decoded exactly once");
        $this->assertSame(self::ENTITIES, XoopsMarkdown::source(XoopsMarkdown::wrap(self::ENTITIES)), 'raw entity text is not decoded');

        $reserved = "100% %3C %26 %25 %5B\n[/xoops:markdown]\n[xoops:markdown=\"1\"]";
        $this->assertSame($reserved, XoopsMarkdown::source(XoopsMarkdown::wrap($reserved)), 'percent sequences and marker examples survive');
        $this->assertSame('# Heading', XoopsMarkdown::source(str_replace("\n", "\r\n", XoopsMarkdown::wrap('# Heading'))), 'browser CRLF');
    }

    #[Test]
    public function markdownNeverInheritsHtmlPermissionOrBypassesImageRules(): void
    {
        $attack = XoopsMarkdown::wrap('<script>alert(1)</script> <img src=x onerror=alert(1)> [bad](javascript:alert%281%29)');
        foreach ([0, 1] as $html) {
            $safe = $this->myts->displayTarea($attack, $html);
            $this->assertStringNotContainsString('<script', $safe);
            $this->assertStringNotContainsString('<img', $safe);
            $this->assertStringNotContainsString('href="javascript:', $safe);
        }
        $image = XoopsMarkdown::wrap('![alternative](https://example.test/image.png)');
        $this->assertStringContainsString('<img', $this->myts->displayTarea($image, 0, 0, 0, 1));
        $noImage = $this->myts->displayTarea($image, 0, 0, 0, 0);
        $this->assertStringNotContainsString('<img', $noImage);
        $this->assertStringContainsString('alternative', $noImage, 'image restriction keeps the alternative text');
    }

    #[Test]
    public function legacyFormatsAreUnchanged(): void
    {
        $this->assertSame('**legacy**', $this->myts->displayTarea('**legacy**', 0, 0, 0, 0, 0), 'unmarked text is never guessed');
        $this->assertStringContainsString('<strong>legacy</strong>', $this->myts->displayTarea('[b]legacy[/b]', 0, 0, 1));
        $this->assertStringContainsString('<strong>legacy</strong>', $this->myts->displayTarea('<strong>legacy</strong>', 1, 0, 0));
    }

    #[Test]
    public function quotedAndConcatenatedDocumentsRender(): void
    {
        $stored = self::stored();
        $quoted = $this->myts->displayTarea("[quote]\nAuthor wrote:\n" . htmlspecialchars($stored, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "[/quote]\nReply", 0, 0, 1);
        $this->assertStringContainsString('<blockquote>', $quoted);
        $this->assertStringContainsString('<h1>Heading</h1>', $quoted, 'NewBB-style quoted Markdown');

        $quoteSource = XoopsMarkdown::editorSource("[quote]\nAuthor wrote:\n" . htmlspecialchars($stored, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '[/quote]');
        $this->assertStringNotContainsString('[xoops:markdown=', $quoteSource, 'markers are hidden in quoted editor content');
        $reply = $this->myts->displayTarea(XoopsMarkdown::wrap($quoteSource));
        $this->assertStringContainsString('<blockquote>', $reply);
        $this->assertStringContainsString('<h1>Heading</h1>', $reply);

        $two = $stored . "\nReply\n" . $stored;
        $this->assertSame(2, substr_count($this->myts->displayTarea($two, 0, 0, 0), '<h1>Heading</h1>'), 'concatenated fields render separately');
        $this->assertSame(2, substr_count($this->myts->displayTarea("[quote]\n" . $two . '[/quote]', 0, 0, 1), '<h1>Heading</h1>'));
        $code = '[code]' . $stored . '[/code]';
        $this->assertSame([], XoopsMarkdown::protect($code), 'code examples are not interpreted');
    }

    #[Test]
    public function requestTransportTagsEditorFieldsOnly(): void
    {
        $stored = self::stored();
        $post = ['message' => self::SOURCE, 'body' => ['intro' => '# Intro'], 'subject' => 'Unchanged',
            '_xoops_markdown' => ['message', 'body[intro]'], '_xoops_markdown_save' => '1'];
        foreach ($post['_xoops_markdown'] as $field) {
            $post['_xoops_markdown_state'][hash('sha256', $field)] = ['initial' => hash('sha256', ''), 'marked' => '0'];
        }
        $request = $post;

        XoopsMarkdown::preparePost($post, $request);
        $this->assertSame($stored, $post['message']);
        $this->assertSame(XoopsMarkdown::wrap('# Intro'), $post['body']['intro'], 'nested fields');
        $this->assertSame('Unchanged', $post['subject']);
        $this->assertSame($stored, $request['message'], 'legacy REQUEST readers');
        XoopsMarkdown::preparePost($post, $request);
        $this->assertSame($stored, $post['message'], 'idempotent');

        $_POST = ['message' => XoopsMarkdown::wrap(self::ENTITIES)];
        foreach (['getString', 'getText'] as $method) {
            $this->assertSame(self::ENTITIES, XoopsMarkdown::source(\Xmf\Request::$method('message', '', 'POST')), 'XMF ' . $method);
        }

        $bad = ['message' => ['invalid'], '_xoops_markdown' => ['message', ['bad'], 'missing', 'body[]']];
        $badRequest = $bad;
        XoopsMarkdown::preparePost($bad, $badRequest);
        $this->assertSame(['invalid'], $bad['message'], 'malformed metadata never coerces input');
        $this->assertArrayNotHasKey('missing', $bad);
    }

    #[Test]
    public function easyMdeShowsExactSourceAndDeclaresItsField(): void
    {
        self::loadEditors();
        $encoded = htmlspecialchars(XoopsMarkdown::wrap(self::ENTITIES), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $document = self::dom((new FormEasyMDE(['name' => 'message', 'value' => $encoded]))->render());

        $this->assertSame(self::ENTITIES, $document->getElementsByTagName('textarea')->item(0)->textContent);
        $fields = [];
        foreach ($document->getElementsByTagName('input') as $input) {
            if ($input->getAttribute('name') === '_xoops_markdown[]') {
                $fields[] = $input->getAttribute('value');
            }
        }
        $this->assertSame(['message'], $fields, 'the field format is declared without JavaScript');
    }

    #[Test]
    public function xoopsObjectRendersMarkdownCentrally(): void
    {
        if (!is_file(XOOPS_VAR_PATH . '/configs/textsanitizer/config.php')) {
            $this->markTestSkipped('Needs an installed sanitizer config; tests must not create site config.');
        }
        require_once XOOPS_ROOT_PATH . '/kernel/object.php';
        $html    = $this->myts->displayTarea(self::stored(), 0, 1, 1, 1, 1);
        $article = new XoopsObject();
        $article->initVar('body', XOBJ_DTYPE_TXTAREA, self::stored());

        $this->assertSame($html, $article->getVar('body'));
        $this->assertSame($html, $article->getVar('body', 'p'));
        $this->assertSame(self::SOURCE, XoopsMarkdown::source($article->getVar('body', 'e')));
        $this->assertSame(self::stored(), $article->getVar('body', 'n'));
    }

    #[Test]
    public function onlyAnEditedFieldSavedWithSaveBecomesMarkdown(): void
    {
        self::loadEditors();
        $original = '<p><strong>Reading the Overview.</strong> &amp; details</p><table><tr><td>Manifest</td><td>module.json</td></tr></table>';
        $key  = hash('sha256', 'message');
        $post = ['message' => $original, '_xoops_markdown' => ['message'],
            '_xoops_markdown_state' => [$key => ['initial' => hash('sha256', $original), 'marked' => '0']]];
        $request = $post;

        XoopsMarkdown::preparePost($post, $request);
        $this->assertSame($original, $post['message'], 'opening/switching unchanged HTML adds no marker');
        $post['_xoops_markdown_save'] = '1';
        XoopsMarkdown::preparePost($post, $request);
        $this->assertSame($original, $post['message'], 'saving unchanged HTML from EasyMDE does not tag it');

        $post['message'] = '# Changed';
        $post['_xoops_markdown_save'] = '0';
        $request = $post;
        XoopsMarkdown::preparePost($post, $request);
        $this->assertSame('# Changed', $post['message'], 'a preview never adds a marker');
        $this->assertSame('<h1>Changed</h1>', $this->myts->previewTarea('# Changed'), 'unsaved Markdown previews as Markdown');
        $this->assertStringContainsString('font-size: x-large', $this->myts->previewTarea('[size=&quot;x-large&quot;]Title[/size]'));

        // Redisplay after Preview keeps the original baseline, so Save still converts.
        $_POST    = $post;
        $document = self::dom((new FormEasyMDE(['name' => 'message', 'value' => '# Changed']))->render());
        $post     = self::submitted($document, $document->getElementsByTagName('textarea')->item(0)->textContent);
        $this->assertSame(hash('sha256', $original), $post['_xoops_markdown_state'][$key]['initial']);
        $post['_xoops_markdown_save'] = '1';
        $request = $post;
        XoopsMarkdown::preparePost($post, $request);
        $this->assertSame("[xoops:markdown=\"1\"]\n# Changed\n[/xoops:markdown]", $post['message']);

        $post['message'] = '# Existing';
        $post['_xoops_markdown_state'][$key] = ['marked' => '1', 'initial' => hash('sha256', '# Existing')];
        XoopsMarkdown::preparePost($post, $request);
        $this->assertSame('# Existing', XoopsMarkdown::source($post['message']), 'existing Markdown keeps its format');

        $_POST    = [];
        $document = self::dom((new FormEasyMDE(['name' => 'message', 'value' => htmlspecialchars($original, ENT_QUOTES | ENT_HTML5, 'UTF-8')]))->render());
        $this->assertSame($original, $document->getElementsByTagName('textarea')->item(0)->textContent, 'no double escaping');

        $post = ['message' => "# Same\r\n\r\ntext", '_xoops_markdown' => ['message'], '_xoops_markdown_save' => '1',
            '_xoops_markdown_state' => [$key => ['initial' => hash('sha256', "# Same\n\ntext"), 'marked' => '0']]];
        $request = $post;
        XoopsMarkdown::preparePost($post, $request);
        $this->assertStringNotContainsString('[xoops:markdown=', $post['message'], 'browser newline normalization is not an edit');
    }

    #[Test]
    public function html5TextareaLeadingNewlineIsNotAnEdit(): void
    {
        if (!class_exists('Dom\\HTMLDocument')) {
            $this->markTestSkipped('Needs the PHP 8.4 HTML5 parser.');
        }
        self::loadEditors();
        $original = '<p>x</p>';
        foreach (["\n", "\r\n", "\r"] as $newline) {
            $editor = new FormEasyMDE(['name' => 'message', 'value' => htmlspecialchars($newline . $original, ENT_QUOTES | ENT_HTML5, 'UTF-8')]);
            $html5  = Dom\HTMLDocument::createFromString($editor->render(), LIBXML_NOERROR);
            $this->assertSame("\n" . $original, $html5->getElementsByTagName('textarea')->item(0)->textContent);
        }
    }

    #[Test]
    public function listSourceNewlinesAddNoEmptyRows(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/textsanitizer/ul/ul.php';
        require_once XOOPS_ROOT_PATH . '/class/textsanitizer/li/li.php';
        $this->myts->config['extensions'] = ['ul' => 1, 'li' => 1];
        $this->myts->path_basic  = XOOPS_ROOT_PATH . '/class/textsanitizer';
        $this->myts->path_plugin = XOOPS_ROOT_PATH . '/class/textsanitizer';

        $html = $this->myts->displayTarea("[ul]\n[li]one[/li]\n[li]two[/li]\n[/ul]", 0, 0, 1, 1, 1);

        $this->assertStringNotContainsString('<br>', $html);
        $this->assertStringContainsString('<ul><li>one</li><li>two</li></ul>', $html);
    }

    #[Test]
    public function storedMarkdownSelectsEasyMdeAndNeverAnHtmlEditor(): void
    {
        self::loadEditors();
        $GLOBALS['xoopsConfig']['language'] = 'english';
        // Isolate editor discovery from the site cache; routing and EasyMDE are real.
        $handler = new class extends XoopsEditorHandler {
            public array $loaded = [];

            public function getList($noHtml = false)
            {
                $list = ['easymde' => 'Markdown', 'tinymce5' => 'TinyMCE5', 'tinymce7' => 'TinyMCE7'];

                return $this->allowed_editors ? array_intersect_key($list, array_flip($this->allowed_editors)) : $list;
            }

            public function _loadEditor($name, $options = null)
            {
                $this->loaded[] = $name;

                return $name === 'easymde' ? parent::_loadEditor($name, $options) : new XoopsFormTextArea('', $options['name'], $options['value']);
            }
        };
        $markdown = "# Heading\n\n| A | B |\n| --- | --- |\n| one | two |\n\n```php\n    echo '&lt;tag&gt;';\n```";
        $stored   = XoopsMarkdown::wrap($markdown);
        foreach (['tinymce5', 'tinymce7'] as $remembered) {
            foreach ([$stored, htmlspecialchars($stored, ENT_QUOTES | ENT_HTML5, 'UTF-8')] as $editValue) {
                $handler->loaded = [];
                $selected = $handler->get($remembered, ['name' => 'message', 'value' => $editValue]);
                $this->assertInstanceOf(FormEasyMDE::class, $selected, 'stored Markdown overrides remembered ' . $remembered);
                $this->assertNotContains($remembered, $handler->loaded, 'the HTML editor never receives Markdown source');
                $this->assertSame($markdown, self::dom($selected->render())->getElementsByTagName('textarea')->item(0)->textContent);
            }
        }

        $handler->loaded = [];
        $handler->get('tinymce7', ['name' => 'message', 'value' => '<p>HTML stays HTML</p>']);
        $this->assertSame(['tinymce7'], $handler->loaded, 'unmarked HTML keeps its editor');

        $handler->allowed_editors = ['tinymce7'];
        $handler->loaded = [];
        $fallback = $handler->get('tinymce7', ['name' => 'message', 'value' => $stored]);
        $this->assertSame([], $handler->loaded);
        $this->assertInstanceOf(XoopsFormTextArea::class, $fallback, 'without EasyMDE: a plain textarea, never HTML');
        $this->assertSame($stored, $fallback->getValue(), 'the fallback keeps the stored document');

        $damaged = '<p>[xoops:markdown="1"] # Heading &mdash; text [/xoops:markdown]</p>';
        $editor  = new FormEasyMDE(['name' => 'message', 'value' => htmlspecialchars($damaged, ENT_QUOTES | ENT_HTML5, 'UTF-8')]);
        $this->assertSame($damaged, self::dom($editor->render())->getElementsByTagName('textarea')->item(0)->textContent, 'no repeated escaping');

        // Reopen, edit, preview, save.
        $handler->allowed_editors = [];
        $selected = $handler->get('tinymce7', ['name' => 'message', 'value' => htmlspecialchars($stored, ENT_QUOTES | ENT_HTML5, 'UTF-8')]);
        $document = self::dom($selected->render());
        $post     = self::submitted($document, $document->getElementsByTagName('textarea')->item(0)->textContent . "\n\nMore **tests**");
        $request  = $post;
        XoopsMarkdown::preparePost($post, $request);
        $this->assertStringContainsString('<strong>tests</strong>', $this->myts->previewTarea($post['message']));
        $post['_xoops_markdown_save'] = '1';
        XoopsMarkdown::preparePost($post, $request);
        $this->assertSame($markdown . "\n\nMore **tests**", XoopsMarkdown::source($post['message']));
    }

    #[Test]
    public function editorSelectorOffersOnlyTheSafeEditorForMarkdown(): void
    {
        // Real discovery, wrapper, selector and renderer; the selector is limited
        // to two editors that ship with core.
        self::loadEditors();
        $GLOBALS['xoopsConfig']['language'] = 'english';
        foreach (['form', 'formelementtray', 'formselect', 'formselecteditor', 'formeditor'] as $file) {
            require_once XOOPS_ROOT_PATH . '/class/xoopsform/' . $file . '.php';
        }
        foreach (['XoopsFormRendererInterface', 'XoopsFormRenderer', 'XoopsFormRendererLegacy'] as $file) {
            require_once XOOPS_ROOT_PATH . '/class/xoopsform/renderer/' . $file . '.php';
        }
        $stored   = XoopsMarkdown::wrap("# Heading\n\ntext");
        $form     = new class('', 'editpost', '', 'post', false) extends XoopsForm {};
        $selector = new XoopsFormSelectEditor($form, 'editor', 'tinymce7', false, ['easymde', 'tinymce7']);
        $form->addElement($selector);
        $wrapper = new XoopsFormEditor('Body', 'tinymce7', ['name' => 'message', 'value' => htmlspecialchars($stored, ENT_QUOTES | ENT_HTML5, 'UTF-8')]);
        $form->addElement($wrapper);

        $this->assertInstanceOf(FormEasyMDE::class, $wrapper->editor);
        $choices = self::dom($selector->render())->getElementsByTagName('option');
        $this->assertSame(1, $choices->length, 'no destructive switch to an HTML editor');
        $this->assertSame('easymde', $choices->item(0)->getAttribute('value'));
        $this->assertTrue($choices->item(0)->hasAttribute('selected'));

        $wrapper->editor->setValue('<p>Ordinary HTML</p>');
        $ordinary = new XoopsFormSelectEditor($form, 'editor', 'tinymce7', false, ['easymde', 'tinymce7']);
        $this->assertSame(2, self::dom($ordinary->render())->getElementsByTagName('option')->length, 'unmarked content keeps its choices');
    }

    #[Test]
    public function generatedSubmitIntentScriptMarksOnlyRealSaves(): void
    {
        $node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node 2>NUL' : 'command -v node 2>/dev/null'));
        if ($node === '') {
            $this->markTestSkipped('Needs Node.js to run the generated editor script.');
        }
        self::loadEditors();
        $script = '';
        foreach (self::dom((new FormEasyMDE(['name' => 'message', 'value' => '']))->render())->getElementsByTagName('script') as $element) {
            if (!$element->hasAttribute('src')) {
                $script .= $element->textContent . "\n";
            }
        }
        $file = tempnam(sys_get_temp_dir(), 'mde');
        file_put_contents($file, $script);
        try {
            exec('node ' . escapeshellarg(dirname(__DIR__, 3) . '/fixtures/easymde-submit-intent.cjs') . ' ' . escapeshellarg($file) . ' 2>&1', $output, $status);
        } finally {
            unlink($file);
        }
        $this->assertSame(0, $status, implode("\n", $output));
    }
}
