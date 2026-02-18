<?php
class EnsureLegacyIpAddrColumnsExist extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        $targets = [
            ['table' => 'dmails', 'column' => 'ip_addr', 'limit' => 46],
            ['table' => 'forum_posts', 'column' => 'ip_addr', 'limit' => 46],
            ['table' => 'forum_posts', 'column' => 'updater_ip_addr', 'limit' => 46],
            ['table' => 'comments', 'column' => 'updater_ip_addr', 'limit' => 46],
        ];

        foreach ($targets as $target) {
            $this->ensureNullableVarcharColumn($target['table'], $target['column'], $target['limit']);
        }
    }

    private function ensureNullableVarcharColumn($tableName, $columnName, $limit)
    {
        if (!$this->dbTableExists($tableName) || $this->columnExists($tableName, $columnName)) {
            return;
        }

        $tableName = $this->quoteIdentifier($tableName);
        $columnName = $this->quoteIdentifier($columnName);

        $sql = sprintf(
            "ALTER TABLE `%s` ADD `%s` VARCHAR(%d) NULL",
            $tableName,
            $columnName,
            (int)$limit
        );

        $this->execute($sql);
    }

    private function dbTableExists($tableName)
    {
        $stmt = $this->connection->executeSql(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            $tableName
        );

        return (int)$stmt->fetchColumn() > 0;
    }

    private function columnExists($tableName, $columnName)
    {
        $stmt = $this->connection->executeSql(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            $tableName,
            $columnName
        );
        $count = (int)$stmt->fetchColumn();

        return $count > 0;
    }

    private function quoteIdentifier($identifier)
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('Invalid SQL identifier: ' . $identifier);
        }

        return str_replace('`', '``', $identifier);
    }
}
