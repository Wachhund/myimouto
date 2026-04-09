<?php
class CreateAuthModernizationTables extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('user_oauth_identities')) {
            $this->execute("
                CREATE TABLE `user_oauth_identities` (
                  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
                  `user_id` int NOT NULL,
                  `provider` varchar(50) NOT NULL,
                  `provider_subject` varchar(255) NOT NULL,
                  `email` varchar(255) DEFAULT NULL,
                  `metadata` text DEFAULT NULL,
                  `created_at` datetime NOT NULL,
                  `updated_at` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $this->execute("CREATE UNIQUE INDEX `idx_oauth_provider_subject` ON `user_oauth_identities` (`provider`, `provider_subject`)");
            $this->execute("CREATE INDEX `idx_oauth_user_id` ON `user_oauth_identities` (`user_id`)");
            $this->execute("ALTER TABLE `user_oauth_identities` ADD CONSTRAINT `fk_oauth_identities__user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");
        }

        if (!$this->tableExists('user_passkeys')) {
            $this->execute("
                CREATE TABLE `user_passkeys` (
                  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
                  `user_id` int NOT NULL,
                  `credential_id` varchar(512) NOT NULL,
                  `public_key` text NOT NULL,
                  `sign_count` int UNSIGNED NOT NULL DEFAULT 0,
                  `device_label` varchar(100) DEFAULT NULL,
                  `created_at` datetime NOT NULL,
                  `updated_at` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $this->execute("CREATE UNIQUE INDEX `idx_passkey_credential_id` ON `user_passkeys` (`credential_id`)");
            $this->execute("CREATE INDEX `idx_passkey_user_id` ON `user_passkeys` (`user_id`)");
            $this->execute("ALTER TABLE `user_passkeys` ADD CONSTRAINT `fk_passkeys__user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");
        }

        if (!$this->tableExists('api_keys')) {
            $this->execute("
                CREATE TABLE `api_keys` (
                  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
                  `user_id` int NOT NULL,
                  `name` varchar(100) NOT NULL,
                  `hashed_key` varchar(128) NOT NULL,
                  `expires_at` datetime DEFAULT NULL,
                  `last_used_at` datetime DEFAULT NULL,
                  `last_ip_address` varchar(45) DEFAULT NULL,
                  `last_user_agent` varchar(255) DEFAULT NULL,
                  `notified_at` datetime DEFAULT NULL,
                  `created_at` datetime NOT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $this->execute("CREATE INDEX `idx_api_keys_user_id` ON `api_keys` (`user_id`)");
            $this->execute("CREATE UNIQUE INDEX `idx_api_keys_hashed_key` ON `api_keys` (`hashed_key`)");
            $this->execute("ALTER TABLE `api_keys` ADD CONSTRAINT `fk_api_keys__user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");
        }
    }
}
