# Two-factor authentication in XOOPS 2.7.4

The site preference supports `off` (challenges paused) and `optional`.
Required enrolment and trusted-device cookies are not part of this release.

## Install or upgrade

Enable PHP sodium in the web server's PHP configuration. The installer and
upgrade preflight report whether it is available. A CLI PHP installation
may load different extensions from the web server, so check the web page.
You can finish installation or upgrade with 2FA off while fixing sodium.

Complete the upgrade wizard **including its final System-module update**.
That step registers the challenge and management templates. Then select
`optional` in System Preferences when ready to accept authenticator logins.

## Enrol and manage

Open Edit Account → Two-factor authentication, or `user.php?op=2fa_manage`.
The core page also works when the Profile module is disabled.

Enter your current password, scan the locally generated QR code (or enter
the manual key), and confirm a six-digit authenticator code. Setup expires
after five minutes; five incorrect confirmation codes require setup to
start again. Two browser tabs share one setup secret; only one confirmation
can enrol the account. Plain HTTP displays a warning; use HTTPS in production.

Save the ten recovery codes immediately. They appear only in the successful
POST response, grouped for reading, and each works once. If you lose that
display, use the management page with your password and an authenticator
code to generate a replacement set. Existing recovery codes are revoked.
Do not share the key or the recovery codes with support staff.

Disabling or replacing recovery codes requires your current password and
an authenticator or recovery code. Recovery codes work during a TOTP lock
and when the encryption key or sodium is unavailable. Five incorrect factor
attempts lock TOTP for fifteen minutes; the lock is not extended by retries.

Enrolment keeps the current browser signed in and invalidates other
pre-enrolment sessions and remember-me cookies. Enrolled accounts never get
remember-me cookies, even when policy is off. Disabling/resetting a factor
revokes its recovery codes and cookies and invalidates pending challenges,
but **does not end established sessions**. An administrator resetting a
factor is not performing a general sign-out-all-devices action.

## Administrator reset

In Users Admin, edit the user and follow “Reset this user’s two-factor
authentication.” Confirm with **your own administrator password**. The
action is logged and the affected user receives a best-effort email. Mail
failure does not undo the reset.

## Back up the site key

Include the `twofactor` key file stored by XMF FileStorage under
`xoops_data/data` in the same protected backup as the database. Its filename
uses the installation's database-prefix namespace. Keep the directory out
of the public web root and preserve its access restrictions. Restoring the
database without its matching key makes authenticator secrets unreadable.

Never replace a lost key while encrypted factor rows still exist. Recovery
codes continue to work; reset affected factors and enrol again. The code
refuses to provision a replacement key while any row retains a secret.

## Emergency recovery and the upgrade wizard

The wizard cannot run the authenticator challenge in this release. Sign in
through the normal site first, or use the operator reset below when needed.
Policy `off` pauses alternate-path challenges too.

After identifying the account UID through trusted administration records:

1. Put a regular file named `2fa-reset-<uid>.txt` in `xoops_data/data`.
   Its contents must be the word `reset` (surrounding whitespace is allowed).
   Do not use PHP code or a symlink.
2. Authenticate with that account's password. Submit the challenge form,
   or authenticate through the upgrade wizard. The reset is bound to the
   password-authenticated UID, never to a request-supplied UID.
3. The file is renamed to `2fa-reset-<uid>.used` **before** the database
   reset. A pre-existing `.used` marker or a failed rename refuses the reset.
4. Check the outcome, remove the `.used` marker after resolving the incident,
   and enrol again. If the database reset failed after consumption, repair
   that failure before removing the marker and creating a new sentinel.

Markers accumulate until an administrator removes them. A `.php` file
returning `true`, described in an earlier proposal, is not recognised.

## Legacy SSL popup and API access

Replace or remove any older copy of the repository-root `extras/login.php`
that an operator previously installed in a separate SSL directory. Updating
the core does not update that copy; it remains a password-only bypass.
The current popup directs factor-protected users to core login. XML-RPC
refuses accounts that must present a factor under the current policy.

## Verification

See `tests/integration/twofactor/README.md` for the MySQL concurrency and
request-flow suite. On the actual deployed site, confirm setup and login,
recovery, reset, Profile routing, a closed-site login and template rendering
before enabling the feature for members.
