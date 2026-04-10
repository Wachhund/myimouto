# Database & Data Model Audit

## Overview

- **DB Engine**: MySQL 8.0+ / MariaDB 10.6+ (InnoDB, utf8 baseline, utf8mb4 target via migration)
- **ORM**: Custom Rails-inspired ActiveRecord (`railsphp/railsphp`)
- **Migration tool**: Custom PHP migration runner (`php config/boot.php db:migrate`)
- **Schema baseline**: `D:/repos/myimouto/myimouto/db/schema.sql`
- **Migration files**: 38 files spanning 2013-11-18 to 2026-03-10 (timestamps)
- **Core tables in schema.sql**: 28 tables
- **Additional tables added by migrations**: ~14 tables (`mod_actions`, `exception_logs`, `user_name_change_requests`, `user_name_change_history`, `forum_topic_subscriptions`, `forum_post_votes`, `upload_whitelists`, `tickets`, `takedowns`, `takedown_posts`, `user_deletion_events`, `user_oauth_identities`, `user_passkeys`, `api_keys`, `site_settings`)
- **Total tables across all migrations**: approximately 43 tables
- **Model files**: 53 PHP files (Post uses 14+ trait-based decomposition)

---

## ER Analysis (Textual)

### Core Content Cluster
The `posts` table is the central entity. It connects to:
- `posts_tags` (many-to-many pivot) -> `tags`
- `post_tag_histories` (append-only audit log)
- `post_votes` (user vote junction)
- `post_replacements` (staged file replacement workflow)
- `favorites` (user-post bookmark junction)
- `comments`, `notes`, `note_versions` (content annotations)
- `pools_posts` -> `pools` (ordered collection membership)

### Tag Management Cluster
`tags` is referenced by `posts_tags`, `tag_aliases`, `tag_implications`, and `tag_subscriptions`. Tag aliasing and implication resolution happen at application level via `TagAlias::to_aliased()` and `TagImplication::with_implied()` loops rather than DB-level computed joins.

### User & Auth Cluster
`users` is the hub for nearly all other tables. Authentication data lives partly in `users` (legacy `password_hash` SHA1, new `bcrypt_password_hash`, `api_key`), and partly in new migration-added tables (`user_oauth_identities`, `user_passkeys`, `api_keys`). This split is intentional transitional state.

### History / Versioning Cluster
`histories` + `history_changes` form a generic EAV-style audit trail. `history_changes` uses `table_name`/`remote_id`/`column_name` to point to any entity, trading FK integrity for generality.

### Moderation Cluster
`flagged_post_details`, `bans`, `ip_bans`, `mod_actions`, `tickets`, `takedowns`, `takedown_posts`, `user_records` form a moderation subsystem. Several of these tables lack FKs to each other.

### Counter / Denormalization Cluster
`table_data`, `tags.post_count`, `pools.post_count`, `post_sets.post_count`, and `posts.has_children` / `posts.last_commented_at` / `posts.last_noted_at` / `posts.score` / `posts.cached_tags` form a deliberate denormalization layer maintained by triggers and application callbacks.

---

## Findings

### 1. Schema Baseline vs. Migration Drift — tables missing from schema.sql

- **Priority**: 🔴 High
- **Problem**: `schema.sql` represents the original schema. Approximately 15 tables created by 2026-era migrations (`mod_actions`, `exception_logs`, `user_name_change_requests`, `user_name_change_history`, `forum_topic_subscriptions`, `forum_post_votes`, `upload_whitelists`, `tickets`, `takedowns`, `takedown_posts`, `user_deletion_events`, `user_oauth_identities`, `user_passkeys`, `api_keys`, `site_settings`) are not reflected in `schema.sql`. A developer installing from `schema.sql` alone would be missing 35% of the live table surface area. The `db/table_schema/production` sub-directory exists but appears to contain only one entry, suggesting the canonical schema has not been kept in sync.
- **Impact**: Fresh installs from `schema.sql` will fail at runtime for any feature that touches these tables. CI cannot validate schema correctness. Onboarding friction is high.
- **Solution**: Generate a single authoritative `schema.sql` from a fully-migrated database (e.g., `mysqldump --no-data`) and commit it. Alternatively, adopt a "dump after migrate" CI step. The `install.php` flow must be verified to run all migrations.
- **Effort**: M

