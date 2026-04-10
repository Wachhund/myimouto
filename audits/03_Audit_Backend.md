# Backend Engineering Audit — myimouto

## Overview

myimouto is a PHP 8.5+ imageboard using **railsphp** (a custom Rails-inspired MVC framework) with a MySQL 8.0+ backend. The application has 43 controllers, 53 models, and a service layer in `lib/MyImouto/`. The architecture follows a Rails-style MVC pattern with ActiveRecord ORM, before/after filters, and a trait-based Post model decomposition across 16 traits. The codebase is a maintained evolution of an originally-discontinued project, and shows clear marks of both legacy heritage and active modernization work.

---

## Findings

### [SECURITY] SHA-1 Password Hashes Retained in Dual-Write

- **Priority**: 🔴 High
- **Problem**: `User::_encrypt_password()` at `/app/models/User.php:194` writes BOTH bcrypt and SHA-1 hashes on every password change. `User::apply_new_password()` at line 236 also persists `$sha1_hash` permanently. The SHA-1 hash is derived from a static salt (`choujin-steiner` in `DefaultConfig::$user_password_salt`).
- **Impact**: If the database is breached, all SHA-1 hashes are crackable offline within hours using GPU rigs. The salt is in source code, removing any meaningful per-user protection. Any user who has changed their password still carries a cracked SHA-1 hash in the DB.
- **Solution**: Stop writing SHA-1 on new password changes. Migrate `apply_new_password()` to only write bcrypt. Keep the SHA-1 column readable for the remaining legacy-auth fallback path during migration, but stop writing it. Add a migration that NULLs `password_hash` on accounts that already have `bcrypt_password_hash`.
- **Effort**: S

---

### [SECURITY] crc32 Used as Session Invalidation Token

- **Priority**: 🔴 High
- **Problem**: `ApplicationController::set_current_user()` (line 94) and `UserController::_save_cookies()` (line 681) store `crc32($user->bcrypt_password_hash)` in the session as the password change signal. `crc32` produces a 32-bit integer (~4 billion values). Given that bcrypt hashes have a known format (`$2y$12$...`), the keyspace of meaningful crc32 outcomes is small and collisions are trivially possible.
- **Impact**: An attacker who has captured a valid session cookie could engineer a collision to survive a password change invalidation, retaining access after the password has been reset. Alternatively a user who changes their password to one that produces the same crc32 would not trigger session invalidation.
- **Solution**: Replace `crc32` with `hash('sha256', $bcrypt_hash)` truncated to 64 bits or stored as a full hex string in a dedicated session field. The session already stores `user_id` — a second 16-byte random token written to the DB on login and compared on each request is the standard approach.
- **Effort**: S

---

### [SECURITY] `blocked_only` Filter Permits Blocked Users

- **Priority**: 🔴 High
- **Problem**: `DmailController` declares `'before' => ['blocked_only']` as its sole filter. Via `ApplicationController::__call()`, `blocked_only` dispatches to `is_blocked_or_higher()` which evaluates to `$user->level >= 10`. Level 10 is the "Blocked" tier, so blocked users (level 10) satisfy the check and are allowed to use the Dmail system. Anonymous users (level 0) are correctly denied, but this is likely unintentional — blocked users should not send messages.
- **Impact**: Banned users can continue sending private messages to other users, circumventing the moderation intent.
- **Solution**: Replace `blocked_only` in `DmailController` with `member_only` (requires level >= 20) or introduce an explicit `no_blocked()` filter. Also apply the same analysis to `UserController` where `blocked_only` protects `authenticate`, `update`, `edit`, and `modifyBlacklist`.
- **Effort**: S

---

### [SECURITY] SQL Injection via String Interpolation in `updateBatch`

- **Priority**: 🔴 High
- **Problem**: `PostController::updateBatch()` at line 246–248 builds a raw `IN` clause by joining integer-like values directly into the SQL string: `$ids = implode(', ', $ids)` followed by `Post::where("id IN ($ids)")`. While `$ids` is populated by casting each found post's `$p->id` (which is presumably an integer from ActiveRecord), the original post IDs come from `$this->params()->post` — user input — and are passed to `Post::find($post_id)` where `$post_id` is not explicitly cast before use.
- **Impact**: If `Post::find()` or the intermediate processing does not sanitize non-integer input robustly, an attacker could inject into the final `id IN (...)` clause. Even if `$p->id` is safe post-ORM, the pattern normalizes unreviewed user-supplied values through an ORM and re-injects the result into a bare string, creating a fragile trust chain.
- **Solution**: Use parameterized placeholders: `Post::where("id IN (?)", $ids)` where `$ids` is an array, or cast all collected IDs explicitly to `int` before interpolation and add a code comment documenting the explicit sanitization.
- **Effort**: S

