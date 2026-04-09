<?php
trait PostStatusMethods
{
    // static public function status_code($status)
    // {
        // $const = 'STATUS_' . strtoupper($status);
        // if (defined('POST::' . $const))
            // return POST::$const;
        // return false;
    // }
    
    public function reset_index_timestamp() 
    {
        $this->index_timestamp = $this->created_at;
    }
    
    # Bump the post to the front of the index.
    public function touch_index_timestamp()
    {
        $this->index_timestamp = date('Y-m-d H:i:s');
    }

    static public function batch_activate($user_id, $post_ids)
    {
        $conds = $cond_params = array();
        
        if ($user_id) {
            $conds[] = "user_id = ?";
            $cond_params[] = $user_id;
        }
        
        $conds[] = "is_held";
        $conds[] = "id = ?";

        # Tricky: we want posts to show up in the index in the same order they were posted.
        # If we just bump the posts, the index_timestamps will all be the same, and they'll
        # show up in an undefined order.    We don't want to do this in the ORDER BY when
        # searching, because that's too expensive.    Instead, tweak the timestamps slightly:
        # for each post updated, set the index_timestamps 1ms newer than the previous.
        #
        # Returns the number of posts actually activated.
        $count = 0;
        
        # Original function is kinda confusing...
        # If anyone knows a better way to do this, it'll be welcome.
        sort($post_ids);
        
        $s = 1;
        
        $timestamp = strtotime(self::connection()->selectValue("SELECT index_timestamp FROM posts ORDER BY id DESC LIMIT 1"));
        
        foreach ($post_ids as $id) {
            $timestamp += $s;
            
            $params = array_merge(array('UPDATE posts SET index_timestamp = ?, is_held = 0 WHERE ' . implode(' AND ', $conds)), array(date('Y-m-d H:i:s', $timestamp)), $cond_params, array($id));
            
            self::connection()->executeSql($params);
            $count++;
            $s++;
        }

        if ($count > 0)
            Moebooru\CacheHelper::expire();

        return $count;
    }

    public function update_status_on_destroy()
    {
        # Can't use updateAttributes here since this method is wrapped inside of a destroy call
        self::connection()->executeSql("UPDATE posts SET status = ? WHERE id = ?", 'deleted', $this->id);
        if ($this->parent_id)
                Post::update_has_children($this->parent_id);
        # Resolve all unresolved flags
        $unresolved = FlaggedPostDetail::where('post_id = ? AND is_resolved = 0', $this->id)->take();
        if ($unresolved) {
            foreach ($unresolved as $flag) {
                $flag->resolve(null);
            }
        }
        return false;
    }

    public function commit_status_reason()
    {
        if (!$this->status_reason)
                return;
        $this->set_flag_detail($this->status_reason, null);
    }

    public function setIsHeld($hold)
    {
        # Hack because the data comes in as a string:
        if ($hold === "false")
            $hold = false;

        $user = current_user();

        # Only the original poster can hold or unhold a post.
        if (!$user || !$user->has_permission($this))
            return;

        if ($hold) {
            # A post can only be held within one minute of posting (except by a moderator);
            # this is intended to be used on initial posting, before it shows up in the index.
            if ($this->created_at && strtotime($this->created_at) < strtotime('-1 minute'))
                return;
        }

        $was_held = $this->is_held;
        
        $this->attributes['is_held'] = $hold;
        
        # When a post is unheld, bump it.
        if ($was_held && !$hold) {
            $this->touch_index_timestamp();
        }
        
        return $hold;
    }

    # MI: Can't have a method with bang!
    // public function undelete!()
    // {
        // $this->status = 'active';
        // $this->save();
        // if ($this->parent_id) {
            // Post::update_has_children($this->parent_id);
        // }
    // }
}