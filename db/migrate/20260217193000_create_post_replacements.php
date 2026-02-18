<?php
class CreatePostReplacements extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('post_replacements')) {
            $this->execute("
                CREATE TABLE `post_replacements` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `post_id` int(11) NOT NULL,
                  `creator_id` int(11) NOT NULL,
                  `reviewed_by_id` int(11) DEFAULT NULL,
                  `status` varchar(16) NOT NULL DEFAULT 'pending',
                  `reason` text,
                  `moderation_reason` text,
                  `source_url` varchar(1024) DEFAULT NULL,
                  `replacement_file_path` varchar(255) DEFAULT NULL,
                  `replacement_file_name` varchar(255) DEFAULT NULL,
                  `replacement_md5` varchar(32) DEFAULT NULL,
                  `reviewed_at` datetime DEFAULT NULL,
                  `created_at` datetime DEFAULT NULL,
                  `updated_at` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ");

            $this->execute("CREATE INDEX `post_replacements_post_status_id` ON `post_replacements` (`post_id`, `status`, `id`)");
            $this->execute("CREATE INDEX `post_replacements_creator_created_at` ON `post_replacements` (`creator_id`, `created_at`)");
            $this->execute("CREATE INDEX `post_replacements_status_updated_at` ON `post_replacements` (`status`, `updated_at`)");
            $this->execute("CREATE INDEX `post_replacements_reviewed_by_id` ON `post_replacements` (`reviewed_by_id`)");

            $this->execute("ALTER TABLE `post_replacements` ADD CONSTRAINT `fk_post_replacements__post_id` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE");
            $this->execute("ALTER TABLE `post_replacements` ADD CONSTRAINT `fk_post_replacements__creator_id` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");
            $this->execute("ALTER TABLE `post_replacements` ADD CONSTRAINT `fk_post_replacements__reviewed_by_id` FOREIGN KEY (`reviewed_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
        }
    }
}
