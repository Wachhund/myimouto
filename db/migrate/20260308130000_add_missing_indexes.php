<?php
class AddMissingIndexes extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        // AC-1: posts(index_timestamp DESC, id DESC) — main page sorting
        $this->addIndexIfNotExists('posts', 'ix_posts__index_timestamp_id',
            '`index_timestamp` DESC, `id` DESC');

        // AC-2: posts(status, created_at) — moderation queue filtering
        $this->addIndexIfNotExists('posts', 'ix_posts__status_created_at',
            '`status`, `created_at`');

        // AC-3: posts(source) — source search
        $this->addIndexIfNotExists('posts', 'ix_posts__source', '`source`');

        // AC-4: posts(score) — score sorting
        $this->addIndexIfNotExists('posts', 'ix_posts__score', '`score`');

        // AC-5: posts(width), posts(height) — dimension range queries
        $this->addIndexIfNotExists('posts', 'ix_posts__width', '`width`');
        $this->addIndexIfNotExists('posts', 'ix_posts__height', '`height`');

        // AC-6: histories(group_by_table, group_by_id, created_at DESC) — version lookups
        $this->addIndexIfNotExists('histories', 'ix_histories__group_created',
            '`group_by_table`, `group_by_id`, `created_at` DESC');

        // AC-7: history_changes(table_name, remote_id) — entity history lookups
        $this->addIndexIfNotExists('history_changes', 'ix_history_changes__table_remote',
            '`table_name`, `remote_id`');

        // AC-8: favorites(user_id) — "my favorites" queries
        $this->addIndexIfNotExists('favorites', 'ix_favorites__user_id', '`user_id`');

        // AC-9: post_tag_histories(user_id) — history by user
        $this->addIndexIfNotExists('post_tag_histories', 'ix_post_tag_histories__user_id',
            '`user_id`');

        // AC-10: comments(created_at) — chronological pagination
        $this->addIndexIfNotExists('comments', 'ix_comments__created_at', '`created_at`');

        // AC-11: pools_posts(pool_id, sequence) — pool display order
        $this->addIndexIfNotExists('pools_posts', 'ix_pools_posts__pool_sequence',
            '`pool_id`, `sequence`');

        // AC-12: dmails(to_id, has_seen, created_at DESC) — inbox queries
        $this->addIndexIfNotExists('dmails', 'ix_dmails__inbox',
            '`to_id`, `has_seen`, `created_at` DESC');

        // AC-13: artists(name) UNIQUE — duplicate protection
        $this->addUniqueIndexSafe('artists', 'name', 'uk_artists__name');

        // AC-14: wiki_pages(title) UNIQUE — duplicate protection
        $this->addUniqueIndexSafe('wiki_pages', 'title', 'uk_wiki_pages__title');

        // AC-15: ip_bans(ip_addr) — ban lookup
        $this->addIndexIfNotExists('ip_bans', 'ix_ip_bans__ip_addr', '`ip_addr`');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $stmt = $this->connection->executeSql(
            "SELECT COUNT(*) AS cnt FROM information_schema.STATISTICS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
            $table,
            $indexName
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row && (int)$row['cnt'] > 0;
    }

    private function addIndexIfNotExists(string $table, string $name, string $columns): void
    {
        if (!$this->indexExists($table, $name)) {
            $this->execute("ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$columns})");
        }
    }

    private function addUniqueIndexSafe(string $table, string $column, string $name): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        // Check for duplicates before creating UNIQUE index
        $stmt = $this->connection->executeSql(
            "SELECT COUNT(*) AS cnt FROM ("
            . "SELECT `{$column}` FROM `{$table}` GROUP BY `{$column}` HAVING COUNT(*) > 1"
            . ") AS dupes"
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $hasDuplicates = $row && (int)$row['cnt'] > 0;

        if ($hasDuplicates) {
            print("WARNING: Duplicates found in `{$table}`.`{$column}`. Creating non-unique index instead.\n");
            $this->execute("ALTER TABLE `{$table}` ADD INDEX `{$name}` (`{$column}`)");
            return;
        }

        $this->execute("ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$name}` (`{$column}`)");
    }
}
