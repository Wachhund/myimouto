<?php

namespace Tag;

use Rails;
use Moebooru;

trait CacheMethods
{
    protected function update_cache()
    {
        Rails::cache()->write(['tag_type' => $this->name], self::type_name_from_value($this->tag_type));

        # Expire the tag cache if a tag's type changes.
        if ($this->tag_type != $this->tagTypeWas()) {
            Moebooru\CacheHelper::expire_tag_version();
        }
    }

    # Expire the tag cache when a new tag is created.
    protected function update_cache_on_create()
    {
        Moebooru\CacheHelper::expire_tag_version();
    }
}
