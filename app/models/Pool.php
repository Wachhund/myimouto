<?php
class Pool_AccessDeniedError extends Exception
{}

class Pool_PostAlreadyExistsError extends Exception
{}

class Pool extends Rails\ActiveRecord\Base
{
    use Moebooru\Versioning\VersioningTrait;
    
    static public function init_versioning($v)
    {
        $v->versioned_attributes([
            'name',
            'description' => ['default' => ''],
            'is_public'   => ['default' => true],
            'is_active'   => ['default' => true],
        ]);
    }
    
    /* PostMethods { */
    static public function get_pool_posts_from_posts(array $posts)
    {
        if (!$post_ids = array_map(function($post){return $post->id;}, $posts))
            return array();
        # CHANGED: WHERE pp.active ...
        $sql = sprintf("SELECT pp.* FROM pools_posts pp WHERE pp.post_id IN (%s)", implode(',', $post_ids));
        return PoolPost::findBySql($sql)->members();
    }

    static public function get_pools_from_pool_posts(array $pool_posts)
    {
        if (!$pool_ids = array_unique(array_map(function($pp){return $pp->pool_id;}, $pool_posts)))
            return array();

        $sql = sprintf("SELECT p.* FROM pools p WHERE p.id IN (%s)", implode(',', $pool_ids));
        
        if ($pools = Pool::findBySql($sql))
            return $pools->members();
        else
            return [];
    }

    public function can_be_updated_by($user)
    {
        return $this->is_public || $user->has_permission($this);
    }

    public function add_post($post_id, $options = array())
    {
        if (isset($options['user']) && !$this->can_be_updated_by($options['user']))
            throw new Pool_AccessDeniedError();
        
        $seq = isset($options['sequence']) ? $options['sequence'] : $this->next_sequence();
        
        $pool_post = $this->all_pool_posts ? $this->all_pool_posts->search('post_id', $post_id) : null;
        
        if ($pool_post) {
            # If :ignore_already_exists, we won't raise PostAlreadyExistsError; this allows
            # he sequence to be changed if the post already exists.
            if ($pool_post->active && empty($options['ignore_already_exists'])) {
                throw new Pool_PostAlreadyExistsError();
            }
            $pool_post->active = 1;
            $pool_post->sequence = $seq;
            $pool_post->save();
        } else {
            /**
             * MI: Passing "active" because otherwise such attribute would be Null and
             * History wouldn't work nicely.
             */
            PoolPost::create(array('pool_id' => $this->id, 'post_id' => $post_id, 'sequence' => $seq, 'active' => 1));
        }
        
        if (empty($options['skip_update_pool_links'])) {
            $this->reload();
            $this->update_pool_links();
        }
    }

    public function remove_post($post_id, array $options = array())
    {
        // self::transaction(function() use ($post_id, $options) {
            if (!empty($options['user']) && !$this->can_be_updated_by($options['user']))
                throw new Exception('Access Denied');
            
            if ($this->all_pool_posts) {
                if (!$pool_post = $this->all_pool_posts->search('post_id', $post_id))
                    return;
                $pool_post->active = 0;
                $pool_post->save();
            }
            
            $this->reload(); # saving pool_post modified us
            $this->update_pool_links();
        // });
    }

    public function recalculate_post_count()
    {
        $this->post_count = $this->pool_posts->size();
    }

    public function transfer_post_to_parent($post_id, $parent_id)
    {
        $pool_post = $this->pool_posts->find_first(array('conditions' => array("post_id = ?", $post_id)));
        $parent_pool_post = $this->pool_posts->find_first(array('conditions' => array("post_id = ?", $parent_id)));
        // return if not parent_pool_post.nil?
        if ($parent_pool_post)
            return;

        $sequence = $pool_post->sequence;
        $this->remove_post($post_id);
        $this->add_post($parent_id, array('sequence' => $sequence));
    }

