# Git hooks for XoopsCore27

Versioned hooks that mechanize the most-violated rules from `CLAUDE.md`.
Prose rules depend on a human (or LLM) reading and remembering them at the
right moment; these hooks make the same checks unbypassable without
explicit `--no-verify`.

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
| 3 | `unserialize($x)` (no `allowed_classes`) | Object-injection risk |
| 4 | `extract(` | Banned — access keys explicitly |
| 5 | `eval(` | Banned, no exceptions |
| 6 | `error_log(` | Use the PSR-3 logger in new code |
| 7 | `->queryF(` | Deprecated in 2.7 — bypassed Protector |
| 8 | `->quoteString(` | Deprecated in 2.7 — use `quote()` |
| 9 | `// removed` | BC-shim antipattern — delete the code instead |
| 10 | `JSON_UNESCAPED_SLASHES` | `</script>` breakout risk in HTML — use `JSON_HEX_TAG` |
| 11 | Standard Smarty `{$var}` in `.tpl` | XOOPS uses `<{$var}>` delimiters |

### `commit-msg`

Refuses commit messages containing:

- "Claude" / "Claude Code" / "Anthropic"
- "Generated with [Claude / AI / ...]"
- `Co-Authored-By:` lines naming an AI assistant

This enforces the universal CLAUDE.md privacy rule mechanically.

## Bypass

Both hooks accept `--no-verify`:

```bash
git commit --no-verify -m "..."
```

Only use this when the match is a genuine false positive (e.g., an
`unserialize($cached, ['allowed_classes' => false])` that the regex
misidentifies). Document the reason in the commit body.

## Adding a new sniff

1. Edit `.githooks/pre-commit`.
2. Each sniff is a `grep -nE` against the staged-added lines, followed by
   a `report` call with a short label and sample output.
3. The diff is filtered to *added* lines only, so legacy code does not
   false-fire — only new occurrences count.
4. Run a test commit to confirm the new sniff fires on the bad pattern
   and stays silent on the good pattern.

## Why a hook, not more CLAUDE.md prose?

CLAUDE.md is read at conversation start and held in context. When code
generation pattern-matches on a *task concept* ("DB safety guard") the
relevant rule may be filed under a different category ("PHP precedence
gotcha") and not fire. Mechanical sniffs run unconditionally on every
commit, so the failure mode of "I knew the rule but didn't apply it"
becomes structurally impossible.

When a rule is violated post-hoc and caught by review, the canonical
response is: **add a sniff here**, not "I will be more careful next
time."
