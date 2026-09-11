# Two-factor authentication for XOOPS 2.7 — design proposal

Date: 2026-09-11. Revision 10, self-contained: every retained requirement is
written out here and nothing refers to an earlier revision.
Status: implementation and local verification complete; CI and integration
of the remaining PR stack are release gates. PRs #201 and #204
are merged, #205 and #206 provide storage and the challenge, and the
completion work adds management, preflight and MySQL coverage. Target: 2.7.4 Beta 2. Beta 1 shipped on 2026-09-10.

**Maintainer decision kept against one reviewer's recommendation:** enrolment
over plain HTTP warns rather than refuses. The password and session cookie
already travel in the clear on such a site, so 2FA there still defends
against credential stuffing and reuse; the warning names the risk.
The same warning policy applies to management and recovery-code display.
HTTPS refusal remains a policy change, not part of these implementation fixes.

## 1. Decisions

- **Beta 2 ships a working feature**: TOTP enrolment with QR and manual key,
  recovery codes, the challenge page, self-service disable, admin reset, with
  policy modes `off` and `optional`. The bypass tests in section 12 are the
  quality bar for that feature, not a substitute for it.
- **`required` mode is deferred** to a later beta or RC. Its behaviour is
  specified where it differs. The mode value is validated in code against
  the allowed set, so a value written straight into the config table falls
  back to `optional`.
- **Two independent predicates** govern the feature:
  - *challenge* ⇔ policy ≠ `off` ∧ factor state ≠ `none`. Policy `off`
    pauses challenges (the preference help says "paused"), including for
    an `unavailable` factor, so a paused site with a bad key file is not
    locked out of a factor nobody is asking for.
  - *remember-me eligibility* ⇔ row absent or row state = `disabled`. Enrolled or unknown factor rows
    never receive or restore a remember-me cookie in phase 1, whatever the
    policy. Trusted devices are a phase-2 cookie.
