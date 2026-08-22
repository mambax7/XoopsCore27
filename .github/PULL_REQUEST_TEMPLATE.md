<!--
Thanks for contributing to XOOPS! Fill in the sections below.
The checklist encodes what the review bots and maintainers check on
every PR - working through it honestly shortens your review cycle.
-->

### Why


### What


### Verification
<!-- Commands run, suites green, manual checks. "Proven red then green"
     beats "should work": if you added a test to pin a fix, say that you
     watched it fail against the unfixed code first. -->


### Checklist

- [ ] docs/changelog.270.txt has an entry (curated notes live there; the root CHANGELOG.md is generated - never hand-edit it)
- [ ] Every changed conditional has both branches covered by a test, including the failure paths (a false return, a refused call)
- [ ] New tests pass with their file run alone - no reliance on suite order, and every process-global they touch (`$_SERVER`, ini settings, session or cookie state, `$GLOBALS['xoopsConfig']`) is saved and restored
- [ ] No diagnostic, log line, or error message emits a full server path - basename() or a root-relative path only
- [ ] Failures are handled explicitly: no @ suppression (the pre-commit hook enforces this) and results of calls like ini_set() are checked
- [ ] The PR description matches the implementation - when review changes a promised contract (severity, return type, behavior), the description is part of the diff
- [ ] Commits follow the conventional-commit dialect used here (fix(scope):, test(scope):, compat(scope):), with code and tests in separate commits
