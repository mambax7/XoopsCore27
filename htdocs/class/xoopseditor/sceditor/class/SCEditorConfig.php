<?php

declare(strict_types=1);

/**
 * SCEditor settings in System > Preferences > Editors (XOOPS_CONF_EDITOR).
 *
 * One definition feeds the installer, the 2.7.4 upgrade and the editor, so the
 * preference rows, their options and the rendered toolbar cannot drift apart.
 *
 * @category  Xoops
 * @package   Xoops\Editor
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

final class SCEditorConfig
{
    /** Core preference category id; also defined as XOOPS_CONF_EDITOR. */
    public const CATEGORY = 8;

    /**
     * The complete toolbar, in groups. It is the default and the list of buttons an
     * admin can switch off; XOOPS overrides in js/xoops-bbcode.js supply the
     * server-compatible output for commands whose stock BBCode differs.
     */
    public const TOOLBAR = [
        ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript'],
        ['left', 'center', 'right', 'justify', 'ltr', 'rtl'],
        ['font', 'size', 'color', 'removeformat'],
        ['cut', 'copy', 'paste', 'pastetext'],
        ['bulletlist', 'orderedlist', 'indent', 'outdent', 'table'],
        ['link', 'unlink', 'siteurl', 'email', 'image', 'youtube', 'mp3'],
        ['quote', 'code', 'wikipage'],
        ['horizontalrule', 'date', 'time', 'emoticon'],
        ['print', 'maximize', 'source'],
    ];

    /**
     * Bundled plugins (minified/plugins/<name>.js) that work without further setup.
     * Not offered: format (switches itself off in BBCode mode), dragdrop (loaded
     * when sceditor_dragdrop_cat names an image category), emojis (needs its own data set), v1compat (old integrations),
     * alternative-lists (writes [list]/[*], which XOOPS does not decode).
     */
    public const PLUGINS = ['autosave', 'autoyoutube', 'plaintext', 'undo'];

    /**
     * The preference rows: name => [title, formtype, valuetype, default, order].
     * Titles are _MD_AM_ constants; each has a matching ...DSC description.
     */
    private const ITEMS = [
        'sceditor_toolbar'    => ['_MD_AM_SCEDITOR_TOOLBAR', 'select_multi', 'array', null, 1],
        'sceditor_plugins'    => ['_MD_AM_SCEDITOR_PLUGINS', 'select_multi', 'array', [], 2],
        'sceditor_emoticons'  => ['_MD_AM_SCEDITOR_EMOTICONS', 'yesno', 'int', 1, 3],
        'sceditor_resize'     => ['_MD_AM_SCEDITOR_RESIZE', 'yesno', 'int', 1, 4],
        'sceditor_autoexpand' => ['_MD_AM_SCEDITOR_AUTOEXPAND', 'yesno', 'int', 0, 5],
        'sceditor_spellcheck' => ['_MD_AM_SCEDITOR_SPELLCHECK', 'yesno', 'int', 1, 6],
        'sceditor_width'      => ['_MD_AM_SCEDITOR_WIDTH', 'textbox', 'text', '100%', 7],
        'sceditor_height'     => ['_MD_AM_SCEDITOR_HEIGHT', 'textbox', 'text', '400px', 8],
        'sceditor_dragdrop_cat' => ['_MD_AM_SCEDITOR_DRAGDROPCAT', 'textbox', 'int', 0, 9],
    ];

    /**
     * Every button in TOOLBAR, in order.
     *
     * @return array<int, string>
     */
    public static function buttons(): array
    {
        return array_merge(...self::TOOLBAR);
    }

    /**
     * The preference rows with their stored default and select options.
     *
     * @return array<string, array{title: string, desc: string, formtype: string, valuetype: string, value: string, order: int, options: array<int, string>}>
     */
    public static function items(): array
    {
        $items = [];
        foreach (self::ITEMS as $name => [$title, $formtype, $valuetype, $default, $order]) {
            if ('sceditor_toolbar' === $name) {
                $default = self::buttons();
            }
            $items[$name] = [
                'title'     => $title,
                'desc'      => $title . 'DSC',
                'formtype'  => $formtype,
                'valuetype' => $valuetype,
                'value'     => is_array($default) ? serialize($default) : (string) $default,
                'order'     => $order,
                'options'   => match ($name) {
                    'sceditor_toolbar' => self::buttons(),
                    'sceditor_plugins' => self::PLUGINS,
                    default            => [],
                },
            ];
        }

        return $items;
    }

    /**
     * The effective settings: saved preferences over the defaults, so the editor
     * keeps working before the category exists (an upgrade not yet run).
     *
     * @param array<string, mixed> $saved getConfigsByCat(XOOPS_CONF_EDITOR)
     *
     * @return array{toolbar: array<int, string>, plugins: array<int, string>, emoticons: bool, resize: bool, autoexpand: bool, spellcheck: bool, width: string, height: string, dragdrop_cat: int}
     */
    public static function settings(array $saved): array
    {
        $list = static fn ($value, array $allowed, array $default): array => is_array($value)
            ? array_values(array_intersect($allowed, array_map('strval', $value)))
            : $default;

        return [
            'toolbar'    => $list($saved['sceditor_toolbar'] ?? null, self::buttons(), self::buttons()),
            'plugins'    => $list($saved['sceditor_plugins'] ?? null, self::PLUGINS, []),
            'emoticons'  => (bool) ($saved['sceditor_emoticons'] ?? true),
            'resize'     => (bool) ($saved['sceditor_resize'] ?? true),
            'autoexpand' => (bool) ($saved['sceditor_autoexpand'] ?? false),
            'spellcheck' => (bool) ($saved['sceditor_spellcheck'] ?? true),
            // Raw: FormSCEditor validates CSS lengths (normalizeCssLength()).
            'width'      => (string) ($saved['sceditor_width'] ?? '100%'),
            'height'     => (string) ($saved['sceditor_height'] ?? '400px'),
            // Image category for drag-and-drop uploads; 0 switches them off.
            'dragdrop_cat' => max(0, (int) ($saved['sceditor_dragdrop_cat'] ?? 0)),
        ];
    }

    /**
     * SCEditor's toolbar string: enabled buttons in TOOLBAR order and grouping,
     * empty groups dropped.
     *
     * @param array{toolbar: array<int, string>, emoticons: bool} $settings from settings()
     *
     * @return string
     */
    public static function toolbar(array $settings): string
    {
        $enabled = array_flip($settings['toolbar']);
        if (!$settings['emoticons']) {
            unset($enabled['emoticon']);
        }
        $groups = [];
        foreach (self::TOOLBAR as $group) {
            $buttons = array_values(array_filter($group, static fn (string $button): bool => isset($enabled[$button])));
            if ($buttons !== []) {
                $groups[] = implode(',', $buttons);
            }
        }
        return implode('|', $groups);
    }
}
