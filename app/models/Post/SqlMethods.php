<?php
trait PostSqlMethods
{
    static public function find_by_tag_join($tag, array $options = array())
    {
        $options = array_merge(array(
            'order' => "posts.id DESC",
            'limit' => null,
            'offset' => null
        ), $options);
        
        $tag = strtolower(str_replace(' ', '_', $tag));
        $posts = self::where("tags.name = ?", $tag)->select("posts.*")->joins("JOIN posts_tags ON posts_tags.post_id = posts.id JOIN tags ON tags.id = posts_tags.tag_id")->limit($options['limit'])->offset($options['offset'])->order($options['order'])->take();
        
        if (!$posts)
            $posts = new ModelCollection(array());
        
        return $posts;
    }

    static public function generate_sql_range_helper($arr, $field, &$c, &$params = null)
    {
        if (!is_array($arr) || !isset($arr[0])) {
            return;
        }

        $query_passed = is_object($c);
        
        switch ($arr[0]) {
            case 'eq':
                if ($query_passed)
                    $c->where($field." = ?", $arr[1]);
                else {
                    $c[] = $field." = ?";
                    $params[] = $arr[1];
                }
                break;

            case 'gt':
                if ($query_passed)
                    $c->where($field." > ?", $arr[1]);
                else {
                    $c[] = $field." > ?";
                    $params[] = $arr[1];
                }
                break;
        
            case 'gte':
                if ($query_passed)
                    $c->where($field." >= ?", $arr[1]);
                else {
                    $c[] = $field." >= ?";
                    $params[] = $arr[1];
                }
                break;

            case 'lt':
                if ($query_passed)
                    $c->where($field." < ?", $arr[1]);
                else {
                    $c[] = $field." < ?";
                    $params[] = $arr[1];
                }
                break;

            case 'lte':
                if ($query_passed)
                    $c->where($field." <= ?", $arr[1]);
                else {
                    $c[] = $field." <= ?";
                    $params[] = $arr[1];
                }
                break;

            case 'between':
                if ($query_passed)
                    $c->where($field." BETWEEN ? AND ?", $arr[1], $arr[2]);
                else {
                    $c[] = $field." BETWEEN ? AND ?";
                    $params[] = $arr[1];
                    $params[] = $arr[2];
                }
                break;

            case 'in':
                if ($query_passed)
                    $c->where($field." IN (".implode(', ', array_fill(0, count($arr[1]), '?')).")", current($arr[1]));
                else {
                    $c[] = $field." IN (".implode(', ', array_fill(0, count($arr[1]), '?')).")";
                    $params[] = current($arr[1]);
                }
                break;
        }
    }

    static public function generate_sql_escape_helper($array)
    {
        return array_filter($array);
    }
    // def generate_sql_escape_helper(array)
      // array.map do |token|
        // next if token.empty?
        // escaped_token = token.gsub(/\\|'/, '\0\0\0\0').gsub("?", "\\\\77").gsub("%", "\\\\45")
        // "''" + escaped_token + "''"
      // end.compact
    // end

