how# Statnive — pre-listing translation seeds

This directory holds **draft PO files** for the six priority Statnive locales — `de_DE`, `fr_FR`, `ja`, `zh_CN`, `fa_IR`, `ar`. They are generated from `statnive/languages/statnive.pot` and `statnive/readme.txt` against the per-locale style guides in `jaan-to/docs/research/47–52-*.md`.

**These files are NOT bundled in the WordPress.org plugin ZIP.** They are seeds for upload to translate.wordpress.org via the **Import Translations** UI on each GlotPress sub-project page. See `jaan-to/docs/research/62-i18n-statnive-wordpress-translation-playbook.md` for full background.

## Layout

```
.translations/
├── README.md            ← this file
├── de_DE/
│   ├── statnive-de_DE.po       # Plugin admin strings
│   └── readme-de_DE.po         # Readme.txt strings
├── fr_FR/
├── ja/
├── zh_CN/
├── fa_IR/
└── ar/
```

## How to upload

Each locale has **two** files going to **two** different translate.wordpress.org sub-projects.

### Plugin admin strings

URL pattern: `https://translate.wordpress.org/projects/wp-plugins/statnive/stable/{locale}/default/`

| Locale | Direct link |
|---|---|
| de_DE | https://translate.wordpress.org/projects/wp-plugins/statnive/stable/de/default/ |
| fr_FR | https://translate.wordpress.org/projects/wp-plugins/statnive/stable/fr/default/ |
| ja | https://translate.wordpress.org/projects/wp-plugins/statnive/stable/ja/default/ |
| zh_CN | https://translate.wordpress.org/projects/wp-plugins/statnive/stable/zh-cn/default/ |
| fa_IR | https://translate.wordpress.org/projects/wp-plugins/statnive/stable/fa/default/ |
| ar | https://translate.wordpress.org/projects/wp-plugins/statnive/stable/ar/default/ |

For each row:

1. Open the URL (you must be logged into WordPress.org).
2. Scroll down to the **Import Translations** link.
3. Choose the corresponding file under `statnive/.translations/{locale}/statnive-{locale}.po`.
4. Status: **Waiting** (the only option available unless you have PTE/GTE/CLPTE rights for the project + locale).
5. Click **Import**.

The strings land as 🟨 Waiting until a PTE/GTE reviews and bulk-approves them.

### Readme strings

Same procedure, but the sub-project is **Stable Readme** rather than **Stable**.

URL pattern: `https://translate.wordpress.org/projects/wp-plugins/statnive/stable-readme/{locale}/default/`

Upload `statnive/.translations/{locale}/readme-{locale}.po` per the same steps.

> **Note**: The readme PO entries use synthetic `#:` reference labels (e.g. `readme.txt:faq-q1`) that won't byte-match translate.wordpress.org's auto-generated POT keys. The strings still import as Waiting, but the GlotPress UI may show them as "new" entries rather than auto-merging into existing pre-translated rows. A PTE can re-key as needed during review.

## Upload-as-Current is gated

