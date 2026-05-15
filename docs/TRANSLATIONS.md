# XOOPS 2.7.0 — Translations & Available Languages

XOOPS 2.7.0 ships with English as its source language and is additionally
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

## Available languages

Each link points to the `latest` release asset for that language
(`<language>-core-2.7.0.zip`).

| Language | Download |
|---|---|
| Arabic | https://github.com/XoopsLanguages/arabic/releases/download/latest/arabic-core-2.7.0.zip |
| Bosnian | https://github.com/XoopsLanguages/bosnian/releases/download/latest/bosnian-core-2.7.0.zip |
| Brazilian Portuguese | https://github.com/XoopsLanguages/brazilian/releases/download/latest/brazilian-core-2.7.0.zip |
| Bulgarian | https://github.com/XoopsLanguages/bulgarian/releases/download/latest/bulgarian-core-2.7.0.zip |
| Catalan | https://github.com/XoopsLanguages/catalan/releases/download/latest/catalan-core-2.7.0.zip |
| Croatian | https://github.com/XoopsLanguages/croatian/releases/download/latest/croatian-core-2.7.0.zip |
| Czech | https://github.com/XoopsLanguages/czech/releases/download/latest/czech-core-2.7.0.zip |
| Danish | https://github.com/XoopsLanguages/danish/releases/download/latest/danish-core-2.7.0.zip |
| Dutch | https://github.com/XoopsLanguages/dutch/releases/download/latest/dutch-core-2.7.0.zip |
| English (reference) | https://github.com/XoopsLanguages/english/releases/download/latest/english-core-2.7.0.zip |
| Finnish | https://github.com/XoopsLanguages/finnish/releases/download/latest/finnish-core-2.7.0.zip |
| French | https://github.com/XoopsLanguages/french/releases/download/latest/french-core-2.7.0.zip |
| Galician | https://github.com/XoopsLanguages/galician/releases/download/latest/galician-core-2.7.0.zip |
| German | https://github.com/XoopsLanguages/german/releases/download/latest/german-core-2.7.0.zip |
| Greek | https://github.com/XoopsLanguages/greek/releases/download/latest/greek-core-2.7.0.zip |
| Gujarati | https://github.com/XoopsLanguages/gujarati/releases/download/latest/gujarati-core-2.7.0.zip |
| Hebrew | https://github.com/XoopsLanguages/hebrew/releases/download/latest/hebrew-core-2.7.0.zip |
| Hungarian | https://github.com/XoopsLanguages/hungarian/releases/download/latest/hungarian-core-2.7.0.zip |
| Italian | https://github.com/XoopsLanguages/italian/releases/download/latest/italian-core-2.7.0.zip |
| Japanese | https://github.com/XoopsLanguages/japanese/releases/download/latest/japanese-core-2.7.0.zip |
| Korean | https://github.com/XoopsLanguages/korean/releases/download/latest/korean-core-2.7.0.zip |
| Malaysian (Malay) | https://github.com/XoopsLanguages/malaysian/releases/download/latest/malaysian-core-2.7.0.zip |
| Norwegian | https://github.com/XoopsLanguages/norwegian/releases/download/latest/norwegian-core-2.7.0.zip |
| Persian (Farsi) | https://github.com/XoopsLanguages/persian/releases/download/latest/persian-core-2.7.0.zip |
| Polish | https://github.com/XoopsLanguages/polish/releases/download/latest/polish-core-2.7.0.zip |
| Portuguese | https://github.com/XoopsLanguages/portuguese/releases/download/latest/portuguese-core-2.7.0.zip |
| Romanian | https://github.com/XoopsLanguages/romanian/releases/download/latest/romanian-core-2.7.0.zip |
| Russian | https://github.com/XoopsLanguages/russian/releases/download/latest/russian-core-2.7.0.zip |
| Chinese (Simplified) | https://github.com/XoopsLanguages/schinese/releases/download/latest/schinese-core-2.7.0.zip |
| Slovenian | https://github.com/XoopsLanguages/slovenian/releases/download/latest/slovenian-core-2.7.0.zip |
| Spanish | https://github.com/XoopsLanguages/spanish/releases/download/latest/spanish-core-2.7.0.zip |
| Swedish | https://github.com/XoopsLanguages/swedish/releases/download/latest/swedish-core-2.7.0.zip |
| Chinese (Traditional) | https://github.com/XoopsLanguages/tchinese/releases/download/latest/tchinese-core-2.7.0.zip |
| Thai | https://github.com/XoopsLanguages/thai/releases/download/latest/thai-core-2.7.0.zip |
| Turkish | https://github.com/XoopsLanguages/turkish/releases/download/latest/turkish-core-2.7.0.zip |
| Ukrainian | https://github.com/XoopsLanguages/ukrainian/releases/download/latest/ukrainian-core-2.7.0.zip |
| Urdu | https://github.com/XoopsLanguages/urdu/releases/download/latest/urdu-core-2.7.0.zip |
| Vietnamese | https://github.com/XoopsLanguages/vietnamese/releases/download/latest/vietnamese-core-2.7.0.zip |

> Tip: every repository also has a `latest` release page at
> `https://github.com/XoopsLanguages/<language>/releases/latest` if you
> prefer to browse assets or changelogs.

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
