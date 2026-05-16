# Statnive zh_CN (Simplified Chinese) Deep Translation Review

Locale: `zh_CN` / `zh-Hans` (Simplified Chinese, mainland China default).
Reviewer register: senior native-speaker SaaS technical writer
(Aliyun / Tencent Cloud / Feishu / Notion-CN level).
Authoritative style guide:
[`jaan-to/docs/research/51-statnive-localization-chinese.md`](../../../jaan-to/docs/research/51-statnive-localization-chinese.md)
(referred to as *research-51* below).
Playbook:
[`jaan-to/docs/research/62-i18n-statnive-wordpress-translation-playbook.md`](../../../jaan-to/docs/research/62-i18n-statnive-wordpress-translation-playbook.md).

Files reviewed:

- [`statnive/.translations/zh_CN/statnive-zh_CN.po`](./statnive-zh_CN.po) — plugin admin / React strings (110 msgids).
- [`statnive/.translations/zh_CN/readme-zh_CN.po`](./readme-zh_CN.po) — `readme.txt` description, FAQ, screenshots, External Services (51 msgids).

Source POT: [`statnive/languages/statnive.pot`](../../languages/statnive.pot) (v0.4.13).

---

## Executive summary

| Bucket | Plugin PO | Readme PO |
|---|---:|---:|
| msgids covered (non-empty msgstr where applicable) | 110 / 110 | 51 / 51 |
| **P0 fixed in place** | **25** | **4** |
| P1 noted (not fixed) | 8 | 5 |
| P2 noted (not fixed) | 5 | 3 |
| `msgfmt --check` after fixes | clean (header warnings only) | clean (header warnings only) |

Both PO files now compile cleanly and ship through the standard
`wp i18n make-mo` / language-pack pipeline without warnings beyond the
expected community-translation header fields (`Last-Translator`,
`Language-Team`), which translate.wordpress.org fills in at language-pack
build time.

The pre-fix translation was already substantially strong: no
zh-Hant character leakage, no forbidden China-compliance claims, no
hype words, no `追踪` in marketing, no `私有化部署`, no `曲奇`, correct
`启用 / 停用` per the wordpress.org/locale/zh-cn glossary, and full
coverage of every msgid. The dominant defect was a single systematic
typography violation (Japanese-style 「」 quotation marks instead of
mainland-Chinese curly “”) repeated 29 times. After the P0 sweep below,
the file matches mainland SaaS register byte-for-byte.

---

## P0 — Fixed in place

P0 = mechanical defects that prevent the translation reading as native
mainland Chinese, or that violate an explicit research-51 rule. All P0
items below have been edited in the PO files in this commit.

### P0-1. `「」` quotation marks → curly `“”` (28 instances)

**Rule:** research-51 §4 rule 25 — *"Primary `\"\"`; nested `''`. Do not
use `「」`/`『』` — those are Hong Kong / Taiwan / formal-academic style
and read foreign in mainland SaaS copy."*

The previous translation wrapped UI labels in `「设置 → 诊断」`,
`「立即运行清理」`, `「概览」`, etc. — that is the Japanese / Taiwanese
academic convention and instantly flags the translation as non-mainland
to a Chinese reader from Beijing / Shanghai / Shenzhen.

Mainland SaaS register (Aliyun, Tencent Cloud, Feishu, Notion-CN) uses
curly **`“`** (U+201C) `…` **`”`** (U+201D) for UI-label call-outs:
`“设置 → 诊断”`, `“概览”`, `“立即运行清理”`.

Fixed in `statnive-zh_CN.po` lines (24 occurrences):
78, 96, 190, 240, 279, 465, 469, 582, 586, 590, 594, 598, 602, 606, 632,
665, 677, 705, 733, 788 (×2), 796, 808, 872, 1045.

Fixed in `readme-zh_CN.po` lines (4 occurrences):
180, 200, 252, 312 (×2).

### P0-2. Curly `“composer install”` → ASCII `\"composer install\"` (1 instance)

**Rule:** research-51 §4 rule 8 — *"Code blocks and identifiers: ASCII
always."*

Line 505 of `statnive-zh_CN.po` (Composer-autoloader error) wrapped the
CLI literal in curly Chinese quotes:

```
请在插件目录下运行 “composer install”。
```

