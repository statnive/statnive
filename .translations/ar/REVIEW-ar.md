# Arabic (ar) Deep Translation Review — Statnive WordPress Plugin

**Reviewer:** senior MSA-native (pan-Arab) translation reviewer
**Date:** 2026-05-16
**Plugin version:** 0.4.13
**Files reviewed:**
- `statnive/.translations/ar/statnive-ar.po` (plugin strings — 241 entries, 240 translated, 1 Plugin URI empty by design)
- `statnive/.translations/ar/readme-ar.po` (WordPress.org readme.txt — 87 entries, all translated)

**Authoritative references:**
- `jaan-to/docs/research/47-statnive-localization-arabic.md` (style guide / glossary)
- `jaan-to/docs/research/62-i18n-statnive-wordpress-translation-playbook.md`
- Project CLAUDE.md → § "Workflow Rule — Keep the Arabic mirror (`ar.statnive.com`) in Sync"

---

## Summary

| Tier | Plugin PO | Readme PO | Notes |
|---|---|---|---|
| **P0 — fixed** | 1 | 0 | Forbidden word `ملاحقة` rewritten to neutral `تتبع` in privacy-policy section |
| **P1 — noted** | 4 | 1 | Minor tightening of MSA register; preserve current shape |
| **P2 — noted** | 5 | 3 | Cosmetic / style-preference flags |
| **msgfmt** | clean (cosmetic warnings only) | clean (cosmetic warnings only) | only standard `Last-Translator` / `Language-Team` header warnings |

**Overall verdict:** the seed is **publication-ready**. The Arabic register is consistent Modern Standard Arabic (الفصحى المعاصرة), pan-Arab, with no dialectal slippage; brand-name policy (Latin `Statnive` / `WordPress` / `WooCommerce` / `GeoIP` / `MaxMind` / `DB-IP` / `WP-Cron` / `WP-CLI`) is enforced consistently; Arabic letterforms `ك` / `ي` are correct (zero Persian-variant `ک` / `ی` leakage); Western digits `0-9` throughout (zero Arabic-Indic `٠-٩` or Persian-Indic `۰-۹` leakage); all `→` arrows are mirrored to `←` for RTL flow; all `printf` placeholders (`%s`, `%d`, `%1$s`, `%2$s`, `%1$d`) preserved verbatim; HTML tag balance preserved verbatim.

---

## A. Coverage

**Plugin PO:** 241 / 242 POT msgids carry a non-empty `msgstr` (240 translated + 1 deliberately empty: `https://statnive.com` Plugin URI, which `msgfmt` correctly classifies as "untranslated" because URLs do not require translation per WP.org convention). `msgfmt` count agrees: `240 translated messages, 1 untranslated message`. Pass.

**Readme PO:** 87 / 87 entries translated. `msgfmt` count agrees: `87 translated messages`. Pass.

---

## B. Native naturalness (MSA, pan-Arab)

The seed reads as MSA suitable for Casablanca, Cairo, Beirut, Riyadh, and Baghdad readers. No dialectal markers detected:

| Probe | Hits | Notes |
|---|---|---|
| Egyptian (`عايز`, `بدون مشاكل`, `كده`, `يلا`, `بس`) | 0 | clean |
| Levantine (`بدّك`, `هلّق`, `شو`, `حلو`) | 0 | clean |
| Gulf (`أبغى`, `وش`, `كذا`) | 0 | clean |
| Maghrebi (`بزاف`, `بَش`) | 0 | clean |
| Over-classical bureaucratic (`نُحيطكم علماً`, `سيادتكم`) | 0 | clean |
| MT-tell calque `يمكنك أن…` | 0 | clean |
| MT-tell calque `بواسطة…` | 0 | clean |
| English SVO forced through Arabic | 0 critical | natural VSO/SVO flow throughout |
| Calque possessive `Statnive's لوحة` | 0 | Arabic `لوحة تحكم Statnive` is used correctly |
| Diacritics (tashkeel) overuse | 0 | only one minimal disambiguation site found in the file: `مُهيَّأ`, `مُجمَّعة`, `مُجهَّل`, `المخزَّنة` — these are passive-participle disambiguations and remain acceptable per research §7 |

