# XOOPS Smarty Extensions Tutorial

This tutorial walks through practical usage of each extension in XOOPS templates. All examples use XOOPS Smarty delimiters (`<{` and `}>`).

For the full plugin reference table, see [README.md](../README.md).

## Table of Contents

- [Getting Started](#getting-started)
  - [Quick Start](#quick-start)
  - [XOOPS Dependency Matrix](#xoops-dependency-matrix)
- [Text Processing](#text-processing)
- [Formatting Numbers, Dates, and Currency](#formatting-numbers-dates-and-currency)
- [Navigation and URLs](#navigation-and-urls)
- [Data Processing](#data-processing)
- [Forms](#forms)
  - [Recipe: Form Validation Pattern](#recipe-form-validation-pattern)
- [Security and Permissions](#security-and-permissions)
- [XOOPS Core Helpers](#xoops-core-helpers)
- [Asset Management](#asset-management)
- [Ray Debugging](#ray-debugging)
- [Writing Your Own Extension](#writing-your-own-extension)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

---

## Getting Started

### Registration

Extensions must be registered with your Smarty instance before they can be used in templates. In a standalone setup, do this explicitly:

```php
use Xoops\SmartyExtensions\ExtensionRegistry;
use Xoops\SmartyExtensions\Extension\TextExtension;
use Xoops\SmartyExtensions\Extension\FormatExtension;
use Xoops\SmartyExtensions\Extension\NavigationExtension;
use Xoops\SmartyExtensions\Extension\DataExtension;
use Xoops\SmartyExtensions\Extension\SecurityExtension;
use Xoops\SmartyExtensions\Extension\FormExtension;
use Xoops\SmartyExtensions\Extension\XoopsCoreExtension;
use Xoops\SmartyExtensions\Extension\RayDebugExtension;
use Xoops\SmartyExtensions\Extension\AssetExtension;

$registry = new ExtensionRegistry();
$registry->add(new TextExtension());
$registry->add(new FormatExtension());
$registry->add(new NavigationExtension());
$registry->add(new DataExtension());
$registry->add(new SecurityExtension($xoopsSecurity, $grouppermHandler));
$registry->add(new FormExtension($xoopsSecurity));
$registry->add(new XoopsCoreExtension());
$registry->add(new RayDebugExtension());
$registry->add(new AssetExtension());

$registry->registerAll($smarty);
```

If XOOPS Core performs this registration in its bootstrap, all plugins will be available in every `.tpl` template without any additional setup in your module code.

### Quick Start

A minimal end-to-end example covering one plugin from each category:

```smarty
<{* Text modifier — pure PHP, works anywhere *}>
<p><{$article.body|excerpt:200}></p>

<{* URL builder — uses XOOPS_URL when defined *}>
<{xo_module_url module="news" path="article.php" params=['id' => $article.id] assign="url"}>
<a href="<{$url}>">Read more</a>

<{* Form with automatic CSRF — requires XoopsSecurity *}>
<{form_open action="save.php" method="post"}>
  <{form_input type="text" name="title" value=$article.title class="form-control"}>
  <{create_button label="Save" type="submit" class="btn btn-primary"}>
<{form_close}>

<{* Permission gate — requires XOOPS user/group system *}>
<{xo_permission require="module_admin" module_id=1}>
  <a href="admin.php">Admin Panel</a>
<{/xo_permission}>
```

### XOOPS Dependency Matrix

Some extensions are pure PHP and work in any Smarty environment. Others require XOOPS runtime objects or globals.

| Extension | XOOPS Required? | Dependencies |
|-----------|:---:|---|
| TextExtension | No | Pure PHP |
| FormatExtension | No | Pure PHP (optional: `intl` extension for currency) |
| NavigationExtension | Partial | `XOOPS_URL` for `generate_canonical_url`; pure PHP otherwise |
| DataExtension | Partial | `XOOPS_ROOT_PATH` or `DOCUMENT_ROOT` for `base64_encode_file` boundary; pure PHP otherwise |
| FormExtension | Optional | `XoopsSecurity` for CSRF injection; works without it (no token) |
| SecurityExtension | Yes | `XoopsSecurity`, `XoopsGroupPermHandler`, `$xoopsUser` global |
| XoopsCoreExtension | Yes | `$xoopsConfig`, `$xoopsUser`, `xoops_getHandler()`, `XOOPS_URL` |
| AssetExtension | No | Pure PHP |
| RayDebugExtension | Yes | Debugbar module with RayLogger enabled, `ray()` function |

### Plugin types

There are three types of Smarty plugins:

- **Modifiers** transform a value inline: `<{$variable|modifier_name}>`
- **Functions** output content or assign values: `<{function_name param="value"}>`
- **Blocks** wrap content conditionally: `<{block_name}>...<{/block_name}>`

### Direct output vs `assign`

Most functions support an `assign` parameter that stores the result in a template variable instead of outputting it directly:

```smarty
<{generate_url route="/search" params=$queryParams assign="searchUrl"}>
<a href="<{$searchUrl}>">Search</a>
```

**Important**: Functions that return boolean or structured data (like `validate_form`, `is_user_logged_in`, `has_user_permission`, `xo_get_current_user`) should always be used with `assign`. Their direct output is either empty or a stringified `'1'`/`''`, which is rarely useful in templates.

```smarty
<{* Wrong — direct output is just "1" or "" *}>
<{is_user_logged_in}>

<{* Right — assign and use as a condition *}>
<{is_user_logged_in assign="loggedIn"}>
<{if $loggedIn}>Welcome back!<{/if}>
```

---

## Text Processing

TextExtension provides modifiers for common text operations. These are pure PHP with no XOOPS dependencies.

### Truncating text

Use `excerpt` to truncate to a character limit (breaks at word boundaries):

```smarty
<{* Truncate to 150 characters with "..." suffix *}>
<p><{$article.body|excerpt:150}></p>

<{* Custom suffix *}>
<p><{$article.body|excerpt:100:" [read more]"}></p>
```

Use `truncate_words` to limit by word count:

```smarty
<{* Show first 25 words *}>
<p><{$article.body|truncate_words:25}></p>

<{* Custom ending *}>
<p><{$article.body|truncate_words:10:" ..."}></p>
```

### Converting newlines to paragraphs

The `nl2p` modifier converts double newlines into `<p>` tags and single newlines into `<br>`.

> **HTML output warning**: `nl2p` returns raw HTML markup. The input is not escaped, so pass only trusted or pre-sanitized content. Do not apply `|escape` after `nl2p` or the tags will be visible as text.

```smarty
<{$userBio|nl2p}>
```

Input: `"First paragraph.\n\nSecond paragraph.\nWith a line break."`
Output: `<p>First paragraph.</p><p>Second paragraph.<br>With a line break.</p>`

### Highlighting search terms

> **HTML output warning**: `highlight_text` wraps matches in `<span>` tags and returns raw HTML. Ensure the source text is already escaped or trusted. Do not apply `|escape` after this modifier.

```smarty
<{* Wrap matches in <span class="highlight"> *}>
<{$article.title|highlight_text:$searchQuery}>

<{* Custom CSS class *}>
<{$article.body|highlight_text:$searchQuery:"search-match"}>
```

### Estimating reading time

```smarty
<span class="meta"><{$article.body|reading_time}></span>
```

Output: `3 min read`

You can adjust the words-per-minute rate:

```smarty
<{$article.body|reading_time:250}>
```

### Pluralizing labels

```smarty
<{$commentCount}> <{$commentCount|pluralize:"comment"}>
```

Output for 1: `1 comment` | Output for 5: `5 comments`

For irregular plurals:

```smarty
<{$childCount|pluralize:"child":"children"}>
```

### Extracting hashtags

```smarty
<{assign var="tags" value=$post.body|extract_hashtags}>
<{foreach $tags as $tag}>
  <a href="/tag/<{$tag}>"><{$tag}></a>
<{/foreach}>
```

---

## Formatting Numbers, Dates, and Currency

FormatExtension handles display formatting for dates, numbers, and currency.

### Date formatting

The `format_date` modifier accepts a date string or Unix timestamp:

```smarty
<{* Default format: Y-m-d H:i:s *}>
<{$article.created|format_date}>

<{* Custom format *}>
<{$article.created|format_date:"F j, Y"}>

<{* From timestamp *}>
<{$article.created|format_date:"M d, Y g:i A"}>
```

### Relative time

```smarty
<span class="timeago"><{$article.created|relative_time}></span>
```

Outputs contextual strings like `3 hours ago`, `2 days ago`, `Just now`, or `5 minutes from now` for future dates.

### Currency formatting

Uses the ICU NumberFormatter when the `intl` extension is available:

```smarty
<{* Default: USD, en_US *}>
<{$product.price|format_currency}>

<{* Euro in German locale *}>
<{$product.price|format_currency:"EUR":"de_DE"}>

<{* Fallback symbol when intl is not loaded *}>
<{$product.price|format_currency:"GBP":"en_GB":"&pound;"}>
```

### Number formatting

```smarty
<{* Default: 2 decimals, period, comma *}>
<{$stats.views|number_format}>

<{* No decimals *}>
<{$stats.views|number_format:0}>

<{* European style *}>
<{$stats.views|number_format:2:",":"."}>
```

### Byte sizes

```smarty
<{$file.size|bytes_format}>
```

Output: `1.45 MB`

### Phone numbers

```smarty
<{$user.phone|format_phone_number}>
```

Input `"5551234567"` becomes `(555) 123-4567`. Input `"15551234567"` becomes `+1 (555) 123-4567`.

### Gravatar URLs

```smarty
<img src="<{$user.email|gravatar}>" alt="Avatar">

<{* Custom size and default *}>
<img src="<{$user.email|gravatar:128:"identicon"}>" alt="Avatar">
```

### Date difference

```smarty
<{datetime_diff start="2024-01-01" end="2024-12-31" format="%m months, %d days"}>
```

### Current year (for copyright footers)

```smarty
<p>&copy; <{get_current_year}> XOOPS Project</p>
```

---

## Navigation and URLs

NavigationExtension provides URL manipulation, breadcrumbs, pagination, and social sharing.

### Building URLs

```smarty
<{generate_url route="/modules/news/article.php" params=$queryParams assign="articleUrl"}>
<a href="<{$articleUrl}>">Read article</a>
```

### Canonical URLs

Builds a full URL by prepending `XOOPS_URL`. Returns an empty string if `XOOPS_URL` is not defined (it will not fall back to `HTTP_HOST` to prevent host-header poisoning). Always check the result before using it:

```smarty
<{generate_canonical_url path="modules/news/article.php?id=42" assign="canonical"}>
<{if $canonical}>
  <link rel="canonical" href="<{$canonical}>">
<{/if}>
```

### URL segments

Extract parts of the current request URI:

```smarty
<{* /modules/news/article.php => index 0 = "modules", 1 = "news" *}>
<{url_segment index=1 assign="currentModule"}>
```

### Slugifying text

```smarty
<{$article.title|slugify}>
```

Input: `"Hello World! This is a Test"` becomes `hello-world-this-is-a-test`

### Extracting YouTube video IDs

```smarty
<{$videoUrl|youtube_id}>
```

Works with `youtube.com/watch?v=`, `youtu.be/`, `youtube.com/embed/`, and `youtube.com/shorts/` URLs.

### Making URLs clickable

> **HTML output warning**: `linkify` returns raw HTML with `<a>` tags. The surrounding text is not escaped. Pass only trusted or pre-sanitized content to avoid XSS. Do not apply `|escape` after this modifier.

```smarty
<{$comment.body|linkify}>
```

Converts plain URLs into `<a>` tags with `target="_blank"` and `rel="noopener noreferrer nofollow"`.

### Removing protocol from URLs

```smarty
<{$website|strip_protocol}>
```

Input: `"https://example.com/page"` becomes `example.com/page`

### Parsing URLs

Returns `false` for seriously malformed URLs. Always check before accessing components:

```smarty
<{assign var="parts" value=$url|parse_url}>
<{if $parts}>
  Host: <{$parts.host}>, Path: <{$parts.path}>
<{/if}>
```

### Social sharing

Generate a share bar with links to all platforms:

```smarty
<{social_share url=$articleUrl title=$article.title}>
```

Or get a link for a specific platform:

```smarty
<{social_share url=$articleUrl title=$article.title platform="twitter" assign="tweetUrl"}>
<a href="<{$tweetUrl}>">Share on Twitter</a>
```

Supported platforms: `twitter`, `facebook`, `linkedin`, `reddit`, `email`.

### Breadcrumbs

> **HTML output note**: `render_breadcrumbs`, `render_pagination`, and `render_alert` all return Bootstrap 5 HTML markup. Do not apply `|escape` to their output. Their parameters are escaped internally.

Renders Bootstrap 5 breadcrumb navigation:

```smarty
<{assign var="crumbs" value=[
    "/": "Home",
    "/modules/news/": "News",
    "#": "Current Article"
]}>
<{render_breadcrumbs items=$crumbs}>
```

The last item is rendered as the active (non-linked) breadcrumb.

### Pagination

Renders Bootstrap 5 pagination controls:

```smarty
<{render_pagination totalPages=$totalPages currentPage=$currentPage urlPattern="/news/?page={page}"}>
```

The `{page}` placeholder in `urlPattern` is replaced with each page number.

### QR codes

```smarty
<{render_qr_code text="https://xoops.org" size=200}>
```

### Alert messages

```smarty
<{render_alert message="Settings saved successfully." type="success" dismissible=true}>
```

Types: `success`, `danger`, `warning`, `info`, `primary`, `secondary`.

---

## Data Processing

DataExtension provides data manipulation, file utilities, and CSV/XML generation.

### Filtering arrays

```smarty
<{assign var="activeUsers" value=$users|array_filter:"status":"active"}>
<{foreach $activeUsers as $user}>
  <{$user.name}>
<{/foreach}>
```

### Sorting arrays

```smarty
<{* Sort by name ascending *}>
<{assign var="sorted" value=$users|array_sort:"name"}>

<{* Sort by date descending *}>
<{assign var="sorted" value=$articles|array_sort:"created":"desc"}>

<{* Simple value sort *}>
<{assign var="sorted" value=$tags|array_sort}>
```

### JSON pretty-printing

```smarty
<pre><{$config|pretty_print_json}></pre>
```

Also works with JSON strings (decodes, then re-encodes with formatting).

### File information

```smarty
<{$filePath|get_file_size}>    <{* Output: "1.45 MB" *}>
<{$filePath|get_mime_type}>    <{* Output: "application/pdf" *}>
<{$filePath|is_image}>         <{* Output: true/false *}>
```

### Stripping HTML comments

```smarty
<{$htmlContent|strip_html_comments}>
```

### Base64-encoding files

Encode a file as a base64 string, for example to inline images in emails or data URIs. For security, this function only reads files under `XOOPS_ROOT_PATH` (or `DOCUMENT_ROOT` outside XOOPS). If neither is set, the function returns an empty string.

```smarty
<{base64_encode_file path=$imagePath assign="b64"}>
<{if $b64}>
  <img src="data:image/png;base64,<{$b64}>" alt="Inline image">
<{/if}>
```

Paths that resolve outside the web root are silently rejected.

### CSV generation

```smarty
<{array_to_csv array=$rows separator="," assign="csvData"}>
```

### Embedding PDFs

Returns an empty string if `url` is empty.

```smarty
<{embed_pdf url=$pdfUrl width="100%" height="600" assign="viewer"}>
<{if $viewer}>
  <{$viewer}>
<{/if}>
```

### XML sitemap generation

```smarty
<{assign var="pages" value=[
    ["url" => "https://example.com/", "lastmod" => "2024-01-01", "priority" => "1.0"],
    ["url" => "https://example.com/about", "changefreq" => "monthly", "priority" => "0.8"]
]}>
<{generate_xml_sitemap pages=$pages assign="sitemap"}>
```

### Meta tag generation

```smarty
<{assign var="meta" value=[
    "description" => "Page description here",
    "keywords" => "xoops, cms, php",
    "author" => "XOOPS Project"
]}>
<{generate_meta_tags config=$meta}>
```

### Session data

Returns `null` (via `assign`) or an empty string (direct output) if the key does not exist.

```smarty
<{get_session_data key="user_preference" assign="pref"}>
<{if $pref}>
  <p>Your preference: <{$pref|escape}></p>
<{/if}>
```

### HTTP referrer

```smarty
<{get_referrer assign="referrer"}>
<{if $referrer}>
  <p>You came from: <{$referrer}></p>
<{/if}>
```

---

## Forms

FormExtension provides form rendering with automatic CSRF token injection.

### Basic form

```smarty
<{form_open action="save.php" method="post" class="needs-validation"}>
  <div class="mb-3">
    <label for="title" class="form-label">Title</label>
    <{form_input type="text" name="title" value=$article.title class="form-control" id="title" required="required"}>
  </div>
  <div class="mb-3">
    <label for="email" class="form-label">Email</label>
    <{form_input type="email" name="email" value=$user.email class="form-control" id="email"}>
  </div>
  <{create_button label="Save" type="submit" class="btn btn-primary"}>
<{form_close}>
```

The `form_open` function automatically injects a hidden CSRF token field for POST forms when `XoopsSecurity` is available.

### File upload form

```smarty
<{form_open action="upload.php" method="post" enctype="multipart/form-data"}>
  <{form_input type="file" name="attachment" class="form-control"}>
  <{create_button label="Upload" type="submit" class="btn btn-primary" icon="bi-upload"}>
<{form_close}>
```

### Buttons with icons

```smarty
<{create_button label="Save" type="submit" class="btn btn-primary" icon="bi-check-lg"}>
<{create_button label="Delete" type="button" class="btn btn-danger" icon="bi-trash"}>
<{create_button label="Cancel" type="button" class="btn btn-secondary"}>
```

### Form validation

Validate form data against rules and display errors. `validate_form` always returns an empty string — it is designed for `assign` usage only. The assigned value is an associative array of field names to error message arrays.

```smarty
<{validate_form data=$formData rules=$validationRules assign="errors"}>

<{if $errors}>
  <{render_form_errors errors=$errors}>
<{/if}>
```

Rules are defined as an associative array in PHP:

```php
$validationRules = [
    'title' => ['required' => true, 'min_length' => 3, 'max_length' => 255],
    'email' => ['required' => true, 'email' => true],
    'age'   => ['numeric' => true],
];
```

### Email validation

```smarty
<{validate_email email=$userEmail assign="isValid"}>
<{if !$isValid}>
  <{display_error message="Please enter a valid email address."}>
<{/if}>
```

### Error display

```smarty
<{display_error message="Something went wrong. Please try again."}>
```

---

### Recipe: Form Validation Pattern

This is the canonical end-to-end pattern for a validated POST form in an XOOPS module. Copy this as your starting point and adapt the rules to your field requirements.

#### Why this pattern exists

Four things are easy to get wrong when combining form rendering, CSRF protection, validation, and error display. This recipe shows all four in one place:

1. `form_open` with `method="post"` is the entry point — it auto-injects the XOOPS CSRF hidden field when `XoopsSecurity` is passed to `FormExtension`. No manual token code required.
2. `validate_form` → `render_form_errors` is a two-step, not one. `validate_form` **never produces output**; it only stores an errors array in the variable named by `assign`. Always pair it with `render_form_errors` or inspect `$errors` yourself.
3. `assign` is mandatory for `validate_form`, not optional. Without it, the errors array is discarded silently and you have no way to display or act on validation failures.
4. `min_length` and `max_length` use `mb_strlen` internally, not `strlen`. A Japanese module title like `ニュース記事` is 6 characters, not 18 bytes — the rules count characters correctly for all UTF-8 scripts.

#### PHP controller (module's save.php)

```php
// Register extensions — FormExtension receives XoopsSecurity for CSRF
$registry = new ExtensionRegistry();
$registry->add(new FormExtension($xoopsSecurity));
$registry->registerAll($xoops->tpl);

// Pass raw POST data and rules to the template
$xoops->tpl->assign('formData', $_POST);
$xoops->tpl->assign('validationRules', [
    'title' => ['required' => true, 'min_length' => 3, 'max_length' => 255],
    'body'  => ['required' => true, 'min_length' => 10],
    'email' => ['required' => true, 'email' => true],
    'year'  => ['numeric' => true],
]);
```

> **Why raw `$_POST` is correct here.** `validate_form` never outputs anything — it only reads values to apply rules. `form_input` applies `htmlspecialchars()` to the value attribute at render time. Pre-escaping `$_POST` before passing it in is the common mistake to avoid: it causes double-escaping (`&amp;lt;` in inputs) and inflates character counts so that `min_length` rules reject valid multibyte input. Pass raw POST data in; let the extension handle escaping at output.

#### Template (module/templates/module_form.tpl)

```smarty
<{* Step 1 — run validation. assign is mandatory; validate_form produces no output. *}>
<{validate_form data=$formData rules=$validationRules assign="errors"}>

<{* Step 2 — display errors if any exist. *}>
<{if $errors}>
  <{render_form_errors errors=$errors}>
<{/if}>

<{* Step 3 — open form. CSRF token is injected automatically for POST. *}>
<{form_open action="save.php" method="post" class="needs-validation"}>

  <div class="mb-3">
    <label for="title" class="form-label">Title</label>
    <{form_input type="text" name="title" value=$formData.title
        class="form-control" id="title" required="required"}>
  </div>

  <div class="mb-3">
    <label for="body" class="form-label">Body</label>
    <textarea name="body" class="form-control" rows="6"><{$formData.body|escape}></textarea>
  </div>

  <div class="mb-3">
    <label for="email" class="form-label">Contact email</label>
    <{form_input type="email" name="email" value=$formData.email
        class="form-control" id="email"}>
  </div>

  <{create_button label="Save" type="submit" class="btn btn-primary" icon="bi-check-lg"}>
  <{create_button label="Cancel" type="button" class="btn btn-secondary"}>

<{form_close}>
```

#### GET forms must not use CSRF

A GET form embeds its fields into the URL. A CSRF token in the URL is a security liability (it leaks in Referer headers and browser history). `form_open` is correct by design here: it only injects the token when `method="post"`.

```smarty
<{* No CSRF token injected — correct for GET *}>
<{form_open action="search.php" method="get"}>
  <{form_input type="search" name="q" value=$query class="form-control"}>
  <{create_button label="Search" type="submit" class="btn btn-outline-secondary"}>
<{form_close}>
```

#### Multibyte field lengths

The `min_length` and `max_length` rules count **characters**, not bytes. You do not need to do anything special — the extension handles this internally. The table below shows why byte-counting would silently break non-Latin input:

| Script | Example | Characters | UTF-8 bytes | `strlen` (wrong) | `mb_strlen` (correct) |
|--------|---------|-----------|-------------|-----------------|----------------------|
| ASCII | `Hello` | 5 | 5 | 5 | 5 |
| Japanese | `ニュース` | 4 | 12 | 12 | 4 |
| Arabic | `مرحبا` | 5 | 10 | 10 | 5 |
| Emoji | `🌍🌎🌏` | 3 | 12 | 12 | 3 |

If your module has a `max_length => 100` rule for a title field and a user enters 100 Japanese characters, `strlen` would report 300 and reject valid input. `mb_strlen` correctly reports 100 and accepts it.

---

## Security and Permissions

SecurityExtension provides CSRF protection, permission checks, and string sanitization.

### CSRF tokens

Generate and validate CSRF tokens using the XOOPS security system:

```smarty
<{* Generate a token (usually handled by form_open) *}>
<{generate_csrf_token}>

<{* Validate on form submission *}>
<{validate_csrf_token assign="isValid"}>
```

### Permission-based content

Use the `xo_permission` block to conditionally show content:

```smarty
<{* Only for logged-in users *}>
<{xo_permission logged_in=true}>
  <p>Welcome back!</p>
<{/xo_permission}>

<{* Only for users with a specific permission *}>
<{xo_permission require="module_admin" module_id=1}>
  <a href="admin.php">Admin Panel</a>
<{/xo_permission}>

<{* Only for a specific group *}>
<{xo_permission group=1}>
  <p>Administrators only.</p>
<{/xo_permission}>
```

### Permission check functions

```smarty
<{is_user_logged_in assign="loggedIn"}>

<{has_user_permission permission="module_admin" module_id=1 item_id=0 assign="isAdmin"}>

<{user_has_role role="1" assign="isInGroup"}>
```

### String sanitization

```smarty
<{* HTML entity encoding (XSS protection) *}>
<{$userInput|sanitize_string}>

<{* URL sanitization *}>
<a href="<{$url|sanitize_url}>">Link</a>

<{* Filename sanitization (strips everything except A-Za-z0-9-_.) *}>
<{$uploadName|sanitize_filename}>

<{* XML-safe encoding *}>
<{$value|sanitize_string_for_xml}>
```

`sanitize_url` allows these schemes: `http://`, `https://`, `ftp://`, `mailto:`, relative paths (`/path`, `page.html`), and hash fragments (`#section`). All other schemes (including `javascript:`, `data:`, and entity-encoded variants) are blocked and return an empty string.

### Email privacy

```smarty
<{* Partially hide: "john.doe@example.com" => "jo***@example.com" *}>
<{$user.email|mask_email}>

<{* Convert to HTML entities to prevent harvesting *}>
<a href="mailto:<{$user.email|obfuscate_text}>"><{$user.email|obfuscate_text}></a>
```

### Hashing

For checksums, cache keys, and fingerprints. **Not for password storage** — use `password_hash()` in PHP for that.

```smarty
<{* Default: SHA-256 (recommended for most uses) *}>
<{$value|hash_string}>

<{* SHA-512 for stronger fingerprints *}>
<{$value|hash_string:"sha512"}>
```

Any algorithm supported by PHP's `hash_algos()` can be used. Returns an empty string for unrecognized algorithms.

---

## XOOPS Core Helpers

XoopsCoreExtension provides template functions that interact with XOOPS configuration, users, and modules.

### Site configuration

```smarty
<{xo_get_config name="sitename" assign="siteName"}>
<title><{$siteName}></title>

<{xo_get_config name="slogan" assign="slogan"}>
<p><{$slogan}></p>
```

### Current user

```smarty
<{xo_get_current_user assign="user"}>
<{if $user}>
  <p>Hello, <{$user.uname}>!</p>
  <{if $user.is_admin}>
    <a href="admin.php">Admin</a>
  <{/if}>
<{else}>
  <a href="user.php">Login</a>
<{/if}>
```

The returned array contains: `uid`, `uname`, `name`, `email`, `groups`, `is_admin`.

### Module information

```smarty
<{xo_get_module_info dirname="news" assign="mod"}>
<{if $mod && $mod.isactive}>
  <p><{$mod.name}> v<{$mod.version}></p>
<{/if}>
```

### Module URLs

Builds a module-relative URL. When `XOOPS_URL` is defined, the output is a full URL; otherwise it starts with `/modules/`.

```smarty
<{xo_module_url module="news" path="article.php" params=$queryParams assign="articleUrl"}>
<a href="<{$articleUrl}>">Read article</a>
```

Example output with `XOOPS_URL` = `https://example.com`: `https://example.com/modules/news/article.php?id=42`

### User avatars

Returns an empty string if no avatar can be resolved (no XOOPS avatar and no email for Gravatar). Check when using with `assign`:

```smarty
<{* By user ID (looks up XOOPS avatar, falls back to Gravatar) *}>
<{xo_avatar uid=$userId size=64 class="rounded-circle" assign="avatar"}>
<{if $avatar}><{$avatar}><{/if}>

<{* By email (Gravatar only) — direct output *}>
<{xo_avatar email=$userEmail size=48}>
```

### Notifications

```smarty
<{xo_get_notifications assign="notifications"}>
<{if $notifications}>
  <span class="badge"><{$notifications|@count}></span>
<{/if}>
```

### Language constants

The `translate` modifier looks up a XOOPS language constant:

```smarty
<{* If _MI_NEWS_TITLE is defined, outputs its value; otherwise outputs the literal string *}>
<h1><{"_MI_NEWS_TITLE"|translate}></h1>
```

### Debug output

Only renders when XOOPS debug mode is active:

```smarty
<{xo_debug var=$someVariable label="My Variable"}>
```

Renders as an expandable `<details>` element with a `<pre>` dump.

### Rendering blocks

Renders a XOOPS block from its options array. This is primarily used by theme templates and the block system; most module developers will not call it directly.

```smarty
<{xo_render_block options=$blockOptions assign="blockHtml"}>
<{if $blockHtml}>
  <div class="block-content"><{$blockHtml}></div>
<{/if}>
```

The `options` array must contain a `block` key with a block object that implements a `getContent()` method (the standard XOOPS block interface).

### Module admin menu

```smarty
<{xo_render_menu module="news"}>
```

---

## Asset Management

AssetExtension prevents duplicate `<link>` and `<script>` tags when multiple templates or blocks request the same stylesheet or script within a single page render. Pure PHP, no XOOPS dependencies.

### Queueing assets

Register CSS and JS files from anywhere in your templates — blocks, module templates, theme includes. Duplicates are deduplicated by file path. If the same file is registered again with different attributes (e.g., different `media` or `defer`), the later registration wins.

```smarty
<{* In a block template *}>
<{require_css file="modules/news/assets/news.css"}>
<{require_js file="modules/news/assets/news.js" defer=true}>

<{* In another block — same file, no duplicate emitted *}>
<{require_css file="modules/news/assets/news.css"}>

<{* External CDN assets *}>
<{require_js file="https://cdn.example.com/lib.js" async=true}>

<{* Print stylesheet *}>
<{require_css file="modules/news/assets/print.css" media="print"}>
```

### Flushing assets in the theme

Place these in your theme footer to output all queued tags at once:

```smarty
<{* In theme header — output all CSS *}>
<{flush_css}>

<{* In theme footer — output all JS *}>
<{flush_js}>
```

After flushing, the queue is cleared. A second `flush_css` or `flush_js` call outputs nothing.

### Custom rendering with assign

Use `assign` to get the full metadata for custom rendering. The assigned value is a list of structured arrays, not just file paths:

```smarty
<{flush_css assign="styles"}>
<{foreach $styles as $entry}>
  <link rel="stylesheet" href="<{$entry.file|escape}>" media="<{$entry.media|escape}>">
<{/foreach}>

<{flush_js assign="scripts"}>
<{foreach $scripts as $entry}>
  <script src="<{$entry.file|escape}>"<{if $entry.defer}> defer<{/if}><{if $entry.async}> async<{/if}>></script>
<{/foreach}>
```

### URL safety

Asset URLs are validated against a safe-scheme allowlist. Only `http://`, `https://`, protocol-relative (`//cdn...`), and relative paths are accepted. Unsafe schemes like `javascript:` and `data:` are silently rejected, including entity-encoded variants. URLs with colons in query strings (e.g., `asset.php?src=https://cdn.example.com/lib.js`) are correctly allowed.

---

## Ray Debugging

RayDebugExtension sends template data to the [Ray](https://myray.app) desktop debugger. All functions silently no-op when Ray is not installed or the Debugbar RayLogger is disabled, so templates can safely contain Ray tags in production.

### Sending values to Ray

```smarty
<{* Send a variable *}>
<{ray value=$config}>

<{* Send a message with color *}>
<{ray msg="Reached the sidebar template" color="green"}>

<{* Send with a label *}>
<{ray value=$user label="Current User" color="blue"}>
```

### Pass-through modifier

The `ray` modifier sends a value to Ray without changing the output:

```smarty
<{* Inspect a value inline without breaking the template *}>
<p>Name: <{$user.name|ray:"Username"}></p>
```

### Dumping template context

```smarty
<{* Dump all template variables as a sorted table *}>
<{ray_context}>

<{* With a label and exclusion patterns *}>
<{ray_context label="Before Loop" exclude="xoops_*,smarty"}>
```

### Variable dumps

```smarty
<{ray_dump value=$complexObject label="Config Dump"}>
```

### Table display

```smarty
<{ray_table value=$users label="User List"}>
```

---

## Writing Your Own Extension

### Step 1: Create the extension class

```php
<?php

declare(strict_types=1);

namespace MyModule\Smarty;

use Xoops\SmartyExtensions\AbstractExtension;

final class MyModuleExtension extends AbstractExtension
{
    public function getModifiers(): array
    {
        return [
            'format_status' => $this->formatStatus(...),
        ];
    }

    public function getFunctions(): array
    {
        return [
            'my_widget' => $this->myWidget(...),
        ];
    }

    public function getBlockHandlers(): array
    {
        return [
            'my_block' => $this->myBlock(...),
        ];
    }

    /**
     * Modifier: <{$status|format_status}>
     */
    public function formatStatus(string $status): string
    {
        return match ($status) {
            'active' => '<span class="badge bg-success">Active</span>',
            'inactive' => '<span class="badge bg-secondary">Inactive</span>',
            'pending' => '<span class="badge bg-warning">Pending</span>',
            default => \htmlspecialchars($status, ENT_QUOTES, 'UTF-8'),
        };
    }

    /**
     * Function: <{my_widget title="Hello" count=5}>
     *
     * @param array<string, mixed> $params  Template parameters
     * @param object               $template Smarty template instance
     */
    public function myWidget(array $params, object $template): string
    {
        $title = \htmlspecialchars($params['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $count = (int) ($params['count'] ?? 0);

        $html = '<div class="my-widget">';
        $html .= '<h3>' . $title . '</h3>';
        $html .= '<p>Count: ' . $count . '</p>';
        $html .= '</div>';

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $html);
            return '';
        }

        return $html;
    }

    /**
     * Block: <{my_block role="admin"}>...content...<{/my_block}>
     */
    public function myBlock(array $params, ?string $content, object $template, bool &$repeat): string
    {
        if ($repeat || $content === null) {
            return '';
        }

        $role = $params['role'] ?? '';
        // Add your condition logic here

        return '<div class="my-block">' . $content . '</div>';
    }
}
```

### Step 2: Register the extension

```php
use Xoops\SmartyExtensions\ExtensionRegistry;
use MyModule\Smarty\MyModuleExtension;

// Get the existing registry (or create one)
$registry = new ExtensionRegistry();
$registry->add(new MyModuleExtension());
$registry->registerAll($smarty);
```

### Step 3: Use in templates

```smarty
<{* Modifier *}>
<{$user.status|format_status}>

<{* Function *}>
<{my_widget title="Dashboard" count=$itemCount}>

<{* Function with assign *}>
<{my_widget title="Sidebar" count=3 assign="widgetHtml"}>
<div class="sidebar"><{$widgetHtml}></div>

<{* Block *}>
<{my_block role="admin"}>
  <p>Admin-only content here.</p>
<{/my_block}>
```

### Key conventions

- **Modifiers** receive the value as the first parameter, followed by any additional arguments
- **Functions** receive `array $params` and `object $template`; return a string
- **Block handlers** receive `array $params`, `?string $content`, `object $template`, and `bool &$repeat`; only process when `$content !== null` (closing tag)
- Always escape output with `\htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`
- Support the `assign` parameter in functions for flexibility
- The `ExtensionRegistry` handles Smarty 4/5 differences automatically

### Returning HTML from modifiers

The `formatStatus()` example above intentionally returns `<span>` markup. This is a valid pattern when the modifier's purpose is to produce styled HTML. However, be deliberate about this choice:

- If your modifier returns **HTML**, document it clearly and do not apply `|escape` after it in templates
- If your modifier returns **plain text**, escape it inside the modifier so it is safe by default
- Do not mix the two — a modifier should consistently return either raw HTML or plain text, never sometimes one and sometimes the other

---

## Best Practices

- **Prefer `assign` for non-display data.** Boolean checks, structured data, and URLs are easier to work with as template variables than as direct output.
- **Escape user input before building custom HTML.** The built-in functions escape parameters internally, but if you build HTML in PHP and pass it to a template, escape it there.
- **Use `xo_permission` for display gating, not authorization.** Hiding a link does not prevent access to the URL. Always enforce permissions in PHP on the server side.
- **Keep heavy business logic in PHP, not templates.** Extensions are for presentation. Complex queries, calculations, or state changes belong in module code.
- **Treat file/path helpers as controlled-environment utilities.** `base64_encode_file`, `get_file_size`, and `get_mime_type` operate on local paths. Never pass user-supplied paths to them without validation.
- **Do not double-escape.** If a function or modifier returns HTML (like `nl2p`, `highlight_text`, `linkify`, `render_breadcrumbs`, `render_alert`), do not apply `|escape` to its output — the tags will be visible as text.

---

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| `generate_canonical_url` returns empty | `XOOPS_URL` is not defined | This function requires `XOOPS_URL`. It will not fall back to `HTTP_HOST`. |
| `form_open` does not inject a CSRF token | `XoopsSecurity` was not passed to `FormExtension` | Pass the security object: `new FormExtension($xoopsSecurity)` |
| `base64_encode_file` returns empty | File is outside the allowed root, or no root is available | The function only reads files under `XOOPS_ROOT_PATH` or `DOCUMENT_ROOT`. If neither is set, it fails closed. |
| `xo_get_config`, `xo_get_current_user`, etc. return empty | XOOPS globals are unavailable | These functions depend on `$xoopsConfig`, `$xoopsUser`, and `xoops_getHandler()`. They return empty outside a XOOPS request context. |
| `has_user_permission` always returns false | `XoopsGroupPermHandler` was not injected | Pass the handler: `new SecurityExtension($security, $grouppermHandler)` |
| Ray functions produce no output | Expected — they send data to the Ray desktop app, not the browser | Check that the Debugbar module is active, RayLogger is enabled, and the `ray()` helper function is installed. |
| Modifier output shows raw HTML tags | `\|escape` was applied after an HTML-producing modifier | Remove `\|escape` from `nl2p`, `highlight_text`, `linkify`, and similar modifiers that return markup. |
| `require_css` / `require_js` silently ignores a file | The URL contains an unsafe scheme (`data:`, `javascript:`) | Only `http://`, `https://`, protocol-relative (`//`), and relative paths are accepted. |
| `flush_css` / `flush_js` outputs nothing | No assets were queued, or the queue was already flushed | Each flush clears the queue. Call `require_css`/`require_js` before flushing. |
