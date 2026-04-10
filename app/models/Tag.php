<?php
foreach (glob(dirname(__FILE__).'/Tag/*.php') as $trait) require $trait;

class Tag extends Rails\ActiveRecord\Base
{
    use TagTypeMethods, TagApiMethods, Tag\CacheMethods;
    
    use Moebooru\Versioning\VersioningTrait;
    
    public $type_name;
    
    static public function init_versioning($v)
    {
        $v->versioned_attributes([
                'tag_type',
                'is_ambiguous' => ['default' => false]
            ])
            ->versioning_group_by(['action' => 'edit']);
    }
    
    static public function find_or_create_by_name($name)
    {
        # Reserve ` as a field separator for tag/summary.
        $name = str_replace(array(' ', '`'), array('_', ''), ltrim((strtolower($name)), '-~'));
        
        $ambiguous = false;
        $tag_type = CONFIG()->tag_types['General'];
        
        if (strpos($name, 'ambiguous:') === 0) {
            $name = str_replace('ambiguous:', '', $name);
            $ambiguous = true;
        }
        
        if (is_int(strpos($name, ':'))) {
            list($type, $tag_name) = explode(':', $name);
            
            if (isset(CONFIG()->tag_types[$type])) {
                $tag_type = CONFIG()->tag_types[$type];
                $name = $tag_name;
            }
        }
        
        $tag = Tag::where(['name' => $name])->first();
        
        if ($tag) {
            if ($tag_type)
                $tag->updateAttribute('tag_type', $tag_type);
            if ($ambiguous)
                $tag->updateAttribute('is_ambiguous', $ambiguous);
            
            return $tag;
        } else {
            return self::create(array('name' => $name, 'tag_type' => $tag_type, 'is_ambiguous' => $ambiguous));
        }
    }
    
    /* } Parse methods { */
    static public function scan_query($query)
    {
        return array_unique(array_filter(explode(' ', strtolower($query))));
    }
    
    static public function scan_tags($tags)
    {
        return array_unique(explode(' ', str_replace(array('%', ','), '', strtolower($tags))));
    }
    
    static public function select_ambiguous($tags)
    {
        if ($tags)
            return self::connection()->selectValues("SELECT name FROM tags WHERE name IN (?) AND is_ambiguous = TRUE ORDER BY name", $tags);
    }
    
    static public function find_suggestions($query)
    {
        if (substr_count($query, '_') === 1)
            # Contains only one underscore
            $search_for = implode('_', array_reverse(explode('_', $query)));
        else
            $search_for = "%" . $query . "%";
        
        $tags = Tag::where("name LIKE ? AND name <> ?", $search_for, $query)->order("post_count DESC")->limit(6)->select("name")->pluck('name');
        sort($tags);
        return $tags;
    }
    
    static public function parse_cast($x, $type)
    {
        if ($type == 'int') {
            $val = (int)$x;
            return max(0, min($val, PHP_INT_MAX));
        } elseif ($type == 'float') {
            return (float)$x;
        } elseif ($type == 'date') {
            return $x;
        }
    }
    
    static public function parse_helper($range, $type = 'int')
    {
        # "1", "0.5", "5.", ".5":
        # (-?(\d+(\.\d*)?|\d*\.\d+))
        // case range
        if (preg_match('/^(.+?)\.\.(.+)/', $range, $m))
            return array('between', self::parse_cast($m[1], $type), self::parse_cast($m[2], $type));

        elseif (preg_match('/^<=(.+)|^\.\.(.+)/', $range, $m))
            return array('lte', self::parse_cast($m[1], $type));

        elseif (preg_match('/^<(.+)/', $range, $m))
            return array('lt', self::parse_cast($m[1], $type));

        elseif (preg_match('/^>=(.+)|^(.+)\.\.$/', $range, $m))
            return array('gte', self::parse_cast($m[1], $type));

        elseif (preg_match('/^>(.+)/', $range, $m))
            return array('gt', self::parse_cast($m[1], $type));

        elseif (preg_match('/^(.+?)|(.+)/', $range, $m)) {
            $items = explode(',', $range);
            foreach ($items as &$val) {
                $val = self::parse_cast($val, $type);
                unset($val);
            }
            return array('in', $items);

        } else
            return array('eq', self::parse_cast($range, $type));
    }
    
