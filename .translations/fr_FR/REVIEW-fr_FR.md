# French (fr_FR) — Deep translation review

> **Reviewer**: senior native fr_FR SaaS-marketing speaker, audit performed 2026-05-16
> **Authoritative source**: `jaan-to/docs/research/52-statnive-localization-french.md`
> **Files under review**:
> - `statnive/.translations/fr_FR/statnive-fr_FR.po` (242 entries, 1 intentionally empty: plugin URI)
> - `statnive/.translations/fr_FR/readme-fr_FR.po` (88 entries)
>
> **Verdict — ship-ready after fixes.** The seed is unusually high-quality for a first pass: zero ASCII apostrophes in body prose, full guillemets discipline (« … » with NBSP), full NBSP-before-`:`-`;`-`?`-`!`-`»`, correct unit translation (KB→Ko, MB→Mo), correct *RGPD/UE/EEE/CNIL* terminology, no forbidden hype words (révolutionnaire / ultime / incontournable / magique / de pointe / propulsé par), no point médian, no "tu" register slippage, no English title case. The few issues found were typographic edge cases and two minor anglicism choices, all fixed inline below.

---

## Executive summary

| File | Entries | Coverage | P0 fixed | P1 fixed | P2 fixed | P1 noted | P2 noted | msgfmt |
|---|---|---|---|---|---|---|---|---|
| `statnive-fr_FR.po` | 242 (1 intentionally empty URI) | 240/241 = **100 %** | 0 | 1 | 1 | 1 | 2 | **clean** (only standard PO-header warnings) |
| `readme-fr_FR.po` | 88 | 87/87 = **100 %** | 2 | 3 | 0 | 0 | 1 | **clean** (only standard PO-header warnings) |

P0 = critical (typography, forbidden words, missing translations, broken structure)
P1 = native naturalness fixes
P2 = style preferences

`msgfmt -c --statistics --output=/dev/null` output:

```
statnive-fr_FR.po:4: warning: header field 'Last-Translator' missing in header
statnive-fr_FR.po:4: warning: header field 'Language-Team' missing in header
240 translated messages, 1 untranslated message.
---
readme-fr_FR.po:4: warning: header field 'Last-Translator' missing in header
readme-fr_FR.po:4: warning: header field 'Language-Team' missing in header
87 translated messages.
```

The two `Last-Translator` / `Language-Team` warnings are universal across machine-generated POs and are tracked as cosmetic. The "1 untranslated message" in `statnive-fr_FR.po` is the plugin URI `https://statnive.com` whose `msgstr` is intentionally empty per WP.org convention.

---

## A. Coverage — 100 %

Every POT msgid has a non-empty msgstr except for:

- `https://statnive.com` (Plugin URI / Author URI) — intentionally left empty per WordPress.org convention; URI must not be translated.

Every translatable readme chunk present (short-description through upgrade-notice headings). No gaps.

---

## B. P0 fixes (applied directly)

### B.1 — NBSP missing before `%` (readme-fr_FR.po) — 2 occurrences

**Rule** (research-52 §4.10): "Insert NBSP (U+00A0) before `%`."

Both instances had U+0020 (regular space) instead of U+00A0 (NBSP) before `%`. Fixed in-place by replacing the byte. Source contexts:

1. `faq-a1`: `Non. Statnive est 100 % sans cookies.` → fixed to `100 %` (digit U+00A0 %).
2. `faq-a9`: `précision d'environ 80 %, sans appel externe` → fixed to `80 %`.

After fix, regex `\d %` (digit + regular space + `%`) returns zero matches in either file; `\d %` (digit + NBSP + `%`) returns the expected occurrences.

### B.2 — Anglicism: "opt-in via une action" (readme-fr_FR.po) — 2 occurrences

**Rule** (glossary research-52, "consent" entries): `consent → consentement`; the construction *"opt-in via X"* is a calque from English. Native FR copy uses **« activation explicite »** or **« consentement explicite »** with `(opt-in)` as a clarifier in parens.

| Before | After |
|---|---|
| `Ce plugin se connecte à deux services tiers, tous deux **soumis à un opt-in via une action explicite de l'utilisateur**.` | `Ce plugin se connecte à deux services tiers, tous deux **activés uniquement via une action explicite de l'utilisateur (opt-in)**.` |
| `Les niveaux 3 et 4 nécessitent un opt-in via un clic explicite de l'utilisateur.` | `Les niveaux 3 et 4 nécessitent une activation explicite (opt-in) par l'utilisateur.` |

