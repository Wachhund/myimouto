<?php
class CreateForumSubscriptionsAndVotes extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('forum_topic_subscriptions')) {
            $this->execute("
                CREATE TABLE `forum_topic_subscriptions` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` INT NOT NULL,
                    `forum_topic_id` INT NOT NULL,
                    `last_read_at` DATETIME DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE INDEX `idx_fts_user_topic` (`user_id`, `forum_topic_id`),
                    INDEX `idx_fts_topic_user` (`forum_topic_id`, `user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        if (!$this->tableExists('forum_post_votes')) {
            $this->execute("
                CREATE TABLE `forum_post_votes` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` INT NOT NULL,
                    `forum_post_id` INT NOT NULL,
                    `score` TINYINT NOT NULL DEFAULT 0,
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE INDEX `idx_fpv_user_post` (`user_id`, `forum_post_id`),
                    INDEX `idx_fpv_post_score` (`forum_post_id`, `score`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }
}