    static public function parse_query($query, $options = array())
    {
        $q = array('include' => array(), 'exclude' => array(), 'related' => array());

        foreach (self::scan_query($query) as $token) {
            if (preg_match('/^([qse])$/', $token, $m)) {
                $q['rating'] = $m[1];
                continue;
            }
            
            if (preg_match('/^(unlocked|deleted|ext|user|sub|vote|-vote|fav|md5|-rating|rating|width|height|mpixels|score|source|id|date|pool|-pool|parent|order|change|holds|pending|shown|limit):(.+)$/', $token, $m))
            {
                if ($m[1] == "user")
                    $q['user'] = $m[2];
                elseif ($m[1] == "vote") {
                    list($vote, $user) = explode(':', $m[2]);
                    if ($user = User::where(['name' => $user])->first())
                        $user_id = $user->id;
                    else
                        $user_id = null;
                    $q['vote'] = array(self::parse_helper($vote), $user_id);
                } elseif ($m[1] == "-vote") {
                
                    if ($user = User::where(['name' => $m[2]])->first())
                        $user_id = $user->id;
                    else
                        $user_id = null;
                    $q['vote_negated'] = $user_id;
                    // $q['vote_negated'] = User.find_by_name_nocase($m[2]).id rescue nil
                    if (!$q['vote_negated'])
                        $q['error'] = "no user named ".$m[2];
                } elseif ($m[1] == "fav") {
                    if (strtolower($m[2]) === 'me') {
                        if (!current_user()->is_anonymous()) {
                            $q['fav'] = current_user()->name;
                        } else {
                            $q['error'] = "must be logged in to use fav:me";
                        }
                    } else {
                        $q['fav'] = $m[2];
                    }
                }
                elseif ($m[1] == "sub")
                    $q['subscriptions'] = $m[2];
                elseif ($m[1] == "md5")
                    $q['md5'] = $m[2];
                elseif ($m[1] == "-rating")
                    $q['rating_negated'] = $m[2];
                elseif ($m[1] == "rating")
                    $q['rating'] = $m[2];
                elseif ($m[1] == "id")
                    $q['post_id'] = self::parse_helper($m[2]);
                elseif ($m[1] == "width")
                    $q['width'] = self::parse_helper($m[2]);
                elseif ($m[1] == "height")
                    $q['height'] = self::parse_helper($m[2]);
                elseif ($m[1] == "mpixels")
                    $q['mpixels'] = self::parse_helper($m[2], 'float');
                elseif ($m[1] == "score")
                    $q['score'] = self::parse_helper($m[2]);
                elseif ($m[1] == "source")
                    $q['source'] = $m[2].'%';
                elseif ($m[1] == "date")
                    $q['date'] = self::parse_helper($m[2], 'date');
                elseif ($m[1] == "pool") {
                    $q['pool'] = $m[2];
                    if (preg_match('/^(\d+)$/', $q['pool']))
                        $q['pool'] = (int)$q['pool'];
                } elseif ($m[1] == "-pool") {
                    $pool = $m[2];
                    
                    if (preg_match('/^(\d+)$/', $pool))
                        $pool = (int)$pool;
                    
                    $q['exclude_pools'][] = $pool;
                } elseif ($m[1] == "parent")
                    $q['parent_id'] = $m[2] == "none" ? false : (int)$m[2];
                elseif ($m[1] == "order")
                    $q['order'] = $m[2];
                elseif ($m[1] == "unlocked")
                    $m[2] == "rating" && $q['unlocked_rating'] = true;
                elseif ($m[1] == "deleted") {
                    # This naming is slightly odd, to retain API compatibility with Danbooru's "deleted:true"
                    # search flag.
                    if ($m[2] == "true")
                        $q['show_deleted_only'] = true;
                    elseif ($m[2] == "all")
                        $q['show_deleted_only'] = false; # all posts, deleted or not
                } elseif ($m[1] == "ext")
                    $q['ext'] = $m[2];
                elseif ($m[1] == "change")
                    $q['change'] = self::parse_helper($m[2]);
                elseif ($m[1] == "shown")
                    $q['shown_in_index'] = ($m[2] == "true");
                elseif ($m[1] == "holds") {
                    if ($m[2] == "true" or $m[2] == "only")
                        $q['show_holds'] = 'only';
                    elseif ($m[2] == "all")
                        $q['show_holds'] = 'yes'; # all posts, held or not
                    elseif ($m[2] == "false")
                        $q['show_holds'] = 'hide';
                } elseif ($m[1] == "pending") {
                    if ($m[2] == "true" or $m[2] == "only")
                        $q['show_pending'] = 'only';
                    elseif ($m[2] == "all")
                        $q['show_pending'] = 'yes'; # all posts, pending or not
                    elseif ($m[2] == "false")
                        $q['show_pending'] = 'hide';
                } elseif ($m[1] == "limit")
                    $q['limit'] = $m[2];
            } elseif (substr($token, 0, 1) == '-' && strlen($token) > 1) {
                $q['exclude'][] = substr($token, 1);
            } elseif (substr($token, 0, 1) == '~' && strlen($token) > 1) {
                $q['include'][] = substr($token, 1);
            } elseif (is_int(strpos($token, '*'))) {
                $matches = Tag::where("name LIKE ?", str_replace('*', '%', $token))->select("name, post_count")->limit(25)->order("post_count DESC")->pluck('name');
                !$matches && $matches = array('~no_matches~');
                $q['include'] = array_merge($q['include'], $matches);
            } else
                $q['related'][] = $token;
        }
        
        if (empty($options['skip_aliasing'])) {
            $q['exclude'] && $q['exclude'] = TagAlias::to_aliased($q['exclude']);
            $q['include'] && $q['include'] = TagAlias::to_aliased($q['include']);
            $q['related'] && $q['related'] = TagAlias::to_aliased($q['related']);
        }
        
        return $q;
    }
    /* } { */
    
