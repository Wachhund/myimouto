<?php
class CreateTicketsAndTakedowns extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('tickets')) {
            $this->execute("
                CREATE TABLE `tickets` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `creator_id` INT NOT NULL,
                    `accused_id` INT DEFAULT NULL,
                    `qtype` VARCHAR(50) NOT NULL DEFAULT 'post',
                    `model_type` VARCHAR(50) DEFAULT NULL,
                    `model_id` INT DEFAULT NULL,
                    `status` ENUM('pending','in_progress','approved','rejected') NOT NULL DEFAULT 'pending',
                    `claimant_id` INT DEFAULT NULL,
                    `reason` TEXT NOT NULL,
                    `response` TEXT DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    INDEX `idx_tickets_status_updated` (`status`, `updated_at`),
                    INDEX `idx_tickets_creator_created` (`creator_id`, `created_at`),
                    INDEX `idx_tickets_claimant_status` (`claimant_id`, `status`),
                    INDEX `idx_tickets_model` (`model_type`, `model_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        if (!$this->tableExists('takedowns')) {
            $this->execute("
                CREATE TABLE `takedowns` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `creator_id` INT DEFAULT NULL,
                    `email` VARCHAR(255) DEFAULT NULL,
                    `source` TEXT DEFAULT NULL,
                    `reason` TEXT NOT NULL,
                    `status` ENUM('pending','approved','denied','partial') NOT NULL DEFAULT 'pending',
                    `vericode` VARCHAR(32) NOT NULL,
                    `instructions` TEXT DEFAULT NULL,
                    `notes` TEXT DEFAULT NULL,
                    `approver_id` INT DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE INDEX `idx_takedowns_vericode` (`vericode`),
                    INDEX `idx_takedowns_status_created` (`status`, `created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        if (!$this->tableExists('takedown_posts')) {
            $this->execute("
                CREATE TABLE `takedown_posts` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `takedown_id` INT NOT NULL,
                    `post_id` INT NOT NULL,
                    `status` ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE INDEX `idx_takedown_posts_unique` (`takedown_id`, `post_id`),
                    INDEX `idx_takedown_posts_post` (`post_id`, `takedown_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }
}
