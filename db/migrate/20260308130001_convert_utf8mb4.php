<?php
class ConvertUtf8mb4 extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        print("=== UTF8mb4 Migration ===\n");
        print("WARNING: Ensure a database backup exists before running this migration.\n");
        print("For databases with >1M posts, plan for a maintenance window.\n\n");

        print("Phase 1: Pre-conversion checks...\n");
        $this->checkCollationDuplicates();

        print("Phase 2: Dropping foreign key constraints...\n");
        $this->dropAllForeignKeys();

        print("Phase 3: Converting table character sets to utf8mb4...\n");
        $this->convertAllTables();

        print("Phase 4: Recreating foreign key constraints...\n");
        $this->recreateAllForeignKeys();

        print("=== UTF8mb4 Migration complete ===\n");
    }

    /**
     * Check for values that are unique under utf8_bin but would collide
     * under utf8mb4_unicode_ci (case-insensitive). Fails early with a
     * clear message instead of a cryptic MySQL duplicate-key error.
     */
    private function checkCollationDuplicates(): void
    {
        // tags.name uses utf8_bin — UNIQUE KEY would fail if case-different duplicates exist
        $this->assertNoCiDuplicates('tags', 'name');
    }

    private function assertNoCiDuplicates(string $table, string $column): void
    {
        if (!$this->tableExists($table)) {
            return;
        }

        $stmt = $this->connection->executeSql(
            "SELECT `{$column}`, COUNT(*) AS cnt "
            . "FROM `{$table}` GROUP BY LOWER(`{$column}`) HAVING cnt > 1"
        );
        $dupes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($dupes)) {
            $examples = array_slice(array_column($dupes, $column), 0, 5);
            throw new \RuntimeException(
                "Cannot convert `{$table}` to utf8mb4_unicode_ci: "
                . count($dupes) . " case-insensitive duplicate(s) in `{$column}` "
                . "(e.g. " . implode(', ', $examples) . "). "
                . "Resolve duplicates first: SELECT LOWER(`{$column}`), COUNT(*) AS cnt "
                . "FROM `{$table}` GROUP BY LOWER(`{$column}`) HAVING cnt > 1"
            );
        }
    }

    private function dropAllForeignKeys(): void
    {
        $this->dropFk('artists',              'fk_artists__updater_id');
        $this->dropFk('artists_urls',         'fk_artists_urls__artist_id');
        $this->dropFk('bans',                 'fk_bans__banned_by');
        $this->dropFk('bans',                 'fk_bans__user_id');
        $this->dropFk('batch_uploads',        'fk_batch_uploads__user_id');
        $this->dropFk('comments',             'fk_comments__post_id');
        $this->dropFk('comments',             'fk_comments__user_id');
        $this->dropFk('dmails',               'fk_dmails__from_id');
        $this->dropFk('dmails',               'fk_dmails__parent_id');
        $this->dropFk('dmails',               'fk_dmails__to_id');
        $this->dropFk('flagged_post_details', 'fk_flag_post_details__user_id');
        $this->dropFk('flagged_post_details', 'fk_flag_post_det__post_id');
        $this->dropFk('forum_posts',          'fk_forum_posts__creator_id');
        $this->dropFk('forum_posts',          'fk_forum_posts__last_updated_by');
        $this->dropFk('forum_posts',          'fk_forum_posts__parent_id');
        $this->dropFk('history_changes',      'fk_history_changes__history_id');
        $this->dropFk('ip_bans',              'fk_ip_bans__banned_by');
        $this->dropFk('note_versions',        'fk_note_versions__note_id');
        $this->dropFk('note_versions',        'fk_note_versions__post_id');
        $this->dropFk('notes',                'fk_notes__post_id');
        $this->dropFk('pools_posts',          'fk_pools_posts__next_post_id');
        $this->dropFk('pools_posts',          'fk_pools_posts__pool_id');
        $this->dropFk('pools_posts',          'fk_pools_posts__post_id');
        $this->dropFk('pools_posts',          'fk_pools_posts__prev_post_id');
        $this->dropFk('post_sets',            'fk_post_sets__creator_id');
        $this->dropFk('post_set_posts',       'fk_post_set_posts__post_set_id');
        $this->dropFk('post_set_posts',       'fk_post_set_posts__post_id');
        $this->dropFk('post_set_maintainers', 'fk_post_set_maintainers__post_set_id');
        $this->dropFk('post_set_maintainers', 'fk_post_set_maintainers__user_id');
        $this->dropFk('post_replacements',    'fk_post_replacements__creator_id');
        $this->dropFk('post_replacements',    'fk_post_replacements__post_id');
        $this->dropFk('post_replacements',    'fk_post_replacements__reviewed_by_id');
        $this->dropFk('post_tag_histories',   'fk_post_tag_histories__post_id');
        $this->dropFk('post_votes',           'fk_post_id__posts_id');
        $this->dropFk('post_votes',           'fk_user_id__users_id');
        $this->dropFk('posts',                'fk_parent_id__posts_id');
        $this->dropFk('posts',                'fk_posts__user_id');
        $this->dropFk('posts',                'posts__approver_id');
        $this->dropFk('posts_tags',           'fk_posts_tags__post_id');
        $this->dropFk('posts_tags',           'fk_posts_tags__tag_id');
        $this->dropFk('tag_aliases',          'fk_tag_aliases__alias_id');
        $this->dropFk('tag_aliases',          'fk_tag_aliases__creator_id');
        // AC-18: Drop BOTH constraints on tag_implications.consequent_id
        // Only the canonical fk_tag_implications__consequent_id will be recreated
        $this->dropFk('tag_implications',     'fk_consequent_id');
        $this->dropFk('tag_implications',     'fk_tag_implications__consequent_id');
        $this->dropFk('tag_implications',     'fk_tag_implications__creator_id');
        $this->dropFk('tag_implications',     'fk_tag_implications__predicate_id');
        $this->dropFk('user_blacklisted_tags', 'fk_user_bl_tags__user_id');
        $this->dropFk('user_logs',            'fk_user_logs__user_id');
        $this->dropFk('user_records',         'fk_user_records__reported_by');
        $this->dropFk('user_records',         'fk_user_records__user_id');
        $this->dropFk('users',                'fk_users__avatar_post_id');
        $this->dropFk('wiki_page_versions',   'fk_wiki_page_versions__wiki_page');
        $this->dropFk('wiki_pages',           'fk_wiki_pages__user_id');
    }

    private function convertAllTables(): void
    {
        // Ordered small → large for faster progress on small tables
        $tables = [
            // Small/config tables
            'schema_migrations', 'table_data', 'job_tasks', 'ip_bans', 'bans',
            // Reference tables
            'artists', 'artists_urls', 'tags', 'tag_aliases', 'tag_implications',
            'tag_subscriptions', 'user_blacklisted_tags', 'pools',
            'wiki_pages', 'wiki_page_versions',
            // User/content tables
            'users', 'user_logs', 'user_records', 'dmails', 'forum_posts',
            'notes', 'note_versions', 'favorites', 'flagged_post_details',
            'comments', 'batch_uploads', 'post_votes', 'post_tag_histories',
            'histories', 'history_changes', 'pools_posts',
            'post_sets', 'post_set_posts', 'post_set_maintainers', 'post_replacements',
            // Large tables last
            'posts', 'posts_tags',
        ];

        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                print("  Converting `{$table}`...\n");
                $this->execute(
                    "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );
            }
        }

        // Optional tables (may not exist on all installations)
        foreach (['advertisements', 'inlines', 'inline_images'] as $table) {
            if ($this->tableExists($table)) {
                print("  Converting `{$table}`...\n");
                $this->execute(
                    "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );
            }
        }
    }

    private function recreateAllForeignKeys(): void
    {
        // Naming convention: fk_{table}__{column}
        // Non-standard legacy names are normalized during recreation
        $this->addFk('artists',              'updater_id',      'users',       'id', 'SET NULL');
        $this->addFk('artists_urls',         'artist_id',       'artists',     'id', 'CASCADE');
        $this->addFk('bans',                 'banned_by',       'users',       'id', 'CASCADE');
        $this->addFk('bans',                 'user_id',         'users',       'id', 'CASCADE');
        $this->addFk('batch_uploads',        'user_id',         'users',       'id', 'CASCADE');
        $this->addFk('comments',             'post_id',         'posts',       'id', 'CASCADE');
        $this->addFk('comments',             'user_id',         'users',       'id', 'CASCADE');
        $this->addFk('dmails',               'from_id',         'users',       'id', 'CASCADE');
        $this->addFk('dmails',               'parent_id',       'dmails',      'id', 'CASCADE');
        $this->addFk('dmails',               'to_id',           'users',       'id', 'CASCADE');
        $this->addFk('flagged_post_details', 'user_id',         'users',       'id');
        $this->addFk('flagged_post_details', 'post_id',         'posts',       'id', 'CASCADE');
        $this->addFk('forum_posts',          'creator_id',      'users',       'id', 'CASCADE');
        $this->addFk('forum_posts',          'last_updated_by', 'users',       'id', 'SET NULL');
        $this->addFk('forum_posts',          'parent_id',       'forum_posts', 'id', 'CASCADE');
        $this->addFk('history_changes',      'history_id',      'histories',   'id', 'CASCADE');
        $this->addFk('ip_bans',              'banned_by',       'users',       'id', 'CASCADE');
        $this->addFk('note_versions',        'note_id',         'notes',       'id', 'CASCADE');
        $this->addFk('note_versions',        'post_id',         'posts',       'id', 'CASCADE');
        $this->addFk('notes',                'post_id',         'posts',       'id', 'CASCADE');
        $this->addFk('pools_posts',          'next_post_id',    'posts',       'id', 'SET NULL');
        $this->addFk('pools_posts',          'pool_id',         'pools',       'id', 'CASCADE');
        $this->addFk('pools_posts',          'post_id',         'posts',       'id', 'CASCADE');
        $this->addFk('pools_posts',          'prev_post_id',    'posts',       'id', 'SET NULL');
        $this->addFk('post_sets',            'creator_id',      'users',       'id', 'RESTRICT');
        $this->addFk('post_set_posts',       'post_set_id',     'post_sets',   'id', 'CASCADE');
        $this->addFk('post_set_posts',       'post_id',         'posts',       'id', 'CASCADE');
        $this->addFk('post_set_maintainers', 'post_set_id',     'post_sets',   'id', 'CASCADE');
        $this->addFk('post_set_maintainers', 'user_id',         'users',       'id', 'CASCADE');
        $this->addFk('post_replacements',    'creator_id',      'users',       'id', 'CASCADE');
        $this->addFk('post_replacements',    'post_id',         'posts',       'id', 'CASCADE');
        $this->addFk('post_replacements',    'reviewed_by_id',  'users',       'id', 'SET NULL');
        $this->addFk('post_tag_histories',   'post_id',         'posts',       'id', 'CASCADE');
        $this->addFk('post_votes',           'post_id',         'posts',       'id', 'CASCADE');
        $this->addFk('post_votes',           'user_id',         'users',       'id', 'CASCADE');
        $this->addFk('posts',                'parent_id',       'posts',       'id', 'SET NULL');
        $this->addFk('posts',                'user_id',         'users',       'id', 'SET NULL');
        $this->addFk('posts',                'approver_id',     'users',       'id', 'SET NULL');
        $this->addFk('posts_tags',           'post_id',         'posts',       'id', 'CASCADE');
        $this->addFk('posts_tags',           'tag_id',          'tags',        'id', 'CASCADE');
        $this->addFk('tag_aliases',          'alias_id',        'tags',        'id', 'CASCADE');
        $this->addFk('tag_aliases',          'creator_id',      'users',       'id', 'CASCADE');
        // tag_implications: only canonical FKs — duplicate fk_consequent_id is NOT recreated (AC-18)
        $this->addFk('tag_implications',     'predicate_id',    'tags',        'id', 'CASCADE');
        $this->addFk('tag_implications',     'consequent_id',   'tags',        'id', 'CASCADE');
        $this->addFk('tag_implications',     'creator_id',      'users',       'id', 'CASCADE');
        $this->addFk('user_blacklisted_tags', 'user_id',        'users',       'id', 'CASCADE');
        $this->addFk('user_logs',            'user_id',         'users',       'id', 'CASCADE');
        $this->addFk('user_records',         'user_id',         'users',       'id', 'CASCADE');
        $this->addFk('user_records',         'reported_by',     'users',       'id', 'CASCADE');
        $this->addFk('users',                'avatar_post_id',  'posts',       'id', 'SET NULL');
        $this->addFk('wiki_page_versions',   'wiki_page_id',    'wiki_pages',  'id', 'CASCADE');
        $this->addFk('wiki_pages',           'user_id',         'users',       'id', 'SET NULL');

        // Optional tables
        if ($this->tableExists('inlines')) {
            $this->addFk('inlines', 'user_id', 'users', 'id');
        }
        if ($this->tableExists('inline_images')) {
            $this->addFk('inline_images', 'inline_id', 'inlines', 'id', 'CASCADE');
        }
    }

    // --- Helper methods ---

    private function dropFk(string $table, string $name): void
    {
        if (!$this->tableExists($table)) {
            return;
        }
        $stmt = $this->connection->executeSql(
            "SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS "
            . "WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? "
            . "AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            $table,
            $name
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row && (int)$row['cnt'] > 0) {
            $this->execute("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
        }
    }

    private function addFk(
        string $table,
        string $column,
        string $refTable,
        string $refColumn,
        ?string $onDelete = null
    ): void {
        $name = 'fk_' . $table . '__' . $column;

        $stmt = $this->connection->executeSql(
            "SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS "
            . "WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?",
            $table,
            $name
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row && (int)$row['cnt'] > 0) {
            return;
        }

        $sql = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` "
            . "FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`{$refColumn}`)";
        if ($onDelete) {
            $sql .= " ON DELETE {$onDelete}";
        }
        $this->execute($sql);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->connection->executeSql(
            "SELECT COUNT(*) AS cnt FROM information_schema.TABLES "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            $table
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row && (int)$row['cnt'] > 0;
    }
}
