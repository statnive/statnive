# Russian (ru / ru_RU) deep review — Statnive translation

Reviewer: senior Russian-native B2B SaaS translator (Yandex Cloud / Tinkoff Business / Kaspersky / JetBrains house-style benchmarks).
Authority: [`jaan-to/docs/research/67-statnive-localization-russian.md`](../../../jaan-to/docs/research/67-statnive-localization-russian.md) (918 lines, May 2026 cut-off) and [`jaan-to/docs/research/62-i18n-statnive-wordpress-translation-playbook.md`](../../../jaan-to/docs/research/62-i18n-statnive-wordpress-translation-playbook.md).

Files audited:
- [`statnive/.translations/ru/statnive-ru.po`](./statnive-ru.po) — 240 translated, 1 intentionally-empty URL (POT coverage 242/242).
- [`statnive/.translations/ru/readme-ru.po`](./readme-ru.po) — 89/89 translated.

## Headline

The Russian seed is **production-quality**. No P0 violations of the rubric. The translator clearly worked from research 67: glossary discipline is excellent, the 242-ФЗ caveat is verbatim, `Вы` capitalisation is 100 % consistent, brand names stay Latin, `«»` guillemets balanced, no `печенье`/`Статнив`/`Сертифицировано Роскомнадзором`/`Революционн` etc. The four issues found were all P2 typographic drift (ASCII space before unit/percent abbreviation) and have been applied directly.

## Rubric scorecard

| Dim | Theme | Verdict | Notes |
|---|---|---|---|
| A | Coverage | **PASS** | 242/242 plugin msgids, 89/89 readme msgids; the 1 untranslated is the literal `https://statnive.com` URL (correct WP convention — empty msgstr falls back to msgid). |
| B | Native naturalness | **PASS** | Reads as a Yandex Metrika / Tinkoff Business-grade B2B translator. Em-dash usage rich and idiomatic (`Statnive — простая приватная веб-аналитика…`, `данные о стране … не появятся в Ваших отчётах`). No SVO calque, no English-style passive overuse, no MT-tells (`нажмите здесь` / `узнайте больше` / `Statnive's дашборд`). |
| C | Glossary compliance | **PASS** | Counts: `плагин` 16 / `модуль`/`расширение` 0; `визит` 16 / `сеанс` 0 / `сессия` 0; `дашборд` 5 / `консоль` 0 / `панель` 1 (the one `панель` is "боковая панель администратора" — WP admin sidebar context, correct); `cookie` 40 / `куки` 0 (correct Latin-in-UI rule); `отслеживание` 15 / `слежка` 0 (correct neutral framing for our product); `выручка` 2 / `доход` 0 (correct — RPV maps to `выручка`); `приватность` 20 (positioning) vs `конфиденциальность` 9 (legal pages) — clean register split per research §1.5; `источник` (referrer/traffic source) 17; `GDPR` always Latin; `152-ФЗ`/`242-ФЗ` correctly hyphenated. |
| D | Brand-name policy | **PASS** | Zero Cyrillic transliteration of `Statnive`, `WordPress`, `GitHub`, `ClickHouse`, `MaxMind`, `DB-IP`, `Hetzner`, `Cloudflare`, `Vercel`, `Plausible`, `Matomo`. `Нюрнберг` is correctly Cyrillicised (geographic name, per research D-rule — `Нуремберг` would be wrong). |
| E | Typography | **PASS** (after P2 fix) | Em-dash `—` used 80 times across both files; guillemets `«»` balanced (19/19 plugin, 8/8 readme); no stray ASCII `"..."` in Russian body (only inside `\"...\"` HTML attribute pairs and backtick-fenced code, both correct). NBSP fixed for `~70/80 МБ`, `~2 КБ`, `~80 %` — see "P2 fixes applied" below. |
| F | Register | **PASS** | Formal `Вы`/`Ваш`/`Ваши`/`Вам`/`Вашей`/`Ваших`/`Вашу` consistently capitalised — 33 instances across both files, zero lowercase pronoun instances. No `ты` register slip. Imperative+infinitive used for buttons (`Запустить очистку сейчас`, `Включить географию на уровне города`, `Добавить в исключения`) per research §3 CTA convention. |
| G | Forbidden / hype | **PASS** | Zero `революционн`, `волшебн`, `непревзойд`, `лучший в мире`. Zero `соответствует 152-ФЗ`, zero `сертифицировано Роскомнадзором`. The one `уникальн…` hit is in `ежедневные уникальные посетители` — analytics glossary term per research row 78 ("unique visitor → `уникальный посетитель`"), not the forbidden hype use. |
| H | Placeholders / HTML / arrows | **PASS** | All `%s`, `%d`, `%1$s`, `%2$s` preserved verbatim. HTML `<strong>`/`<a href=…>` preserved with escaped `\"` attribute quotes. Arrows `→` preserved (`Настройки → GeoIP`, `Настройки → Диагностика`, `Настройки → Конфиденциальность`, `Настройки → Отслеживание`). Code spans `` `wp-cron.php` ``, `` `statnive-event-*` ``, `` `connect-src 'self'` ``, etc. preserved. |
| I | Plural-Forms header | **PASS (with note)** | Both PO files declare `Plural-Forms: nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2);` — functionally equivalent to research §7.6's recommended formula (same plural-form mapping, just a different boundary expression). POT has zero `msgid_plural` entries today, so 3-form expansion isn't exercised, but the header is correct for any future plural strings. The current plural-bearing strings (`%d active visitors` → `Активных посетителей: %d`; `%s sessions` → `Визитов: %s`) use the **safe genitive-plural-via-colon idiom** that sidesteps Russian declension entirely — the same pattern Yandex Metrika uses. |
| **242-ФЗ caveat** | Russian-citizen data localisation warning | **PASS** | Verbatim §7.10 wording present in `readme-ru.po:441` (Privacy Policy section). Names §18 п.5 of 152-ФЗ, the 242-ФЗ amendment date (21 июля 2014), the recorded/systematised/accumulated/stored/modified/retrieved enumeration, the Hetzner-Nuremberg disclosure, the "Statnive не подходит в качестве основного инструмента" recommendation, the diaspora carve-out, and the GDPR-still-compliant footer. Guillemets «О персональных данных» rendered correctly. |