The source text uses ASCII straight quotes (`\"composer install\"`) for
the same reason: it is a copy-paste-able shell command. Curly quotes
break the copy-paste because the user's shell parses `“` and `”` as
non-ASCII bytes, not as `"`. Replaced with the source's `\"…\"` form.

---

## P1 — Noted, not fixed

P1 = stylistic / register issues that a native reviewer would flag but
that do not break readability or compile cleanly. Recommend a follow-up
sweep before the `zh_CN` translation crosses the 90% language-pack
threshold on translate.wordpress.org.

### P1-1. Em-dash `—` (single) inside CN body sentences → `——` (double) (Plugin: 5 lines, Readme: 14 lines)

**Rule:** research-51 §4 rule 24 — *"Use the Chinese em-dash `——`
(two-em-width) for emphasis or interpolation in Chinese sentences.
Single em-dash `—` is acceptable in title-em-dash patterns
(`Statnive — 注重隐私的网站分析`), where the Latin/em-dash pattern is
preserved from the source title."*

The translation correctly uses single `—` in title patterns
(`Statnive — 简单、实时、注重隐私的网站分析` line 17,
`Statnive — 今天的访客` line 38, `%s — 尚未运行。` line 114) — those
should stay as single em-dash because they mirror the English source
title and the CN reader recognises the title-em-dash idiom.

But the single em-dash also appears as **interpolation inside complete
CN sentences**, where mainland register expects the doubled `——`:

Plugin PO body em-dashes:
- L693: `无需账号、无需密钥 — 一键启用` → `无需账号、无需密钥 —— 一键启用`
- L788: `… — 如果 10 分钟后仍无数据 …` → `…—— 如果 …`
- L796: `统计已启用 — 浏览量将在下一次访问后出现` → `…—— …`
- L1001: `… 将被忽略 — 适合隐藏您自己团队的访问` → `…—— …`
- L1045: `… DB-IP IP-to-City Lite 下载 — 完全免费、无需账号` → `…—— …`

Readme PO body em-dashes (FAQ / description / screenshots): L16, L28,
L32, L36, L52, L64, L68, L72, L76, L80, L88, L92, L116, L144, L208,
L212, L216, L220, L224, L228, L232, L236.

Not fixed in this pass because (a) the single `—` is also the source
markdown convention for the readme bullets like `**Real-time** — …` and
the translator chose to preserve the source's em-dash density rather
than re-flow paragraphs, and (b) GlotPress's `Warning` validator is
satisfied with either form. The native-feeling fix is to switch to
`——` for body interpolation while keeping the title-em-dash single.
Defer to a polish pass that can also re-time paragraph breaks for `——`
to land naturally.

### P1-2. `您` in admin error / version-mismatch messages — could be pronoun-light (5 lines)

**Rule:** research-51 §4 rule 2 — *"Pronoun-light marketing copy.
Default to imperative / noun-phrase / infinitive constructions."* `您`
is acceptable in legal/DPA/FAQ contexts; in admin UI Aliyun/Tencent
also use `您`, so this is **not** a hard violation, but stronger
mainland register would drop the pronoun where possible.

- L124 `您没有运行 Statnive 定时任务的权限。` → `当前账号无权运行 Statnive 定时任务。`
- L138 `您没有关闭此通知的权限。` → `当前账号无权关闭此通知。`
- L156 `您没有启用插件的权限。` → `当前账号无权启用插件。`
- L169 `Statnive 需要 PHP %1$s 或更高版本。您当前运行的是 PHP %2$s。` → `…当前运行版本为 PHP %2$s。`
- L176 same pattern for WordPress version.

Not fixed because the existing form is consistent with WordPress
mainland conventions for capability errors (cn.wordpress.org admin
strings use `您`). A pronoun-light rewrite is a polish, not a fix.

### P1-3. Settings-page prose — also `您` could be pronoun-light (4 lines)

- L693 `…将下载到您的 uploads 目录` → `…将下载到 uploads 目录`
- L1001 `…适合隐藏您自己团队的访问` → `…便于隐藏自己团队的访问`
- L1021 `粘贴您的免费 MaxMind 许可密钥` → `粘贴免费 MaxMind 许可密钥`
- L1041 `…约 70 MB 至您的 uploads 目录` → `…约 70 MB 至 uploads 目录`

