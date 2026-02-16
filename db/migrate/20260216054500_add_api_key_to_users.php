<?php
class AddApiKeyToUsers extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        $this->execute("ALTER TABLE `users` ADD `api_key` VARCHAR(64) NULL DEFAULT NULL AFTER `password_hash`");
        $this->execute("CREATE UNIQUE INDEX `users_api_key_unique` ON `users` (`api_key`)");
    }
}
