# Swedish (sv / sv_SE) deep translation review — Statnive

Reviewer: senior Swedish-native technical-SaaS reviewer.
Authority: `jaan-to/docs/research/66-statnive-localization-swedish.md`.
Date: 2026-05-16.

Scope:

- `statnive/.translations/sv/statnive-sv.po` (plugin strings, 242 msgid, 240 translated + 1 intentionally empty `Plugin URI` + header)
- `statnive/.translations/sv/readme-sv.po` (readme.txt, 89 strings)

Verdict: high baseline quality. The seed already follows the most consequential rules (typographic å/ä/ö, `du` lowercase, decimal comma, NBSP before `%` and `kr`, no särskrivning of compound nouns, `kakor` over `cookies` in body, en-dash `–` for editorial breaks, IMY not Datainspektionen, no false certifications). P0 issues were narrowly clustered around (a) inconsistent `plugin` vs research-66's `tillägg` canonical and (b) two small native-naturalness slips. All P0 items have been applied. The P1/P2 list below is for follow-up polish and does not block release.

msgfmt: both files compile cleanly. Warnings are limited to the conventional headers `Last-Translator` and `Language-Team` (informational only, not a translation defect; the seed leaves these blank deliberately).

---

## P0 — fixed in this pass (applied directly to PO files)

Each fix maps to a rule in research 66.

### Plugin PO (`statnive-sv.po`)

| # | msgid (English source) | Before | After | Rule | Rationale |
|---|---|---|---|---|---|
| P0-1 | `Statnive: plugin version mismatch detected.` | `Statnive: avvikande pluginversion upptäcktes.` | `Statnive: avvikande tilläggsversion upptäcktes.` | R5 | `tillägg` is the WP.org-sv canonical for admin/error strings; the loanword `plugin` is reserved for SEO long-form, not error messages from `src/Database/Migrator.php`. |
| P0-2 | `Your database schema is at version %1$s but the running plugin is version %2$s. This may happen if you downgraded the plugin. Please re-install the latest version to avoid data inconsistencies.` | `…men pluginet som körs… nedgraderade pluginet.` | `…men tillägget som körs… nedgraderade tillägget.` | R4, R5 | Same; replace loanword definite `pluginet` with the WP.org-sv canonical `tillägget`. |
| P0-3 | `You do not have permission to activate plugins.` | `Du har inte behörighet att aktivera plugin.` | `Du har inte behörighet att aktivera tillägg.` | R5 | Admin permission string mirrors WP.org-sv's `Aktivera tillägg` menu copy. Also `plugin` without article looked truncated. |
| P0-4 | `Plugin Activation Error` | `Fel vid aktivering av plugin` | `Fel vid aktivering av tillägg` | R5 | Same; admin-facing error heading. |
| P0-5 | `Data purge cron not scheduled. Deactivate and reactivate the plugin.` | `…Inaktivera och aktivera pluginet igen.` | `…Inaktivera och aktivera tillägget igen.` | R4, R5 | Admin instruction string; align with `Inaktivera/Aktivera` verbs already used elsewhere in the file. |
| P0-6 | `Statnive: Composer autoloader not found. Please run "composer install" in the plugin directory.` | `…i pluginets katalog.` | `…i tilläggets katalog.` | R5 | Bootstrap failure message; align with WP.org-sv. |
| P0-7 | `No cookies, privacy-first. Designed to support GDPR/CCPA/APPI compliance.` | `Inga kakor, integritet först. Utformad för att stödja…` | `Inga kakor, integritetsvänligt som standard. Utformat för att stödja…` | rubric §B | `integritet först` is a calque of "privacy-first" that reads MT in Swedish. Research-66 §2 canonicalises `integritetsvänlig` for the adjective and `integritet som standard` for "by default". Also fixes the gender-agreement: the noun being modified is `Statnive` (a neuter brand product) so `Utformat`/`integritetsvänligt` (neuter `-t` ending), not `Utformad`/`integritet först`. |
| P0-8 | `Analytics are disabled until you provide explicit consent through our cookie/consent banner. No data is collected before consent is granted.` | `…via vår kak-/samtyckesbanner.` | `…via vår cookie- eller samtyckesbanner.` | rubric §B, R6 | `kak-/samtyckesbanner` is an awkward orthography: it forces the reader to expand to `kakbanner eller samtyckesbanner`, but `kakbanner` is not the Statnive house term (we use `cookiebanner` or `samtyckesbanner`, per research 66 §2). The slash-with-hyphen is also un-Swedish; rule 6 of research 66 says PTS-style PolicyView uses `cookies` in dev/banner UI surfaces. The replacement keeps the dual-banner concept readable. |
| P0-9 | `No visitors with a resolvable country in this period. Approximate country is being derived from each visitor's browser timezone; for precise city-level data, enable DB-IP below or configure MaxMind GeoIP in Settings → GeoIP.` | `…härleds från varje besökares webbläsartidszon.` | `…härleds från varje besökares webbläsares tidszon.` | R2 | `webbläsartidszon` (one word) is a forced 3-noun compound that doesn't appear in any Swedish tech corpus; native is the genitive split `webbläsarens tidszon`. The same string already uses the natural genitive elsewhere in the file, so consistency wins. |

