# Frontend Engineering Audit — myimouto

## Overview

myimouto is a server-rendered PHP imageboard using a custom Rails-inspired framework (railsphp). The frontend is a traditional multi-page application: PHP templates produce full HTML pages, with jQuery 1.x as the primary JS layer and a full Prototype.js + Scriptaculous bundle carried in a "moe-legacy" package for backward compatibility with ported Moebooru code. There is no build step beyond Sprockets-style asset concatenation and gzip/brotli compression. The UI is permanently dark-themed, targets a desktop-primary audience, and predates mobile-first design. Two layout files exist (`default.php` and `application.php`) plus several ancillary layouts (`admin`, `bare`, `settings`).

---

## Findings

### 1. Dual JS Framework Payload (Prototype.js + jQuery shipped simultaneously)
- **Priority**: High (Performance)
- **Problem**: Every page load delivers two competing JS ecosystems. `application.js` (384 KB raw, 110 KB gzip) bundles jQuery 1.x + jQuery Migrate + jQuery UI, while `moe-legacy/application.js` (376 KB raw, 98 KB gzip) bundles Prototype 1.7 + Scriptaculous + eight sub-libraries. Total parsed JS is ~760 KB uncompressed (~208 KB gzip). Both are loaded synchronously in `<head>` via `asset_javascripts` config.
- **Impact**: ~760 KB of JS must be parsed before the first interactive frame. Two competing prototype-chain extension systems (`Object.extend`, `Array.prototype`) create hidden runtime conflicts that jQuery Migrate partially suppresses. Each page requires the browser to JIT-compile both runtimes even for pages that only use jQuery.
- **Solution**: Migrate the ~26 files under `lib/assets/javascripts/moe-legacy/` to jQuery/vanilla ES6 incrementally, starting with the most used (`post.js`, `user.js`, `tag_completion.js`). Remove `lib/assets/javascripts/prototype/` once migration is complete. Estimated JS payload reduction: 60–65%.
- **Effort**: L

### 2. All JavaScript Loaded Synchronously in `<head>` Without `defer`
- **Priority**: High (Performance)
- **Problem**: Both `asset_javascripts` files are emitted by `javascriptIncludeTag()` in the `<head>` without `async` or `defer` attributes. This blocks HTML parsing until both bundles are downloaded and executed.
- **Impact**: Full render-blocking on every page. On a cold load the browser cannot paint anything until ~760 KB of JS finishes executing. Time-to-first-contentful-paint is directly bottlenecked.
- **Solution**: Add `defer: true` to `javascriptIncludeTag` calls (or patch the helper to emit `defer` by default). Scripts that run on `DOMContentLoaded` / `document.observe("dom:loaded")` are safe with `defer`. For inline `<script>` blocks in views that call functions immediately (e.g., `InitTextAreas()` in `default.php` line 97), move them to `defer`-safe event callbacks.
- **Effort**: S