    static public function compact_tags($tags, $max_len)
    {
        if (strlen($tags) < $max_len)
            return $tags;

        $split_tags = explode(' ', $tags);

        # Put long tags first, so we don't remove every tag because of one very long one.
        usort($split_tags, function($a, $b) {
            $a_len = strlen($a);
            $b_len = strlen($b);
            if ($a_len == $b_len)
                return 0;
            return $a_len > $b_len ? 1 : -1;
        });

        # Tag types that we're allowed to remove:
        $length = strlen($tags);
        foreach (array_keys($split_tags) as $i) {
            $length -= strlen($split_tags[$i]) + 1;
            $split_tags[$i] = null;
            if ($length <= $max_len)
                break;
        }
        
        sort($split_tags);
        return trim(implode(' ', $split_tags));
    }
    
    static public function count_by_period($start, $stop, array $options = array())
    {
        $options['limit'] = !isset($options['limit']) || !ctype_digit((string)$options['limit']) ? 50 : (int)$options['limit'];
        !isset($options['exclude_types']) && $options['exclude_types'] = array();
        $sql = 'SELECT
           (SELECT name FROM tags WHERE id = pt.tag_id) AS name,
            COUNT(pt.tag_id) AS post_count
            FROM posts p, posts_tags pt, tags t
            WHERE p.created_at BETWEEN ? AND ? AND
                p.id = pt.post_id AND
                pt.tag_id = t.id AND
                t.tag_type IN (?)
            GROUP BY pt.tag_id
            ORDER BY post_count DESC
            LIMIT '.$options['limit'];
        
        $tag_types_to_show = array_diff(Tag::tag_type_indexes(), $options['exclude_types']);
        
        $counts = self::connection()->select($sql, $start, $stop, $tag_types_to_show);
        return $counts;
    }
    
    static public function type_name_helper($tag_name) # :nodoc:
    {
        $tag = Tag::where("name = ?", $tag_name)->select("tag_type")->first();

        if (!$tag)
            return "general";
        else
            return self::_type_map($tag->tag_type);
    }
    
    # Find the tag type name of a tag.
    #
    # === Parameters
    # * :tag_name<String>:: The tag name to search for
    static public function type_name($tag_name)
    {
        return Rails::cache()->fetch(['tag_type' => $tag_name], ['expires_in' => '1 day'], function() use ($tag_name) {
            return self::type_name_helper(str_replace(' ', '_', $tag_name));
        });
    }
    
    static public function tag_type_indexes()
    {
        $all = array_unique(array_values(CONFIG()->tag_types));
        sort($all);
        return $all;
    }
    
    static public function tag_list_order($tag_type)
    {
        switch ($tag_type) {
            case "artist"   : return 0;
            case "circle"   : return 1;
            case "copyright": return 2;
            case "character": return 3;
            case "general"  : return 5;
            case "faults"   : return 6;
            default         : return 4;
        }
    }
    
    static public function type_name_from_value($type_value)
    {
        $type = array_search($type_value, CONFIG()->tag_types);
        if ($type)
            return strtolower($type);
    }
    
    static public function batch_get_tag_types_for_posts($posts)
    {
        $tags = array();
        foreach ($posts as $post) {
            $tags = array_merge($tags, $post->tags());
        }
        return self::batch_get_tag_types($tags);
    }
    