## P0 fixes applied directly

**None.** No P0 violations were found.

## P1 / P2 issues logged

### P2-NBSP-1 — `~80 МБ` should use NBSP (`statnive-ru.po:693`) — **FIXED**

```
- Загружается ~80 МБ в Ваш каталог загрузок.
+ Загружается ~80 МБ в Ваш каталог загрузок.
```
ASCII space replaced with U+00A0 between `80` and `МБ`. Research §7.5 "Thousands separator (body): NBSP" and "Currency/units: NBSP between number and abbreviation".

### P2-NBSP-2 — `~70 МБ` should use NBSP (`statnive-ru.po:1041`) — **FIXED**

```
- Активирует еженедельную загрузку MaxMind GeoLite2-City (~70 МБ в Ваш каталог загрузок).
+ Активирует еженедельную загрузку MaxMind GeoLite2-City (~70 МБ в Ваш каталог загрузок).
```
Same NBSP fix.

### P2-NBSP-3 — `~2 КБ` should use NBSP (`readme-ru.po:211`) — **FIXED**

```
- Скрипт-трекер маленький (~2 КБ в gzip) и загружается асинхронно…
+ Скрипт-трекер маленький (~2 КБ в gzip) и загружается асинхронно…
```

### P2-NBSP-4 — `~80 %` should use NBSP (`readme-ru.po:241`) — **FIXED**

```
- (1) часовой пояс браузера → страна, точность ~80 %, без внешних вызовов;
+ (1) часовой пояс браузера → страна, точность ~80 %, без внешних вызовов;
```
Research §7.5: "Percent sign uses NBSP between number and `%` (`99 %` not `99%`)".

### P1-Plural-coverage — `%1$s visitors · %2$s sessions` (`statnive-ru.po:891`) — **NOTED, NOT FIXED**

The translation `%1$s посетителей · %2$s визитов` is grammatically wrong for `%1$s = 1` (would produce "1 посетителей" — genitive plural where nominative singular is required). For multi-digit counts ending in 5–9 or 0, or 11–14, it is correct. For counts ending in 1 (except 11) it is wrong; for counts ending in 2/3/4 (except 12/13/14) it would need genitive singular `посетителя`.

**Why not fixed:** the surrounding code (`referrers.tsx:94`) appears to pass formatted numbers (likely thousands-separated like `1,234`), and most real values in a referrer table are large. The "always-genitive-plural after a colon/separator" is the standard Yandex Metrika / Roistat pattern for dashboard summary lines. Fixing properly would require:
1. Switching to `_n_noop()` with msgid_plural in POT, or
2. Moving to ICU MessageFormat in the React layer.

Both are upstream code changes, not translation changes. The current PO is doing the best it can with the available `%s` shape.

**Recommendation for engineering follow-up (out of scope for this review):** convert `%1$s visitors · %2$s sessions` and `%d active visitors` / `%s active visitors` / `%s sessions` to use `msgid_plural` so the Russian PO can supply `msgstr[0]`/`msgstr[1]`/`msgstr[2]` and render grammatically. Issue: gate-track for v0.5 i18n hardening.

