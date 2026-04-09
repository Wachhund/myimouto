<?php
class CleanupFkAndIpFields extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        // AC-18: Remove duplicate FK fk_consequent_id on tag_implications
        // (May already be absent if Migration 2 ran first — safe no-op)
        $this->dropFkIfExists('tag_implications', 'fk_consequent_id');

        // AC-19: Remove redundant index fk_posts_tags__post_id on posts_tags
        // Already covered by UNIQUE KEY (post_id, tag_id)
        $this->removeRedundantPostsTagsIndex();

        // AC-20: Normalize IP fields to VARCHAR(46) for IPv6 support
        // IPv4-mapped-IPv6 = max 45 chars (e.g. ::ffff:192.168.1.1) + 1 reserve
        $this->widenIpField('batch_uploads', 'ip');
        $this->widenIpField('comments', 'ip_addr');
        $this->widenIpField('ip_bans', 'ip_addr');
        $this->widenIpField('post_tag_histories', 'ip_addr');
        $this->widenIpField('wiki_page_versions', 'ip_addr');
        $this->widenIpField('wiki_pages', 'ip_addr');
        // May exist from earlier migrations — safe no-op if column absent
        $this->widenIpField('dmails', 'ip_addr');
        $this->widenIpField('forum_posts', 'ip_addr');
        $this->widenIpField('comments', 'updater_ip_addr');
    }

    private function removeRedundantPostsTagsIndex(): void
    {
        if (!$this->indexExists('posts_tags', 'fk_posts_tags__post_id')) {
            return;
        }

        // Must drop the FK constraint first (it may reference this index)
        $this->dropFkIfExists('posts_tags', 'fk_posts_tags__post_id');

        // Drop the redundant single-column index
        $this->execute("ALTER TABLE `posts_tags` DROP INDEX `fk_posts_tags__post_id`");

        // Recreate the FK — MySQL will use UNIQUE KEY (post_id, tag_id) as backing index
        if (!$this->fkExists('posts_tags', 'fk_posts_tags__post_id')) {
            $this->execute(
                "ALTER TABLE `posts_tags` ADD CONSTRAINT `fk_posts_tags__post_id` "
                . "FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE"
            );
        }
    }

    private function widenIpField(string $table, string $column): void
    {
        $stmt = $this->connection->executeSql(
            "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            $table,
            $column
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Skip if column doesn't exist or already wide enough
        if (!$row || (int)$row['CHARACTER_MAXIMUM_LENGTH'] >= 46) {
            return;
        }

        $this->execute("ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR(46) NOT NULL");
    }

    private function dropFkIfExists(string $table, string $name): void
    {
        if ($this->fkExists($table, $name)) {
            $this->execute("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
        }
    }

    private function fkExists(string $table, string $name): bool
    {
        $stmt = $this->connection->executeSql(
            "SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS "
            . "WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? "
            . "AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            $table,
            $name
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row && (int)$row['cnt'] > 0;
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
}