    static public function generate_sql($q, $options = array())
    {
        if (is_array($q)) {
            $original_query = isset($options['original_query']) ? $options['original_query'] : null;
        } else {
            $original_query = $q;
            $q = Tag::parse_query($q);
        }
        
        # Filling default values.
        $q = array_merge(array_fill_keys(array('md5', 'ext', 'source', 'fav', 'user', 'subscriptions', 'rating', 'rating_negated', 'unlocked_rating', 'show_holds', 'shown_in_index', 'exclude', 'related', 'post_id', 'mpixels', 'width', 'height', 'score', 'date', 'change'), null), $q);
        $options = array_merge(array_fill_keys(array('pending', 'flagged', 'from_api', 'limit', 'offset', 'count', 'select', 'having'), null), $options);
        
        $conds = array('true');
        
        $joins = array(
            'posts p',
        );
        $join_params = array();
        $cond_params = array();
        
        if (!empty($q['error']))
            $conds[] = "FALSE";

        self::generate_sql_range_helper($q['post_id'], "p.id", $conds, $cond_params);
        self::generate_sql_range_helper($q['mpixels'], "p.width*p.height/1000000.0", $conds, $cond_params);
        self::generate_sql_range_helper($q['width'],   "p.width", $conds, $cond_params);
        self::generate_sql_range_helper($q['height'],  "p.height", $conds, $cond_params);
        self::generate_sql_range_helper($q['score'],   "p.score", $conds, $cond_params);
        self::generate_sql_range_helper($q['date'],    "DATE(p.created_at)", $conds, $cond_params);
        self::generate_sql_range_helper($q['change'],  "p.change_seq", $conds, $cond_params);

        if (is_string($q['md5'])) {
            $conds[] = "p.md5 IN (?)";
            $cond_params[] = explode(',', $q['md5']);
        }
    
        if (is_string($q['ext'])) {
            $conds[] = "p.file_ext IN (?)";
            $cond_params[] = explode(',', strtolower($q['ext']));
        }
    
        if (isset($q['show_deleted_only'])) {
            if ($q['show_deleted_only'])
                $conds[] = "p.status = 'deleted'";
        } elseif (empty($q['post_id'])) {
            # If a specific post_id isn't specified, default to filtering deleted posts.
            $conds[] = "p.status <> 'deleted'";
        }

        if (isset($q['parent_id']) && is_numeric($q['parent_id'])) {
            $conds[] = "(p.parent_id = ? or p.id = ?)";
            $cond_params[] = $q['parent_id'];
            $cond_params[] = $q['parent_id'];
        } elseif (isset($q['parent_id']) && $q['parent_id'] == false) {
            $conds[] = "p.parent_id is null";
        }

        if (is_string($q['source'])) {
            $conds[] = "lower(p.source) LIKE lower(?)";
            $cond_params[] = $q['source'];
        }

        if (is_string($q['subscriptions'])) {
            $subscription_filter = trim($q['subscriptions']);
            $username = $subscription_filter;
            $subscription_name = null;

            if (strpos($subscription_filter, ':') !== false) {
                list($username, $subscription_name) = explode(':', $subscription_filter, 2);
            }

            $user = User::find_by_name($username);
            if (!$user) {
                $conds[] = "FALSE";
            } else {
                $post_ids = TagSubscription::find_post_ids($user->id, $subscription_name);
                if (!$post_ids) {
                    $conds[] = "FALSE";
                } else {
                    $conds[] = "p.id IN (?)";
                    $cond_params[] = $post_ids;
                }
            }
        }

        if (is_string($q['fav'])) {
            $joins[] = "JOIN favorites f ON f.post_id = p.id JOIN users fu ON f.user_id = fu.id";
            $conds[] = "lower(fu.name) = lower(?)";
            $cond_params[] = $q['fav'];
        }

        if (isset($q['vote_negated'])) {
            $joins[] = "LEFT JOIN post_votes v ON p.id = v.post_id AND v.user_id = ?";
            $join_params[] = $q['vote_negated'];
            $conds[] = "v.score IS NULL";
        }
        
        if (isset($q['vote'])) {
            $joins[] = "JOIN post_votes v ON p.id = v.post_id";
            // $conds[] = sprintf("v.user_id = %d", $q['vote'][1]);
            $conds[] = 'v.user_id = ?';
            $cond_params[] = $q['vote'][1];

            self::generate_sql_range_helper($q['vote'][0], "v.score", $conds, $cond_params);
        }

        if (is_string($q['user'])) {
            $joins[] = "JOIN users u ON p.user_id = u.id";
            $conds[] = "lower(u.name) = lower(?)";
            $cond_params[] = $q['user'];
        }

        if (isset($q['exclude_pools'])) {
            foreach (array_keys($q['exclude_pools']) as $i) {
                if (is_int($q['exclude_pools'][$i])) {
                    $joins[] = "LEFT JOIN pools_posts ep${i} ON (ep${i}.post_id = p.id AND ep${i}.pool_id = ?)";
                    $join_params[] = $q['exclude_pools'][$i];
                    $conds[] = "ep${i}.id IS NULL";
                }

                if (is_string($q['exclude_pools'][$i])) {
                    $joins[] = "LEFT JOIN pools_posts ep${i} ON ep${i}.post_id = p.id LEFT JOIN pools epp${i} ON (ep${i}.pool_id = epp${i}.id AND LOWER(epp${i}.name) LIKE ?)";
                    $join_params[] = "%".strtolower($q['exclude_pools'][$i])."%";
                    $conds[] = "ep${i}.id IS NULL";
                }
            }
        }
        
        if (isset($q['pool'])) {
            $conds[] = "pools_posts.active = true";

            if (!isset($q['order']))
                $paramsool_ordering = " ORDER BY pools_posts.pool_id ASC, CAST(pools_posts.sequence AS UNSIGNED), pools_posts.post_id";

            if (is_int($q['pool'])) {
                $joins[] = "JOIN pools_posts ON pools_posts.post_id = p.id JOIN pools ON pools_posts.pool_id = pools.id";
                $conds[] = "pools.id = ".$q['pool'];
            }

            if (is_string($q['pool'])) {
                if ($q['pool'] == "*")
                    $joins[] = "JOIN pools_posts ON pools_posts.post_id = p.id JOIN pools ON pools_posts.pool_id = pools.id";
                else {
                    $joins[] = "JOIN pools_posts ON pools_posts.post_id = p.id JOIN pools ON pools_posts.pool_id = pools.id";
                    $conds[] = "LOWER(pools.name) LIKE ?";
                    $cond_params[] = "%".strtolower($q['pool'])."%";
                }
            }
        }
        
        # http://stackoverflow.com/questions/8106547/how-to-search-on-mysql-using-joins/8107017
        $tags_index_query = array();

        if (!empty($q['include']) && ($tags_include = self::generate_sql_escape_helper($q['include']))) {
            $joins[] = 'INNER JOIN posts_tags pti ON p.id = pti.post_id JOIN tags ti ON pti.tag_id = ti.id';
            $tags_index_query[] = 'ti.name IN ('.implode(', ', array_fill(0, count($tags_include), '?')).')';
            $cond_params = array_merge($cond_params, $tags_include);
        }
        
        if (!empty($q['related'])) {
            if (count($q['exclude']) > CONFIG()->tag_query_limit) {
                throw new Exception("You cannot search for more than ".CONFIG()->tag_query_limit." tags at a time");
            }
            
            $tags_index_query[] = '('.implode(', ', array_map(function($v, $k){return 't'.($k+1).'.name';}, $q['related'], array_keys($q['related']))).') = ('.implode(', ', array_fill(0, count($q['related']), '?')).')';
            
            $cond_params = array_merge($cond_params, $q['related']);
            
            $joins[] = implode(' ', array_map(function($k){return 'INNER JOIN posts_tags pt'.($k+1).' ON p.id = pt'.($k+1).'.post_id INNER JOIN tags t'.($k+1).' ON pt'.($k+1).'.tag_id = t'.($k+1).'.id';}, array_keys($q['related'])));
        }

        if (!empty($q['exclude'])) {
            if (count($q['exclude']) > CONFIG()->tag_query_limit) {
                throw new Exception("You cannot search for more than ".CONFIG()->tag_query_limit." tags at a time");
            }
            
            $tags_index_query[] = 'NOT EXISTS
            (SELECT *
                FROM posts_tags pt
                    INNER JOIN tags t ON pt.tag_id = t.id
                WHERE p.id = pt.post_id 
                    AND t.name IN ('.implode(', ', array_fill(0, count($q['exclude']), '?')).')
            )';
            $cond_params = array_merge($cond_params, $q['exclude']);
        }
        
        if (!empty($tags_index_query)) {
            $conds[] = implode(' AND ', $tags_index_query);
        }

        if (is_string($q['rating'])) {
            $r = strtolower(substr($q['rating'], 0, 1));
            if ($r == "s")
                $conds[] = "p.rating = 's'";
            elseif ($r == "q")
                $conds[] = "p.rating = 'q'";
            elseif ($r == "e")
                $conds[] = "p.rating = 'e'";
        }

        if (is_string($q['rating_negated'])) {
            $r = strtolower(substr($q['rating_negated'], 0, 1));
            if ($r == "s")
                $conds[] = "p.rating <> 's'";
            elseif ($r == "q")
                $conds[] = "p.rating <> 'q'";
            elseif ($r == "e")
                $conds[] = "p.rating <> 'e'";
        }

        if ($q['unlocked_rating'] == true)
            $conds[] = "p.is_rating_locked = FALSE";
    
        if (isset($options['flagged']))
            $conds[] = "p.status = 'flagged'";

        if (isset($q['show_holds'])) {
            if( $q['show_holds'] == 'only')
                $conds[] = "p.is_held";
            elseif ($q['show_holds'] == 'hide')
                $conds[] = "NOT p.is_held";
            elseif ($q['show_holds'] == 'yes') {/*do nothing?*/}
            
        } else {
            # Hide held posts by default only when not using the API.
            if (!$options['from_api'])
                $conds[] = "NOT p.is_held";
        }

        /**
         * MyImouto: Moved the following condition here so only one
         * of the conditions that set the pending status is met.
         * Before this, in post#moderate, when searching for a user's
         * pending posts, the SQL query would end up like this:
         * ... AND p.status = 'pending' ... AND p.status <> 'pending' ...
         */
        if (isset($options['pending']))
            $conds[] = "p.status = 'pending'";
        else {
            if (isset($q['show_pending'])) {
                if ($q['show_pending'] == 'only')
                    $conds[] = "p.status = 'pending'";
                elseif ($q['show_pending'] == 'hide')
                    $conds[] = "p.status <> 'pending'";
                elseif ($q['show_pending'] == 'yes') {/*do nothing?*/}
            } else {
                # Hide pending posts by default only when not using the API.
                if (CONFIG()->hide_pending_posts && !isset($options['from_api']))
                    $conds[] = "p.status <> 'pending'";
            }
        }

        if (isset($q['shown_in_index'])) {
            if ($q['shown_in_index'])
                $conds[] = "p.is_shown_in_index";
            else
                $conds[] = "NOT p.is_shown_in_index";
        } elseif (!$original_query && !$options['from_api']) {
            # Hide not shown posts by default only when not using the API.
            $conds[] = "p.is_shown_in_index";
        }
        
        $sql = "SELECT ";
        
        if ($options['count'])
            $sql .= " COUNT(*)";
        elseif ($options['select'])
            $sql .= ' '.$options['select'];
        else
            $sql .= " p.*";

        $sql .= " FROM ".implode(' ', $joins);
        $sql .= " WHERE ".implode(' AND ', $conds);
        
        if (!$options['count'])
            $sql .= ' GROUP BY p.id ';
        
        if (isset($q['order']) && !$options['count']) {
            if ($q['order'] == "id")
                $sql .= " ORDER BY p.id";
            
            elseif ($q['order'] == "id_desc")
                $sql .= " ORDER BY p.id DESC";
            
            elseif ($q['order'] == "score")
                $sql .= " ORDER BY p.score DESC";
            
            elseif ($q['order'] == "score_asc")
                $sql .= " ORDER BY p.score";
            
            elseif ($q['order'] == "mpixels")
                # Use "w*h/1000000", even though "w*h" would give the same result, so this can use
                # the posts_mpixels index.
                $sql .= " ORDER BY width*height/1000000.0 DESC";

            elseif ($q['order'] == "mpixels_asc")
                $sql .= " ORDER BY width*height/1000000.0";

            elseif ($q['order'] == "portrait")
                $sql .= " ORDER BY 1.0*width/GREATEST(1, height)";

            elseif ($q['order'] == "landscape")
                $sql .= " ORDER BY 1.0*width/GREATEST(1, height) DESC";

            elseif ($q['order'] == "portrait_pool") {
                # We can only do this if we're searching for a pool.
                if (isset($q['pool']))
                    $sql .= " ORDER BY 1.0*width / GREATEST(1, height), CAST(pools_posts.sequence AS UNSIGNED), pools_posts.post_id";

            } elseif ($q['order'] == "change" || $q['order'] == "change_asc")
                $sql .= " ORDER BY change_seq";

            elseif ($q['order'] == "change_desc")
                $sql .= " ORDER BY change_seq DESC";

            elseif ($q['order'] == "vote") {
                if (isset($q['vote']))
                    $sql .= " ORDER BY v.updated_at DESC";

            } elseif ($q['order'] == "fav") {
                if (is_string($q['fav']))
                    $sql .= " ORDER BY f.id DESC";

            } elseif ($q['order'] == "random")
                $sql .= " ORDER BY RAND()";

            else
                $use_default_order = true;
        } else {
            $use_default_order = true;
        }

        if (isset($use_default_order) && !$options['count']) {
            if (isset($paramsool_ordering))
                $sql .= $paramsool_ordering;
            else {
                if (!empty($options['from_api'])) {
                    # When using the API, default to sorting by ID.
                    $sql .= " ORDER BY p.id DESC";
                } else {
                    # MI: Added p.id DESC so posts with same index_timestamp are ordered by id.
                    $sql .= " ORDER BY p.index_timestamp DESC, p.id DESC";
                }
            }
        }
        
        if (isset($options['limit']) && isset($options['offset']))
            $sql .= ' LIMIT ' . $options['offset'] . ', ' . $options['limit'];
        elseif (isset($options['limit']))
            $sql .= " LIMIT ".$options['limit'];
        
        $params = array_merge($join_params, $cond_params);
        
        return array($sql, $params);
    }
}