---

### [SECURITY] Unvalidated `report` Parameter Written Directly to Log File

- **Priority**: 🔴 High
- **Problem**: `UserController::error()` at line 641–652 accepts `$this->params()->report` from POST input and writes it verbatim to `log/user_errors.log` via `file_put_contents`. There is no authentication requirement, no input sanitization, and no size limit on the `report` parameter. This endpoint is accessible to unauthenticated users via the route system.
- **Impact**: (1) **Log injection**: an attacker can write arbitrary content into the server log, potentially poisoning audit trails or causing log parsers to misread entries. (2) **Disk flooding**: without a size limit, repeated requests with large payloads can fill the disk. (3) The endpoint returns `{success: true}` unconditionally, confirming the write.
- **Solution**: Require authentication for this endpoint (add `no_anonymous` filter). Validate and truncate the `report` parameter (e.g., max 2048 bytes, strip control characters). Consider using `Rails::log()->warning()` instead of a manually managed file.
- **Effort**: S

---

### [SECURITY] Open Redirect via `$previous_url` in `access_denied()`

- **Priority**: 🟠 Medium
- **Problem**: `ApplicationController::access_denied()` at line 47 stores `$previous_url = $this->params()->url || $this->request()->fullPath()`. When redirecting to `user#login`, this URL is passed as a `url` query parameter. If the login action blindly redirects to `$this->params()->url` after successful authentication (as `UserController::authenticate()` at line 167 does: `$path = $this->params()->url ?: '#home'`), any externally supplied URL value can be used for phishing.
- **Impact**: An attacker crafts `https://site.com/some_action?url=https://evil.com` and sends it to victims. After login, they are redirected to the attacker's site.
- **Solution**: Before redirecting, validate that `$this->params()->url` starts with `/` or matches the configured `CONFIG()->url_base`. Reject or strip any absolute URL pointing to a different domain.
- **Effort**: S

---

### Missing MIME-Type Content Inspection for File Uploads

- **Priority**: 🟠 Medium
- **Problem**: `PostFileMethods::validate_content_type()` at `/app/models/Post/FileMethods.php:105` checks `$this->mime_type` against a whitelist. However, the MIME type is set from `determine_content_type()` earlier in the pipeline. The actual content-inspection method needs to be reviewed — if it relies on the uploaded filename extension or client-supplied `Content-Type` rather than `finfo` magic-byte detection, a file rename attack is possible.
- **Impact**: An attacker uploads a PHP file with a `.jpg` extension. If MIME detection relies on extension, the file passes validation, is stored server-side, and if served from a PHP-executing directory, achieves remote code execution.
- **Solution**: Confirm that `determine_content_type()` uses `finfo_open(FILEINFO_MIME_TYPE)` on the file contents, not on the filename. Ensure uploaded files are stored outside the webroot or with execution prevented by webserver configuration. The SWF comment (`// SWF uploads disabled (stored XSS risk via Flash)`) shows awareness of this class of issue.
- **Effort**: M

---

### Commented-Out Transactions on Critical Multi-Step Operations

- **Priority**: 🟠 Medium
- **Problem**: Multiple critical operations lack transaction wrapping because the transaction calls are commented out:
  - `Post::destroy_with_reason()` at `/app/models/Post.php:195`: `// Post.transaction do` / `// end` comments surround a 4-step operation (resolve flag, set flag, first_delete, optionally permanent delete).
  - `Pool::add_post()` and `remove_post()` at `/app/models/Pool.php:137`: `// self::transaction(...)` is commented out, leaving the pool-post link and pool-count update exposed to partial-failure corruption.
  - `WikiPage` operations at lines 84 and 94 also have commented-out transactions.
  - `User::tag_subscriptions_text_setter()` commented-out transaction at line 751.
