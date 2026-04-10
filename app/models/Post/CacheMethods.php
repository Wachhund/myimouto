<?php

namespace Post;

trait CacheMethods
{
    public function expire_cache()
    {
        # Have to call this twice in order to expire tags that may have been removed
        if ($this->old_cached_tags) {
            \Moebooru\CacheHelper::expire(['tags' => $this->old_cached_tags]);
        }
        \Moebooru\CacheHelper::expire(['tags' => $this->cached_tags]);
    }
}
