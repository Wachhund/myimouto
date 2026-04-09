<?php
class AddTosAcceptance extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        // Add ToS version tracking to users table
        if (!$this->columnExists('users', 'tos_accepted_version')) {
            $this->execute("ALTER TABLE `users` ADD COLUMN `tos_accepted_version` INT DEFAULT NULL");
            $this->execute("ALTER TABLE `users` ADD COLUMN `tos_accepted_at` DATETIME DEFAULT NULL");
        }
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
}