**Pass.**

---

## C. Glossary compliance (research-47)

| Term | Required | Found in files | Status |
|---|---|---|---|
| analytics | `تحليلات` | `تحليلات` consistent | Pass |
| stats / statistics | `إحصاءات` | `إحصاءات` (hamza on alif) consistent; zero `إحصائات` typo | Pass |
| Statnive's own measurement | `قياس` / `تسجيل` (NEVER `تتبع`) | `قياس` used in tracker / settings / overview contexts; `تتبع` reserved for anti-tracking framing | Pass — see § G note about one P0 fix applied |
| third-party tracking (negative) | `تتبع` | `سكريبتات تتبع تابعة لأطراف ثالثة` (readme-ar.po:28); `بدون تتبع` framing in line 52 (cross-day denial) and 128 (cross-day/site denial). Glossary §17 honored. | Pass |
| dashboard (WP admin) | `لوحة التحكم` | `لوحة التحكم` used in dashboard navigation + Site Health context | Pass |
| dashboard (analytics overview) | `لوحة المعلومات` / `لوحة التحكم` (WP context permits both) | Throughout the React pages the file uses `لوحة التحكم` for the embedded WP dashboard chrome (status messages, nav). Acceptable WP-context choice — research permits `لوحة التحكم` inside WP admin. | Pass |
| visitor | `زائر` | consistent (`زائر`, `الزائر`, `زوار`, `الزوار`, `الزائرة`) | Pass |
| privacy | `الخصوصية` | consistent | Pass |
| privacy-first | `تحترم الخصوصية` | `تحليلات تحترم الخصوصية` — exact research-47 target | Pass |
| plugin | `إضافة` | consistent; zero `ملحق` / `بلجن` | Pass |
| plugins (pl.) | `إضافات` | consistent | Pass |
| settings | `الإعدادات` | consistent | Pass |
| cookie | `ملفات تعريف الارتباط` (formal) / `كوكيز` (technical-loan) | Both forms appear. Pattern: legal generator file (`PrivacyPolicyGenerator`) leans `ملفات تعريف الارتباط`; admin-UI / quick-settings / hero / privacy-policy bullet-points use `كوكيز`. One msgstr (line 270) glosses with both: `ملفات تعريف ارتباط (كوكيز)` on first mention — recommended pattern per research §15. | Pass with note (P2-1) |
| cookieless | `بدون كوكيز` | consistent (`بدون كوكيز`, `قياس بدون كوكيز`, `cookieless` toggle label) | Pass |
| self-hosted | `ذاتي الاستضافة` | `ذاتية الاستضافة` in the readme short-description; matches research adjectival form | Pass |
| imprint | n/a (not in current strings) | not present | n/a |

**Pass overall.**

---

## D. Brand-name policy

| Token | Required | Found | Status |
|---|---|---|---|
| `Statnive` | Latin always; no transliteration | Latin throughout; zero `ستاتنايف` or `ستات‌نايف` | Pass |
| `WordPress` | Latin always | Latin throughout; zero `وردبريس` / `ووردبريس` (note: research permits `ووردبريس` in SEO marketing body, but the plugin/readme files are admin-UI, where Latin-only is correct) | Pass |
| `WooCommerce` | Latin | Latin | Pass |
| `GeoIP` / `MaxMind` / `DB-IP` / `GeoLite2` | Latin | Latin | Pass |
| `WP-Cron` / `WP-CLI` / `Cron` | Latin | Latin throughout | Pass |
| `GDPR` / `CCPA` / `APPI` / `PIPL` | Latin | Latin | Pass |
| `Google Analytics` / `GA4` / `Matomo` | Latin | Latin | Pass |
| `MCP` / `LCP` / `KB` / `MB` / `GB` / `API` / `IP` | Latin | Latin | Pass |
| `JavaScript` / `TypeScript` / `Cookie` (as technical loanword) | Latin | Latin where invoked | Pass |
| `ClickHouse` / `GitHub` / `Node.js` / `npm` | Latin | Latin | Pass |

