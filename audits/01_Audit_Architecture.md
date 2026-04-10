# Architecture & Structure Audit

## Architecture Overview

myimouto is an imageboard application (~12,000 lines of app code across 74 model files and 43 controller files) built on `railsphp/railsphp` 1.0.\*, a custom Rails-inspired PHP framework with no wider community or security advisory channel. The project originally targeted Moebooru/Danbooru behavior and is now being actively modernized, with 45 feature specs (PROJ-1 through PROJ-45) either deployed or planned. The application follows a conventional MVC layout (`app/controllers`, `app/models`, `app/views`) with supporting libraries in `lib/`, database migrations in `db/migrate/`, and a CI pipeline through GitHub Actions (lint, PHPUnit, PHPStan, PHP-CS-Fixer). A Laravel migration (PROJ-36) is tracked as a planned future initiative.

---

## Findings

### 1. Proprietary Framework with No Community, No Security Advisories, No Packagist Maintenance

- **Priority:** High
- **Problem:** The entire MVC stack depends on `railsphp/railsphp` 1.0.\* — a single-author, unmaintained framework. Every security feature (CSRF, bcrypt, rate-limiting, session token invalidation) had to be manually implemented. The framework exposes no security advisory feed. The bus factor is 1. The `composer.json` also uses `railsphp/actsasversioned` and `railsphp/willpaginate`, both from the same ecosystem.
- **Impact:** Any framework-level vulnerability has no upstream fix path. Security work (PROJ-27 through PROJ-31) had to be retrofitted by hand rather than inherited from a maintained base. Onboarding new developers requires learning a non-documented, non-standard framework.
- **Solution:** PROJ-36 (Laravel migration) is already planned. Until then, treat all framework surfaces (routing, session, ORM) as potentially unsafe and continue the manual hardening approach. Document the framework API internally.
- **Effort:** XL (PROJ-36 is scoped as multi-month)

---

### 2. Mixed Naming Convention for Controller Action Methods

- **Priority:** Medium
- **Problem:** Controller action methods are inconsistently named. Approximately 60% use `snake_case` (`index`, `show`, `popular_by_day`, `upload_problem`) while 40% use `camelCase` (`uploadProblem`, `updateBatch`, `deletedIndex`, `acknowledgeNewDeletedPosts`, `markAsSpam`, `editUser`, `resetPassword`, `blockIp`). The route definitions in `config/routes.php` all use `snake_case` URLs, meaning the framework silently maps them to camelCase method names via some internal inflection. This is invisible to a reader navigating from route to controller.
- **Impact:** A developer reading `$this->match('post/upload_problem')` must know the framework maps `upload_problem` to `uploadProblem()`. This reduces code navigability and increases onboarding friction.
- **Solution:** Standardize all action methods to `snake_case` (consistent with routes and Ruby on Rails convention this framework emulates) or document the inflection rule explicitly. Enforce through PHP-CS-Fixer or a custom PHPStan rule.
- **Effort:** M

---

### 3. Post Model Is an Oversized God Object Despite Trait Decomposition

- **Priority:** Medium
- **Problem:** `Post.php` (521 lines) composes 14+ traits (`PostSqlMethods`, `PostFileMethods`, `PostTagMethods`, etc.) loaded via a runtime `glob()` call. The total logical size of the Post entity across all trait files exceeds 3,500 lines. Individual traits (`FileMethods.php` at 727 lines, `SqlMethods.php` at 465 lines, `TagMethods.php` at 397 lines) are themselves large enough to be standalone concerns. Traits use non-namespaced global functions (`CONFIG()`, `Rails::root()`) and call static methods on sibling models (`Tag`, `TagAlias`, `User`, `Pool`) creating invisible coupling. The trait loading mechanism (`foreach (glob(...) as $trait) require $trait`) is load-order sensitive and bypasses the autoloader.
- **Impact:** Changes to Post touch many tangentially related concerns simultaneously. Testing individual concerns in isolation requires the full model to be bootstrapped. The glob-based loader means IDE navigation and static analysis tools cannot resolve trait members without running the code.
- **Solution:** Convert the glob loader to explicit `require` or PSR-4 autoloaded trait includes. Extract service objects from FileMethods and SqlMethods (pattern already demonstrated by `lib/MyImouto/PostReplacement/StagingService`). Consider aligning with the service-object pattern being established in `lib/MyImouto/`.
- **Effort:** L

---

### 4. User Model Is a 1,002-Line Monolith

