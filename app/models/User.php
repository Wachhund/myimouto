<?php
class User_AlreadyFavoritedError extends Exception{}
class User_NoInvites extends Exception{}
class User_HasNegativeRecord extends Exception{}

class User extends Rails\ActiveRecord\Base
{
    static private $_current;
    
    public $current_email;
    
    public $country;
    
    public $passwordConfirmation;
    
    /**
     * Set in ApplicationController.
     */
    public $ip_addr;
    
    protected $post_count;
    
    static public function set_current_user(User $user)
    {
        self::$_current = $user;
    }
    
    static public function current()
    {
        return self::$_current;
    }
    
    # Defines various convenience methods for finding out the user's level
    public function __call($method, $params)
    {
        if (strpos($method, 'is_post_')) {
            return $this->_parse_is_post_level_or($method);
        } elseif (strpos($method, 'is_') === 0) {
            if (is_int(strpos($method, '_or_')))
                return $this->parse_is_level_or($method);
            else
                return $this->parse_is_level($method);
        }
        parent::__call($method, $params);
    }
    
    public function log($ip)
    {
        return Rails::cache()->fetch(['type' => 'user_logs', 'id' => $this->id, 'ip' => $ip], ['expires_in' => '10 minutes'], function() use ($ip) {
            Rails::cache()->fetch(['type' => 'user_logs', 'id' => 'all'], ['expires_in' => '1 day'], function() {
                # RP: Missing relation method "deleteAll()"
                return UserLog::where('created_at < ?', date('Y-m-d 0:0:0', strtotime('-3 days')))->take()->each('destroy');
            });
            
            # RP: Missing feature.
            $log_entry = UserLog::where(['ip_addr' => $ip, 'user_id' => $this->id])->firstOrInitialize();
            $log_entry->created_at = date('Y-m-d H:i:s');
            return $log_entry->save();
        });
    }

    # UserBlacklistMethods {
    # TODO: I don't see the advantage of normalizing these. Since commas are illegal
    # characters in tags, they can be used to separate lines (with whitespace separating
    # tags). Denormalizing this into a field in users would save a SQL query.
    public $blacklisted_tags;

    public function blacklisted_tags()
    {
        return implode("\n", $this->blacklisted_tags_array()) . "\n";
    }

    public function blacklisted_tags_array()
    {
        if ($this->user_blacklisted_tag)
            return preg_split("/(\r\n|\r|\n)/", trim($this->user_blacklisted_tag->tags));
        else
            return [];
    }

    protected function _commit_blacklists() 
    {
        if ($this->user_blacklisted_tag && isset($this->blacklisted_tags))
            $this->user_blacklisted_tag->updateAttribute('tags', $this->blacklisted_tags);
    }

    protected function _set_default_blacklisted_tags()
    {
        UserBlacklistedTag::create(array('user_id' => $this->id, 'tags' => implode("\r\n", CONFIG()->default_blacklists)));
    }
    
    # } UserAuthenticationMethods {
    
    static public function authenticate($name, $pass)
    {
        return self::authenticate_hash($name, self::sha1($pass));
    }

    static public function authenticate_hash($name, $pass)
    {
        $user = parent::where("lower(name) = lower(?) AND password_hash = ?", $name, $pass)->first();
        return $user;
    }
    
    static public function authenticate_with_api_key($name, $api_key)
    {
        if (!$name || !$api_key) {
            return null;
        }

        return parent::where("lower(name) = lower(?) AND api_key = ?", $name, $api_key)->first();
    }

    static public function sha1($pass)
    {
        return sha1(CONFIG()->user_password_salt . '--' . $pass . '--');
    }

    public function set_api_key()
    {
        do {
            $api_key = self::generate_api_key();
        } while (self::where("api_key = ?", $api_key)->first());

        $this->api_key = $api_key;
    }

    static private function generate_api_key()
    {
        if (function_exists('random_bytes')) {
            return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        }

        return substr(sha1(uniqid(mt_rand(), true)), 0, 32);
    }
    
