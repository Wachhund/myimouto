<?php
class CreateUserNameChangeRequests extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('user_name_change_requests')) {
            $this->execute("
                CREATE TABLE `user_name_change_requests` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` INT NOT NULL,
                    `old_name` VARCHAR(100) NOT NULL,
                    `desired_name` VARCHAR(100) NOT NULL,
                    `reason` TEXT DEFAULT NULL,
                    `status` ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
                    `staff_id` INT DEFAULT NULL,
                    `staff_reason` TEXT DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME DEFAULT NULL,
                    `resolved_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    INDEX `idx_uncr_user_status` (`user_id`, `status`),
                    INDEX `idx_uncr_status_created` (`status`, `created_at`),
                    INDEX `idx_uncr_desired_name` (`desired_name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        if (!$this->tableExists('user_name_change_history')) {
            $this->execute("
                CREATE TABLE `user_name_change_history` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` INT NOT NULL,
                    `old_name` VARCHAR(100) NOT NULL,
                    `new_name` VARCHAR(100) NOT NULL,
                    `changed_by` INT NOT NULL,
                    `request_id` INT DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    INDEX `idx_unch_user_created` (`user_id`, `created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }
}
