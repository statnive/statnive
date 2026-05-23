# Dutch (nl_NL) Deep Translation Review — Statnive

**Reviewer role:** senior Dutch (nl-NL) native-speaker translation reviewer.
**Authority:** `jaan-to/docs/research/64-statnive-localization-dutch.md` (Researched Edition).
**Files reviewed:**

- `statnive/.translations/nl/statnive-nl.po` (240 strings translated, 1 intentional URL pass-through)
- `statnive/.translations/nl/readme-nl.po` (89 strings translated)

**msgfmt status (post-fix):**

- `statnive-nl.po` — clean (240 translated, 1 untranslated = intentional `https://statnive.com` Plugin URI pass-through; two header warnings about `Last-Translator` / `Language-Team` are pre-existing and informational, not blockers)
- `readme-nl.po` — clean (89 translated; same two informational header warnings)

**Headline:** translation quality is **exceptionally high**. Coverage is complete, `je`-register is consistent across both files, `AVG`/`EER` correctly replace `GDPR`/`EEA`, no MT-tells (`klik hier`, `leer meer`, `gratis voor altijd`, hype words) leak through, no regulatory overclaim (`AVG-gecertificeerd`, `door de AP goedgekeurd`) appears, all placeholders preserved, and `WordPress-plugin` consistently uses the Polyglots-mandated hyphen. **One P0 naturalness fix** applied (awkward word order in `nothing ever leaves your server`). The rest is P1/P2 polish.

---

## P0 — fixes applied directly

### `readme-nl.po` — naturalness (word order)

| # | Location | Before | After | Reason |
|---|---|---|---|---|
| P0-1 | `readme-nl.po:41` (`description-p2`) | `er verlaat niets ooit je server` | `er verlaat nooit iets je server` | Original word order is grammatically valid but reads as an MT-tell: putting `ooit` after the indefinite pronoun `niets` is stiff and non-idiomatic Dutch. Native Dutch SaaS prose (Mollie, WeTransfer) prefers `nooit + iets` ordering for "never anything." Maps to research § 4 rule 30 (active voice, no bureaucratic prose) and § 7.8 MT-tell list. |

### `statnive-nl.po` — none

No P0 issues found. All glossary terms canonical, all placeholders preserved, all HTML/anchor markup intact, all `AVG`/`EER` conversions correct, no regulatory overclaim.

---

## P1 — naturalness, consistency, glossary preference (logged, not edited)

### P1-1 — Cross-file compound inconsistency: `webanalyse-plugin` vs `analyseplugin`

- **`readme-nl.po:31`** (`description-tagline`) — `**De privacyvriendelijke webanalyse-plugin voor WordPress.**`
- **`statnive-nl.po:345`** (`PrivacyPolicyGenerator.php:56`) — `… een privacyvriendelijke analyseplugin die volledig op onze server draait.`

Per research § 3 (Dutch compound rule, Onze Taal *samenstelling*), native-noun + naturalised-loanword compounds default to solid: `cookiebanner`, `paginaweergave`, `gebruikerservaring`. The solid form `webanalyseplugin` / `analyseplugin` is more native than the hyphenated `webanalyse-plugin`. The two files currently disagree.

**Recommendation:** standardise on solid `webanalyseplugin` in `readme-nl.po:31` to match the existing `analyseplugin` (statnive-nl.po:345) and align with research § 3 native-default. Hyphen reserved for brand + Dutch-tail compounds (`WordPress-plugin`, `Google Analytics-alternatief`) per research.

### P1-2 — `Top-bronnen` / `Top-pagina's` hyphen vs solid

- **`statnive-nl.po:784`** — `Top-bronnen` (Top Sources, dashboard heading)
- **`statnive-nl.po:792`** — `Top-pagina's` (Top Pages, dashboard heading)
- **`readme-nl.po:266`** — `top-bronnen en top-pagina's` (screenshot caption 2)

`top-` as a productive Dutch prefix on native nouns normally takes the solid form: `topbron`, `toppagina`, `topinhoud`. Compare `statnive-nl.po:824`: `Topinhoud` (Top Content) — already solid and correct.

**Recommendation:** standardise on solid form for internal consistency — `Topbronnen`, `Toppagina's`, `topbronnen en toppagina's`. Per Onze Taal, the hyphen is reserved for clarity in ambiguous cases (none here). Matches existing `Topinhoud` precedent in the same file.