- **Impact**: Partial failures can leave data in an inconsistent state. For `destroy_with_reason`, a crash between flagging and the actual delete would produce a post flagged but not deleted. For Pool, a race condition could corrupt post counts.
- **Solution**: Uncomment or replace these with the framework's `self::transaction(function(){...})` pattern. The `PostReplacementController` demonstrates the correct approach is already known and used in the codebase.
- **Effort**: M

---

### `DeletionService` Uses Manual `BEGIN`/`COMMIT`/`ROLLBACK` Instead of ORM Transactions

- **Priority**: 🟠 Medium
- **Problem**: `DeletionService::executeAnonymize()` at `/lib/MyImouto/UserDeletion/DeletionService.php:101` calls `$conn->executeSql("BEGIN")` and `$conn->executeSql("COMMIT/ROLLBACK")` manually. If the framework's ORM itself wraps operations in transactions or uses connection pooling with transaction state, manual `BEGIN` may produce nested transactions with undefined behavior.
- **Impact**: Nested transaction mismanagement can cause incomplete rollbacks, silent data corruption, or errors in MySQL (which does not support savepoints transparently). The `$event->save()` call at line 135 — itself an ORM operation — runs inside this manual transaction.
- **Solution**: Replace with `\Rails\ActiveRecord\Base::transaction(function() use (...) {...})` consistently.
- **Effort**: S

---

### Rate Limiter Race Condition (Non-Atomic Check-Then-Increment)

- **Priority**: 🟠 Medium
- **Problem**: `RateLimiter::isLimited()` reads the current count and `RateLimiter::hit()` increments it separately, with no atomic compare-and-swap. The file-cache backend (`Rails::cache()`) is not atomic. Under concurrent requests, two requests can simultaneously read `hits < max`, both pass, and both increment — allowing bursts above the configured limit.
- **Impact**: Under load, the rate limiter can be bypassed by concurrent requests. For login protection (10 per 15 minutes), the effective limit may be 2–3x the configured value during traffic spikes.
- **Solution**: For the login path specifically this is not a critical flaw (the window is short, the increment still happens), but for signup (3 per hour) a persistent Redis-backed atomic increment (`INCR`/`EXPIRE`) would be more robust. Alternatively document the accepted race window explicitly.
- **Effort**: M

---

### Architecture: Business Logic Partially in Controllers, Fat `PostController`

- **Priority**: 🟠 Medium
- **Problem**: `PostController` spans 1382 lines and contains scattered business logic (post creation state assignment, MD5 mismatch handling, batch update loop). The rate-limit check for comments is in `CommentController::create()` with a `# TODO: move this to the model` annotation. The daily-post-limit check in `PostController::create()` is also inline in the controller.
- **Impact**: Difficult to test in isolation, duplicated logic risk, harder to maintain as features grow.
- **Solution**: Extract upload, moderation, and batch-update logic into dedicated service classes analogous to the already-good `PostReplacement` service layer. The `lib/MyImouto/PostReplacement/` trio (`StagingService`, `ApplyService`, `NotificationService`) is the correct pattern to replicate.
- **Effort**: L

---

### `UserController::error()` — No Auth, No Size Limit, No Rate Limit

- **Priority**: 🟠 Medium
- **Problem**: (Separate from the log injection security finding above.) The endpoint has no authentication, no `Retry-After` / rate limiting, and no content-length guard. It writes to disk on every call.
- **Impact**: DoS via disk exhaustion; endpoint enumerable by scanners as a public write surface.
- **Solution**: Add `no_anonymous` or `member_only` filter; cap payload at 2 KB; add rate limiting.
- **Effort**: S

---

### Validation Gaps: `Comment::body` Is the Only Content-Length Validated

- **Priority**: 🟢 Low
- **Problem**: `Comment::validations()` validates non-empty body and UTF-8 correctness. `DmailController::create()` has a per-hour count limit but no body-length limit. `ForumPost` validates UTF-8 and title but no explicit length caps on body. Long-form text fields with no length limit can be used for storage exhaustion or rendering attacks.
- **Impact**: Low-severity: most frameworks truncate at database column limits, but explicit application-layer validation is best practice.
- **Solution**: Add `maxlength` validations matching the DB column sizes for `body`, `title`, and similar free-text fields.
- **Effort**: S

---

### Logging Coverage Is Sparse Outside PostReplacement

