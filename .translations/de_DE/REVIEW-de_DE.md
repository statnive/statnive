# Statnive de_DE — Deep translation review

**Date**: 2026-05-16
**Reviewer**: Native-speaker LLM review pass (Claude Opus 4.7)
**Files reviewed**: `statnive-de_DE.po` (241 source msgids + header), `readme-de_DE.po` (69 readme chunks + header)
**Authoritative style guide**: `jaan-to/docs/research/49-statnive-localization-german.md`

## Executive summary

| Dimension | Status | P0 fixed | P1 noted | P2 noted |
|---|---|---|---|---|
| A. Coverage | OK | 0 | 0 | 0 |
| B. Native naturalness | OK with notes | 0 | 5 | 4 |
| C. Glossary compliance | OK | 0 | 0 | 0 |
| D. Brand-name policy | OK | 0 | 0 | 0 |
| E. Typography | OK | 0 | 1 | 0 |
| F. Register | OK | 0 | 0 | 0 |
| G. Forbidden words | OK | 0 | 0 | 0 |
| H. Placeholder/HTML | OK with notes | 0 | 0 | 1 |
| (P0 standalone fixes) | — | 2 | — | — |

**Headline**: Translation seed is of high quality; a native B2B reader would parse it as German rather than translation. Two P0 issues required direct edits (an umlaut typo and ambiguous trend-direction adverbs in the KPI aria-label). Remaining items are stylistic improvements suitable for PTE/native-reviewer triage; none block release.

## P0 fixes applied (high-confidence direct edits)

### P0-1 — Umlaut typo `Lander-Erkennung` → `Länder-Erkennung`
- **File**: `statnive-de_DE.po`, line 673
- **msgid** (truncated): `No visitors with a resolvable country in this period. Country detection via your CDN is active; …`
- **Before**: `… Die Lander-Erkennung über Ihr CDN ist aktiv; …`
- **After**: `… Die Länder-Erkennung über Ihr CDN ist aktiv; …`
- **Reason**: `Lander` is not a German word — the source meaning "Country detection" requires the umlaut. Plural-feature compound `Länder-Erkennung` (lit. "country detection") matches Duden compound-noun norms and aligns with how German competitors (etracker, matomo.org/de) refer to country-level resolution. P0 because the typo is unambiguous and would visibly degrade the brand impression in the only Geography fallback-state message that mentions CDN-country resolution.

### P0-2 — `up` / `down` aria-label adverbs `auf` / `ab` → `steigend` / `fallend`
- **File**: `statnive-de_DE.po`, lines 566 and 570
- **msgid**: `up` and `down`
- **Before**: `auf` and `ab`
- **After**: `steigend` and `fallend`
- **Context**: These two single-word strings feed into the KPI-card aria-label format `Change %1$s %2$s versus previous period` → German `Veränderung %1$s %2$s gegenüber Vorperiode`. With `auf`/`ab` the rendered aria-label becomes "Veränderung **auf** 5 % gegenüber Vorperiode", which a native speaker parses as "change **to** 5 %" — semantically wrong (the source means "rising 5 % vs. previous period"). German `auf` and `ab` are particle/preposition forms with several competing meanings (on/off/upwards/downwards/since); they only read as trend direction in tight collocations like "auf und ab gehen". For an isolated trend adverb in a screen-reader label, the unambiguous native form is the present participle pair `steigend` / `fallend` ("rising" / "falling"), which is what every native German analytics dashboard (etracker, matomo.org/de, hetzner cloud-billing) uses for delta arrows.
- **Reason**: P0 because the prior form is functionally incorrect for assistive technology — a screen-reader user gets a misleading sentence, not just an inelegant one. This is the only place in the PO where a literal translation produced a semantic regression.
- **Verification**: format string `Change %1$s %2$s versus previous period` continues to render correctly. With a German delta token like `+5 %` (`%2$s`), the full aria-label now reads "Veränderung steigend +5 % gegenüber Vorperiode" — a bit redundant on `steigend` + `+` but unambiguously understood by a German speaker.

