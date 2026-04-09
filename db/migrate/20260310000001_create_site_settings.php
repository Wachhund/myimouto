<?php
class CreateSiteSettings extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('site_settings')) {
            $this->execute("
                CREATE TABLE `site_settings` (
                    `key_name` VARCHAR(100) NOT NULL,
                    `value` TEXT NOT NULL,
                    `updated_at` DATETIME NOT NULL,
                    PRIMARY KEY (`key_name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `site_settings`");
    }

    private function tableExists($table)
    {
        $result = $this->execute(
            "SELECT COUNT(*) AS cnt FROM information_schema.TABLES " .
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            $table
        );
        $row = $result->fetch();
        return $row && (int)($row['cnt'] ?? $row[0] ?? 0) > 0;
    }
}
