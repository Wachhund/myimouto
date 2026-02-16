<?php
class PoolController extends ApplicationController
{
    protected function init()
    {
        $this->helper('Post');
    }
    
    protected function filters()
    {
        return [
            'before' => [
                'user_can_see_posts' => ['only' => ['zip']],
                'member_only' => ['only' => ['destroy', 'update', 'addPost', 'removePost', 'import', 'zip']],
                'post_member_only' => ['only' => ['create']],
                'contributor_only' => ['only' => ['copy', 'transferMetadata']]
            ]
        ];
    }

    public function index()
    {
        $this->set_title('Pools');
        
        $sql_query = Pool::none()->page($this->page_number())->perPage(CONFIG()->pool_index_default_limit);

        $order = $this->params()->order ?: 'id';

        $search_tokens = array();

        if ($this->params()->query) {
            $this->set_title($this->params()->query . " - Pools");
            // $query = array_map(function($v){return addslashes($v);}, explode($this->params()->query);
            // $query = Tokenize.tokenize_with_quotes($this->params[:query] || "")
            $query = explode(' ', addslashes($this->params()->query));

            foreach ($query as &$token) {
                if (preg_match('/^(order|limit|posts):(.+)$/', $token, $m)) {
                    if ($m[1] == "order") {
                        $order = $m[2];
                    } elseif ($m[1] == "limit") {
                        $sql_query->perPage(min((int)$m[2], 100));
                    } elseif ($m[1] == "posts") {
                        Post::generate_sql_range_helper(Tag::parse_helper($m[2]), "post_count", $sql_query);
                    }
                } else {
                    // # TODO: removing ^\w- from token.
                    // $token = preg_replace('~[^\w-]~', '', $token);
                    $search_tokens[] = $token;
                }
            }
        }

        if (!empty($search_tokens)) {
            // $value_index_query = QueryParser.escape_for_tsquery($search_tokens);
            $value_index_query = implode('_', $search_tokens);
            if ($value_index_query) {
                # If a search keyword contains spaces, then it was quoted in the search query
                # and we should only match adjacent words.    tsquery won't do this for us; we need
                # to filter results where the words aren't adjacent.
                #
                # This has a side-effect: any stopwords, stemming, parsing, etc. rules performed
                # by to_tsquery won't be done here.    We need to perform the same processing as
                # is used to generate search_index.    We don't perform all of the stemming rules, so
                # although "jump" may match "jumping", "jump beans" won't match "jumping beans" because
                # we'll filter it out.
                #
                # This also doesn't perform tokenization, so some obscure cases won't match perfectly;
                # for example, "abc def" will match "xxxabc def abc" when it probably shouldn't.    Doing
                # this more correctly requires Postgresql support that doesn't exist right now.
                foreach ($query as $q) {
                    # Don't do this if there are no spaces in the query, so we don't turn off tsquery
                    # parsing when we don't need to.
                    // if (!strstr($q, ' ')) continue;
                    $sql_query->where("(position(LOWER(?) IN LOWER(REPLACE(name, '_', ' '))) > 0 OR position(LOWER(?) IN LOWER(description)) > 0)",
                        $q,
                        $q);
                }
            }
        }

        if (empty($order))
            $order = empty($search_tokens) ? 'date' : 'name';

        switch ($order) {
            case "name":
                $sql_query->order("name asc");
                break;
            
            case "date":
                $sql_query->order("created_at desc");
            
            case "updated":
                $sql_query->order("updated_at desc");
                break;
            
            case "id":
                $sql_query->order("id desc");
                break;
            
            default:
                $sql_query->order("created_at desc");
                break;
        }

        $this->pools = $sql_query->paginate();

        $samples = [];
        foreach($this->pools as $p) {
            if (!$post = $p->get_sample())
                continue;
            $p_id = (string)$p->id;
            $samples[$p_id] = $post;
        }
        $this->samples = $samples;

        $this->respond_to_list('pools');
    }

