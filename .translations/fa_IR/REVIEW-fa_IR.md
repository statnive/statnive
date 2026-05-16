# Persian (fa_IR) Translation Deep Review — Statnive

Reviewer: senior Persian (fa_IR) native-speaker translation reviewer
Audience: pan-Persian (diaspora + Iran + Afghan Dari)
Date: 2026-05-16
Plugin version: 0.4.13
Source POT: `statnive/languages/statnive.pot` (240 strings, plus 1 plugin-URI exception)
Readme source: `statnive/readme.txt` (84 strings)

## Top-line verdict

- **Hygiene: clean.** Zero Arabic letterforms (no `ك`/`ي`), zero Arabic-Indic digits (`٠–٩`), zero Persian-Indic digits (`۰–۹`) anywhere in either PO file. Western digits used consistently per CLAUDE.md fa-mirror invariant.
- **ZWNJ usage: strong.** All obvious compounds (`وب‌سایت`, `حریم‌خصوصی‌محور`, `به‌روزرسانی`, `پیش‌فرض`, `بازدیدکننده‌ها`, `داده‌ها`, `خودمیزبان`, `بی‌درنگ`, `ناشناس‌سازی`, `پاک‌سازی`) carry the correct U+200C. A handful of past-participle compounds had a normal space instead of ZWNJ; fixed in this review.
- **Coverage: complete.** 240/241 plugin msgids translated (only the Plugin URI is empty, per rubric); 84/84 readme msgids translated.
- **Glossary: faithful to research-48.** `افزونه` (not `پلاگین`), `حریم خصوصی` / `حریم‌خصوصی‌محور`, `اندازه‌گیری` for own product (NOT `ردیابی`), `بازدیدکننده`, `داشبورد` for Statnive's analytics view, `بدون کوکی`, `خودمیزبان`, `cron` Latin, `salt` Latin. Forbidden words `محرمانگی`, `می‌باشد`, `جهت`, `لذا`, `بواسطه` do not appear.
- **Brand-name policy: clean.** `Statnive`, `WordPress`, `WooCommerce`, `GeoIP`, `MaxMind`, `DB-IP`, `Google Analytics`, `GitHub`, `ChatGPT`, `Claude`, `Gemini`, `MCP`, `LCP`, `KB`, `MB`, `API`, `IP`, `cron`, `WP-CLI`, `Cookie API`, `ClickHouse` all stay Latin. No `استتنایو` / `وردپرس` / `گیت‌هاب` leakage anywhere.
- **Register: formal pan-Persian.** Uses `شما` and second-person plural verbs throughout. No `تو`, no Tehrani slang (`می‌تونی`, `می‌شه`), no over-Arabicised literary register.
- **Forbidden compliance claims: none.** No `جمهوری اسلامی`, no `قوانین ایران`, no fake Iranian privacy-law references.
- **Placeholders + HTML: perfect.** `%s`, `%d`, `%1$s`, `%2$s`, `<strong>`, `<a href=…>`, `<code>` all preserved byte-for-byte across all strings.
- **RTL adaptation: appropriate.** The English `→` (LTR arrow) used in breadcrumbs (`Settings → GeoIP`, `Settings → Diagnostics`, `Settings → Privacy`) is consistently rendered as `←` (the visually-equivalent RTL arrow), so the breadcrumb still points "downstream" in the RTL flow. Per the rubric this is correct RTL behaviour, not an error.

## A. Coverage

| File | POT msgids | Translated | Missing | Notes |
|---|---|---|---|---|
| Plugin PO (`statnive-fa_IR.po`) | 241 | 240 | 1 | The 1 missing msgstr is `https://statnive.com` (Plugin URI/Author URI) — rubric exception; expected. |
| Readme PO (`readme-fa_IR.po`) | 84 | 84 | 0 | All description / why / features / installation / FAQ / screenshots / external-services / privacy-policy chunks translated. |

`msgfmt --check` exits clean on both files. The only `msgfmt` warnings are `Last-Translator` and `Language-Team` header fields not yet populated; these are seed-stage placeholders that the WordPress.org Polyglots flow fills in automatically when the locale's PTE submits the first batch.

## B. Native naturalness

