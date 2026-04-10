# xTailwind2 Theme Guide

This guide documents the actual `xtailwind2` implementation. It is not a generic "build any Tailwind theme" tutorial. Use it when you want to understand, extend, or restyle this specific XOOPS theme.

## 1. Theme concept

xTailwind2 was built to feel more intentional than the first `xtailwind` experiment. The goal was not "more utilities". The goal was a better visual hierarchy:

- a stronger first viewport
- a calmer main reading surface
- sidebars that support content instead of competing with it
- fewer but better palettes
- lighter chrome and better depth

In practice that means the theme uses one visual system across the shell:

- translucent floating navigation
- large rounded content surfaces
- softer borders and deeper shadows
- editorial typography with `Space Grotesk` for display and `Inter` for body text
- DaisyUI semantic colors instead of hard-coded one-off component colors

## 2. File map

The most important files are:

```text
themes/xtailwind2/
|- theme.tpl
|- theme_autorun.php
|- tailwind.config.js
|- theme.ini
|- css/
|  |- input.css
|  `- styles.css
`- tpl/
   |- nav-menu.tpl
   |- content-zone.tpl
   |- leftBlock.tpl
   |- rightBlock.tpl
   `- blockContent.tpl
```

What each file is responsible for:

- `theme.tpl`
  Owns the page shell, homepage hero, footer rails, footer bar, and the JavaScript that drives theme switching and the mobile menu.
- `theme_autorun.php`
  Sets `XoopsFormRendererTailwind` and exposes the curated palette list to Smarty as `xtailwindThemes`.
- `tailwind.config.js`
  Defines the font stacks, shell shadows, shell radius, content scan paths, and all six DaisyUI palettes.
- `css/input.css`
  Defines the real visual identity. If the theme "feels" different, it is usually because of rules in this file.
- `tpl/nav-menu.tpl`
  Contains the floating top bar, desktop dropdown navigation, search, theme switcher, and mobile drawer.
- `tpl/content-zone.tpl`
  Wraps the main page content in the central content panel.
- `tpl/leftBlock.tpl` and `tpl/rightBlock.tpl`
  Render the supporting rails.
- `tpl/blockContent.tpl`
  Normalizes raw XOOPS block output inside the theme surfaces.

## 3. Palette system

xTailwind2 does not expose the full DaisyUI theme catalog. It ships six curated palettes:

- `atelier`
- `fjord`
- `pulsefire`
- `noctis`
- `velvet`
- `graphite`

The palette definitions live in `tailwind.config.js` under `daisyui.themes`.

Each palette needs three things to work cleanly:

1. the DaisyUI palette object in `tailwind.config.js`
2. a matching entry in `theme_autorun.php`
3. a `mode` value of `light` or `dark`

The current runtime behavior is:

- light fallback: `atelier`
- dark fallback: `noctis`
- global remembered theme: `xtailwind2-theme`
- remembered per-mode themes: `xtailwind2-light-theme` and `xtailwind2-dark-theme`

That means the mode toggle is not just binary. It swaps between the last light palette and the last dark palette the visitor chose.

## 4. Page shell and homepage hero

The page shell is defined in `theme.tpl`.

Important layout decisions:

- the document starts with `data-theme="atelier"`
- an inline script replaces that before paint with the saved theme or the default dark/light fallback
- the body uses floating blurred background shapes to create depth without heavy graphics
- the homepage gets a dedicated hero only when `$xoops_page == "index"`

The homepage hero is split into two areas:

- left side: headline, supporting copy, calls to action, and three compact metric cards
- right side: a visual atmosphere panel showing palette chips and design notes

That structure is deliberate. It creates a first impression that feels like a designed front page instead of a CMS default frame.

If you want to change the homepage personality, edit the hero block in `theme.tpl` first.

## 5. Navigation and interaction model

`tpl/nav-menu.tpl` owns the navigation shell.

Desktop behavior:

- floating glass container
- brand lockup with dot mark and compact descriptor line
- category navigation using `<details>` dropdowns
- inline search on larger screens
- dark/light mode toggle
- curated theme picker panel

Mobile behavior:

- simple toggle button
- hidden panel revealed by class toggle
- stacked category links and nested `<details>` sections
- palette buttons rendered as a two-column grid

The theme switcher JavaScript is in `theme.tpl`, not in a separate file. The key functions are:

- `xtailwind2SetTheme()`
- `xtailwind2ToggleMode()`
- `xtailwind2UpdateUI()`
- `xtailwind2ToggleMobileMenu()`

That logic is intentionally small and local to the theme.

## 6. Visual language in css/input.css

`css/input.css` is where the theme gets its look.

The main design classes are:

- `.shell-container`
  Defines the site width and horizontal rhythm.
- `.glass-chrome`
  Shared translucent shell for the nav and footer bar.
- `.editorial-kicker`
  Small uppercase label used in the homepage hero.
- `.hero-panel`
  Main homepage panel with layered gradients and deeper shadow.
- `.hero-metric`
  Small supporting stat/statement cards inside the hero.
- `.content-panel`
  Main article or module surface.
- `.rail-panel`
  Lighter support surface for sidebars and footer blocks.
- `.surface-label`
  Small uppercase section labels used throughout the shell.
- `.block-content`
  Cleans up raw XOOPS block markup.
- `.xoops-content`
  Restyles raw module content like tables, headings, links, and blockquotes.

If the theme needs to feel more premium, most of the work should happen in this file, not by stacking random utility classes into templates.

## 7. Sidebars and content hierarchy

The old problem with many XOOPS themes is that every area gets the same card treatment. xTailwind2 avoids that.

The hierarchy is:

- hero first
- main content second
- side rails third
- footer rails last

That is why:

- the main reading area uses `content-panel`
- sidebars use `rail-panel`
- footer blocks stay lighter than the main content shell

If you change one of those panel classes, check the full page afterward. It is easy to accidentally flatten the whole hierarchy by making every surface look equally strong.

## 8. How to add or change a palette

### Add a new palette

1. Open `tailwind.config.js`.
2. Add a new DaisyUI palette object under `daisyui.themes`.
3. Open `theme_autorun.php`.
4. Add the same palette name to the `$themes` array with a label and a `mode`.
5. Run `npm run build`.

### Tune an existing palette

Change these semantic colors first:

- `primary`
- `secondary`
- `accent`
- `neutral`
- `base-100`
- `base-200`
- `base-300`
- `base-content`

Because the theme uses DaisyUI semantic colors consistently, a good palette update can reshape the entire site without rewriting templates.

## 9. Build workflow

From the theme directory:

```bash
npm install
npm run build
npm run watch
```

Notes:

- `css/input.css` is the source file.
- `css/styles.css` is generated output.
- Tailwind scans `theme.tpl`, all `tpl/**/*.tpl`, and all `modules/**/*.tpl` files listed in `tailwind.config.js`.
- If you add a class in a file outside those scan paths, it will not land in the compiled CSS unless you update the `content` list.

## 10. XOOPS integration notes

- The theme uses `XoopsFormRendererTailwind`, so form output is expected to fit the Tailwind shell better than the bootstrap-oriented fallback.
- `theme_autorun.php` adds the theme directory as a Smarty config dir and passes `xtailwindThemes` to the templates.
- The search form assumes XOOPS search is enabled and posts to `search.php` with `action=results`.
- Footer block regions are rendered only when those XOOPS block groups exist.

## 11. Best places to customize the theme

If you want fast, high-impact changes, work in this order:

1. `tailwind.config.js`
   Change palette mood, fonts, shadows, and radii.
2. `css/input.css`
   Change the shared surfaces and typography behavior.
3. `theme.tpl`
   Change homepage structure, hero text, footer treatment, and inline theme behavior.
4. `tpl/nav-menu.tpl`
   Change the brand bar, theme picker, and mobile drawer.
5. block templates
   Only after the overall shell is already right.

## 12. Good next upgrades

If you want to push the theme further, these are good next steps:

- add palette preview thumbnails in the switcher
- add module-specific overrides for news, profile, and forum-like layouts
- add a second homepage variant for content-heavy portals
- create a denser navigation mode for large community sites
- add subtle motion for hero entrance and panel hover states

## 13. Summary

xTailwind2 works best when it stays curated. Do not turn it back into a generic utility theme by exposing every DaisyUI theme or by making every surface the same card. Keep the palette list small, keep the main content dominant, and let the shell feel lighter than the content it frames.
