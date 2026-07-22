# XOOPS 2.7.1 — Translations & Available Languages

XOOPS 2.7.1 ships with English as its source language and is additionally
available in **37 community translations** (38 language packs in total,
including the English reference pack).

All language packs are maintained in the **XoopsLanguages** organization on
GitHub:

➡️ **https://github.com/XoopsLanguages**

## Help wanted — translations need you

Translations are a community effort, and they are never "finished":

- **Found a wrong, awkward, or missing translation?** Please help us fix it.
  Open an issue or a pull request in the relevant language repository under
  https://github.com/XoopsLanguages.
- **Your language isn't listed?** We would love your help adding it. Start
  from the English pack as the reference and open a pull request, or contact
  the XOOPS team.
- **Speak a listed language?** A quick review pass to catch errors is hugely
  valuable, even if you don't translate anything new.

Every correction makes XOOPS better for users worldwide — thank you.

## Installing a language pack

1. Download the `.zip` for your language from the table below.
2. Unzip it and copy the language files into your XOOPS installation
   (the pack mirrors the XOOPS directory structure — drop the folders in
   place).
3. Select the language in **Admin → Preferences → General Settings**, or
   per user where applicable.

No build step is required — the packs contain ready-to-use language files.

## Updating a 2.7.0 language pack

XOOPS 2.7.1 adds one English language constant compared with 2.7.0:

`_AM_SYSTEM_BLOCKS_INVALID_CLONE` in
`htdocs/modules/system/language/english/admin/blocksadmin.php`.

See [`lang_diff.txt`](lang_diff.txt) for the exact definition. No English
language constants were added, changed, renamed, or removed between 2.7.1-RC1
and Final.

## Available languages

Language packs are published independently from XOOPS Core. Each link therefore
points to the repository's current release page, where the asset name and its
declared XOOPS compatibility can be verified before download.

| Language | Current release |
|---|---|
| Arabic | https://github.com/XoopsLanguages/arabic/releases/latest |
| Bosnian | https://github.com/XoopsLanguages/bosnian/releases/latest |
| Brazilian Portuguese | https://github.com/XoopsLanguages/brazilian/releases/latest |
| Bulgarian | https://github.com/XoopsLanguages/bulgarian/releases/latest |
| Catalan | https://github.com/XoopsLanguages/catalan/releases/latest |
| Croatian | https://github.com/XoopsLanguages/croatian/releases/latest |
| Czech | https://github.com/XoopsLanguages/czech/releases/latest |
| Danish | https://github.com/XoopsLanguages/danish/releases/latest |
| Dutch | https://github.com/XoopsLanguages/dutch/releases/latest |
| English (reference) | https://github.com/XoopsLanguages/english-Reference/releases/latest |
| Finnish | https://github.com/XoopsLanguages/finnish/releases/latest |
| French | https://github.com/XoopsLanguages/french/releases/latest |
| Galician | https://github.com/XoopsLanguages/galician/releases/latest |
| German | https://github.com/XoopsLanguages/german/releases/latest |
| Greek | https://github.com/XoopsLanguages/greek/releases/latest |
| Gujarati | https://github.com/XoopsLanguages/gujarati/releases/latest |
| Hebrew | https://github.com/XoopsLanguages/hebrew/releases/latest |
| Hungarian | https://github.com/XoopsLanguages/hungarian/releases/latest |
| Italian | https://github.com/XoopsLanguages/italian/releases/latest |
| Japanese | https://github.com/XoopsLanguages/japanese/releases/latest |
| Korean | https://github.com/XoopsLanguages/korean/releases/latest |
| Malaysian (Malay) | https://github.com/XoopsLanguages/malaysian/releases/latest |
| Norwegian | https://github.com/XoopsLanguages/norwegian/releases/latest |
| Persian (Farsi) | https://github.com/XoopsLanguages/persian/releases/latest |
| Polish | https://github.com/XoopsLanguages/polish/releases/latest |
| Portuguese | https://github.com/XoopsLanguages/portuguese/releases/latest |
| Romanian | https://github.com/XoopsLanguages/romanian/releases/latest |
| Russian | https://github.com/XoopsLanguages/russian/releases/latest |
| Chinese (Simplified) | https://github.com/XoopsLanguages/schinese/releases/latest |
| Slovenian | https://github.com/XoopsLanguages/slovenian/releases/latest |
| Spanish | https://github.com/XoopsLanguages/spanish/releases/latest |
| Swedish | https://github.com/XoopsLanguages/swedish/releases/latest |
| Chinese (Traditional) | https://github.com/XoopsLanguages/tchinese/releases/latest |
| Thai | https://github.com/XoopsLanguages/thai/releases/latest |
| Turkish | https://github.com/XoopsLanguages/turkish/releases/latest |
| Ukrainian | https://github.com/XoopsLanguages/ukrainian/releases/latest |
| Urdu | https://github.com/XoopsLanguages/urdu/releases/latest |
| Vietnamese | https://github.com/XoopsLanguages/vietnamese/releases/latest |

## Contributing

The translation workflow lives entirely in the
[XoopsLanguages](https://github.com/XoopsLanguages) organization:

- **Fix an error:** open an issue or PR in the matching language repository.
- **Add a new language:** request a repository (or open a PR) and use the
  English pack as the canonical reference for the constant names.
- **Keep packs in sync:** when core adds or renames language constants, the
  change is recorded in `docs/lang_diff.txt` of this repository — use it as a
  checklist when updating a translation.

Thank you for helping make XOOPS speak everyone's language.