The Persian reads as a competent technical SaaS writer, comparable to fa.wordpress.org register and Persian SaaS marketing copy on ArvanCloud / StatsFA / Hamyarwp. Sentence rhythm is two-clause-friendly (`آمار ساده، تصمیم‌های روشن.`). No machine-translation tells like literal `می‌تواند به شما کمک کند تا...`, no English-shaped possessive (`Statnive's داشبورد`), no over-Arabicised register. Persian SOV verb-final order is respected throughout (`Statnive به PHP %1$s یا بالاتر نیاز دارد.`).

Calques are minimal and limited to a few stylistic choices flagged as P1/P2 below.

## C. Glossary compliance (research-48 + research-62)

| Term | Recommended | In translation | Status |
|---|---|---|---|
| plugin | `افزونه` | `افزونه` (always) | ok |
| tracking (own product) | `اندازه‌گیری` | `اندازه‌گیری` | ok |
| tracking (third-party) | `ردیاب` | `ردیاب‌های شخص ثالث` (line 24 readme) | ok |
| dashboard (Statnive) | `داشبورد` | `داشبورد` | ok |
| visitor | `بازدیدکننده` | `بازدیدکننده` / `بازدیدکنندگان` | ok |
| privacy | `حریم خصوصی` | `حریم خصوصی` | ok |
| privacy-first | `حریم‌خصوصی‌محور` | `حریم‌خصوصی‌محور` | ok |
| settings | `تنظیمات` | `تنظیمات` | ok |
| cookie | `کوکی` | `کوکی` | ok |
| cookieless | `بدون کوکی` | `بدون کوکی` | ok |
| self-hosted | `خودمیزبان` | `خودمیزبان` | ok |
| update | `به‌روزرسانی` | `به‌روزرسانی` | ok |
| documentation | `مستندات` | (not in current corpus) | n/a |
| support | `پشتیبانی` | (not in current corpus) | n/a |
| real-time | `بی‌درنگ` / `بلادرنگ` / `در لحظه` | `بی‌درنگ` (chosen) | ok |
| Imprint | `اطلاعات ناشر (Imprint)` | (not in current corpus) | n/a |

No forbidden synonyms (`ردیابی` for own product, `محرمانگی`, `پلاگین`, `بروزر`, etc.) appear.

## D. Brand-name policy

All thirteen AI-assistant brand names in the readme (line 64) stay Latin: `ChatGPT`, `Claude`, `Gemini`, `Perplexity`, `Copilot`, `NotebookLM`, `Meta AI`, `Le Chat`, `Deepseek`, `You`, `iAsk`, `Jasper`, `Writesonic`. Likewise `Real Cookie Banner`, `Complianz`, `CookieYes`, `Cloudflare`, `CloudFront`, `Vercel`, `Composer`. No transliteration of `Statnive` to `استتنایو`. No transliteration of `WordPress` to `وردپرس` anywhere in the user-visible strings (the rubric notes one historic instance in short description was already fixed; double-check pass shows no other recurrences).

## E. Typography

- **Persian letterforms.** `ک` (U+06A9) and `ی` (U+06CC) are used universally. Zero counts of Arabic `ك` (U+0643) and `ي` (U+064A).
- **ZWNJ (U+200C).** Used in compounds, `می‌` and `نمی‌` prefixes, plural `‌ها`, possessive `‌ات/‌اش/‌ام` after `ـه`, and past-participle constructions. P0 fixes applied to three past-participles that originally had a normal space — see "P0 fixes" below.
- **Persian-specific letters.** `پ`, `چ`, `ژ`, `گ` appear where appropriate (`پشتیبانی`, `چرخش`, `چنانچه`, `گرفتن`, `پیشخوان`, `پنهان`).
- **Punctuation.** Persian comma `،` (U+060C) used in body prose. Persian semicolon `؛` (U+061B) used in MaxMind FAQ for clause separation. Persian question mark `؟` (U+061F) used in FAQ headings (`آیا Statnive از کوکی استفاده می‌کند؟`). Persian guillemets `«…»` used for quoted button labels (`«اکنون پاک‌سازی را اجرا کن»`, `«همیشه»`, `«composer install»`, `«بدون داده»`). ASCII punctuation appears only inside backticks / URLs / HTML attributes.
- **Numerals.** Western digits `0-9` are used consistently in body prose (`30 روز`, `90 روز`, `180 روز`, `1 سال`, `2 KB`, `10 دقیقه`, `100`, `80`). Per CLAUDE.md fa-mirror invariant, this is the mandated policy. Zero Persian-Indic `۰–۹` and zero Arabic-Indic `٠–٩` leakage.

