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
| 3 | `unserialize($x)` (no `allowed_classes`) | Object-injection risk |
| 4 | `extract(` | Banned — access keys explicitly |
| 5 | `eval(` | Banned, no exceptions |
| 6 | `error_log(` | Use the PSR-3 logger in new code |
| 7 | `->queryF(` | Deprecated in 2.7 — bypassed Protector |
| 8 | `->quoteString(` | Deprecated in 2.7 — use `quote()` |
| 9 | `// removed` | BC-shim antipattern — delete the code instead |
| 10 | Standard Smarty `{$var}` in `.tpl` | XOOPS uses `<{$var}>` delimiters |

The diff is scanned with `--diff-filter=ACMR` so edits inside `git mv`
or `git cp` are also covered.

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