### P1-3 — `Naar inhoud` is correct but minimal; consider `Naar de inhoud`

- **`statnive-nl.po:546`** — `msgstr "Naar inhoud"` for `Skip to content` (a11y skip-link).

`Naar inhoud` is attested on `nl.wordpress.org`, but the more frequent native form in Dutch SaaS a11y links is `Naar de inhoud` (with the definite article). Either is correct; `Naar de inhoud` reads slightly more natural in screen-reader output.

**Recommendation:** acceptable as-is; consider `Naar de inhoud` for richer SR output. Low priority.

### P1-4 — Typographic quotes inside body prose

Several msgstr strings reference UI labels using ASCII `"…"` instead of typographic `"…"` (U+201C / U+201D). Per research § 5 rule 22, modern Dutch SaaS standardises on double-curly in body prose; ASCII `"` is reserved for code/JSON/HTML attributes.

Examples (body-prose contexts, not code attributes):

- `statnive-nl.po:96` — `Door hieronder op \"Opruimen nu uitvoeren\" te klikken …` → curly `"Opruimen nu uitvoeren"`
- `statnive-nl.po:279` — `De bewaarmodus staat op \"onbeperkt\" …` → curly `"onbeperkt"`
- `readme-nl.po:226` — `Wat kan \"geen gegevens\" veroorzaken?` → curly `"geen gegevens"`
- `readme-nl.po:251` — `… zodat \"Bezoekers\" en \"Paginaweergaven\" alleen mensen weergeven.` → curly `"Bezoekers"` / `"Paginaweergaven"`
- `readme-nl.po:391` — `… "Geografie op stadsniveau inschakelen" …` → curly

**Recommendation:** convert body-prose references to UI labels to curly quotes (U+201C / U+201D). Faithful to source (the English msgid also uses ASCII straight `"`), but native Dutch typography expects curly in rendered prose. **Note:** if the PO is compiled to MO and rendered into HTML pages where the browser handles ligatures, this is purely typographic. P1 because Mollie / Adyen / Plausible-NL all ship curly here.

### P1-5 — `Verbergen` for `Dismiss` (acceptable; `Sluiten` more idiomatic on WP)

- **`statnive-nl.po:104`** — `msgstr "Verbergen"` for `Dismiss`.

`Verbergen` literally = "hide"; the more common WP admin-notice verb is `Sluiten` ("close") or `Negeren` ("dismiss / ignore"). `nl.wordpress.org` admin notices use `Sluiten` for the X-button label.

**Recommendation:** acceptable. If aligning with WP core admin notice idiom, `Sluiten` is the canonical choice; if conveying "make the notice go away without acting on it," `Negeren` is more accurate. `Verbergen` is understood by all readers.

### P1-6 — `Geen rommel, geen upsells` — `upsells` left as loanword plural

- **`readme-nl.po:71`** — `**Acht doelgerichte dashboardpagina's.** Geen rommel, geen upsells.`

`upsells` is left as the English loanword. Dutch alternatives: `geen upsell-meldingen`, `geen verkooptrucs`, `geen extra verkooppogingen`. Loanword `upsell` exists in NL marketing jargon (Frankwatching, Emerce) but `upsells` is less idiomatic for WP-plugin context (sounds like SaaS jargon transplant).

**Recommendation:** consider `geen extra verkoop` or `geen verkoopdrang` for the WP.org plugin-directory audience. Acceptable as-is; tech-savvy buyers parse `upsells` fine.

### P1-7 — `instappagina's` / `uitstappagina's` for `Entry/Exit Pages`

- **`statnive-nl.po:828`** — `Instappagina's` (Entry Pages)
- **`statnive-nl.po:832`** — `Uitstappagina's` (Exit Pages)

These are correct compounds but the analytics-standard Dutch terms used by Google Analytics NL, Plausible NL, and Matomo NL are `Landingspagina's` (Entry / Landing pages) and `Uitstappagina's` (Exit pages). `Instappagina's` is less common; `Landingspagina's` is the SERP-dominant term for the "page visitors arrive on."

**Recommendation:** consider `Landingspagina's` for Entry Pages if matching GA4-NL / Matomo-NL vocabulary; current `Instappagina's` is internally consistent with the source's `Entries / Visitors` framing (line 800 = `Instappen / Bezoekers`) and is acceptable. Keep `Uitstappagina's` (already canonical).

### P1-8 — ASCII apostrophe in plurals like `pagina's`, `regio's`, `dashboardpagina's`