### Readme PO (`readme-sv.po`)

| # | msgid (English source) | Before | After | Rule | Rationale |
|---|---|---|---|---|---|
| P0-10 | `No. Statnive is 100% cookie-free. …` | `Nej. Statnive är 100 % fritt från kakor. …` | `Nej. Statnive är 100 % utan kakor. …` | research-66 §2 row "no cookies / cookieless" | `fritt från kakor` is a calque of "free from cookies"; the PTS- and SERP-canonical idiom is `utan kakor`. Also harmonises with `Cookieless` UI label (`Utan kakor`) in `settings.tsx:158`. |
| P0-11 | `…so the same visitor gets a different hash tomorrow…` | `…får en annan hash imorgon…` | `…får en annan hash i morgon…` | TT-språket / Språkrådet | `imorgon` is widely accepted in informal writing but the TT-språket and Datatermgruppen canonical for product/legal copy is the two-word `i morgon`. Compare `idag` (one word, rule 19 of research 66) which is the established one-word exception. |

Plugin PO: 9 P0 fixes. Readme PO: 2 P0 fixes. msgfmt re-verified clean post-edit.

---

## P1 — recommended polish, not yet applied

These are stylistic improvements that don't block release but would tighten native-naturalness if a second pass is done.

### Plugin PO (`statnive-sv.po`)