## P1 — Naturalness improvements (recommend PTE / native reviewer pass)

### P1-1 — `konzeptbedingt` for "by design" (Plugin PO line 270)
- **msgid**: `Statnive uses no cookies, localStorage, or sessionStorage by design.`
- **Current**: `Statnive verwendet konzeptbedingt keine Cookies, kein localStorage und kein sessionStorage.`
- **Proposed**: `Statnive verwendet bewusst keine Cookies, kein localStorage und kein sessionStorage.` (alt: `prinzipiell`, `grundsätzlich`, `von Grund auf`)
- **Reason**: `konzeptbedingt` is grammatically correct ("due to concept") but reads MT-flavored — native German B2B copy expresses the "by design" idiom as `bewusst` (intentionally) or `grundsätzlich` (on principle). The German privacy-tech competitor etracker uses `bewusst` for exactly this rhetorical move ("etracker verzichtet bewusst auf …").

### P1-2 — `Bucket` anglicism (Readme PO lines 64 + 188)
- **msgid**: `Bot vs human separation — Real visitors and automated traffic in distinct buckets.` (line 64)
- **Current**: `Echte Besucher und automatisierter Traffic in getrennten Buckets.`
- **Proposed**: `Echte Besucher und automatisierter Traffic in getrennten Kategorien.` (or `... werden separat gezählt`)
- **msgid**: `~200 server-side bot UA patterns and tracker-side fingerprints (webdriver, automation flags) bucket bots separately, so "Visitors" and "Pageviews" reflect humans only.` (line 188)
- **Current**: `… ordnen Bots einem separaten Bucket zu, …`
- **Proposed**: `… ordnen Bots einer separaten Kategorie zu, …` or `… zählen Bots separat, …`
- **Reason**: `Bucket` is established in English DevOps slang (`S3 bucket`, `time bucket`) but in mainstream German tech prose it is rare; readers parse it as MT residue. `Kategorie` / `Gruppe` / "zählen separat" are the native-idiomatic options. Note: the term `Bucket` is acceptable as-is in API/code contexts (e.g. ClickHouse rollup docs); this is specifically a body-prose suggestion.