## F. Register

- Formal `شما` throughout (lines 124, 138, 156, 169, 176, 389, 393, 405, 449, …). No `تو` / `تون` / `می‌تونی` slang.
- Imperative plural for CTAs and one-liners: `نصب کنید`, `فعال کنید`, `بارگذاری کنید`, `وارد کنید`, `بررسی کنید`, `اضافه کنید`, `ببینید`, `پاک کنید`.
- No overly bureaucratic / Arabicised register. `می‌باشد` / `می‌گردد` / `جهت` / `لذا` are absent.
- Pan-Persian vocabulary — no Tehrani slang. Diaspora, Iranian and Afghan Dari readers should all parse the strings without friction.

## G. Forbidden words

- `ردیابی` for own product: not found. The two `ردیاب` instances refer to **third-party trackers** (`ردیاب‌های شخص ثالث`, `هیچ ردیابی برای پنهان کردن`) — correct per glossary §9.
- `ردگیری`: not found.
- Hype words (`معجزه`, `انقلابی`, `بی‌نظیر`, `قدرت بی‌نهایت`, `نهایی`): not found.
- `بهترین` appears twice, both as literal translations of legitimate English source phrases (`best content` → `بهترین محتوای خود`, `best practices` → `بهترین روش‌ها`). Source-driven, not promotional hype.
- Iranian compliance claims: not found.

## H. Placeholder, HTML and arrow preservation

- All `%s`, `%d`, `%1$s`, `%2$s` placeholders preserved.
- All `<strong>…</strong>`, `<a href="%1$s" target="_blank" rel="noopener">…</a>`, `<code>…</code>` tags preserved byte-for-byte.
- Bold `**…**` markdown preserved in readme strings.
- Backtick code spans (`statnive_`, `wp statnive cron run`, `DISABLE_WP_CRON`, `/wp-json/statnive/v1/hit`, `admin-ajax.php?action=statnive_hit`, `connect-src 'self'`, `fetch`, `sendBeacon`, `statnive-event-*`) preserved.
- The `→` LTR arrow in `Settings → GeoIP` / `Settings → Diagnostics` / `Settings → Privacy` becomes `←` (RTL-flipped) in the Persian rendering. Breadcrumb semantic is intact (parent ← child reads correctly in RTL flow). Not an error.
- Em-dash `—` (U+2014) preserved unchanged.
- Markdown link `[github.com/statnive/statnive](https://github.com/statnive/statnive)` and `[statnive.com](https://statnive.com)` preserved verbatim.

## I. Plurals

POT contains zero `_n()` / `_nx()` plural calls. The Persian PO correctly contains zero `msgid_plural` / `msgstr[0]` / `msgstr[1]` blocks; `nplurals=2; plural=(n > 1);` is set in the header for forward compatibility. Status: clean.

---

## P0 fixes applied during this review

Four edits, all to the plugin PO except one to the readme. All are ZWNJ-in-past-participle and one calque cleanup; total bytes touched ≈ 60. msgfmt re-validated clean afterwards.

| File | Line | Before | After | Reason |
|---|---|---|---|---|
| `statnive-fa_IR.po` | 270 | `Statnive به‌صورت طراحی‌شده از هیچ کوکی، localStorage یا sessionStorage استفاده نمی‌کند.` | `Statnive بنا به طراحی از هیچ کوکی، localStorage یا sessionStorage استفاده نمی‌کند.` | `به‌صورت طراحی‌شده` is a calque from "by design". `بنا به طراحی` is the natural Persian construction (used by ArvanCloud, Yektanet, MihanWP). |
| `statnive-fa_IR.po` | 236 | `صفحه سیاست حریم خصوصی منتشر شده است.` | `صفحه سیاست حریم خصوصی منتشرشده است.` | ZWNJ in past-participle compound `منتشرشده`, for consistency with line 240's `منتشرشده‌ای`. |
| `statnive-fa_IR.po` | 501 | `نگهداری داده‌ها با پاک‌سازی خودکار پیکربندی شده است.` | `نگهداری داده‌ها با پاک‌سازی خودکار پیکربندی‌شده است.` | ZWNJ in past-participle `پیکربندی‌شده` per research-48 §7.1 ("past-participle constructions: `ساخته‌شده`, `بهینه‌شده`, `پیکربندی‌شده`"). |
| `readme-fa_IR.po` | 132 | `Statnive **طراحی شده است تا از** انطباق با GDPR، …` | `Statnive **طراحی‌شده است تا از** انطباق با GDPR، …` | ZWNJ in `طراحی‌شده` for consistency with `طراحی‌شده` already used at lines 40 and 957. |