Multiple instances of ASCII straight `'` (U+0027) in Dutch apostrophe-s plurals:

- `pagina's` (lines 321, 329, 586, 792, 812, 816, 820, 828, 832, 848, 852 in `statnive-nl.po`; 81, 266, 276 in `readme-nl.po`)
- `regio's` (line 985, `statnive-nl.po`; line 291, `readme-nl.po`)
- `dashboardpagina's` (line 71, `readme-nl.po`)
- `query's` (line 211, `readme-nl.po`)

Per research § 5 rule 23: "typographic `'` (U+2019)" for genitives and contractions; Dutch plural-apostrophe (`API's`, `cd's`, `iglo's`) also typographically prefers U+2019.

**Recommendation:** typographically these should be U+2019 (`'`) instead of ASCII `'`. Pragmatic note: many MT pipelines and PO editors auto-emit ASCII `'`; modern Dutch readers don't see it as an error. P1 typography preference.

### P1-9 — `Bezig met inschakelen…` / `Bezig met opslaan…` — verbose but native

- **`statnive-nl.po:697`** — `Bezig met inschakelen…` for `Enabling…`
- **`statnive-nl.po:937`** — `Bezig met opslaan…` for `Saving…`

Mollie / Adyen-NL precedent in spinner labels favours the shorter present-progressive form: `Inschakelen…`, `Opslaan…`. `Bezig met X…` is grammatical and explicit but reads slightly more like a Windows dialog than a modern web SaaS spinner.

**Recommendation:** consider tightening to `Inschakelen…` / `Opslaan…` to match Mollie-NL pattern. Acceptable as-is.

### P1-10 — `Real Cookie Banner` (third-party product name) untranslated

- **`statnive-nl.po:969`** — `Werkt met Real Cookie Banner, Complianz, CookieYes of elke andere WordPress Consent API-plugin.`

`Real Cookie Banner` is a third-party WP-plugin brand name and stays English. ✓ correct per do-not-translate list (research § 5). No fix needed; flagging for explicit confirmation against research § 5 brand policy.

---

## P2 — micro-style polish (informational)

### P2-1 — ASCII ellipsis preserved from msgid

- **`statnive-nl.po:820`** — `Pagina's zoeken...` (source `Search pages...` uses ASCII `...`)

Both `…` (U+2026) and `...` appear across the file. The translator faithfully preserved the msgid's ASCII `...`. Per research § 5: typographic `…` preferred in body prose. This is a **source-side issue** (the POT msgid uses ASCII `...`), not a translator-side issue. If the POT is corrected to `Search pages…`, the PO will need a corresponding update.

### P2-2 — `100%` no-space variant

- **`readme-nl.po:161`** — `Statnive is 100% zonder cookies.`

Research § 4 rule 26 (units): split rule — space `99 %` in body prose, no-space `10%` in dense dashboard cells. The body-prose Q&A context here marginally favours `100 %` with space. Both forms are accepted by Onze Taal vs WP Polyglots respectively. **No fix needed** — the current `100%` form is supported by WP Polyglots stijlgids.

### P2-3 — `versus` for `vs.`

- **`statnive-nl.po:644`** — `Bot vs. mens` for `Bot vs Human`.

Correct Dutch idiom uses `vs.` with the period. Translator uses `vs.` ✓. No fix needed; flagging that research § 3 (table of key sentences) explicitly recommends `vs.` form.

### P2-4 — `Gem. duur` abbreviation for `Avg Duration`

- **`statnive-nl.po:776`** — `Gem. duur` for `Avg Duration`.

`Gem. duur` is the canonical Dutch dashboard abbreviation (Plausible-NL, Matomo-NL, Mollie-NL all use `Gem.` for `Gemiddelde`). ✓ Correct.

### P2-5 — `geüploade` correctly diacriticed

- **`readme-nl.po:191`** — `… alle tabellen en geüploade GeoIP-bestanden …`

The trema on `geüploade` is correctly preserved (research § 4 rule 24, diacritic preservation). ✓ Good.

### P2-6 — `Aangepaste gebeurtenissen` for `Custom events`

