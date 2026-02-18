<?php
class EnsureLegacyIpAddrColumnsExist extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        $this->ensureNullableVarcharColumn('dmails', 'ip_addr', 46);
        $this->ensureNullableVarcharColumn('forum_posts', 'ip_addr', 46);
        $this->ensureNullableVarcharColumn('forum_posts', 'updater_ip_addr', 46);
        $this->ensureNullableVarcharColumn('comments', 'updater_ip_addr', 46);
    }

    private function ensureNullableVarcharColumn($tableName, $columnName, $limit)
    {
        if (!$this->dbTableExists($tableName) || $this->columnExists($tableName, $columnName)) {
            return;
        }

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
}
