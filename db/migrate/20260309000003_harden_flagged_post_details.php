<?php
class HardenFlaggedPostDetails extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        // Add PK - MySQL auto-assigns IDs for existing rows
        if (!$this->columnExists('flagged_post_details', 'id')) {
            $this->execute("ALTER TABLE `flagged_post_details` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
        }

        // Add new columns
        if (!$this->columnExists('flagged_post_details', 'reason_category')) {
            $this->execute("ALTER TABLE `flagged_post_details` ADD COLUMN `reason_category` VARCHAR(50) DEFAULT NULL");
        }
        if (!$this->columnExists('flagged_post_details', 'parent_post_id')) {
            $this->execute("ALTER TABLE `flagged_post_details` ADD COLUMN `parent_post_id` INT DEFAULT NULL");
        }
        if (!$this->columnExists('flagged_post_details', 'resolved_by')) {
            $this->execute("ALTER TABLE `flagged_post_details` ADD COLUMN `resolved_by` INT DEFAULT NULL");
        }

        // Add indexes
        $this->addIndexIfNotExists('flagged_post_details', 'idx_fpd_post_resolved_created', '`post_id`, `is_resolved`, `created_at`');
        $this->addIndexIfNotExists('flagged_post_details', 'idx_fpd_user_created', '`user_id`, `created_at`');
        $this->addIndexIfNotExists('flagged_post_details', 'idx_fpd_reason_cat', '`reason_category`, `created_at`');
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->connection->executeSql(
            "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            $table, $column
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row && (int)$row['cnt'] > 0;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $stmt = $this->connection->executeSql(
            "SELECT COUNT(*) AS cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
            $table, $indexName
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
}
