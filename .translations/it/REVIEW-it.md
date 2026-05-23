# Italian (it / it_IT) Deep Translation Review

> Reviewer: Senior native-speaker Italian SaaS translation reviewer
> Files reviewed: `statnive-it.po` (241 strings), `readme-it.po` (89 strings)
> Authority: `jaan-to/docs/research/65-statnive-localization-italian.md`
> Reference: `jaan-to/docs/research/62-i18n-statnive-wordpress-translation-playbook.md`
> Date: 2026-05-16
> Rubric: 9 dimensions (A Coverage, B Naturalness, C Glossary, D Brand, E Typography, F Register, G Hype, H Placeholders, I Plurals)

---

## TL;DR

The Italian seed is **high quality** overall: coverage is 100 % (241/241 plugin, 89/89 readme), formal `Lei` register is consistently applied with proper capitalisation of `Suo` / `Sua` / `Suoi` / `Sue`, glossary discipline is strong (`plugin` invariant plural, `visualizzazione di pagina`, `tracciamento`, `senza cookie`, `conservazione`, `sorgente`, `informativa sulla privacy`, `UE`/`SEE`, `Norimberga`, `GDPR` not `RGPD`, `dashboard` masculine per research 65 rule 23, typographic apostrophe `’` U+2019 used in every elision), and msgfmt is clean on both files.

Two P0 issues were caught and fixed directly in-place:

1. **`statnive-it.po` L152** — `è stata fatta una downgrade del plugin` → `ha installato una versione precedente del plugin`. Two-fold bug: wrong gender (`downgrade` is masculine in Italian loanword convention — `il downgrade`, never `la downgrade`) AND an awkward passive calque (`è stata fatta una downgrade` ≈ "a downgrade has been made"). The native formal-Lei fix swaps the passive for an active formal verb and dodges the gender question entirely.
2. **`readme-it.po` L191** — `a meno che non Lei attivi esplicitamente` → `a meno che Lei non attivi esplicitamente`. Italian syntax requires the expletive `non` to follow the subject inside `a meno che` clauses, not precede it. The original word order is ungrammatical.

Eight P1 items (style / consistency drift) and four P2 items (minor preference) are logged below. None block release.

---

## Headline counts

- **Plugin PO** (`statnive-it.po`)
  - Strings translated: 241 / 241 (100 %)
  - P0 fixed: 1
  - P1 noted: 5
  - P2 noted: 2
  - msgfmt: clean (standard `Last-Translator` / `Language-Team` header warnings only — present in every seed PO and ignored by WP.org)

- **Readme PO** (`readme-it.po`)
  - Strings translated: 89 / 89 (100 %)
  - P0 fixed: 1
  - P1 noted: 3
  - P2 noted: 2
  - msgfmt: clean (same standard warnings)

---

## A. Coverage — pass

Both PO files mirror the POT 1-to-1 with no untranslated `msgstr ""`. The React/TypeScript string block (lines 507–1045 of `statnive-it.po`) is fully populated alongside the PHP-source strings.

## B. Native naturalness — strong, with minor drift

The prose reads as a native Italian B2B-SaaS technical writer would write it. The translator chose idiomatic constructs over literal calques throughout:

- `Per quanto tempo le statistiche vengono conservate prima della cancellazione` (L989) — idiomatic, not the calque `Per quanto le statistiche sono tenute`.
- `Comportamento di Statnive` (L72) for "What Statnive will do" — idiomatic, not the calque `Cosa Statnive farà`.
- `nessun ingombro, nessuna proposta di upselling` (readme L71) for "No clutter, no upsells" — natural, not literal.
- `pre-aggregati` (readme L211) for "pre-aggregated" — Italian dev idiom.
- `respirare in tempo reale` (readme L296) for "breathe in real time" — preserves the metaphor without calque.

The forbidden MT-tells (`clicca qui`, `Statnive's dashboard`, `ottieni Statnive gratis`, `propulsato da`, `rivoluzionario`, `all'avanguardia`, `il migliore`, `next-gen`, `leader del settore`, `certificato GDPR`) are **all absent**.

P1 drift logged below.

## C. Glossary compliance — strong