**Pass.**

---

## E. Typography (CRITICAL for Arabic)

| Probe | Required | Found |
|---|---|---|
| Persian KAF `ک` (U+06A9) | zero in body | **0** |
| Persian YEH `ی` (U+06CC) | zero in body | **0** |
| Persian-only `پ` `چ` `ژ` `گ` | zero in body | **0** |
| Eastern Arabic-Indic digits `٠–٩` (U+0660–0669) | zero | **0** |
| Persian-Indic digits `۰–۹` (U+06F0–06F9) | zero | **0** |
| Western digits `0-9` | mandatory in body | **present everywhere** (`30 يومًا`, `90 يومًا`, `180 يومًا`, `100%`, `2026`, `80%`, `200`, `80 MB`, `70 MB`, `1 KB`) |
| Arabic comma `،` (U+060C) | preferred in body prose | **used systematically** in lists and clauses |
| Arabic semicolon `؛` (U+061B) | preferred in body prose | **used** in geography-tier sentence (readme-ar.po:192) and various explanatory clauses |
| Arabic question mark `؟` (U+061F) | preferred for questions | **used** in `هل تستخدم Statnive ملفات تعريف الارتباط (الكوكيز)؟`, `هل تريد بيانات على مستوى المدينة؟`, etc. |
| Arabic guillemets `«…»` | preferred in long prose; ASCII `"…"` allowed for short labels/code | **`«…»` used in `«شغّل التنظيف الآن»`, `«إلى الأبد»`, `«composer install»`, `«لا توجد بيانات»`, `«الزوار»`, `«مشاهدات الصفحة»`, `«فعّل الجغرافيا على مستوى المدينة»` — consistent professional pattern** |
| Period `.` | ASCII OK; Arabic prose may use `.` | used |
| ASCII punctuation inside code/URLs/CLI literals | required | preserved (`composer install`, `wp-cron.php`, `wp-content/uploads/statnive/`, `dbip-city-lite-YYYY-MM.mmdb.gz`, `connect-src 'self'`, `/wp-json/statnive/v1/hit`, `admin-ajax.php?action=statnive_hit`) |
| Tashkeel overuse | avoid | only minimal disambiguation (`مُهيَّأ`, `مُجهَّل`, `مُجمَّعة`, `المخزَّنة`, `مصمَّمة`) — acceptable |
| Arrows `→` in RTL | mirror to `←` or rephrase | **systematically mirrored** — every breadcrumb `Settings → GeoIP`, `Settings → Privacy`, `Settings → Diagnostics`, `browser timezone → country` rendered as `الإعدادات ← GeoIP` etc. |

**Pass — typography is correct at every probe.**

---

## F. Register

- MSA throughout. No dialect. Pan-Arab vocabulary (no Egyptian-only `بدون مشاكل`, no Levantine-only `شو`, no Gulf-only `أبغى`, no Maghrebi `بزاف`).
- Imperative for CTAs: `جرّب`, `ثبّت`, `فعّل`, `أنشئ`, `راجع`, `حاول مرة أخرى`, `ألصق`, `أضِف إلى الاستثناءات`, `احصل على مفتاح مجاني`, `أعد تثبيت`, `شغّل التنظيف الآن`. Strong, direct, professional.
- Status messages use the conventional Arabic passive (`يتم احترام ترويسة DNT`, `يتم التخلص من عناوين IP`, `لا يتم تخزين أي بيانات شخصية`) — natural for technical UI status reporting.
- Plugin description hero (`Statnive – تحليلات ويب بسيطة ولحظية تحترم الخصوصية`) matches research §3 target.

**Pass.**

---

## G. Forbidden words