- **`readme-nl.po:91`** — `Aangepaste gebeurtenissen en betrokkenheid`

Native; matches research § 2 glossary `event/events → gebeurtenis/gebeurtenissen`. `engagement → betrokkenheid` is the canonical Dutch term used by frankwatching.com and DDMA. ✓ Good.

### P2-7 — `salt rouleert` vs `salt roteert`

- **`readme-nl.po:181`** — `De salt rouleert dagelijks`
- **`statnive-nl.po:48`** — `Dagelijkse saltrotatie`
- **`statnive-nl.po:252`** — `Dagelijkse saltrotatie actief`

Mixed verb form: `rouleert` (verbal) and `rotatie` (noun) — both correct but root differs. `Roteert` is also valid. Native crypto-domain Dutch prefers `roteren` for cryptographic key/salt rotation (`sleutelrotatie`, `saltrotatie`). Verb `rouleren` carries a slightly different sense (rotation of duty/role). Internal consistency would prefer `De salt roteert dagelijks` to match `saltrotatie`. Minor; both forms are understood.

### P2-8 — `respect aan de serverkant` ordering

- **`readme-nl.po:171`** — `… en respect aan de serverkant voor GPC- en DNT-signalen.`

Reads slightly stiff; alternative: `respecteert GPC- en DNT-signalen aan de serverkant`. Acceptable; meaning is fully clear.

### P2-9 — `serverkant` vs `server-side` / `aan de serverzijde`

- **`readme-nl.po:61`** — `aan de serverkant` for `server-side`
- **`readme-nl.po:171`** — `aan de serverkant`
- **`readme-nl.po:251`** — `aan de serverkant`

Native: `aan de serverkant` is correct. Alternative: `serverzijde`. Both attested in NL tech press. Internal consistency is good. ✓

### P2-10 — `automatiseringsindicatoren` for `automation flags`

- **`readme-nl.po:251`** — `(webdriver, automatiseringsindicatoren)`

Translates `automation flags` to `automatiseringsindicatoren` — meaning preserved, slightly verbose. Native Dutch dev jargon uses `automation flags` directly as a loanword phrase in security/anti-bot contexts. Acceptable; the Dutch coinage is also understood.

---

## Glossary compliance audit (rubric § C)

Spot-checks against research 64 § 2 priority glossary:

| Term | Required | Used | Status |
|---|---|---|---|
| pronoun | `je` informal | `je`, `jou`, `jouw` consistent across both files; no `u` drift | ✓ |
| plugin | `plugin` loanword | `plugin` (singular), `plugins` (plural), `WordPress-plugin` hyphenated | ✓ |
| dashboard | Latin loanword | `dashboard`, `Dashboardnavigatie`, `dashboardpagina's` | ✓ |
| bezoeker | native | `bezoeker`, `bezoekers`, `bezoekersgegevens` | ✓ |
| paginaweergave | native compound | `paginaweergave`, `paginaweergaven` | ✓ |
| verwijzer | native | `verwijzer`, `verwijzers` (statnive-nl:877, readme-nl) | ✓ |
| bron | native | `bron`, `bronnen`, `top-bronnen` (P1-2: prefer `topbronnen` solid) | ✓ |
| omzet | native | `omzet`, `WooCommerce-omzet`, `omzet per bezoeker (RPV)` | ✓ |
| AVG | NL not GDPR | `AVG` in 4 body-prose contexts; no GDPR in msgstr | ✓ |
| AP | first-use expansion | not used in PO (no AP mention) | n/a |
| cookie | Latin | `cookie`, `cookies` plural; `Cookies en tracking` heading | ✓ |
| toestemming | native | `toestemming`, `toestemmingsmodus`, `toestemmingsbanner` | ✓ |
| privacy | Latin | `privacy`, `privacyvriendelijk` (one solid word), `privacyverklaring`, `privacyregels`, `privacynaleving`, `privacy-instellingen` | ✓ |
| privacyverklaring | AP canonical | `Privacyverklaring`, `privacyverklaringspagina` | ✓ |
| webanalyse | native | `webanalyse`, `webanalyse-plugin` (P1-1: prefer solid) | mostly ✓ |
| gebeurtenis | native | `gebeurtenis`, `gebeurtenissen` (readme-nl:91 `Aangepaste gebeurtenissen`) | ✓ |
| sessie | native | `sessie`, `sessies`, `analysesessies` | ✓ |
| gegevens | legal/native | `gegevens`, `persoonsgegevens` (never `persoonlijke gegevens`), `bezoekersgegevens`, `gegevensaggregatie`, `gegevensminimalisatie` | ✓ |
| inschakelen / uitschakelen | native | `inschakelen`, `uitschakelen` consistent | ✓ |
| bewaartermijn | native | `bewaartermijn` consistent (5+ occurrences) | ✓ |
| zelfgehost | native solid | `Zelfgehost`, `zelfgehost` | ✓ |
| cookieloos / zonder cookies | native | `Zonder cookies` (preferred per research SERP-rank); also `zonder cookies` in prose | ✓ |
| tracking (noun, loanword) | acceptable in body | `Tracking`, `trackingscript`, `trackingverzoeken`, `trackingcode` | ✓ |
| bijhouden (neutral verb) | preferred for analytics-collection | `bijhouden`, `bijgehouden` (readme:84, 91; statnive:266) | ✓ |
| EER | NL not EEA | not used in PO (no EU/EEA mention) | n/a |
| `WordPress-plugin` hyphen | mandatory | always hyphenated where compounded with English `plugin` | ✓ |
| Made in Germany | dual policy | not used in PO (brand-stripe phrase only on website) | n/a |