    public function get_sample() 
    {
        # By preference, pick the first post (by sequence) in the pool that isn't hidden from
        # the index.
        $pool_post = PoolPost::where("pool_id = ? AND posts.status = 'active' AND pools_posts.active", $this->id)
                            ->order("posts.is_shown_in_index DESC, pools_posts.sequence, pools_posts.post_id")
                            ->joins("JOIN posts ON posts.id = pools_posts.post_id")
                            ->take();
        
        foreach ($pool_post as $pp) {
            if ($pp->post->can_be_seen_by(current_user())) {
                return $pp->post;
            }
        }
    }

    public function can_change_is_public($user) 
    {
        return $user->has_permission($this);
    }

    public function can_change($user, $attribute)
    {
        if (!$user->is_member_or_higher())
            return false;
        return $this->is_public || $user->has_permission($this);
    }

    public function update_pool_links() 
    {
        if (!$this->pool_posts)
            return;
        
        # iTODO: an assoc can be called like "pool_posts(true)"
        # to force reload.
        # Add support for this maybe?
        # $this->_load_association('pool_posts');
        $pp = $this->pool_posts; //(true) # force reload
        
        $count = $pp->size();
        
        foreach ($pp as $i => $v) {
            $v->next_post_id = ($i == $count - 1)
                ? null
                : (isset($pp[$i + 1]) ? $pp[$i + 1]->post_id : null);
            $v->prev_post_id = ($i == 0)
                ? null
                : (isset($pp[$i - 1]) ? $pp[$i - 1]->post_id : null);
            $pp[$i]->save();
        }
    }

    public function next_sequence() 
    {
        $seq = 0;
        
        foreach ($this->pool_posts as $pp) {
            $seq = max(array($seq, $pp->sequence));
        }
        
        return $seq + 1;
    }
    
    public function expire_cache()
    {
        Moebooru\CacheHelper::expire();
    }
   
    /* } ApiMethods { */
    
