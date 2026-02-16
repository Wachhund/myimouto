<?php
class ChangeIpAddrLength extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        $this->execute("ALTER TABLE `ip_bans` CHANGE `ip_addr` `ip_addr` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL");
        $this->execute("ALTER TABLE `comments` CHANGE `ip_addr` `ip_addr` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL");
        $this->execute("ALTER TABLE `post_tag_histories` CHANGE `ip_addr` `ip_addr` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL");
        $this->execute("ALTER TABLE `wiki_page_versions` CHANGE `ip_addr` `ip_addr` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL");
        $this->execute("ALTER TABLE `wiki_pages` CHANGE `ip_addr` `ip_addr` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL");
    }
}