| # | Line | Current | Suggested | Rationale |
|---|---|---|---|---|
| P1-1 | 169 | `Statnive kräver PHP %1$s eller högre. Du kör PHP %2$s.` | (unchanged; already idiomatic) | Verify: `eller högre` mirrors WP.org-sv minimum-requirement strings. Confirmed correct. |
| P1-2 | 270 | `Statnive använder inga kakor, localStorage eller sessionStorage – det är inbyggt i designen.` | `Statnive använder inga kakor, localStorage eller sessionStorage – så är det byggt från start.` | `det är inbyggt i designen` reads slightly Anglo (calque of "by design"). The suggested alternative is the native equivalent. Borderline; safe to leave. |
| P1-3 | 405 | `Webbanalysen är inaktiverad tills du ger uttryckligt samtycke via vår cookie- eller samtyckesbanner. Inga uppgifter samlas in innan samtycke har lämnats.` | `Webbanalysen är inaktiverad tills du ger uttryckligt samtycke via vår samtyckesbanner. Inga uppgifter samlas in innan samtycke har lämnats.` | Simpler: `samtyckesbanner` already implies the cookie-consent banner pattern in PTS/IMY Swedish. Source string says `cookie/consent banner` to disambiguate to English readers; Swedish readers don't need the disambiguation. |
| P1-4 | 465 | `Statnives integritetsinställningar kan förbättras. Granska integritetsgranskningen i din kontrollpanel.` | `Statnives integritetsinställningar kan förbättras. Granska integritetsöversikten i kontrollpanelen.` | `integritetsgranskningen` is a noun-of-action ("the integrity reviewing"), while what the user opens is a *report/section* — `integritetsöversikten` is the more product-honest noun. Also drop possessive `din` since context already implies the user's own admin. |
| P1-5 | 487 | `Analysdata sparas för alltid. Överväg att ställa in en lagringstid för att följa principen om dataminimering.` | (consider) `Analysdata sparas tills vidare. …` | `För alltid` is fine but `tills vidare` is the more IMY-flavoured legal register for "indefinite retention" used elsewhere in the file (lines 435, 483). Consistency across the SiteHealth surface would help. |
| P1-6 | 501 | `Lagringstiden är konfigurerad med automatisk rensning.` | (unchanged) | Good. |
| P1-7 | 632, 665, 733, 808, 872 | `…kolla Inställningar → Diagnostik.` | `…kontrollera Inställningar → Diagnostik.` | `kolla` is colloquial. `kontrollera` is the WP.org-sv canonical verb for "check" in product UI. Five occurrences. |
| P1-8 | 805 | `Utgångar / besökare` | `Avhopp / besökare` | "Exits" in funnel-analytics Swedish is often translated `avhopp` (exit/drop-off) rather than the literal `utgångar` ("doors"). However `utgångar` is the GA4-sv canonical for "exit pages" so this is borderline; leave. |
| P1-9 | 1001 | `Spårningsförfrågningar från dessa IP-adresser eller intervall ignoreras – praktiskt för att dölja ditt eget team.` | `Spårningsförfrågningar från dessa IP-adresser eller intervall ignoreras – praktiskt för att utesluta ditt eget team.` | `dölja` reads as "hide" (slightly conspiratorial); `utesluta` is the analytic-exclusion verb. |
| P1-10 | 1045 | `Föredrar du att slippa konto? Använd ettklicksnedladdningen DB-IP IP-to-City Lite på sidan Geografi i stället – den är kostnadsfri och kontolös.` | `Föredrar du att slippa konto? Använd ettklicksnedladdningen av DB-IP IP-to-City Lite på sidan Geografi – kostnadsfri och utan konto.` | Tighter, drops the awkward `kontolös` neologism, drops the redundant `i stället`. |
| P1-11 | 957 | `Inga kakor, integritetsvänligt som standard.` | (just applied as P0-7) | OK. |
| P1-12 | 211 (readme equivalent in plugin PO) | n/a — see readme P1-13 | n/a | — |

### Readme PO (`readme-sv.po`)