Per the [Polyglots GlotPress handbook](https://make.wordpress.org/polyglots/handbook/translating/glotpress-translate-wordpress-org/):

> Any user can import plugin and theme translation files, the translations uploaded will have the **"Waiting"** status. PTEs can also choose to upload the translations with the **"Current"** status for the projects they are Translation Editor. GTEs can also import translation files for WordPress core and projects other than plugins and themes, and can choose to upload with the **"Current"** status for all projects.

So the Statnive maintainer can upload **Waiting** today; **Current** requires CLPTE for Statnive (request once the plugin is listed — see research 62 §5.3).

## When to regenerate these files

These seeds are **versioned against `statnive.pot`** at the time they were generated. They should be regenerated whenever:

- `statnive.pot` is regenerated (i.e. after `/statnive-release` or any new translatable string lands)
- A locale glossary entry changes that touches a frequently-recurring term in Statnive (e.g. if the de team decides `Tracking` → `Erfassung`)
- A research file (`jaan-to/docs/research/47–52-*.md`) is updated with corrections

To regenerate: re-run the same 6 parallel sub-agents that originally produced these files. The conversation that generated them is captured in `~/.claude/plans/now-plan-to-create-structured-wigderson.md`.

## What's in the header

Every PO file ships with the canonical WordPress translate.wordpress.org headers:

```po
"PO-Revision-Date: 2026-05-16 10:00+0000\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Plural-Forms: ...\n"
"Language: ...\n"
"Project-Id-Version: Plugins - Statnive - Stable (latest release)\n"
```

Per-locale `Plural-Forms`:

| Locale | Plural-Forms |
|---|---|
| de_DE | `nplurals=2; plural=(n != 1);` |
| fr_FR | `nplurals=2; plural=(n > 1);` |
| ja | `nplurals=1; plural=0;` |
| zh_CN | `nplurals=1; plural=0;` |
| fa_IR | `nplurals=2; plural=(n > 1);` |
| ar | `nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5);` |

The current POT contains **zero** `_n()` plural strings, so the Plural-Forms expression is moot in practice. It becomes load-bearing the first time a `_n()` call enters the codebase — at that point regenerate the POT and re-translate the new plural entries (Arabic alone needs 6 forms).

## Known divergences from strict `→` preservation

The plan's hard rule was "preserve Unicode arrows `→` verbatim". For LTR locales (de_DE, fr_FR, ja, zh_CN) this is enforced — all source `→` arrows are present in msgstrs.

For RTL locales (fa_IR, ar) the arrow was intentionally dropped or rephrased by the translation agents. Rationale: `→` in source breadcrumb strings like `Settings → GeoIP` renders visually left-pointing in RTL text flow, which reads backwards to RTL users. The agents replaced with text equivalents (`>`, `<`, or simply restructured the phrase) or dropped where context made it redundant. This is the correct adaptation; a strict character-preserve rule would produce confusing UI.

## Brand-name policy

Brand names stay Latin across all six locales (verified):

- `Statnive`, `statnive.live`
- `WordPress` (one exception: Persian short description previously used `وردپرسی` adjective form — corrected to `WordPress`)
- `WooCommerce`, `GeoIP`, `MaxMind`, `DB-IP`
- `Google Analytics` (German uses hyphenated compound `Google-Analytics-Alternative` per Duden Rule 41 — still Latin, correctly preserved per research 49)
- `GA4`, `Matomo`, `Plausible`, `MCP`, `LCP`, `API`, `IP`, `KB`, `MB`, `GB`
- `JavaScript`, `TypeScript`, `Cookie` (Latin in admin contexts), `ClickHouse`, `GitHub`, `Node.js`, `npm`, `WP-CLI`

## Validation report (at-time-of-generation)

All 12 files passed `msgfmt -c --statistics` (the two warnings about missing `Last-Translator` and `Language-Team` header fields are cosmetic and resolved automatically when a PTE/GTE imports through the GlotPress UI).

| Locale | Plugin msgids | Readme chunks | msgfmt status |
|---|---|---|---|
| de_DE | 241 | 69 | ✅ clean (after 7 typographic-quote escape fixes) |
| fr_FR | 241 | 87 | ✅ clean |
| ja | 240 | 87 | ✅ clean |
| zh_CN | 241 | 88 | ✅ clean |
| fa_IR | 241 | 84 | ✅ clean |
| ar | 240 | 87 | ✅ clean |

(The "missing 1 msgid" in ja and ar plugin totals refers to the plugin URI msgid `https://statnive.com` which is intentionally left as empty `msgstr ""` — URIs don't translate.)

## Cross-references

- `jaan-to/docs/research/62-i18n-statnive-wordpress-translation-playbook.md` — full WordPress.org translation pipeline reference
- `jaan-to/docs/research/47-statnive-localization-arabic.md` — ar style guide
- `jaan-to/docs/research/48-statnive-localization-persian.md` — fa_IR style guide
- `jaan-to/docs/research/49-statnive-localization-german.md` — de_DE style guide
- `jaan-to/docs/research/50-statnive-localization-japanese.md` — ja style guide
- `jaan-to/docs/research/51-statnive-localization-chinese.md` — zh_CN style guide
- `jaan-to/docs/research/52-statnive-localization-french.md` — fr_FR style guide
