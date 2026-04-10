<?php

class PostController extends ApplicationController
{
    public function activate()
    {
        $ids = is_array($this->params()->post_ids) ? array_map(function ($id) {
            return (int) $id;
        }, $this->params()->post_ids) : [];
        $changed = Post::batch_activate(current_user()->is_mod_or_higher() ? null : current_user()->id, $ids);
        $this->respond_to_success("Posts activated", '#moderate', ['api' => ['count' => $changed]]);
    }

    public function uploadProblem() {}

    public function upload()
    {
        $this->set_title('Upload');

        $this->deleted_posts = FlaggedPostDetail::new_deleted_posts(current_user());
        #        if $this->params()->url
        #            $this->post = Post.find(:first, 'conditions' => ["source = ?", $this->params()->url])
        #        end
        $this->default_rating = CONFIG()->default_rating_upload ?: "q";
        if (empty($this->post)) {
            $this->post = new Post();
        }
    }

    public function create()
    {
        if (current_user()->is_member_or_lower() && Post::where("user_id = ? AND created_at > ? ", current_user()->id, date('Y-m-d H:i:s', strtotime('-1 day')))->count() >= CONFIG()->member_post_limit) {
            $this->respond_to_error("Daily limit exceeded", '#error', ['status' => 421]);
            return;
        }
        if (current_user()->is_privileged_or_higher()) {
            $status = "active";
        } else {
            $status = "pending";
        }

        if ($this->params()->anonymous == '1' and current_user()->is_contributor_or_higher()) {
            $user_id = null;
            // # FIXME: someone track down the user of Thread evilry here and nuke
            // #                it please?
            // $this->session()->delete('danbooru-user');
            // $this->session()->delete('danbooru-user_id');
            // $this->session->danbooru-ip_addr = $this->request()->remoteIp());
        } else {
            $user_id = current_user()->id;
        }

        // $is_upload = array_key_exists('post', $_FILES);

        # iTODO
        $post_params = array_merge($this->params()->post ?: [], [
            'updater_user_id' => current_user()->id,
            'updater_ip_addr' => $this->request()->remoteIp(),
            'user_id'         => current_user()->id,
            'ip_addr'         => $this->request()->remoteIp(),
            'status'          => $status,
            'tempfile_path'   => $_FILES['post']['tmp_name']['file'],
            'tempfile_name'   => $_FILES['post']['name']['file'],
            'is_import'       => false, # Make sure to keep this value false
            // 'tempfile_path'   => $is_upload ? $_FILES['post']['tmp_name']['file'] : null,
            // 'tempfile_name'   => $is_upload ? $_FILES['post']['name']['file'] : null,
            // 'is_upload'       => $is_upload,
        ]);

        $this->post = Post::create($post_params);

        if ($this->post->errors()->blank()) {
            if ($this->params()->md5 && $this->post->md5 != strtolower($this->params()->md5)) {
                $this->post->destroy();
                $this->respond_to_error("MD5 mismatch", '#error', ['status' => 420]);
            } else {
                $api_data = ['post_id' => $this->post->id, 'location' => $this->urlFor(['post#show', 'id' => $this->post->id])];
                if (CONFIG()->dupe_check_on_upload && $this->post->image() && !$this->post->parent_id) {
                    if ($this->params()->format == "xml" || $this->params()->format == "json") {
                        # iTODO
                        // $options = array('services' => SimilarImages::get_services("local"), 'type' => 'post', 'source' => $this->post);

                        // $res = SimilarImages::similar_images($options);
                        // if (!empty($res['posts'])( {
                        // $this->post->tags = $this->post->tags() . " possible_duplicate";
                        // $this->post->save();
                        // $api_data['has_similar_hits'] = true;
                        // }
                    }

                    $api_data['similar_location'] = $this->urlFor(['post#similar', 'id' => $this->post->id, 'initial' => 1]);
                    $this->respond_to_success("Post uploaded", ['post#similar', 'id' => $this->post->id, 'initial' => 1], ['api' => $api_data]);
                } else {
                    $this->respond_to_success("Post uploaded", ['post#show', 'id' => $this->post->id, 'tag_title' => $this->post->tag_title()], ['api' => $api_data]);
                }
            }
        } elseif ($this->post->errors()->on('md5')) {
            $p = Post::where(['md5' => $this->post->md5])->first();

            $update = ['tags' => $p->cached_tags . " " . (isset($this->params()->post['tags']) ? $this->params()->post['tags'] : ''), 'updater_user_id' => $this->session()->user_id, 'updater_ip_addr' => $this->request()->remoteIp()];
            if (!$p->source && $this->post->source) {
                $update['source'] = $this->post->source;
            }
            $p->updateAttributes($update);

            $api_data = [
                'location' => $this->urlFor(['post#show', 'id' => $p->id]),
                'post_id' => $p->id,
            ];
            $this->respond_to_error("Post already exists", ['post#show', 'id' => $p->id, 'tag_title' => $this->post->tag_title()], ['api' => $api_data, 'status' => 423]);
        } else {
            $this->respond_to_error($this->post, '#error');
        }
    }

    public function moderate()
    {
        $this->set_title('Moderation Queue');

        if ($this->request()->isPost()) {
            $posts = new Rails\ActiveRecord\Collection();

            if ($this->params()->ids) {
                foreach (array_keys($this->params()->ids) as $post_id) {
                    $post = Post::find($post_id);

                    if ($this->params()->commit == "Approve") {
                        $post->approve(current_user()->id);
                    } elseif ($this->params()->commit == "Delete") {
                        $post->destroy_with_reason(($this->params()->reason ? $this->params()->reason : $this->params()->reason2), current_user());

                        # Include post data for the parent: deleted posts aren't counted as children, so
                        # their has_children attribute may change.
                        if ($post->parent_id) {
                            $posts[] = $post->get_parent();
                        }
                    }

                    # Post may have been permanently deleted.
                    if (!CONFIG()->delete_posts_permanently) {
                        $post->reload();
                    }

                    $posts[] = $post;
                }
            }

            $posts->unique();

            if ($this->request()->format() == "json" || $this->request()->format() == "xml") {
                $api_data = Post::batch_api_data($posts->members());
            } else {
                $api_data = [];
            }

            if ($this->params()->commit == "Approve") {
                $this->respond_to_success("Post approved", "#moderate", ['api' => $api_data]);
            } elseif ($this->params()->commit == "Delete") {
                $this->respond_to_success("Post deleted", "#moderate", ['api' => $api_data]);
            }

        } else {
            if ($this->params()->query) {
                list($sql, $params) = Post::generate_sql($this->params()->query, ['pending' => true, 'order' => "id desc"]);
                $this->pending_posts = Post::findBySql($sql, $params);
                list($sql, $params) = Post::generate_sql($this->params()->query, ['flagged' => true, 'order' => "id desc"]);
                $this->flagged_posts = Post::findBySql($sql, $params);
            } else {
                $this->pending_posts = Post::where("status = 'pending'")->order("id desc")->take();
                $this->flagged_posts = Post::where("status = 'flagged'")->order("id desc")->take();
            }

            # Preload all pending (unresolved) flags for flagged posts to avoid N+1 queries
            $flagged_post_ids = [];
            foreach ($this->flagged_posts as $fp) {
                $flagged_post_ids[] = $fp->id;
            }
            $this->flags_by_post = [];
            if (!empty($flagged_post_ids)) {
                $placeholders = implode(',', array_fill(0, count($flagged_post_ids), '?'));
                $all_flags = FlaggedPostDetail::where(
                    "post_id IN ({$placeholders}) AND is_resolved = 0",
                    ...$flagged_post_ids,
                )->order('created_at DESC')->take();
                foreach ($all_flags as $flag) {
                    $this->flags_by_post[$flag->post_id][] = $flag;
                }
            }
        }
    }

