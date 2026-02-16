<?php
trait PostTagMethods
{
    public $new_tags;
    
    public $old_tags;
    
    public $old_cached_tags;
    
    protected $tags_list = [];
    
    private $tags_string;

    static public function find_by_tags($tags, array $options = array())
    {
        list ($sql, $params) = Post::generate_sql($tags, $options);
        return Post::findBySql($sql, $params);
    }

    static public function recalculate_cached_tags($id = null)
    {
        // conds = array()
        // cond_params = array()

        // sql = %{
            // UPDATE posts p SET cached_tags = (
                // SELECT array_to_string(coalesce(array(
                    // SELECT t.name
                    // FROM tags t, posts_tags pt
                    // WHERE t.id = pt.tag_id AND pt.post_id = p.id
                    // ORDER BY t.name
                // ), 'array()'::textarray()), ' ')
            // )
        // }
        // if ((id) {) {
            // conds << "WHERE p.id = ?"
            // cond_params << id
        // }

        // sql = [sql, conds].join(" ")
        // executeSql sql, *cond_params
    }

    # new, previous and latest are History objects for cached_tags.  Split
    # the tag changes apart.
    static public function tag_changes($new, $previous, $latest)
    {
        $new_tags = explode(' ', $new->value);
        if ($previous && $previous->value)
            $old_tags = explode(' ', $previous->value);
        else
            $old_tags = [];
        
        $latest_tags = explode(' ', $latest->value);

        return [
            'added_tags' => array_diff($new_tags, $old_tags),
            'removed_tags' => array_diff($old_tags, $new_tags),
            'unchanged_tags' => array_intersect($new_tags, $old_tags),
            'obsolete_added_tags' => array_diff(array_diff($new_tags, $old_tags), $latest_tags),
            'obsolete_removed_tags' => array_intersect(array_diff($old_tags, $new_tags), $latest_tags),
        ];
    }

    public function cached_tags_undo($change, $redo_changes = false)
    {
        $current_tags = explode(' ', $this->cached_tags);
        $prev = $change->previous;
        
        if ($redo_changes) {
            list($change, $prev) = [$prev, $change];
        }
        $changes = self::tag_changes($change, $prev, $change->latest());
        $new_tags = array_unique(array_merge(array_diff($current_tags, $changes['added_tags']), $changes['removed_tags']));
        $this->setTags(implode(' ', $new_tags));
    }

    public function cached_tags_redo($change)
    {
        $this->cached_tags_undo($change, true);
    }

    # === Parameters
    # * :tag<String>:: the tag to search for
    public function has_tag($tag)
    {
        return array_search($tag, $this->tags) !== false;
    }

    # Returns the tags in a URL suitable string
    public function tag_title()
    {
        return substr(preg_replace('/\W+/', '-', $this->cached_tags), 0, 50);
    }

    # Return the tags we display in URLs, page titles, etc.
    public function title_tags()
    {
        return $this->cached_tags;
    }

    public function tags()
    {
        return $this->getTags();
    }
    
    public function getTags()
    {
        return explode(' ', $this->cached_tags);
    }

    # Sets the tags for the post. Does not actually save anything to the database when called.
    #
    # === Parameters
    # * :tags<String>:: a whitespace delimited list of tags
    public function setTags($tags)
    {
        $this->new_tags = array_filter(Tag::scan_tags($tags));

        $current_tags = explode(' ', $this->cached_tags);
        
        if ($this->new_tags != $current_tags)
            $this->touch_change_seq();
        
        $this->tags_list = $tags;
    }

    # Returns all versioned tags and metatags.
    public function cached_tags_versioned()
    {
        return "rating:" . $this->rating . ' ' . $this->cached_tags;
    }
    