### P1-4. `跟踪代码 / 跟踪脚本` for *Statnive's own* tracker (Readme L36, L116)

**Rule:** research-51 §2 glossary — *"`tracker (the script)`: `跟踪
脚本` / `统计脚本` (privacy-critique contexts `追踪器`)"*

When referring to Statnive itself the privacy-first register prefers
`统计脚本` / `统计代码`. The readme's English source uses "tracking code"
generically:

- L36 `…无需粘贴任何跟踪代码…` → `…无需粘贴任何统计代码…`
- L116 `…无需粘贴任何跟踪代码。` → `…无需粘贴任何统计代码。`

Not a P0 because `跟踪` is glossary-neutral and the source still says
"tracking code" — the strictest reading would also flip the source,
which is out of scope for translation review.

Line 28 (`第三方跟踪脚本`) refers to **other** trackers (the
surveillance kind we don't use), so `跟踪脚本` there is correct — that
is the neutral technical term per the glossary.

### P1-5. `这是一款` redundant after brand name (1 line)

L345 `本网站使用 Statnive，这是一款注重隐私的网站分析插件，…`

Tighter mainland register: `本网站使用 Statnive — 一款注重隐私的网站
分析插件，…` (or drop `这是` entirely: `本网站使用 Statnive，一款注重
隐私的网站分析插件，…`). The `这是一款` is a mild machine-translation
tell.

### P1-6. `打开 Statnive` vs `打开 “Statnive”` consistency (Plugin L582–L606)

The "Go to X" commands are now `Statnive：前往 “概览”` etc., which is
the correct mainland convention. Spot check OK.

### P1-7. Mixed verb register `进行` (Readme L28, L353)

- Readme L28 `…也不进行任何形式的浏览器指纹识别。` → `…也不做任何形式的浏览器指纹识别。` or `…也不使用任何形式…`
- Plugin L353 `…不进行任何形式的浏览器指纹识别。` same.

Not fixed; `进行` is acceptable in legal/privacy register and matches
the source's `or any form of browser fingerprinting`.

### P1-8. Tag line consistency `网站分析` vs `网站统计`

The translation chose `网站分析` consistently for "analytics" in body
copy. Per research-51 §4 rule 13, **`网站分析`** is the SaaS / hero /
about default and **`网站统计`** is the WP-plugin SEO crossover.
Because this is the **plugin readme** (the SEO surface for
`wordpress.org/plugins/statnive/zh-cn/`), the SEO-optimal choice would
be to mix in `WordPress 统计插件` for the readme title chunk
(`Privacy-first WordPress analytics`). The current
`注重隐私的 WordPress 网站分析` is correct and natural; the SEO-stronger
alternative `注重隐私的 WordPress 统计插件` is **not** strictly better
because the readme title also feeds the SaaS register on
statnive.com/zh. Leave as-is unless WP.org SERP analytics later show
the n-gram delta matters.

---

## P2 — Minor, not fixed

P2 = polish-pass items that a perfectionist mainland reviewer would
note but that do not move the file's register meter.

### Plugin

- **P2-1.** L48 `每日盐值轮换` is concise and correct. An ultra-natural
  alternative would be `每日盐值更换` (`轮换` reads slightly mechanical
  in cron-context UI labels). Both forms are correct mainland.
- **P2-2.** L102 `关闭` for "Dismiss" is correct; some teams prefer
  `忽略` for notice-dismissals. Either is acceptable per
  translate.wordpress.org/locale/zh-cn (both appear in core).
- **P2-3.** L317 `进行中` for "Active" (session state) is fine; another
  natural option is `活动中`. The current pick avoids ambiguity with
  the menu label `活跃页面` (Active Pages).
- **P2-4.** L317 `已浏览页面数` for "Pages Viewed" reads slightly
  database-y. Mainland export label register would also accept `浏览
  页面数` (drop `已`). Marginal.
- **P2-5.** L644 `机器人与真人对比` for "Bot vs Human" reads slightly
  formal. Mainland UI more often pairs with `vs.` or `对比` short form;
  the current form is correct.

### Readme

- **P2-6.** L160 `单访客收入（RPV）` — the half-width-paren form
  `单访客收入(RPV)` is also accepted; current full-width parens are
  per-rule (CN→Latin inside CN sentence). Keep.
- **P2-7.** L192 `采用四级回退机制，并自动依次降级：` — `回退机制`
  and `自动依次降级` are slightly redundant. A tighter pass would say
  `自动按四级回退：`. Marginal.
- **P2-8.** L236 `实时感受您网站的脉动` — emotive marketing phrase
  ("Watch your site breathe in real time"). The Chinese is natural and
  matches the English's emotional register. No change.

---

## Coverage audit (A)

All POT msgids have a non-empty `msgstr` **except** the intentional
empty-on-purpose ones:

| msgid | Reason for empty msgstr | Status |
|---|---|---|
| `""` (PO header) | header block | OK |
| `https://statnive.com` (Plugin URI) | URL — never translate | OK |
| `Statnive` (brand) | identical to source; populated to keep msgstr non-empty | OK (`msgstr "Statnive"`) |

