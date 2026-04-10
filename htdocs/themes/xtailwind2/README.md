# xTailwind2

xTailwind2 is a curated Tailwind CSS + DaisyUI theme for XOOPS 2.7.0. It was built as a stronger, more art-directed follow-up to `xtailwind`, with a calmer layout, a more editorial homepage, quieter side rails, and a smaller set of intentional palettes instead of a long generic theme list.

## What makes this theme different

- a floating glass top bar instead of a stock navbar
- a homepage hero that feels like a landing page, not a module wrapper
- clearer hierarchy between the main content canvas and supporting sidebars
- six curated light and dark palettes instead of the full DaisyUI catalog
- custom XOOPS content styling for tables, links, blockquotes, lists, and raw block output
- remembered light and dark theme choices using `localStorage`

## Included palettes

- `atelier` - warm editorial light
- `fjord` - crisp blue-green light
- `pulsefire` - warm high-energy light
- `noctis` - cyan-led dark default
- `velvet` - rich magenta-violet dark
- `graphite` - restrained slate dark

The default light palette is `atelier`. The default dark palette is `noctis`.

## Theme structure

- `theme.tpl`
  Main page shell, homepage hero, footer rails, and the theme-switching JavaScript.
- `tpl/nav-menu.tpl`
  Floating desktop navigation, mobile panel, search, mode toggle, and curated palette switcher.
- `tpl/content-zone.tpl`
  Main content canvas wrapper.
- `tpl/leftBlock.tpl` and `tpl/rightBlock.tpl`
  Supporting rail layout for XOOPS blocks.
- `tpl/blockContent.tpl`
  Shared block-content wrapper used across rail and footer blocks.
- `css/input.css`
  Tailwind source file with the real design language: shell spacing, glass surfaces, hero panels, content typography, and XOOPS block normalization.
- `css/styles.css`
  Compiled production CSS built from `css/input.css`.
- `tailwind.config.js`
  Tailwind scanning rules, font stacks, custom shadows/radius, and all six DaisyUI palettes.
- `theme_autorun.php`
  Selects the best available form renderer (`XoopsFormRendererTailwind` if present in XOOPS core, otherwise falls back to `XoopsFormRendererBootstrap5`) and passes the curated palette list to Smarty as `xtailwindThemes`.

## Theme behavior

### Theme memory

The theme picker stores the current choice in:

- `xtailwind2-theme`
- `xtailwind2-light-theme`
- `xtailwind2-dark-theme`

This lets the mode toggle switch between remembered light and dark palettes instead of always falling back to a single pair.

### Rendering choices

- The root `<html>` starts with `data-theme="atelier"`, then swaps to the remembered theme before paint.
- The homepage gets a dedicated hero section when `$xoops_page == "index"`.
- Footer blocks render as lighter support panels instead of repeating the full card treatment from the sidebars.
- The theme prefers `XoopsFormRendererTailwind` (XOOPS 2.5.13+) so forms stay aligned with the rest of the Tailwind shell. On older XOOPS versions without that renderer, it gracefully falls back to `XoopsFormRendererBootstrap5` — forms still render correctly because DaisyUI aliases most Bootstrap 5 form component classes.

## Build workflow

From inside the theme directory:

```bash
npm install
npm run build
npm run watch
```

`npm run build` compiles `css/input.css` into `css/styles.css`.

## Customizing the look

### Change a palette

Edit the matching theme object in `tailwind.config.js`, then keep the same name in `theme_autorun.php` so the picker still works.

### Add a new palette

1. Add a new DaisyUI theme object in `tailwind.config.js`.
2. Add the same theme name, label, and mode in `theme_autorun.php`.
3. Rebuild CSS with `npm run build`.

### Change the shell style

Most of the signature look lives in `css/input.css`:

- `.glass-chrome` controls the floating translucent surfaces.
- `.hero-panel` and `.hero-metric` shape the homepage signature.
- `.content-panel` controls the main reading canvas.
- `.rail-panel` controls sidebars and footer support panels.
- `.xoops-content` styles raw module content.

### Rework the homepage

Edit the hero section directly in `theme.tpl`. That is the right place to change headlines, supporting text, metrics, or the right-hand atmosphere panel.

### Rework navigation

Edit `tpl/nav-menu.tpl`. That file owns:

- desktop category navigation
- dropdown styling
- search box
- dark/light mode toggle
- palette switcher
- mobile drawer panel

## Recommended next refinements

- add custom thumbnails or screenshots for each palette
- create module-specific template overrides for high-traffic modules
- tune typography per palette if you want stronger editorial contrast
- add a compact dashboard variant if the site uses many admin-like modules on the front end

## License

Same license model as the surrounding theme assets and XOOPS project files in this directory.