### 2. `change_seq` Column Referenced in Code but Missing from Schema

- **Priority**: 🔴 High
- **Problem**: `PostSqlMethods::generate_sql()` references `p.change_seq` as a sortable/filterable column (lines 138, 420–423 of `SqlMethods.php`). The `PostChangeSequenceMethods` trait declares `touch_change_seq()` and `update_change_seq()` but the actual SQL update is commented out. `posts.change_seq` is not present in `schema.sql` and the column does not appear in any migration file. `PostApiMethods` hard-codes `'change' => 0` as a workaround.
- **Impact**: Any query using `order:change` or `change:>N` will throw a MySQL unknown column error at runtime, causing a 500 response. The API always returns `change=0`, breaking any client relying on incremental sync.
- **Solution**: Either add a `change_seq` auto-increment column to `posts` with a supporting migration and re-enable the update logic in `update_change_seq()`, or fully remove all references to `change_seq` and the `change:` search modifier if the feature is intentionally dropped.
- **Effort**: M

### 3. Duplicate Foreign Key Constraint on `tag_implications.consequent_id`

- **Priority**: 🔴 High
- **Problem**: `schema.sql` defines two FK constraints on `tag_implications.consequent_id`: `fk_consequent_id` and `fk_tag_implications__consequent_id`. Both point to `tags.id ON DELETE CASCADE`. MySQL will enforce both, doubling the constraint check overhead on every write. Migration `20260308130001_convert_utf8mb4.php` and `20260308130002_cleanup_fk_and_ip_fields.php` attempt to clean this up, but the duplicate is present in the schema baseline used by all fresh installs.
- **Impact**: Insert/update/delete on `tag_implications` is unnecessarily slowed. On some MySQL versions, duplicate constraint names may cause an error if schema.sql is applied directly.
- **Solution**: Remove `fk_consequent_id` from `schema.sql`. The cleanup migration is already correct; the issue is the baseline.
- **Effort**: S

### 4. `flagged_post_details` Had No Primary Key in Baseline Schema

