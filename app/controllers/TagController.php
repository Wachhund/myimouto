<?php
class TagController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'mod_only' => ['only' => ['massEdit', 'editPreview']],
                'member_only' => ['only' => ['update', 'edit']]
            ]
        ];
    }

    public function cloud()
    {
        $this->tags = Tag::where("post_count > 0")->order("post_count DESC")->limit(100)->take()->sort(function ($a, $b) {
            $a_name = strlen($a->name);
            $b_name = strlen($b->name);
            if ($a_name == $b_name)
                return 0;
            return ($a_name < $b_name) ? -1 : 1;
        });
    }

    # Generates list of tag names matching parameter term.
    # Used by jquery.ui.autocomplete.
    public function autocompleteName()
    {
        $this->tags = Tag::where('name LIKE ?', '%' . $this->params()->term . '%')->pluck('name');
        # MI: RoR can respond to requests by reading the "Accept" request header. So even though
        # the request URL doesn't have the .json extension, it will respond JSON if the request accepts
        # it. But this is not the case here, so this action will always respond JSON.
        $this->render(['json' => $this->tags]);
    }

    public function summary()
    {
         if ($this->params()->version) {
            # HTTP caching is unreliable for XHR.    If a version is supplied, and the version
            # hasn't changed since then, return; an empty response.
            $version = Tag::get_summary_version();
            if ((int)$this->params()->version == $version) {
                $this->render(array('json' => array('version' => $version, 'unchanged' => true)));
                return;
            }
        }

        # This string is already JSON-encoded, so don't call toJson.
        $this->render(array('json' => Tag::get_json_summary()));
    }

    public function index()
    {
        $this->set_title('Tags');
        
        if ($this->params()->limit === "0")
            $limit = null;
        elseif (!$this->params()->limit)
            $limit = 50;
        else
            $limit = (int)$this->params()->limit;

        switch ($this->params()->order) {
            case "name":
                $order = "name";
                break;
            case "count":
                $order = "post_count desc";
                break;
            case "date":
                $order = "id desc";
                break;
            default:
                $order = "name";
                break;
        }

        $query = Tag::where("true");
        // $cond_params = array();

        if ($this->params()->name) {
            // $conds[] = "name LIKE ?";
            if (is_int(strpos($this->params()->name, '*')))
                $query->where('name LIKE ?', str_replace('*', '%', $this->params()->name));
            else
                $query->where('name LIKE ?', '%' . str_replace('*', '%', $this->params()->name) . '%');
        }

        if (ctype_digit($this->params()->type)) {
            $this->params()->type = (int)$this->params()->type;
            $query->where('tag_type = ?', $this->params()->type);
        }

        if (!empty($this->params()->after_id)) {
            $query->where('id >= ?', $this->params()->after_id);
        }

        if (!empty($this->params()->id)) {
            $query->where('id = ?', $this->params()->id);
        }

        $query->order($order);
        
        $this->respondTo(array(
            'html' => function () use ($order, $query) {
                $this->can_delete_tags = CONFIG()->enable_tag_deletion && current_user()->is_mod_or_higher();
                $this->tags = $query->paginate($this->page_number(), 50);
            },
            'xml' => function () use ($order, $limit, $query) {
                // $conds = implode(" AND ", $conds);
                // if ($conds == "true" && CONFIG()->web_server == "nginx" && file_exists(Rails::publicPath()."/tags.xml")) {
                    // # Special case: instead of rebuilding a list of every tag every time, cache it locally and tell the web
                    // # server to stream it directly. This only works on Nginx.
                    // $this->response()->headers()->add("X-Accel-Redirect", Rails::publicPath() . "/tags.xml");
                    // $this->render(array('nothing' => true));
                // } else {
                    $this->render(array('xml' => $query->limit($limit)->take(), 'root' => "tags"));
                // }
            },
            'json' => function () use ($order, $limit, $query) {
                $tags = $query->limit($limit)->take();
                $this->render(array('json' => $tags));
            }
        ));
    }

    public function massEdit()
    {
        $this->set_title('Mass Edit Tags');
        
        if ($this->request()->isPost()) {
            if (!$this->params()->start) {
                $this->respond_to_error("Start tag missing", ['#mass_edit'], ['status' => 424]);
                return;
            }

            if (CONFIG()->is_job_task_active('mass_tag_edit')) {
                $task = JobTask::create(['task_type' => "mass_tag_edit", 'status' => "pending", 'data' => ["start_tags" => $this->params()->start, "result_tags" => $this->params()->result, "updater_id" => $this->current_user->id, "updater_ip_addr" => $this->request()->remoteIp()]]);
                $this->respond_to_success("Mass tag edit job created", 'job_task#index');
            } else {
                Tag::mass_edit($this->params()->start, $this->params()->result, current_user()->id, $this->request()->remoteIp());
            }
        }
    }

    public function editPreview()
    {
        list($sql, $params) = Post::generate_sql($this->params()->tags, ['order' => "p.id DESC", 'limit' => 500]);
        $this->posts = Post::findBySql($sql, $params);
        $this->setLayout(false);
    }

    public function edit()
    {
        if ($this->params()->id) {
            $this->tag = Tag::where(['id' => $this->params()->id])->first() ?: new Tag();
        } else {
            $this->tag = Tag::where(['name' => $this->params()->name])->first() ?: new Tag();
        }
    }

    public function update()
    {
        $tag = Tag::where(['name' => $this->params()->tag['name']])->first();
        if ($tag)
            $tag->updateAttributes($this->params()->tag);
        $this->respond_to_success("Tag updated", '#index');
    }

    public function related()
    {
         if ($this->params()->type) {
            $this->tags = Tag::scan_tags($this->params()->tags);
            $this->tags = TagAlias::to_aliased($this->tags);
            
            $all = [];
            $tag_type = CONFIG()->tag_types[$this->params()->type];
            
            foreach ($this->tags as $x) {
                $all[$x] = array_map(function($y) use ($x) {
                        return [$y["name"], $y["post_count"]];
                }, Tag::calculate_related_by_type($x, $tag_type));
            }
            $this->tags = $all;
        } else {
            $tags = Tag::scan_tags($this->params()->tags);
            $this->patterns = $this->tags = [];
            
            foreach ($tags as $tag) {
                if (is_int(strpos($tag, "*")))
                    $this->patterns[] = $tag;
                else
                    $this->tags[] = $tag;
            }
            unset($tags, $tag);
            
            $this->tags = TagAlias::to_aliased($this->tags);
            
            $all = [];
            foreach ($this->tags as $x) {
                $all[$x] = array_map(function($y) { return [$y[0], $y[1]]; }, Tag::find_related($x));
            }
            $this->tags = $all;
            
            foreach ($this->patterns as $x) {
                $this->tags[$x] = array_map(function($y) { return [$y->name, $y->post_count]; }, Tag::where("name LIKE ?", '%' . $x . '%')->first());
            }
        }

        $this->respondTo([
            // fmt.xml do
                // # We basically have to do this by hand.
                // builder = Builder::XmlMarkup.new('indent' => 2)
                // builder.instruct!
                // xml = builder.tag!("tags") do
                    // $this->tags.each do |parent, related|
                        // builder.tag!("tag", 'name' => parent) do
                            // related.each do |tag, count|
                                // builder.tag!("tag", 'name' => tag, 'count' => count)
                            // end
                        // end
                    // end
                // end

                // $this->render(array('xml' => xml)
            // end
            'json' => function() { $this->render(['json' => json_encode($this->tags)]); }
        ]);
    }

    public function popularByDay()
    {
        if (!$this->params()->year || !$this->params()->month || !$this->params()->day ||
            !($this->day = @strtotime($this->params()->year . '-' . $this->params()->month . '-' . $this->params()->day))) {
            $this->day = strtotime('this day');
        }

        $this->tags = Tag::count_by_period(date('Y-m-d', $this->day), date('Y-m-d', strtotime('+1 day', $this->day)));
    }

    public function popularByWeek()
    {
        if (!$this->params()->year || !$this->params()->month || !$this->params()->day ||
            !($this->start = strtotime('this week', @strtotime($this->params()->year . '-' . $this->params()->month . '-' . $this->params()->day)))) {
            $this->start = strtotime('this week');
        }

        $this->end = strtotime('next week', $this->start);
        
        $this->tags = Tag::count_by_period(date('Y-m-d', $this->start), date('Y-m-d', $this->end));
    }

    public function popularByMonth()
    {
        if (!$this->params()->year || !$this->params()->month || !($this->start = @strtotime($this->params()->year . '-' . $this->params()->month . '-01'))) {
            $this->start = strtotime('first day of this month');
        }

        $this->end = strtotime('+1 month', $this->start);

        $this->tags = Tag::count_by_period(date('Y-m-d', $this->start), date('Y-m-d', $this->end));
    }

    // public function show()
    // {
        // begin
            // name = Tag.find($this->params()->id, 'select' => :name).name
        // rescue
            // raise ActionController::RoutingError.new('Not Found')        }
        // redirectTo 'controller' => :wiki, 'action' => :show, 'title' => name
    // }
    
    public function delete()
    {
        if (!CONFIG()->enable_tag_deletion) {
            $this->respond_to_error('Access denied', '#index');
            return;
        }
        
        $tag = Tag::find($this->params()->id);
        
        if ($tag)
            $tag->destroy();

        $opts = $this->params()->get();
        unset($opts['id']);

        array_unshift($opts, '#index');
        $this->respond_to_success('Tag deleted', $opts);
    }
}
