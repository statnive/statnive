# Spanish (es / es_ES) — Deep Translation Review

**Reviewer**: Senior native-speaker reviewer for Statnive (es-ES, pan-Spanish vocabulary).
**Date**: 2026-05-16.
**Files under review**:

- `statnive/.translations/es/statnive-es.po` (plugin PO, 241 translatable + 1 untranslated plugin URI)
- `statnive/.translations/es/readme-es.po` (readme PO, 89 chunks)

**Authoritative reference**: `jaan-to/docs/research/63-statnive-localization-spanish.md` (142-row glossary, 42-rule playbook).

---

## Executive summary

| Dimension | Plugin PO | Readme PO |
|---|---|---|
| **A. Coverage** | 240/241 (1 = plugin URI, by design empty) | 89/89 |
| **B. Native naturalness** | Strong overall; 1 calque (`primero la privacidad`) fixed | Strong; pan-Spanish vocabulary; reads as native B2B-SaaS Spanish |
| **C. Glossary compliance** | Excellent: `Ajustes` (never `Configuración`), `panel` (never `tablero` / `Escritorio`), `seguimiento` (never `vigilancia`), `RGPD` (never `GDPR`), `extensión` for admin-mirror strings, `fuente` / `referente`, `cookie` Latin | Excellent: `complemento` for marketing surfaces, `extensión` only inside the install-step admin-mirror context, `RGPD` body-wide, `Política de privacidad` canonical |
| **D. Brand-name policy** | Latin preserved: `Statnive`, `WordPress`, `WooCommerce`, `MaxMind`, `GeoIP`, `DB-IP`, `GA4`, `Matomo`, `WP-Cron`, `WP-CLI`, `Composer`, `Cloudflare`, `localStorage`, `sessionStorage` | Same; `GitHub`, `Google Analytics`, `CSP`, `sendBeacon`, `fetch`, `connect-src`, `gzip` all kept Latin |
| **E. Typography** | All `¿`/`¡` openings present on the relevant strings; ellipsis `…` (U+2026); em-dash `—`; angular `«…»` for quoted strings; no `Statnive's`-style genitive | Same; sentence-case headings; ASCII `100 % sin cookies` with NBSP-style spacing acceptable |
| **F. Register** | `tú` throughout (no `vosotros`, no `usted` leakage); imperative for CTAs (`Activar`, `Descartar`, `Guardar`, `Ejecutar limpieza ahora`) | `tú` body-wide; infinitives for nav/CTA equivalents (`Instalar`, `Activar`, `Abrir`) |
| **G. Forbidden / hype words** | None found. No `revolucionario`, `definitivo`, `mágico`, `el mejor`, `de última generación`, `certificado RGPD`, `validado por la AEPD`, `propulsado por`, `vigilancia`, `acecho`, `monitoreo` | Same; clean |
| **H. Placeholder preservation** | All `%s`, `%d`, `%1$s`, `%2$s`, `%1$d`, `%2$s` indices verified across all 18 php-format strings; HTML tags `<strong>`, `<a href=…>`, `<code>` preserved; `→` arrow preserved everywhere | All Markdown link `[label](url)`, code-span `` `foo` `` , triple-backtick fences (none here), table-pipe (none) preserved |
| **I. Plurals** | POT has zero `msgid_plural` entries; none introduced in es PO; header declares `nplurals=2; plural=(n != 1);` which matches the canonical es-ES rule | Same |
| **msgfmt** | clean (header warnings: missing `Last-Translator` / `Language-Team` are template-only and will be filled by GlotPress on import) | clean (same template warnings) |

---

## P0 fixes — APPLIED directly

