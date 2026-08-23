# Pull Request

<!--
Thanks for contributing to XOOPS! Fill in the sections below.
The checklist encodes what the review bots and maintainers check on
every PR - working through it honestly shortens your review cycle.
-->

### Why
<!-- The problem, bug report link, or motivation for this change -->


### What
<!-- What changed, at the level a reviewer needs before reading the diff -->


### Verification
<!-- Commands run, suites green, manual checks. "Proven red then green"
     beats "should work": if you added a test to pin a fix, say that you
     watched it fail against the unfixed code first. -->


### Checklist
<!-- An item that does not apply gets "N/A" plus the reason in
     Verification - honesty beats box-ticking. -->

- [ ] docs/changelog.270.txt has an entry (curated notes live there; the root CHANGELOG.md is generated - never hand-edit it)
- [ ] Every changed conditional has both branches covered by a test, including the failure paths (a false return, a refused call)
- [ ] New tests pass with their file run alone - no reliance on suite order, and every process-global they touch (`$_SERVER`, ini settings, session or cookie state, `$GLOBALS['xoopsConfig']`) is saved and restored - or, for constant-driven branches, the test is isolated in its own process (PHPUnit's RunInSeparateProcess attribute, the repository's existing pattern), because a defined constant cannot be unset or restored
- [ ] No diagnostic, log line, or error message emits a full server path - basename() or a root-relative path only
- [ ] Production code (htdocs/) handles failures explicitly: avoid @ suppression and check results of calls like ini_set(). Where repository guidance permits a guarded suppression (an immediate === false check plus trigger_error(), per xoops-copilot-template.md), say why in the commit body. Tests may use @ for expected diagnostics. The .githooks pre-commit check (opt-in: git config core.hooksPath .githooks) scans only the ADDED lines of staged non-test, non-vendored PHP, and flags only the direct @identifier() form - the rule is yours to apply, not the hook's
- [ ] Factual claims in the description, comments, and changelog (what a function does, which PHP versions are affected) were verified by running them, not inferred
- [ ] The PR description matches the implementation - when review changes a promised contract (severity, return type, behavior), the description is part of the diff
- [ ] Commits follow the conventional-commit dialect in use (for example fix(scope):, test(scope):, compat(scope): - cliff.toml groups the changelog by these types but accepts any subject, so match existing history rather than treating it as a validator), with code and tests in separate commits where practical, and fixup commits squashed
