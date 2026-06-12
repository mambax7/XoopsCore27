# xoops/smartyextensions

Domain-grouped Smarty 4/5 plugins for the XOOPS CMS.

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![Smarty](https://img.shields.io/badge/Smarty-4.x%20%7C%205.x-orange)](https://smarty.net/)

---

## Overview

`xoops/smartyextensions` replaces the traditional flat pile of Smarty plugin files with a structured, testable, PSR-4 library. Extensions are grouped by domain, registered through a unified `ExtensionRegistry`, and fully compatible with both Smarty 4 (`registerPlugin`) and Smarty 5 (`addExtension`).

XOOPS uses `<{` and `}>` as Smarty delimiters. All examples below use these delimiters.

---

## Requirements

| Requirement | Version   |
|-------------|-----------|
| PHP         | ^8.2      |
| Smarty      | ^4.5 or ^5.0 |

---

## Installation

```bash
composer require xoops/smartyextensions
```

---

## Quick Start

```php
use Xoops\SmartyExtensions\ExtensionRegistry;
use Xoops\SmartyExtensions\Extension\TextExtension;
use Xoops\SmartyExtensions\Extension\SecurityExtension;

$registry = new ExtensionRegistry();
$registry->add(new TextExtension());
$registry->add(new SecurityExtension($xoopsSecurity, $xoopsGroupPermHandler));
$registry->registerAll($smarty); // works with both Smarty 4 and Smarty 5
```

Or register a single extension directly:

```php
(new TextExtension())->register($smarty);
```

---

## Extension Catalogue

### TextExtension

Modifiers for string processing and readability.

| Name | Signature | Description |
|---|---|---|
| `excerpt` | `$text\|excerpt:150:' ...'` | Truncate at word boundary with multibyte safety |
| `truncate_words` | `$text\|truncate_words:20` | Truncate at exact word count |
| `nl2p` | `$text\|nl2p` | Convert newlines to `<p>` and `<br>` |
| `highlight_text` | `$text\|highlight_text:'XOOPS'` | Wrap search term in `<span class="highlight">` |
| `reading_time` | `$text\|reading_time` | Estimated reading time, e.g. "3 min read" |
| `pluralize` | `$count\|pluralize:'comment'` | Singular/plural based on count |
| `extract_hashtags` | `$text\|extract_hashtags` | Returns array of hashtag strings |

---

### FormatExtension

Date, time, number, and display formatting.

| Name | Signature | Description |
|---|---|---|
| `format_date` | `$date\|format_date:'Y-m-d'` | Format any date string or timestamp |
| `relative_time` | `$ts\|relative_time` | "3 days ago" / "2 hours from now" |
| `format_currency` | `$amount\|format_currency:'USD'` | Currency formatting with ICU/intl fallback |
| `number_format` | `$n\|number_format:2:',':'.'` | Locale-aware number formatting |
| `bytes_format` | `$bytes\|bytes_format:1` | Human-readable file size (e.g. "1.5 MB") |
| `format_phone_number` | `$phone\|format_phone_number` | Formats 10- or 11-digit US phone numbers |
| `gravatar` | `$email\|gravatar:64:'mp'` | Gravatar URL for an email address |
| `datetime_diff` | `<{datetime_diff start="2024-01-01" end="2026-04-01"}>` | Human-readable span between two dates |
| `get_current_year` | `<{get_current_year}>` | Current 4-digit year |

---

### NavigationExtension

URL generation, breadcrumbs, pagination, and social sharing.

| Name | Type | Description |
|---|---|---|
| `generate_url` | function | Build a URL with query params; direct output is HTML-escaped, `assign` stores raw URL |
| `generate_canonical_url` | function | Build canonical URL from `XOOPS_URL`; no-op without it |
| `url_segment` | function | Extract a path segment from the current URL |
| `social_share` | function | Social share links/bar (Twitter, Facebook, LinkedIn) |
| `render_breadcrumbs` | function | Bootstrap 5 breadcrumb nav |
| `render_pagination` | function | Bootstrap 5 pagination with prev/next |
| `render_qr_code` | function | `<img>` tag via Google Charts QR API |
| `render_alert` | function | Bootstrap 5 dismissible alert |
| `parse_url` | modifier | Returns parsed URL components as array |
| `strip_protocol` | modifier | Removes `http://` or `https://` scheme |
| `slugify` | modifier | Converts text to URL-safe slug |
| `youtube_id` | modifier | Extracts YouTube video ID from any URL format |
| `linkify` | modifier | Converts plain-text URLs to `<a>` anchors |

**Assign contract**: when `assign` is given, the **raw** value is stored. Only direct output applies `htmlspecialchars`.

```smarty
<{generate_url route="modules/news/article.php" params=['id' => 42] assign="articleUrl"}>
<a href="<{$articleUrl|escape}>">Read more</a>
```

---

### SecurityExtension

CSRF, permission checks, email masking, and sanitization.

**Constructor**: `new SecurityExtension(?XoopsSecurity $security, ?XoopsGroupPermHandler $permHandler)`

| Name | Type | Description |
|---|---|---|
| `sanitize_string` | modifier | `htmlspecialchars` with `ENT_QUOTES` |
| `sanitize_url` | modifier | Blocks `javascript:`, `data:`, entity-encoded variants |
| `sanitize_filename` | modifier | `basename()` + allowlist + strip leading dots |
| `sanitize_string_for_xml` | modifier | XML-safe entity encoding |
| `mask_email` | modifier | `us***@example.com` format |
| `obfuscate_text` | modifier | Converts all chars to HTML entities |
| `hash_string` | modifier | Hash with any algorithm; defaults to SHA-256 |
| `generate_csrf_token` | function | Renders XOOPS CSRF hidden input |
| `validate_csrf_token` | function | Validates XOOPS CSRF token |
| `has_user_permission` | function | Checks group permission via `XoopsGroupPermHandler` |
| `is_user_logged_in` | function | Boolean check on `$xoopsUser` global |
| `user_has_role` | function | Checks if user belongs to a group ID |
| `xo_permission` | block | Conditionally renders content based on login/permission/group |

```smarty
<{xo_permission logged_in=true require="module_admin"}>
  <a href="admin.php">Admin panel</a>
<{/xo_permission}>
```

---

### FormExtension

Form rendering with automatic CSRF injection and validation.

**Constructor**: `new FormExtension(?XoopsSecurity $security)`

| Name | Description |
|---|---|
| `form_open` | Opens `<form>` with CSRF token auto-injected for POST |
| `form_close` | Closes `</form>` |
| `form_input` | Renders `<input>` with XSS-safe escaping |
| `create_button` | Renders `<button>` with optional Bootstrap icon |
| `render_form_errors` | Renders Bootstrap 5 error alert list |
| `validate_form` | Validates data against rules; returns errors array |
| `validate_email` | Validates a single email address |
| `display_error` | Renders a single Bootstrap 5 danger alert |

`validate_form` rules example:

```smarty
<{validate_form data=$_POST rules=['email' => ['required' => true, 'email' => true], 'bio' => ['max_length' => 500]] assign="errors"}>
<{render_form_errors errors=$errors}>
```

Validation uses `mb_strlen` for `min_length`/`max_length`, ensuring multibyte UTF-8 characters (CJK, Arabic, etc.) count correctly.

---

### DataExtension

Data manipulation, CSV export, file info, and XML sitemaps.

**Modifiers**: `array_filter`, `array_sort`, `pretty_print_json`, `get_file_size`, `get_mime_type`, `is_image`, `strip_html_comments`

**Functions**: `array_to_csv`, `base64_encode_file`, `embed_pdf`, `generate_xml_sitemap`, `generate_meta_tags`, `get_referrer`, `get_session_data`

The `get_referrer` assign path stores the **raw** referrer URL; direct output applies `htmlspecialchars`.

---

### AssetExtension

Deferred CSS/JS asset queuing with deduplication and XSS-safe scheme validation.

| Function | Description |
|---|---|
| `require_css` | Queue a stylesheet (deduplicates by file path) |
| `require_js` | Queue a script (deduplicates by file path) |
| `flush_css` | Output all queued `<link>` tags and clear queue |
| `flush_js` | Output all queued `<script>` tags and clear queue |

Blocked schemes: `javascript:`, `data:`, and their HTML entity-encoded variants. Last-write wins for conflicting attributes (e.g., `defer` vs no defer for the same file).

```smarty
<{require_css file="modules/news/css/style.css"}>
<{require_js file="modules/news/js/app.js" defer=true}>

<{* In the layout head: *}>
<{flush_css}>
<{* Before </body>: *}>
<{flush_js}>
```

---

### XoopsCoreExtension

Wrappers around XOOPS globals and handlers.

| Name | Type | Description |
|---|---|---|
| `xo_get_config` | function | Read from `$xoopsConfig` |
| `xo_get_current_user` | function | Current user as array (uid, uname, name, email, groups, is_admin) |
| `xo_get_module_info` | function | Module info by dirname |
| `xo_get_notifications` | function | Current user's notification list |
| `xo_module_url` | function | Module URL; assign stores raw, output is HTML-escaped |
| `xo_render_block` | function | Render a XOOPS block object |
| `xo_render_menu` | function | Module admin menu as Bootstrap nav |
| `xo_avatar` | function | User avatar (XOOPS upload or Gravatar fallback) |
| `xo_debug` | function | Dump variable (only outputs when `debug_mode` is active) |
| `translate` | modifier | Resolve XOOPS language constant with string fallback |

```smarty
<{xo_get_config name="sitename" assign="siteName"}>
<title><{$siteName}></title>

<{"_MI_NEWS_LATEST_TITLE"|translate}>
```

---

### RayDebugExtension

Zero-cost debug output via [spatie/ray](https://spatie.be/products/ray). All functions and the modifier silently no-op when Ray is not installed — no runtime dependency.

| Name | Type |
|---|---|
| `ray` | function + modifier |
| `ray_context` | function |
| `ray_dump` | function |
| `ray_table` | function |

---

## Architecture

```
src/
├── AbstractExtension.php       Base class — register(), getModifiers(), getFunctions(), getBlockHandlers()
├── ExtensionRegistry.php       Collects extensions, auto-detects Smarty 4/5, registers all
├── Adapter/
│   └── Smarty5Adapter.php      Wraps AbstractExtension for Smarty 5 addExtension() API
└── Extension/
    ├── TextExtension.php
    ├── FormatExtension.php
    ├── NavigationExtension.php
    ├── SecurityExtension.php
    ├── FormExtension.php
    ├── DataExtension.php
    ├── AssetExtension.php
    ├── XoopsCoreExtension.php
    └── RayDebugExtension.php
```

`AbstractExtension::register($smarty)` iterates the three registries (modifiers, functions, block handlers) and calls `$smarty->registerPlugin()` for each — the Smarty 4 API.

`ExtensionRegistry::registerAll($smarty)` auto-detects the Smarty version: if `\Smarty\Extension\Base` exists (Smarty 5), each extension is wrapped in `Smarty5Adapter` and passed to `$smarty->addExtension()`.

---

## Security Conventions

All extensions follow a consistent **assign contract**:

- **Direct output** (no `assign` param): HTML-escaped with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.
- **Assign path** (`assign` param present): raw value is stored in the template variable. The caller is responsible for escaping when interpolating into HTML (e.g. `href="<{$url|escape}>"`).

This prevents double-escaping while maintaining XSS safety at every output point.

---

## Running Tests

```bash
composer install
composer test
```

PHPUnit scans `tests/Unit/` and uses `tests/bootstrap.php`, which loads XOOPS class stubs so the suite runs standalone (no XOOPS installation required).

```bash
composer analyse   # PHPStan level 9
composer lint      # PHPCS
composer fix       # PHPCBF
```

---

## Contributing

1. Fork the repository.
2. Create a feature branch.
3. Write or update tests in `tests/Unit/`.
4. Ensure `composer test` passes with no failures.
5. Submit a pull request.

---

## License

GNU General Public License v2.0 or later. See [LICENSE](LICENSE) for details.

© 2000–2026 [XOOPS Project](https://xoops.org)
