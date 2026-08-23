<!-- markdownlint-disable MD041 -->
<!--
Thanks for contributing to XOOPS! Fill in the sections below.
The checklist encodes what review actually catches here - working
through it honestly shortens your review cycle. The items that need
background (changelog location, test isolation, error suppression,
commit style) have notes in CONTRIBUTING.md under "Pull request
checklist notes".
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
Notes for the items marked "see notes": [CONTRIBUTING.md - Pull request checklist notes](https://github.com/XOOPS/XoopsCore27/blob/master/CONTRIBUTING.md#pull-request-checklist-notes)


- [ ] docs/changelog.270.txt has an entry (the root CHANGELOG.md is generated - never hand-edit it)
- [ ] Changed conditionals have both branches covered by a test, including the failure paths
- [ ] New or changed tests pass with their file run alone and leave no process-global state behind (constants need process isolation - see notes)
- [ ] No diagnostic, log line, or error message emits a full server path - basename() or root-relative only
- [ ] Production code handles failures explicitly - no unchecked @ suppression, results of calls like ini_set() checked (see notes)
- [ ] Factual claims in the description, comments, and changelog were verified by running them, not inferred
- [ ] The description matches the implementation - a contract change during review updates it too
- [ ] Commits use the conventional-commit dialect in use, fixups squashed (see notes)
