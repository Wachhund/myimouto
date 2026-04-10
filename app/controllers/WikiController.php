<?php

class WikiController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'post_member_only' => ['only' => ['update', 'create', 'edit', 'revert']],
                'mod_only' => ['only' => ['lock', 'unlock', 'destroy', 'rename']],
            ],
        ];
    }

    protected function init()
    {
        $this->helper('Post');
    }

    public function destroy()
    {
        $page = WikiPage::find_page($this->params()->title);
        $page->destroy();
        $this->respond_to_success("Page deleted", ['action' => "show", 'title' => $this->params()->title]);
    }

    public function lock()
    {
        $page = WikiPage::find_page($this->params()->title);
        $page->lock();
        $this->respond_to_success("Page locked", ['action' => "show", 'title' => $this->params()->title]);
    }

    public function unlock()
    {
        $page = WikiPage::find_page($this->params()->title);
        $page->unlock();
        $this->respond_to_success("Page unlocked", ['action' => "show", 'title' => $this->params()->title]);
    }

    public function index()
    {
        $this->set_title('Wiki');

        $this->params = $this->params();
        if ($this->params()->order == "date") {
            $order = "updated_at DESC";
        } else {
            $order = "lower(title)";
        }

        $limit = $this->params()->limit ?: 25;
        $query = $this->params()->query ?: "";

        $sql_query = WikiPage::order($order)->page($this->page_number())->perPage($limit);

        if ($query) {
            if (preg_match('/^title:/', $query)) {
                $sql_query->where("title LIKE ?", "%" . substr($query, 6) . "%");
            } else {
                $query = str_replace(' ', '%', $query);
                $sql_query->where("body LIKE ?", '%' . $query . '%');
            }
        }

        $this->wiki_pages = $sql_query->paginate();

        $this->respond_to_list("wiki_pages");
    }

    public function preview()
    {
        $this->setLayout(false);
        $this->render(['inline' => '<?= $this->format_text($this->params()->body) ?>']);
    }

    public function add()
    {
        $this->wiki_page = new WikiPage();
        $this->wiki_page->title = $this->params()->title ?: "Title";
    }

    public function create()
    {
        $permitted = array_intersect_key($this->params()->wiki_page ?: [], array_flip(['title', 'body']));
        $page = WikiPage::create(array_merge($permitted, ['ip_addr' => $this->request()->remoteIp(), 'user_id' => $this->current_user->id]));

        if ($page->errors()->blank()) {
            $this->respond_to_success("Page created", ["#show", 'title' => $page->title], ['location' => $this->urlFor(["#show", 'title' => $page->title])]);
        } else {
            $this->respond_to_error($page, "#index");
        }
    }

    public function edit()
    {
        if (!$this->params()->title) {
            $this->render(['text' => "no title specified"]);
        } else {
            $this->wiki_page = WikiPage::find_page($this->params()->title, $this->params()->version);

            if (!$this->wiki_page) {
                $this->redirectTo(["#add", 'title' => $this->params()->title]);
            }
        }
    }

    public function update()
    {
        $this->page = WikiPage::find_page(($this->params()->title ?: $this->params()->wiki_page['title']));

        if ($this->page->is_locked) {
            $this->respond_to_error("Page is locked", ['action' => "show", 'title' => $this->page->title], ['status' => 422]);
        } else {
            $permitted = array_intersect_key($this->params()->wiki_page ?: [], array_flip(['title', 'body']));
            if ($this->page->updateAttributes(array_merge($permitted, ['ip_addr' => $this->request()->remoteIp(), 'user_id' => $this->current_user->id]))) {
                $this->respond_to_success("Page updated", ['action' => "show", 'title' => $this->page->title]);
            } else {
                $this->respond_to_error($this->page, ['action' => "show", 'title' => $this->page->title]);
            }
        }
    }

    public function show()
    {
        if (!$this->params()->title) {
            $this->render(['text' => "no title specified"]);
            return;
        }
        $this->title  = $this->params()->title;
        $this->page   = WikiPage::find_page($this->params()->title, $this->params()->version);
        $this->posts  = Post::find_by_tag_join($this->params()->title, ['limit' => 8])->select(function ($x) {
            return $x->can_be_seen_by(current_user());
        });
        $this->artist = Artist::where(['name' => $this->params()->title])->first();
        $this->tag    = Tag::where(['name' => $this->params()->title])->first();
        $this->set_title(str_replace("_", " ", $this->params()->title));
    }

    public function revert()
    {
        $this->page = WikiPage::find_page($this->params()->title);
        if ($this->page->is_locked) {
            $this->respond_to_error("Page is locked", ['action' => "show", 'title' => $this->params()->title], ['status' => 422]);
        } else {
            $this->page->revertTo($this->params()->version);
            $this->page->ip_addr = $this->request()->remoteIp();
            $this->page->user_id = $this->current_user->id;

            if ($this->page->save()) {
                $this->respond_to_success("Page reverted", ["#show", 'title' => $this->params()->title]);
            } else {
                $error = ($msgs = $this->page->errors()->fullMessages()) ? array_shift($msgs) : "Error reverting page";
                $this->respond_to_error($error, ['action' => 'show', 'title' => $this->params()->title]);
            }
        }
    }

    public function recentChanges()
    {
        $this->set_title('Recent Changes');

        if ($this->params()->user_id) {
            $this->params()->user_id = $this->params()->user_id;
            $this->wiki_pages = WikiPage::where("user_id = ?", $this->params()->user_id)->order("updated_at DESC")->paginate($this->page_number(), (int) $this->params()->per_page ?: 25);
        } else {
            $this->wiki_pages = WikiPage::order("updated_at DESC")->paginate($this->page_number(), (int) $this->params()->per_page ?: 25);
        }
        $this->respond_to_list("wiki_pages");
    }

    public function history()
    {
        $this->set_title('Wiki History');

        if ($this->params()->title) {
            $wiki = WikiPage::find_by_title($this->params()->title);
            $wiki_id = $wiki ? $wiki->id : null;
        } elseif ($this->params()->id) {
            $wiki_id = $this->params()->id;
        } else {
            $wiki_id = null;
        }

        $this->wiki_pages = WikiPageVersion::where('wiki_page_id = ?', $wiki_id)->order('version DESC')->take();

        $this->respond_to_list("wiki_pages");
    }

    public function diff()
    {
        $this->set_title('Wiki Diff');

        if ($this->params()->redirect) {
            $this->redirectTo(['action' => "diff", 'title' => $this->params()->title, 'from' => $this->params()->from, 'to' => $this->params()->to]);
            return;
        }

        if (!$this->params()->title || !$this->params()->to || !$this->params()->from) {
            $this->notice("No title was specificed");
            $this->redirectTo("#index");
            return;
        }

        $this->oldpage = WikiPage::find_page($this->params()->title, $this->params()->from);
        $this->difference = $this->oldpage->diff($this->params()->to);
    }

    public function rename()
    {
        $this->wiki_page = WikiPage::find_page($this->params()->title);
    }
}