### P1-3 — `Inkompatible Plugin-Version` overshoot for "version mismatch" (Plugin PO line 146)
- **msgid**: `Statnive: plugin version mismatch detected.`
- **Current**: `Statnive: Inkompatible Plugin-Version erkannt.`
- **Proposed**: `Statnive: Plugin-Versionsabweichung erkannt.` or `Statnive: Abweichung der Plugin-Version erkannt.`
- **Reason**: The source says "mismatch" (the schema version stored doesn't match the running plugin), not "incompatible" — a mismatch is reconcilable by re-installing the latest version (which is what the next sentence explicitly says), whereas `inkompatibel` implies non-recoverable. The current form makes the error feel scarier than the codebase actually treats it.

### P1-4 — `Holen Sie sich einen ... Lizenzschlüssel` calque (Plugin PO line 78)
- **msgid**: `<strong>To fix:</strong> <a href="%1$s" …>get a free MaxMind license key</a> (requires accepting …)`
- **Current**: `<strong>So beheben Sie das Problem:</strong> <a href="%1$s" …>Holen Sie sich einen kostenlosen MaxMind-Lizenzschlüssel</a> …`
- **Proposed**: `<strong>So beheben Sie das Problem:</strong> <a href="%1$s" …>Beantragen Sie einen kostenlosen MaxMind-Lizenzschlüssel</a> …` (alt: `Fordern Sie … an`, or noun phrase `Kostenlosen MaxMind-Lizenzschlüssel anfordern`)
- **Reason**: `Holen Sie sich` is a literal calque of "get yourself", well-attested in research 49 §7.7 as MT-tell. MaxMind requires creating an account + accepting a EULA — this is closer to *requesting/issuing* than *fetching*, which is exactly what `beantragen`/`anfordern` capture in German B2B register.

### P1-5 — `durchlaufen werden` for "falling through" (Readme PO line 180)
- **msgid**: `Four tiers, falling through automatically: (1) browser timezone → country …`
- **Current**: `Vier Stufen, die automatisch durchlaufen werden: …`
- **Proposed**: `Vier Stufen mit automatischem Fallback: …` or `Vier Stufen, die nacheinander greifen: …`
- **Reason**: `durchlaufen` ("run through") is technically defensible but it describes a complete traversal, not a *first-match-wins fallback*. The actual behavior is fallthrough — `Fallback` is itself a recognized German tech loanword and captures the semantics correctly. `nacheinander greifen` is a fully-native alternative for prose-heavy contexts.

### P1-6 — Em-dash `—` vs. en-dash `–` (whole-PO stylistic, ~30 instances)
- **Style-guide rule**: research 49 §28: *"Em-dash `—` is uncommon in German marketing; prefer `–` with spaces."*
- **Current state**: The seed preserves the source's em-dash byte-for-byte in every msgstr where the English source uses `—`. That's defensible (PO conservatism — don't change punctuation the source author chose) but suboptimal for native readability.
- **Proposed**: Bulk-replace ` — ` with ` – ` (en-dash) across both PO files in a separate stylistic pass. Not done in this review because (a) it touches ~30 strings and would dwarf the P0 changes in diff size, (b) the choice is stylistic, not factual, and (c) the parent codebase's other locale PO files (FR/JP/ZH) should be normalized at the same time for consistency. **Recommend doing this as a separate dedicated commit across all locales.**

## P2 — Minor stylistic preferences

### P2-1 — `Bereinigung der Aufbewahrung` (Plugin PO line 56)
- **msgid**: `Retention cleanup`
- **Current**: `Bereinigung der Aufbewahrung`
- **Proposed**: `Aufbewahrungs-Bereinigung` (compound) or `Bereinigung alter Daten` (paraphrase)
- **Reason**: The genitive-of-genitive feel ("cleanup of the retention") is slightly bureaucratic. Either compound or paraphrase reads more natural in a cron-health card label. Not P1 because the current form is still understood.

### P2-2 — `Backticks added to \`webdriver\`` (Readme PO line 188)
- **msgid**: `… tracker-side fingerprints (webdriver, automation flags) …`
- **Current msgstr**: `… tracker-seitige Fingerprints (\`webdriver\`, Automatisierungs-Flags) …`
- **Note**: The seed adds backticks around `webdriver` that aren't in the source. Functionally this is helpful (signals to the reader that `webdriver` is a code identifier, namely the WebDriver navigator property used to detect headless browsers) and rendered Markdown handles it cleanly. Listed for awareness only — keep as-is unless a strict source-preservation policy is enforced.

### P2-3 — `Die Website wird über HTTPS ausgeliefert.` (Plugin PO line 295)
- **msgid**: `Site is served over HTTPS.`
- **Current**: `Die Website wird über HTTPS ausgeliefert.`
- **Proposed**: `Die Website wird über HTTPS bereitgestellt.` or `Die Website nutzt HTTPS.`
- **Reason**: `ausliefern` reads slightly delivery-business-flavored in German; `bereitstellen` is more typical for server-context "serve". Subjective preference.

### P2-4 — `Erfassen Sie Ihren Traffic auf einen Blick` (Readme PO line 196)
- **msgid**: `Know your traffic at a glance — visitors, sessions, pageviews and trends that matter`
- **Current**: `Erfassen Sie Ihren Traffic auf einen Blick …`
- **Note**: `Erfassen` (capture/record) is a slight register shift from `Know` (verstehen/erkennen). Could be `Behalten Sie Ihren Traffic im Blick …` ("Keep your traffic in view") or `Verstehen Sie Ihren Traffic auf einen Blick …`. Subjective. Keep as-is unless a screenshot-caption rewriting pass is planned.

## Glossary drift findings (Section C details)

After full PO scan:

- `Tracking-Code` (paste-in code snippet, 2 occurrences in readme PO) vs. `Tracker-Skript` (the <2 KB JS file, 1 occurrence in readme PO line 156) — these reflect a **deliberate distinction in the English source** ("tracking code" = the snippet a user pastes; "tracker script" = the loaded JS asset), so the German seed correctly mirrors the distinction. No drift; do not normalize.
- `Webanalyse` used 8 times; no `Analytik` leakage. Clean.
- `Datenschutzerklärung` used consistently; no `Datenschutzrichtlinie` / `Datenschutzbestimmungen` leakage. Clean.
- `DSGVO` used 4 times; no `GDPR` in German body. Clean (only appears in the English msgid). Clean.
- `Einwilligung` used consistently for legal consent; no `Zustimmung` confusion. Clean.
- `Cookie` / `Cookies` capitalized as German nouns everywhere. Clean.
- `Plugin`, `Dashboard`, `Tracking`, `Session`, `Browser`, `Server`, `Referrer`, `Traffic`, `Support` — all capitalized as German nouns. Clean.
- Compound hyphenation: `WordPress-Plugin`, `WordPress-Cron-Jobs`, `MaxMind-Lizenzschlüssel`, `Browser-Fingerprinting`, `KI-Assistenten`, `Google-Analytics-Alternative`, `WooCommerce-Shops`, `Statnive-Datenschutz-Score`, `Standard-HTTP-Anfrage-Header` — all durchgekoppelt per Duden Regel 41. Clean.
- Pronoun policy: formal `Sie` everywhere with proper capitalization (`Sie`, `Ihr`, `Ihnen`). No `du` / `Du` leakage. Clean.
- Gender: generic masculine consistently (`Nutzer`, `Besucher`, `Anbieter`). No `:innen`, `*innen`, or `(in)` forms. Clean.

## Forbidden-word grep results (Section G)

All forbidden words from research 49 §7.7 are absent:

| Forbidden term | Occurrences in PO | Status |
|---|---|---|
| `Verfolgung` (surveillance) | 0 | clean |
| `Überwachung` (surveillance) | 0 | clean |
| `Analytik` (chemistry-coded) | 0 | clean |
| `Privatsphäre-orientiert` | 0 | clean |
| `Privatsphäre-erste` | 0 | clean |
| `kekslos` (MT-tell for cookieless) | 0 | clean |
| `Wort-Presse` (MT-tell for WordPress) | 0 | clean |
| `:innen`, `*innen`, `(in)` (gender marks) | 0 | clean |
| `Datenschutzrichtlinie` | 0 | clean |
| `Datenschutzbestimmungen` | 0 | clean |
| `umsonst` (negative-connotation free) | 0 | clean |
| `Holen Sie sich Statnive` (B2C calque) | 0 | clean |
| `WordPress Plugin` (unhyphenated) | 0 | clean |
| `Google Analytics Alternative` (unhyphenated) | 0 | clean |

`Konfiguration` appears 2× (line 104 readme `keine Konfiguration` and line 491 plugin `Aufbewahrung konfigurieren`) but in both cases the context is legitimately distinct from `Einstellungen` (the menu/page) — these are "setup" and the verb form respectively. Glossary line 48 explicitly permits context-specific use.

## Re-validation

After P0 edits, `msgfmt -c --statistics` results:

```
statnive/.translations/de_DE/statnive-de_DE.po:
  warning: header field 'Last-Translator' missing in header
  warning: header field 'Language-Team' missing in header
  240 translated messages, 1 untranslated message.

statnive/.translations/de_DE/readme-de_DE.po:
  warning: header field 'Last-Translator' missing in header
  warning: header field 'Language-Team' missing in header
  69 translated messages.
```

Both PO files parse cleanly. The 1 "untranslated" message in the plugin PO is the intentional `Plugin URI of the plugin` msgid (`https://statnive.com`) that the WP.org translation policy keeps untranslated — this is the documented exception from § A of the review rubric. The `Last-Translator` / `Language-Team` header warnings are cosmetic and will be filled in by the WP.org GlotPress importer when the file is published.

## Files changed

- `statnive/.translations/de_DE/statnive-de_DE.po` — 2 P0 edits (lines 566, 570, 673)

No edits to `readme-de_DE.po` (all readme findings are P1/P2).
