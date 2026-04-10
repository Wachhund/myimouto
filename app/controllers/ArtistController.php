<?php

# encoding: utf-8
class ArtistController extends ApplicationController
{
    protected function init()
    {
        $this->helper('Post', 'Wiki');
    }

    protected function filters()
    {
        return [
            'before' => [
                'post_member_only' => ['only' => ['create', 'update']],
                'post_privileged_only' => ['only' => ['destroy']],
            ],
        ];
    }

    public function preview()
    {
        $this->render(['inline' => "<h4>Preview</h4><?= \$this->format_text(\$this->params()->artist['notes']) ?>"]);
    }

    public function destroy()
    {
        $this->artist = Artist::find($this->params()->id);

        if ($this->request()->isPost()) {
            if ($this->params()->commit == "Yes") {
                $this->artist->destroy();
                $this->respond_to_success("Artist deleted", ['#index', 'page' => $this->page_number()]);
            } else {
                $this->redirectTo(['#index', 'page' => $this->page_number()]);
            }
        }
    }

    public function update()
    {
        if ($this->request()->isPost()) {
            if ($this->params()->commit == "Cancel") {
                $this->redirectTo(['#show', 'id' => $this->params()->id]);
                return;
            }

            $artist = Artist::find($this->params()->id);
            $artist->updateAttributes(array_merge($this->params()->artist, ['updater_ip_addr' => $this->request()->remoteIp(), 'updater_id' => current_user()->id]));

            if ($artist->errors()->blank()) {
                $this->respond_to_success("Artist updated", ['#show', 'id' => $artist->id]);
            } else {
                $this->respond_to_error($artist, ['#update', 'id' => $artist->id]);
            }
        } else {
            $this->artist = Artist::find($this->params()->id);
        }
    }

    public function create()
    {
        if ($this->request()->isPost()) {
            $artist = Artist::create(array_merge($this->params()->artist, ['updater_ip_addr' => $this->request()->remoteIp(), 'updater_id' => current_user()->id]));

            if ($artist->errors()->blank()) {
                $this->respond_to_success("Artist created", ['#show', 'id' => $artist->id]);
            } else {
                $this->respond_to_error($artist, ['#create', 'alias_id' => $this->params()->alias_id]);
            }
        } else {
            $this->artist = new Artist();

            if ($this->params()->name) {
                $this->artist->name = $this->params()->name;

                $post = Post::where("tags.name = ? AND source LIKE 'http%'", $this->params()->name)
                            ->joins('JOIN posts_tags pt ON posts.id = pt.post_id JOIN tags ON pt.tag_id = tags.id')
                            ->select('posts.*')->first();
                if ($post && $post->source) {
                    $this->artist->urls = $post->source;
                }
            }

            if ($this->params()->alias_id) {
                $this->artist->alias_id = $this->params()->alias_id;
            }
        }
    }

    public function index()
    {
        $this->set_title('Artists');

        if ($this->params()->order == "date") {
            $order = "artists.updated_at DESC";
        } else {
            $order = "artists.name";
        }

        $aliases_only = $this->params()->name == 'aliases_only';

        $query = Artist::none();

        $page = $this->page_number();
        $per_page = 50;

        if ($this->params()->name && !$aliases_only) {
            $query = Artist::generate_sql($this->params()->name);
        } elseif ($this->params()->url && !$aliases_only) {
            $query = Artist::generate_sql($this->params()->url);
        } else {
            $query = Artist::order($order);
        }

        if (!$this->params()->name && !$this->params()->url) {
            $query->where(($aliases_only ? '!' : '') . 'ISNULL(artists.alias_id)');
        }

        $this->artists = $query->paginate($page, $per_page);

        $this->respond_to_list("artists");
    }

    public function show()
    {
        if ($this->params()->name) {
            $this->artist = Artist::where(['name' => $this->params()->name])->first();
        } else {
            $this->artist = Artist::find($this->params()->id);
        }

        if (!$this->artist) {
            $this->redirectTo(['#create', 'name' => $this->params()->name]);
        } else {
            $this->redirectTo(['wiki#show', 'title' => $this->artist->name]);
        }
    }
}