- **Remember-me tokens carry the factor generation** in a claim `fgen`
  (the row's generation at issue, `''` with no row), beside the `pfp`
  password fingerprint #194 added. Restore requires it to equal the current
  generation. **An absent `fgen` is read as `''`**, not as a failure:
  #194's `pfp` is fail-closed when absent so that pre-#194 cookies die, but
  copying that for `fgen` would revoke every remember-me cookie on the site
  at upgrade, including for users who never enrol. Fail closed only on a
  present-but-wrong `fgen`.
- **TOTP parameters:** SHA-1, 30-second period, 6 digits, 160-bit secret in
  base32, window of one step either side, accepted steps strictly
  increasing. The window is not a preference.
- **Recovery codes reuse `XoopsTokenHandler`** with scope `2fa_recovery`:
  ten codes of 128 random bits, shown once as base32 in groups of four,
  hashed SHA-256 by the handler. **Canonicalisation (spaces stripped,
  upper-cased) happens in the 2FA caller, never inside the handler**, whose
  other scopes hash case-sensitive base64url tokens that upper-casing would
  break. **No-expiry is `expires_at = 4294967295`** written by a `$ttl = null`
  path: `verify()` requires `expires_at > now`, so `0` would fail every code,
  and changing the meaning of `0` would make every default-`0` row
  immortal; `MIN_TTL` (60 s) is not bypassed with `ttl = 0`.
  `purgeExpired()` deletes rows older than seven days only when expired or
  used, so unused codes survive it; a test proves both.
- **Password changes do not rotate the factor generation.** A reset during
  a pending login is caught by the pending record's password digest.
  Ending other sessions on a password change stays out of scope, as
  decided for #194. Session IP binding is not part of this design; the
  session handler's own IP rules apply unchanged.
- **Sodium is required by enrolment and TOTP verification**, listed in
  Composer's `suggest` rather than the site's hard requirements. Runtime,
  installer and upgrade checks require the extension and the keygen, encrypt
  and decrypt functions. Setup and TOTP fail closed when unavailable;
  recovery codes and sites with 2FA off remain usable.
- **The 2FA preference rows live in `XOOPS_CONF`**, the category
  `common.php` loads on every request, so their presence doubles as the
  "installed" signal at no extra query. No other setting moves.
- **TOTP implementation:** the core `XoopsTotp` helper is used, with RFC
  6238 vector tests and no OTP dependency. Its SHA-1/six-digit/30-second
  parameters and one-step window remain fixed.


## 2. Baseline before implementation (master after #184 to #199)

- `include/checklogin.php` is the password login path for core `user.php`,
  the profile module's `user.php` and `include/site-closed.php`. It writes
  `last_login` and saves the user as soon as the password is accepted, then
  establishes the session inline; its only hook fires after the session
  exists; `doLoginMaintenance()` runs at the end. #194's remember-me token
  carries `uid` and `pfp`.
- `upgrade/login.php` authenticates and writes `$_SESSION['xoopsUserId']`
  itself. `upgrade/checkmainfile.php` includes `include/common.php`, so
  **`common.php` runs inside the wizard against the old schema** until the
  patch has applied. `class/xml/rpc/xmlrpcapi.php` uses `loginUser()` for
  the Blogger, MetaWeblog, MovableType and XOOPS APIs. `ajaxfineupload.php`
  validates a session-bound JWT. `kernel/user.php`'s deprecated static
  login always returns false. The installer seeds its own token session.
  `extras/login.php` at the repository root (not under `htdocs/`) is a
  further password path: a legacy SSL-bridge popup login that an operator
  copies under an SSL directory by hand; it includes `mainfile.php`, calls
  `loginUser()` and writes `$_SESSION['xoopsUserId']` itself. Nothing in
  core references it.
- `include/common.php` after #194 seeds `$_SESSION['xoopsUserId']` from the
  remember cookie before loading the user; `pfp` is fail-closed when
  absent; the block that loads the user from the session runs on every
  authenticated request; `X-Frame-Options` is sent from the site setting
  (default `sameorigin`) and not at all when that setting is empty.
- `XoopsMySQLDatabaseProxy::query()` **refuses database writes during a GET
  request** with a warning; `exec()` does not go through that guard.
- `users.uid` is `mediumint(8) unsigned`; the `tokens` table matches. The
  `session` table is MyISAM and outside any InnoDB transaction. The
  session handler's default security level 3 drops a session whose IP
  leaves the configured mask.
- `XoopsTokenHandler`: `create(uid, scope, ttl = 3600, revokePrevious = true)`
  generates 32 random bytes, stores `sha256(rawToken)`, `expires_at = now +
  max(60, ttl)`; `verify()` is one conditional update on the token row
  only, with no knowledge of `user_2fa`; `revokeByScope()` returns void and
  ignores failure; `purgeExpired()` as above; nothing calls purge today.
- `XoopsMySQLDatabase` holds one mysqli handle per request; Protector's
  trap delegates to it; the logger issues no statements; no core path uses
  `autocommit = 0` or a transaction API. Transactions go through
  `exec('START TRANSACTION')` / `COMMIT` / `ROLLBACK`.
- `XoopsMemberHandler::deleteUser()` deletes memberships and the user row
  only.
- **MySQL evaluates single-table `SET` assignments left to right and later
  expressions see the updated value.** No existing core `UPDATE` has a
  dependent assignment.
- XMF `Key\FileStorage`: `fetch()` is `include` (a missing file yields `''`,
  a truncated one can yield `'1'`), `Basic::create()` writes a hex string,
  `KeyFactory::build()` always creates. `chillerlan/php-qrcode` is vendored;
  the smartyextensions QR plugin renders locally unless `externalFallback`
  is set.
- `tests/` runs against a stub database.

## 3. Sequencing

1. **PR A — the login-path split.** Not "behaviour-preserving": the cookie
   restore reorder is a deliberate change on the failure path. Split
   `checklogin.php` into `xoops_login_authenticate()` and
   `xoops_login_establish_session()`; move `last_login`, the user save and
   `doLoginMaintenance()` into the completion helper; change the cookie
   restore to load-before-seed. Pins: a failed password does not move
   `last_login`; a successful password login still does; a valid cookie
   still restores; a cookie for a missing or inactive account is refused
   without seeding.
2. **PR B — token handler and deletion.** Caller-supplied raw token;
   `$ttl = null` writes `4294967295`; `revokeByScope()` returns its outcome;
   regression coverage for `revokePrevious = false`; the purge test.
   `deleteUser()` removes tokens for all scopes first, then memberships and
   the user; it removes the factor row only after the user delete succeeds.
   A failed membership/user delete can revoke tokens but must retain the
   enrolled factor, so the surviving account cannot become password-only.
   Legacy MyISAM tables prevent promising an atomic rollback. Failed factor
   cleanup after successful user deletion is logged; the deletion still
   returns success because the account is gone. Canonicalisation stays out
   of the token handler.
3. **PR C — the feature**, split into C1 storage (#205), C2 login gate
   (#206), and C3 enrolment, management, admin reset and preflight. Extra
   pins: a password accepted with a challenge pending does not move
   `last_login`; a fresh challenge request loads its completion helper;
   Profile does not redirect core 2FA POSTs or discard their form data.
4. **A MySQL-backed CI job**, skipped locally without a database, for the
   concurrency and real-flow tests. New infrastructure that the session
   handler, token handler and upgrade patches would use as well.

## 4. Storage

`user_2fa`, bare name in install SQL, `$db->prefix('user_2fa')` at runtime,
`ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`, no FOREIGN KEY.

```
  uid             mediumint unsigned NOT NULL PRIMARY KEY
  state           varchar(10)   NOT NULL                        enrolled | disabled
  method          varchar(16)   NOT NULL DEFAULT 'totp'
  secret          varbinary(255) NULL                            AEAD blob; NULL unless enrolled
  confirmed_at    int unsigned  NOT NULL DEFAULT 0
  last_counter    bigint unsigned NOT NULL DEFAULT 0
  failed_attempts smallint unsigned NOT NULL DEFAULT 0
  locked_until    int unsigned  NOT NULL DEFAULT 0
  generation      char(32)      NOT NULL                        128 random bits, hex
```

**The row is created on the enrolment confirmation POST, never earlier.**
Opening the setup page writes nothing: the pending secret lives in the
enrolling session, and "no row" already means "no factor". Creating a row
with a fresh generation on the setup GET would have ended every other
session and every remember-me cookie (`fgen = ''`) before a factor existed,
and would have been a write on a GET that the database proxy refuses.
There is no `unenrolled` state; the deferred `required` mode treats "no row
or disabled" as not enrolled and throttles its forced-enrolment attempts in
the pending session, since the only secret being guessed there is one the
session itself holds.

Rows are never deleted by disable or reset (only by `deleteUser()`): those
set `state = 'disabled'`, null the secret, revoke the `2fa_recovery`
tokens and write a new generation, in one transaction (section 6).

**`stateFor()` maps the row and the key, not the policy:**

| Row | Result |
|---|---|
| no row, or `disabled` | `none` (`required-unenrolled` under the deferred policy) |
| `enrolled` and the secret decrypts | `enrolled` |
| `enrolled` and the key or blob is bad | `unavailable` (recovery codes still work) |

**Installed signal.** Every factor-state lookup first checks that the
`twofactor_mode` row exists **in the database-loaded configuration**,
captured in `common.php` before `var/configs/xoopsconfig.php` merges in, so a
file override cannot fake installation. Absent means the 2.7.4 patch has
not run and the feature is dormant with no query; the config handler's
request-local cache means a row inserted during the wizard's request is
seen from the next request on. **The upgrade task creates the table first
and inserts the config row last.** "Row present, table missing" is
therefore unreachable except by tampering.

**Lookup failure is asymmetric.** At the login gate and on the challenge
page a failed `user_2fa` query returns `unavailable`, which blocks login
until repaired or the escape hatch is used: fail closed. On an already
established session that presented the factor (section 9), a failed
lookup is logged once per request and the request continues: fail open. Otherwise a
five-second database blip would sign out every user on the site with no
way back in.

## 5. Encryption of the secret

- **Key**: `sodium_crypto_aead_xchacha20poly1305_ietf_keygen()` (32 bytes),
  stored through `FileStorage::save('twofactor', base64_encode($key))`.
  `Basic` and `KeyFactory` are never used for it.
- **Provisioning** at the start of the first enrolment on the site, only if
  the key is missing or malformed **and** no row has a non-null secret, so a
  lost key is never replaced while encrypted rows exist. Serialised with
  `flock()` on a stable lock file `xoops_data/data/twofactor.lock` held
  across the re-check and the write; the encrypted-row check is a callback
  evaluated inside that lock, never a stale precomputed query result; **if the lock cannot be opened or
  taken, enrolment reports the factor unavailable.** A temp-and-rename
  alone does not serialise: the loser can recreate the temp path after
  the winner's rename.
- **Reading**: `exists()`, `fetch()`, strict `base64_decode(..., true)`,
  exactly 32 bytes; anything else is *unavailable*. The read is wrapped so
  no include warning with a path reaches a page; the failure class is
  logged.
- **Cipher**: XChaCha20-Poly1305 IETF with a fresh 24-byte nonce.
  Separate associated-data domains: `row:<uid>:<method>` for the stored
  blob, `pending:<uid>` for the secret held in the enrolling session, so a
  blob cannot be moved between rows or from a session into a row. Stored
  as `v1:` + base64(nonce ‖ ciphertext), which fits `varbinary(255)`.
- **Unavailable TOTP secret** refuses the TOTP path and says so; never
  "unenrolled". Recovery codes remain usable because they do not depend on
  the key, so a site whose key is lost is not locked out of every
  administrator at once.
- **Operator escape hatch**: `xoops_data/data/2fa-reset-<uid>.txt` containing
  the word `reset`, read as text and never executed, honoured by the challenge page and the wizard login. Bound to the
  pending or wizard-authenticated uid, never a request parameter; filename
  from `(int) $uid`; `realpath()` result required under
  `realpath($dataDir) . DIRECTORY_SEPARATOR`; regular file only, no symlink.
  Consumed by renaming to `.used` **before** the reset runs; **refused if
  the rename fails or if a `.used` twin already exists**, because a POSIX
  rename would replace the earlier marker and a fresh file could be
  dropped in again. The operator docs say that a refused reset means the
  twin must be removed first and that `.used` files accumulate until an
  administrator removes them. An attacker who can write the data directory
  can already execute code on the site, so the remaining race is accepted.
  Logged on use.
- Docs: the key file is part of the site backup.

## 6. Atomic verification

**TOTP acceptance** binds to the generation the challenge verified against
and to the lock:
```
UPDATE {p}user_2fa
   SET last_counter = :step, failed_attempts = 0, locked_until = 0
 WHERE uid = :uid AND state = 'enrolled' AND generation = :verifiedGeneration
   AND locked_until <= :now AND last_counter < :step
```
Exactly one affected row grants. `last_counter < :step` alone guarantees
one winner per step; a code for step N-1 after N was accepted is refused
(the window tolerates skew, not reuse). `locked_until = 0` compares as
unlocked. Success clears the lock as well as the counter, so the users
admin never shows a stale lock. With every operation touching the same
InnoDB row, a concurrent disable or reset either lands first (generation
changes, this update affects zero rows) or second (the code was accepted
before it).

**Recovery acceptance is serialised on the factor row.** The token
handler's `verify()` knows nothing of `user_2fa`, so the challenge runs one
transaction: `SELECT state, generation FROM user_2fa WHERE uid FOR UPDATE`;
require `state = 'enrolled'` and `generation = :pendingGeneration`; call
`verify()` (which consumes the token); `UPDATE user_2fa SET failed_attempts
= 0, locked_until = 0 WHERE uid`; `COMMIT`. Disable, reset and regeneration
take the same row lock first, so a reset cannot commit between the check
and the grant. A recovery code is accepted during a TOTP lock (128-bit
single-use tokens cannot realistically be guessed, and this is the legitimate user's way
past an attacker who knows the password and burns the TOTP throttle);
using one sends a mail.

**Failure throttle** runs in a short transaction: select the enrolled row
`FOR UPDATE`, require the rejected challenge's generation, compute the
counter and lock in PHP, then write literal values and commit. This avoids
MySQL/MariaDB assignment-order differences (including
`SIMULTANEOUS_ASSIGNMENT`):

- An expired lock resets the counter to 1 and lock to 0.
- Otherwise increment the counter, capped at 65535.
- An unlocked row reaching five failures locks until now + 900 seconds.
- An active lock is never extended by a wrong code.
- A stale challenge never increments a later enrolment's counter.
- Mail is sent after commit only when this request transitions from
  unlocked to locked; mail failure never reverses the database change.
- A storage failure is unavailable, not an incorrect code, and does not
  enter the failure-count path. Strict token verification distinguishes
  a failed UPDATE from an UPDATE matching no token, while other token
  callers retain their existing false-on-failure contract.

**Transactions** run through one handler helper, `withTransaction(callable)`:

- `START TRANSACTION` on the request's single mysqli handle; `COMMIT` on
  success; **`ROLLBACK` in `catch`/`finally` on any exception or any write
  that reports failure**, never relying on connection close.
- **Re-entrancy guard**: a nested `START TRANSACTION` would commit the outer
  one, so the helper refuses to nest.
- Inside the callable: only `user_2fa` and `tokens` writes, through `exec()`
  (`SELECT ... FOR UPDATE` is a read and is allowed on GET, though the
  challenge and management pages are POST). **No `redirect_header()`,
  `exit()`, preload event, mail, DDL, or write to `users`** (MyISAM, cannot
  roll back); those happen after commit.
- Enrolment confirmation, disable, reset and regeneration each run inside
  it, with the row locked `FOR UPDATE` first.

## 7. The login flow

After PR A:

- `xoops_login_authenticate()` — password auth, level and closed-site checks.
  No `last_login` write, no save, no maintenance.
- `xoops_login_establish_session(XoopsUser $user, bool $remember, string $redirect, ?string $verifiedGeneration)`
  — regenerates the session id once and refuses failure before any account
  writes; seeds `$_SESSION` including `xoops2faGeneration` and `xoops2faVerified`,
  writes `last_login` and saves the user,
  fires the login event, runs `doLoginMaintenance()`, issues the remember-me
  cookie with `fgen` only when the row state is not `enrolled`, and
  redirects. **`xoops2faVerified` is true only when `$verifiedGeneration` is
  supplied by a completed challenge or enrolment confirmation, and the
  session is stamped with that verified value, never with a freshly read
  one**: a reset between code consumption and session completion changes
  the row's generation, and the completion re-reads the row and refuses if
  it no longer equals the verified one. A password login while policy is
  `off` leaves `xoops2faVerified` false, so turning enforcement on ends
  that session.

Between them, the gate:
```
$state = stateFor($user);      // none | enrolled | unavailable | required-unenrolled (deferred)
if ('off' !== policy && 'none' !== $state) {
    if (!regenerate_id(true)) { clear session and refuse login; }
    $_SESSION = [];
    $_SESSION['xoops2faPending'] = [
        'uid', 'state', 'generation',
        'passdigest' => hash('sha256', stored password hash),   // server-side only, no key
        'expires'    => time() + 300,
        'remember', 'redirect',
    ];
    redirect to user.php?op=2fa
}
```
No `xoopsUserId` is set, so the visitor is anonymous to every other page.
Both remember-cookie forms are expired, and cookie restoration refuses a
session with a pending challenge, so another account cannot be restored
behind the challenge.
The pending session is the first-factor result; a stolen pending session
can submit codes without the password, which is why the throttle is per
account. The 300-second expiry is the time to open an authenticator app,
not a security boundary, and is not a preference.

## 8. The challenge page

`op=2fa` in core `user.php`; the profile module's `user.php` and
`site-closed.php` include the same `include/checklogin2fa.php`.

- Without a pending record, or with an expired one, or after the session
  handler dropped the session (a mobile user crossing an IP mask at the
  default security level), the page says **"start again"** with a link to
  the login form; it is not presented as a security error.
- **Re-checks before granting**: active, closed-site permission, groups,
  `state = 'enrolled'`, generation equal to the pending record's, and
  `hash('sha256', current stored hash)` equal to `passdigest`. A password
  reset, an admin reset or another enrolment invalidates the challenge.
- **Two inputs**: the code field with `inputmode="numeric"
  autocomplete="one-time-code" maxlength="6" dir="ltr"`, and a revealed
  recovery-code field with `inputmode="text" autocomplete="off"`. One field
  routing by length fights mobile keyboards. All forms POST with the
  XoopsSecurity token, checked first.
- TOTP through section 6; recovery through section 6; the escape hatch
  honoured.
- Throttle per section 6: five failures lock the second factor for fifteen
  minutes and drop the pending record; independent of Protector, which
  keeps its own IP limiting.
- On success: `xoops_login_establish_session()` with the verified generation.
- Response headers on the challenge and enrolment pages:
  `Cache-Control: no-store`, `Referrer-Policy: no-referrer`, and
  `X-Frame-Options: DENY` **set unconditionally**, because `common.php`
  sends the site's own value earlier and nothing at all when that setting
  is empty, so only an unconditional `header()` replaces it. Template and
  page caching off for both pages.
- A rejected code whose step lies within two steps of the window is logged
  as probable clock skew, without the code.

## 9. Sessions on every authenticated request

In `common.php`'s `if (!empty($_SESSION['xoopsUserId']))` block, when the
installed signal is present:

- One primary-key lookup of `user_2fa` for the session's uid. Sites with no
  rows pay one indexed miss per request; that cost buys the guarantee that
  an enrolment or an enrolled-row generation change in another browser
  ends the session on its next request. Caching the row state in the session would defeat exactly
  that. A failed lookup here is logged once per request and the request continues
  (section 4).
- **Generation, by row state.** Row `enrolled`: a stored generation is
  compared and a mismatch ends the session; **no stored generation ends
  the session whatever the policy mode**, because only enrolment
  confirmation or a completed challenge may establish the generation in a
  session, and a pre-enrolment session (or a stolen session-store one) must
  not be adopted. Row absent or `disabled`: no comparison; the session is
  stamped with the row's generation (or `''`) and continues, so a later
  write to a non-enrolled row cannot sign anyone out. **Disable and admin
  or operator reset keep established sessions active.** They revoke recovery
  codes and remember cookies and invalidate pending challenges; they are
  not a general sign-out-all-sessions mechanism.
- **Policy**: if the challenge predicate is true for this account and
  `xoops2faVerified` is not true, the session ends and the user logs in
  again through the challenge. Covers `off → login → on` and a user added
  to a required group.
- The enrolling browser is re-established with the new generation and
  `xoops2faVerified = true` at confirmation, so it survives its own change;
  every other pre-enrolment session and every remember-me cookie for that
  account ends. This guarantee applies to enrolment, not to disabling a row.

**Cookie restore**, in this order: validate the token; load the candidate
user without seeding; check active, the #194 `pfp`, `fgen` against the
row's current generation (absent `fgen` reads as `''`), and row state; an
`enrolled` row, a generation mismatch or any failed check clears both
cookie forms and stops; only then seed. The renewal recomputes `fgen` from
the row.

## 10. Alternate login paths

| Site | Disposition |
|---|---|
| `include/checklogin.php` via `user.php`, `modules/profile/user.php`, `include/site-closed.php` | the gate in section 7 |
| `upgrade/login.php` | phase 1: an account whose factor must be presented under the current policy is refused unless the escape-hatch file for that uid exists; the installed signal covers a 2.7.3 schema. Beta 3 item: run `checklogin2fa.php` inline in the wizard so the next core upgrade is not a webmaster lockout |
| `class/xml/rpc/xmlrpcapi.php` | one check after `loginUser()` refuses accounts whose factor must be presented under the current policy |
| `extras/login.php` (repository root, operator-copied SSL popup login) | one check after `loginUser()` refuses accounts whose factor must be presented under the current policy, with a comment pointing at the site's own login; a copied older version stays a bypass on that operator's host, so the release notes name it |
| `kernel/member.php::loginUser()` | unchanged; PR C greps the tree for every other caller and lists them in the PR body |
| `kernel/user.php` deprecated static login | always false |
| `ajaxfineupload.php` | unchanged, session-bound JWT, issued only to a fully established session |
| `install/include/functions.php` | out of scope: installer token session before a site exists |

The SSL popup buffers output until session rotation can send its cookie and
refuses authentication if rotation fails. Its factor refusal redirects only
to an HTTPS core URL; an HTTP core URL gets an explicit transport warning and
link, since the separate SSL bridge does not prove HTTPS exists for the core.
Core pending-login and authenticated-session creation, enrolment session
re-establishment and wizard login also refuse failed session rotation before
granting authentication state. A failed enrolment session reset leaves the
committed factor in place; the user must sign in again with the authenticator.

## 11. Enrolment and management, in core

`user.php?op=2fa_setup` and `op=2fa_manage`, templates in the system module,
linked from the profile module's `edituser.php`. Every state-changing POST
checks the XoopsSecurity token first.

- **Enable**: current password re-entered; provision the key if permitted
  (section 5); generate a 160-bit secret, encrypt it into the session under
  the `pending` domain; show the QR (local render, `externalFallback` never
  set, manual key always shown, HTTP warning when applicable) once; on the
  confirmation POST verify one code against the session secret, then in
  one transaction `INSERT` the row (`state = 'enrolled'`, encrypted secret
  under the `row` domain, `confirmed_at`, `last_counter` = the accepted
  step, a fresh generation) — or, if a row exists in `disabled`, update it
  the same way under `FOR UPDATE` — issue ten recovery tokens
  (`revokePrevious = false`, no expiry), commit; then show the codes once
  and re-establish the enrolling session with that generation as verified.
  Two tabs on the setup page share one session secret; the first
  confirmation wins and the second finds `state = 'enrolled'` and stops.
- **Regenerate recovery codes** and **disable**: current password plus a
  valid code. Code consumption and the requested mutation share one
  transaction with the row locked; nested transactions are forbidden. Disable sets
  `state = 'disabled'`, nulls the secret, revokes the tokens, writes a new
  generation. A user whose group is under a `required` policy cannot
  disable.
- **Admin reset**: users admin, acting administrator's current password in
  the form, same transaction as disable, event logged, user mailed; mail
  failure does not roll back. An absent/disabled factor is an authenticated
  no-op without a generation change, reset event or reset email.
- **Policy `off`**: challenges paused, rows kept, no remember-me for
  enrolled accounts.

## 12. Tests

- TOTP vectors; window; monotonic counter; N-1 after N refused; acceptance
  refused when the generation changed between verification and update;
  success clears the lock.
- Token handler: raw token; `4294967295` survives purge while used tokens
  are purged; `revokePrevious = false` batch; `revokeByScope()` outcome;
  `deleteUser()` removes tokens first but retains factor protection on a
  failed membership/user delete; factor cleanup follows successful deletion.
- Throttle: failures one to five individually; four do not lock; concurrent
  failures all count; a wrong code after expiry lands on 1; mail on the
  transition only.
- Real flow (MySQL job): no `xoopsUserId` before the challenge; a
  pre-enrolment cookie refused without seeding; a cookie with no `fgen`
  restores for an account with no row; session end on generation change;
  an `enrolled` row ends a session with no stored generation under policy
  `off` as well; a session with no stored generation and no row is stamped
  and kept; `off → login → on` ends an unverified session; an enrolled
  account password-logs in while paused, receives no cookie, and loses that
  session when enforcement resumes; setup GET writes nothing and ends no
  other session; confirmation POST creates the row and re-establishes the
  enrolling session; an authenticated 2.7.3 site upgraded file-first keeps
  working through the wizard.
- Concurrency (MySQL job): one TOTP code twice; one recovery code twice;
  five simultaneous wrong codes; recovery consumption racing a reset; a
  reset after code consumption but before session completion refuses
  completion.
- Lookup failure: a unit test with a handler double that throws proves the
  gate returns `unavailable` while an established verified session
  continues; no connection killing is needed.
- Key: provisioning refused with encrypted rows and no key; concurrent
  first enrolments produce one key; lock failure reports unavailable; a
  missing or 31-byte key gives *unavailable* for TOTP while a recovery code
  works; a blob moved to another uid, or from a session to a row, fails.
- Escape hatch: consumed once; refused when the rename fails or a `.used`
  twin exists; ignores a request-supplied uid; refuses a symlink.
- Endpoints refuse enrolled accounts; the wizard treats a missing table as
  `none`; Profile deactivated; no local QR package; no sodium fails closed.

## 13. Files touched

- PR A: `include/checklogin.php`, `include/common.php`, tests
- PR B: `class/XoopsTokenHandler.php`, `kernel/member.php`, tests
- PR C: `include/checklogin2fa.php`, `kernel/user2fa.php`, `user.php`,
  `modules/profile/user.php`, `include/site-closed.php`, system-module
  templates, profile `edituser.php` link, install SQL, an upgrade task,
  `upgrade/login.php`, `class/xml/rpc/xmlrpcapi.php`, users admin reset,
  `XOOPS_CONF` rows, `xoops_lib/composer.dist.json` (`ext-sodium`, the OTP
  library if chosen), language files and `docs/lang_diff.txt`,
  install/upgrade preflight sodium check

## 14. Delivery and operational verification

- The installer and upgrader report sodium availability explicitly; an
  installation/upgrade may continue with 2FA off. Setup and TOTP verification
  require sodium; recovery-code verification remains independent of the key.
- The final System-module update step imports both 2FA templates on an
  existing site. A manifest version bump is not required for that update.
- The 2.7.4 preference migration holds a database advisory lock while
  checking and inserting the preference/options, preventing concurrent
  authorized upgrade requests from creating duplicate configuration rows.
- `docs/2fa-operations.md` is the operator guide, including key backups,
  text reset sentinels, `.used` cleanup and replacing old SSL popup scripts.
- `tests/integration/twofactor/README.md` describes the opt-in MySQL suite,
  disposable database requirements and its boundary doubles. The concurrency
  runner exercises real database locks, separate PHP processes and sessions.
  The separate installed-site HTTP smoke runs the shipped bootstrap, native
  login, CSRF, database sessions, templates, Profile routing, closed-site
  challenge and admin reset. Neither is a browser/JavaScript or upgrade-wizard
  UI test; deployed extensions still need the site's acceptance check.
- Setup displays recovery codes only in the successful POST response,
  never in a persistent session, log or cached page. Lost display after a
  committed request is handled by password-and-code protected regeneration.