    public function update()
    {
        $this->post = Post::find($this->params()->id);
        if ($this->post->is_deleted() and !current_user()->is_mod_or_higher()) {
            $this->respond_to_error('Post Locked', ['#show', 'id' => $this->params()->id], ['status' => 422]);
            return;
        }
        $user_id = current_user()->id;

        $post = $this->params()->post;
        Post::filter_api_changes($post);

        $post['updater_user_id'] = current_user()->id;
        $post['updater_ip_addr'] = $this->request()->remoteIp();

        if ($this->post->updateAttributes($post)) {
            # Reload the post to send the new status back; not all changes will be reflected in
            # $this->post due to after_save changes.
            $this->post->reload();

            if ($this->params()->format == "json" || $this->params()->format == "xml") {
                $api_data = $this->post->api_data();
            } else {
                $api_data = [];
            }
            $this->respond_to_success("Post updated", ['#show', 'id' => $this->post->id, 'tag_title' => $this->post->tag_title()], ['api' => $api_data]);
        } else {
            $this->respond_to_error($this->post, ['#show', 'id' => $this->params()->id]);
        }
    }

    public function updateBatch()
    {
        $user_id = current_user()->id;

        $ids = [];
        if (!is_array($this->params()->post)) {
            $this->params()->post = [];
        }
        foreach ($this->params()->post as $post) {
            if (isset($post[0])) {
                # We prefer { :id => 1, :rating => 's' }, but accept ["123", {:rating => 's'}], since that's
                # what we'll get from HTML forms.
                $post_id = $post[0];
                $post = $post[1];
            } else {
                $post_id = $post['id'];
                unset($post['id']);
            }

            $p = Post::find($post_id);
            $ids[] = $p->id;

            # If an entry has only an ID, it was just included in the list to receive changes to
            # a post without changing it (for example, to receive the parent's data after reparenting
            # a post under it).
            if (empty($post)) {
                continue;
            }

            $old_parent_id = $p->parent_id;

            Post::filter_api_changes($post);

            if ($p->updateAttributes(array_merge($post, ['updater_user_id' => $user_id, 'updater_ip_addr' => $this->request()->remoteIp()]))) {
                // post.merge(:updater_user_id => user_id, :updater_ip_addr => request.remoteIp))
                # Reload the post to send the new status back; not all changes will be reflected in
                # @post due to after_save changes.
                // $p->reload();
            }

            if ($p->parent_id != $old_parent_id) {
                $p->parent_id && $ids[] = $p->parent_id;
                $old_parent_id && $ids[] = $old_parent_id;
            }
        }

        # Updates to one post may affect others, so only generate the return list after we've already
        # updated everything.
        $ids = array_map('intval', $ids);
        $ids = implode(', ', $ids);

        $posts = Post::where("id IN ($ids)")->take();
        $api_data = Post::batch_api_data($posts->members());

        $url = $this->params()->url ?: '#index';
        $this->respond_to_success("Posts updated", $url, ['api' => $api_data]);
    }

    public function delete()
    {
        $this->post = Post::find($this->params()->id);

        if ($this->post && $this->post->parent_id) {
            $this->post_parent = Post::find($this->post->parent_id);
        } else {
            $this->post_parent = null;
        }
    }

    public function destroy()
    {
        if ($this->params()->commit == "Cancel") {
            $this->redirectTo(['#show', 'id' => $this->params()->id]);
            return;
        }

        $this->post = Post::find($this->params()->id);

        if ($this->post->can_user_delete(current_user())) {
            if ($this->post->status == "deleted") {
                if ($this->params()->destroy) {
                    if (current_user()->is_mod_or_higher()) {
                        $this->post->delete_from_database();
                        $this->respond_to_success("Post deleted permanently", ['#show', 'id' => $this->params()->id]);
                    } else {
                        $this->access_denied();
                    }
                } else {
                    $this->respond_to_success("Post already deleted", ['#delete', 'id' => $this->params()->id]);
                }
            } else {
                Post::static_destroy_with_reason($this->post->id, $this->params()->reason, current_user());
                $notice = "Post deleted";
                if ($this->request()->format() == 'json') {
                    $options = ['api' => Post::batch_api_data([$this->post])];
                } else {
                    $options = [];
                }

                $this->respond_to_success($notice, ['#show', 'id' => $this->params()->id], $options);
            }
        } else {
            $this->access_denied();
        }
    }

    public function deletedIndex()
    {
        if (!current_user()->is_anonymous() && $this->params()->user_id && (int) $this->params()->user_id == current_user()->id) {
            current_user()->updateAttribute('last_deleted_post_seen_at', date('Y-m-d H:i:s'));
        }

        $page = $this->page_number();

        $query = Post::order("flagged_post_details.created_at DESC")
                    ->select('posts.*')
                    ->group('posts.id')
                    ->joins("JOIN flagged_post_details ON flagged_post_details.post_id = posts.id")
                    ->page($page)->perPage(25);

        # Filter by reason_category if provided and valid
        $reason_category = $this->params()->reason_category;
        if ($reason_category) {
            $valid_keys = array_map(function ($r) {
                return $r['key'];
            }, CONFIG()->flag_reasons);
            if (in_array($reason_category, $valid_keys)) {
                $query = $query->where("flagged_post_details.reason_category = ?", $reason_category);
            }
        }

        if ($this->params()->user_id) {
            $user_id = (int) $this->params()->user_id;
            $this->posts = $query->where("posts.status = 'deleted' AND posts.user_id = ? ", $user_id)->paginate();
        } else {
            $this->posts = $query->where("posts.status = 'deleted'")->paginate();
        }
    }