    # } UserPasswordMethods {
    
    public $password, $current_password;
    
    protected function validate_current_password()
    {
        # First test to see if it's creating new user (no password_hash)
        # or updating user. The second is to see if the action involves
        # updating password (which requires this validation).
        if ($this->password_hash and ($this->password or ($this->emailChanged() or $this->current_email))) {
            if (!$this->current_password)
                $this->errors()->add('current_password', 'blank');
            elseif (!User::authenticate($this->name, $this->current_password))
                $this->errors()->add('current_password', 'invalid');
        }
    }
    
    protected function _encrypt_password()
    {
        if ($this->password)
            $this->password_hash = self::sha1($this->password);
    }

    public function reset_password()
    {
        $consonants = "bcdfghjklmnpqrstvqxyz";
        $vowels = "aeiou";
        $pass = "";

        foreach (range(1, 4) as $i) {
            $pass .= substr($consonants, rand(0, 20), 1);
            $pass .= substr($vowels, rand(0, 4), 1);
        }

        $pass .= rand(0, 100);
        self::connection()->executeSql("UPDATE users SET password_hash = ? WHERE id = ?", self::sha1($pass), $this->id);
        return $pass;
    }
    
    public function setPasswordConfirmation($value)
    {
        $this->passwordConfirmation = $value;
    }
    
    # } UserCountMethods {
    
    # TODO: This isn't used anymore. Should be safe to delete.
    static public function fast_count()
    {
        return self::connection()->selectValue("SELECT row_count FROM table_data WHERE name = 'users'");
    }

    protected function _increment_count()
    {
        self::connection()->executeSql("UPDATE table_data set row_count = row_count + 1 where name = 'users'");
    }
    
    protected function _decrement_count()
    {
        self::connection()->executeSql("UPDATE table_data set row_count = row_count - 1 where name = 'users'");
    }
    
    # } UserNameMethods {
    
    static private function _find_name_helper($user_id)
    {
        if (!$user_id)
          return CONFIG()->default_guest_name;

        $user = self::where('id = ?', $user_id)->first();

        if ($user) {
          return $user->name;
        } else {
          return CONFIG()->default_guest_name;
        }
    }

