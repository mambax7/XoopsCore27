/** @type {import('tailwindcss').Config} */
module.exports = {
    // Scan all template files so JIT compiler picks up utility classes
    content: [
        './theme.tpl',
        './tpl/**/*.tpl',
        './modules/**/*.tpl',
    ],
    // Safelist classes emitted by XoopsFormRendererTailwind so they are
    // always compiled, even if no template file references them directly.
    // The PHP renderer is invoked at request time, so Tailwind's JIT scanner
    // cannot see the class names in its content files.
    safelist: [
        'btn', 'btn-neutral', 'btn-primary', 'btn-secondary', 'btn-success',
        'btn-warning', 'btn-error', 'btn-info', 'btn-sm', 'btn-xs',
        'input', 'input-bordered', 'textarea', 'textarea-bordered',
        'select', 'select-bordered', 'file-input', 'file-input-bordered',
        'checkbox', 'checkbox-primary', 'radio', 'radio-primary',
        'label', 'label-text', 'label-text-alt',
        'form-control', 'card', 'card-body', 'card-title',
        'join', 'join-item', 'divider', 'dropdown', 'dropdown-content',
        'menu', 'rounded-box',
        'text-error', 'text-base-content/60',
        'bg-base-100', 'bg-base-200',
    ],
    theme: {
        extend: {},
    },
    // Enable DaisyUI for component classes (.btn, .card, .navbar, .modal, etc.)
    // and pre-built themes (34 bundled themes switchable via data-theme attribute).
    plugins: [
        require('@tailwindcss/typography'),  // .prose classes for XOOPS content output
        require('daisyui'),
    ],
    daisyui: {
        // All 34 DaisyUI themes compiled in. Comment out any you don't want
        // to ship to reduce the final CSS size (each adds ~2-3 KB).
        themes: [
            'light',      'dark',       'cupcake',    'bumblebee',  'emerald',
            'corporate',  'synthwave',  'retro',      'cyberpunk',  'valentine',
            'halloween',  'garden',     'forest',     'aqua',       'lofi',
            'pastel',     'fantasy',    'wireframe',  'black',      'luxury',
            'dracula',    'cmyk',       'autumn',     'business',   'acid',
            'lemonade',   'night',      'coffee',     'winter',     'dim',
            'nord',       'sunset',
        ],
        // When you add a new theme, also add an entry to theme_autorun.php
        // so the picker dropdown renders it.
        darkTheme: 'dark',
        logs: false,
    },
};