    public function acknowledgeNewDeletedPosts()
    {
        if (!current_user()->is_anonymous()) {
            current_user()->updateAttribute('last_deleted_post_seen_at', date('Y-m-d H:i:s'));
        }
        $this->respond_to_success("Success", []);
    }

    public function index()
    {
        $tags = $this->params()->tags;
        $split_tags = $tags ? array_filter(explode(' ', $tags)) : [];
        $page = $this->page_number();
        $this->tag_suggestions = $this->searching_pool = [];
        $from_api = $this->is_search_api_request();

        /*        if $this->current_user.is_member_or_lower? && count(split_tags) > 2
        #            $this->respond_to_error("You can only search up to two tags at once with a basic account", 'action' => "error")
        #            return;
        #        elseif count(split_tags) > 6
        */
        if (count($split_tags) > CONFIG()->tag_query_limit) {
            $message = "You can only search up to " . CONFIG()->tag_query_limit . " tags at once";
            if ($from_api) {
                $this->respond_to_search_error($message, 424, $tags);
            } else {
                $this->respond_to_error($message, "#error");
            }
            return;
        }

        try {
            $q = Tag::parse_query($tags);
        } catch (\Throwable $e) {
            $this->respond_to_search_error("Invalid search query: " . $e->getMessage(), 424, $tags);
            return;
        }

        $limit = (int) $this->params()->limit;
        isset($q['limit']) && $limit = (int) $q['limit'];
        $limit <= 0 && $limit = CONFIG()->post_index_default_limit;
        $limit > 1000 && $limit = 1000;

        $count = 0;

        $this->set_title("/" . str_replace("_", " ", $tags));

        try {
            $count = $this->calculate_search_count($q, $tags, $from_api);
        } catch (\Throwable $e) {
            $this->respond_to_search_error($e->getMessage(), 424, $tags);
            return;
        }


        $this->ambiguous_tags = Tag::select_ambiguous($split_tags);
        if (isset($q['pool']) and is_int($q['pool'])) {
            $this->searching_pool = Pool::where(['id' => $q['pool']])->first();
        }

        // $this->posts = Post::find_all(array('page' => $page, 'per_page' => $limit, $count));
        // $this->posts = WillPaginate::Collection.new(page, limit, count);

        // $offset = $this->posts->offset();
        // $posts_to_load = $this->posts->per_page();
        $per_page = $limit;
        $offset = ($page - 1) * $per_page;
        $posts_to_load = $per_page;

        if (!$from_api) {
            # For forward preloading:
            // $posts_to_load += $this->posts->per_page();
            $posts_to_load += $per_page;

            # If we're not on the first page, load the previous page for prefetching.    Prefetching
            # the previous page when the user is scanning forward should be free, since it'll already
            # be in cache, so this makes scanning the index from back to front as responsive as from
            # front to back.
            if ($page and $page > 1) {
                // $offset -= $this->posts->per_page();
                // $posts_to_load += $this->posts->per_page();
                $offset -= $per_page;
                $posts_to_load += $per_page;
            }
        }

        $this->showing_holds_only = isset($q['show_holds']) && $q['show_holds'] == 'only';
        try {
            list($sql, $params) = Post::generate_sql($q, ['original_query' => $tags, 'from_api' => $from_api, 'order' => "p.id DESC", 'offset' => $offset, 'limit' => $posts_to_load]);
        } catch (\Throwable $e) {
            $this->respond_to_search_error($e->getMessage(), 424, $tags);
            return;
        }

        $results = Post::findBySql($sql, $params);

        $this->preload = new Rails\ActiveRecord\Collection();
        if (!$from_api) {
            if ($page && $page > 1) {
                $this->preload = $results->slice(0, $limit);
                $results = $results->slice($limit);
            }

            $this->preload->merge($results->slice($limit));

            $results = $results->slice(0, $limit);
        }

        # Apply can_be_seen_by filtering to the results.    For API calls this is optional, and
        # can be enabled by specifying filter=1.
        $apply_visibility_filter = $this->should_filter_search_results($from_api);
        if ($apply_visibility_filter) {
            $results->deleteIf(function ($post) {
                return !$post->can_be_seen_by(current_user(), ['show_deleted' => true]);
            });
            $this->preload->deleteIf(function ($post) {
                return !$post->can_be_seen_by(current_user());
            });
        }

        if ($from_api and $this->params()->api_version == "2" and $this->params()->format != "json") {
            $this->respond_to_error("V2 API is JSON-only", [], ['status' => 424]);
            return;
        }
        $this->posts = new Rails\ActiveRecord\Collection($results->members(), ['page' => $page, 'perPage' => $per_page, 'totalRows' => $count]);

        if ($count < CONFIG()->post_index_default_limit && count($split_tags) == 1) {
            $this->tag_suggestions = Tag::find_suggestions($tags);
        }
        // exit;
        $this->respondTo([
            'html' => function () use ($split_tags, $tags) {
                if ($split_tags) {
                    $this->tags = Tag::parse_query($tags);
                } else {
                    $this->tags = Rails::cache()->fetch('$poptags', ['expires_in' => '1 hour'], function () {
                        return ['include' => Tag::count_by_period(date('Y-m-d', strtotime('-' . CONFIG()->post_index_tags_limit)), date('Y-m-d H:i:s'), ['limit' => 25, 'exclude_types' => CONFIG()->exclude_from_tag_sidebar])];
                    });
                }
            },
            'xml' => function () {
                $this->setLayout(false);
            },
            'json' => function () {
                $api_version = (string) $this->params()->api_version;
                if ($api_version !== "2") {
                    $this->render(['json' => array_map(function ($p) {
                        return $p->api_attributes();
                    }, $this->posts->members())]);
                    return;
                }

                $api_data = Post::batch_api_data($this->posts->members(), [
                    'exclude_tags' => $this->params()->include_tags != "1",
                    'exclude_votes' => $this->params()->include_votes != "1",
                    'exclude_pools' => $this->params()->include_pools != "1",
                    'fake_sample_url' => CONFIG()->fake_sample_url,
                ]);

                $this->render(['json' => $this->build_search_json_envelope($api_data)]);
            },
            // ,
            // 'atom'
        ]);
    }