Tally: **4 P0 fixes** (Plugin PO: 3, Readme PO: 1).

## P1 notes (naturalness — recommended for next pass)

These are style polishes that improve fluency but do not block release.

| File | Line | Current | Suggestion | Reason |
|---|---|---|---|---|
| `readme-fa_IR.po` | 24 | `بدون ارسال داده بازدیدکنندگان به هیچ‌کس` | `بدون فرستادن داده بازدیدکنندگان به جایی` | `هیچ‌کس` (no-one, person-oriented) is mildly anthropomorphising — third-party services aren't people. `جایی` reads more idiomatically. P1, optional. |
| `readme-fa_IR.po` | 52 | `بدون شلوغی، بدون پیشنهادهای فروش اضافی.` | `بدون شلوغی، بدون اصرار به ارتقا.` | "No upsells" — `پیشنهادهای فروش اضافی` is verbose for a feature bullet. `اصرار به ارتقا` (no pushing to upgrade) is tighter and matches Persian SaaS norm. P1. |
| `readme-fa_IR.po` | 124 | `…نمی‌توان از آن برای پیگیری افراد در طول روزها یا وب‌سایت‌ها استفاده کرد.` | `…نمی‌توان از آن برای ردیابی افراد در طول روزها یا وب‌سایت‌ها استفاده کرد.` | This is the negative-framing branch of glossary §9 (Statnive's hash being **impossible** to use for tracking is the privacy assertion — that's the surveillance frame). `ردیابی` here is on-brand because we are denying a surveillance-style capability. `پیگیری` is less precise. P1. |
| `statnive-fa_IR.po` | 644 | `ربات در برابر انسان` | `ربات یا انسان` | The chart label is a category split, not an adversarial framing. `یا` reads more naturally in a dashboard tab. P1. |
| `statnive-fa_IR.po` | 305 | `%d جلسه آنالیتیکس ناشناس‌سازی شد. آمار تجمیعی حفظ شد.` | `%d جلسه آنالیتیکس ناشناس‌سازی شد؛ آمار تجمیعی حفظ شد.` | Persian semicolon `؛` better matches the source's compound-sentence flow than a period; the second clause expands the first, not a new thought. P1. |
| `statnive-fa_IR.po` | 1041 | `دانلود هفتگی MaxMind GeoLite2-City را فعال می‌کند (حدود 70 MB در پوشه بارگذاری‌های شما).` | `دانلود هفتگی MaxMind GeoLite2-City را فعال می‌کند (حدود ۷۰ مگابایت روی پوشه بارگذاری شما).` — **only if** the digit policy ever flips back to Persian-Indic. Under current Western-digit invariant, keep as-is. | Cosmetic / future-policy. |
| `statnive-fa_IR.po` | 132–135 (kpi-card) | `تغییر %1$s %2$s نسبت به دوره قبلی` (with `%2$s = افزایش / کاهش`) | Refactor the React source to use a templated string with a single `%s` for direction so the Persian word order can match (`نسبت به دوره قبلی، تغییر %1$s %2$s`). | Source-code-level concern; the translation is the best possible given the current English template. P1 — flagged to the engineering side for a future POT bump. |

Tally: **6 P1 notes**.

## P2 notes (stylistic / consistency)