    static public function find_name($user_id)
    {
        return Rails::cache()->fetch('user_name:' . $user_id, function() use ($user_id) {
            try {
                return self::find($user_id)->name;
            } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
                return CONFIG()->default_guest_name;
            }
        });
    }

    static public function find_by_name($name)
    {
        return self::where("lower(name) = lower(?)", $name)->first();
    }
    
    public function pretty_name()
    {
        return str_replace('_', ' ', $this->name);
    }
    
    // public function setPrettyName($value)
    // {
        
    // }

    protected function update_cached_name()
    {
        Rails::cache()->write("user_name:".$this->id, $this->name);
    }
    # }

    # UserApiMethods {
    # iTODO:
    public function toXml(array $options = array())
    {
        // !isset($options['indent']) && $options['indent'] = 2;
        // if (isset($options['builder']))
            // $xml = $options['builder'];
        // else
            // $xml = Builder::XmlMarkup.new('indent' => options[:indent])
        
        // xml.post('name' => name, 'id' => id) do
            // blacklisted_tags_array.each do |t|
                // xml.blacklisted_tag('tag' => t)
            // end

            // yield options[:builder] if block_given?
        // end
    }

    public function asJson(array $args = array())
    {
        return ['name' => $this->name, 'blacklisted_tags' => $this->blacklisted_tags_array(), 'id' => $this->id];
    }

    public function user_info_cookie()
    {
        return implode(';', [$this->id, $this->level, ($this->use_browser ? "1":"0")]);
    }
    # }

    public function find_by_name_nocase($name)
    {
        return User::where("lower(name) = lower(?)", $name)->first();
    }

    # UserTagMethods {
    # iTODO:
    public function uploaded_tags(array $options = array())
    {
        $type = !empty($options['type']) ? $options['type'] : null;
        
        $uploaded_tags = Rails::cache()->read("uploaded_tags/". $this->id . "/" . $type);
        if ($uploaded_tags) {
            return $uploaded_tags;
        }

        if (Rails::env() == "test") {
            # disable filtering in test mode to simplify tests
            $popular_tags = "";
        } else {
            $popular_tags = implode(', ', self::connection()->selectValues("SELECT id FROM tags WHERE tag_type = " . CONFIG()->tag_types['General'] . " ORDER BY post_count DESC LIMIT 8"));
            if ($popular_tags)
                $popular_tags = "AND pt.tag_id NOT IN (${popular_tags})";
        }

        if ($type) {
            $type = (int)$type;
            $sql = "SELECT 
                (SELECT name FROM tags WHERE id = pt.tag_id) AS tag, COUNT(*) AS count
                FROM posts_tags pt, tags t, posts p
                WHERE p.user_id = {$this->id}
                AND p.id = pt.post_id
                AND pt.tag_id = t.id
                {$popular_tags}
                AND t.tag_type = {$type}
                GROUP BY pt.tag_id
                ORDER BY count DESC
                LIMIT 6
            ";
        } else {
            $sql = "SELECT 
                (SELECT name FROM tags WHERE id = pt.tag_id) AS tag, COUNT(*) AS count
                FROM posts_tags pt, posts p
                WHERE p.user_id = {$this->id}
                AND p.id = pt.post_id
                ${popular_tags}
                GROUP BY pt.tag_id
                ORDER BY count DESC
                LIMIT 6
            ";
        }

        $uploaded_tags = self::connection()->select($sql);

        Rails::cache()->write("uploaded_tags/" . $this->id . "/" . $type, $uploaded_tags, ['expires_in' => '1 day']);

        return $uploaded_tags;
    }

    public function voted_tags(array $options = array())
    {
        $type = !empty($options['type']) ? $options['type'] : null;

        $favorite_tags = Rails::cache()->read("favorite_tags/".  $this->id . "/" . $type);
        if ($favorite_tags) {
            return $favorite_tags;
        }

        if (Rails::env() == "test") {
            # disable filtering in test mode to simplify tests
            $popular_tags = "";
        } else {
            $popular_tags = implode(', ', self::connection()->selectValues("SELECT id FROM tags WHERE tag_type = " . CONFIG()->tag_types['General'] . " ORDER BY post_count DESC LIMIT 8"));
            if ($popular_tags)
                $popular_tags = "AND pt.tag_id NOT IN (${popular_tags})";
        }

        if ($type) {
            $type = (int)$type;
            $sql = "SELECT
                (SELECT name FROM tags WHERE id = pt.tag_id) AS tag, SUM(v.score) AS sum
                FROM posts_tags pt, tags t, post_votes v
                WHERE v.user_id = {$this->id}
                AND v.post_id = pt.post_id
                AND pt.tag_id = t.id
                {$popular_tags}
                AND t.tag_type = {$type}
                GROUP BY pt.tag_id
                ORDER BY sum DESC
                LIMIT 6
            ";
        } else {
            $sql = "SELECT
                (SELECT name FROM tags WHERE id = pt.tag_id) AS tag, SUM(v.score) AS sum
                FROM posts_tags pt, post_votes v
                WHERE v.user_id = {$this->id}
                AND v.post_id = pt.post_id
                ${popular_tags}
                GROUP BY pt.tag_id
                ORDER BY sum DESC
                LIMIT 6
            ";
        }

        $favorite_tags = self::connection()->select($sql);

        Rails::cache()->write("favorite_tags/" . $this->id . "/" . $type, $favorite_tags, ['expires_in' => '1 day']);

        return $favorite_tags;
    }
    # }

    # UserPostMethods {
    public function recent_uploaded_posts()
    {
        $posts = Post::findBySql("SELECT p.* FROM posts p WHERE p.user_id = {$this->id} AND p.status <> 'deleted' ORDER BY p.id DESC LIMIT 6");
        return $posts ?: new Rails\ActiveRecord\Collection();
    }

    public function recent_favorite_posts()
    {
        return Post::findBySql('SELECT p.* FROM posts p JOIN post_votes pv ON p.id = pv.post_id WHERE pv.user_id = ' . $this->id . ' AND pv.score = 3 ORDER BY pv.updated_at DESC LIMIT 6');
    }

    public function favorite_post_count($options = array())
    {
        return self::connection()->selectValue("SELECT COUNT(*) FROM post_votes v WHERE v.user_id = {$this->id} AND v.score = 3");
    }

    public function post_count()
    {
        if (!$this->post_count)
            $this->post_count = Post::where("user_id = ? AND status = 'active'", $this->id)->count();
        return $this->post_count;
    }

    public function held_post_count()
    {
        $version = (int)Rails::cache()->read('$cache_version');
        $key = 'held-post-count/v=' . $version . '/u=' . $this->id;
        
        return Rails::cache()->fetch($key, function() {
            return Post::where(['user_id' => $this->id, 'is_held' => true])->where('status <> ?', 'deleted')->count();
        });
    }
    # }

    # UserLevelMethods {
    public function pretty_level()
    {
        return array_search($this->level, CONFIG()->user_levels);
    }

    protected function _set_role()
    {
        if (CONFIG()->enable_account_email_activation)
            $this->level = CONFIG()->user_levels["Unactivated"];
        else
            $this->level = CONFIG()->starting_level;

        $this->last_logged_in_at = date('Y-m-d H:i:s');
    }

    public function has_permission(Rails\ActiveRecord\Base $record, $foreign_key = 'user_id') 
    {
        return ($this->is_mod_or_higher() || $record->$foreign_key == $this->id);
    }

    # Return true if this user can change the specified attribute.
    #
    # If record is an ActiveRecord object, return;s true if the change is allowed to complete.
    #
    # If record is an ActiveRecord class (eg. Pool rather than an actual pool), return;s
    # false if the user would never be allowed to make this change for any instance of the
    # object, and so the option should not be presented.
    #
    # For example, can_change(Pool, :description) return;s true (unless the user level
    # is too low to change any pools), but can_change(Pool.find(1), :description) return;s
    # false if that specific pool is locked.
    #
    # attribute usually corresponds with an actual attribute in the class, but any value
    # can be used.
    public function can_change(Rails\ActiveRecord\Base $record, $attribute)
    {
        $method = "can_change_" . $attribute;
        if ($this->is_mod_or_higher())
            return true;
        elseif (method_exists($record, $method))
            return $record->$method($this);
        elseif (method_exists($record, 'can_change'))
            return $record->can_change($this, $attribute);
        else
            return true;
    }

    static public function get_user_level($level)
    {
        static $user_level = [];
        
        if (!$user_level) {
            foreach (CONFIG()->user_levels as $name => $value) {
                $normalized_name = strtolower(str_replace(' ', '_', $name));
                $user_level[$normalized_name] = $value;
            }
        }
        
        return $user_level[$level];
    }
    
    # Created to statically get level name for level id.
    static public function level_name($level_id)
    {
        return array_search($level_id, CONFIG()->user_levels);
    }
    
    public function can_see_posts()
    {
        return !CONFIG()->user_min_level_can_see_posts || $this->level >= CONFIG()->user_min_level_can_see_posts;
    }
    # }

    # module UserInviteMethods {
    public function invite($name, $level)
    {
        if ($this->invite_count <= 0) {
            throw new User_NoInvites();
        }

        if ((int)$level >= CONFIG()->user_levels["Contributor"])
            $level = CONFIG()->user_levels["Contributor"];

        $invitee = User::where(['name' => $name])->first();

        if (!$invitee) {
            throw new Rails\ActiveRecord\Exception\RecordNotFoundException();
        }
        
        if (UserRecord::where("user_id = ? AND is_positive = false AND reported_by IN (SELECT id FROM users WHERE level >= ?)", $invitee->id, CONFIG()->user_levels["Mod"])->exists() && !$this->is_admin()) {
            throw new User_HasNegativeRecord();
        }

        // transaction do
        if ($level == CONFIG()->user_levels["Contributor"]) {
            Post::where("user_id = ? AND status = 'pending'", $this->id)->take()->each(function($post) {
                $post->approve($id);
            });
        }
        $invitee->level = $level;
        $invitee->invited_by = $this->id;
        $invitee->save();
        # iTODO: add support for decrement!
        // decrement! :invite_count
        self::connection()->executeSql("UPDATE users SET invite_count = invite_count - 1 WHERE id = ".$this->id);
        $this->invite_count--;
        // end
    }
    # }

    # UserAvatarMethods {
    # post_id is being destroyed.  Clear avatar_post_ids for this post, so we won't use
    # avatars from this post.  We don't need to actually delete the image.
    static public function clear_avatars($post_id)
    {
        self::connection()->executeSql("UPDATE users SET avatar_post_id = NULL WHERE avatar_post_id = ?", $post_id);
    }

    public function avatar_url()
    {
        return CONFIG()->url_base . "/data/avatars/".$this->id.".jpg";
    }

    public function has_avatar()
    {
        return (bool)$this->avatar_post_id;
    }

    public function avatar_path()
    {
        return Rails::root() . "/public/data/avatars/" . $this->id . ".jpg";
    }

    public function set_avatar($params)
    {
        $post = Post::find($params['id']);
        if (!$post->can_be_seen_by($this)) {
            $this->errors()->add('access', "denied");
            return false;
        }
        
        if ($params['top'] < 0 or $params['top'] > 1 or
            $params['bottom'] < 0 or $params['bottom'] > 1 or
            $params['left'] < 0 or $params['left'] > 1 or
            $params['right'] < 0 or $params['right'] > 1 or
            $params['top'] >= $params['bottom'] or
            $params['left'] >= $params['right'])
        {
            $this->errors()->add('parameter', "error");
            return false;
        }

        $tempfile_path = Rails::root() . "/public/data/" . $this->id . ".avatar.jpg";
        
        $use_sample = $post->has_sample();
        if ($use_sample) {
            $image_path = $post->sample_path();
            $image_ext = "jpg";
            $size = $this->_reduce_and_crop($post->sample_width, $post->sample_height, $params);

            # If we're cropping from a very small region in the sample, use the full
            # image instead, to get a higher quality image.
            if (($size['crop_bottom'] - $size['crop_top'] < CONFIG()->avatar_max_height) or
                ($size['crop_right'] - $size['crop_left'] < CONFIG()->avatar_max_width))
                $use_sample = false;
        }

        if (!$use_sample) {
            $image_path = $post->file_path();
            $image_ext = $post->file_ext;
            $size = $this->_reduce_and_crop($post->width, $post->height, $params);
        }
        
        try {
            Moebooru\Resizer::resize($image_ext, $image_path, $tempfile_path, $size, 95);
        } catch (Moebooru\Exception\ResizeErrorException $x) {
            if (file_exists($tempfile_path))
                unlink($tempfile_path);

            $this->errors()->add("avatar", "couldn't be generated (" . $x->getMessage() . ")");
            return false;
        }
        
        rename($tempfile_path, $this->avatar_path());
        chmod($this->avatar_path(), 0775);
        
        $this->updateAttributes(array(
            'avatar_post_id' => $params['post_id'],
            'avatar_top' => $params['top'],
            'avatar_bottom' => $params['bottom'],
            'avatar_left' => $params['left'],
            'avatar_right' => $params['right'],
            'avatar_width' => $size['width'],
            'avatar_height' => $size['height'],
            'avatar_timestamp' => date('Y-m-d H:i:s')
        ));
        
        return true;
    }
    
    private function _reduce_and_crop($image_width, $image_height, $params)
    {
        $cropped_image_width = $image_width * ($params['right'] - $params['left']);
        $cropped_image_height = $image_height * ($params['bottom'] - $params['top']);

        $size = Moebooru\Resizer::reduce_to(
            ['width' => $cropped_image_width, 'height' => $cropped_image_height],
            ['width' => CONFIG()->avatar_max_width, 'height' => CONFIG()->avatar_max_height],
            1, true);
        $size['crop_top'] = $image_height * $params['top'];
        $size['crop_bottom'] = $image_height * $params['bottom'];
        $size['crop_left'] = $image_width * $params['left'];
        $size['crop_right'] = $image_width * $params['right'];
        return $size;
    }
    # }


    # UserTagSubscriptionMethods {
    // protected function tag_subscriptions_text_setter($text)
    // {
      // User.transaction do
        // tag_subscriptions.clear

        // text.scan(/\S+/).each do |new_tag_subscription|
          // tag_subscriptions.create('tag_query' => new_tag_subscription)        }
      // end
    // }

    // def tag_subscriptions_text
      // tag_subscriptions_text.map(&:tag_query).sort.join(" ")
    // end

    public function tag_subscription_posts($limit, $name = null)
    {
        return TagSubscription::find_posts($this->id, $name, $limit);
    }
    # }

    # UserLanguageMethods {
    protected function setSecondaryLanguageArray($langs)
    {
        $this->secondary_languages = $langs;
    }

    public function secondary_language_array()
    {
        if (!is_array($this->secondary_languages))
            $this->secondary_languages = explode(",", $this->secondary_languages);
        return $this->secondary_languages;
    }

    protected function _commit_secondary_languages()
    {
        if (!$this->secondary_languages)
            return;

        if (in_array("none", $this->secondary_languages))
            $this->secondary_languages = "";
        else
            $this->secondary_languages = implode(",", $this->secondary_languages);
    }
    # }

  // $this->salt = CONFIG()->password_salt

  // class << self
    // attr_accessor :salt
  // end

    # For compatibility with AnonymousUser class
    public function is_anonymous()
    {
        return !$this->level;
    }

    public function invited_by_name()
    {
        return self::find_name($this->invited_by);
    }

    public function similar_users()
    {
        # This uses a naive cosine distance formula that is very expensive to calculate.
        # TODO: look into alternatives, like SVD.
        $sql = "
            SELECT
                f0.user_id as user_id,
                COUNT(*) / (SELECT sqrt((SELECT COUNT(*) FROM post_votes WHERE user_id = f0.user_id) * (SELECT COUNT(*) FROM post_votes WHERE user_id = {$this->id}))) AS similarity
            FROM
                vote v0,
                vote v1,
                users u
            WHERE
                v0.post_id = v1.post_id
                AND v1.user_id = {$this->id}
                AND v0.user_id <> {$this->id}
                AND u.id = v0.user_id
            GROUP BY v0.user_id
            ORDER BY similarity DESC
            LIMIT 6
        ";

        return self::connection()->select($sql);
    }

    public function set_show_samples()
    {
        $this->show_samples = true;
    }

    static public function generate_sql($params)
    {
        $query = self::where('true');
        if (isset($params['name']) && (string)$params['name'] !== '') {
            $query->where("name LIKE ? ", "%" . str_replace(" ", "_", $params['name']) . "%");
        }

        if (!empty($params['level']) && $params['level'] != "any") {
            $query->where("level = ?", $params['level']);
        }
        
        if (!empty($params['id'])) {
            $query->where("id = ?", $params['id']);
        }
        
        !isset($params['order']) && $params['order'] = false;
        
        switch ($params['order']) {
            case "name":
                $query->order("lower(name)");
                break;
            case "posts":
                $query->order("(SELECT count(*) FROM posts WHERE user_id = users.id) DESC");
                break;
            case "favorites":
                $query->order("(SELECT count(*) FROM favorites WHERE user_id = users.id) DESC");
                break;
            case "notes":
                $query->order("(SELECT count(*) FROM note_versions WHERE user_id = users.id) DESC");
                break;
            default:
                $query->order("id DESC");
                break;       
        }
        
        return $query;
    }

    protected function associations()
    {
        return array(
            'has_one' => array(
                'ban' => array('foreign_key' => 'user_id'),
                'user_blacklisted_tag' => ['class_name' => 'UserBlacklistedTag']
            ),
            'belongs_to' => array(
                'avatar_post' => array('class_name' => "Post", 'foreign_key' => 'avatar_post_id')
            ),
            'has_many' => array(
                'post_votes' => ['class_name' => 'PostVote'],
                'user_logs' => ['class_name' => 'UserLog'],
                // 'user_blacklisted_tags' => array('dependent' => 'delete_all'),
                'tag_subscriptions' => array(function() { $this->order('name'); }, 'dependent' => 'delete_all', 'class_name' => 'TagSubscription')
            )
        );
    }
    
    protected function _can_signup()
    {
        if (!CONFIG()->enable_signups) {
            $this->errors()->add('signups', 'are disabled');
            return false;
        }
    }
    
    protected function callbacks()
    {
        $before_create = array('_set_role');
        if (CONFIG()->show_samples)
            $before_create[] = '_set_show_samples';
        
        return array(
            'before_validation_on_create' => ['_can_signup'],
            'before_create'     => $before_create,
            'before_save'       => array('_encrypt_password'),
            'before_validation' => array('_commit_secondary_languages'),
            'after_save'        => array('_commit_blacklists', 'update_cached_name'),
            'after_create'      => array('_set_default_blacklisted_tags', '_increment_count'),
            'after_destroy'     => array('_decrement_count')
        );
    }
    
    protected function validations()
    {
        $validations = array(
            'name' => array(
                'length'     => ['in' => [2, 20], 'on' => 'create'],
                'format'     => array('with' => '/\A[^\s;,]+\Z/', 'on' => 'create', 'message' => 'cannot have whitespace, commas, or semicolons'),
                'uniqueness' => array(true, 'on' => 'create')
            ),
            'password' => array(
                'length'       => array('minimum' => 5, 'if' => array('property_exists' => 'password')),
                'confirmation' => true
            ),
            'language' => array(
                'format' => ['with' => '/^([a-z\-]+)|$/']
            ),
            'secondary_languages' => array(
                'format' => ['with' => '/^([a-z\-]+(,[a-z\0]+)*)?$/']
            ),
            
            # Changing password requires current password.
            'validate_current_password'
        );
        
        if (CONFIG()->enable_account_email_activation) {
            $validations['email'] = array(
                'presence' => array(true, 'on' => 'create', 'if' => array('property_exists' => 'email'))
            );
        }
        return $validations;
    }
    
    protected function attrProtected()
    {
        return ['level', 'invite_count'];
    }
    
    private function parse_is_level_or($method)
    {
        list($name, $operator) = explode('_or_', substr($method, 3));
        $name = ucfirst($name);
        $levels = CONFIG()->user_levels;
        
        if (!isset($levels[$name]))
            throw new InvalidArgumentException("User level name not found for " . $method);
        $level = $levels[$name];
        
        if ($operator == 'higher') {
            # For anonymous users
            if (!$this->id) {
                return false;
            } else {
                return $this->level >= $level;
            }
        } elseif ($operator == 'lower') {
            # For anonymous users
            if (!$this->id) {
                return true;
            } else {
                return $this->level <= $level;
            }
        } else {
            throw new InvalidArgumentException("Invalid user level operator " . $operator);
        }
    }
    
    private function parse_is_level($method)
    {
        # For anonymous users
        if (!$this->id) {
            return false;
        }
        
        $level_name = ucfirst(substr($method, 3));
        $levels = CONFIG()->user_levels;
        if (!isset($levels[$level_name])) {
            throw new InvalidArgumentException("User level name not found for " . $method);
        }
        return $this->level == $levels[$level_name];
    }
}