- **Priority:** Medium
- **Problem:** `/d/repos/myimouto/myimouto/app/models/User.php` is 1,002 lines and lacks trait decomposition unlike Post. It handles authentication logic, authorization level resolution (via `__call()` magic methods), session management, cookie generation, email verification, favorites, blacklists, OAuth identity, passkeys, and UI preferences — all in a single class with no namespace.
- **Impact:** High coupling makes it difficult to test authentication logic independently of ORM behavior. The `__call()` magic for `is_X_or_higher()` patterns creates runtime behavior that cannot be verified statically by PHPStan.
- **Solution:** Extract authentication into a service object (e.g., `MyImouto\Auth\AuthenticationService`), authorization level checks into a dedicated value object or trait. The `lib/MyImouto/` pattern exists and should be extended here.
- **Effort:** L

---

### 5. PostController Is 1,381 Lines with Fat Action Methods

- **Priority:** Medium
- **Problem:** `PostController.php` is 1,381 lines. Several action methods contain multiple embedded business logic paths (e.g., `create()` embeds MD5 validation, duplicate detection, similar image logic; `flag()` embeds flag reason validation and moderation logic). Business logic that belongs in the model or a service layer is mixed with request/response handling.
- **Impact:** Violates single-responsibility. Makes controller-level testing impossible without a full stack (no dependency injection). New developers cannot distinguish "what the controller orchestrates" from "what the model owns."
- **Solution:** Extract business logic from `create()`, `flag()`, `vote()`, and `updateBatch()` into model methods or service objects. Controllers should delegate and respond, not compute.
- **Effort:** M

---

### 6. No Namespace or PSR-4 Autoloading for Legacy App-Layer Classes

- **Priority:** Medium
- **Problem:** All classes in `app/controllers/`, `app/models/`, `app/helpers/`, and `app/mailers/` use the global namespace (no `namespace` declaration). Only `lib/MyImouto/` and `lib/Zend/` (shim) are registered under PSR-4. The framework uses its own class loader for the app layer. Root-level `lib/` files (`DText.php`, `Danbooru.php`, `SimilarImages.php`, `ExternalPost.php`, `ExtractUrls.php`) are also unnamespaced. Two isolated exceptions (`Post\CacheMethods` in namespace `Post`, `Tag\CacheMethods` in namespace `Tag`) create an inconsistent partial namespace at the model subdirectory level.
- **Impact:** Global namespace creates collision risk as the project grows. Cannot use PSR-4 tooling, IDE indexing is weaker, PHPStan type inference is degraded. The two inconsistently namespaced files in `app/models/Post/` and `app/models/Tag/` suggest an abandoned attempt at namespacing that stopped partway.
- **Solution:** Incrementally namespace app-layer classes starting with new service objects. Normalize `Post\CacheMethods` and `Tag\CacheMethods` to be consistent with their siblings (either all namespaced or all global). Prefer the former as part of a migration path.
- **Effort:** L

---

### 7. `strict_types` Not Declared in Application Code

- **Priority:** Medium
- **Problem:** Zero files in `app/` declare `declare(strict_types=1)`. Zero files in `lib/MyImouto/` declare it either. All 8 test files that exist declare strict types, creating a split contract: tests enforce strict typing, but the code under test does not. This means type coercions (e.g., string `"1"` silently accepted as `int`) happen invisibly in production code paths.
- **Impact:** On PHP 8.5 (the target), silent type coercions hide bugs at call boundaries. PHPStan can detect some issues but cannot enforce parameter types at runtime without `strict_types`.
- **Solution:** Enable `strict_types=1` gradually starting with `lib/MyImouto/` and new controller/service files. Do not enable retroactively on large legacy files without type-checking all call sites first.
- **Effort:** M

---

### 8. Hardcoded `/tmp/post_replacements` Staging Path

- **Priority:** Medium
- **Problem:** `lib/MyImouto/PostReplacement/StagingService.php` declares `const STAGING_DIR = '/tmp/post_replacements'` as a hardcoded constant. Although it prepends `Rails::root()` on line 284, the constant itself is `/tmp/...` which will be wrong in all non-Linux deployments and in containerized environments with read-only `/tmp`.
- **Impact:** Post replacement uploads will silently fail on Windows dev environments or Docker containers with alternative temp directories. The path is not configurable via `DefaultConfig`.
- **Solution:** Move the staging path to `DefaultConfig` with a sensible default (`$post_replacement_staging_dir`), validated at startup. `StagingService` reads from `CONFIG()->post_replacement_staging_dir`.
- **Effort:** S

---

### 9. Deprecated `utf8_encode()` and `utf8_decode()` Still in Use