    public function show()
    {
        if (isset($this->params()->samples) && $this->params()->samples == 0)
            unset($this->params()->samples);

        $this->pool = Pool::find($this->params()->id);

        $this->browse_mode = current_user()->pool_browse_mode;

        $q = [];
        $q['pool'] = (int)$this->params()->id;
        $q['show_deleted_only'] = false;
        if ($this->browse_mode == 1) {
            $q['limit'] = 1000;
            $q['order'] = "portrait_pool";
        } else {
            $q['limit'] = 24;
        }
        $page = (int)$this->page_number() > 0 ? (int)$this->page_number() : 1;
        $offset = ($page-1)*$q['limit'];
        
        list($sql, $params) = Post::generate_sql($q, array('from_api' => true, 'offset' => $offset, 'limit' => $q['limit']));
        
        # Stringify query so it can be passed to fast_count.
        $tag_query = [];
        foreach ($q as $tag => $value) {
            if ($tag == 'show_deleted_only') {
                $tag   = 'deleted';
                $value = 'all';
            }
            $tag_query[] = $tag . ':' . $value;
        }
        $count = Post::fast_count(implode(' ', $tag_query));
        
        $posts = Post::findBySql($sql, $params);
        $this->posts = new Rails\ActiveRecord\Collection($posts->members(), ['page' => $page, 'perPage' => $q['limit'], 'offset' => $offset, 'totalRows' => $count]);
        $this->set_title($this->pool->pretty_name());

        # iTODO:
        $this->respondTo([
            'html',
            // 'xml' => function() {
                // $builder = new Builder_XmlMarkup(['indent' => 2]);
                // $builder->instruct();

                // $xml = $this->pool->to_xml(['builder' => $builder, 'skip_instruct' => true], function() {
                    // $builder->posts(function() use ($builder) {
                        // foreach ($this->posts as $post)
                            // $post->to_xml(['builder' => $builder, 'skip_instruct' => true]);
                    // })
                // });
                // $this->render(['xml' => $xml]);
            // },
            'json' => function() {
                $this->render(['json' => json_encode(array_merge($this->pool->asJson(), ['posts' => $this->posts->asJson()]))]);
            }
        ]);
    }

    public function update()
    {
        $this->pool = Pool::find($this->params()->id);

        if (!$this->pool->can_be_updated_by(current_user())) {
            $this->access_denied();
            return;
        }

        if ($this->request()->isPost()) {
            $this->pool->updateAttributes($this->params()->pool);
            $this->respond_to_success("Pool updated", array(array('#show', 'id' => $this->params()->id)));
        }
    }

    public function create()
    {
        if ($this->request()->isPost()) {
            $pool = Pool::create(array_merge($this->params()->pool, array('user_id' => current_user()->id)));
            
            if ($pool->errors()->blank())
                $this->respond_to_success("Pool created", array(array('#show', 'id' => $pool->id)));
            else
                $this->respond_to_error($pool, "#index");
        }
    }

    public function copy()
    {
        $this->old_pool = Pool::find($this->params()->id);
        
        $name = $this->params()->name ?: $this->old_pool->name . ' (copy)';
        $this->new_pool = new Pool(['user_id' => $this->current_user->id, 'name' => $name, 'description' => $this->old_pool->description]);
        
        if ($this->request()->isPost()) {
            $this->new_pool->save();
            
            if ($this->new_pool->errors()->any()) {
                $this->respond_to_error($this->new_pool, ['#index']);
                return;
            }
            
            foreach ($this->old_pool->pool_posts as $pp) {
                $this->new_pool->add_post($pp->post_id, ['sequence' => $pp->sequence]);
            }
            
            $this->respond_to_success("Pool created", ['#show', 'id' => $this->new_pool->id]);
        }
    }

    public function destroy()
    {
        $this->pool = Pool::find($this->params()->id);

        if ($this->request()->isPost()) {
            if ($this->pool->can_be_updated_by(current_user())) {
                $this->pool->destroy();
                $this->respond_to_success("Pool deleted", "#index");
            } else
                $this->access_denied();
        }
    }

