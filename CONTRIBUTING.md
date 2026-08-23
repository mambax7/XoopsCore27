# Contributing to [XOOPS CMS](https://xoops.org)

![alt XOOPS CMS](https://xoops.org/images/logoXoops4GithubRepository.png)

[![XOOPS CMS](https://img.shields.io/badge/XOOPS%20CMS-Core-blue.svg)](https://xoops.org)
[![Software License](https://img.shields.io/badge/license-GPL-brightgreen.svg?style=flat)](https://www.gnu.org/licenses/gpl-2.0.html)

Contributions are **welcome** and will be fully **credited**.

We accept contributions via Pull Requests on [GitHub](https://github.com/XOOPS/XoopsCore27).

## Pull Requests

- **[PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)** - The easiest way to apply the conventions is to install [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer) via Composer: `composer require --dev squizlabs/php_codesniffer`.
- **Add tests!** - We encourage providing tests for your contributions.
- **Document any change in behavior** - Make sure `docs/changelog.270.txt` (the curated notes for the 2.7.x line; the root `CHANGELOG.md` is generated) and any other relevant documentation are up-to-date.
- **Consider our release cycle** - We try to follow [Semantic Versioning v2.0.0](https://semver.org/). Randomly breaking public APIs is not an option.
- **Create feature branches** - Don't ask us to pull from your master branch.
- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.
- **Send coherent history** - Make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please squash them before submitting.

## Pull request checklist notes

The PR template's checklist links here for the detail behind each item.

- **Changelog** - curated release notes live in `docs/changelog.270.txt` for the 2.7.x line; the root `CHANGELOG.md` is generated from commit history and must never be hand-edited.
- **Process-global state in tests** - save and restore everything a test touches: `$_SERVER`, ini settings, session or cookie state, `$GLOBALS['xoopsConfig']`. A defined constant cannot be unset or restored, so a constant-driven test is isolated in its own process with PHPUnit's `RunInSeparateProcess` attribute - the repository's existing pattern.
- **Error suppression** - avoid `@` in production code (`htdocs/`) and check the results of calls like `ini_set()`. Repository guidance permits a guarded suppression - an immediate `=== false` check plus `trigger_error()` - where that is the cleaner path; say why in the commit body. Tests may use `@` for expected diagnostics. The opt-in pre-commit hook (`git config core.hooksPath .githooks`; see `.githooks/README.md`) catches only the direct `@identifier()` form on added lines of staged non-test, non-vendored PHP - the rule is yours to apply, not the hook's.
- **Commit dialect** - `fix(scope):`, `test(scope):`, `compat(scope):` and similar; `cliff.toml` groups the changelog by these types but accepts any subject, so match existing history rather than treating it as a validator. Code and tests in separate commits where practical; squash fixup commits before merge.

## Licensing

By contributing code you agree to license your contribution under the [GNU General Public License, Version 2 or any later version.](https://www.gnu.org/licenses/gpl-2.0.html)

Happy coding, and **_May the Source be with You_**!