| # | Line | Current | Suggested | Rationale |
|---|---|---|---|---|
| P1-13 | 31 | `**Det integritetsvänliga analystillägget för WordPress.**` | (unchanged; idiomatic) | Good. Note: this string uses `analystillägg` (analytics-plugin), which is allowed per research 66 rule 5 (compound with WP.org-sv canonical `tillägg`). |
| P1-14 | 86 | `**Smart kanalgruppering** – Direkt, organisk sökning, sociala medier, e-post, hänvisning, betald sökning, betald social och en dedikerad kanal för **AI-assistenter** med ChatGPT, Claude, Gemini, Perplexity, Copilot, NotebookLM, Meta AI, Le Chat, Deepseek, You, iAsk, Jasper och Writesonic.` | `**Smart kanalgruppering** – Direkt, organisk sökning, sociala medier, e-post, hänvisning, betald sökning, betalda sociala medier och en dedikerad kanal för **AI-assistenter** med ChatGPT, Claude, Gemini, Perplexity, Copilot, NotebookLM, Meta AI, Le Chat, Deepseek, You, iAsk, Jasper och Writesonic.` | `betald social` is an awkward calque of "Paid Social". Native: `betalda sociala medier` (plural matches `sociala medier`). |
| P1-15 | 96 | `**Separation mellan bot och människa** – Riktiga besökare och automatiserad trafik i separata kategorier.` | `**Bottar vs människor** – Riktiga besökare och automatiserad trafik i separata kategorier.` | Shorter, mirrors the `Bot vs Human` headline in the plugin PO (line 645), and avoids the noun `separation` which reads slightly Latinate. |
| P1-16 | 101 | `**Geografi i flera nivåer** – Landmappning från tidszon utan konfiguration, valfria CDN-headrar, valfri ettklicksnedladdning av DB-IP-stad (kostnadsfri) och valfri MaxMind GeoLite2.` | `**Geografi i flera nivåer** – Landmappning från tidszon utan konfiguration, valfria CDN-land-headrar, valfri ettklicksnedladdning av DB-IP-stadsdatabasen (kostnadsfri) och valfri MaxMind GeoLite2.` | `CDN-headrar` is fine but `CDN-land-headrar` matches the more specific phrasing in the plugin PO (line 681). `DB-IP-stad` is a stub noun; `DB-IP-stadsdatabasen` (matching plugin PO line 669) is more readable. |
| P1-17 | 211 | `Spårningsskriptet är litet (cirka 2 kB gzippat) och laddas asynkront, så det blockerar inte sidans rendering. Hit-endpointen skriver en enda rad per sidvisning. Frågor från kontrollpanelen körs mot föraggregerade dagliga sammanställningar i stället för mot råa händelser.` | `Spårningsskriptet är litet (cirka 2 kB gzippat) och laddas asynkront, så det blockerar inte sidans rendering. Hit-anropet skriver en enda rad per sidvisning. Frågor i kontrollpanelen körs mot föraggregerade dagliga sammanställningar i stället för råa händelser.` | `Hit-endpointen` mixes English `Hit-endpoint` with Swedish definite `-en` — readable to devs but awkward in user-facing readme prose. Native: `Hit-anropet` (anrop = call/request). Also drops the redundant `mot` repetition. |
| P1-18 | 161 | (just applied as P0-10) | OK. |
| P1-19 | 181 | (just applied as P0-11) | OK. |
| P1-20 | 251 | `Nej. Cirka 200 mönster av bot-User-Agent på serversidan tillsammans med fingeravtryck på spårarsidan (webdriver, automatiseringsflaggor) placerar bottar i en egen kategori, så ”Besökare” och ”Sidvisningar” speglar bara människor.` | `Nej. Cirka 200 bot-User-Agent-mönster på serversidan tillsammans med fingeravtryck på spårarsidan (webdriver, automatiseringsflaggor) placerar bottar i en egen kategori, så ”Besökare” och ”Sidvisningar” speglar bara människor.` | `mönster av bot-User-Agent` reads MT (calque of "patterns of bot User Agent"). Native compound: `bot-User-Agent-mönster` (chained hyphenation with the brand-initial element pattern). Also more concise. Note: `fingeravtryck` here is OK in context (web fingerprints, not biometric). |
| P1-21 | 296 | `Se din webbplats andas i realtid – aktiva besökare och sidvisningar live` | `Se din webbplats andas i realtid – aktiva besökare och live-sidvisningar` | Caption-style: `live` works in Swedish as a loan adjective, but post-positioning `sidvisningar live` is English calque. Native order is pre-position: `live-sidvisningar`. |
| P1-22 | 391 | `När: Ett enskilt klick av användaren på ”Aktivera geografi på stadsnivå” på sidan Geografi, därefter månadsvis uppdatering via WP-Cron` | (unchanged) | OK. |

---

## P2 — typography / convention notes (low priority)

These are observations, not defects. None blocks release.