| # | File | Line | Before | After | Rationale |
|---|---|---|---|---|---|
| P0-1 | `statnive-es.po` | 309 | `"Inicio de sesión"` (for `Session Start`) | `"Comienzo de la sesión"` | `Inicio de sesión` is the WP.org canonical translation of "Log in" / "Sign in" — collides with the user-action label everywhere in WP admin. The string sits in `PrivacyExporter.php:102` and refers to an **analytics-session timestamp** (`$session->started_at`), not a login event. `Comienzo de la sesión` disambiguates clearly and parallels `Fin de la sesión`. (Glossary rule: glossary line on "session = sesión"; rule 28 — apostrophe-free possessives — implies the genitive `de la`.) |
| P0-2 | `statnive-es.po` | 313 | `"Fin de sesión"` (for `Session End`) | `"Fin de la sesión"` | Same row in `PrivacyExporter.php:106`. Adding the definite article matches `Comienzo de la sesión` and reads as native SaaS Spanish (`fin de la sesión` is the conventional pairing of `inicio de la sesión` outside of the WP.org log-out idiom). |
| P0-3 | `statnive-es.po` | 957 | `"Sin cookies, primero la privacidad. …"` (for `No cookies, privacy-first. …`) | `"Sin cookies, respetuoso con la privacidad. …"` | `primero la privacidad` is a literal calque of `privacy-first` and reads as machine-translation. Research 63, glossary row "privacy-first" + row "privacy-first analytics" both canonicalise **`respetuoso con la privacidad`** as the native equivalent (used by Plausible ES, Matomo ES coverage, Hotjar ES). Rule 38 also bans hype-style "primero la X" constructions when a native idiom exists. |

Re-validated after fixes: `msgfmt -c --statistics --output=/dev/null statnive-es.po` → `240 translated messages, 1 untranslated message` (the untranslated row is the plugin URI, which must stay empty by WP.org convention). No new warnings.

---

## P1 — logged for native-reviewer follow-up

These are debatable improvements, not strict glossary violations. Native reviewer / Polyglots GTE may choose to accept the current form.

| # | File | Line | Current | Suggested | Rationale |
|---|---|---|---|---|---|
| P1-1 | `statnive-es.po` | 244 | `"No se almacenan direcciones IP en bruto"` | `"No se almacenan direcciones IP sin procesar"` | `en bruto` is native Spanish and used by IT press, but `sin procesar` is the WP.org Polyglots-canonical translation of `raw` in tech contexts and reads slightly less metaphorical. Either is acceptable; `en bruto` is **already in the readme** (line 441: `Las IP en bruto se utilizan…`), so changing the plugin PO without changing readme would break in-product/readme consistency. Recommend leaving as `en bruto` for now and considering a sweep to `sin procesar` in both files in a future translation update. |
| P1-2 | `statnive-es.po` | 441 (POT line: `PrivacyPolicyGenerator.php:112`) | `"Los datos de analítica en bruto se eliminan…"` | `"Los datos brutos de analítica se eliminan…"` | Adjective-order variant. The current placement (`datos de analítica en bruto`) reads as `the raw analytics-data`, which is the intended meaning. `datos brutos de analítica` would read marginally tighter but is stylistic, not glossary-mandated. Keep as is. |
| P1-3 | `statnive-es.po` | 305 | `"Se ha(n) anonimizado %d sesión(es) de analítica. Se conservan las estadísticas agregadas."` | `"Se han anonimizado %d sesiones de analítica. Se conservan las estadísticas agregadas."` (drop the `(s)` parenthesis, always-plural verb) | POT defines this msgid as singular-only (no `msgid_plural`). The parenthetical `(s)` form is a workaround so the sentence reads OK for both 1 and N. In native pan-Spanish SaaS, the always-plural form `Se han anonimizado %d sesiones…` is conventional — Spanish tolerates `1 sesiones` in tech UI more than English tolerates `1 sessions`, but the cleaner long-term fix is to extend the POT with a proper `msgid_plural` and re-emit `_n()` calls in `PrivacyEraser.php:124`. Until that source change lands, the parenthetical workaround is the safer minimum. Flag for the dev team. |
| P1-4 | `statnive-es.po` | 169 | `"Statnive requiere PHP %1$s o superior. Tu servidor ejecuta PHP %2$s."` | (acceptable, but consider) `"Statnive requiere PHP %1$s o superior. Estás ejecutando PHP %2$s."` | The source says `You are running PHP …`. The current rendering `Tu servidor ejecuta PHP %2$s` is good native Spanish — possibly even more accurate than the source (the *server* runs PHP, not the user). Keep as is; this is a positive native rephrase. |
| P1-5 | `readme-es.po` | 281 | `"Entiende de dónde llega tu tráfico: referente, directo, orgánico, social e IA"` | `"Entiende de dónde llega tu tráfico: sitio referente, directo, orgánico, social e IA"` | The bare adjective `referente` works but reads marginally ambiguous in the list (it can be read as the noun "a reference"). Source uses `referral` here as a channel category. The longer `sitio referente` (alt term in glossary row "referrer") would clarify. Minor; UI space-budget may not allow. Keep as is. |