    public function addPost()
    {
        if ($this->request()->isPost()) {
            # iMod
            if ($this->request()->format() == 'json') {
                try {
                    $pool = Pool::find($this->params()->pool_id);
                } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
                    $this->render(['json' => ['reason' => 'Pool not found']]);
                    return;
                }
            } else
                $pool = Pool::find($this->params()->pool_id);
            
            $this->session()->last_pool_id = $pool->id;
            
            if (isset($this->params()->pool) && !empty($this->params()->pool['sequence']))
                $sequence = $this->params()->pool['sequence'];
            else
                $sequence = null;
            
            try {
                $pool->add_post($this->params()->post_id, array('sequence' => $sequence, 'user' => current_user()));
                $this->respond_to_success('Post added', array(array('post#show', 'id' => $this->params()->post_id)));
            } catch (Pool_PostAlreadyExistsError $e) {
                $this->respond_to_error("Post already exists", array('post#show', 'id' => $this->params()->post_id), array('status' => 423));
            } catch (Pool_AccessDeniedError $e) {
                $this->access_denied();
            } catch (Exception $e) {
                $this->respond_to_error(get_class($e), array('post#show', 'id' => $this->params()->post_id));
            }
        } else {
            if (current_user()->is_anonymous)
                $pools = Pool::where("is_active = TRUE AND is_public = TRUE")->order("name")->take();
            else
                $pools = Pool::where("is_active = TRUE AND (is_public = TRUE OR user_id = ?)", current_user()->id)->order("name")->take();
            
            $post = Post::find($this->params()->post_id);
        }
    }

    public function removePost()
    {
        $pool = Pool::find($this->params()->pool_id);
        $post = Post::find($this->params()->post_id);

        if ($this->request()->isPost()) {
            try {
                $pool->remove_post($this->params()->post_id, array('user' => current_user()));
            } catch (Exception $e) {
                if ($e->getMessage() == 'Access Denied')
                    $this->access_denied();
            }
            
            $api_data = Post::batch_api_data(array($post));

            $this->response()->headers()->add("X-Post-Id", $this->params()->post_id);
            $this->respond_to_success("Post removed", array('post#show', 'id' => $this->params()->post_id), array('api' => $api_data));
        }
    }

    public function order()
    {
        $this->pool = Pool::find($this->params()->id);

        if (!$this->pool->can_be_updated_by(current_user()))
            $this->access_denied();

        if ($this->request()->isPost()) {
            foreach ($this->params()->pool_post_sequence as $i => $seq)
                PoolPost::update($i, array('sequence' => $seq));
            
            $this->pool->reload();
            $this->pool->update_pool_links();
            
            $this->notice("Ordering updated");
            $this->redirectTo(array('#show', 'id' => $this->params()->id));
        } else
            $this->pool_posts = $this->pool->pool_posts;
    }

    public function select()
    {
        if (current_user()->is_anonymous())
            $this->pools = Pool::where("is_active = TRUE AND is_public = TRUE")->order("name")->take();
        else
            $this->pools = Pool::where("is_active = TRUE AND (is_public = TRUE OR user_id = ?)", current_user()->id)->order("name")->take();

        $options = array('(000) DO NOT ADD' => 0);

        foreach ($this->pools as $p) {
            $options[str_replace('_', ' ', $p->name)] = $p->id;
        }
        $this->options = $options;
        $this->last_pool_id = $this->session()->last_pool_id;
        $this->setLayout(false);
    }

    public function zip()
    {
        if (!CONFIG()->pool_zips)
            throw new Rails\ActiveRecord\Exception\RecordNotFoundException();
        
        $pool = Pool::find($this->params()->id);
        $files = $pool->get_zip_data($this->params()->all());
        
        $zip = new ZipStream($pool->pretty_name() . '.zip');
        
        foreach ($files as $file) {
            list($path, $filename) = $file;
            $zip->addLargeFile($path, $filename);
        }
        
        $zip->finalize();
        $this->render(['nothing' => true]);
    }

    public function transferMetadata()
    {
        $this->to = Pool::find($this->params()->to);
        
        if (!$this->params()->from) {
            $this->from = null;
            return;
        }
        
        $this->from = Pool::find($this->params()->from);
        
        $from_posts = $this->from->pool_posts;
        $to_posts = $this->to->pool_posts;
        
        if ($from_posts->size() == $to_posts->size()) {
            $this->truncated = false;
        } else {
            $this->truncated = true;
            $min_posts = min($from_posts->size(), $to_posts->size());
            $from_posts = $from_posts->slice(0, $min_posts);
            $to_posts = $to_posts->slice(0, $min_posts);
        }
        
        $this->posts = Post::emptyCollection();
        foreach ($from_posts as $k => $v) {
            $data = [];
            $from = $v->post;
            $to = $to_posts[$k]->post;
            $data['from'] = $from;
            $data['to'] = $to;
            
            $from_tags = $from->tags;
            $to_tags = $to->tags;
            
            $tags = $from_tags;
            
            if ($from->rating != $to->rating) {
                $tags[] = 'rating:' . $to->rating;
            }
            
            if ($from->is_shown_in_index != $to->is_shown_in_index) {
                $tags[] = $from->is_shown_in_index ? 'show' : 'hide';
            }
            
            if ($from->parent_id != $to->id) {
                $tags[] = 'child:' . $from->id;
            }
            
            $data['tags'] = implode(' ', $tags);
            
            $this->posts[] = $data;
        }
    }
}
