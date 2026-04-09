<?php
class CreateExceptionLogs extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('exception_logs')) {
            $this->execute("
                CREATE TABLE `exception_logs` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `code` VARCHAR(36) NOT NULL,
                    `exception_class` VARCHAR(255) NOT NULL,
                    `message` TEXT,
                    `backtrace` LONGTEXT,
                    `request_uri` VARCHAR(2048) DEFAULT NULL,
                    `request_method` VARCHAR(10) DEFAULT NULL,
                    `request_params` TEXT DEFAULT NULL,
                    `ip_address` VARCHAR(45) DEFAULT NULL,
                    `user_id` INT DEFAULT NULL,
                    `version` VARCHAR(12) DEFAULT NULL,
                    `extra_data` TEXT DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `idx_exception_logs_code` (`code`),
                    INDEX `idx_exception_logs_created_at` (`created_at`),
                    INDEX `idx_exception_logs_exception_class` (`exception_class`),
                    INDEX `idx_exception_logs_user_id` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }
}
