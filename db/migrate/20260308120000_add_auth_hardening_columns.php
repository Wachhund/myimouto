<?php
class AddAuthHardeningColumns extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->columnExists('users', 'bcrypt_password_hash')) {
            $this->execute("ALTER TABLE `users` ADD COLUMN `bcrypt_password_hash` VARCHAR(255) DEFAULT NULL AFTER `password_hash`");
        }

        if (!$this->columnExists('users', 'remember_token')) {
            $this->execute("ALTER TABLE `users` ADD COLUMN `remember_token` VARCHAR(64) DEFAULT NULL AFTER `bcrypt_password_hash`");
        }

        if (!$this->columnExists('users', 'reset_token')) {
            $this->execute("ALTER TABLE `users` ADD COLUMN `reset_token` VARCHAR(64) DEFAULT NULL AFTER `remember_token`");
        }

        if (!$this->columnExists('users', 'reset_token_expires_at')) {
            $this->execute("ALTER TABLE `users` ADD COLUMN `reset_token_expires_at` DATETIME DEFAULT NULL AFTER `reset_token`");
        }

        if (!$this->columnExists('users', 'failed_login_count')) {
            $this->execute("ALTER TABLE `users` ADD COLUMN `failed_login_count` INT DEFAULT 0 AFTER `reset_token_expires_at`");
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
}
