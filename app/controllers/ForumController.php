<?php
class ForumController extends ApplicationController
{
    protected function init()
    {
        $this->helper('Avatar');
    }
    
    protected function filters()
    {
        return [
            'before' => [
                'sanitize_id' => ['only' => ['show']],
                'mod_only' => ['only' => ['stick', 'unstick', 'lock', 'unlock']],
                'member_only' => ['only' => ['destroy', 'update', 'edit', 'add', 'markAllRead', 'preview']],
                'post_member_only' => ['only' => ['create']]
            ]
        ];
    }

    public function stick()
    {
        ForumPost::stick($this->params()->id);
        $this->notice("Topic stickied");
        $this->redirectTo(['action' => "show", 'id' => $this->params()->id]);
    }

    public function unstick()
    {
        ForumPost::unstick($this->params()->id);
        $this->notice("Topic unstickied");
        $this->redirectTo(['action' => "show", 'id' => $this->params()->id]);
    }

    public function preview()
    {
        if ($this->params()->forum_post) {
            $this->preview = true;
            $forum_post = new ForumPost(array_merge($this->params()->forum_post, ['creator_id' => $this->current_user->id]));
            $forum_post->created_at = date('Y-m-d H:i:s');
            $this->post = $forum_post;
            $this->render(['partial' => "post"]);
        } else {
            $this->render(['text' => ""]);
        }
    }
    
    # Changed method name from "new" to "blank".
    public function blank()
    {
        $this->forum_post = new ForumPost();

        if ($this->params()->type == "alias") {
            $this->forum_post->title = "Tag Alias: ";
            $this->forum_post->body = "Aliasing ___ to ___.\n\nReason: ";
        } elseif ($this->params()->type == "impl") {
            $this->forum_post->title = "Tag Implication: ";
            $this->forum_post->body = "Implicating ___ to ___.\n\nReason: ";
        }
    }

    public function create()
    {
        $params = $this->params()->forum_post;
        if (empty($params['parent_id']) || !ctype_digit($params['parent_id']))
            $params['parent_id'] = null;
        
        $this->forum_post = ForumPost::create(array_merge($params, ['creator_id' => $this->current_user->id, 'ip_addr' => $this->request()->remoteIp()]));

        if ($this->forum_post->errors()->blank()) {
            if (!$this->params()->forum_post['parent_id']) {
                $this->notice("Forum topic created");
                $this->redirectTo(['action' => "show", 'id' => $this->forum_post->root_id()]);
            } else {
                $this->notice("Response posted");
                $this->redirectTo(["#show", 'id' => $this->forum_post->root_id(), 'page' => ceil($this->forum_post->root()->response_count / 30.0)]);
            }
        } else {
            $this->render_error($this->forum_post);
        }
    }

    public function add()
    {
    }

    public function destroy()
    {
        $this->forum_post = ForumPost::find($this->params()->id);

        if ($this->current_user->has_permission($this->forum_post, 'creator_id')) {
            $this->forum_post->destroy();
            $this->notice("Post destroyed");

            if ($this->forum_post->is_parent()) {
                $this->redirectTo("#index");
            } else {
                $this->redirectTo(["#show", 'id' => $this->forum_post->root_id()]);
            }
        } else {
            $this->notice("Access denied");
            $this->redirectTo(["#show", 'id' => $this->forum_post->root_id()]);
        }
    }

    public function edit()
    {
        $this->forum_post = ForumPost::find($this->params()->id);

        if (!$this->current_user->has_permission($this->forum_post, 'creator_id'))
            $this->access_denied();
    }

    public function update()
    {
        $this->forum_post = ForumPost::find($this->params()->id);

        if (!$this->current_user->has_permission($this->forum_post, 'creator_id')) {
            $this->access_denied();
            return;
        }

        $this->forum_post->assignAttributes(array_merge($this->params()->forum_post, ['updater_ip_addr' => $this->request()->remoteIp()]));
        if ($this->forum_post->save()) {
            $this->notice("Post updated");
            
            $page = $this->params()->page ? $this->page_number() : ceil($this->forum_post->root()->response_count / 30.0);
            $this->redirectTo(["#show", 'id' => $this->forum_post->root_id(), 'page' => $page]);
        } else {
            $this->_render_error($this->forum_post);
        }
    }

    public function show()
    {
        $this->forum_post = ForumPost::find($this->params()->id);
        $this->children   = ForumPost::where("parent_id = ?", $this->params()->id)->order("id")->paginate($this->page_number(), 30);

        if (!$this->current_user->is_anonymous() && $this->current_user->last_forum_topic_read_at < $this->forum_post->updated_at && $this->forum_post->updated_at < (time() - 3)) {
            $this->current_user->updateAttribute('last_forum_topic_read_at', $this->forum_post->updated_at);
        }

        $this->respond_to_list("forum_post");
    }

    public function index()
    {
        $this->set_title("Forum");
        
        $query = ForumPost::order("is_sticky desc, updated_at DESC");
        
        if ($this->params()->parent_id) {
            $this->forum_posts = ForumPost::where("parent_id = ?", $this->params()->parent_id)->order("is_sticky desc, updated_at DESC")->paginate($this->page_number(), 100);
        } else {
            $this->forum_posts = ForumPost::where("parent_id IS NULL")->order("is_sticky desc, updated_at DESC")->paginate($this->page_number(), 30);
        }
        
        $this->respond_to_list("forum_posts");
    }

    public function search()
    {
        if ($this->params()->query) {
            $query = '%' . str_replace(' ', '%', $this->params()->query) . '%';
            $this->forum_posts = ForumPost::where('title LIKE ? OR body LIKE ?', $query, $query)->order("id desc")->paginate($this->page_number(), 30);
        } else {
            $this->forum_posts = ForumPost::order("id desc")->paginate($this->page_number(), 30);
        }

        $this->respond_to_list("forum_posts");
    }

    public function lock()
    {
        ForumPost::lock($this->params()->id);
        $this->notice("Topic locked");
        $this->redirectTo(["#show", 'id' => $this->params()->id]);
    }

    public function unlock()
    {
        ForumPost::unlock($this->params()->id);
        $this->notice("Topic unlocked");
        $this->redirectTo(["#show", 'id' => $this->params()->id]);
    }

    public function markAllRead()
    {
        $this->current_user->updateAttribute('last_forum_topic_read_at', date('Y-m-d H:i:s'));
        $this->render('nothing');
    }
}
