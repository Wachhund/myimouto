<?php
class CreateModActions extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('mod_actions')) {
            $this->execute("
                CREATE TABLE `mod_actions` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `creator_id` INT NOT NULL,
                    `action` VARCHAR(100) NOT NULL,
                    `values` JSON DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    INDEX `idx_mod_actions_action_created` (`action`, `created_at`),
                    INDEX `idx_mod_actions_creator_created` (`creator_id`, `created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }
}