- **Priority:** Medium
- **Problem:** `utf8_encode()` and `utf8_decode()` are deprecated since PHP 8.2 and will generate deprecation notices on PHP 8.5. They appear in `PostController.php` (lines 1151, 1168) for the import feature, and in `Post/FileMethods.php` (lines 57, 71).
- **Impact:** Deprecation notices in production error logs. These functions will be removed in a future PHP version, breaking the import feature.
- **Solution:** Replace with `mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1')` or `iconv('ISO-8859-1', 'UTF-8', $str)`.
- **Effort:** S

---

### 10. Monolithic Route File with Non-RESTful Verb Overloading

- **Priority:** Medium
- **Problem:** `config/routes.php` is 376 lines and uses `$this->match(...)` with `['via' => ['get', 'post']]` throughout — many destructive actions (destroy, update, delete) accept both GET and POST. Comments on several routes acknowledge this is a workaround ("GET shows confirmation page, POST deletes — controller checks isPost"). This is a pattern from early 2010s Rails and bypasses HTTP method semantics. CSRF protection (PROJ-28) partially mitigates this, but GET-accessible destructive endpoints remain a security anti-pattern.
- **Impact:** GET-accessible destructive endpoints can be triggered by loading a URL, prefetching, or CSRF in edge cases. Anti-crawlable semantics (DELETE via GET) are violated.
- **Solution:** Migrate destructive actions to POST/DELETE only. Separate confirmation pages (GET) from execution endpoints (POST/DELETE). This is a prerequisite for PROJ-36 and aligns with REST semantics.
- **Effort:** M

---

### 11. Dual Mail Namespace: Zend Shim and MyImouto Canonical Coexist

- **Priority:** Low
- **Problem:** `lib/Zend/` contains 13 files that are shim wrappers pointing to `lib/MyImouto/Mail/` (the canonical implementation). Both are PSR-4 registered (`Zend\\` and `MyImouto\\`). The shim exists for backward compatibility after PROJ-7 and PROJ-16 migrations. The test in `tests/Unit/Mail/NamespaceDecouplingTest.php` verifies the aliasing works. The `composer.json` uses `replace` to displace `zendframework/zend-mail` and `zendframework/zend-crypt`.
- **Impact:** Two namespaces serving the same purpose is cognitive overhead. The shim is a maintenance surface that must be kept in sync with the canonical implementation. This is a known transitional state (per PROJ-16 spec).
- **Solution:** Complete the shim sunset: audit all remaining `Zend\Mail` call sites, migrate them to `MyImouto\Mail`, then remove `lib/Zend/` and the PSR-4 `Zend\\` registration. Verify via test suite.
- **Effort:** S

---

### 12. Test Coverage Is Very Thin — Only 8 Unit Test Files

- **Priority:** High
- **Problem:** The entire test suite consists of 8 files across 2 categories (`tests/Unit/` and `tests/Security/`). Coverage targets `lib/MyImouto/` service objects (PostReplacement, PostSearch, Mail, UserDeletion) and two security unit tests. There are zero tests for models (`Post`, `User`, `Tag`, `Pool`), zero controller tests, and zero integration tests. The CI pipeline runs these 8 files and reports coverage only for the tested modules.
- **Impact:** Regressions in models, controllers, and the custom query builder (`PostSqlMethods`) go undetected. PROJ-36 (Laravel migration) will have no safety net without model/controller tests. Critical paths (post upload, tag processing, post replacement approval) are entirely untested.
- **Solution:** Add PHPUnit tests for at minimum `Post::generate_sql`, `User::authenticate`, `TagAlias::to_aliased`, and the PostController `create` path using the existing stub/bootstrap pattern demonstrated in `ApplyServiceApprovalBootstrap.php`. Target 40% statement coverage for `app/models/` as a first milestone.
- **Effort:** L

---

### 13. `IpBans` Model Uses Incorrect Plural Naming for a Singular Entity

- **Priority:** Low
- **Problem:** The model is named `IpBans` (plural) while all other models follow the Rails convention of singular class names (`Ban`, `User`, `Post`, `Tag`). The corresponding table is `ip_bans` (correctly plural). The ActiveRecord ORM auto-maps singular class to plural table, so `IpBan` → `ip_bans` would be correct. This inconsistency means `IpBans::where(...)` looks like a class that encapsulates a collection rather than an individual record.
- **Impact:** Low functional risk (the framework presumably handles it), but creates a false mental model for developers reading `IpBans::where("ip_addr = ?", ...)`.
- **Solution:** Rename to `IpBan`, verify the ORM table mapping, update all references.
- **Effort:** S

---

