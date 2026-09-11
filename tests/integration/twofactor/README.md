# Two-factor MySQL integration tests

Run from the repository root with PHP 8.2–8.5, `mysqli`, `sodium`, `proc_open`
enabled, and the normal `htdocs/xoops_lib/vendor` dependencies installed:

```sh
XOOPS_2FA_TEST_DATABASE=xoops_twofactor_test \
XOOPS_2FA_TEST_HOST=127.0.0.1 \
XOOPS_2FA_TEST_USER=test_runner \
XOOPS_2FA_TEST_PASSWORD=test_password \
php tests/integration/twofactor/run.php
```

Optional `XOOPS_2FA_TEST_PORT` defaults to 3306. The database must already
exist; use a disposable database and a user allowed to CREATE, DROP, SELECT,
INSERT, UPDATE and DELETE its tables. No `mainfile.php` or installed site's
credentials are read. With no database configured the runner prints SKIP and
exits successfully. A configured database that cannot connect fails the run.

The runner generates its own unpredictable `twofactor_test_<hex>` table
prefix and private temporary directory. It creates only four tables using
the shipped install DDL, and drops only those exact names in `finally`.
Keys, generated PHP fixture slices and PHP session files stay in that
temporary directory and are removed. A killed process may leave these
explicitly named test artifacts; it never deletes the supplied database.

CI supplies a disposable MySQL 8.0 service for each PHP version. The script
does not require PHPUnit or a new Composer dependency. Failures throw even
when PHP's `assert()` has been compiled out.

## What runs

- Separate PHP workers open distinct real MySQL connections, signal ready,
  then wait for the parent's start barrier. Checks cover one TOTP step twice,
  one recovery code twice, five simultaneous failures with exactly one lock
  transition, ten recovery/reset races, and concurrent key provisioning.
  Concurrent first enrolments for distinct accounts share one key; concurrent
  enrolments for the same account yield one durable row and exactly ten codes.
  MySQL deadlock error 1213 is an explicit refused attempt; a distinct-account
  victim retries once, as a user would, without replacing the key or neighbour.
- Real `XoopsUser2faHandler`, `XoopsTokenHandler`, encryption, TOTP, MySQL
  transactions and installed schema are used throughout.
- A pending login and its challenge run in different PHP processes with a
  real file-backed PHP session. The challenge includes the actual controller
  without preloading its completion helper. Reset after consumption must
  prevent actual session completion.
- The unmodified cookie/session block of `common.php` is extracted at checked
  boundaries and included. Real JWT signing/validation and userutility key
  loading exercise pre-enrolment cookies, missing `fgen`, pending challenges,
  stale/missing session generations and policy pause/resume.
- The management controller runs unchanged up to its template-rendering
  boundary: GET, CSRF/password refusal, encrypted pending enrolment,
  confirmation with a new session ID, second-tab refusal, one-time recovery
  display, regeneration and disable. Authentication uses the real native
  adapter with a boundary account-loader double. That double also simulates
  password rehash persistence across requests: the setup digest must use the
  newly authenticated user object, and failed persistence must prevent
  confirmation. This tests the controller contract, not the native member
  handler's password hashing implementation.
- Missing and malformed encryption keys still permit recovery. Provisioning
  refuses to replace the key over encrypted rows; AAD prevents moving blobs
  between users or between setup sessions and stored factors.
- A genuinely absent factor table preserves an existing session with the
  feature not installed. The actual 2.7.4 upgrade tasks create the table,
  preference and options, rerun without duplicates, and support enrolment.

## Boundaries

This is process/request and database integration, **not a full HTTP site or
browser test**. It does not run all of `common.php`, the upgrade wizard UI,
Profile routing/preloads, template rendering, or HTTP cache/frame headers.
User/member loading, mail/events, cookie emission and security-token
validation are boundary doubles. CSRF branching runs, but token generation
and transport are covered elsewhere. The fixture keeps real PHP sessions,
factor storage and controller/helper logic; it does not replace that logic
with source-string assertions or mock SQL.

The upgrade check proves the actual patch and file-first authentication gate,
not an end-to-end authenticated browser upgrade. Concurrent enrolment workers
exercise real handlers and storage, not simultaneous HTTP forms.

## Installed-site HTTP smoke

With the same disposable database settings, run:

```sh
php tests/integration/twofactor/site-smoke.php
```

CI runs this after the concurrency suite. It requires DOM, mysqli and sodium.
It installs the shipped schema and seed data under a random table prefix,
copies the core into a private temporary docroot, and reuses the installed
vendor directory read-only. A local PHP server runs the actual mainfile,
common bootstrap, native authentication, controllers and Smarty templates.
The HTTP client carries real cookies and CSRF fields; sessions use MySQL.

Checks cover setup GET, rejected CSRF/password, encrypted pending setup,
confirmation and one-time rendered codes, Profile routing on/off, a fresh
pending login without an authenticated UID, recovery challenge, closed-site
challenge and password-confirmed users-admin reset. Cleanup removes only the
generated tables and private temporary directory, including on failure.

This is an HTTP smoke, not a JavaScript/browser or upgrade-wizard UI test.
Profile metadata/preloads are enabled without installing custom-field tables.
Mail transport is deliberately unavailable; committed actions must survive
notification failure. No real user data or installed-site credentials are used.