    public function api_attributes()
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'user_id'     => $this->user_id,
            'is_public'   => $this->is_public,
            'post_count'  => $this->post_count,
            'description' => $this->description,
        ];
    }

    public function asJson(array $params = [])
    {
        return $this->api_attributes();
    }

    public function toXml(array $options = [])
    {
        /*empty($options['indent']) && $options['indent'] = 2;*/
        $xml = isset($options['builder']) ? $options['builder'] : new Rails\ActionView\Xml(/*['indent' => $options['indent']]*/);
        $xml->pool($this->api_attributes(), function() use ($xml) {
            $xml->description($this->description);
        });
        return $xml->output();
    }
    
    /* } NameMethods { */
    
    static public function find_by_name($name)
    {
        if (ctype_digit((string)$name)) {
            return self::where(['id' => $name])->first();
        } else {
            return self::where("lower(name) = lower(?)", $name)->first();
        }
    }

    public function normalize_name()
    {
        $this->name = str_replace(' ', "_", $this->name);
    }

    public function pretty_name()
    {
        return str_replace('_', ' ', $this->name);
    }
    
    /* } ZipMethods { */
    
    public function get_zip_filename(array $options = [])
    {
        $filename = str_replace('?', "", $this->pretty_name());
        if (!empty($options['jpeg']))
            $filename .= " (JPG)";
        return $filename . ".zip";
    }

    # Return true if any posts in this pool have a generated JPEG version.
    public function has_jpeg_zip(array $options = [])
    {
        foreach ($this->pool_posts as $pool_post) {
            $post = $pool_post->post;
            if ($post->has_jpeg())
                return true;
        }
        return false;
    }

    # Estimate the size of the ZIP.
    public function get_zip_size(array $options = [])
    {
        $sum = 0;
        foreach ($this->pool_posts as $pool_post) {
            $post = $pool_post->post;
            if ($post->status == 'deleted')
                continue;
            $sum += !empty($options['jpeg']) && $post->has_jpeg() ? $post->jpeg_size : $post->file_size;
        }

        return $sum;
    }

    public function get_zip_data($options = [])
    {
        if (!$this->pool_posts->any())
            return false;

        $jpeg = !empty($options['jpeg']);
        
        # Pad sequence numbers in filenames to the longest sequence number.    Ignore any text
        # after the sequence for padding; for example, if we have 1, 5, 10a and 12,pad
        # to 2 digits.

        # Always pad to at least 3 digits.
        $max_sequence_digits = 3;
        foreach ($this->pool_posts as $pool_post) {
            $filtered_sequence = preg_replace('/^([0-9]+(-[0-9]+)?)?.*/', '\1', $pool_post->sequence); # 45a -> 45
            foreach (explode('-', $filtered_sequence) as $p)
                $max_sequence_digits = max(strlen($p), $max_sequence_digits);
        }

        $filename_count = $files = [];
        foreach ($this->pool_posts as $pool_post) {
            $post = $pool_post->post;
            if ($post->status == 'deleted')
                continue;

            if ($jpeg && $post->has_jpeg()) {
                $path = $post->jpeg_path();
                $file_ext = "jpg";
            } else {
                $path = $post->file_path();
                $file_ext = $post->file_ext;
            }
            
            # Pretty filenames
            if (!empty($options['pretty_filenames'])) {
                $filename = $post->pretty_file_name() . '.' . $file_ext;
            } else {
                # For padding filenames, break numbers apart on hyphens and pad each part.    For
                # example, if max_sequence_digits is 3, and we have "88-89", pad it to "088-089".
                if (preg_match('/^([0-9]+(-[0-9]+)*)(.*)$/', $pool_post->sequence, $m)) {
                    if ($m[1] != "") {
                        $suffix = $m[3];
                        $numbers = implode('-', array_map(function($p) use ($max_sequence_digits) {
                            return str_pad($p, $max_sequence_digits, '0', STR_PAD_LEFT);
                        }, explode('-', $m[1])));
                        $filename = sprintf("%s%s", $numbers, $suffix);
                    } else
                        $filename = sprintf("%s", $m[3]);
                } else
                    $filename = $pool_post->sequence;
                
                # Avoid duplicate filenames.
                !isset($filename_count[$filename]) && $filename_count[$filename] = 0;
                $filename_count[$filename]++;
                if ($filename_count[$filename] > 1)
                    $filename .= sprintf(" (%i)", $filename_count[$filename]);
                $filename .= sprintf(".%s", $file_ext);
            }
            
            $files[] = [$path, $filename];
        }
        
        return $files;
    }
    
    protected function destroy_pool_posts()
    {
        PoolPost::destroyAll('pool_id = ?', $this->id);
    }
    
    /* } */
    protected function associations()
    {
        return [
            'belongs_to' => [
                'user',
            ],
            'has_many' => [
                'pool_posts' => [function() { $this->where('pools_posts.active = true')->order('CAST(sequence AS UNSIGNED), post_id'); }, 'class_name' => "PoolPost"],
                'all_pool_posts' => [function() { $this->order('CAST(sequence AS UNSIGNED), post_id'); }, 'class_name' => "PoolPost"]
            ]
        ];
    }
    
    protected function validations()
    {
        return [
            'name' => [
                'presence' => true,
                'uniqueness' => true
            ],
            'validate_utf8_name',
            'validate_utf8_description'
        ];
    }

    protected function validate_utf8_name()
    {
        if ($this->name === null || $this->name === '') {
            return;
        }

        if (!$this->is_valid_utf8($this->name)) {
            $this->errors()->add('name', 'contains invalid UTF-8');
        }
    }

    protected function validate_utf8_description()
    {
        if ($this->description === null || $this->description === '') {
            return;
        }

        if (!$this->is_valid_utf8($this->description)) {
            $this->errors()->add('description', 'contains invalid UTF-8');
        }
    }

    protected function is_valid_utf8($value)
    {
        if (!is_string($value)) {
            if (is_int($value) || is_float($value)) {
                $value = (string) $value;
            } else {
                return false;
            }
        }

        if (function_exists('mb_check_encoding')) {
            return mb_check_encoding($value, 'UTF-8');
        }

        return preg_match('//u', $value) === 1;
    }
    
    protected function callbacks()
    {
        return [
            'before_destroy' => ['destroy_pool_posts'],
            'after_save' => ['expire_cache'],
            'before_validation' => ['normalize_name'],
            'after_undo' => ['update_pool_links']
        ];
    }
}