- **Priority**: 🔴 High
- **Problem**: The baseline `flagged_post_details` table has no primary key column — only a composite index `post_id`. InnoDB silently creates a hidden 6-byte row-id PK. Migration `20260309000003_harden_flagged_post_details.php` adds an explicit `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, but all installations that existed before this migration ran have inconsistent row identification semantics.
- **Impact**: Without an explicit PK, application code cannot reliably refer to individual flag records by ID. The `FlaggedPostDetail` model can only be destroyed via full-table scan if the FK cascade is not triggered. Row-level locking is less efficient on hidden-PK tables.
- **Solution**: The migration fix is correct. Ensure schema.sql is regenerated after this migration runs. Add a test that each table has an explicit primary key.
- **Effort**: S (migration already written, just needs schema refresh)

### 5. `posts` Table Has No `change_seq` or Global Sequence Column; `table_data` Is a Manual Counter Anti-Pattern

- **Priority**: 🟠 Medium
- **Problem**: `table_data` is a hand-maintained row-count cache for posts and users, updated by triggers and application callbacks. `recalculate_row_count()` in `PostCountMethods` uses `COUNT(*) WHERE parent_id IS NULL` (parent-only count) while the insert trigger simply counts all rows — these two definitions are inconsistent. The `row_count()` method (used for display) adds additional filters (`NOT is_held`, `status <> 'pending'`, `is_shown_in_index`) that are never reflected in `table_data`.
- **Impact**: The displayed count can diverge from the trigger-maintained count. `fast_count()` bypasses `table_data` entirely and uses the full `generate_sql()` path with caching, making `table_data` effectively unused for the primary count displayed to users.
- **Solution**: Either eliminate `table_data` entirely (keep only `fast_count` with its cache) or document precisely which count each row is supposed to represent and add a nightly reconciliation job. The partial use creates confusion without benefit.
- **Effort**: M

### 6. `posts.cached_tags` Denormalization — Full Delete-Insert on Every Tag Edit

- **Priority**: 🟠 Medium
- **Problem**: `PostTagMethods::commit_tags()` performs a `DELETE FROM posts_tags WHERE post_id = ?` followed by a bulk `INSERT INTO posts_tags VALUES (?, ?), ...` for every tag-update operation. This is an unconditional full replacement regardless of how many tags actually changed. The comment on line 319 acknowledges: "TODO: be more selective in deleting from the join table."
- **Impact**: For a post with 30 tags, editing one tag causes 30 FK-cascade-triggered tag-count decrements (via the `trg_posts_tags__delete` trigger) and 30 increments (via `trg_posts_tags__insert`). At scale this generates significant write amplification on the hot `tags.post_count` column. InnoDB row-level locking still serializes trigger-driven counter updates per tag row.
- **Solution**: Compute the diff of added/removed tags and issue targeted `INSERT`/`DELETE` statements for only the changed rows. The `old_tags` variable is already available in `commit_tags()` to support this.
- **Effort**: M

### 7. `ORDER BY RAND()` in Search Engine

- **Priority**: 🟠 Medium
- **Problem**: `PostSqlMethods::generate_sql()` (line 434) supports `order:random`, which emits `ORDER BY RAND()`. On `posts`, this forces a full table sort with a per-row call to MySQL's random function.
- **Impact**: For a table with 100k+ posts, `ORDER BY RAND()` can take several seconds and pins a CPU core. The query cannot be index-assisted.
- **Solution**: Replace with a keyset-random approach: `WHERE id >= (SELECT FLOOR(MAX(id) * RAND()) FROM posts) LIMIT N`, or pre-generate random values stored in `posts.random` (the column already exists in the schema but is not used for this purpose). Alternatively, cache a shuffled ID list and paginate through it.
- **Effort**: M

### 8. Tag Implication Resolution — Up to 10 Nested Queries Per Tag Per Save

- **Priority**: 🟠 Medium
- **Problem**: `TagImplication::with_implied()` resolves transitive tag implications with a PHP loop of up to 10 iterations, each issuing a `SELECT` query. For a post with 20 tags and a deep implication chain, this can generate up to 200 sequential round-trips to the database at save time.
- **Impact**: Post save latency grows linearly with implication chain depth and tag count. There is no query batching or recursive CTE used.
- **Solution**: Replace the iterative PHP loop with a single recursive MySQL CTE: `WITH RECURSIVE implied AS (SELECT consequent_id FROM tag_implications WHERE predicate_id IN (...) AND NOT is_pending UNION ALL SELECT ti.consequent_id FROM tag_implications ti JOIN implied i ON ti.predicate_id = i.consequent_id WHERE NOT ti.is_pending) SELECT DISTINCT t.name FROM implied JOIN tags t ON t.id = implied.consequent_id`. MySQL 8.0+ supports this natively.
- **Effort**: M

### 9. `lower(p.source) LIKE lower(?)` — Non-Sargable Source Search

- **Priority**: 🟠 Medium
- **Problem**: Source search in `generate_sql()` (line 167) wraps `p.source` in `lower()`, which prevents use of the `ix_posts__source` index added in migration `20260308130000`. A function-wrapped column is not sargable in MySQL unless a functional index is explicitly created on that expression.
- **Impact**: Every `source:` query performs a full `posts` table scan after the other conditions, even with the index present.
- **Solution**: Either use a case-insensitive collation (utf8mb4_unicode_ci) on `posts.source` so a plain `LIKE ?` uses the index, or create a MySQL 8 functional index: `ALTER TABLE posts ADD INDEX ix_posts__source_lower ((LOWER(source)))` and change the query to `LOWER(p.source) LIKE LOWER(?)`.
- **Effort**: S

### 10. `pool_name` LIKE Search With Leading Wildcard in `generate_sql()`

- **Priority**: 🟠 Medium
- **Problem**: Pool name search (line 255) uses `LOWER(pools.name) LIKE ?` with value `"%<term>%"` — a leading wildcard. This prevents any index on `pools.name` from being used. `pools.name` has a `UNIQUE KEY pool_name` index that only helps for exact lookups.
- **Impact**: Full scan of `pools` table joined to all matching `pools_posts` on every pool-name-pattern search query.
- **Solution**: For substring searches, consider a FULLTEXT index on `pools.name` and switch to `MATCH(pools.name) AGAINST (? IN BOOLEAN MODE)`. For simpler cases, require a suffix wildcard only and document the limitation.
- **Effort**: M

### 11. `GROUP BY p.id` on All Non-Count Post Queries

- **Priority**: 🟠 Medium
- **Problem**: `generate_sql()` appends `GROUP BY p.id` to every non-count query (line 385), regardless of whether multi-row joins are present. When searching for a single tag (one `INNER JOIN posts_tags`), there is guaranteed to be at most one row per post_id, so the `GROUP BY` is redundant. For complex queries with multiple joins (e.g., exclude-pools + include-tags + favor join), the GROUP BY is correct and necessary.
- **Impact**: MySQL must sort or hash-aggregate results even on simple queries that do not need deduplication, adding CPU and memory overhead.
- **Solution**: Track whether multi-row joins have been added (boolean flag in the query builder) and only emit `GROUP BY` when truly necessary. For simple single-tag queries via `find_by_tag_join()`, the GROUP BY is already absent — that optimised path should be used more consistently.
- **Effort**: M

### 12. Schema Charset Mixed utf8 / utf8mb4 — Baseline vs. Live

- **Priority**: 🟠 Medium
- **Problem**: All 28 baseline tables use `DEFAULT CHARSET=utf8`. Mixed inline overrides exist: `posts.status` uses `utf8_unicode_ci`, `tags.name` uses `utf8_bin`, `tag_subscriptions.tag_query` uses `utf8` (after a charset migration changed it from latin1 back). The `utf8mb4` conversion migration (`20260308130001`) is large, invasive (drops all FKs), and requires a maintenance window. Until it has run, 4-byte Unicode characters (emoji, rare CJK) in tags, sources, or usernames will be silently truncated.
- **Impact**: Silent data corruption for any content containing emoji or 4-byte Unicode. The migration exists but may not be run on all deployments.
- **Solution**: Complete the utf8mb4 migration on all environments. Update `schema.sql` to reflect `DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` as the baseline. Note that `tags.name` will need a special case: it was `utf8_bin` for case-sensitive uniqueness — with utf8mb4, use `utf8mb4_bin`.
- **Effort**: L

### 13. No `down()` Method on Most Migrations

- **Priority**: 🟠 Medium
- **Problem**: Of the 38 migrations, only 2 implement a `down()` method: `20260309100001_add_scheduled_job_support.php` and `20260310000001_create_site_settings.php`. All others have no rollback capability.
- **Impact**: Cannot roll back a failed deployment without manual DDL intervention. Schema drift becomes permanent once a migration runs.
- **Solution**: For table-creation migrations, add `$this->dropTable(...)` in `down()`. For column-addition migrations, add `$this->removeColumn(...)`. For data migrations, document that they are one-way. This is lower priority for the 2013-era migrations but should be standard for all new migrations.
- **Effort**: L

### 14. `users.api_key` — Plaintext API Key in `users` Table Alongside New `api_keys` Table

- **Priority**: 🟠 Medium
- **Problem**: `users.api_key` stores a plaintext random API key. Migration `20260309000012` creates a separate `api_keys` table with `hashed_key`. The `User::authenticate_with_api_key()` method still queries `users.api_key = ?` with the plaintext value. Both systems coexist with no migration path defined between them.
- **Impact**: Plaintext API keys in the `users` table are a security risk — a read-only SQL injection or DB dump exposes all keys directly. The new `api_keys` table (hashed) is the correct direction but is not yet the active authentication path.
- **Solution**: Migrate all existing `users.api_key` values to the `api_keys` table (hashed), update `authenticate_with_api_key()` to use `api_keys`, then drop `users.api_key`. Add a migration that performs this data migration.
- **Effort**: M

### 15. `users.password_hash` (SHA1) Still Present Alongside `bcrypt_password_hash`

- **Priority**: 🟠 Medium
- **Problem**: `users.password_hash` stores `sha1(salt + '--' + password + '--')`. SHA1 is cryptographically broken for password hashing. The transparent migration to bcrypt in `User::authenticate()` works only on next login — inactive users remain SHA1 indefinitely.
- **Impact**: If the database is compromised, SHA1-hashed passwords are crackable with commodity hardware within hours.
- **Solution**: Add a scheduled job that pre-hashes remaining SHA1 accounts to a bcrypt-wrapped form (e.g., `bcrypt(sha1(password))` where the SHA1 hash is treated as input — this is a known upgrade pattern for offline migration). After a grace period, force password resets for any account still on SHA1.
- **Effort**: M

### 16. `artists` Table Missing Unique Index on `name`

- **Priority**: 🟠 Medium
- **Problem**: `artists.name` has only a regular index via `KEY fk_artists__updater_id (updater_id)`. There is no unique constraint on `artists.name`. Migration `20260308130000` attempts to add `uk_artists__name` as a unique index but with duplicate-checking safety (falls back to a non-unique index if duplicates exist).
- **Impact**: Without a unique constraint, duplicate artist records can be inserted concurrently, leading to confusing search results and broken alias chains.
- **Solution**: Deduplicate artist names and then enforce a unique index. The migration already handles this cautiously — ensure duplicates are resolved and the unique index is confirmed after migration.
- **Effort**: S

### 17. `wiki_pages.title` Missing Unique Index in Schema Baseline

- **Priority**: 🟠 Medium
- **Problem**: `wiki_pages` has no unique constraint on `title` in the baseline schema. Migration `20260308130000` adds `uk_wiki_pages__title` conditionally. `WikiPage::find_by_title()` assumes uniqueness to return a single record.
- **Impact**: Concurrent wiki page creation can produce duplicate titles. `find_by_title()` will return the first match, making the second record unreachable via normal navigation.
- **Solution**: Same pattern as artists — deduplicate and enforce the unique index. Merge or redirect duplicate wiki pages.
- **Effort**: S

### 18. `pools_posts` Allows Duplicate (pool_id, post_id) Pairs

- **Priority**: 🟠 Medium
- **Problem**: The baseline `pools_posts` table has a PK on `id` and four individual/composite indexes but no unique constraint on `(pool_id, post_id)`. Migration `20260309000009_add_pool_search_indexes.php` adds `UNIQUE INDEX ix_pools_posts__pool_post (pool_id, post_id)` conditionally. Until this migration runs, application-level code (guarded by `catch (Pool_PostAlreadyExistsError $e)`) is the only protection against duplicates.
- **Impact**: A race condition (two concurrent pool-add operations for the same post) can produce duplicate rows, corrupting `post_count` counters and causing incorrect pool display.
- **Solution**: Ensure the unique index migration has been applied. Add an application-level unique validation as defence-in-depth.
- **Effort**: S

### 19. Inconsistent `ON DELETE` Behaviour for `flagged_post_details.user_id`

- **Priority**: 🟠 Medium
- **Problem**: `flagged_post_details` has `ADD CONSTRAINT fk_flag_post_details__user_id FOREIGN KEY (user_id) REFERENCES users(id)` — with no `ON DELETE` clause, the default is `RESTRICT`. This means deleting a user who has filed flags will fail. Every other user-referencing FK in the schema uses `ON DELETE CASCADE` or `ON DELETE SET NULL`.
- **Impact**: User deletion will be blocked if the user has any flag records. This is a surprise constraint that produces a confusing foreign key constraint violation at runtime.
- **Solution**: Decide on the correct semantics: either `ON DELETE CASCADE` (delete flags with user) or `ON DELETE SET NULL` (preserve flags as anonymous). The new `user_deletion_events` system suggests the intent is to anonymize — so `SET NULL` with a nullable `user_id` is appropriate. A migration to change this constraint is needed.
- **Effort**: S

### 20. `TagAlias::to_aliased()` and `TagImplication::with_implied()` Called Per-Tag in Application Layer

- **Priority**: 🟢 Low
- **Problem**: During post saves and tag queries, `TagAlias::to_aliased()` and `TagImplication::with_implied()` are called with arrays of tags. `to_aliased()` issues one `SELECT` for the entire array (good). `with_implied()` issues up to `N_tags * 10` queries (bad — see Finding 8). `Tag::parse_query()` calls both in sequence, meaning every search request goes through this path.
- **Impact**: Tag-heavy search queries (10+ tags) incur significant DB round-trips.
- **Solution**: See Finding 8 for the recursive CTE approach. Additionally, warm the implication chain into the cache keyed on the tag set.
- **Effort**: M

### 21. Missing Index on `posts.user_id` for Uploader-Based Queries

- **Priority**: 🟢 Low
- **Problem**: `posts.user_id` has `KEY fk_posts__user_id (user_id)` in the baseline, which is correct. However, `generate_sql()` for `user:` searches adds a JOIN to `users` on `p.user_id = u.id` and then filters by `LOWER(u.name) = LOWER(?)`. The function wrapper on `u.name` prevents the `UNIQUE KEY name (name)` index on `users` from being used. The query does a full `users` table scan to find the user, then joins.
- **Impact**: Minor for small user tables; significant if the user table grows.
- **Solution**: Resolve the user name to `user_id` first (one indexed lookup), then filter `p.user_id = ?` directly. `User::find_by_name()` already does this and is used in the subscription path — apply the same pattern for `user:` searches.
- **Effort**: S

### 22. `histories` Table Has No Indexes Beyond the Primary Key

- **Priority**: 🟢 Low
- **Problem**: The baseline `histories` table has only a `PRIMARY KEY (id)`. Queries on `histories` are typically by `(group_by_table, group_by_id)` — e.g., "find all history records for post 1234". Migration `20260308130000` adds `ix_histories__group_created (group_by_table, group_by_id, created_at DESC)`. Without this migration, version history pages require a full table scan.
- **Impact**: History lookup performance degrades linearly with total history record count.
- **Solution**: Ensure the index migration has run. The composite index added is correct and sufficient.
- **Effort**: S (migration already written)

### 23. `user_blacklisted_tags.tags` — Entire Blacklist as a Single Text Blob

- **Priority**: 🟢 Low
- **Problem**: Each user's tag blacklist is stored as a newline-delimited text blob in a single row per user. The `User` model comment (line 63) acknowledges this as a TODO: "I don't see the advantage of normalizing these. Since commas are illegal characters in tags, they can be used to separate lines." There is no server-side parsing at the DB level; all matching is done in application code.
- **Impact**: Cannot efficiently query "how many users have blacklisted tag X" or enforce tag consistency when a tag is aliased. Blacklist lines are not indexed.
- **Solution**: For the current scale of a self-hosted imageboard, this is acceptable. If analytics over blacklists are ever needed, normalize to `user_id, tag_name` rows. Low priority unless that feature is planned.
- **Effort**: L

### 24. Data Migration Mixed with Schema Migration

- **Priority**: 🟢 Low
- **Problem**: Migration `20140112113211_insert_tag_sub_job_task.php` inserts a row into `job_tasks`. Migration `20260309000002_extend_user_records.php` performs `UPDATE user_records SET category = 'positive' WHERE is_positive = 1`. These are data migrations bundled with schema changes in the same migration file.
- **Impact**: If the migration fails partway through, the schema change may have been applied but the data change was not (or vice versa), leaving the database in an inconsistent state. Most other migrations lack `down()` methods, making rollback impossible.
- **Solution**: Separate schema-only and data-only migration files. Data migrations should be idempotent (check before update) and wrapped in transactions where possible.
- **Effort**: S (new migrations only)

---

## Index Recommendations

### Confirmed Present in Migrations (verify they have been applied)
- `posts(index_timestamp DESC, id DESC)` — `ix_posts__index_timestamp_id` (migration `20260308130000`) — critical for main page load
- `posts(status, created_at)` — `ix_posts__status_created_at` — moderation queue
- `posts(source)` — `ix_posts__source` — needs functional index on `LOWER(source)` to be effective
- `posts(score)` — `ix_posts__score`
- `histories(group_by_table, group_by_id, created_at)` — `ix_histories__group_created`
- `history_changes(table_name, remote_id)` — `ix_history_changes__table_remote`
- `favorites(user_id)` — `ix_favorites__user_id` — currently missing from baseline
- `comments(created_at)` — `ix_comments__created_at`
- `pools_posts(pool_id, sequence)` — `ix_pools_posts__pool_sequence`
- `dmails(to_id, has_seen, created_at)` — `ix_dmails__inbox`

### Still Missing / Not Yet Addressed
- `posts(rating, status)` — composite index for `rating:safe` + `status <> deleted` queries (very common filter combination)
- `posts(user_id, status)` — uploader profile page: "posts by user X that are active"
- `posts(parent_id)` — parent/children lookup; only `fk_posts__parent_id` single-column index exists (this is fine for FK lookup, but range queries on `parent_id IS NOT NULL` still need it, which is covered)
- `post_tag_histories(post_id, created_at DESC)` — pagination of tag history per post (only `fk_post_tag_histories__post_id` exists)
- Functional index on `LOWER(posts.source)` — required for source-search to use the index
- `users(name)` with `utf8mb4_unicode_ci` collation — current `UNIQUE KEY name (name)` requires `LOWER()` wrapping at query time for case-insensitive auth; switching collation would allow direct indexed lookup

### Redundant Indexes
- `posts_tags` had `fk_posts_tags__post_id` (single-column) alongside the `UNIQUE KEY (post_id, tag_id)`. Migration `20260308130002` removes this correctly — verify it ran.
- `tag_aliases` has both `UNIQUE KEY alias_unique (name, alias_id)` and `KEY name (name)`. The UNIQUE KEY covers prefix lookups on `name` alone; the standalone `KEY name` is redundant and can be dropped.

---

## Performance Optimizations

### Query-Level

1. **Tag exclusion subquery** (lines 286–293 of `SqlMethods.php`): The `NOT EXISTS (SELECT * FROM posts_tags pt INNER JOIN tags t ...)` subquery is correlated and executes once per candidate post row. For a large result set, this is significantly slower than a left-anti-join pattern: `LEFT JOIN posts_tags pt ON pt.post_id = p.id LEFT JOIN tags t ON t.id = pt.tag_id AND t.name IN (...) WHERE pt.post_id IS NULL`. The correlated `NOT EXISTS` is functionally correct but forces a nested-loop execution plan.

2. **`fast_count()` caches the result of a full `generate_sql()` count query**. The count query itself still goes through all the join machinery. For the common case of zero-tag queries, the cached `table_data.row_count` could be used (as the commented-out Ruby original intended), but only if the inconsistency in `table_data` (Finding 5) is resolved first.

3. **`Tag::calculate_related()`** (lines 457–489 of `Tag.php`) builds a Cartesian product of `posts_tags` aliases (`pt0`, `pt1`, `pt2`...) for multi-tag related-tag calculation. This is O(N^k) in join complexity where k is the number of input tags and N is posts_tags cardinality. It is already cached for 1 hour, but the initial population can be slow for large datasets.

4. **`Comment::update_last_commented_at()`** runs a `SELECT created_at FROM comments WHERE post_id = :post_id ORDER BY created_at DESC LIMIT 1` subquery on every comment save/delete. With an index on `comments(post_id, created_at DESC)` this would be fast — but no such composite index exists in the baseline. The existing `fk_comments__post_id` (single-column) index handles the filter but not the sort.

5. **`PostTagHistory::save_post_history()`** queries `$this->tag_history[0]` (which loads the association) to check whether the last entry matches the current tags. If a full ActiveRecord load of the association is triggered here, it fetches all tag history records for the post rather than just the most recent one. An indexed `SELECT ... ORDER BY id DESC LIMIT 1` raw query would be more efficient.

---

## Migration Structure and Quality

### Positive Observations
- All 2026-era migrations are idempotent: they check `tableExists()`, `columnExists()`, and `indexExists()` before applying changes. This is correct defensive practice.
- The utf8mb4 migration (`20260308130001`) does pre-conversion duplicate checks, handles FK drop/re-add in a defined order, and documents the maintenance window requirement.
- `20260308130000_add_missing_indexes.php` has a `addUniqueIndexSafe()` helper that downgrades to a non-unique index if duplicates exist, preventing migration failure.
- Naming convention for migration files is consistent: `YYYYMMDDHHMMSS_description_in_snake_case.php`.

### Problem Areas
- **No `down()` methods** on 36 of 38 migrations (Finding 13).
- **Timestamp gaps**: 2013–2016 migrations use real timestamps; 2026 migrations use artificial timestamps clustered around specific dates (20260308130000, 20260308130001, 20260308130002 are all the same date with sequential suffixes). The last three digits are being used as a sub-sequence number (e.g., `20260309000001` through `20260309000012`). This is an unusual but functional workaround for the lack of a migration generator.
- **No `down()` on data migration** `20140112113211_insert_tag_sub_job_task.php`: the seeded `job_task` row cannot be rolled back.
- **`20260216170500_ensure_advertisement_columns.php`** duplicates logic already present in `20131201031201_add_html_to_advertisements.php` and `20131205153422_add_position_to_ads.php`. This is safe but shows schema drift causing defensive re-runs.
- **Multiple user_logs creation migrations** (`20140126192028_create_user_log.php` and `20260218221000_ensure_user_logs_table_exists.php`) both attempt to create the same table with idempotency guards. The duplication is harmless but adds noise.
- **`20260216165000_ensure_inline_tables_exist.php`** duplicates `20131231072227_create_inline_images.php`. Same issue as above.

---

## Database Score: 5.5/10

### Justification

**Strengths (+)**
- Foreign key constraints are declared on almost all relationships with appropriate cascade behaviour (CASCADE or SET NULL).
- InnoDB is used throughout, enabling row-level locking and FK enforcement.
- The `posts_tags` pivot table has a proper composite UNIQUE KEY.
- `post_replacements` has well-designed composite indexes covering its main query patterns.
- Triggers maintain `tag.post_count` and `pools.post_count` counters atomically.
- The 2026 migration wave significantly improved the index coverage, UTF-8 hygiene, and removed the duplicate FK on `tag_implications`.
- New tables (mod_actions, exception_logs, tickets, etc.) are well-structured with appropriate indexes inline.
- Security direction is correct: bcrypt migration, hashed API keys in dedicated table, passkeys support.

**Weaknesses (-)**
- `schema.sql` is severely out of date — 15 tables are missing. This makes it unusable as a source of truth for fresh installs.
- `change_seq` is referenced in search/API code but the column does not exist, causing runtime errors for those query types.
- No rollback capability on 36/38 migrations.
- UTF-8 is still the baseline character set; utf8mb4 migration must be run manually on each environment.
- The `generate_sql()` search engine accumulates technical debt: non-sargable `LOWER(source) LIKE`, redundant `GROUP BY`, `ORDER BY RAND()`, correlated `NOT EXISTS` exclusion subquery.
- Tag implication resolution uses up to 200 sequential round-trips; a recursive CTE would reduce this to one query.
- Dual API key systems (plaintext in `users.api_key` and hashed in `api_keys` table) coexist with no active migration path.
- SHA1 password hashes remain in the database for inactive users.
- Counter inconsistency: `table_data` counter definitions do not agree across the three code paths that maintain it.
- The schema baseline will produce a broken database for anyone installing from it alone.

---

## Key File References

- Schema baseline: `D:/repos/myimouto/myimouto/db/schema.sql`
- Post search engine: `D:/repos/myimouto/myimouto/app/models/Post/SqlMethods.php`
- Tag parsing and query logic: `D:/repos/myimouto/myimouto/app/models/Tag.php`
- Tag implication resolver: `D:/repos/myimouto/myimouto/app/models/TagImplication.php`
- Tag commit (full-replace pattern): `D:/repos/myimouto/myimouto/app/models/Post/TagMethods.php` (lines 319–368)
- Counter inconsistency: `D:/repos/myimouto/myimouto/app/models/Post/CountMethods.php`
- Index additions migration: `D:/repos/myimouto/myimouto/db/migrate/20260308130000_add_missing_indexes.php`
- UTF-8 conversion: `D:/repos/myimouto/myimouto/db/migrate/20260308130001_convert_utf8mb4.php`
- Auth hardening: `D:/repos/myimouto/myimouto/db/migrate/20260308120000_add_auth_hardening_columns.php`
- Duplicate FK cleanup: `D:/repos/myimouto/myimouto/db/migrate/20260308130002_cleanup_fk_and_ip_fields.php`