| Forbidden | Status |
|---|---|
| `تتبع` for own product | Was found at one site referring to inability to track (`لاحقها/ملاحقة`) — see P0-1 below. After fix: only used in **negative-denial framing** (i.e. describing what Statnive does **not** do, or what third-party scripts do) per research §17. Pass. |
| `ملاحقة` (manhunt) | **P0-1 fix applied** — see § P0 below. |
| Hype: `ثوري`, `الأفضل بلا منازع`, `سحري`, `الحل النهائي`, `قوة لا تُضاهى`, `الأقوى` | **0** matches in both files. Pass. |
| Saudi PDPL claim (`متوافق مع نظام حماية البيانات السعودي`, etc.) | **0** matches. Pass. |
| UAE PDPL / Egyptian / Qatar PDPPL / Morocco 09-08 / Lebanon / Jordan privacy-law claims | **0** matches. Pass. |
| Invented ISO / SOC compliance | **0**. Pass. |

---

## H. Placeholder / HTML / arrow preservation

- All `printf` placeholders (`%s`, `%d`, `%1$s`, `%2$s`, `%1$d`) preserved per pair — **0 mismatches** across 240 translated entries (verified by Python diff).
- All HTML tags (`<strong>`, `<a href="…">`, `<code>`) preserved per pair — **0 mismatches** across 87 readme entries.
- Arrows: every `→` → `←` (mirrored for RTL) so breadcrumbs like `Settings → GeoIP` render visually `GeoIP ← الإعدادات` correctly in an RTL stream.
- Code/CLI literals preserved verbatim (`wp statnive cron run`, `composer install`, `/wp-content/plugins/`, `connect-src 'self'`, `statnive-event-*`, `statnive_hit`, `wp-json/statnive/v1/hit`).

**Pass.**

---

## I. Plurals

POT contains **0** plural forms (no `_n()` / `_nx()` strings). PO file's `Plural-Forms` header declares the standard 6-form Arabic CLDR plural rule (`nplurals=6`) for future use. No plural mismatches. Pass.

---

## P0 — fixes applied directly

### P0-1 (plugin PO) — Forbidden word `ملاحقة` (manhunt connotation)

- **File:** `statnive/.translations/ar/statnive-ar.po`
- **Line:** 353
- **Source msgid:** `Statnive does not use cookies, localStorage, sessionStorage, or any form of browser fingerprinting. Visitor identification uses a daily-rotating cryptographic hash that cannot be reversed or used to track individuals across days.`
- **Before:** `… ولا يمكن عكسها أو استخدامها لملاحقة الأفراد عبر الأيام.`
- **After:** `… ولا يمكن عكسها أو استخدامها لتتبع الأفراد عبر الأيام.`
- **Rationale:** The rubric explicitly lists `ملاحقة` as forbidden — it carries a "manhunt / pursuit" connotation that reads ominous in a privacy-policy bullet that is meant to *reassure* readers. Research-47 §17 ("`تتبع` disambiguation") permits `تتبع` in **explicit-denial framing** ("cannot be used to track individuals"), which is precisely the syntactic context here ("cannot … track"). The fix preserves intent, restores the WP.org-standard neutral verb, and brings this string into parity with the analogous readme-ar.po:128 phrasing already approved by the seed (`لتتبع الأفراد عبر الأيام أو المواقع`).

---

## P1 — noted (no edits)

### P1-1 (plugin PO line 644) — `الروبوتات مقابل البشر` for "Bot vs Human"

The word `البشر` (humans) is technically correct MSA but reads a touch literary in a dashboard tab label. **Alternative for future tightening:** `روبوتات مقابل بشر` (drop the definite article for chart-label brevity) or — preferred — `البشر مقابل الروبوتات` (BotHash ordering puts the friendlier noun first). **Keep current**; this is a label preference, not a defect.

### P1-2 (plugin PO line 194) — `احترام إشارة Do Not Track` (status item label)

Verbless noun phrase is fine; could also read as `تُحترم إشارة Do Not Track` (passive present) to match the parallel `يتم احترام ترويسة DNT.` body sentence on line 198. **Keep current** — verbless heading + passive body is a common Arabic UI pattern.

### P1-3 (plugin PO lines 582–606) — Command-palette entries