- **Quotation marks.** Both PO files consistently use Swedish typographic `”…”` (U+201D U+201D, matching curly close-close) — correct per rule 20 of research 66. ASCII quotes inside `<code>` blocks and inline `code` spans are preserved per rule 33. No mixed-quote pollution found.
- **Em-dash.** All editorial breaks use `–` (U+2013 en-dash with spaces), not `—` (em-dash). Research-66 rule 22 says en-dash with spaces is the Swedish-canonical for editorial breaks; em-dash is uncommon in Swedish prose. The seed already follows this convention.
- **NBSP/thousands separator.** `99 %`, `199 kr` (none of the latter actually appear; SEK is not used in these PO files because Statnive's WP-admin strings don't quote prices), `cirka 80 MB`, `100 %` all use NBSP correctly between number and unit.
- **Decimal comma.** No floating-point numbers in these files. `2 kB gzippat` (readme line 211) uses no decimal, so no comma test triggers.
- **å/ä/ö typography.** Verified — no plain `aa/ae/oe` substitutions in any visible body string.
- **Ellipsis.** Search for `...` in msgstr — found `Sök sidor…` and `Sparar…` and `Aktiverar…` and `Laddar…` all using U+2026, correct per rule 23. No three-period offenders.
- **Plurals.** `data` is used consistently as neuter uncountable (`inga data`, `analysdata`, `besökardata`). No invented native plurals like `datan` (definite singular which would imply countable). Good. Note: the plugin PO uses `inga data tillgängliga` (line 554, `No data available`) — this is acceptable. Some Swedish styleguides prefer `Ingen data tillgänglig` (uncountable agreement) but `inga data` is well attested in IT contexts.
- **`Person` vs `Människa`** for "Human" (vs Bot). Line 614 uses `Människa`. Acceptable. `Person` would also work but `Människa` is more concrete in the bot-detection context (and matches the `Människor` plural in the suggested P1-15 above).
- **`hashen` / `hash`** loanword inflection in line 389 uses Swedish definite `hashen` correctly per rule 4.
- **`headern` / `headrar`** — both forms appear (`DNT-headern`, `CDN-headrar`). Swedish has standardised on these forms; correct.
- **`endpoint` / `Hit-endpointen`** — see P1-17 above; not strictly wrong but heavy on English. Acceptable for dev-flavoured admin UI.
- **`kontolös`** (line 1045) — neologism for "without account"; understandable but not in any Swedish dictionary. See P1-10 for a smoother alternative.
- **`scrolldjup`** (readme line 91) — accepted compound loanword in Swedish UX vocabulary; correct.
- **`statnive-event-*` classes** — kept Latin (correct per rule 33).
- **`SiteHealth`** — currently translated as `Statnive: data sparas tills vidare` etc. (line 483) rather than carrying the WP `Webbplatshälsa` (Site Health) module name. Acceptable since the source string also omits the module reference; the user sees the dashboard widget and recognises context.
- **`integritetsefterlevnad`** — long compound (22 chars in a SiteHealth widget title). Within research-66 rule 27's secondary-CTA limit (30 chars). OK.
- **`MaxMinds` genitive** (readme lines 356, 361) uses Swedish bare-s (no apostrophe) on a Latin proper noun, correct per rule 15.
- **`DB-IP:s` genitive** (readme lines 411, 416) uses Swedish colon-S genitive on an acronym, correct convention.
- **`Statnives`** (multiple) — Swedish bare-s on the brand. No apostrophe. Correct.
- **`statnive-event-*` classes** — kept as code-span backticks; correct per rule 33.
- **Calendar / date format.** No localised dates appear in PO files (all date placeholders are dynamic via `%s`); no rule-19 conflicts.
- **CTA-length budget rule 27.** "Add to exclusions" → "Lägg till i undantag" (17 chars) — under budget. "Configure Retention" → "Konfigurera lagringstid" (22 chars) — at the primary-CTA limit; acceptable. "Review Privacy Settings" → "Granska integritetsinställningar" (33 chars) — over the 30-char secondary-CTA limit but is a section header (`Sidebar Tools` link in SiteHealth), not a button. Acceptable in context but if visual overflow appears, fallback `Granska integritet` (18 chars) would work.

---

## Coverage

- Plugin PO: 240 / 242 strings translated. The 1 untranslated msgid is the `Plugin URI` (`https://statnive.com`), which is intentionally left empty per WP.org convention (the URL itself is shared between locales and not localised). msgfmt count `1 untranslated message` is therefore expected and acceptable.
- Readme PO: 89 / 89 strings translated.