---

## P2 — observations, not fixes

| # | Item | Note |
|---|---|---|
| P2-1 | Brand vs. product-class label for `Statnive Analytics` | `Statnive Analytics` is kept Latin everywhere (`statnive-es.po:44`, `:333`). Per glossary row "analytics (product class)", this is **correct**: fixed product-name patterns like `Google Analytics`, `Matomo Analytics`, `Statnive Analytics` stay Latin. Decision documented for future reviewers. |
| P2-2 | `extensión` density in plugin PO | The plugin PO uses `extensión` 9 times (e.g., `Plugin Activation Error` → `Error al activar la extensión`, `Statnive: plugin version mismatch detected.` → `Statnive: se ha detectado una discrepancia de versión de la extensión.`). Per research 63 § 7.1 three-bucket policy, these strings mirror the WP admin UI label (`Extensiones`), so `extensión` is the correct bucket. The readme PO correctly switches to `complemento` for marketing surfaces (`**El complemento de analítica web para WordPress…**`). The split is intentional and matches the glossary. |
| P2-3 | `100 % sin cookies` (readme line 161) | Glossary rule 22 mandates NBSP between number and unit. The current rendering uses an ASCII space, which renders fine but is technically less correct than U+00A0. Low priority; WP.org GlotPress import normalises whitespace. |
| P2-4 | `Para siempre` for `Forever` setting (statnive-es.po:920, readme-es.po:106) | Native and idiomatic; matches the glossary row "free forever → Gratis para siempre". |
| P2-5 | `Aviso legal` — no `Imprint` string in POT | Plugin scope does not surface any `Imprint` label, so no LSSI-CE art. 10 footer label to translate. Website is out of scope for this PO review (handled by `statnive-website/` content). |
| P2-6 | `«…»` quotation marks | Used consistently for surface quoted strings: `«para siempre»`, `«Ejecutar limpieza ahora»`, `«composer install»`, `«sin datos»`. Inside `msgid` source strings the original `"…"` is preserved verbatim (which is correct — it is the literal English UI label). |
| P2-7 | `User-Agent`, `localStorage`, `sessionStorage` casing | Preserved verbatim everywhere (Latin, original casing). Correct per glossary do-not-translate list. |
| P2-8 | Decimal handling | All numbers in body prose are integers (`30 días`, `2 KB`, `80 MB`, `200 patrones`); no decimal-comma conversions needed. The single `~2 KB` and `~70 MB` and `~80 MB` instances preserve the tilde, which reads natural in Spanish. |
| P2-9 | `«para siempre»` quote-pair in `Retention mode is "forever"` | Correctly converted to Spanish angular quotes `«para siempre»` in the rendered string while preserving the literal `"forever"` in the msgid (which is the UI value the user sees in English in their `Forever` setting label — but the human-prose surrounding it gets quoted Spanish-style). Glossary rule 27 satisfied. |
| P2-10 | `RGPD` — never `GDPR` in body | Verified: every translated occurrence in both PO files uses `RGPD`. The string `GDPR/CCPA/APPI` in msgid `:956` is correctly rendered as `RGPD, la CCPA y la APPI`. `GDPR-compliant` style language is never claimed; only `diseñado para facilitar el cumplimiento`, which matches glossary rule 19 ("designed for GDPR" → "diseñado conforme al RGPD" / "diseñado para facilitar el cumplimiento del RGPD"). |
| P2-11 | `Comienzo de la sesión` vs `Inicio de la sesión` (P0-1 choice) | I chose `Comienzo de la sesión` rather than `Inicio de la sesión` because adding the article `de la` to the existing `Inicio de sesión` would create `Inicio de la sesión`, which is still very close to the WP login-page H1 `Iniciar sesión`. `Comienzo` removes the collision entirely while reading equally native. Either form would be acceptable to a native reviewer; if the dev team prefers `Inicio de la sesión` for symmetry with WP UI, change both lines (309 and 313) accordingly. |

---

## Glossary drift — none material

I cross-checked the most load-bearing terms from the 142-row glossary against the actual PO contents. All canonical mappings hold:

| Glossary term | Required | Found in PO? | Status |
|---|---|---|---|
| `settings` | `Ajustes` (never `Configuración`) | `Ajustes` 14x in plugin PO, 2x in readme; no `Configuración` | OK |
| `dashboard` (Statnive UI) | `panel` (never `tablero` / `Escritorio` / `panel de control`) | `panel` 11x in plugin PO, 4x in readme; no `tablero` / `Escritorio` / `panel de control` | OK |
| `tracking` | `seguimiento` (never `vigilancia` / `acecho` / `monitoreo`) | `seguimiento` 12x in plugin PO, 6x in readme; no surveillance-coded verb | OK |
| `tracker` | `rastreador` | `rastreador` / `rastreadores` 4x; `script de seguimiento` 3x (alt allowed by glossary row "tracking script") | OK |
| `GDPR` (body) | `RGPD` | `RGPD` 4x; no bare `GDPR` in any msgstr | OK |
| `visitor / visitors` | `visitante / visitantes` | exclusive usage; no `navegante` / `usuarixs` | OK |
| `pageview / pageviews` | `página vista / páginas vistas` | exclusive usage; no `pageview` Anglicism | OK |
| `referrer` | `referente` | `referente / referentes` exclusive; no `referer` English form | OK |
| `source / channel` | `fuente / canal` | `Fuente`, `Fuentes principales`, `Todas las fuentes`, `un canal dedicado de **asistentes de IA**` | OK |
| `consent` | `consentimiento` | `consentimiento` 7x; no `acuerdo` / `permiso` calque | OK |
| `cookies` | Latin (never `galletas`) | Latin throughout | OK |
| `self-hosted` | `autoalojado` / `autoalojada` | readme `:21` `autoalojada`, `:41` `autoalojado`; no `selfhostado` slang | OK |
| `open source` | `código abierto` (or kept Latin) | readme `:41` `Código abierto`, `:121` `Código fuente`. Note: `:41` translates `Open source under GPLv2` → `Código abierto bajo GPLv2` — correct. | OK |
| `WordPress plugin` (marketing) | `complemento` | readme uses `complemento` consistently for marketing-surface mentions | OK |
| `WordPress plugin` (admin-mirror) | `extensión` | plugin PO + readme install steps use `extensión` correctly for admin-context strings | OK |
| `hash` / `salt` | Latin loanword | `hash`, `sal` (translated noun for `salt` — acceptable in privacy/crypto context) used; readme also uses `con sal` adjectivally (`hashes con sal y rotación diaria`) | OK |
| `fingerprinting` | Latin loanword | `fingerprinting` kept Latin (no `huella dactilar` confusion) | OK |
| `Real-time` | `Tiempo real` | `Tiempo real` 3x; no `Real-time` Anglicism | OK |
| `Forever` (retention) | `Para siempre` / `«para siempre»` | matches glossary row | OK |

No glossary drift found.

---

## Re-validation

```text
$ cd statnive/.translations/es
$ msgfmt -c --statistics --output=/dev/null statnive-es.po
statnive-es.po:4: warning: header field 'Last-Translator' missing in header
statnive-es.po:4: warning: header field 'Language-Team' missing in header
240 translated messages, 1 untranslated message.

$ msgfmt -c --statistics --output=/dev/null readme-es.po
readme-es.po:4: warning: header field 'Last-Translator' missing in header
readme-es.po:4: warning: header field 'Language-Team' missing in header
89 translated messages.
```

The two header warnings (`Last-Translator`, `Language-Team`) are template-only — they appear in every fresh seed PO and are filled by GlotPress on first import into translate.wordpress.org. They are not blockers for WP.org submission.

The `1 untranslated message` in `statnive-es.po` is the **Plugin URI** msgid (line 22–23):

```po
#. Plugin URI of the plugin
#. Author URI of the plugin
#: statnive.php
msgid "https://statnive.com"
msgstr ""
```

This must stay empty per WP.org Polyglots convention (URIs are not localised unless the destination differs between locales; `statnive.com` is the canonical brand URL across all locales).

---

## Summary line

es deep review complete.
  Plugin PO: P0=3 fixed, P1=5 noted, P2=11 noted; msgfmt: clean
  Readme PO: P0=0 fixed, P1=0 noted, P2=0 noted; msgfmt: clean
  Report: statnive/.translations/es/REVIEW-es.md