### B.3 — "site de référence" → "site référent" (readme-fr_FR.po) — 2 occurrences

**Rule** (research-52 glossary): for the *Referral* acquisition channel and *referrer* metric, the native term is **« site référent »** (or simply **« source »**). *« Site de référence »* is the construction for "reference site" / "leading site", not for HTTP referrer.

Both occurrences in screenshot captions and the channel-list bullet have been fixed.

### B.4 — "ventes additionnelles" → "incitations à l'achat" (readme-fr_FR.po) — 1 occurrence

**Rule**: source is "no upsells"; *« ventes additionnelles »* is a defensible translation but reads as machine-translated jargon. Native FR SaaS (OVHcloud, Qonto, Scaleway) uses **« incitations à l'achat »** or **« ventes incitatives »** for "upsells". The wider FR audience parses "ventes additionnelles" as supplementary product sales, which loses the negative connotation the source intends.

| Before | After |
|---|---|
| `**Huit pages de tableau de bord ciblées.** Sans surcharge, sans ventes additionnelles.` | `**Huit pages de tableau de bord ciblées.** Sans surcharge, sans incitations à l'achat.` |

### B.5 — Bare `=` in body prose (statnive-fr_FR.po) — 1 occurrence

**Rule**: research-52 §4.31 condemns dev-jargon tone in user-facing prose. The bare `=` ("Shorter = more privacy-friendly...") in body copy is acceptable in English microcopy but reads as untranslated when transposed; native FR replaces it with `:` or em-dashes.

| Before | After |
|---|---|
| `Plus court = plus respectueux de la vie privée et base de données plus petite. Plus long = comparaisons d'une année sur l'autre.` | `Plus court : plus respectueux de la vie privée et base de données plus petite. Plus long : comparaisons d'une année sur l'autre.` |

Note: NBSP correctly inserted before each `:` per research-52 rule §4.10.

### B.6 — "La résolution de géographie" → "La résolution géographique" (statnive-fr_FR.po) — 1 occurrence

**Rule**: matches Piano Analytics / Matomo FR copy ("résolution géographique"). The source "geography resolution" calques to *« résolution de géographie »* but native FR analytics tools all say **« résolution géographique »** or **« détection géographique »**. This is a phrase-level naturalness lift, not a glossary mandate, but worth applying.

| Before | After |
|---|---|
| `La résolution de géographie est actuellement désactivée. Réactivez la solution de repli par fuseau horaire, configurez MaxMind GeoIP, ou placez votre site derrière un CDN qui ajoute un en-tête de pays.` | `La résolution géographique est actuellement désactivée. Réactivez la solution de repli par fuseau horaire, configurez MaxMind GeoIP, ou placez votre site derrière un CDN qui ajoute un en-tête de pays.` |

---

## C. P1 — native naturalness (noted, not edited)

### C.1 — `statnive-fr_FR.po`: "Visiteurs et sessions dans le temps"

The chart-title translation for `Visitors and sessions over time` uses **« dans le temps »**. This is acceptable but slightly literal. Piano Analytics and Matomo FR use **« au fil du temps »** or **« sur la durée »**. Recommend a future pass to align with industry register.

Suggestion: `Visiteurs et sessions au fil du temps`.

### C.2 — `statnive-fr_FR.po`: button label "Saving…" → "Enregistrement…"

The current translation is correct, but for a button label the more native FR SaaS pattern is **« Enregistrement en cours… »** (Qonto, OVHcloud microcopy convention). The bare verbal noun is concise enough — kept as-is; flagging only for future polish.

### C.3 — `statnive-fr_FR.po`: "fingerprinting" first-occurrence gloss