| Term | Required | Present | Status |
|---|---|---|---|
| `plugin` (invariant plural) | yes | yes — `i plugin`, never `plugins` | OK |
| `dashboard` (Statnive product) | yes (masculine) | yes — `il dashboard`, `nel Suo dashboard` (L465) | OK |
| `Bacheca` only for wp-admin home | yes | absent here (no wp-admin home reference); correct restraint | OK |
| `visitatore` / `visitatori` | yes | yes throughout | OK |
| `visualizzazione di pagina` (pageview) | yes | yes (L771, L856, readme L211 etc.) | OK |
| `referrer` (loanword for dev contexts) | acceptable | yes (L590, L876) — keeps loanword in admin/dev tone | OK |
| `sorgente` (source) | yes | yes (L738, L784, L884) | OK |
| `fatturato` (revenue) | yes for Statnive (e-commerce-leaning) | yes (readme L201) — `tracciamento dedicato del fatturato WooCommerce con il fatturato per visitatore (RPV)` | OK |
| `cookie` (Latin universal, invariant plural) | yes | yes — `senza cookie`, never `senza cookies` | OK |
| `senza cookie` (cookieless) | yes | yes (L266, L949, readme L21, L56, L80, L101, L161, L957) | OK |
| `consenso` (consent) | yes | yes (L180, L186, L190, L397, L960) | OK |
| `privacy` (Latin loanword) | yes | yes throughout | OK |
| `Informativa sulla privacy` (privacy policy page) | yes | yes (L232, L240, readme L356, L416, L436) | OK |
| `impostazioni` (settings) | yes | yes (L190, L463, L925, etc.) | OK |
| `analisi web` (analytics, dominant) | yes | yes (L17, L28, L341, L345, readme L16, L21) — leads the brand stripe | OK |
| `evento` (event) | yes | yes (readme L91, L201) | OK |
| `sessione` (session) | yes | yes (L309, L313, L534, L880, etc.) | OK |
| `dati` (data) | yes | yes throughout | OK |
| `attiva` / `disattiva` (enable/disable) | yes | yes (L317, `Disattivi e riattivi`, etc.) | OK |
| `conservazione` (retention) | yes | yes (L56, L218, L223, L228, L431, etc., readme L106, L432) | OK |
| `UE` not `EU`; `SEE` not `EEA` | yes | N/A — no UE/SEE references in current corpus; no `EU`/`EEA` leaks either | OK |
| `Norimberga` for Nuremberg | yes | N/A — no Nuremberg reference in current corpus | OK |
| `GDPR` not `RGPD` | yes | yes (L957, readme L56, L165, L170) — `RGPD` does not appear | OK |
| `Garante per la protezione dei dati personali` | yes | N/A — no Garante reference in current corpus | OK |
| `Codice della Privacy` | yes | N/A — no national-statute reference in current corpus | OK |
| `Linee guida cookie 10 giugno 2021` | yes | N/A — no Garante linee-guida reference in current corpus | OK |

## D. Brand-name policy — pass

`Statnive`, `WordPress`, `GitHub`, `GDPR`, `CCPA`, `APPI`, `PIPL`, `WP-Cron`, `WP-CLI`, `MaxMind`, `GeoIP`, `GeoLite2`, `DB-IP`, `MaxMind GeoLite2-City`, `WooCommerce`, `Google Analytics`, `Matomo`, `Cloudflare`, `CloudFront`, `Vercel`, `ChatGPT`, `Claude`, `Gemini`, `Perplexity`, `Copilot`, `NotebookLM`, `Meta AI`, `Le Chat`, `Deepseek`, `You`, `iAsk`, `Jasper`, `Writesonic`, `Real Cookie Banner`, `Complianz`, `CookieYes`, `Composer`, `localStorage`, `sessionStorage`, `User-Agent`, `webdriver`, `connect-src 'self'`, `DISABLE_WP_CRON`, `CC-BY-4.0`, `SHA-256`, `RPV`, `DNT`, `GPC` — all preserved verbatim in Latin script. ✓

## E. Typography — strong