    public function count()
    {
        if (!$this->is_search_api_request()) {
            throw new \Rails\ActiveRecord\Exception\RecordNotFoundException();
        }

        $tags = $this->params()->tags;
        $split_tags = $tags ? array_filter(explode(' ', $tags)) : [];
        if (count($split_tags) > CONFIG()->tag_query_limit) {
            $this->respond_to_search_error("You can only search up to " . CONFIG()->tag_query_limit . " tags at once", 424, $tags);
            return;
        }

        try {
            $q = Tag::parse_query($tags);
            $count = $this->calculate_search_count($q, $tags, true);
        } catch (\Throwable $e) {
            $this->respond_to_search_error($e->getMessage(), 424, $tags);
            return;
        }

        $payload = \MyImouto\PostSearch\ApiContract::buildCountEnvelope($tags, $count, [
            'api_version' => (string) $this->params()->api_version,
            'filter' => $this->should_filter_search_results(true),
        ]);

        $this->respondTo([
            'json' => function () use ($payload) {
                $this->render(['json' => $payload]);
            },
            'xml' => function () use ($payload) {
                $this->render(['xml' => $payload, 'root' => 'response']);
            },
        ]);
    }

    // private function is_mobile_browser()
    // {
    // if ($agent = $this->request()->get("HTTP_USER_AGENT")) {
    // return is_int(strpos($agent, "Android")) ||
    // is_int(strpos($agent, "BlackBerry")) ||
    // is_int(strpos($agent, "iPhone")) ||
    // is_int(strpos($agent, "iPad")) ||
    // is_int(strpos($agent, "iPod")) ||
    // is_int(strpos($agent, "Opera Mini")) ||
    // is_int(strpos($agent, "IEMobile"));
    // }
    // return false;
    // }

    public function atom()
    {
        list($sql, $params) = Post::generate_sql($this->params()->tags, ['limit' => 20, 'order' => "p.id DESC"]);
        $this->posts = Post::findBySql($sql, $params);
        if (!$this->posts) {
            $this->posts = new Rails\ActiveRecord\Collection();
        }
        $this->setLayout(false);
    }

    public function piclens()
    {
        $page = $this->page_number();
        $per_page = 16;
        $offset = ($page - 1) * $per_page;
        $total_rows = Post::fast_count($this->params()->tags);

        list($sql, $params) = Post::generate_sql($this->params()->tags, ['order' => "p.id DESC", 'offset' => $offset, 'limit' => $per_page]);
        $posts = Post::findBySql($sql, $params);
        if (!$posts) {
            $posts = new Rails\ActiveRecord\Collection();
        }

        $this->posts = new Rails\ActiveRecord\Collection(
            $posts->members(),
            ['page' => $page, 'perPage' => $per_page, 'totalRows' => $total_rows],
        );

        $this->setLayout(false);
    }

    public function favorites()
    {
        $this->post = Post::find($this->params()->id);
        $favorite_user_ids = [];
        foreach ($this->post->favorited_by() as $favorited_user) {
            if (is_array($favorited_user) && isset($favorited_user['id'])) {
                $favorite_user_ids[] = (int) $favorited_user['id'];
            }
        }
        $favorite_user_ids = array_unique(array_filter($favorite_user_ids));

        if (!$favorite_user_ids) {
            $this->users = new Rails\ActiveRecord\Collection();
            return;
        }

        $this->users = User::where('id IN (?)', $favorite_user_ids)->order('name')->take();
        if (!$this->users) {
            $this->users = new Rails\ActiveRecord\Collection();
        }
    }