### 14. Hardcoded Default Password Salt in Public Default Config

- **Priority:** High
- **Problem:** `config/default_config.php` line 23 sets `public $user_password_salt = 'choujin-steiner'` — a value that is publicly known because it exists in the original open-source Moebooru repository and in this codebase. Any operator who does not override this in `config/config.php` runs with a published salt, allowing pre-computation of rainbow tables for SHA1 password hashes from the legacy auth stack.
- **Impact:** If an operator deploys without overriding the salt, all legacy SHA1 hashed passwords are crackable faster using known-salt attack tables. PROJ-27 (bcrypt migration) mitigates this for users who log in after migration, but the risk remains for any unlogged-in users with old hash rows.
- **Solution:** Add a startup validation that aborts with a fatal error if the salt equals the default value in non-development environments. Document this prominently in the installation guide and in `install.php`.
- **Effort:** S

---

### 15. Missing Database Transactions in Several Multi-Step Operations

- **Priority:** High
- **Problem:** Several multi-step database operations lack explicit transactions. Notable cases: `Pool.php` line 137 shows a transaction block that is commented out (`// self::transaction(function() { ...`). In `Moebooru/Versioning/Versioning.php` line 282 there is a similar commented-out transaction block. The CLAUDE.md acknowledges this: "Use DB transactions for multi-step operations (many are currently missing/commented out — this is a known debt)."
- **Impact:** Partially committed states are possible in post pool operations and versioning updates. If a write to `pools` succeeds but the corresponding `pools_posts` insert fails, the pool record is corrupt.
- **Solution:** Restore and add `self::transaction(function() { ... })` blocks for all multi-step writes. Pool add/remove, tag implication cascades, and post replacement state transitions should be prioritized.
- **Effort:** M

---

## Documentation Gaps

| Document | Status | Severity |
|---|---|---|
| CHANGELOG.md | Missing (project root has no CHANGELOG) | Medium |
| CONTRIBUTING.md | Missing (README has a brief contributing section but no dedicated file) | Medium |
| SECURITY.md | Missing (no coordinated disclosure policy documented) | High |
| API documentation | Missing (no OpenAPI/Swagger spec; API endpoints documented only via feature specs) | Medium |
| Inline docblocks in app/models | Sparse — 110 total `@param`/`@return`/`@throws` across 74 files, mostly in `lib/MyImouto/` | Medium |
| Inline docblocks in app/controllers | Near-absent — only comments in ApplicationController | Medium |
| Architecture Decision Records (ADRs) | Missing (decisions like "why railsphp", "why trait-based Post decomposition" are not recorded; PROJ-36 spec partially fills this gap) | Low |
| `config/default_config.php` attribute descriptions | Good — most public properties have inline comments | Low (gap: ~20% undocumented properties) |
| Onboarding guide for new developers | README has installation steps; no "how the code works" walkthrough | Medium |
| `lib/` root-level files (DText, Danbooru, SimilarImages, ExternalPost) | No docblocks; no description of external dependencies or expected input formats | Medium |

**Total gaps: 10 (1 critical, 7 important, 2 nice-to-have)**

- Critical: SECURITY.md (no disclosure policy for a publicly accessible multi-user application)
- Important: CHANGELOG.md, CONTRIBUTING.md, API documentation, controller docblocks, model docblocks, onboarding guide, lib/ root-level documentation
- Nice-to-have: ADRs, full default_config annotation coverage

## Recommended Next Step

For fixing documentation gaps, use the `code-documenter` agent — it specializes in README, API docs, inline comments, changelogs, and architecture documentation.

---

## Architecture Score: 5/10

**Justification:**

The project demonstrates clear awareness of its own architectural debt and has made meaningful structural progress: the `lib/MyImouto/` service layer is cleanly namespaced, the CI pipeline is well-configured, the security hardening campaign (PROJ-27 through PROJ-31) is thorough, the feature spec index is well-organized, and the `ApplicationController` correctly centralizes auth, CSRF, rate-limiting, and security headers.

What holds the score to 5 are the structural problems that will compound over time: the proprietary, unmaintained framework with no security advisory path; a test suite of 8 files covering less than 5% of functional surface area; the Post model's distributed complexity across 14+ traits loaded by glob; a 1,000-line User monolith; zero `strict_types` in production code; and several confirmed missing transactions on multi-step DB operations. The planned Laravel migration (PROJ-36) acknowledges the root cause, but until it executes, each new feature must work around the framework rather than leverage it.

The 5 is a "structurally aware but technically constrained" score. The project knows what needs to change and has documented it; the constraint is the legacy foundation it sits on.