- **Apostrophe direction.** All elisions use typographic `’` (U+2019): `L’intestazione` (L198, L202), `l’hashing` (L248), `dall’hash` (L389), `L’IP` (L389), `un’intestazione` (L681), `L’IP` (L389), `un’unica vista` (readme L286), `un’azione esplicita` (readme L306), `nell’architettura` etc. **Zero ASCII apostrophes in body prose** — the only ASCII `'` in either file sits inside the CSP literal `` `'self'` `` (readme L231), where it must stay ASCII because CSP grammar mandates it.
- **Truncated articles.** `un’amica`-style feminine elisions correctly apostrophised: `un’unica vista` (readme L286), `un’azione esplicita dell’utente` (readme L306), `un’intestazione di paese` (statnive-it L681). Masculine `un` without apostrophe: `un nuovo heartbeat` (L96), `un solo clic` (L693). All correct.
- **Accents.** `è` (verb), `é` not seen but no `perché` / `affinché` / `né` constructions appear in either corpus; `à`, `ò`, `ù`, `ì` used throughout in precomposed NFC form.
- **Caporali.** Italian primary quotation `«…»` used consistently for quoted UI labels and product affordances: `«Esegui pulizia ora»` (L96), `«per sempre»` (L279), `«composer install»` (L505), `«Visitatori» e «Visualizzazioni di pagina»` (readme L251), `«Abilita la geografia a livello di città»` (readme L391). The English source uses straight ASCII quotes; the translator correctly upgraded to caporali.
- **No NBSP** before `:` `;` `?` `!` — confirmed (rubric explicitly notes this is a fr_FR rule, not Italian).
- **Sentence case** headlines throughout (`Conservazione dei dati`, `Tracciamento senza cookie`, `Distribuzione dei dispositivi`, `Pagine attive`, `Visitatori e sessioni nel tempo`).
- **Numerals.** Western digits everywhere; `~80 MB`, `~70 MB`, `30 giorni`, `90 giorni`, `1 anno`, `circa 80 MB`, `circa l’80 %`, `~200 pattern`, `100 %`. No Italian-decimal-comma context to test (no fractional measurements).
- **Currency.** No €/$ surfaces in current PO corpus — pricing-page text not present here.

## F. Register — strong, one mixed pocket

Formal `Lei` register is dominant and consistent:

- Honorific possessives capitalised everywhere they appear: `Suoi report` (L68), `Sua cartella uploads` (L1041), `Suo browser` (L409, L677), `Suo team` (L1001), `Suo IP attuale` (L1005), `Suo database WordPress` (readme L36), `Sua chiave di licenza` (L1021), `Suoi diritti` (L445), `Suo account utente` (L449), `i Suoi visitatori` (readme L291), `i Suoi migliori contenuti` (readme L271).
- Formal-Lei third-person imperatives used: `Verifichi` (L262), `Riveda` (L465, L479), `Apra` (L469), `Imposti` (L190), `Valuti` (L202, L228, L487), `Reinstalli` (L152), `Disattivi` (L287), `Riprovi` (L705), `Abiliti` (L677, L1045), `Configuri` (L677, L705), `Svuoti` (L1033), `Escluda` (readme L231), `Consenta` (readme L231), `Carichi` (readme L131), `Attivi` (readme L136), `Apra` (readme L141), `Installi, attivi, apra` (readme L46), `Veda` (readme L36, L296), `Scopra` (readme L121), `Conoscere`, `Individuare`, `Capire`, `Vedere`, `Raggiungere` (infinitive-style screenshot captions).
- **No `Voi` plural-formal** anywhere.
- **No mixed `tu` + `Lei` within a single string** in the PHP-source block.

P2 mixed-register pocket: a few button-label strings and the skip-link use familiar `tu`-imperative (e.g. `Esegui pulizia ora` L100, `Ignora` L104, `Salva` L941, `Vai al contenuto` L546, `Abilita la geografia a livello di città` L701, `Aggiungi alle esclusioni` L1009). This matches the long-standing WordPress-IT core convention for button labels — WP core itself uses `Salva`, `Aggiungi`, `Pubblica`, `Elimina`, `Vai al contenuto` in familiar form. Keeping the buttons in familiar imperative while the surrounding prose stays formal-Lei is a legitimate stylistic choice, but research 65 § 5 mildly prefers infinitive (`Eseguire`, `Salvare`) to sidestep the question entirely. Logged as P2 only.

## G. Forbidden / hype words — pass

Scanned for: `rivoluzionario`, `definitivo`, `magico`, `il migliore`, `il più potente`, `imperdibile`, `unico`, `next-gen`, `di nuova generazione`, `di ultima generazione`, `all'avanguardia`, `alla portata di tutti`, `senza precedenti`, `straordinario`, `eccezionale`, `leader del settore`, `clicca qui`, `Statnive's dashboard`, `propulsato da`, `ottieni Statnive gratis`, `certificato GDPR`, `validato dal Garante`. **None present.** ✓

## H. Placeholder / HTML / arrow preservation — pass

