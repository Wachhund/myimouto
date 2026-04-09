<?php
class CreateUploadWhitelists extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('upload_whitelists')) {
            $this->execute("
                CREATE TABLE `upload_whitelists` (
                  `id` int unsigned NOT NULL AUTO_INCREMENT,
                  `pattern` varchar(255) NOT NULL,
                  `allowed` tinyint(1) NOT NULL DEFAULT 1,
                  `reason` text DEFAULT NULL,
                  `note` text DEFAULT NULL,
                  `hidden` tinyint(1) NOT NULL DEFAULT 0,
                  `created_at` datetime NOT NULL,
                  `updated_at` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ");

            $this->execute("CREATE INDEX `upload_whitelists_allowed_pattern` ON `upload_whitelists` (`allowed`, `pattern`)");
            $this->execute("CREATE INDEX `upload_whitelists_pattern` ON `upload_whitelists` (`pattern`)");
        }
    }
}