    # Get all tag types for the given tags.
    static public function batch_get_tag_types($post_tags)
    {
        $post_tags_key = [];
        
        foreach ($post_tags as $t) {
            $post_tags_key[] = ['tag_type' => $t];
        }
        # Without this, the following splat will eat the last argument because
        # it'll be considered an option instead of key (being a hash).
        $post_tags_key[] = [];
        
        $results = [];
        $cached = call_user_func_array([Rails::cache(), 'readMulti'], $post_tags_key);
        foreach ($cached as $cache_key => $value) {
            # The if cache_key is required since there's small chance read_multi
            # returning nil key on certain key names.
            // if ($cache_key) {
                $results[substr($cache_key, 9)] = $value;
            // }
        }
        foreach( array_diff( $post_tags, array_keys($results) ) as $tag ) {
            $results[$tag] = self::type_name($tag);
        }
        return $results;
    }
    
    # Returns tags (with type specified by input) related by input tag
    # In array of hashes.
    # Hash in format { 'name' => tag_name, 'post_count' => tag_post_count }
    static public function calculate_related_by_type($tag, $type, $limit = 25)
    {
        return Rails::cache()->fetch(['category' => 'reltags_by_type', 'type' => $type, 'tag' => $tag], ['expires_in' => '1 hour'], function() use ($tag, $type, $limit) {
            
            $sql = "
                SELECT tags.name, tags.post_count
                FROM tags
                JOIN posts_tags pt ON tags.id = pt.tag_id
                JOIN posts ON pt.post_id = posts.id
                INNER JOIN posts_tags pti ON posts.id = pti.post_id JOIN tags ti ON pti.tag_id = ti.id
                WHERE
                  posts.status != 'deleted'
                  AND ti.name IN (?)
                  AND tags.tag_type = ?
                GROUP BY tags.name
                LIMIT " . (int)$limit;

            $rows = self::connection()->select($sql, $tag, $type);
            $reduced = [];
            foreach ($rows as $row) {
                $reduced[] = ['name' => $row['name'], 'post_count' => $row['post_count']];
            }
            return $reduced;
        });
    }
    
    static public function find_related($tags)
    {
        if (is_array($tags)) {
            if (count($tags) == 1)
                # Replace tags array into its first element
                # to be searched again later below.
                $tags = current($tags);
            else
                return self::calculate_related($tags);
        }
        
        if ($tags) {
            $t = self::where(['name' => $tags])->first();
            if ($t)
                return $t->related();
        }

        return [];
    }
    
    // static public function find_related($tags)
    // {
        // if (is_array($tags) && count($tags) > 1)
            // return self::calculate_related($tags);
        // elseif ($tags = current($tags)) {
            // $t = Tag::find_by_name($tags);
            // if ($t)
                // return $t->related();
        // }
        // return array();
    // }
    
    static public function calculate_related($tags)
    {
        if (!$tags)
            return array();
        
        !is_array($tags) && $tags = array($tags);
        
        return Rails::cache()->fetch(['category' => 'reltags', 'tags' => implode(',', $tags)], ['expires_in' => '1 hour'], function() use ($tags) {
            $from = array("posts_tags pt0");
            $cond = array("pt0.post_id = pt1.post_id");
            $sql = "SELECT ";

            # Ignore deleted posts in pt0, so the count excludes them.
            $cond[] = "(SELECT TRUE FROM posts p0 WHERE p0.id = pt0.post_id AND p0.status <> 'deleted')";

            foreach (range(1, count($tags)) as $i)
                $from[] = "posts_tags pt" . $i;
            if (count($tags) > 1) {
                foreach (range(2, count($tags)) as $i)
                    $cond[] = "pt1.post_id = pt".$i.".post_id";
            }
            foreach (range(1, count($tags)) as $i)
                $cond[] = "pt".$i.".tag_id = (SELECT id FROM tags WHERE name = ?)";

            $sql .= "(SELECT name FROM tags WHERE id = pt0.tag_id) AS tag, COUNT(*) AS tag_count";
            $sql .= " FROM " . implode(', ', $from);
            $sql .= " WHERE " . implode(' AND ', $cond);
            $sql .= " GROUP BY pt0.tag_id";
            $sql .= " ORDER BY tag_count DESC LIMIT 25";
            array_unshift($tags, $sql);
            $tags = self::connection()->query($tags);

            if (!$tags)
                return array();

            $calc = array();
            foreach ($tags as $tag)
                $calc[] = array($tag['tag'], $tag['tag_count']);

            return $calc;
        });
    }
    
    # Return the cache version of the summary.
    static public function get_summary_version()
    {
        return Rails::cache()->fetch('$tag_version', function() { return 0; });
    }
    