All `%s`, `%d`, `%1$s`, `%2$s`, `%1$d`, `%2$d` positional placeholders preserved verbatim. All HTML tags (`<strong>`, `<a href="…">`, `<code>`) preserved with attribute values intact. Em-dashes `—` (U+2014) and arrows `→` (U+2192) preserved in `Impostazioni → GeoIP`, `Impostazioni → Diagnostica`, `Impostazioni → Privacy`, `Impostazioni → Tracciamento`, `Bacheca → Plugin → Aggiungi nuovo` etc. — across all surfaces. ✓

## I. Plurals — pass

Italian `Plural-Forms: nplurals=2; plural=(n != 1);` matches the PO header. The two msgid strings carrying numeric placeholders use Italian-natural agreement: `Anonimizzate %d sessioni di analisi` (L305) and `%d visitatori attivi` (L574, L844). Note: these English source strings use the singular form `(s)` notation, not a `msgid_plural` block, so the seed only carries one msgstr per ID — which is correct for the source as authored. If WP.org pluralisation is desired later, the upstream POT would need `Anonymized %d analytics session(s).` rewritten as a proper plural pair.

---

## P0 — Fixed directly

### 1. `statnive-it.po` L152 — wrong gender + awkward passive calque (FIXED)

```diff
- è stata fatta una downgrade del plugin
+ ha installato una versione precedente del plugin
```

Two problems addressed:

1. **Gender of loanword.** Research 65 rule 23 standardises Statnive loanwords as masculine when not explicitly fixed elsewhere — `downgrade` follows the pattern of `il plugin`, `il browser`, `il server`, `il link`, `il bug`. Writing `una downgrade` is the kind of feminisation that consumer-side Italian magazines do for `app` / `query` but never for transactional dev-ops nouns like `downgrade`, `rollback`, `commit`. Native form is `un downgrade`.
2. **Calque syntax.** `è stata fatta una downgrade` is the literal passive translation of "a downgrade has been made". Native Italian dev writing uses an active formal verb addressed to the reader: `se ha installato una versione precedente del plugin` ("if you installed an earlier version of the plugin"). This also dodges the gender question entirely and reads as natural error-message copy.

The string is `src/Database/Migrator.php:263` — the version-mismatch banner shown after a downgrade-induced schema drift.

### 2. `readme-it.po` L191 — wrong word order in `a meno che` clause (FIXED)

```diff
- a meno che non Lei attivi esplicitamente i download GeoIP
+ a meno che Lei non attivi esplicitamente i download GeoIP
```

In Italian, the `a meno che` (= "unless") subordinator takes the subjunctive **plus an expletive `non`**, and the `non` always follows the subject, never precedes it. The MT-tell `a meno che non + subject` is a French-influenced calque (French uses `à moins que` + expletive `ne` before the verb but no fixed subject position). Garante and Iubenda corpora consistently use `a meno che [SUBJECT] non + SUBJUNCTIVE`.

The string is the FAQ answer to "Where is my data stored?" — high-visibility on the WP.org plugin page.

---

## P1 — Logged (style / consistency, no release blocker)

### Plugin PO

1. **L546 `Vai al contenuto`** — accessibility skip-link uses familiar `tu`-imperative while the rest of the file uses formal `Lei`. Acceptable because WP-core IT itself ships `Vai al contenuto` as the canonical skip-link in `wp-admin`, so changing it would create cross-plugin dissonance for screen-reader users. Recommend keeping as-is and flagging in the style guide that "skip-link is WP-core verbatim; do not formalise".
2. **L100 / L104 button labels** — `Esegui pulizia ora` and `Ignora` use familiar `tu`-imperative inside admin notices whose prose uses formal `Lei`. WP-IT button convention matches this. Research 65 § 5 mildly prefers infinitive (`Eseguire la pulizia`, `Ignorare`) for buttons. Not a regression on its own; revisit if/when we adopt a consistent infinitive button policy site-wide.
3. **L88 `pannello del Suo dashboard`** — N/A here (no such string), but watch in future strings: research 65 rule 23 prescribes masculine `il dashboard` for the Statnive product surface and the current corpus is consistent (`Suo dashboard` L465). Keep enforcing.
4. **L1041 `circa 70 MB nella Sua cartella uploads`** — perfectly idiomatic. Worth noting that `uploads` is preserved as the directory name (a code-side identifier referring to `wp-content/uploads/`), not translated as `caricamenti` — correct, but on a future review pass consider standardising to `nella Sua cartella di upload` (loanword `upload` is masculine and more universally recognised as the directory name in Italian WP discourse). P1 stylistic only.
5. **L632 / L665 / L733 / L808 / L872 `Diagnostica`** — `Settings → Diagnostics` translated as `Impostazioni → Diagnostica`. This is the canonical UI label and consistent across the file. No issue. (Noted only because the analogous string for `Settings → Tracking` (L788) renders `Impostazioni → Tracciamento` — verify these two settings keys match the React-side label keys exactly when running the build.)

