# Git hooks for XoopsCore27

Versioned hooks that mechanize the project's most frequently-violated
conventions. Prose rules depend on a human (or LLM) reading and
remembering them at the right moment; these hooks make the same checks
unbypassable without explicit `--no-verify`.

## Activate (one-time, per clone)

```bash
git config core.hooksPath .githooks
```

This change is local to your clone — it does not propagate to other
contributors via push. Re-run after any fresh clone.

## What's enforced

### `pre-commit`

Sniffs staged PHP and `.tpl` changes for known-bad shapes:

| # | Pattern | Why |
|---|---------|-----|
| 1 | `!$x instanceof Y` (no parens) | PHP precedence: `!` binds tighter than `instanceof`, so the unparenthesized form is always `false` |
| 2 | `header('HTTP/1.x ...')` | Use `http_response_code(NNN)` for SAPI portability |
| 3 | `unserialize($x)` without `'allowed_classes' => false` | Object-injection risk; the rule requires `=> false` literally so `=> true` (allows ANY class) and ambiguous variable-options forms don't slip through |
| 4 | `extract(` | Banned — access keys explicitly |
| 5 | `eval(` | Banned, no exceptions |
| 6 | `error_log(` | Use the PSR-3 logger in new code |
| 7 | `->queryF(` | Deprecated in 2.7 — bypassed Protector |
| 8 | `->quoteString(` | Deprecated in 2.7 — use `quote()` |
| 9 | `// removed` | BC-shim antipattern — delete the code instead |
| 10 | Loose `in_array()` on `theme_set_allowed` | Theme directory names such as `"0"` must not coerce in a loose comparison; pass `true` as the third argument (issue #45) |
| 11 | Raw assignment to `->allowedThemes` / `->defaultTheme` from `$xoopsConfig` | Go through `xoops_resolveThemeConfig()` so the validated theme set is kept (issue #45) |
| 12 | Variable variables (`$$x`, `${$x}`) | Opaque and injection-prone — access keys and properties explicitly |
| 13 | Direct `$_GET` / `$_POST` / `$_REQUEST` / `$_COOKIE` access | Use `Xmf\Request` with an explicit hash (`'GET'` / `'POST'`) |
| 14 | Legacy Criteria IN format (a preformatted `"(1,2,3)"` string) | Pass an array so Criteria casts and quotes each element |
| 15 | Error-suppressed call `@foo()` | Handle the failure explicitly instead of hiding it |
| 16 | New PHP file under `tests/` without the standard XOOPS file header | New tests carry the same header as the rest of the project; copy it from `tests/unit/htdocs/modules/system/SystemMenuInstallationTest.php` |
| 17 | New PHP file (renames and copies count as new) without **both** `@copyright` and `@license` in PHP comments, found with PHP's tokenizer; vendor trees, `language/` and `fixtures/` inside a `tests/` tree are excluded, as are symlinks and submodules. Skipped with a notice when `php` is not on `PATH`; a failed git listing or unreadable staged file fails the commit | Caught post-hoc by review on new files across the 2FA PRs (#205-#207); test files are included |
| tpl | Standard Smarty `{$var}` in `.tpl` | XOOPS uses `<{$var}>` delimiters |

The numbers match the numbered comments in `.githooks/pre-commit`; the
`.tpl` check runs as its own pass and is not numbered there.

The diff is scanned with `--diff-filter=ACMR` so edits to renamed
files (`git mv`) and copies that Git's copy-detection finds are also
covered. Vendored trees (`htdocs/xoops_lib/vendor/**`,
`htdocs/class/libraries/vendor/**`) and the `tests/**` tree are
excluded from the scan so dependency updates and test fixtures
containing pattern literals do not false-fire.

Rules 16 and 17 also look at `tests/`. Rule 16 reads the staged
content of newly added `tests/**/*.php` files (every `fixtures/`
directory under `tests/` excluded, at any depth) and requires the
standard header at the top: `<?php` on line 1, a `/*` or `/**` comment
opening on line 2, and the header's first sentence ("You may not change
or alter any portion of this comment ...") inside that comment, within
the first 12 lines; on each line the sentence must come before any
`*/`. Only added files are checked, so existing tests without the
header do not fire when they are edited.

### `commit-msg`

Refuses commit messages containing:

- An attribution trailer at the start of a line (`Generated with ...`)
  — vendor-agnostic, so any tool name is rejected
- `Co-Authored-By:` trailers naming an AI assistant (matches `claude`,
  `anthropic`, `gpt`, `copilot`, `codex`, `gemini`, `bard`)

The hook deliberately does **not** reject bare in-prose mentions of
vendor or product names — that would false-fire on legitimate file or
context references (e.g. a docs commit titled `docs: update CLAUDE.md`).

## Bypass

Both hooks accept `--no-verify`:

```bash
git commit --no-verify -m "..."
```

Only use this when the match is a genuine false positive. The most
common real-world case is a multi-line `unserialize()` call where
`allowed_classes` appears on a different added line than the call
itself — the line-by-line sniff can't see across lines. Document the
reason in the commit body.

## Adding a new sniff

1. Edit `.githooks/pre-commit`.
2. Each sniff is a `grep -E` against the staged-added lines, followed
   by a `report` call with a short label and sample output.
3. The diff is filtered to *added* lines only, so legacy code does not
   false-fire — only new occurrences count.
4. Run a test commit to confirm the new sniff fires on the bad pattern
   and stays silent on the good pattern.

## Why a hook, not more conventions-doc prose?

Convention docs are read once at conversation start and held in
context. When code generation pattern-matches on a *task concept*
(e.g. "DB safety guard") the relevant rule may be filed under a
different category (e.g. "PHP precedence gotcha") and not fire.
Mechanical sniffs run unconditionally on every commit, so the failure
mode of "I knew the rule but didn't apply it" becomes structurally
impossible.

When a rule is violated post-hoc and caught by review, the canonical
response is: **add a sniff here**, not "I will be more careful next
time."