Each WP commands palette item is currently `Statnive: انتقل إلى نظرة عامة`. The verb `انتقل إلى` is correct but slightly long for a command-palette pattern. **Alternative for future tightening:** `Statnive: الانتقال إلى نظرة عامة` (verbal-noun form) or `Statnive: فتح نظرة عامة` (verbal-noun form parallels Statnive's other UI). **Keep current** — both forms are idiomatic; the imperative reads warmer.

### P1-4 (plugin PO line 96) — `«شغّل التنظيف الآن»` quoted phrase

The msgstr correctly uses Arabic guillemets `«…»` around the button label. Cross-reference: the actual button (line 100) carries the bare phrase `شغّل التنظيف الآن` — exact match. Pass with note — the quotation pattern is exemplary and should be propagated to other locales.

### P1-5 (readme PO line 192) — `أربعة مستويات تتسلسل تلقائيًا` (Geography tier explanation)

The cascade verb `تتسلسل` reads slightly bureaucratic; alternative `أربعة مستويات تعمل بالتسلسل تلقائيًا` or `أربعة مستويات تتبع بعضها تلقائيًا`. **Keep current** — `تتسلسل` is precise.

---

## P2 — noted (cosmetic)

### P2-1 — Cookie-form alternation across the plugin PO

- Lines using `كوكيز` (loanword form): 266, 270 (with formal gloss), 401, 405, 949, 957.
- Lines using `ملفات تعريف الارتباط` (formal form): 270 (paired gloss), 337, 349, 353, 401.

Per research §15, mixing within a file is acceptable provided the legal generator file uses the formal form consistently — which is true here (`PrivacyPolicyGenerator.php` strings on lines 337, 349, 353 all use `ملفات تعريف الارتباط`). The admin-UI labels and short bullets use `كوكيز`. Pass — pattern is intentional.

### P2-2 (plugin PO line 614) — `بشر` for "Human"

Stand-alone dropdown value `بشر` for a Bot/Human chip. Could be `بشري` (singular agent noun) to match the adjective form. **Keep current** — `بشر` reads as a collective and that is in fact how the data is grouped (humans collectively vs bots collectively).

### P2-3 (plugin PO lines 800, 802, 828, 832) — `الدخول / الزوار`, `الخروج / الزوار`, `صفحات الدخول`, `صفحات الخروج`

Entry/exit pages: `الدخول` and `الخروج` are correct nouns. Alternative `صفحات الورود / صفحات المغادرة` is more literary. **Keep current** — `دخول/خروج` is unambiguous and consistent with `زر/تسجيل دخول/خروج` UX patterns.

### P2-4 (plugin PO line 696, 938) — Ellipsis `…`

`جارٍ التفعيل…` and `جارٍ الحفظ…`. The ellipsis is the Unicode HORIZONTAL ELLIPSIS `…` (U+2026), matching source. Pass.

### P2-5 (plugin PO line 766) — `آخر %s` for "Last %s"

Translates to "Last [period]". Correct minimalist Arabic; could be `أحدث %s` ("most recent") but `آخر` matches the source register. Keep.

### P2-6 (readme PO line 32) — `لا يغادر شيء خادمك أبدًا`

"Nothing ever leaves your server." Reads idiomatic. Pass.

### P2-7 (readme PO line 36) — `لا توجد شيفرة قياس لتلصقها ولا حساب لتنشئه`

"No tracking code to paste, no account to create." `شيفرة قياس` and `لتلصقها` and `لتنشئه` are all natural. Pass.

### P2-8 (readme PO line 200) — `أعلام الأتمتة`

For "automation flags" — `أعلام` is the standard tech-Arabic gloss of "flags". Pass.

---

## Final tally

| Tier | Plugin PO | Readme PO |
|---|---|---|
| P0 fixed | 1 | 0 |
| P1 noted | 4 | 1 |
| P2 noted | 5 | 3 |
| msgfmt status | clean (cosmetic-header warnings only) | clean (cosmetic-header warnings only) |
| Coverage | 240/241 (1 by-design empty Plugin URI) | 87/87 |

The Arabic translation seed is **ready for publication** on WordPress.org / WP polyglots. All P0 defects have been corrected in-place; P1/P2 entries are stylistic notes for the next translation pass and do not block release.
