<?php
class AddScheduledJobSupport extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        // Add composite index on job_tasks for scheduling queries.
        // Covers: pending/processing duplicate check and last-run lookup.
        if (!$this->indexExists('job_tasks', 'idx_job_tasks_type_status')) {
            $this->execute("ALTER TABLE job_tasks ADD INDEX idx_job_tasks_type_status (task_type, status)");
        }

        // Add cleanup_status and cleanup_retries to user_deletion_events for async bulk-delete tracking.
        if ($this->tableExists('user_deletion_events')) {
            if (!$this->columnExists('user_deletion_events', 'cleanup_status')) {
                $this->execute(
                    "ALTER TABLE user_deletion_events " .
                    "ADD COLUMN cleanup_status ENUM('completed','pending','failed') NOT NULL DEFAULT 'completed'"
                );
            }
            if (!$this->columnExists('user_deletion_events', 'cleanup_retries')) {
                $this->execute(
                    "ALTER TABLE user_deletion_events " .
                    "ADD COLUMN cleanup_retries TINYINT UNSIGNED NOT NULL DEFAULT 0"
                );
            }
        }
    }

    public function down()
    {
        if ($this->indexExists('job_tasks', 'idx_job_tasks_type_status')) {
            $this->execute("ALTER TABLE job_tasks DROP INDEX idx_job_tasks_type_status");
        }

        if ($this->tableExists('user_deletion_events')) {
            if ($this->columnExists('user_deletion_events', 'cleanup_retries')) {
                $this->execute("ALTER TABLE user_deletion_events DROP COLUMN cleanup_retries");
            }
            if ($this->columnExists('user_deletion_events', 'cleanup_status')) {
                $this->execute("ALTER TABLE user_deletion_events DROP COLUMN cleanup_status");
            }
        }
    }

    private function indexExists($table, $indexName)
    {
        $result = $this->execute(
            "SELECT COUNT(*) AS cnt FROM information_schema.STATISTICS " .
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
            $table,
            $indexName
        );
        $row = $result->fetch();
        return $row && (int)($row['cnt'] ?? $row[0] ?? 0) > 0;
    }

    private function columnExists($table, $column)
    {
        $result = $this->execute(
            "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS " .
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            $table,
            $column
        );
        $row = $result->fetch();
        return $row && (int)($row['cnt'] ?? $row[0] ?? 0) > 0;
    }
}
