<?php
class AddReceiveTicketDmailsToUsers extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->columnExists('users', 'receive_ticket_dmails')) {
            $this->execute("ALTER TABLE `users` ADD COLUMN `receive_ticket_dmails` TINYINT(1) NOT NULL DEFAULT 1");
        }
    }

    public function down()
    {
        if ($this->columnExists('users', 'receive_ticket_dmails')) {
            $this->execute("ALTER TABLE `users` DROP COLUMN `receive_ticket_dmails`");
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