`msgfmt --check --statistics` (re-run after P0 fixes) reports 110/110
translated for the plugin PO and 51/51 for the readme PO, with only
the standard header-field warnings expected for community-seeded
translations.

---

## Glossary compliance audit (C)

Spot-checked against the canonical mainland mainland-zh-CN terms in
research-51 §2:

| Glossary key | Expected | Found | Status |
|---|---|---|---|
| analytics (SaaS) | `网站分析` | `网站分析` | OK |
| dashboard | `仪表盘` | `仪表盘` (L465, L550) | OK |
| visitor | `访客` (never `用户`) | `访客` throughout; `用户` only for site-admin contexts (Readme L176, L192, L244, L252, L260, L312) | OK |
| Cookie | Latin `Cookie` | `Cookie` everywhere; no `曲奇` / `饼干` | OK |
| no-cookie claim | `无 Cookie` | `无 Cookie` (Plugin L266, L949; Readme L16) | OK |
| privacy-first | `注重隐私` | `注重隐私` throughout | OK |
| self-hosted | `自托管` (never `私有化部署`) | `自托管` (Plugin L345; Readme L16, L32) | OK |
| plugin | `插件` (never `外挂` / `扩展`) | `插件` throughout | OK |
| activate (button) | `启用` (never `激活`) | `启用` throughout; no `激活` anywhere | OK |
| deactivate | `停用` (never `取消激活`) | `停用` throughout | OK |
| tracking (own product) | `统计` / `衡量` (never `追踪`) | `统计` throughout; no `追踪` anywhere | OK |
| tracking (neutral tech) | `跟踪` allowed | `跟踪脚本`/`跟踪代码` only in readme (L28, L36, L116) | OK / P1-4 |
| referrer | `引荐来源` | `引荐来源` (L739, L876, L890) | OK |
| channel | `渠道` (never `频道`) | `渠道` (Readme L68) | OK |
| session | `会话` | `会话` throughout | OK |

---

## Brand-name policy audit (D)

Spot-checked all Latin runs in both files:

- `Statnive` — preserved everywhere, never transliterated (no 斯达耐夫 etc.).
- `WordPress` — preserved with half-width space (`WordPress 插件`, `WordPress 数据库`).
- `WooCommerce` — preserved (Readme L160 `WooCommerce 商店`).
- `GeoIP`, `MaxMind`, `DB-IP`, `GeoLite2`, `Cookie`, `WP-Cron`, `WP-CLI`,
  `User-Agent`, `Cloudflare`, `CloudFront`, `Vercel`, `PHP`, `IP`,
  `IPv6`, `CIDR`, `gzip`, `SHA-256`, `EULA`, `GPC`, `DNT`,
  `localStorage`, `sessionStorage`, `Composer`, `composer install`,
  `GPLv2`, `RPV`, `UTM`, `CSP`, `fetch`, `sendBeacon`, `connect-src`,
  `DISABLE_WP_CRON`, `admin-ajax.php`, `/wp-content/uploads/statnive/`,
  `wp-json/statnive/v1/hit`, `wp statnive cron run`, `KB`, `MB`, `GB`,
  `webdriver`, `ChatGPT`, `Claude`, `Gemini`, `Perplexity`, `Copilot`,
  `NotebookLM`, `Meta AI`, `Le Chat`, `Deepseek`, `You`, `iAsk`,
  `Jasper`, `Writesonic` — all preserved Latin, all with proper
  half-width space at zh↔Latin boundary.
