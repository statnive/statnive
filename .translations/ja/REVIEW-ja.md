# Statnive Japanese (ja) Translation — Deep Review

**Reviewer:** senior Japanese native-speaker translation reviewer
**Date:** 2026-05-16
**Scope:** `statnive/.translations/ja/statnive-ja.po` (plugin), `statnive/.translations/ja/readme-ja.po` (readme)
**Authoritative sources:**
- `jaan-to/docs/research/50-statnive-localization-japanese.md` (Japanese style guide)
- `jaan-to/docs/research/62-i18n-statnive-wordpress-translation-playbook.md` (i18n stack + GlotPress workflow)
- `statnive/languages/statnive.pot`, `statnive/readme.txt` (English source of truth)

---

## TL;DR

The Japanese seed is in **good shape overall** — registers, glossary terms, and the privacy framing are correct. The translator avoided the most common MT-tells (no `することができます` overuse, no `あなたの〜`, no `クッキー` in tech contexts, no `追跡` for Statnive's own product, no over-claimed APPI compliance). The main issues found and fixed (P0) are typography drift around **paren width** and **a stray ASCII `?` with a leading half-width space** — both byproducts of seed-file mechanics rather than translation quality. After P0 fixes, both PO files compile cleanly via `msgfmt --check`.

**Plugin PO (statnive-ja.po):** 240 / 241 strings translated (Plugin URI intentionally empty). All placeholders, HTML tags, and arrows preserved. P0 fixes applied: **9**. P1 noted: **4**. P2 noted: **2**.
**Readme PO (readme-ja.po):** 87 / 87 strings translated. P0 fixes applied: **22**. P1 noted: **3**. P2 noted: **2**.

---

## msgfmt result

```
$ msgfmt --statistics --check --output-file=/dev/null statnive-ja.po
statnive-ja.po:4: warning: header field 'Last-Translator' missing in header
statnive-ja.po:4: warning: header field 'Language-Team' missing in header
240 translated messages, 1 untranslated message.

$ msgfmt --statistics --check --output-file=/dev/null readme-ja.po
readme-ja.po:4: warning: header field 'Last-Translator' missing in header
readme-ja.po:4: warning: header field 'Language-Team' missing in header
87 translated messages.
```

Both files: **clean** (only the harmless `Last-Translator` / `Language-Team` warnings, identical across all locale seeds). The 1 untranslated message in `statnive-ja.po` is `https://statnive.com` (Plugin URI), which is intentionally empty per WordPress.org convention — URIs never translate.

---

## Dimension A — Coverage

| File | Total | Translated | Untranslated | Notes |
|---|---:|---:|---:|---|
| statnive-ja.po | 241 | 240 (99.6 %) | 1 | Plugin URI by design |
| readme-ja.po | 87 | 87 (100 %) | 0 | All readme sections covered |

**Verdict:** Pass. Coverage matches the POT delta exactly; no missing surfaces.

---

## Dimension B — Native naturalness

**Strong points:**
- No `〜することができます` padding; `できます` used throughout (e.g. `閲覧できます`, `削除できます`, `推定することがあります`). Matches research §7.14.
- Zero-pronoun grammar dominant — no overuse of `あなたの〜`. Subjectless sentences (e.g. `IP アドレスはハッシュ化と GeoIP ルックアップの直後に破棄されます。`) read as native JP technical writing.
- No `非常に` over-use; no `素晴らしい` / `驚異の` / `シームレス` MT crutches.
- Modern long-vowel forms throughout: `サーバー` (not サーバ), `ユーザー` (not ユーザ), `プラグイン`, `ダッシュボード`, `ブラウザ` (acceptable tech form; both `ブラウザ` and `ブラウザー` are dominant).
- 漢字/かな/カタカナ balance feels natural — no over-kana cheapness, no over-kanji bureaucratic stiffness.
- Headlines/buttons consistently use 体言止め:
  - `Statnive アクセス解析`, `デバイス分布`, `アクティブな訪問者`, `除外設定`, `MaxMind ライセンスキー` — all noun phrases.
  - Body / Site Health sentences correctly switch to です・ます (`〜が設定されています`, `〜は有効です`).

**P1 — minor register drift:**
- `アカウントなしで使いたい場合は、地域ページのワンクリック DB-IP IP-to-City Lite ダウンロードをご利用ください` (settings.tsx:349) — **P0 fixed** to `お使いください`. `ご利用ください` is on the avoid-list in research §7.14 ("too imperative-polite for SaaS"). `お使いください` is the recommended SaaS register.
- `本日` vs `今日` inconsistency for "Today": admin-bar widget (`Statnive — 本日の訪問者`, line 38) uses 本日 while the Overview page dropdown (`Today` → `今日`, line 759) uses 今日. Both are defensible — admin-bar prefers 本日 (more formal label), Overview date-range chip prefers 今日 (informal). Recommend acknowledging this is a deliberate UX split; otherwise unify to 今日 across both surfaces for consistency.

**P2 — stylistic suggestions:**
- "Help text after a dash" pattern in error states (e.g. `自社チームを除外する際に便利です — `) uses the em-dash from English source. Idiomatic JP would more often use `。` + sentence break. Kept as-is because the source format uses em-dash explicitly and the rendered effect is similar.

---

## Dimension C — Glossary compliance (research 50)

| Term (EN) | Recommended JP | Found in seed | Status |
|---|---|---|---|
| analytics (head term) | アクセス解析 | アクセス解析 | ✓ |
| analytics (branded) | Latin/`アナリティクス` | `Statnive Analytics` → `Statnive アクセス解析` | ✓ |
| tracking (own product) | 計測 | `計測スクリプト`, `データを計測します`, `計測リクエスト` | ✓ |
| tracking (3rd party) | 追跡 | `第三者の追跡スクリプト` (readme:28) | ✓ (one valid hit) |
| dashboard | ダッシュボード | ダッシュボード | ✓ |
| visitor / visitors | 訪問者 | 訪問者 (consistent) | ✓ |
| cookie | `Cookie` (Latin) | `Cookie 不要`, `Cookie バナー`, `Cookie・localStorage` — Latin only | ✓ |
| cookie-less | Cookie 不要 | `Cookie 不要` | ✓ |
| privacy-first | プライバシー重視 | プライバシー重視 (consistent) | ✓ |
| self-hosted | セルフホスト | (not in plugin strings; readme: `セルフホスト`) | ✓ |
| Imprint | 運営者情報 | n/a (no Imprint strings in plugin POT) | n/a |
| WordPress site owner | WordPress サイト運営者 | n/a (no SaaS hero strings in plugin POT) | n/a |
| fingerprinting | フィンガープリンティング | `ブラウザフィンガープリンティング`, `フィンガープリンティング` | ✓ (never `指紋認証`) |
| server | サーバー | サーバー (long vowel) | ✓ |
| browser | ブラウザ | ブラウザ (tech-context default) | ✓ |
| consent | 同意 | 同意 (no 承諾) | ✓ |
| opt-in / opt-out | オプトイン/オプトアウト | `オプトイン方式` (readme:192) | ✓ |
| EU/EEA | EU/EEA Latin | n/a | n/a |
| installation | インストール | インストール | ✓ |
| activate | 有効化 | 有効化 (matches ja.wordpress.org) | ✓ |
| settings | 設定 | 設定 | ✓ |
| update | 更新 | `週次更新` | ✓ |
| referrer | リファラー | リファラー (long vowel) | ✓ |
| sessions | セッション | セッション | ✓ |
| channels | チャネル | `スマートなチャネルグルーピング` | ✓ (not チャンネル) |

**Verdict:** Full glossary compliance. The seed correctly distinguishes Statnive's own `計測` from competitor 3rd-party `追跡` (the one `追跡スクリプト` instance in `readme-ja:28` is in the context describing what Statnive does NOT do — i.e. the competitor surveillance behavior — which is the correct case per research §2 glossary row "tracking (competitor surveillance)").

---

## Dimension D — Brand-name policy

All brand and tech names preserved in Latin, exact case:
- `Statnive`, `statnive.live`, `statnive_`, `WordPress`, `WooCommerce`, `GeoIP`, `MaxMind`, `DB-IP`, `Google Analytics`, `GA4`, `Matomo`, `ChatGPT`, `Claude`, `Gemini`, `Perplexity`, `Copilot`, `NotebookLM`, `Meta AI`, `Le Chat`, `Deepseek`, `You`, `iAsk`, `Jasper`, `Writesonic`, `Cloudflare`, `CloudFront`, `Vercel`, `GitHub`, `Real Cookie Banner`, `Complianz`, `CookieYes`.
- Acronyms in Latin: `KB`, `MB`, `API`, `IP`, `IPv6`, `HTTPS`, `CIDR`, `CSP`, `CDN`, `EULA`, `GDPR`, `CCPA`, `APPI`, `PIPL`, `DNT`, `GPC`, `RPV`, `WP-Cron`, `WP-CLI`, `JavaScript`, `Cookie`, `localStorage`, `sessionStorage`.
- File paths and code tokens kept in code-block/Latin form: `/wp-content/plugins/`, `wp-cron.php`, `/wp-json/statnive/v1/hit`, `admin-ajax.php?action=statnive_hit`, `statnive_*`, `DISABLE_WP_CRON`, `statnive-event-*`, `connect-src 'self'`, `fetch`, `sendBeacon`.

No `スタットナイブ`, no `ワードプレス`, no `ウーコマース`, no other katakana transliterations of brand names. **Verdict:** Pass.

---

## Dimension E — Typography (critical)

**Full-width punctuation (`。 、 （ ） 「 」`) inside JP-only segments — observed and correct in most places.**

**P0 fixes applied (paren width):** The seed had **15 occurrences** of half-width parens wrapping JP-dominant content. Per research §7.2 and rule #6, half-width `()` is reserved for **bare Latin acronyms** inside JP prose; JP-context content gets full-width `（）`. Fixes applied:

`statnive-ja.po`:
1. Line 78 (`%2$s モード`) → `（%2$s モード）` *— placeholder + JP*
2. Line 325 `(秒)` → `（秒）`
3. Line 365 `(クエリパラメーターを除く)` → `（クエリパラメーターを除く）`
4. Line 369 `(ドメインのみ、クエリパラメーターは削除)` → `（…）`
5. Line 373 `(IP から取得され、即時に破棄されます)` → `（…）`
6. Line 1001 `CIDR (例: 10.0.0.0/8) と IPv6` → `CIDR（例: 10.0.0.0/8）と IPv6`
7. Line 1041 `(アップロードディレクトリに約 70 MB)` → `（…）`

`readme-ja.po`:
8. Line 72 `(および … クラスでタグ付けされたボタンクリック)` → `（…）`
9. Line 80 `(無料)` → `（無料）`
10. Line 152 `(一度きりのデータベースファイルであり、訪問者データではありません)` → `（…）`
11. Line 168 `(gzip 圧縮後で約 2 KB)` → `（…）`
12. Line 184 `(\`connect-src 'self'\` を許可してください)` → `（…）`
13. Line 192 (3 occurrences) `(Cloudflare、CloudFront、Vercel)` / `(無料、CC-BY 4.0)` / `(アカウント取得で無料)` → `（…）` x3
14. Line 200 `(webdriver、自動化フラグ)` → `（…）`
15. Line 248 `MaxMind GeoLite2 (任意)` → `MaxMind GeoLite2（任意）` *(heading)*
16. Line 272 `(国 / 地域 / 大まかな都市)` → `（…）`
17. Line 296 `(https://www.maxmind.com から入手可能)` → `（…）`
18. Line 300 `DB-IP IP-to-City Lite (任意)` → `DB-IP IP-to-City Lite（任意）` *(heading)*
19. Line 316 `(訪問者データ・アカウント・キーは送信されません)` → `（…）`
20. Line 324 `(都市 / 地域 / 国)` → `（…）`

**Half-width parens correctly retained around bare Latin acronyms inside JP prose:**
- `訪問者あたり売上 (RPV)` (readme:160) — half-width because content is pure Latin acronym. ✓
- `(1)` / `(2)` / `(3)` / `(4)` tier-number list markers (readme:192) — list-numbering convention kept half-width to match the English source structure. ✓
- The HTML-link case at `statnive-ja.po:78` (`(<a href="…">GeoLite2 EULA</a> への同意が必要)`) — left half-width because the paren content opens with HTML/Latin; flipping to full-width would visually clash with the open-anchor tag.

**P0 fixes applied (stray ASCII `?` with leading half-width space):** **11 occurrences** of `か ?` / `何ですか ?` — the seed has an English-typography legacy of separating the question mark with a space. JP typography never has a space before a punctuation mark. Fixed to `か?` (closed up; ASCII `?` retained for keyboard-friendliness on JP QWERTY input — full-width `？` would also be acceptable, but ASCII `?` is a common modern SaaS style and is consistent with the English source's plain `?`).

`statnive-ja.po:689` (`Want city-level data?`) and `readme-ja.po:124, 132, 140, 148, 156, 164, 172, 180, 188, 196` (FAQ questions) — all fixed.

**Half-width digits 0–9 — observed and correct:** No full-width `０-９` anywhere. `1 KB`, `2 KB`, `30 日`, `80 MB`, `100`, `200`, `365`, `0.0/8`, `99.9%` all half-width. ✓

**Half-width space between JP and Latin runs — observed throughout:**
- `WordPress プラグイン`, `Cookie 不要`, `1 KB`, `200 件`, `30 日`, `Statnive を有効化`, `GDPR に準拠していますか`, `Google Analytics や Matomo と併用`. ✓
- No `WordPressプラグイン` (no-space) and no full-width space `　`. ✓

**Katakana long-vowel marker — correct everywhere:** `サーバー`, `ユーザー`, `ダッシュボード`, `リファラー`, `プラグイン`, `トラッカー`, `ブラウザ` (acceptable). ✓ No `サーバ`, `ユーザ`, `プラギン`, `ダッシボード`.

**Middle dot (`・`) for Latin-token list separators:** Used consistently and correctly: `Cookie・localStorage・sessionStorage`, `GDPR・CCPA・APPI・PIPL`, `ChatGPT・Claude・Gemini・…`, `訪問者データ・アカウント・キー`. ✓

**P1 — typography polish:**
- ASCII `?` vs full-width `？`: Both are acceptable in modern JP SaaS. Current seed uses ASCII `?` consistently. If a future polish pass wants the more traditional JP appearance, replace 11 occurrences with `？` (full-width).

---

## Dimension F — Register

**Body text uses 丁寧語 (です・ます) consistently.** Spot checks:
- `毎日のソルトローテーション` (体言止め label)
- `Statnive: GeoIP は有効ですが、MaxMind ライセンスキーが設定されていません。` (です・ます body) ✓
- `Statnive は完全に独立して動作します。` (です body) ✓
- `WordPress の **プラグイン** メニューからプラグインを有効化します。` (instruction) ✓

**Headlines / Site Health labels:** Mixture of 体言止め (e.g. `毎日のソルトローテーション有効`) and predicate forms (e.g. `データ保持期間が設定されています`). The predicate forms here are appropriate — they appear as Site Health *status descriptions*, not as button labels, so reading as a full clause is natural. ✓

**No casual particles (`ね`/`よ`/`だね`).** ✓
**No `だ・である` plain form leaks.** ✓

**P0 fixed:**
- `ご利用ください` (settings.tsx:349) → `お使いください` per research §7.6 / §7.14 ("`ご利用ください` … too imperative-polite for SaaS"). Modern SaaS prefers `お使いください` or `お試しください` / `ご活用ください`.

**P1 — minor:**
- One `ご返信します` / `ご利用` pattern is not present; nothing else flagged.

---

## Dimension G — Forbidden words

- `追跡` for own product → only one hit, in `readme-ja:28` (`Cookie や第三者の追跡スクリプト` — describing what Statnive does NOT use). This is the **valid** case per research §2 ("`追跡` reserved for third-party trackers"). ✓
- `革命的`, `史上最強`, `次世代`, `究極の`, `最強の`, `最先端`, `驚異の`, `シームレス`, `素晴らしい` → **zero occurrences**. ✓
- `クッキー` → **zero occurrences** (Cookie Latin used throughout). ✓
- `APPI 準拠` / `個人情報保護法に準拠` → **zero occurrences**. The seed correctly uses `〜対応を支援する設計` / `〜に対応した運用` phrasing per research §7.11 CRITICAL guardrail. ✓
- `指紋認証` → **zero occurrences** (proper `フィンガープリンティング` used). ✓
- `サイト所有者` → **zero occurrences** (n/a in plugin POT; would be flagged on a SaaS website surface). ✓

**Verdict:** Pass — no forbidden words leak through.

---

## Dimension H — Placeholder / HTML / arrow preservation

Verified programmatically — for every msgid/msgstr pair:
- All `%s`, `%d`, `%1$s`, `%2$s`, `%1$d`, `%2$s` placeholders preserved exactly. ✓
- All HTML tags (`<strong>`, `<a href="…">`, `<code>`) preserved with attribute order intact. ✓
- All `→` arrows preserved (e.g. `設定 → GeoIP`, `設定 → プライバシー`, `設定 → 診断`). ✓
- All en-dash `—` preserved as em-dash style breaks. ✓
- Markdown emphasis (`**bold**`, `` `code` ``) preserved. ✓

**Verdict:** Pass — `msgfmt --check` confirms zero placeholder mismatches.

---

## Dimension I — Plurals

POT declares `Plural-Forms: nplurals=1; plural=0;` for Japanese — single morphological form. No `_n()` / `_nx()` calls in the POT exist that would force a plural input. Spot checks:
- `アクティブな訪問者 %d 人` (counter) — single form, correct (JP has no morphological plural).
- `%d 件のアクセス解析セッションを匿名化しました。` — single form, correct.
- `データは %1$d 日間保持されます（%2$s モード）。` — single form, correct.

**Verdict:** Pass — no plural forms accidentally added.

---

## P0 fix summary

| # | File | Line | Before | After | Reason |
|---|---|---:|---|---|---|
| 1 | statnive-ja.po | 224 | `(%2$s モード)` | `（%2$s モード）` | Paren width for JP content (rubric E) |
| 2 | statnive-ja.po | 325 | `(秒)` | `（秒）` | Paren width |
| 3 | statnive-ja.po | 365 | `(クエリパラメーターを除く)` | `（…）` | Paren width |
| 4 | statnive-ja.po | 369 | `(ドメインのみ、クエリパラメーターは削除)` | `（…）` | Paren width |
| 5 | statnive-ja.po | 373 | `(IP から取得され、即時に破棄されます)` | `（…）` | Paren width |
| 6 | statnive-ja.po | 689 | `必要ですか ?` | `必要ですか?` | Space before `?` |
| 7 | statnive-ja.po | 1001 | `CIDR (例: 10.0.0.0/8) と` | `CIDR（例: 10.0.0.0/8）と` | Paren width |
| 8 | statnive-ja.po | 1041 | `(アップロードディレクトリに約 70 MB)` | `（…）` | Paren width |
| 9 | statnive-ja.po | 1045 | `ご利用ください` | `お使いください` | SaaS register (research §7.14) |
| 10 | readme-ja.po | 72 | `(および … クラスで…)` | `（…）` | Paren width |
| 11 | readme-ja.po | 80 | `(無料)` | `（無料）` | Paren width |
| 12 | readme-ja.po | 124 | `しますか ?` | `しますか?` | Space before `?` |
| 13 | readme-ja.po | 132 | `していますか ?` | `していますか?` | Space before `?` |
| 14 | readme-ja.po | 140 | `していますか ?` | `していますか?` | Space before `?` |
| 15 | readme-ja.po | 148 | `されますか ?` | `されますか?` | Space before `?` |
| 16 | readme-ja.po | 152 | `(一度きりの…)` | `（…）` | Paren width |
| 17 | readme-ja.po | 156 | `しますか ?` | `しますか?` | Space before `?` |
| 18 | readme-ja.po | 164 | `しますか ?` | `しますか?` | Space before `?` |
| 19 | readme-ja.po | 168 | `(gzip 圧縮後で約 2 KB)` | `（…）` | Paren width |
| 20 | readme-ja.po | 172 | `できますか ?` | `できますか?` | Space before `?` |
| 21 | readme-ja.po | 180 | `何ですか ?` | `何ですか?` | Space before `?` |
| 22 | readme-ja.po | 184 | `(\`connect-src 'self'\` を…)` | `（…）` | Paren width |
| 23 | readme-ja.po | 188 | `しますか ?` | `しますか?` | Space before `?` |
| 24 | readme-ja.po | 192 | 3 paren cases | `（…）` x3 | Paren width |
| 25 | readme-ja.po | 196 | `しますか ?` | `しますか?` | Space before `?` |
| 26 | readme-ja.po | 200 | `(webdriver、自動化フラグ)` | `（…）` | Paren width |
| 27 | readme-ja.po | 248 | `(任意)` | `（任意）` | Paren width |
| 28 | readme-ja.po | 272 | `(国 / 地域 / 大まかな都市)` | `（…）` | Paren width |
| 29 | readme-ja.po | 296 | `(https://… から入手可能)` | `（…）` | Paren width |
| 30 | readme-ja.po | 300 | `(任意)` | `（任意）` | Paren width |
| 31 | readme-ja.po | 316 | `(訪問者データ・アカウント・キーは…)` | `（…）` | Paren width |
| 32 | readme-ja.po | 324 | `(都市 / 地域 / 国)` | `（…）` | Paren width |

Plugin PO: **9 P0 fixed**. Readme PO: **22 P0 fixed** (with 3 inside line 192).

---

## P1 / P2 items (noted, not fixed)

### P1 — Plugin PO
1. `本日` vs `今日` for "Today" — split across admin-bar widget (本日, line 38) and Overview date picker (今日, line 759). Probably deliberate UX split (label vs date-range), but reviewers may unify.
2. ASCII `?` vs full-width `？`: 11 question marks now closed up but still ASCII. Either form is acceptable in modern JP SaaS; consider switching to `？` for a more traditional JP appearance.
3. Site Health labels mix 体言止め (`毎日のソルトローテーション`) and predicate `〜が設定されています`. Both work; if the reviewers want a uniform 体言止め label set, this would be a polish pass.
4. The single `# (Note: …)` comment that briefly appeared in an interim version was removed; current state is clean.

### P1 — Readme PO
1. Same ASCII `?` vs full-width `？` choice carries over.
2. Line 184: half-width space inside `（\`connect-src 'self'\` を許可してください）` reads slightly tight after the full-width paren switch; visually-balanced JP might prefer no inner space (closed code-span on left side). Kept as-is for grep-ability of the directive name.
3. `アトリビューション` (line 176) is borderline — `広告アトリビューション用` is acceptable martech jargon but a more conservative `広告計測の帰属用` would read more neutrally. Kept as-is since `アトリビューション` is the dominant martech katakana.

### P2 — both files
1. Header fields `Last-Translator` and `Language-Team` are blank; not a blocker (msgfmt only warns) and matches the template seed pattern across all locales.
2. The em-dash `—` is used as a JP separator in several Site Health and dashboard strings, mirroring the English source. JP would more typically use `。` + new sentence, but the visual rendering is acceptable and avoids unintended re-wrapping.

---

## Recommendations

1. **Hold the line on paren width.** A pre-commit hook or the `bin/extract-react-pot.mjs --check` flow could grep for `\([ぁ-ゟ゠-ヿ一-鿿]` (half-width paren followed by JP) and warn. Same for full-width `（[A-Za-z][A-Za-z]*）` (full-width paren around a bare Latin acronym).
2. **Decide ASCII `?` vs full-width `？` once and document.** Whichever choice — pick one across all six locales' question forms and add a one-line note to research 50 / 62.
3. **Watch for `ご利用ください` regressions.** The translator caught all but one; add to a "stop words for SaaS register" list when refreshing the seed.
4. **Glossary submission to ja Polyglots.** Once Statnive lists on WordPress.org and CLPTE is approved, propose `Statnive` (no-translate), `tracker → 計測スクリプト`, `cookieless → Cookie 不要`, and `revenue per visitor → 訪問者あたり売上 (RPV)` to the JA locale GTE so the GlotPress glossary tooltip helps every translator (per research 62 §9.8).

---

ja deep review complete.
  Plugin PO: P0=9 fixed, P1=4 noted, P2=2 noted; msgfmt: clean
  Readme PO: P0=22 fixed, P1=3 noted, P2=2 noted; msgfmt: clean
  Report: statnive/.translations/ja/REVIEW-ja.md