    public function show()
    {
        $this->helper('Avatar');

        try {
            if ($this->params()->cache) {
                $this->response()->headers()->add("Cache-Control", "max-age=300");
            }
            $this->cache = $this->params()->cache; # temporary
            $this->body_only = (int) $this->params()->body == 1;

            if ($this->params()->md5) {
                if (!$this->post = Post::where(['md5' => strtolower($this->params())])->first()) {
                    throw Rails\ActiveRecord\Exception\RecordNotFoundException();
                }
            } else {
                $this->post = Post::find($this->params()->id);
            }

            $this->pools = Pool::where("pools_posts.post_id = {$this->post->id} AND pools_posts.active")->joins("JOIN pools_posts ON pools_posts.pool_id = pools.id")->order("pools.name")->select("pools.name, pools.id")->take();

            if ($this->params()->pool_id) {
                $this->following_pool_post = PoolPost::where("pool_id = ? AND post_id = ?", $this->params()->pool_id, $this->post->id)->first();
            } else {
                $this->following_pool_post = PoolPost::where("post_id = ?", $this->post->id)->first();
            }

            $this->tags = ['include' => $this->post->tags()];
            $this->include_tag_reverse_aliases = true;
            $this->set_title(str_replace('_', ' ', $this->post->title_tags()));
            $this->respondTo([
                'html',
            ]);
        } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
            $this->respondTo([
                'html' => function () {
                    $this->render(['action' => 'show_empty', 'status' => 404]);
                },
            ]);
        }
    }

    public function browse()
    {
        $this->response()->headers()->add("Cache-Control", "max-age=300");
        $this->setLayout("bare");
    }

    public function view()
    {
        $this->redirectTo(["#show", 'id' => $this->params()->id]);
    }

    public function popularRecent()
    {
        switch ($this->params()->period) {
            case "1w":
                $this->period_name = "last week";
                $period = '1 week';
                break;
            case "1m":
                $this->period_name = "last month";
                $period = '1 month';
                break;
            case "1y":
                $this->period_name = "last year";
                $period = '1 year';
                break;
            default:
                $this->params()->period = "1d";
                $this->period_name = "last 24 hours";
                $period = '1 day';
                break;
        }

        $this->post_params = $this->params()->all();
        $this->start = strtotime('-' . $period);

        $this->set_title('Exploring ' . $this->period_name);

        $this->posts = Post::where("status <> 'deleted' AND posts.index_timestamp >= ? AND posts.index_timestamp <= ? ", date('Y-m-d', $this->start), date('Y-m-d H:i:s'))->order("score DESC")->limit(20)->take();

        $this->respond_to_list("posts");
    }

    public function popularByDay()
    {
        if (!$this->params()->year || !$this->params()->month || !$this->params()->day ||
            !($this->day = @strtotime($this->params()->year . '-' . $this->params()->month . '-' . $this->params()->day))) {
            $this->day = strtotime('this day');
        }

        $this->set_title('Exploring ' . date('Y', $this->day) . '/' . date('m', $this->day) . '/' . date('d', $this->day));

        $this->posts = Post::available()->where('created_at BETWEEN ? AND ?', date('Y-m-d', $this->day), date('Y-m-d', strtotime('+1 day', $this->day)))->order("score DESC")->limit(20)->take();

        $this->respond_to_list("posts");
    }

    public function histogram()
    {
        // Legacy /histogram alias: keep route working without a separate code path.
        $this->popularByDay();
    }

    public function popularByWeek()
    {
        if (!$this->params()->year || !$this->params()->month || !$this->params()->day ||
            !($this->start = strtotime('this week', @strtotime($this->params()->year . '-' . $this->params()->month . '-' . $this->params()->day)))) {
            $this->start = strtotime('this week');
        }

        $this->end = strtotime('next week', $this->start);

        $this->set_title('Exploring ' . date('Y', $this->start) . '/' . date('m', $this->start) . '/' . date('d', $this->start) . ' - ' . date('Y', $this->end) . '/' . date('m', $this->end) . '/' . date('d', $this->end));

        $this->posts = Post::available()->where('created_at BETWEEN ? AND ?', date('Y-m-d', $this->start), date('Y-m-d', $this->end))->order('score DESC')->limit(20)->take();

        $this->respond_to_list("posts");
    }

    public function popularByMonth()
    {
        if (!$this->params()->year || !$this->params()->month || !($this->start = @strtotime($this->params()->year . '-' . $this->params()->month . '-1'))) {
            $this->start = strtotime('first day of this month');
        }

        $this->end = strtotime('+1 month', $this->start);

        $this->set_title('Exploring ' . date('Y', $this->start) . '/' . date('m', $this->start));

        $this->posts = Post::available()->where('created_at BETWEEN ? AND ?', date('Y-m-d', $this->start), date('Y-m-d', $this->end))->order('score DESC')->limit(20)->take();

        $this->respond_to_list("posts");
    }

    // public function revertTags()
    // {
    // user_id = current_user()->id
    // $this->post = Post.find($this->params()->id)
    // $this->post.updateAttributes('tags' => (int)PostTagHistory.find($this->params()->history_id).tags, 'updater_user_id' => user_id, 'updater_ip_addr' => $this->request()->remoteIp())

    // $this->respond_to_success("Tags reverted", '#show', 'id' => $this->post.id, 'tag_title' => $this->post.tag_title)
    // }

    public function vote()
    {
        if ($this->params()->score === null) {
            $vote = PostVote::where(['user_id' => current_user()->id, 'post_id' => $this->params()->id])->first();
            $score = $vote ? $vote->score : 0;
            $this->respond_to_success("", [], ['vote' => $score]);
            return;
        }

        $p = Post::find($this->params()->id);
        $score = (int) $this->params()->score;

        if (!current_user()->is_mod_or_higher() && ($score < 0 || $score > 3)) {
            $this->respond_to_error("Invalid score", ["#show", 'id' => $this->params()->id, 'tag_title' => $p->tag_title(), 'status' => 424]);
            return;
        }

        $vote_successful = $p->vote($score, current_user());

        $api_data = Post::batch_api_data([$p]);
        $api_data['voted_by'] = $p->voted_by();

        if ($vote_successful) {
            $this->respond_to_success("Vote saved", ["#show", 'id' => $this->params()->id, 'tag_title' => $p->tag_title()], ['api' => $api_data]);
        } else {
            $this->respond_to_error("Already voted", ["#show", 'id' => $this->params()->id, 'tag_title' => $p->tag_title()], ['api' => $api_data, 'status' => 423]);
        }
    }

    public function flag()
    {
        $post = Post::find($this->params()->id);

        if ($this->params()->unflag == '1') {
            # Allow the user who flagged a post to unflag it.
            #
            # posts
            # "approve" is used both to mean "unflag post" and "approve pending post".
            if ($post->status != "flagged") {
                $this->respond_to_error("Can only unflag flagged posts", ["#show", 'id' => $this->params()->id]);
                return;
            }

            $latest_flag = $post->latest_flag();
            if (!current_user()->is_janitor_or_higher() and (!$latest_flag || current_user()->id != $latest_flag->user_id)) {
                $this->access_denied();
                return;
            }

            # Resolve the flag via resolve() instead of approve()
            if ($latest_flag) {
                $latest_flag->resolve(current_user()->id);
            }
            $post->approve(current_user()->id);
            $message = "Post approved";
        } else {
            if ($post->status != "active") {
                $this->respond_to_error("Can only flag active posts", ["#show", 'id' => $this->params()->id]);
                return;
            }

            # Cooldown check: non-janitors cannot flag the same post within 24 hours
            if (!current_user()->is_janitor_or_higher() && !FlaggedPostDetail::can_flag_again(current_user()->id, $post->id)) {
                $this->respond_to_error("You can only flag a post once every 24 hours", ["#show", 'id' => $this->params()->id]);
                return;
            }

            $reason_category = $this->params()->reason_category;
            $parent_post_id = $this->params()->parent_post_id;

            // Validate reason_category against configured reasons
            $valid_categories = array_map(fn($r) => $r['key'], CONFIG()->flag_reasons);
            if ($reason_category && !in_array($reason_category, $valid_categories)) {
                $this->respond_to_error("Invalid flag reason category", ["#show", 'id' => $this->params()->id]);
                return;
            }

            // Validate requires_detail
            $reason_config = null;
            if ($reason_category) {
                foreach (CONFIG()->flag_reasons as $r) {
                    if ($r['key'] === $reason_category) {
                        $reason_config = $r;
                        break;
                    }
                }
            }
            if ($reason_config && !empty($reason_config['requires_detail'])) {
                if (!$this->params()->reason || trim($this->params()->reason) === '') {
                    $this->respond_to_error("This flag reason requires a detailed explanation", ["#show", 'id' => $this->params()->id]);
                    return;
                }
            }

            // Validate parent_post_id references an existing, non-deleted post
            if ($parent_post_id) {
                $parent = Post::where('id = ? AND status <> ?', (int) $parent_post_id, 'deleted')->first();
                if (!$parent) {
                    $this->respond_to_error("Referenced parent post does not exist or is deleted", ["#show", 'id' => $this->params()->id]);
                    return;
                }
            }

            $post->flag($this->params()->reason, current_user()->id, $reason_category, $parent_post_id);
            $message = "Post flagged";
        }

        # Reload the post to pull in post.flag_reason.
        $post->reload();

        if ($this->request()->format() == "json" || $this->request()->format() == "xml") {
            $api_data = Post::batch_api_data([$post]);
        } else {
            $api_data = [];
        }
        $this->respond_to_success($message, ["#show", 'id' => $this->params()->id], ['api' => $api_data]);
    }

    public function resolveFlag()
    {
        $flag = FlaggedPostDetail::where('id = ?', (int) $this->params()->id)->first();

        if (!$flag) {
            $this->render(['json' => json_encode(['success' => false, 'reason' => 'Flag not found']), 'status' => 404]);
            return;
        }

        # Already resolved — idempotent success
        if ($flag->is_resolved) {
            $this->render(['json' => json_encode(['success' => true])]);
            return;
        }

        $flag->resolve(current_user()->id);

        $this->render(['json' => json_encode(['success' => true])]);
    }

    public function random()
    {
        $max_id = Post::maximum('id');

        foreach (range(1, 10) as $i) {
            $post = Post::where("id = ? AND status <> 'deleted'", rand(1, $max_id) + 1)->first();

            if ($post && $post->can_be_seen_by(current_user())) {
                $this->redirectTo(['#show', 'id' => $post->id, 'tag_title' => $post->tag_title()]);
                return;
            }
        }

        $this->notice("Couldn't find a post in 10 tries. Try again.");
        $this->redirectTo("#index");
    }

    public function similar()
    {
        $params = array_merge([
            'file'      => null,
            'url'       => null,
            'id'        => $this->params()->id,
            'search_id' => null,
            'services'  => null,
            'threshold' => null,
            'forcegray' => null,
            'initial'   => null,
            'width'     => null,
            'height'    => null,
        ], $this->params()->toArray());

        if (!empty($params['data_search']) && !current_user()->is_mod_or_higher()) {
            unset($params['data_search']);
        }

        if (!SimilarImages::valid_saved_search($params['search_id'])) {
            $params['search_id'] = null;
        }
        if (!empty($params['width'])) {
            $params['width'] = (int) $params['width'];
        }
        if (!empty($params['height'])) {
            $params['height'] = (int) $params['height'];
        }

        $this->initial = $params['initial'];
        if ($this->initial && !$params['services']) {
            $params['services'] = "local";
        }

        $this->services = SimilarImages::get_services($params['services']);

        if ($this->params()->id) {
            $this->compared_post = Post::find($this->params()->id);
        } else {
            $this->compared_post = new Post();
        }

        $this->errors  = null;
        $this->posts   = Post::emptyCollection();
        $this->similar = [];
        $similarity    = [];

        if ($this->compared_post && $this->compared_post->is_deleted()) {
            $this->respond_to_error("Post deleted", ['post#show', 'id' => $this->params()->id, 'tag_title' => $this->compared_post->tag_title()]);
            return;
        }

        # We can do these kinds of searches:
        #
        # File: Search from a specified file.    The image is saved locally with an ID, and sent
        # as a file to the search servers.
        #
        # URL: search from a remote URL.    The URL is downloaded, and then treated as a :file
        # search.    This way, changing options doesn't repeatedly download the remote image,
        # and it removes a layer of abstraction when an error happens during download
        # compared to having the search server download it.
        #
        # Post ID: Search from a post ID.    The preview image is sent as a URL.
        #
        # Search ID: Search using an image uploaded with a previous File search, using
        # the search MD5 created.    We're not allowed to repopulate filename fields in the
        # user's browser, so we can't re-submit the form as a file search when changing search
        # parameters.    Instead, we hide the search ID in the form, and use it to recall the
        # file from before.    These files are expired after a while; we check for expired files
        # when doing later searches, so we don't need a cron job.
        $search = function (array $params) {
            $options = array_merge($params, [
                'services' => $this->services,
            ]);

            # Check search_id first, so options links that include it will use it.    If the
            # user searches with the actual form, search_id will be cleared on submission.
            if (!empty($params['search_id'])) {
                $file_path = SimilarImages::find_saved_search($params['search_id']);
                if (!$file_path) {
                    # The file was probably purged.    Delete :search_id before redirecting, so the
                    # error doesn't loop.
                    // unset($params.delete(:search_id)
                    return [ 'errors' => [ 'error' => "Search expired" ] ];
                }
            } elseif (!empty($params['url']) || !empty($_FILES['file']['tmp_name'])) {
                # Save the file locally.
                try {
                    if (!empty($params['url'])) {
                        $whitelist_result = UploadWhitelist::is_allowed($params['url']);
                        if (!$whitelist_result['allowed']) {
                            return ['errors' => ['error' => 'URL is not on the upload whitelist: ' . $whitelist_result['reason']]];
                        }
                        $file = Danbooru::http_get_streaming($params['url']);
                        $search = SimilarImages::save_search($file);
                    } else { # file
                        $file = file_get_contents($_FILES['file']['tmp_name']);
                        $search = SimilarImages::save_search($file);
                        $options['type'] = 'file';
                    }
                } catch (/*SocketError, URI::Error, SystemCallError,*/Moebooru\Exception\ResizeErrorException $e) {
                    return [ 'errors' => [ 'error' => $e->getMessage() ] ];
                } catch (Danbooru\Exception\ExceptionInterface $e) {
                    return [ 'errors' => [ 'error' => $e->getMessage() ] ];
                }
                // } rescue Timeout::Error => e
                // return { :errors => { :error => "Download timed out" } }
                // end

                $file_path = $search['file_path'];

                # Set :search_id in params for generated URLs that point back here.
                $params['search_id'] = $search['search_id'];

                # The :width and :height params specify the size of the original image, for display
                # in the results.    The user can specify them; if not specified, fill it in.
                empty($params['width']) && $params['width'] = $search['original_width'];
                empty($params['height']) && $params['height'] = $search['original_height'];
            } elseif (!empty($params['id'])) {
                $options['source'] = $this->compared_post;
                $options['type'] = 'post';
            }

            if (!empty($params['search_id'])) {
                $options['source'] = $file_path;
                $options['source_filename'] = $params['search_id'];
                $options['source_thumb'] = "/data/search/" . $params['search_id'];
                $options['type'] = 'file';
            }
            $options['width'] = $params['width'];
            $options['height'] = $params['height'];

            if ($options['type'] == 'file') {
                SimilarImages::cull_old_searches();
            }

            return SimilarImages::similar_images($options);
        };

        $this->searched = false;
        if ($this->params()->url || $this->params()->id || (!empty($_FILES['file']) && empty($_FILES['file']['error'])) || !empty($this->params()->search_id)) {
            $res = $search($params);

            # Error when no service was selected and/or local search isn't supported
            if (is_string($res['errors'])) {
                $this->notice($res['errors']);
            } else {
                $this->errors = !empty($res['errors']) ? $res['errors'] : [];
                $this->searched = true;
                $this->search_id = $this->params()->search_id;

                # Never pass :file on through generated URLs.
                $params['file'] = null;
            }
        } else {
            $res = [];
            $this->errors = [];
        }

        if ($res && $this->searched) {
            !empty($res['posts']) && $this->posts = $res['posts'];
            $this->similar = $res;
            !empty($res['similarity']) && $similarity = $res['similarity'];
        }

        if ($this->request()->format() == "json" || $this->request()->format() == "xml") {
            if (!empty($this->errors['error'])) {
                $this->respond_to_error($this->errors['error'], ['#index'], ['status' => 503]);
                return;
            }
            if (!$this->searched) {
                $this->respond_to_error("no search supplied", ['#index'], ['status' => 503]);
                return;
            }
        }

        $this->respondTo([
            'html' => function () use ($similarity, $res) {
                if ($this->initial && !$this->posts->any()) {
                    // flash.keep
                    $this->redirectTo(['post#show', 'id' => $this->params()->id, 'tag_title' => $this->compared_post->tag_title()]);
                    return;
                }
                if (!empty($this->errors['error'])) {
                    $this->notice($this->errors['error']);
                }

                if ($this->searched) {
                    !empty($res['posts_external']) && $this->posts->merge($res['posts_external']);
                    $this->posts->sort(function ($a, $b) use ($similarity) {
                        $aid = spl_object_hash($a);
                        $bid = spl_object_hash($b);
                        if ($similarity[$aid] == $similarity[$bid]) {
                            return 0;
                        } elseif ($similarity[$aid] > $similarity[$bid]) {
                            return 1;
                        }
                        return -1;
                    });
                    # Add the original post to the start of the list.
                    if (!empty($res['source'])) {
                        $this->posts[] = $res['source'];
                    } elseif (!empty($res['external_source'])) {
                        $this->posts[] = $res['external_source'];
                    }
                }
            },
            'json' => function () use ($res) {
                foreach ($this->posts as $post) {
                    $post->similarity = $res['similarity'][spl_object_hash($post)];
                }
                if (!empty($res['posts_external'])) {
                    foreach ($res['posts_external'] as $post) {
                        $post->similarity = $res['similarity'][spl_object_hash($post)];
                    }
                    $this->posts->merge($res['posts_external']);
                }
                $api_data = [
                    'posts'     => $this->posts,
                    'search_id' => $this->params()->search_id,
                ];
                if (!empty($res['source'])) {
                    $api_data['source'] = $res['source'];
                } elseif (!empty($res['external_source'])) {
                    $api_data['source'] = $res['external_source'];
                } else {
                    $api_data['source'] = '';
                }

                if (!empty($res['errors'])) {
                    $api_data['error'] = [];
                    foreach ($res['errors'] as $server => $error) {
                        $services = !empty($error['services']) ? implode(', ', $error['services']) : [];
                        $api_data['error'][] = [ 'server' => $server, 'message' => $error['message'], 'services' => $services ];
                    }
                }

                $this->respond_to_success('', [], ['api' => $api_data]);
            },

            // fmt.xml do
            // x = Builder::XmlMarkup.new('indent' => 2)
            // x.instruct!
            // $this->render(array('xml' => x.posts() {)
            // unless res[:errors].empty?
            // res[:errors].map { |server, error|
            // { :server=>server, :message=>error[:message], :services=>error[:services].join(",") }.to_xml('root' => "error", 'builder' => x, 'skip_instruct' => true)
            // }
            // end

            // if (res[:source]) {
            // x.source() {
            // res[:source].to_xml('builder' => x, 'skip_instruct' => true)
            // }
            // } else {
            // x.source() {
            // res[:external_source].to_xml('builder' => x, 'skip_instruct' => true)
            // }
            // }

            // $this->posts.each { |e|
            // x.similar(:similarity=>res[:similarity][e]) {
            // e.to_xml('builder' => x, 'skip_instruct' => true)
            // }
            // }
            // res[:posts_external].each { |e|
            // x.similar(:similarity=>res[:similarity][e]) {
            // e.to_xml('builder' => x, 'skip_instruct' => true)
            // }
            // }
            // }
        ]);

        $this->params = $params;
    }

    public function undelete()
    {
        $post = Post::where(['id' => $this->params()->id])->first();

        if (!$post) {
            $this->respond_to_error("Post not found", ['#show', 'id' => $this->params()->id]);
            return;
        }

        $post->undelete();

        $affected_posts = [$post];
        if ($post->parent_id) {
            $affected_posts[] = $post->get_parent();
        }
        if ($this->params()->format == "json" || $this->params()->format == "xml") {
            $api_data = Post::batch_api_data($affected_posts);
        } else {
            $api_data = [];
        }
        $this->respond_to_success("Post was undeleted", ['#show', 'id' => $this->params()->id], ['api' => $api_data]);
    }

    public function error() {}

    public function exception()
    {
        throw new Exception();
    }

    public function import()
    {
        $import_dir = Rails::publicPath() . '/data/import/';

        if ($this->request()->isPost()) {
            if ($this->request()->format() == 'json') {
                foreach (explode('::', $this->params()->dupes) as $file) {
                    $file = stripslashes(utf8_decode($file));
                    $file = $import_dir . $file;
                    if (file_exists($file)) {
                        unlink($file);
                    } else {
                        $error = true;
                    }
                }

                $resp = !empty($error) ? ['reason' => 'Some files could not be deleted'] : ['success' => true];
                $this->render(['json' => $resp]);
                return;
            }

            $this->setLayout(false);
            $this->errors = $this->dupe = false;
            $post = $this->params()->post;
            $post['filename'] = stripslashes(utf8_decode($post['filename']));
            $filepath = $import_dir . $post['filename'];

            # Take folders as tags
            if (is_int(strpos($post['filename'], '/'))) {
                $folders = str_replace('#', ':', $post['filename']);
                $tags = array_filter(array_unique(array_merge(explode(' ', $post['tags']), explode('/', $folders))));
                array_pop($tags);
                $post['tags'] = trim($post['tags'] . ' ' . implode(' ', $tags));
            }

            $post = array_merge($post, [
                'ip_addr'       => $this->request()->remoteIp(),
                'user_id'       => current_user()->id,
                'status'        => 'active',
                'tempfile_path' => $filepath,
                'tempfile_name' => $post['filename'],
                'is_import'     => true,
            ]);
            unset($post['filename'], $post['i']);

            $this->post = Post::create($post);

            if ($this->post->errors()->blank()) {
                $this->import_status = 'Posted';
            } elseif ($this->post->errors()->invalid('md5')) {
                $this->dupe   = true;
                $this->import_status = 'Already exists';
                $this->post   = Post::where(['md5' => $this->post->md5])->first();
                $this->post->status = 'flagged';
            } else {
                $this->errors = $this->post->errors()->fullMessages('<br />');
                $this->import_status = 'Error';
            }
        } else {
            $this->set_title('Import');
            $this->invalid_files = $this->files = [];

            list($this->files, $this->invalid_files, $this->invalid_folders) = Post::get_import_files($import_dir);

            $pools = Pool::where('is_active')->take();

            if ($pools) {
                $this->pool_list = '<datalist id="pool_list">';
                foreach ($pools as $pool) {
                    $this->pool_list .= '<option value="' . str_replace('_', ' ', $pool->name) . '" />';
                }
                $this->pool_list .= '</datalist>';
            } else {
                $this->pool_list = null;
            }
        }
    }

    public function searchExternalData()
    {
        if (!CONFIG()->enable_find_external_data) {
            throw new Rails\ActiveRecord\Exception\RecordNotFoundException();
        }

        if ($this->params()->ids) {
            $ids = $this->params()->ids;
            !is_array($ids) && $ids = [$ids];
            $this->posts = Post::where('id IN (?)', $ids)->take();
        } else {
            $this->posts = new Rails\ActiveRecord\Collection();
        }

        $this->services = SimilarImages::get_services('all');
    }

    private function is_search_api_request()
    {
        return ($this->params()->format == "json" || $this->params()->format == "xml");
    }

    private function should_filter_search_results($from_api)
    {
        return (!$from_api || $this->params()->filter);
    }

    private function calculate_search_count(array $query, $rawTags, $from_api)
    {
        if ($from_api && $this->should_filter_search_results($from_api)) {
            $chunk_query = $query;
            $chunk_query['order'] = 'id_desc';

            list($count_sql, $count_params) = Post::generate_sql($chunk_query, [
                'original_query' => $rawTags,
                'from_api' => $from_api,
                'count' => true,
            ]);
            array_unshift($count_params, $count_sql);
            $total_rows = (int) Post::countBySql($count_params);
            if ($total_rows === 0) {
                return 0;
            }

            $chunk_size = 500;
            $visible_count = 0;

            for ($offset = 0; $offset < $total_rows; $offset += $chunk_size) {
                list($sql, $params) = Post::generate_sql($chunk_query, [
                    'original_query' => $rawTags,
                    'from_api' => $from_api,
                    'offset' => $offset,
                    'limit' => $chunk_size,
                ]);

                $posts = Post::findBySql($sql, $params);
                if (!$posts || $posts->size() === 0) {
                    break;
                }

                $posts->deleteIf(function ($post) {
                    return !$post->can_be_seen_by(current_user(), ['show_deleted' => true]);
                });
                $visible_count += $posts->size();
            }

            return $visible_count;
        }

        list($sql, $params) = Post::generate_sql($query, [
            'original_query' => $rawTags,
            'from_api' => $from_api,
            'count' => true,
        ]);
        array_unshift($params, $sql);

        return (int) Post::countBySql($params);
    }

    private function build_search_json_envelope(array $batchData)
    {
        $query = (string) $this->params()->tags;
        $posts = isset($batchData['posts']) && is_array($batchData['posts']) ? $batchData['posts'] : [];
        $total_rows = isset($this->posts) && method_exists($this->posts, 'totalRows') ? (int) $this->posts->totalRows() : count($posts);
        $per_page = isset($this->posts) && method_exists($this->posts, 'perPage') ? (int) $this->posts->perPage() : count($posts);
        $page = isset($this->posts) && method_exists($this->posts, 'currentPage') ? (int) $this->posts->currentPage() : (int) $this->page_number();

        return \MyImouto\PostSearch\ApiContract::buildSearchEnvelope($posts, [
            'batch_data' => $batchData,
            'query' => $query,
            'count' => $total_rows,
            'page' => $page,
            'per_page' => $per_page,
            'api_version' => (string) $this->params()->api_version,
        ]);
    }

    private function respond_to_search_error($message, $status = 424, $query = null)
    {
        $payload = [
            'success' => false,
            'reason' => (string) $message,
            'query' => (string) $query,
        ];

        $this->respondTo([
            'html' => function () use ($payload, $status) {
                $this->respond_to_error($payload['reason'], '#error', ['status' => $status]);
            },
            'json' => function () use ($payload, $status) {
                $this->render(['json' => $payload, 'status' => $status]);
            },
            'xml' => function () use ($payload, $status) {
                $this->render(['xml' => $payload, 'root' => 'response', 'status' => $status]);
            },
        ]);
    }

    // public function download()
    // {
    // require 'base64'

    // data = $this->params()->data
    // filename = $this->params()->filename
    // type = $this->params()->type
    // if (filename.nil?) {
    // filename = "file"
    // }
    // if (type.nil?) {
    // type = "application/octet-stream"
    // }

    // data = Base64.decode64(data)

    // send_data data, 'filename' => filename, 'disposition' => "attachment", 'type' => type
    // }

    protected function init()
    {
        $this->helper('Avatar', 'Wiki', 'Tag', 'Comment', 'Pool', 'Favorite', 'Advertisements');
    }

    protected function filters()
    {
        return [
            'before' => [
                'user_can_see_posts' => ['only' => ['show', 'browse']],
                'member_only' => ['only' => ['create', 'destroy', 'delete', 'flag', 'revertTags', 'activate', 'updateBatch', 'vote', 'error']],
                'post_member_only' => ['only' => ['update', 'upload', 'flag']],
                'janitor_only' => ['only' => ['moderate', 'undelete']],
                'admin_only' => ['only' => ['import']],
                'mod_only' => ['only' => ['searchExternalData', 'resolveFlag']],
            ],
            'after' => [
                'save_tags_to_cookie' => ['only' => ['update', 'create']],
            ],
            # iTODO :
            'around' => [
                // 'cache_action' => ['only' => ['index', 'atom', 'piclens']]
            ],
        ];
    }
}