### 3. Prototype.js `document.observe`, `Ajax.Request`, and `.invoke()` Scattered Across Views
- **Priority**: High (Maintainability)
- **Problem**: Direct Prototype.js API calls appear inside 15+ template files (e.g., `D:/repos/myimouto/myimouto/app/views/layouts/_login.php:89`, `D:/repos/myimouto/myimouto/app/views/post/browse.php:218`, `D:/repos/myimouto/myimouto/app/views/post/show_partials/_image.php:51`). `Ajax.Updater` is used in forum, wiki, artist, and pool views. Binding logic is split between views and the moe-legacy JS files.
- **Impact**: Removing Prototype without touching view templates is impossible, making the migration path (Finding 1) far harder. Business logic is embedded inline, preventing testability. `evalScripts:true` on `Ajax.Updater` calls is an XSS risk if upstream payloads include `<script>` tags.
- **Solution**: Replace `Ajax.Updater`/`Ajax.Request` with `$.ajax()` or `fetch()`. Replace `document.observe` with `$(document).ready()` or native `DOMContentLoaded`. Replace `.invoke()` with `.map()`. Replace `$('id')` (Prototype's `document.getElementById` wrapper) with `$('#id')` (jQuery).
- **Effort**: L

### 4. 295 Inline `style=` Attributes Across Views
- **Priority**: Medium (Maintainability, Performance)
- **Problem**: 295 occurrences of `style="..."` exist across view templates (confirmed via grep). Prominent examples: `post/browse.php` uses inline dimensions/positions for the browser panel, `post/index.php` uses inline `display:none` for transient UI state, `layouts/_login.php` uses inline `width`/`margin` for the login modal table.
- **Impact**: Inline styles cannot be overridden by theming or user stylesheets without `!important`. They are invisible to CSS tooling, make responsive overrides fragile, and bypass Content-Security-Policy `style-src` if a strict CSP is ever added. The `post/browse.php` file alone has 30+ inline style declarations.
- **Solution**: Extract recurring inline patterns to utility classes. For toggle-visibility patterns (`display: none`), use CSS classes (`.is-hidden`) toggled by JS. For layout-critical dynamic values (e.g., image crop offsets computed in PHP), inline styles are acceptable but should be documented.
- **Effort**: M

### 5. 26 Inline Event Handlers (`onclick`, `onchange`, `onsubmit`) in Templates
- **Priority**: Medium (Maintainability, Accessibility)
- **Problem**: 26 view files contain inline event handlers. Examples: `post/index.php:27` uses `onchange="PostModeMenu.change()"` on a `<select>`, `post/show_partials/_image.php:36` uses `ondblclick=` on a `<div>`, `artist/update.php:35` and `forum/blank.php:19` use inline `Ajax.Updater` calls in `onclick`.
- **Impact**: Inline handlers create tight coupling between markup and behavior, duplicate event registration logic across templates, and cannot be removed or replaced without editing every view. They also fire before `DOMContentLoaded`, creating race conditions if the handler references a JS object not yet initialized.
- **Solution**: Remove inline handlers and delegate via `$(document).on()` in module JS files. For the mode select box specifically, the handler should be in `init.post_edit.js`.
- **Effort**: M

### 6. No Responsive Breakpoints — Mobile Experience is Non-Functional
- **Priority**: Medium (UX, Accessibility)
- **Problem**: The entire CSS has exactly one `@media` query (`prefers-reduced-motion`). There are no viewport breakpoints. The body has `padding: 1em 3em` at all screen sizes; the post-list and sidebar use floats with fixed or large-em widths. The `post/browse.php` view has a mobile-oriented variant for iOS but it is a completely separate code path, not a responsive adaptation.
- **Impact**: On viewports below ~900px the layout overflows or collapses. The `<meta name="viewport">` tag is present (both layouts), but layout does not respond to it. Users on tablets or mobile phones get an unreadable interface.
- **Solution**: Add CSS breakpoints at 1024px, 768px, and 480px. Convert float-based sidebar/content layout to `display: flex` or `display: grid`. Convert `padding: 1em 3em` on body to a clamp or responsive value.
- **Effort**: L

### 7. Heading Hierarchy Is Inconsistent and Non-Semantic
- **Priority**: Medium (Accessibility, SEO)
- **Problem**: Views use heading levels inconsistently: `api_key/index.php` starts at `<h1>` while the surrounding page has no `<h1>` in the layout. Most content pages use `<h4>` or `<h5>` as the first heading (`advertisements/`, `dmail/`, `artist/destroy.php`). Comment authors are marked as `<h6>` (`comment/_comment.php:4`). Forum posts use `<h6>` for titles. The default layout's site title uses `<h2>` (`default.php:59`). There is no `<h1>` in the main layouts.
- **Impact**: Screen readers and search engines cannot infer document structure. WCAG 1.3.1 (Info and Relationships) is violated. Google's document outline is broken for all content pages.
- **Solution**: Establish a consistent heading contract: `<h1>` = page title (rendered once per page inside `<main>`), `<h2>` = major sections, `<h3>` = subsections. Replace `<h2>` site-title with `<div>` or `<p>`. Audit all content views to follow this contract.
- **Effort**: M

### 8. `aria-expanded` on Submenu Buttons Is Static — Never Updated by JS
- **Priority**: Medium (Accessibility)
- **Problem**: All 8 submenu toggle buttons in `_menu.php` have `aria-expanded="false"` hardcoded (lines 16, 32, 63, 83, 101, 119, 142, 160, 178, 198). The `menu.js` `toggle()` function shows/hides submenus via `.show()`/`.hide()` but never updates the `aria-expanded` attribute.
- **Impact**: Screen reader users are told the submenus are always collapsed. Keyboard navigation of the menu gives no state feedback. WCAG 4.1.2 (Name, Role, Value) is violated.
- **Solution**: In `menu.js` `toggle()`, after calling `submenu.show()`/`.hide()`, update the corresponding button: `target.attr('aria-expanded', submenu_hid ? 'true' : 'false')`.
- **Effort**: S

### 9. Missing `alt` Text on Several Images
- **Priority**: Medium (Accessibility, WCAG 1.1.1)
- **Problem**: `inline/edit.php:107` has `<img>` with no `alt` attribute. `ApplicationHelper.php:101` generates `<img src="...">` with no `alt`. `post/similar.php:153` has `<img src="<?= $this->params()->full_url ?>"/>` with no `alt`. JS-generated images in `post/search_external_data.php:281` omit `alt`.
- **Impact**: Screen readers announce these images as file URLs or skip them unpredictably. WCAG 1.1.1 violation. Decorative images should have `alt=""` explicitly; informative images need descriptive text.
- **Solution**: Add `alt=""` to decorative images. Add meaningful `alt` text to informative images. Fix `ApplicationHelper::format_inline()` to pass alt text through.
- **Effort**: S

### 10. `outline: none` Applied Without `:focus-visible` Guard in Several Places
- **Priority**: Medium (Accessibility)
- **Problem**: Lines 431, 1417, 1468, 1482, 1667, 2237 in `application.css` use `outline: none`. Lines 431 and 1417/1468/1482 do use the `:focus:not(:focus-visible)` guard pattern correctly. However, line 1667 and 2237 need verification — they appear in contexts that may remove focus visibility for keyboard users.
- **Impact**: If unguarded, keyboard users lose focus visibility on those elements, violating WCAG 2.4.7.
- **Solution**: Audit lines 1667 and 2237 specifically; ensure all `outline: none` declarations are paired with `:focus:not(:focus-visible)` to preserve keyboard focus rings.
- **Effort**: S

### 11. Hardcoded `meta description` Content in `application.php`
- **Priority**: Medium (SEO)
- **Problem**: `D:/repos/myimouto/myimouto/app/views/layouts/application.php:11` has the description hardcoded as `"yande.re - A Danbooru focusing on High Resolution Anime Scans..."` — it is the upstream site's description, not the configured `app_name`. The `default.php` layout uses `CONFIG()->app_name` which is correct, but the `application` layout is not consistent.
- **Impact**: Installations using the `application` layout will serve the wrong site name in search engine results and social media cards.
- **Solution**: Replace the hardcoded string with `CONFIG()->app_description` (adding a config key) or at minimum `CONFIG()->app_name`. Also add per-page `<meta description>` support via the `provide/content` mechanism already used for `<title>`.
- **Effort**: S

### 12. No `robots.txt` or `sitemap.xml`
- **Priority**: Medium (SEO)
- **Problem**: No `robots.txt` or `sitemap.xml` exists anywhere under `public/`. The `application.php` layout emits `<meta name="robots" content="none">` for search queries, which is correct, but there is no global crawl policy file.
- **Impact**: Search engines crawl all URLs by default, including paginated search results, admin endpoints, and API endpoints. Without `robots.txt`, the `Disallow` rules for `/admin/`, `/api/`, and duplicate search pages cannot be expressed.
- **Solution**: Add `public/robots.txt` with appropriate `Disallow` rules for admin, API, and parameter-heavy search URLs. Consider a dynamic sitemap for canonical post pages.
- **Effort**: S

### 13. Post Thumbnail List — No Virtualization, Full DOM Render
- **Priority**: Medium (Performance)
- **Problem**: `post/_posts.php` renders all posts in a single `<ul id="post-list-posts">` with no virtualization. The default page size can yield up to 100 `<li>` elements, each with multiple nested `<div>`, `<a>`, `<img>`, and `<span>` elements (~15 DOM nodes per post). At 100 posts that is ~1500 DOM nodes from the post list alone.
- **Impact**: Excessive DOM size increases style recalculation time and memory usage. Chrome's Lighthouse threshold is 1500 total nodes; this single component can approach that limit.
- **Solution**: Reduce default page size to 40–50 or implement intersection-observer-based deferred rendering for below-fold thumbnails. For the browse mode this is less critical (it already virtualizes via JavaScript).
- **Effort**: M

### 14. No Web Fonts — System Fonts Only (Acceptable but Undeclared)
- **Priority**: Low (Performance, DX)
- **Problem**: The CSS references `Verdana`, `Tahoma`, and `sans-serif` — all system fonts. No `@font-face` declarations exist. No external font CDN is referenced. This is actually good for performance (no FOUT/FOIT, no extra HTTP round-trips), but there is no explicit `font-display` consideration and no fallback font stack for non-Windows systems where Tahoma and Verdana may differ.
- **Impact**: On Linux systems, Verdana and Tahoma fall back to generic sans-serif, leading to minor layout differences. No critical rendering path impact.
- **Solution**: Consider adding `font-family: Tahoma, 'Liberation Sans', Verdana, sans-serif` for cross-platform consistency. No web font loading is needed.
- **Effort**: S

### 15. IE-era Conditional Comments and Polyfill Scripts Still Present
- **Priority**: Low (Maintainability)
- **Problem**: Both layout files contain `<!--[if lt IE 7]>` and `<!--[if lt IE 8]>` conditional comment blocks loading `IE8.js` and a Google Code-hosted `IE7.js` via HTTP (not HTTPS). `default.php` also has an `<div id="old-browser">` notification block.
- **Impact**: The Google Code-hosted URL (`ie7-js.googlecode.com`) no longer resolves, making the HTTP fetch fail silently. The code adds dead weight and conceptual noise. The `default.php:55` IE7 script loads over HTTP, which causes a mixed-content warning on HTTPS deployments.
- **Solution**: Remove all IE conditional comment blocks. Remove `public/IE8.js`. Remove the old-browser notification div.
- **Effort**: S

### 16. Cookie as Primary State Transport
- **Priority**: Low (Architecture, UX)
- **Problem**: Large amounts of UI state are communicated via cookies: notice messages (`Cookie.get("notice")`), held post count, forum state (`current_forum_posts` cookie stores serialized JSON), blacklist settings, resize preferences, and login cookies. The notice system in `_notice.php` reads from a cookie rather than from a server-side flash message or session, requiring a JavaScript-dependent rendering path.
- **Impact**: Notices are invisible if JavaScript is disabled. Cookie-based state is lost on private browsing, is bound to path/domain, and has a 4 KB limit. Serializing JSON into cookies (forum posts) is fragile and exposed to client modification.
- **Solution**: Migrate flash notices to server-side session flash (the framework supports this). Migrate forum-menu state to a lightweight AJAX endpoint rather than a cookie JSON blob.
- **Effort**: M

---

## Performance Risk Analysis

The most significant performance risk is the **dual JS framework payload** (Finding 1) combined with **synchronous `<head>` loading** (Finding 2). Together they force ~760 KB of JS to be parsed and executed before the browser can render a single pixel. The moe-legacy bundle includes Prototype.js 1.7 in its entirety even on pages that use zero Prototype.js features.

Secondary risk is the **unvirtualized post list DOM** (Finding 13): at 100 posts per page the DOM node count from the list alone approaches browser performance thresholds, compounded by the post registration loop that serializes and deserializes all post data as JSON in inline `<script>` blocks.

The **compressed asset sizes** (application.js.gz: 110 KB, moe-legacy/application.js.gz: 98 KB) are moderate for 2025 standards but both bundles are monolithic with no code splitting — every page receives all JS for every feature (note editor, batch uploader, pool ordering, image cropper, etc.) even when unused.

The CSS payload (application.css.gz: 11.6 KB) is well within acceptable limits and not a concern.

---

## Web Interface Guidelines Compliance

Checked against Vercel Web Interface Guidelines (fetched from https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md).

| Location | Violation | Severity |
|---|---|---|
| `app/views/layouts/_menu.php:16,32,63,...` | `aria-expanded` is static string `"false"`, never toggled by JS | Must-fix |
| `app/views/layouts/application.php:11` | `meta description` hardcoded to upstream site copy | Must-fix |
| `app/views/post/show_partials/_image.php:51`, `layouts/_login.php:89` | `document.observe()` — Prototype.js idiom, not interruptible, no keyboard handler on non-button interactive elements | Must-fix |
| `app/views/inline/edit.php:107`, `app/helpers/ApplicationHelper.php:101`, `app/views/post/similar.php:153` | `<img>` without `alt` attribute | Must-fix |
| `app/views/post/index.php:27` | `onchange=` inline handler on `<select>` — no keyboard-equivalent path documented | Should-fix |
| `app/views/post/browse.php:5-7` | `document.write()` for loading startup image — blocking, deprecated | Should-fix |
| `app/views/post/import.php:240` | `eval()` on innerHTML — security and performance risk | Should-fix |
| `app/assets/stylesheets/application.css` (body rule) | `font-size: 80%` on `<body>` — shrinks base font below user preference of 16px, fails WCAG 1.4.4 Resize Text at zoom levels | Should-fix |
| All views | No `Intl.DateTimeFormat` usage — date formatting is server-side string output with no locale-aware client rendering | Consider |
| All views | No `font-display: swap` — not applicable (no web fonts), but system font stack lacks cross-platform fallbacks | Consider |
| `app/assets/stylesheets/application.css:1667,2237` | `outline: none` without verified `:focus-visible` guard | Should-fix |
| No `robots.txt` in `public/` | Missing crawl control for admin/API endpoints | Should-fix |

**Total violations: 13 (Must-fix: 4, Should-fix: 7, Consider: 2)**

---

## Recommended Next Step

For fixing identified issues, use the `frontend-designer` agent — it can rebuild or refactor UI components with high design quality and automatically self-review against the same Web Interface Guidelines.

Priority order for immediate action:
1. Add `defer` to JS includes (Finding 2) — small effort, large rendering impact.
2. Fix `aria-expanded` toggling in `menu.js` (Finding 8) — one-line fix, meaningful a11y improvement.
3. Add `alt=""` to decorative images and meaningful `alt` to informative images (Finding 9).
4. Remove IE conditional comment blocks (Finding 15) — reduces noise.
5. Fix hardcoded meta description in `application.php` (Finding 11).
6. Begin moe-legacy JS migration starting with `user.js` and the `Ajax.Updater` calls in forum/wiki views.

---

## Frontend Score: 4/10

**Justification:**

Positive aspects: the skip-link is implemented and styled correctly; `<nav>` with `aria-label` is used for the main menu; `loading="lazy"` is applied to thumbnails; `fetchpriority="high"` is used on the main post image; canonical URL tags are emitted; Open Graph tags exist for post pages; brotli/gzip compressed assets are pre-built; the `prefers-reduced-motion` media query is respected; `touch-action: manipulation` and `focus-visible` indicators were added (evidenced by the PROJ-34/PROJ-35 CSS comment blocks). These indicate ongoing improvement effort.

Negative aspects pulling the score down: the dual-framework payload with synchronous head loading is a fundamental performance problem; 295 inline style attributes and 26 inline event handlers reflect architecture from 2008; there are no responsive breakpoints for a media-heavy application; heading hierarchy is inconsistent across ~40 view directories; several images lack alt text; aria-expanded is never dynamically updated; there is no robots.txt; the hardcoded upstream site description in application.php is a regression risk; and Prototype.js `eval`/`document.write` patterns remain in active use.

The score reflects a functional but technically dated frontend that has received targeted accessibility patches but has not been refactored at the architectural level.
