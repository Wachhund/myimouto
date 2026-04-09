<?php
class CreateUserDeletionEvents extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('user_deletion_events')) {
            $this->execute("
                CREATE TABLE `user_deletion_events` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `target_user_id` INT NOT NULL,
                    `target_user_name` VARCHAR(100) NOT NULL,
                    `target_user_level` INT NOT NULL,
                    `actor_id` INT DEFAULT NULL,
                    `actor_type` ENUM('staff','self') NOT NULL DEFAULT 'staff',
                    `reason` TEXT NOT NULL,
                    `strategy` ENUM('anonymize','hard_delete') NOT NULL DEFAULT 'anonymize',
                    `affected_records` TEXT DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    INDEX `idx_ude_target_created` (`target_user_id`, `created_at`),
                    INDEX `idx_ude_actor_created` (`actor_id`, `created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }
}
