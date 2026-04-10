xTailwind
=========

A Tailwind CSS + DaisyUI theme for [XOOPS 2.7.0](https://xoops.org). Proof of concept sibling to xSwatch5 for site owners who prefer a utility-first CSS framework.

**Status: Proof of concept.** Use for evaluation. For production sites, see [xSwatch5](../xswatch5/).

## Why a Tailwind theme?

Tailwind takes a fundamentally different approach from Bootstrap:

- **Utility-first** — compose designs from small single-purpose classes (`flex`, `px-4`, `bg-primary`) rather than pre-styled components
- **Build step** — a compiler scans your templates and ships only the classes you actually use, producing a smaller final CSS
- **DaisyUI** bridges the gap by adding component classes (`.btn`, `.card`, `.navbar`, `.modal`) on top of Tailwind, plus 34 pre-built themes switchable via a single `data-theme` attribute

xTailwind pairs Tailwind with DaisyUI so you get the Bootswatch-style theme picker experience (34 themes out of the box) with Tailwind's smaller payload and modern tooling.

## Features

- **34 DaisyUI themes** — light, dark, cupcake, cyberpunk, synthwave, dracula, and 28 more, all switchable from the navbar
- **Live theme switcher** with localStorage persistence and FOUC-free restoration
- **Light/dark toggle** that respects OS preference on first visit
- **Alpine.js** (~15 KB) for interactive components — dropdowns, mobile nav, toasts, modals (loaded from the shared `xoops_lib/Frameworks/alpine/` location so multiple Tailwind themes share a single copy)
- **Built-in RTL support** via Tailwind logical properties
- **WCAG AA** baseline from DaisyUI's color system

## Installation

xTailwind ships with the compiled CSS already in place (`css/styles.css`), so there is **no build step required** for end users. Just drop the theme in and activate it.

1. Copy the `xtailwind/` directory to `themes/` in your XOOPS installation
2. In XOOPS admin → System → Preferences → General Settings → select `xtailwind` as the theme

Alpine.js (required for interactive components like dropdowns and mobile nav) is loaded from the shared `xoops_lib/Frameworks/alpine/` location in XOOPS core. No per-theme JavaScript install needed.

## Rebuilding the CSS (developers only)

You only need to rebuild if you modify templates, add custom CSS to `css/input.css`, or change which DaisyUI themes are bundled. End users never need to run these commands.

Prerequisites: Node.js 18+ installed on your development machine.

```bash
cd themes/xtailwind
npm install      # one-time: downloads Tailwind, DaisyUI, and Typography plugin
npm run build    # compiles css/input.css → css/styles.css (minified)
```

For live rebuilding during development:
```bash
npm run watch
```

After editing and rebuilding, commit the updated `css/styles.css` so the next person can use the theme without a build step.

## Theme Switcher

The navbar includes:

**Dark/Light toggle** — switches to a sensible light or dark DaisyUI theme. The opposite mode is picked based on the current theme's classification (light/dark) defined in `theme_autorun.php`.

**Theme dropdown** — lists all 34 DaisyUI themes. Selecting one applies instantly and persists to `localStorage`. Disable themes you don't want by removing them from both `tailwind.config.js` (the `daisyui.themes` array) **and** `theme_autorun.php` (the `$daisyThemes` array), then rebuild.

## How it compares to xSwatch5

| | xSwatch5 | xTailwind |
|---|----------|-----------|
| CSS framework | Bootstrap 5.3.8 | Tailwind 3.4 + DaisyUI 4 |
| Themes | 21 Bootswatch variants | 34 DaisyUI themes |
| CSS approach | Component library | Utility-first + DaisyUI components |
| JavaScript | Bootstrap bundle | Alpine.js (shared via `xoops_lib/Frameworks/alpine/`) |
| End-user install | Drop-in (ships compiled CSS) | Drop-in (ships compiled CSS) |
| Developer rebuild | N/A (no build step) | Optional, via `npm run build` |
| Dark mode | `data-bs-theme` attribute | `data-theme` attribute |
| RTL | Bootstrap logical props | Tailwind logical props |
| Form renderer | `XoopsFormRendererBootstrap5` | `XoopsFormRendererTailwind` (native DaisyUI) |

## Known Limitations (Proof of Concept)

- **Module templates not included** — only core theme templates are provided. Module overrides (newbb, publisher, wggallery, etc.) still need to be rewritten from the xSwatch5 Bootstrap versions.
- **Admin toolbar** — not yet ported from xSwatch5.
- **Cookie consent** — not yet ported.
- **Slider / jumbotron alternatives** — basic hero section included; full slider not ported.

## Customization

- **Custom styles** — edit `css/input.css` and use Tailwind's `@apply` directive or raw CSS in the appropriate `@layer`. Rebuild with `npm run build`.
- **Enable/disable themes** — edit `daisyui.themes` in `tailwind.config.js` and `$daisyThemes` in `theme_autorun.php`. Each enabled theme adds ~2-3 KB to the compiled CSS.
- **Custom color palette** — add a custom theme to `tailwind.config.js` under `daisyui.themes` with your own primary/secondary/accent colors. See [DaisyUI theming docs](https://daisyui.com/docs/themes/).

## Requirements

### End users (site administrators)
- XOOPS 2.5.11+ (RTL auto-detection requires 2.7.0+)
- PHP 8.2+
- `XoopsFormRendererTailwind` class in XOOPS core is **recommended** (shipped with XOOPS 2.5.13+). If your XOOPS version doesn't include it, the theme automatically falls back to the Bootstrap 5 form renderer — forms will still work and look mostly correct because DaisyUI aliases most Bootstrap 5 form classes. For pixel-perfect form styling, add `XoopsFormRendererTailwind.php` to `htdocs/class/xoopsform/renderer/` manually.

### Developers (only if modifying and rebuilding the theme)
- Node.js 18+

## Credits

- Adam Wathan and team — [Tailwind CSS](https://tailwindcss.com/)
- Saadeghi — [DaisyUI](https://daisyui.com/)
- Caleb Porzio — [Alpine.js](https://alpinejs.dev/)
- XOOPS Project — template integration patterns

## License

GPL v3 — see the XOOPS project license. Third-party assets are MIT-licensed.
