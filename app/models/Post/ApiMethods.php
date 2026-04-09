<?php
trait PostApiMethods
{
    public $similarity;

    public function api_attributes()
    {
        $ret = [
            'id' => (int)$this->id,
            'tags' => $this->cached_tags,
            'created_at' => strtotime($this->created_at),
            'creator_id' => (int)$this->user_id,
            'author' => (string)$this->author(),
            'change' => 0, // $this->change_seq,
            'source' => (string)$this->source,
            'score' => $this->score,
            'md5' => $this->md5,
            'file_size' => (int)$this->file_size,
            'file_url' => $this->file_url(),
            'is_shown_in_index' => (bool)$this->is_shown_in_index,
            'preview_url' => $this->preview_url(),
            'preview_width' => $this->preview_dimensions()[0],
            'preview_height' => $this->preview_dimensions()[1],
            'actual_preview_width' => $this->raw_preview_dimensions()[0],
            'actual_preview_height' => $this->raw_preview_dimensions()[1],
            'sample_url' => $this->sample_url(),
            'sample_width' => (int)($this->sample_width ?: $this->width),
            'sample_height' => (int)($this->sample_height ?: $this->height),
            'sample_file_size' => (int)$this->sample_size,
            'jpeg_url' => $this->jpeg_url(),
            'jpeg_width' => (int)($this->jpeg_width ?: $this->width),
            'jpeg_height' => (int)($this->jpeg_height ?: $this->height),
            'jpeg_file_size' => (int)$this->jpeg_size,
            'rating' => $this->rating,
            'has_children' => (bool)$this->has_children,
            'parent_id' => (int)$this->parent_id ?: null,
            'status' => $this->status,
            'width' => (int)$this->width,
            'height' => (int)$this->height,
            'is_held' => (bool)$this->is_held,
            'frames_pending_string' => '', //$this->frames_pending,
            'frames_pending' => [], //$this->frames_api_data($this->frames_pending),
            'frames_string' => '', //$this->frames,
            'frames' => [] //frames_api_data(frames)
        ];
        
        if ($this->status == "deleted") {
            unset($ret['sample_url']);
            unset($ret['jpeg_url']);
            unset($ret['file_url']);
        }

        $latest_flag = $this->latest_flag();
        if (($this->status == "flagged" or $this->status == "deleted" or $this->status == "pending") && $latest_flag) {
            $latest_flag->hide_user = ($this->status == "deleted" and current_user()->is_mod_or_higher());
            $ret['flag_detail'] = $latest_flag->api_attributes();
        }
        
        # For post/similar results:
        if ($this->similarity)
            $ret['similarity'] = $this->similarity;
        
        return $ret;
    }
    
    public function asJson($args = array())
    {
        return $this->api_attributes();
    }
    
    public function toXml(array $params = [])
    {
        $params['root'] = 'post';
        $params['attributes'] = $this->api_attributes();
        return parent::toXml($params);
    }
    
    public function api_data()
    {
        return array(
            'post' => $this->api_attributes(),
            'tags' => Tag::batch_get_tag_types_for_posts(array($this))
        );
    }
    
    # Remove attribute from params that shouldn't be changed through the API.
    static public function filter_api_changes(&$params)
    {
        if (!is_array($params)) {
            $params = [];
            return;
        }

        $always_blocked = [
            'id',
            'user_id',
            'ip_addr',
            'created_at',
            'file_size',
            'md5',
            'file_ext',
            'width',
            'height',
            'preview_width',
            'preview_height',
            'actual_preview_width',
            'actual_preview_height',
            'sample_width',
            'sample_height',
            'sample_size',
            'jpeg_width',
            'jpeg_height',
            'jpeg_size',
            'score',
            'cached_tags',
            'last_commented_at',
            'last_noted_at',
            'index_timestamp',
            'random',
            'approver_id',
            'has_children',
            'updater_user_id',
            'updater_ip_addr',
            'frames',
            'frames_warehoused'
        ];

        foreach ($always_blocked as $field) {
            unset($params[$field]);
        }

        $user = current_user();
        $is_mod_or_higher = $user && $user->is_mod_or_higher();

        if (!$is_mod_or_higher) {
            $moderation_only = [
                'status',
                'is_held',
                'is_shown_in_index',
                'is_note_locked',
                'is_rating_locked'
            ];

            foreach ($moderation_only as $field) {
                unset($params[$field]);
            }
        }
    }

    /**
     * MI: Accept "fake_sample_url" option, passed only in
     * post#index. It will set a flag that will be read by PostFileMethods::sample_url()
     */
    static public function batch_api_data(array $posts, $options = array())
    {
        self::$create_fake_sample_url = !empty($options['fake_sample_url']);

        $result = [
            'posts' => [],
            'tags' => [],
            'pools' => [],
            'pool_posts' => [],
            'votes' => [],
        ];
        
        foreach ($posts as $post)
            $result['posts'][] = $post->api_attributes();
        
        if (empty($options['exclude_pools'])) {
            $result['pools'] = $result['pool_posts'] = [];
            $pool_posts = Pool::get_pool_posts_from_posts($posts);
            $pools = Pool::get_pools_from_pool_posts($pool_posts);
            
            foreach ($pools as $p)
                $result['pools'][] = $p->api_attributes();
            foreach ($pool_posts as $pp)
                $result['pool_posts'][] = $pp->api_attributes();
        }
        
        if (empty($options['exclude_tags']))
            $result['tags'] = Tag::batch_get_tag_types_for_posts($posts);
        
        if (!empty($options['user']))
            $user = $options['user'];
        else
            $user = current_user();

        # Allow loading votes along with the posts.
        #
        # The post data is cachable and vote data isn't, so keep this data separate from the
        # main post data to make it easier to cache API output later.
        if (empty($options['exclude_votes'])) {
            $vote_map = array();
            
            if ($posts) {
                $post_ids = array();
                foreach ($posts as $p) {
                    $post_ids[] = $p->id;
                }
                
                if ($post_ids) {
                    $post_ids = implode(',', $post_ids);
                    
                    $sql = sprintf("SELECT v.* FROM post_votes v WHERE v.user_id = %d AND v.post_id IN (%s)", $user->id, $post_ids);
                    
                    $votes = PostVote::findBySql($sql);
                    foreach ($votes as $v) {
                        $vote_map[$v->post_id] = $v->score;
                    }
                }
            }
            $result['votes'] = $vote_map;
        }
        
        self::$create_fake_sample_url = false;
        
        return $result;
    }
}
