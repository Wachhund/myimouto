<?php

trait PostChangeSequenceMethods
{
    public $increment_change_seq;

    public function touch_change_seq()
    {
        $this->increment_change_seq = true;
    }

    # iTODO
    public function update_change_seq()
    {
        if (!$this->increment_change_seq) {
            return;
        }
        // self::connection()->executeSql("UPDATE posts SET change_seq = nextval('post_change_seq') WHERE id = ?", $this->id);
        // $this->change_seq = self::connection()->selectValue("SELECT change_seq FROM posts WHERE id = ?", $this->id);
    }
}