**Glossary compliance: pass.** No violations of required AVG/EER/AP/persoonsgegevens canonical terms.

---

## Brand-name policy audit (rubric § D)

Per research § 5 do-not-translate list:

- `Statnive` — preserved Latin everywhere ✓
- `statnive.live` — not appearing in PO; no risk ✓
- `WordPress` — never translated ✓ (always `WordPress` Latin)
- `WooCommerce` — preserved Latin ✓ (line 196 readme: `WooCommerce-winkels`, `WooCommerce-omzet`)
- `Google Analytics` — preserved Latin, hyphenated with Dutch tail: `Google Analytics-alternatief` ✓
- `Matomo`, `GA4` — preserved Latin ✓
- `GitHub` — preserved Latin ✓
- `ClickHouse`, `MCP`, `LCP`, `API`, `SaaS`, `JSON`, `Docker`, `Hetzner`, `Astro`, `ChatGPT`, `Claude`, `Gemini`, `Perplexity`, `Copilot`, `NotebookLM`, `Meta AI`, `Le Chat`, `Deepseek`, `You`, `iAsk`, `Jasper`, `Writesonic` — all preserved Latin ✓
- `Real Cookie Banner`, `Complianz`, `CookieYes` — third-party plugin names preserved Latin ✓
- `MaxMind`, `GeoLite2`, `DB-IP`, `IP-to-City Lite` — preserved Latin with NL-style hyphen (`MaxMind-licentiesleutel`, `MaxMind-account`) ✓
- `Cloudflare`, `CloudFront`, `Vercel` — preserved Latin ✓
- `WP-CLI`, `WP-Cron`, `wp-cron.php`, `wp-content/uploads/statnive/` — preserved verbatim ✓

**Brand-name compliance: pass.** No untranslated brands rendered into Dutch, no Dutch endings inflected onto brand names.

---

## Placeholder / HTML / arrow preservation (rubric § H)

Programmatic check (regex-based) of all `%1$s`, `%2$s`, `%d`, `%s`, `<strong>`, `<a href="…">`, `<code>`, `→` tokens across both PO files:

```
statnive-nl.po: 0 placeholder mismatches
readme-nl.po:   0 placeholder mismatches
```

Spot-verified the most complex strings:

- `statnive-nl.po:78` — `<strong>Zo los je dit op:</strong> <a href="%1$s" target="_blank" rel="noopener">…</a> … <a href="%2$s" …>GeoLite2-EULA</a>) en plak deze in Instellingen → GeoIP.` → `%1$s`, `%2$s`, both `<a href>` pairs, `<strong>` pair, `→` arrow, `<code>` tokens — all preserved ✓
- `statnive-nl.po:92` — `<strong>Zo los je dit op:</strong> … <code>wp-cron.php</code> … <code>%1$s</code> … <code>%2$s</code> via WP-CLI.` ✓
- `statnive-nl.po:152` — `%1$s` / `%2$s` for schema/plugin version ✓
- `statnive-nl.po:561` — `%1$s %2$s` for KPI change indicator ✓
- `statnive-nl.po:891` — `%1$s bezoekers · %2$s sessies` ✓
- `readme-nl.po:241` — `→` arrows preserved in `browsertijdzone → land` ✓