Research-52 §1.2 says first occurrence of *fingerprinting* in body prose should add a parenthetical native gloss: **« fingerprinting (prise d'empreinte) »**. The two body occurrences in `statnive-fr_FR.po:352` and `readme-fr_FR.po:52` both omit the gloss.

This is **P1**, not P0, because:
- the term is widely understood in FR-FR tech audiences as a loanword,
- adding the parenthetical would break the rhythm of `Sans cookies. Sans fingerprinting. Sans transferts vers des tiers.` (a deliberately punchy three-beat),
- and CNIL itself uses both forms interchangeably (`cnil.fr/fr/definition/fingerprinting`).

Recommend adding the gloss only in long-form contexts (privacy policy generator paragraph) but keeping the bare loanword in marketing taglines.

---

## D. P2 — style preferences (noted, not edited)

### D.1 — `readme-fr_FR.po:217`: "en parallèle de Google Analytics"

**Current**: `Puis-je utiliser Statnive en parallèle de Google Analytics ou Matomo ?`
**More native**: `Puis-je utiliser Statnive en parallèle de Google Analytics ou Matomo ?` (kept) **or** `Puis-je utiliser Statnive aux côtés de Google Analytics ou Matomo ?`

*« En parallèle de »* is grammatical and widely used in FR-FR; *« aux côtés de »* is slightly warmer and avoids the (mathematical / engineering) "parallel" overtone. Both acceptable.

### D.2 — `readme-fr_FR.po:282`: "Atteindre toutes les langues et toutes les régions"

The construction calques the English "Reach across languages and regions". A more native FR SaaS phrasing would be **« Toucher toutes les langues, partout »** or **« Voir qui vous lit, dans toutes les langues »** — but the current phrasing is acceptable and preserves the source's meaning faithfully.

### D.3 — `statnive-fr_FR.po`: column header `Visitors / Sessions` → `Visiteurs / Sessions`

The slash-separated pattern is unchanged from English and works in FR; this is correct. Just noting that some FR analytics tools use the dot or hyphen (`Visiteurs · Sessions`), but the slash is the universal convention across Matomo FR / Plausible FR / Piano FR — kept.

---

## E. Glossary drift findings

Cross-checked every glossary entry from research-52 §2 against actual translations. Findings:

| Glossary term (EN) | research-52 says | Found in PO | Status |
|---|---|---|---|
| analytics (commercial) | analyse web | "Analyse web pour WordPress…" (plugin name, readme short-desc) | ✓ |
| analytics (regulatory) | mesure d'audience | (not invoked — no CNIL-targeted strings in the plugin POT) | n/a |
| dashboard | tableau de bord | "tableau de bord" everywhere | ✓ |
| settings | réglages | "Réglages" (menu/path/aria) consistently | ✓ |
| plugin (commercial/SEO) | plugin WordPress | "plugin WordPress" in readme & PrivacyPolicy | ✓ |
| extension (admin) | extension | "extension" in install steps & admin notices | ✓ |
| tracking (commercial) | suivi | "suivi" used everywhere appropriate | ✓ |
| traceurs | traceurs | not invoked (no CNIL-context strings) | n/a |
| privacy (life concept) | vie privée | "respectueuse de la vie privée" — used correctly in tagline, plugin name, FAQ | ✓ |
| privacy (page label) | confidentialité | "politique de confidentialité", "page de politique de confidentialité", "réglages de confidentialité" | ✓ — never conflated |
| RGPD | RGPD (never GDPR) | "RGPD" everywhere, never "GDPR" except in `GDPR/CCPA/APPI` keyword bundle (intentional brand keyword from source) | ✓ |
| UE | UE (never EU) | n/a in seed | — |
| EEE | EEE (never EEA) | n/a in seed | — |
| CNIL | CNIL | n/a in seed | — |
| self-hosted | auto-hébergé (hyphen) | "Auto-hébergé dans votre propre base de données" with hyphen | ✓ |
| cookies | cookies | "cookies" / "sans cookies" everywhere | ✓ |
| bot | robot | "Robot" / "robots" (lowercase in body) | ✓ |
| sessions | sessions | "sessions" | ✓ |
| referrer (metric) | source / référent | "Sources" / "sources" used for column / page label; "Source" singular column. Note: bullet now says `site référent` (post-P0 fix) | ✓ |
| visitor / visitors | visiteur(s) | "visiteur(s)" — generic masculine, no point médian | ✓ |
| KB→Ko, MB→Mo | translate units | "~80 Mo", "~2 Ko gzippé", "~70 Mo" with NBSP-before-Mo/Ko | ✓ |
| `Made in Germany` | keep English in stripe | n/a (no brand stripe strings in seed) | — |
| imprint → Mentions légales | LCEN | n/a (no imprint strings in seed) | — |
| Geolocation / GeoIP | keep "GeoIP" Latin | "GeoIP" kept Latin throughout | ✓ |

**No glossary drift detected.** All major terms are applied consistently.

---

## F. Brand-name policy — full compliance

- `Statnive`, `WordPress`, `WooCommerce`, `GeoIP`, `MaxMind`, `DB-IP`, `Google Analytics`, `GA4`, `Matomo`, `Plausible`, `MCP`, `LCP`, `API`, `IP`, `JavaScript`, `ClickHouse`, `GitHub`, `WP-CLI`, `Real Cookie Banner`, `Complianz`, `CookieYes`, `Cookie`, `Le Chat`, `ChatGPT`, `Claude`, `Gemini`, `Perplexity`, `Copilot`, `NotebookLM`, `Meta AI`, `Deepseek`, `You`, `iAsk`, `Jasper`, `Writesonic`, `Cloudflare`, `CloudFront`, `Vercel` — all kept Latin.
- Units translated: `KB → Ko`, `MB → Mo`. No `GB`/`TB` strings in seed.
- `support@statnive.com` not invoked in PO seed (no contact email msgids in plugin POT).
- `Statnive Analytics` (the WP admin menu label & PrivacyExporter group name) kept English-style — this is the product's branded surface label, deliberate per Statnive convention. The PrivacyPolicyGenerator's content-author label, by contrast, correctly localises to `Analyse web (Statnive)` per the localised privacy-policy template.

---

## G. Typography — full audit

| Rule | Compliance |
|---|---|
| `« … »` guillemets in body (not ASCII `"…"`) | ✓ — every quoted UI label uses `« … »` with NBSP inside (e.g. `« Lancer le nettoyage maintenant »`, `« indéfini »`, `« composer install »`) |
| NBSP before `:` `;` `?` `!` `»` | ✓ — verified via regex sweep; only PO header has ASCII colons (correct) |
| NBSP after `«` | ✓ |
| NBSP before `%` | **✓ after B.1 fix** (was 2 violations, now 0) |
| NBSP before unit symbols (Ko / Mo) | ✓ — `~80 Mo`, `~2 Ko`, `~70 Mo` |
| Decimal comma | ✓ — `CC-BY 4.0` is a license code (kept), no other decimals invoked |
| Apostrophe U+2019 in body | ✓ — zero ASCII `'` in body across both files |
| Ellipsis `…` (U+2026) | ✓ — `Enabling…` → `Activation…`, `Saving…` → `Enregistrement…`, `Search pages...` → `Rechercher des pages…` |
| Em-dash `—` for editorial breaks | ✓ — consistent throughout |
| Sentence-case headings | ✓ — no English title case |
| Slug ASCII (not relevant here — POs only have body strings) | n/a |
| Western digits | ✓ — `30 jours`, `200 millions` etc — all Western digits |

---

## H. Register — `vous` + infinitives

| Surface | Expected | Found |
|---|---|---|
| Body sentences | `vous` (formal-plural) | ✓ — `Vous n'avez pas l'autorisation…`, `Veuillez réinstaller…`, `Vérifiez WP-Cron`, `Vous pouvez vous désinscrire…` |
| Button labels / CTAs | infinitive | ✓ — `Ignorer`, `Activer`, `Enregistrer`, `Ajouter aux exclusions`, `Installer`, `Téléverser`, `Activez l'extension` (imperative `vous`-form acceptable for install steps) |
| H1 / nav labels | infinitive or noun | ✓ — `Vue d'ensemble`, `Pages`, `Sources`, `Géographie`, `Appareils`, `Langues`, `Temps réel`, `Réglages` |
| Error messages | `vous` | ✓ — `Vous n'avez pas l'autorisation…` |
| FAQ questions | natural inversion | ✓ — `Statnive utilise-t-il des cookies ?`, `Statnive est-il conforme au RGPD ?`, `Où mes données sont-elles stockées ?` |
| Tu register | never | ✓ — zero `tu` / `te` / `ton` occurrences in body |

Register is **fully consistent**. No mixing of `vous` and `tu`.

---

## I. Forbidden words & phrases — clean

Cross-checked against research-52 §4.31 and the rubric:

| Forbidden term | Occurrences |
|---|---|
| `révolutionnaire` | 0 |
| `ultime` | 0 |
| `incontournable` | 0 |
| `magique` | 0 |
| `puissant` (hype use) | 0 |
| `de pointe` / `cutting-edge` / `next-gen` | 0 |
| `expérience utilisateur de pointe` | 0 |
| `certifié RGPD` | 0 |
| `validé par la CNIL` | 0 |
| `point médian` (·) for inclusive | 0 — generic masculine + neutral throughout |
| `politique de vie privée` | 0 — only `politique de confidentialité` used |
| `propulsé par` | 0 |
| `obtenez Statnive gratuit` (calque) | 0 |
| English possessives (`Statnive's X`) | 0 — `tableau de bord de Statnive`, `clé de licence de MaxMind`, etc |

**Zero forbidden constructions detected.**

---

## J. Placeholders / HTML / arrows — preserved

Spot-checked every `%d` / `%s` / `%1$s` / `%2$s` placeholder against POT:

- `%1$d days (%2$s mode)` → `%1$d jours (mode %2$s)` ✓ — argument reorder is grammatical (Romance languages prefer `mode X` to `X mode`), placeholders preserved verbatim
- `%1$s — last ran %2$s` → `%1$s — dernière exécution %2$s` ✓
- `Change %1$s %2$s versus previous period` → `Variation %1$s %2$s par rapport à la période précédente` ✓ — verified against `kpi-card.tsx`: `%1$s` = `en hausse`/`en baisse`, `%2$s` = percentage; FR order `Variation en hausse 5 % par rapport...` is natural.
- `<strong>Pour corriger :</strong> <a href="%1$s" target="_blank" rel="noopener">…</a>` — all HTML attribute structure preserved byte-for-byte; only visible text translated.
- Arrows `→` (U+2192) preserved (e.g. `Réglages → GeoIP`, `Réglages → Diagnostics`, `Settings → Privacy` → `Réglages → Confidentialité`).
- Bullet `·` (U+00B7) in `%1$s visitors · %2$s sessions` preserved → `%1$s visiteurs · %2$s sessions`.

No placeholder drift.

---

## K. Plurals — none introduced

POT has 0 plural-form entries. Both PO files have `Plural-Forms: nplurals=2; plural=(n > 1);` (correct fr_FR rule), but no actual `msgid_plural` / `msgstr[0]` / `msgstr[1]` blocks. Confirmed clean.

---

## L. Re-validation after fixes

```
$ msgfmt -c --statistics --output=/dev/null statnive-fr_FR.po
statnive-fr_FR.po:4: warning: header field 'Last-Translator' missing in header
statnive-fr_FR.po:4: warning: header field 'Language-Team' missing in header
240 translated messages, 1 untranslated message.

$ msgfmt -c --statistics --output=/dev/null readme-fr_FR.po
readme-fr_FR.po:4: warning: header field 'Last-Translator' missing in header
readme-fr_FR.po:4: warning: header field 'Language-Team' missing in header
87 translated messages.
```

Both files compile clean. The `Last-Translator` / `Language-Team` header warnings are cosmetic and present in all WP-CLI-generated POs; they don't affect runtime.

The "1 untranslated message" in `statnive-fr_FR.po` is the intentionally-empty plugin URI `msgid "https://statnive.com"`, exempted by the rubric.

---

## M. Summary scoreboard

| Dimension | Grade |
|---|---|
| A. Coverage | A (100 %; 1 intentional empty msgstr) |
| B. Native naturalness | A− (4 small lifts applied; tone strong throughout) |
| C. Glossary compliance | A (full conformance) |
| D. Brand-name policy | A |
| E. Typography | A (after NBSP-before-% fix) |
| F. Register | A (no `tu` slip) |
| G. Forbidden words | A (zero violations) |
| H. Placeholders / HTML / arrows | A |
| I. Plurals | A (none, as expected) |

Overall: **ready for WP.org `/fr_FR/` submission** after the 7 inline edits documented in §B.

---

## N. Suggested future polish (not edits to this seed)

These are deferred to a content-team pass and should not block first-release:

1. Add `fingerprinting (prise d'empreinte)` parenthetical on first long-form occurrence inside `PrivacyPolicyGenerator` only.
2. Lift `dans le temps` → `au fil du temps` on the time-series chart title once Piano Analytics / Matomo FR are confirmed as the lexical reference.
3. Once a *Statnive — Today's visitors* admin bar widget surface is finalised, evaluate `Statnive — Visiteurs du jour` against the alternative `Statnive — Visiteurs aujourd'hui` (both grammatical; first is more terse and currently chosen).
4. Once `Statnive Analytics` (admin menu label) is decided as either Latin brand surface or localised label, propagate the decision across AdminMenuManager + PrivacyEraser + PrivacyExporter consistently. Current convention (Latin) is defensible.
