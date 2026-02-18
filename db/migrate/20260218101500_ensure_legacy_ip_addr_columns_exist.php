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
        if (!$this->tableExists($tableName)) {
            return;
        }

        $metadata = $this->fetchColumnMetadata($tableName, $columnName);
        if (!$metadata) {
            $this->execute(
                sprintf(
                    "ALTER TABLE `%s` ADD `%s` VARCHAR(%d) NULL",
                    $this->quoteIdentifier($tableName),
                    $this->quoteIdentifier($columnName),
                    (int)$limit
                )
            );
            return;
        }

        if ($this->columnMatchesExpected($metadata, $limit)) {
            return;
        }

        $this->execute(
            sprintf(
                "ALTER TABLE `%s` MODIFY `%s` VARCHAR(%d) NULL",
                $this->quoteIdentifier($tableName),
                $this->quoteIdentifier($columnName),
                (int)$limit
            )
        );
    }

    private function fetchColumnMetadata($tableName, $columnName)
    {
        $stmt = $this->connection->executeSql(
            "SELECT COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1",
            $tableName,
            $columnName
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function columnMatchesExpected(array $metadata, $limit)
    {
        $columnType = strtolower((string)$metadata['COLUMN_TYPE']);
        $length = (int)$metadata['CHARACTER_MAXIMUM_LENGTH'];
        $isNullable = strtoupper((string)$metadata['IS_NULLABLE']) === 'YES';

        return strpos($columnType, 'varchar(') === 0
            && $length === (int)$limit
            && $isNullable;
    }

    private function quoteIdentifier($identifier)
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('Invalid SQL identifier: ' . $identifier);
        }

        return str_replace('`', '``', $identifier);
    }
}
