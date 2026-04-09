<?php
class ExtendUserRecords extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('user_records')) {
            return;
        }

        // Add category ENUM column
        if (!$this->columnExists('user_records', 'category')) {
            $this->execute("ALTER TABLE `user_records` ADD COLUMN `category` ENUM('positive','negative','neutral') NOT NULL DEFAULT 'neutral' AFTER `is_positive`");
        }

        // Migrate data from is_positive to category
        if ($this->columnExists('user_records', 'is_positive')) {
            $this->execute("UPDATE `user_records` SET `category` = 'positive' WHERE `is_positive` = 1");
            $this->execute("UPDATE `user_records` SET `category` = 'negative' WHERE `is_positive` = 0");

            // Drop is_positive column
            $this->execute("ALTER TABLE `user_records` DROP COLUMN `is_positive`");
        }

        // Add soft-delete columns
        if (!$this->columnExists('user_records', 'is_deleted')) {
            $this->execute("ALTER TABLE `user_records` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0");
        }

        if (!$this->columnExists('user_records', 'deleted_by')) {
            $this->execute("ALTER TABLE `user_records` ADD COLUMN `deleted_by` INT DEFAULT NULL");
        }

        if (!$this->columnExists('user_records', 'updated_at')) {
            $this->execute("ALTER TABLE `user_records` ADD COLUMN `updated_at` DATETIME DEFAULT NULL");
        }

        // Add indexes
        if (!$this->indexExists('user_records', 'idx_user_records_user_cat_created')) {
            $this->execute("ALTER TABLE `user_records` ADD INDEX `idx_user_records_user_cat_created` (`user_id`, `category`, `created_at`)");
        }

        if (!$this->indexExists('user_records', 'idx_user_records_active')) {
            $this->execute("ALTER TABLE `user_records` ADD INDEX `idx_user_records_active` (`user_id`, `is_deleted`)");
        }
    }

    private function columnExists($table, $column)
    {
        $stmt = $this->connection->executeSql(
            "SELECT COUNT(*) AS count_value FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            $table,
            $column
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row && (int)$row['count_value'] > 0;
    }

    private function indexExists($table, $indexName)
    {
        $stmt = $this->connection->executeSql(
            "SELECT COUNT(*) AS cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
            $table,
            $indexName
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row && (int)$row['cnt'] > 0;
    }
}
