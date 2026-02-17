<?php
class CreatePostSetsAndMaintainers extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('post_sets')) {
            $this->execute("
                CREATE TABLE `post_sets` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `creator_id` int(11) NOT NULL,
                  `name` varchar(128) NOT NULL,
                  `shortname` varchar(128) NOT NULL,
                  `description` text,
                  `is_public` tinyint(1) NOT NULL DEFAULT '1',
                  `post_count` int(11) NOT NULL DEFAULT '0',
                  `created_at` datetime DEFAULT NULL,
                  `updated_at` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ");

            $this->execute("CREATE UNIQUE INDEX `post_sets_shortname_unique` ON `post_sets` (`shortname`)");
            $this->execute("CREATE INDEX `post_sets_creator_public_id` ON `post_sets` (`creator_id`, `is_public`, `id`)");
            $this->execute("ALTER TABLE `post_sets` ADD CONSTRAINT `fk_post_sets__creator_id` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT");
        }

        if (!$this->tableExists('post_set_posts')) {
            $this->execute("
                CREATE TABLE `post_set_posts` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `post_set_id` int(11) NOT NULL,
                  `post_id` int(11) NOT NULL,
                  `created_at` datetime DEFAULT NULL,
                  `updated_at` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ");

            $this->execute("CREATE UNIQUE INDEX `post_set_posts_post_set_id_post_id_unique` ON `post_set_posts` (`post_set_id`, `post_id`)");
            $this->execute("CREATE INDEX `post_set_posts_post_id_post_set_id` ON `post_set_posts` (`post_id`, `post_set_id`)");
            $this->execute("ALTER TABLE `post_set_posts` ADD CONSTRAINT `fk_post_set_posts__post_set_id` FOREIGN KEY (`post_set_id`) REFERENCES `post_sets` (`id`) ON DELETE CASCADE");
            $this->execute("ALTER TABLE `post_set_posts` ADD CONSTRAINT `fk_post_set_posts__post_id` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE");
        }

        if (!$this->tableExists('post_set_maintainers')) {
            $this->execute("
                CREATE TABLE `post_set_maintainers` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `post_set_id` int(11) NOT NULL,
                  `user_id` int(11) NOT NULL,
                  `status` varchar(16) NOT NULL DEFAULT 'pending',
                  `created_at` datetime DEFAULT NULL,
                  `updated_at` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ");

            $this->execute("CREATE UNIQUE INDEX `post_set_maintainers_post_set_id_user_id_unique` ON `post_set_maintainers` (`post_set_id`, `user_id`)");
            $this->execute("CREATE INDEX `post_set_maintainers_user_status_set` ON `post_set_maintainers` (`user_id`, `status`, `post_set_id`)");
            $this->execute("ALTER TABLE `post_set_maintainers` ADD CONSTRAINT `fk_post_set_maintainers__post_set_id` FOREIGN KEY (`post_set_id`) REFERENCES `post_sets` (`id`) ON DELETE CASCADE");
            $this->execute("ALTER TABLE `post_set_maintainers` ADD CONSTRAINT `fk_post_set_maintainers__user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");
        }
    }
}