| File | Line | Current | Note |
|---|---|---|---|
| `readme-fa_IR.po` | 124, 188 | `100٪`, `80٪` | The translator chose Persian percent sign `٪` (U+066A) while digits are Western. Research-48 §24 permits either `٪` or `%`. For strict consistency with the Western-digit invariant, consider migrating to Western `%`. Borderline P2. |
| `readme-fa_IR.po` | 16 | `آنالیتیکس WordPress حریم‌خصوصی‌محور` | Word order is Latin-shaped (English: "WordPress analytics"). A more Persian-flow phrasing would be `آنالیتیکس حریم‌خصوصی‌محور برای WordPress` (mirrors the readme description heading at line 20). P2. |
| `readme-fa_IR.po` | 48 | `**hashهای salt‌دار با چرخش روزانه**` | The plural attached directly to a Latin token (`hashهای`) is a known Persian fa.wp.org pattern but reads slightly awkward. Alternative: `**hashهای salt‌خورده با چرخش روزانه**`. P2. |
| `statnive-fa_IR.po` | 465 | `بازرسی حریم خصوصی` | `بازرسی` (inspection) is fine; `ممیزی` (audit) is the more standard analytics-domain term. P2. |
| `statnive-fa_IR.po` | 56 | `پاک‌سازی نگهداری داده‌ها` | "Retention cleanup". The current phrasing is acceptable but slightly literal. `پاک‌سازی داده‌های قدیمی` (cleanup of old data) is what the cron actually does — clearer for the admin reading the Site Health row. P2. |
| `statnive-fa_IR.po` | 287 | `cron پاک‌سازی داده‌ها زمان‌بندی نشده است. افزونه را غیرفعال و دوباره فعال کنید.` | Could be tightened: `زمان‌بندی cron پاک‌سازی داده‌ها وجود ندارد. افزونه را غیرفعال و دوباره فعال کنید.` P2 cosmetic. |
| `statnive-fa_IR.po` | 305 | `ناشناس‌سازی شد` | Past-participle space-vs-ZWNJ here is technically a passive past tense (`X ناشناس‌سازی شد`) where `شد` is a separate verb. Both forms are accepted. P2. |
| `readme-fa_IR.po` | 64 | `شبکه اجتماعی پولی` | "Paid Social" channel. The naming is fine; for consistency with Persian SaaS analytics jargon, `سوشال پولی` is also seen, but `شبکه اجتماعی پولی` is clearer and matches the rest of the channel names in the same string. P2 — keep as-is. |

Tally: **8 P2 notes**.

---

## Reviewer note on the digit-policy collision

CLAUDE.md (fa-mirror invariant) and research-48 §24 differ on the body-prose digit policy:

- **CLAUDE.md**: Western digits everywhere (`30 روز`, not `۳۰ روز`); hygiene grep aborts on Persian-Indic.
- **Research-48 §7.3 / §24**: Persian-Indic digits `۰–۹` recommended for body prose, Western for code; calls Western digits an "acceptable second-best".

The plugin PO follows the CLAUDE.md policy (Western digits in body prose). This is the **authoritative** choice for the fa-mirror because:
1. The CLAUDE.md fa-mirror invariant is repo-policy and runs in CI (the sync hygiene grep aborts on Persian-Indic leakage).
2. The plugin runs inside the WP admin where Western numerals are already mixed with Persian (`PHP 8.1`, version strings, CIDR blocks) and Western digits there are uncontroversial.
3. The diaspora-first audience (per CLAUDE.md fa-mirror brief) is more uniformly fluent in Western numerals than Iran-only audiences.

Translators submitting via translate.wordpress.org should keep Western digits. If a future Iran-domestic-priority pivot happens, the policy should flip in CLAUDE.md and research-48 simultaneously, then a one-shot conversion script can update the PO.

---

## Final state

| | Plugin PO | Readme PO |
|---|---|---|
| msgfmt | clean (240 translated, 1 untranslated = Plugin URI exception) | clean (84 translated) |
| Header warnings | `Last-Translator`, `Language-Team` missing — fills in via Polyglots flow | same |
| P0 fixes applied | 3 | 1 |
| P1 noted | 5 | 1 (plus 1 cross-file React-source note) |
| P2 noted | 5 | 3 |
| Hygiene | clean (zero Arabic letterforms, zero Persian-Indic digits, zero Arabic-Indic digits) | same |
| Placeholder integrity | 100% | 100% |
| HTML integrity | 100% | 100% |