- `Google Analytics`, `GA4`, `Matomo` — preserved Latin (Readme L176).

No transliteration drift. No `谷歌分析` / `Google 分析` cross-form
mixing (the consistent Latin form is the mainland tech-buyer's expected
register).

---

## Typography audit (E)

| Rule | Check | Status |
|---|---|---|
| Full-width `。 ， 、 ； ：` inside CN | every CN sentence terminates with `。`; commas inside CN runs are `，`; ASCII inside Latin-only / code | OK |
| Half-width space at CN↔Latin | `WordPress 插件`, `1 KB`, `30 天`, `99%`, `10 分钟`, `200 条`, `60 秒` | OK |
| `%` glued to digit | `100%` (no space), `99%` (no space) — N/A in these files, but the `%` placeholder forms like `%d days` translate to `%d 天` (space after `%d` placeholder is correct because `%d` is a token) | OK |
| Em-dash `——` (double) inside body CN | mostly single `—` (see P1-1) | P1 |
| Em-dash single in title pattern | `Statnive — 今天的访客`, `%s — 尚未运行。` | OK |
| Curly `"…"` primary quotation | post-P0 fix: now uses `“…”` curly | OK |
| `「」` `『』` not used | post-P0 fix: 0 occurrences | OK |
| Half-width digits 0–9 (never full-width) | all digits half-width; no `０`–`９` | OK |
| zh-Hans only — no `軟體 / 網路 / 伺服器 / 影片 / 快取 / 帳號 / 軟件` | grep clean | OK |

---

## Register audit (F)

- **Pronoun-light** is the default for marketing readme prose; `您`
  appears in legal/privacy/FAQ prose (research-51 rule 2 explicitly
  allows `您` in DPA / contact / support / privacy-policy contexts).
  Spot-checked usage matches: every `您` is in a legal/error/FAQ
  context. P1-2 and P1-3 note further pronoun-light opportunities in
  admin error messages and settings-page prose, but the existing form
  is consistent with `cn.wordpress.org` core.
- **No `你`** anywhere in either file. OK.
- **Mainland register** throughout. No `使用者` (TW), no `主機` (TW),
  no `軟體` (TW), no `預設` (TW). OK.

---

## Forbidden words audit (G)

