<?php
class AddPoolSearchIndexes extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        // Ensure pools_posts has a composite unique index on (pool_id, post_id)
        // to prevent duplicate entries and speed up lookups.
        $this->addIndexIfNotExists('pools_posts', 'ix_pools_posts__pool_post',
            '`pool_id`, `post_id`', true);

        // Reverse lookup index: find all pools a given post belongs to.
        $this->addIndexIfNotExists('pools_posts', 'ix_pools_posts__post_pool',
            '`post_id`, `pool_id`', false);
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

    private function addIndexIfNotExists(string $table, string $name, string $columns, bool $unique): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        $type = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $this->execute("ALTER TABLE `{$table}` ADD {$type} `{$name}` ({$columns})");
    }
}