### Readme PO

1. **Screenshot caption 7 (L291)** — `Raggiungere tutte le lingue e tutte le regioni — veda quali lingue parlano i Suoi visitatori` mixes infinitive (`Raggiungere`) on the first clause with formal-Lei imperative (`veda`) on the second. Captions #1–#6 use infinitive throughout (`Conoscere`, `Individuare`, `Vedere`, `Capire`). Caption #8 (L296) starts with formal-Lei imperative (`Veda il Suo sito respirare`). The English source already mixes styles (caption #7 mixes "Reach... see"; caption #8 starts with "Watch"). Recommend two passes:
   - Option A (preserve EN parallelism): keep as-is.
   - Option B (uniform IT register): rewrite all eight in infinitive — caption #7 → `Raggiungere tutte le lingue e tutte le regioni — scoprire quali lingue parlano i Suoi visitatori`; caption #8 → `Vedere il Suo sito respirare in tempo reale — visitatori attivi e visualizzazioni di pagina in diretta`.
   Either passes B2B muster; I lean **Option B** for cleaner Italian register, but it's a judgement call.
2. **L36 `Veda con precisione`** — formal-Lei imperative on a description paragraph, which is internally consistent with the rest of the readme. The English `See exactly who visits` uses imperative; the IT translator chose formal-Lei matching, which is correct. Not an issue, just noting the pattern.
3. **L46 `Installi, attivi, apra Statnive — il dashboard si popola in pochi minuti`** — `il dashboard si popola` ("the dashboard populates itself") is borderline-literal. Native Italian SaaS writers tend to say `il dashboard si riempie` ("fills up") or `i dati cominciano ad arrivare nel dashboard`. P1 stylistic — not wrong, just a touch translationese. Suggestion: `il dashboard si riempie di dati in pochi minuti`.

---

## P2 — Minor preferences (not regression risks)

### Plugin PO

1. **L1041 `circa 70 MB`** — research 65 rule 12 standardises Italian units as `KB / MB / GB / TB` with English symbol forms (which the translator follows correctly). No-op.
2. **L693 `~80 MB`** — using the ASCII tilde `~` for "approximately" is normal in Italian dev tone and renders identical to source.

### Readme PO

1. **L261 / L266 / L271 / L276 / L281 / L286 / L291 / L296 screenshot captions** — see P1 #1 above for the register split. Logging here as P2 to make the choice explicit: if we keep the existing pattern (infinitive #1–6, mixed #7, imperative #8), document it; if we unify, edit per Option B.
2. **L101 `Geografia a livelli`** — translates "Geography in tiers" as "Geography in layers". `livelli` is fine; `gradini` ("steps") or the dev-tone `tier` loanword (`Geografia a tier`) would also be acceptable. `livelli` is the safest and matches the body copy at L241 (`Quattro livelli`). No change.

---

## Cross-file consistency check — pass

`Statnive – Simple, Real-time, Privacy-first Web Analytics` is translated identically in both files (`Statnive – Analisi web semplice, in tempo reale e rispettosa della privacy` — `statnive-it.po` L17 and `readme-it.po` L16). The em-dash style (`–`, U+2013) and the order of adjectives are byte-identical, as required by the Italian product-stripe contract.

`Description of the plugin` (statnive-it L28) and the readme tagline (L31) carry mutually reinforcing phrasing: `Analisi web per WordPress rispettosa della privacy` is the canonical lead phrase, deployed as both the plugin's short-form description and the readme's bold opener.

---

## msgfmt — clean

```
statnive-it.po:4: warning: header field 'Last-Translator' missing in header
statnive-it.po:4: warning: header field 'Language-Team' missing in header
241 translated messages.
---
readme-it.po:4: warning: header field 'Last-Translator' missing in header
readme-it.po:4: warning: header field 'Language-Team' missing in header
89 translated messages.
```

The two header warnings are present in every Statnive seed PO across all locales and are explicitly ignored by `wordpress.org/plugins/translate/` ingestion. No content-level warnings, no syntax errors, no placeholder mismatches, no plural-form arithmetic problems.

---

## Final verdict

**Ship.** Both PO files are release-ready after the two P0 fixes applied above. The P1 / P2 items are improvements to schedule for the next localisation sprint; none of them is visible to end users in a way that would damage trust or comprehension.