    # Commit metatags; this is done before save, so any changes are stored normally.
    /**
     * Moved commit_metatags to be executed before commit_tags to avoid re-saving the
     * model. This might have Unforeseen Consequences, but nothing detected so far.
     */
    protected function commit_metatags()
    {
        foreach ($this->new_tags as $k => $tag) {
            # Moved this one outside the switch because preg_match
            # doesn't get along well with switch.
            if (preg_match('/^source:(.*)/', $tag, $m)) {
                unset($this->new_tags[$k]);
                $this->source = $m[1];
                continue;
            }
            
            switch ($tag) {
                case 'hold':
                    $this->is_held = true;
                    unset($this->new_tags[$k]);
                    break;
                
                case 'unhold':
                    $this->is_held = false;
                    unset($this->new_tags[$k]);
                    break;
                
                case 'show':
                    $this->is_shown_in_index = true;
                    unset($this->new_tags[$k]);
                    break;
                
                case 'hide':
                    $this->is_shown_in_index = false;
                    unset($this->new_tags[$k]);
                    break;
                
                case '+flag':
                    unset($this->new_tags[$k]);
                    # Permissions for this are checked on commit.
                    $this->metatag_flagged = "moderator flagged";
                    break;
            }
        }
    }

    # Commit any tag changes to the database.  This is done after save, so any changes
    # must be made directly to the database.
    /**
     * It's strange how actions like changing pool's sequence, that updates the post via
     * update_batch and sends only one metatag: 'pool:$id:$seq' and doesn't send old_tags, don't
     * mess up the rest of the tags (because that metatag is discarted, and the post is left without
     * old_tags or any tag).
     * So we're gonna do a workaround for that: if the new tags only contain metatags, we won't
     * take any further action but commit such metatags.
     * We'll use $had_metatags for that.
     */
    protected function commit_tags()
    {
        if ($this->isNewRecord() || !$this->new_tags)
            return;
        
        if ($this->old_tags) {
            # If someone else committed changes to this post before we did,
            # then try to merge the tag changes together.
            $current_tags = explode(' ', $this->cached_tags);
            $this->old_tags = Tag::scan_tags($this->old_tags);
            $this->new_tags = array_filter(array_merge(array_diff(array_merge($current_tags, $this->new_tags), $this->old_tags), array_intersect($current_tags, $this->new_tags)));
        }
        
        $this->commit_metatags();
        
        $meta_tags = ['-pool:', 'pool:', 'rating:', 'parent:', 'child:', 'source:'];
        $ratings = ['q', 's', 'e'];
        
        $had_metatags = false;
        foreach ($this->new_tags as $k => $tag) {
            # To avoid preg_match.
            $is_mt = false;
            foreach ($meta_tags as $mt) {
                if (strpos($tag, $mt) === 0 || in_array($tag, $ratings)) {
                    $is_mt = true;
                    break;
                }
            }
            if (!$is_mt)
                continue;
            
            $had_metatags = true;
            
            unset($this->new_tags[$k]);
            
            if (in_array($tag, $ratings))
                $tag = 'rating:'.$tag;
            
            $subparam = explode(':', $tag, 3);
            $metatag  = array_shift($subparam);
            $param    = array_shift($subparam);
            $subparam = empty($subparam) ? null : array_shift($subparam);
            
            switch($metatag) {
                case 'rating':
                    # Change rating. This will override rating selected on radio buttons.
                    if (in_array($param, $ratings))
                        $this->rating = $param;
                    break;
                
                case 'pool':
                    try {
                        $name = $param;
                        $seq = $subparam;
                        
                        # Workaround: I don't understand how can the pool be found when finding_by_name
                        # using the id.
                        if (ctype_digit($name))
                            $pool = Pool::where(['id' => $name])->first();
                        else
                            $pool = Pool::where(['name' => $name])->first();
                        
                        # Set :ignore_already_exists, so pool:1:2 can be used to change the sequence number
                        # of a post that already exists in the pool.
                        $options = array('user' => User::where('id = ?', $this->updater_user_id)->first(), 'ignore_already_exists' => true);
                        if ($seq)
                            $options['sequence'] = $seq;
                        
                        if (!$pool and !ctype_digit($name))
                            $pool = Pool::create(array('name' => $name, 'is_public' => false, 'user_id' => $this->updater_user_id));
                        
                        if (!$pool || !$pool->can_change(current_user(), null))
                            continue 2;
                        
                        $pool->add_post($this->id, $options);
                        
                    } catch(Pool_PostAlreadyExistsError $e) {
                    } catch (Pool_AccessDeniedError $e) {
                    }
                    break;
                    
                case '-pool':
                    $name = $param;
                    $cmd = $subparam;

                    $pool = Pool::where(['name' => $name])->first();
                    if (!$pool->can_change(current_user(), null))
                        break;

                    if ($cmd == "parent") {
                        # If we have a parent, remove ourself from the pool and add our parent in
                        # our place.    If we have no parent, do nothing and leave us in the pool.
                        if (!empty($this->parent_id)) {
                            $pool->transfer_post_to_parent($this->id, $this->parent_id);
                            break;
                        }
                    }
                    $pool && $pool->remove_post($id);
                    break;
                    
                case 'source':
                    $this->source = $param;
                    break;
                    
                case 'parent':
                    if (is_numeric($param)) {
                        $this->parent_id = (int)$param;;
                    }
                    break;
                
                case 'child':
                    unset($this->new_tags[$k]);
                    break;
            }
        }
        
        if (!$this->new_tags) {
            if ($had_metatags)
                return;
            $this->new_tags[] = "tagme";
        }
        
        // $this->tags = implode(' ', array_unique(TagImplication::with_implied(TagAlias::to_aliased($this->new_tags))));
        $this->new_tags = TagAlias::to_aliased($this->new_tags);
        $this->new_tags = array_unique(TagImplication::with_implied($this->new_tags));
        sort($this->new_tags);
        // $this->tags = implode(' ', $this->tags());
        
        # TODO: be more selective in deleting from the join table
        self::connection()->executeSql("DELETE FROM posts_tags WHERE post_id = ?", $this->id);
        
        $new_tags_ids   = [];
        $new_tags_names = [];
        $this->new_tags = array_map(function ($x) use (&$new_tags_ids, &$new_tags_names) {
            $tag = Tag::find_or_create_by_name($x);
            if (!in_array($tag->id, $new_tags_ids)) {
                $new_tags_ids[]   = $tag->id;
                $new_tags_names[] = $tag->name;
                return $tag;
            }
        }, $this->new_tags);
        unset($new_tags_ids);
        $this->new_tags = array_filter($this->new_tags);
        
        # If any tags are newly active, expire the tag cache.
        if ($this->new_tags) {
            $any_new_tags = false;
            $previous_tags = explode(' ', $this->cached_tags);
            foreach ($this->new_tags as $tag) {
                # If this tag is in old_tags, then it's already active and we just removed it
                # in the above DELETE, so it's not really a newly activated tag.    (This isn't
                # self.old_tags; that's the tags the user saw before he edited, not the data
                # we're replacing.)
                if ($tag->post_count == 0 and !in_array($tag->name, $previous_tags)) {
                    $any_new_tags = true;
                    break;
                }
            }

            if ($any_new_tags) {
                Moebooru\CacheHelper::expire_tag_version();
            }
        }
        
        # Sort
        sort($new_tags_names);
        sort($this->new_tags);
        
        $tag_set = implode(", ", array_map(function($x){return "(".$this->id.", ".$x->id.")";}, $this->new_tags));
        
        $this->cached_tags = implode(' ', $new_tags_names);
        
        $sql = "INSERT INTO posts_tags (post_id, tag_id) VALUES " . $tag_set;
        self::connection()->executeSql($sql);
        
        # Store the old cached_tags, so we can expire them.
        $this->old_cached_tags = $this->cached_tags;
        // Commenting the following line; it will cause cached_tags to not to be updated.
        // They're set above.
        // $this->cached_tags = self::connection()->selectValue("SELECT cached_tags FROM posts WHERE id = " . $this->id);

        $this->new_tags = null;
    }

    protected function save_post_history()
    {
        $new_cached_tags = $this->cached_tags_versioned();
        if ($this->tag_history->none() or $this->tag_history[0]->tags != $new_cached_tags) {
            PostTagHistory::create([
                'post_id' => $this->id,
                'tags'    => $new_cached_tags,
                'user_id' => $this->user_id,
                'ip_addr' => current_user() && current_user()->ip_addr ? current_user()->ip_addr : "127.0.0.1"
            ]);
        }
    }

    
    public function tag_names()
    {
        return $this->cached_tags;
    }
}