- **Priority**: 🟢 Low
- **Problem**: `Rails::log()->info/warning/exception` calls appear in fewer than 15 places across the application (primarily `PostReplacement/NotificationService`, `JobTask`, `Advertisement`, `TagSubscription`, and `AdminController`). Critical security events — login failures, IP ban hits, CSRF failures, permission denials — are not logged. The CSRF failure path returns 403 with no log entry.
- **Impact**: Incident response and audit trail completeness are compromised. There is no structured log record for authentication abuse.
- **Solution**: Add `Rails::log()->warning()` calls on CSRF failures, repeated login blocks, access denied events, and IP ban hits in `ApplicationController`. The PostReplacement notification service demonstrates the correct log format already.
- **Effort**: M

---

### Session `ph` Token Not Rotated on Session Regeneration

- **Priority**: 🟢 Low
- **Problem**: When a user logs out, `UserController::logout()` deletes `session.user_id` but does not invalidate the `remember_token` column in the DB [ANNAHME: it does call `updateAttribute('remember_token', null)` — this is correct]. However, the session `ph` field is set in `_save_cookies` but is never cleared on logout — only on the next login. This is a minor inconsistency.
- **Impact**: Negligible in practice since the session itself is destroyed on logout, but a belt-and-suspenders security review should note the asymmetry.
- **Solution**: Add `$this->session()->delete('ph')` in `logout()` alongside the existing `$this->session()->delete('user_id')`.
- **Effort**: S

---

### Test Coverage Is Narrow

- **Priority**: 🟢 Low
- **Problem**: The test suite contains 7 test files covering: `StagingService` SSRF checks, `ApplyService` approval paths (stub-based), `PostSearch/ApiContract`, `PostApiMethodsFilter`, `TagHelperEscaping`, a `NamespaceDecouplingTest`, and a Mail test. There are no integration tests for authentication flows, CSRF, rate limiting, controller authorization filters, or model validations. The 43 controllers and 53 models are essentially untested in an automated sense.
- **Impact**: Regressions in auth logic, permission checks, and business rules will not be caught by CI.
- **Solution**: Prioritize integration tests for `ApplicationController` before-filters (CSRF verification, IP ban, TOS check), `UserController` login/register/rate-limit flows, and the `Post` creation/update authorization paths. The PHPUnit 13 + PHPStan setup is already a solid foundation.
- **Effort**: L

---

## Technical Debt Summary

The codebase has a clear split between old-heritage code and recently modernized features. The `PostReplacement` subsystem, `ApiKey` model, `UserDeletion` service, and `ApplicationController` security filters (CSRF, security headers, session invalidation, rate limiting) are well-structured and follow defensible patterns. Legacy debt is concentrated in:

1. **Commented-out transactions** on Pool, Post, Wiki, User operations — these are the highest-priority correctness debt.
2. **SHA-1 dual-write** — the migration is half-done (bcrypt is the primary path), but SHA-1 is still actively written, leaving a security liability.
3. **Fat PostController** (1382 lines) — the PostReplacement refactor already demonstrates the right pattern; the same needs to be applied to the upload and moderation paths.
4. **Sparse logging** — straightforward to add incrementally.

Recommended payoff order: (1) stop writing SHA-1, (2) fix `blocked_only` in DmailController, (3) uncomment/replace commented-out transactions, (4) fix the `error()` endpoint, (5) fix open-redirect in `access_denied()`, (6) parameterize the `updateBatch` `IN` clause, (7) expand test coverage.

---

## Backend Score: 6/10

**Justification**: The codebase earns above-average marks for its security-conscious recent work (proper bcrypt with transparent SHA-1 migration, timing-safe `hash_equals()` throughout, CSRF verification with header support, security response headers including HSTS/CSP, MySQL advisory locks for concurrency on replacements, SSRF protection with IPv6-mapped address handling, rate limiting on login/signup/reset flows, API key hashing). The overall architecture is coherent and the PostReplacement service layer is a strong model.

Deductions are for: four high-severity security issues (SHA-1 retention, crc32 session tokens, `blocked_only` semantic error, unauthenticated write endpoint), two medium-severity SQL safety concerns, a pattern of commented-out transactions indicating known but unaddressed correctness gaps, and very sparse automated test coverage for the controller and authorization surface. The legacy framework dependency (railsphp 1.0.\*) also constrains the ability to adopt modern PHP idioms such as typed properties and dependency injection.