---

## Glossary compliance summary

Research-66 mandates, with audit result:

| Term family | Mandate | Status |
|---|---|---|
| Pronoun | `du` lowercase universal, never `Ni`/`Er`/`Era`/`Ert`/`Eder`/`Du` | OK — zero `Ni`/`Er` forms found. All occurrences are `du`/`din`/`ditt`/`dina`/`dig`. |
| Plugin term | `tillägg` for WP.org-aligned UI; `plugin` only in commercial/SEO long-form | NOW OK — P0-1..P0-6 normalised the 6 admin-string occurrences. The 1 readme `analystillägg` is correct (compound noun, WP.org-aligned). |
| Dashboard term | `kontrollpanel` (Statnive house choice) | OK — all 3 occurrences use `kontrollpanel`. None use the forbidden `instrumentpanel` or `dashboard`-loanword. |
| Cookies term | `kakor` in body, `cookies` only in dev/code | OK — body strings use `kakor`, code-block contents preserved Latin. Mixed pluralisations like `kak-/samtyckesbanner` fixed (P0-8). |
| DPA name | `IMY`, never `Datainspektionen` | OK — zero occurrences of `Datainspektionen`. (IMY itself does not appear since the plugin strings don't reference the Swedish DPA by name.) |
| GDPR | English acronym in body, `dataskyddsförordningen` in formal legal | OK — `GDPR` used throughout. |
| Visitor / pageview / referrer / source / event | `besökare`, `sidvisning`, `hänvisare`, `källa`, `händelse` | OK — all canonical forms used. |
| Revenue | `intäkt` | OK — used in readme FAQ A5. |
| Cookieless | `utan kakor` | OK — `Utan kakor` is the settings label; `kakfritt läge` and `kakfria, hashbaserade arkitekturen` appear in privacy policy. After P0-10, all `cookie-free` framings use `utan kakor`. |
| Consent | `samtycke` | OK. |
| Privacy | `integritet` | OK. |
| Settings / Analytics / Event | `inställningar` / `webbanalys` / `händelse` | OK. |
| Retention | `lagring` / `lagringstid` / `datalagring` | OK — `datalagring` (privacy-policy heading line 431), `lagringstid` (settings), `bevarandetid` not used; consistent with research-66 row "Data retention". |
| Nürnberg | German umlaut spelling preserved | N/A — no occurrences in these PO files. |
| Forbidden hype words (`revolutionerande`, `ultimata`, `magisk`, `next-gen`, `bäst i klassen`) | Reject | OK — none found. |
| Forbidden false-certification claims (`IMY-certifierad`, `PTS-godkänd`, `GDPR-certifierad`) | Reject | OK — none found. The closest claim is `GDPR-kompatibelt` (readme line 166, accurate translation of source `GDPR compliant`). |
| Apostrophe-genitive Englishism (`Statnive's …`) | Reject | OK — every brand-genitive is `Statnives` (bare-s, no apostrophe). |
| Verbose MT-tells (`du kan klicka på…`) | Reject | OK — none found. The CTAs use imperative form per rule 28. |

---

## msgfmt run log

```
$ msgfmt --check --statistics -o /dev/null .translations/sv/statnive-sv.po
.translations/sv/statnive-sv.po:4: warning: header field 'Last-Translator' missing in header
.translations/sv/statnive-sv.po:4: warning: header field 'Language-Team' missing in header
240 translated messages, 1 untranslated message.

$ msgfmt --check --statistics -o /dev/null .translations/sv/readme-sv.po
.translations/sv/readme-sv.po:4: warning: header field 'Last-Translator' missing in header
.translations/sv/readme-sv.po:4: warning: header field 'Language-Team' missing in header
89 translated messages.
```

Result: `clean` (header warnings are informational; the `1 untranslated message` in the plugin PO is the intentional empty `Plugin URI` per WP.org convention).