    # Create a compact list of all active tags, sorted by post_count.
    #
    # "1`tagme` 2`fixme` 3`fixed`alias` "
    #
    # Each tag is bounded by backticks, so "`tagme`" can be used to match a whole tag.
    #
    # This is returned as a preencoded JSON string, so the entire block can be cached.
    static public function get_json_summary()
    {
        $summary_version = self::get_summary_version();
        $key = 'tag_summary/' . $summary_version;
        
        $data = Rails::cache()->fetch($key, ['expires_in' => '1 hour'], function() {
            $data = Tag::get_json_summary_no_cache();
            return json_encode($data);
        });
        
        return $data;
    }
    
    static public function get_json_summary_no_cache()
    {
        $version = Tag::get_summary_version();

        $tags = self::connection()->select("SELECT
            t.id, t.name, t.tag_type, ta.name AS alias
            FROM tags t LEFT JOIN tag_aliases ta ON (t.id = ta.alias_id AND NOT ta.is_pending)
            WHERE t.post_count > 0
            ORDER BY t.id, ta.name");

        $tags_with_type = array();
        $current_tag = "";
        $last_tag_id = null;
        foreach($tags as $tag) {
            $id = $tag["id"];
            if ($id != $last_tag_id) {
                if (!empty($last_tag_id))
                    $tags_with_type[] = $current_tag;

                $last_tag_id = $id;
                $current_tag = $tag['tag_type'].'`'.$tag['name'].'`';
            }

            if (!empty($tag["alias"]))
                $current_tag .= $tag["alias"] . "`";
        }
        if (!empty($last_tag_id))
            $tags_with_type[] = $current_tag;

        $tags_string = implode(' ', $tags_with_type) . " ";
        return array('version' => $version, 'data' => $tags_string);
    }
    
    static public function purge_tags()
    {
        $condition = "post_count = 0 AND " .
            "id NOT IN (SELECT alias_id FROM tag_aliases UNION SELECT predicate_id FROM tag_implications UNION SELECT consequent_id FROM tag_implications)";
        foreach(Tag::where($condition)->take() as $tag) {
            $tag->destroy();
        }
    }
    
    static public function recalculate_post_count()
    {
        $sql = "UPDATE tags SET post_count = (SELECT COUNT(*) FROM posts_tags pt, posts p WHERE pt.tag_id = tags.id AND pt.post_id = p.id AND p.status <> 'deleted')";
        self::connection()->executeSql($sql);
    }
    
    static public function mass_edit($start_tags, $result_tags, $updater_id, $updater_ip_addr)
    {
        foreach (Post::find_by_tags($start_tags) as $p) {
            $start  = TagAlias::to_aliased(Tag::scan_tags($start_tags));
            $result = TagAlias::to_aliased(Tag::scan_tags($result_tags));
            $tags = array_merge(array_diff($p->tags(), $start), $result);
            $tags = implode(' ', $tags);
            $p->updateAttributes(array('updater_user_id' => $updater_id, 'updater_ip_addr' => $updater_ip_addr, 'tags' => $tags));
        }
    }
    
    static private function _type_map($tag_type)
    {
        foreach (CONFIG()->tag_types as $type_name => $id) {
            if ($id == $tag_type)
                return strtolower($type_name);
        }
    }

    public function related()
    {
        if (time() > $this->cached_related_expires_on) {
            $length = ceil($this->post_count / 3);
            $length < 12 && $length = 12;
            $length > 8760 && $length = 8760;

            self::connection()->executeSql("UPDATE tags SET cached_related = ?, cached_related_expires_on = ? WHERE id = ?", implode(",", Rails\Toolbox\ArrayTools::flatten($this->calculate_related($this->name))), date('Y-m-d H:i:s', strtotime('+'.$length.' hours')), $this->id);
            $this->reload();
        }

        $related = explode(',', $this->cached_related);
        $i = 0;
        $groups = array();
        foreach ($related as $rel) {
            $group[] = $rel;
            if ($i &1) {
                $groups[] = $group;
                $group = array();
            }
            $i++;
        }
        
        return $groups;
    }
    
    protected function init()
    {
        $this->tag_type     = (int)$this->tag_type;
        $this->type_name    = self::type_name_from_value($this->tag_type);
        $this->is_ambiguous = (bool)$this->is_ambiguous;
    }
    
    protected function validations()
    {
        return [
            'name' => [
                'length' => ['minimum' => 1]
            ]
        ];
    }
    
    protected function callbacks()
    {
        return [
            'after_save' => [
                'update_cache'
            ],
            'after_create' => [
                'update_cache_on_create'
            ]
        ];
    }
}
