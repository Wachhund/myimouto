<?php
class EnsureUserLogsTableExists extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('user_logs')) {
            $this->execute(<<<SQL
                CREATE TABLE `user_logs` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `user_id` int(11) NOT NULL,
                  `ip_addr` varchar(46) NOT NULL,
                  `created_at` datetime NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `created_at` (`created_at`),
                  KEY `user_id` (`user_id`)
                ) ENGINE=InnoDB
SQL
            );
        }

        if (!$this->foreignKeyExists('user_logs', 'fk_user_logs__user_id')) {
            $this->execute(
                "ALTER TABLE `user_logs` ADD CONSTRAINT `fk_user_logs__user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE"
            );
        }
    }

    private function tableExists($tableName)
    {
        $stmt = $this->connection->executeSql(
            "SELECT COUNT(*) AS count_value FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            $tableName
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row && (int)$row['count_value'] > 0;
    }

    private function foreignKeyExists($tableName, $constraintName)
    {
        $stmt = $this->connection->executeSql(
            "SELECT COUNT(*) AS count_value FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            $tableName,
            $constraintName
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row && (int)$row['count_value'] > 0;
    }
}