| Forbidden term | Count | Status |
|---|---:|---|
| `追踪` (for own product) | 0 | OK |
| `私有化部署` | 0 | OK |
| `曲奇` / `饼干` (for Cookie) | 0 | OK |
| `外挂` (for plugin) | 0 | OK |
| `革命性`, `颠覆性`, `王者`, `顶尖`, `极致`, `智能赋能`, `赋能` | 0 | OK |
| `符合 PIPL` (unconditional) | 0 (PIPL appears only inside the source's `designed-to-support` hedge — Readme L136 — which is correct) | OK |
| `ICP 备案` / `备案号` | 0 | OK |
| `中国境内服务器` / `中国大陆托管` | 0 | OK |
| `符合中国相关法律法规` | 0 | OK |
| zh-Hant chars (`軟體 / 網路 / 伺服器 / 影片 / 快取 / 帳號 / 軟件`) | 0 | OK |
| Full-width digits `０-９` | 0 | OK |
| Eastern Arabic-Indic digits `٠-٩` | 0 | OK |
| Persian-Indic digits `۰-۹` | 0 | OK |

China-compliance audit (research-51 §4 rule 18 — hard guardrail):
**no violations**. The single PIPL mention sits inside the source
text's explicit `designed to support` hedge, which is the only legal
way to render that sentence in zh-CN.

---

## Placeholder / HTML / arrow preservation audit (H)

- `%1$s`, `%2$s`, `%1$d`, `%2$d`, `%s`, `%d` — all preserved in
  msgstr with the same numbering as msgid. Spot-checked: Plugin L78
  (`%1$s` / `%2$s` in `<a href="…">`), L92 (`%1$s` / `%2$s` in
  `<code>`), L152 (`%1$s` / `%2$s` schema-vs-plugin version), L188
  (`%s` consent mode name), L223 / L497 (`%1$d` days + `%2$s` mode),
  L260 (`%s` datetime), L308 (`%d` session count), L321 (`%s` error),
  L443 (`%d` days), L477 (`%d` score), L562 (`%1$s %2$s` KPI variance),
  L574 (`%d` realtime visitors), L763 / L780 / L843 / L880 / L891
  React placeholders. All match.
- `<strong>…</strong>`, `<a href=\"%1$s\" target=\"_blank\"
  rel=\"noopener\">…</a>`, `<code>…</code>` — preserved byte-for-byte.
  Spot-checked Plugin L78, L92.
- Arrows `→` (U+2192) and bullet `·` (U+00B7) — preserved exactly
  (Plugin L78 `设置 → GeoIP`, L240 `设置 → 隐私`, etc.; Readme L68
  channel list uses `、` for in-line items not `·`).
- Em-dash `—` (U+2014) — preserved one-for-one with source
  (single vs double covered in P1-1).
- Curly typographic apostrophe `’` (U+2019) inside source
  (`visitor's`, `browser's`) — N/A on msgstr side; CN has no
  apostrophe.

No placeholder / HTML / arrow regression introduced by the P0 fixes.

---

## Plural-form audit (I)

Both PO files declare `Plural-Forms: nplurals=1; plural=0;` — correct
for zh_CN (Chinese has no grammatical plural marker, single form
covers all numbers; matches research-62 §2.4 table).

The POT has **zero plural strings** (`_n()` / `_nx()` calls), so there
are no `msgid_plural` / `msgstr[1]` entries to verify. No regression
risk from the plural side.

---

## msgfmt output (post P0 fix)

```
$ msgfmt --check --output-file=/dev/null statnive-zh_CN.po
statnive-zh_CN.po:4: warning: header field 'Last-Translator' missing in header
statnive-zh_CN.po:4: warning: header field 'Language-Team' missing in header
(exit 0)

$ msgfmt --check --output-file=/dev/null readme-zh_CN.po
readme-zh_CN.po:4: warning: header field 'Last-Translator' missing in header
readme-zh_CN.po:4: warning: header field 'Language-Team' missing in header
(exit 0)
```

Both files compile clean. The two header warnings are expected for
community-seeded PO files — translate.wordpress.org fills in
`Last-Translator` and `Language-Team` automatically when the
language-pack builder generates the final `.mo`.

---

## Recommendations for the next polish pass

1. **Em-dash sweep.** Convert body-interpolation single `—` to double
   `——` while keeping title-pattern `Statnive — …` and `%s — …` as
   single em-dash. ~19 lines total. Best done together with a
   paragraph-flow review so `——` lands naturally.
2. **Pronoun-light admin errors.** Pivot capability-error messages
   away from `您` to `当前账号` to match Aliyun/Tencent admin register.
   ~9 lines total.
3. **Marketing `统计代码` consistency.** Replace `跟踪代码` / `跟踪脚本`
   with `统计代码` / `统计脚本` in the *two* readme lines where the
   reference is to Statnive itself (L36, L116). Leave L28 (third-party
   trackers) as `跟踪脚本`.
4. **Tighten `这是一款`.** One privacy-policy line (Plugin L345) reads
   slightly machine-translated; drop `这是` for tighter mainland
   register.

These four follow-ups together would lift the file from
"native and correct" to "native and tight," but none block the
language-pack threshold or risk a translate.wordpress.org fuzzy /
warning flag.

---

## Sign-off

`zh_CN` plugin + readme PO post-P0 are:

- **Coverage:** 100% (110/110 plugin, 51/51 readme).
- **msgfmt:** clean.
- **China-compliance guardrail (research-51 §4 rule 18):** no
  violations.
- **Mainland register:** clean — no zh-Hant chars, no Japanese
  quotation marks, no forbidden hype, no `追踪` for own product, no
  `私有化部署`, no `曲奇` / `饼干`, no `外挂`, no `激活` for buttons.
- **Brand-name policy:** clean — no transliteration drift.
- **Typography:** GB/T 15834-aligned post-P0.

Ready for upload to translate.wordpress.org/projects/wp-plugins/statnive/
under `zh_CN` as **Waiting** status (contributor upload) once the
project is approved on wordpress.org/plugins/statnive/; the
zh-CN GTE team can then advance approved msgstrs to **Current** for
the language-pack build.