**Placeholder integrity: pass.**

---

## Plurals & verb agreement (rubric § I)

Plural-Forms header: `nplurals=2; plural=(n != 1);` matches WP NL standard ✓.

Spot-checks:

- `%d active visitors` → `%d actieve bezoekers` ✓ (plural form correct; singular handled separately if needed)
- `%d analytics session(s)` → `%d analysesessie(s)` ✓ (parenthetical "(s)" preserved verbatim from msgid)
- `%s active visitors` (string-templated) → `%s actieve bezoekers` ✓
- `%1$s visitors · %2$s sessions` → `%1$s bezoekers · %2$s sessies` ✓

No `ngettext` plural-form entries in either file (no `msgid_plural`); all plurals are handled via printf-style placeholders and direct English `(s)` suffixes carried verbatim. Consistent with current Statnive practice. ✓

---

## MT-tells & hype check (rubric § B + § G)

| MT-tell / hype term | Found in msgstr? |
|---|---|
| `klik hier` (calque "click here") | not found ✓ |
| `leer meer` (calque "learn more") | not found ✓ |
| `gratis voor altijd` (calque "free forever") | not found ✓ |
| `revolutionair`, `de ultieme`, `magisch`, `next-gen`, `state-of-the-art`, `baanbrekend` | not found ✓ |
| `AVG-gecertificeerd`, `door de AP goedgekeurd`, `AP-gecertificeerd` | not found ✓ |
| `WordPress plugin` (with bare space, *onjuist spatiegebruik*) | not found ✓ |
| `privacy beleid` (with space) | not found ✓ |
| `cookie banner` (with space) | not found ✓ |
| `GDPR` / `EEA` in msgstr | not found ✓ (only in msgid source) |
| `persoonlijke gegevens` on legal pages | not found ✓ (always `persoonsgegevens`) |
| Anglicism `feature` for `functie` | not found in plugin context ✓ |

**MT-tell / hype audit: pass.**

---

## Register (rubric § F)

- All msgstr use `je` / `jou` / `jouw` consistently:
  - `statnive-nl.po:152` — `als je de plugin hebt teruggezet…`
  - `statnive-nl.po:156` — `Je hebt geen rechten…`
  - `statnive-nl.po:169` — `Je gebruikt PHP…`
  - `statnive-nl.po:445` — `Jouw rechten`
  - `statnive-nl.po:449` — `je kunt … aan jouw account zijn gekoppeld`
  - `statnive-nl.po:1005` — `Jouw huidige IP-adres`
- Zero `u` / `uw` occurrences in msgstr (no register drift)
- Imperatives used for CTAs: `Opslaan`, `Verbergen`, `Inschakelen`, `Toevoegen aan uitsluitingen`, `Open Instellingen`, etc.

**Register: pass.** Matches research § 4 rule 1 (informal `je` everywhere outside legal-contract surfaces; PO files contain no Algemene voorwaarden / Verwerkersovereenkomst material so no `u` is expected here).

---

## Summary

The Dutch translation is **publication-ready** with only one applied P0 naturalness fix and a handful of P1/P2 polish suggestions. All authoritative requirements from research 64 are met: `je`-register, AVG/EER substitution, `WordPress-plugin` hyphen, `persoonsgegevens` (never `persoonlijke gegevens`), no regulatory overclaim, no MT-tells, no hype, all placeholders intact, all brand names Latin.

The remaining P1 items are consistency tightening (compound forms, top- prefix solid vs hyphen, typographic quotes) and the P2 items are aesthetic micro-polish (ellipsis, spinner verbosity, salt verb choice). None of these block WordPress.org release or Dutch user comprehension; addressing them moves the file from "very good" to "indistinguishable from a native Dutch SaaS technical writer."

---

## Final tally

- **Plugin PO** (`statnive-nl.po`) — P0 = 0 fixed, P1 = 8 noted, P2 = 8 noted; msgfmt = clean
- **Readme PO** (`readme-nl.po`) — P0 = 1 fixed, P1 = 4 noted (cross-file P1s already counted above; only file-specific items listed here), P2 = 2 noted; msgfmt = clean
