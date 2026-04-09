<?php
class BatchController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'contributor_only' => ['only' => ['index', 'create', 'enqueue', 'update']]
            ]
        ];
    }

    public function index()
    {
        if ($this->current_user->is_mod_or_higher() and $this->params()->user_id == "all") {
            $user_id = null;
        } elseif ($this->current_user->is_mod_or_higher() and $this->params()->user_id) {
            $user_id = $this->params()->user_id;
        } else {
            $user_id = $this->current_user->id;
        }
        
        $query = BatchUpload::order("created_at ASC, id ASC");
        
        if ($user_id)
            $query->where("user_id = ?", $user_id);
        
        # conds[] = "batch_uploads.status = 'deleted'";
        $this->items = $query->paginate($this->page_number(), 25);
    }

    public function update()
    {
        $query = BatchUpload::none();
        
        $conds = [];
        $cond_params = [];

        if ($this->current_user->is_mod_or_higher() and $this->params()->user_id == "all") {
        } elseif ($this->current_user->is_mod_or_higher() and $this->params()->user_id) {
            $query->where("user_id = ?", $this->params()->user_id);
        } else {
            $query->where("user_id = ?", $this->current_user->id);
        }

        # Never touch active files.    This can race with the uploader.
        $query->where("not active");
        $count = 0;

        if ($this->params()->do == "pause") {
            foreach($query->where("status = 'pending'")->take() as $item) {
                $item->updateAttribute('status', "paused");
                $count++;
            };
            $this->notice("Paused $count uploads.");
        } elseif ($this->params()->do == "unpause") {
            foreach($query->where("status = 'paused'")->take() as $item) {
                $item->updateAttribute('status', "pending");
                $count++;
            };
            $this->notice("Resumed $count uploads.");
        } elseif ($this->params()->do == "retry") {
            foreach($query->where("status = 'error'")->take() as $item) {
                $item->updateAttribute('status', "pending");
                $count++;
            };

            $this->notice("Retrying $count uploads.");
        } elseif ($this->params()->do == "clear_finished") {
            foreach($query->where("(status = 'finished' or status = 'error')")->take() as $item) {
                $item->destroy();
                $count++;
            };
            
            $this->notice("Cleared $count finished uploads.");
        } elseif ($this->params()->do == "abort_all") {
            foreach($query->where("(status = 'pending' or status = 'paused')")->take() as $item) {
                $item->destroy();
                $count++;
            };

            $this->notice("Cancelled $count uploads.");
        }

        $this->redirectTo("#");
    }

    public function create()
    {
        $filter = [];
        if ($this->current_user->is_mod_or_higher() and $this->params()->user_id == "all") {
        } elseif ($this->current_user->is_mod_or_higher() and $this->params()->user_id) {
            $filter['user_id'] = $this->params()->user_id;
        } else {
            $filter['user_id'] = $this->current_user->id;
        }

        if ($this->params()->url) {
            $this->source = $this->params()->url;

            // Check upload whitelist before fetching
            $whitelist_result = UploadWhitelist::is_allowed($this->source);
            if (!$whitelist_result['allowed']) {
                $this->respond_to_error('Source URL is not on the upload whitelist: ' . $whitelist_result['reason'], '#index', ['status' => 424]);
                return;
            }

            // $text = "";
            $text = Danbooru::http_get_streaming($this->source);
            
            $this->urls = ExtractUrls::extract_image_urls($this->source, $text);
        }
    }

    public function enqueue()
    {
        # Ignore duplicate URLs across users, but duplicate URLs for the same user aren't allowed.
        # If that happens, just update the tags.
        foreach ($this->params()->files as $url) {
            $tags = !empty($this->params()->post['tags']) ? $this->params()->post['tags'] : '';
            $tags = explode(' ', $tags);
            if ($this->params()->post['rating']) {
                # Add this to the beginning, so any rating: metatags in the tags will
                # override it.
                $tags = array_merge(["rating:" . $this->params()->post['rating']], $tags);
            }
            $tags[] = "hold";
            $tags = implode(' ', array_unique($tags));

            $b = BatchUpload::where(['user_id' => $this->current_user->id, 'url' => $url])->firstOrInitialize();
            $b->tags = $tags;
            $b->ip = $this->request()->remoteIp();
            $b->save();
        }

        $this->notice(sprintf("Queued %i files", count($this->params()->files)));
        $this->redirectTo("#index");
    }
}