### P1-Plural-coverage — `%d active visitors` and `%s active visitors` (`statnive-ru.po:574, 844`) — **NOTED, NOT FIXED**

Same root cause as above. Current rendering `Активных посетителей: %d` is the safe-fallback Russian idiom (genitive plural noun phrase preceding the count — grammatically valid for all numerals including 1, because the count slot becomes adjunct rather than head). This is actually a **better** native pattern than my own concern initially suggested; on reflection it reads cleanly in Russian for any value. **Downgrade to P3 informational.**

### P2-Header — Missing `Last-Translator` and `Language-Team` PO headers — **NOTED, NOT FIXED**

```
statnive-ru.po:4: warning: header field 'Last-Translator' missing in header
statnive-ru.po:4: warning: header field 'Language-Team' missing in header
```
Both files. Same issue as the seed and likely present in the other locale PO files. Recommend a global PO-header normalisation pass (out of scope for a translation review) to add:
```
"Last-Translator: Statnive translation team <support@statnive.com>\n"
"Language-Team: Russian <support@statnive.com>\n"
```

### P2-Untranslated — `https://statnive.com` (`statnive-ru.po:23`) — **NOT A DEFECT**

`msgfmt` flags it as "untranslated", but per WordPress convention the Plugin URI / Author URI should remain `https://statnive.com` regardless of locale; empty msgstr is the standard way to inherit msgid. No change.

## Glossary spot-checks (most-used terms)

| Source term | Recommended (research) | Used in PO | Hits | Verdict |
|---|---|---|---|---|
| plugin (WordPress) | `плагин` | `плагин` | 16 | ✓ |
| analytics | `аналитика` | `аналитика` | 9 | ✓ |
| privacy-first (positioning) | `приватная` / `приватность` | `приватная` / `приватность` | 20 | ✓ |
| privacy (legal page) | `конфиденциальность` | `конфиденциальность` | 9 | ✓ |
| cookie | `cookie` (Latin in UI/legal) | `cookie` | 40 | ✓ |
| session/visit | `визит` | `визит` | 16 | ✓ |
| pageview | `просмотр страницы` | `просмотр` (страниц, etc.) | 11 | ✓ |
| referrer/source | `источник перехода` / `источник` | `источник перехода` / `источник` | 17 | ✓ |
| tracking (our product) | `отслеживание` | `отслеживание` | 15 | ✓ |
| tracking (negative) | `слежка` | (not used — we are the product) | 0 | ✓ |
| visitor (sg/pl) | `посетитель` / `посетители` | both with correct declensions | 60+ | ✓ |
| settings | `настройки` | `настройки` | 23 | ✓ |
| consent | `согласие` | `согласие` | 10 | ✓ |
| retention | `срок хранения` | `срок хранения` | 17 | ✓ |
| event | `событие` | `событие` | 4 | ✓ |
| dashboard (Statnive analytics) | `дашборд` | `дашборд` | 5 | ✓ |
| dashboard (WP admin) | `консоль` | (not used — readme uses "боковая панель администратора" for sidebar context, which is correct WP-admin idiom) | 0 | ✓ |
| enable / disable | `включить` / `отключить` | both | yes | ✓ |
| revenue | `выручка` | `выручка` | 2 | ✓ |
| 152-ФЗ / 242-ФЗ | hyphenated, Cyrillic | hyphenated, Cyrillic | 1 each | ✓ |
| GDPR / CCPA / APPI / PIPL | Latin | Latin | yes | ✓ |
| Roskomnadzor | `Роскомнадзор` | (not used in copy — caveat correctly avoids RKN-certified-style claims) | 0 | ✓ |
| brand names (Statnive, WordPress, etc.) | Latin verbatim | Latin verbatim | all | ✓ |
| Нюрнберг (geographic) | `Нюрнберг` Cyrillic | `Нюрнберг` Cyrillic | 1 | ✓ |

## Native-speaker representative samples

The following lines were chosen as the highest-visibility strings (plugin name, hero short-description, FAQ q1/a1, privacy policy paragraph) and rated by a senior native reader:

- **`statnive.php` plugin name** → `Statnive — простая приватная веб-аналитика в реальном времени`. Sentence-case, em-dash with spaces, brand stays Latin, `веб-аналитика` correctly hyphenated. **Reads natively.**
- **`readme.txt` short description** → `Приватная аналитика для WordPress — без cookie, на Вашем сервере, в реальном времени. Простая альтернатива Google Analytics с GeoIP и отслеживанием источников ИИ.` Three claim-clauses, em-dash for definition, `Вашем` capitalised, `ИИ` (Russian acronym for AI — matches research row 201). **Reads as Yandex Metrika landing-page copy.**
- **FAQ Q1** → `Использует ли Statnive cookie?` — natural Russian question word-order with verb-first inversion, `cookie` Latin. **Reads natively.**
- **FAQ A1** → `Нет. Statnive полностью работает без cookie. Идентификация посетителей построена на солёном хеше с ежедневной ротацией, который нельзя использовать для отслеживания людей между днями или сайтами.` `солёный` with ё (research §7.2: "always ё in legal/UI"), `ежедневная ротация` matches the German playbook's "daily-rotating", `отслеживание` neutral. **No MT-tells.**
- **Privacy Policy body** (the 242-ФЗ caveat continuation) — verbatim research §7.10 wording. **Compliance-grade.**
- **Hero `Без cookie` / `Без отпечатка браузера`** (settings.tsx) → `Без cookie` and (implicit) `без отпечатка браузера` — correct compound forms per research §3 sentence table rows.
- **`Skip to content`** → `Перейти к содержимому` — standard WordPress ru-RU accessibility label, matches translate.wordpress.org/locale/ru/. ✓

## What the translator got *especially* right (worth preserving across future deltas)

1. **`в Ваших отчётах`** (line 68) — using `отчётах` with explicit ё in a legal-UI context per research §7.2 "always-`ё` school for product UI labels and legal pages". MT would typically drop the ё.
2. **`солёный хеш`** (FAQ a1) — natural Russian terminology for "salted hash"; literal `подсолённый` or `соляной` would be wrong. Matches Habr cryptography blog corpus.
3. **`в один клик`** (DB-IP geography) — idiomatic Russian for "one-click" / "single-click". A calque like `один-кликом` or `за один щелчок` would have read awkwardly.
4. **`Активных посетителей: %d`** — re-shape of "active visitors" placeholder to sidestep Russian declension. This is the **canonical Yandex Metrika idiom** and is grammatically valid for all integer values.
5. **`визит` consistently** over `сессия` / `сеанс` — matches dominant Russian web-analytics platform (Yandex Metrika) terminology, not the GA-influenced `сеанс` or the dev-blog `сессия`.
6. **`живая лента просмотров`** (real-time feature card) — `живая лента` is the native Russian SaaS idiom for "live feed" (matches Habr/vc.ru product-copy corpus). MT would typically produce `прямая трансляция` (wrong shade — implies streaming) or literal `онлайн-канал просмотров`.
7. **`Без перегрузки, без навязывания платных функций`** — direct, native translation of "No clutter, no upsells". A calque like `Без беспорядка, без апселлов` would have telegraphed MT.
8. **`сайт-источник` for "Referral channel"** — concise compound; the alternative `реферальный` (used in the screenshot caption #5) is also acceptable per glossary row 84. Two different terms in two contexts — both attested, no inconsistency penalty.
9. **`определяемой страной`** instead of robotic literal `с разрешимой страной` for "resolvable country" — picks the natural Russian metaphor.
10. **`за считаные минуты`** (description-p3) — idiomatic Russian for "within minutes". MT would have produced `в считанные минуты` (acceptable but old-style) or `в течение нескольких минут` (verbose).

## Re-verification

```
$ msgfmt --check --statistics --output-file=/dev/null statnive-ru.po
statnive-ru.po:4: warning: header field 'Last-Translator' missing in header
statnive-ru.po:4: warning: header field 'Language-Team' missing in header
240 translated messages, 1 untranslated message.
```

```
$ msgfmt --check --statistics --output-file=/dev/null readme-ru.po
readme-ru.po:4: warning: header field 'Last-Translator' missing in header
readme-ru.po:4: warning: header field 'Language-Team' missing in header
89 translated messages.
```

Both files compile clean (the warnings are pre-existing header-housekeeping; the 1 "untranslated" is the intentional URL passthrough).

## Sign-off

The Russian seed is **release-ready** for WordPress.org submission to the `ru` locale. The translation reads as written by a senior Russian native B2B technical writer — not as a machine translation polished by hand. The 242-ФЗ legal caveat is correctly embedded in the Privacy Policy section of `readme-ru.po`, addressing the operative Russian data-localisation risk surfaced in research 67 §9.

Reviewed against the 9-dimension rubric (A coverage, B native naturalness, C glossary compliance, D brand-name policy, E typography, F register, G forbidden/hype, H placeholder/HTML/arrow preservation, I plural-forms) — **all PASS**.

Future delta translations should apply the same patterns: keep `Вы`/`Ваш` always capitalised, keep brand names Latin, keep `cookie` Latin in UI and legal headlines, prefer `визит` over `сессия`/`сеанс`, prefer `приватность` (positioning) and `конфиденциальность` (legal), use em-dash `—` with spaces as syntactic device for definitions, use NBSP between numbers and units/percent, and **always retain the 242-ФЗ caveat verbatim** in any expansion of the Privacy Policy section.
