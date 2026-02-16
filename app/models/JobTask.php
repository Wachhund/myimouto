<?php
class JobTask extends Rails\ActiveRecord\Base
{
    protected $data;

    static public function execute_once()
    {
        foreach (self::where('status = "pending" AND task_type IN (?)', CONFIG()->active_job_tasks)->order("id desc")->take() as $task) {
            $task->execute();
            sleep(1);
        }
    }

    public function pretty_data()
    {
        switch ($this->task_type) {
            case "mass_tag_edit":
                $start = $this->data["start_tags"];
                $result = $this->data["result_tags"];
                $user = User::find_name($this->data["updater_id"]);

                return "start: ".$start.", result: ".$result.", user: ".$user;
                break;

            case "approve_tag_alias":
                $ta = TagAlias::where('id', $this->data->id)->first();
                if (!$ta) {
                    Rails::log()->warning(sprintf("Tag alias #%s couldn't be found for job task #%s. Destroying job task.", $this->data->id, $this->id));
                    $this->destroy();
                    return "Error - Tag alias doesn't exist";
                }
                return "start: " . $ta->name . ", result: " . $ta->alias_name();
                break;

            case "approve_tag_implication":
                $ti = TagImplication::where('id', $this->data->id)->first();
                if (!$ti) {
                    Rails::log()->warning(sprintf("Tag implication #%s couldn't be found for job task #%s. Destroying job task.", $this->data->id, $this->id));
                    $this->destroy();
                    return "Error - Tag implication doesn't exist";
                }
                return "start: " . $ti->predicate->name . ", result: " . $ti->consequent->name;
                break;

            case "calculate_tag_subscriptions":
                if (CONFIG()->tag_subscription_delay && isset($this->data->last_run)) {
                    $nextRun = date('Y-m-d H:i:s', strtotime('+' . CONFIG()->tag_subscription_delay, strtotime($this->data->last_run)));
                } else {
                    $nextRun = 'imminent';
                }

                $lastRun = (isset($this->data->last_run) ? $this->data->last_run : 'never');

                return "last run: " . $lastRun . '; next run: ' . $nextRun;

            // case "upload_posts_to_mirrors"
                // ret = ""
                // if data["post_id"]
                    // ret << "uploading post_id #{data["post_id"]}"
                // elsif data["left"]
                    // ret << "sleeping"
                // else
                    // ret << "idle"
                // end
                // ret << (" (%i left) " % data["left"]) if data["left"]
                // ret

            case "periodic_maintenance":
                if ($this->status == "processing")
                    return !empty($this->data->step) ? $this->data->step : 'unknown';
                elseif ($this->status != "error") {
                    $next_run = (!empty($this->data->next_run) ? strtotime($this->data->next_run) : 0) - time();
                    $next_run_in_minutes = $next_run / 60;
                    if ($next_run_in_minutes > 0)
                        $eta = "next run in ".round($next_run_in_minutes / 60.0)." hours";
                    else
                        $eta = "next run imminent";
                    return "sleeping (".$eta.")";
                }
                break;

            case "external_data_search":
                return 'last updated post id: ' . (isset($this->data->last_post_id) ? $this->data->last_post_id : '(none)');
                break;

            case "upload_batch_posts":
                if ($this->status == "pending")
                    return "idle";
                elseif ($this->status == "processing") {
                    $user = User::find_name($this->data->user_id);
                    return "uploading " . $this->data->url . " for " . $user;
                }
                break;
            // case "update_post_frames"
                // if status == "pending" then
                    // return "idle"
                // elsif status == "processing" then
                    // return data["status"]
                // end
            // end
        }
    }

    public function execute()
    {
        if ($this->repeat_count > 0)
            $count = $this->repeat_count - 1;
        else
            $count = $this->repeat_count;

        Rails::systemExit()->register(function(){
            if ($this->status == 'processing')
                $this->updateAttribute('status', 'pending');
        }, 'job_task');

        try {
            $this->updateAttribute('status', "processing");
            $task_method = 'execute_'.$this->task_type;
            $this->$task_method();

            if ($count == 0)
                $this->updateAttribute('status', "finished");
            else {
                // This is necessary due to a bug with Rails that won't clear changed attributes,
                // so when 'status' is changed back to 'pending', the system will think the attribute
                // is being reversed to its previous value, and will remove it from the changedAttributes,
                // array, therefore the new value 'pending' won't be set and will stay as 'processing'.
                $this->clearChangedAttributes();

                $this->updateAttributes(array('status' => "pending", 'repeat_count' => $count));
            }
        } catch (Exception $x) {
            $text  = "";
            $text .= "Error executing job: " . $this->task_type . "\n";
            $text .= "        \n";
            $text .= $x->getTraceAsString();
            Rails::log()->warning($text);

            $this->updateAttributes(['status' => "error", 'status_message' => get_class($x) . ': ' . $x->getMessage()]);
            throw $x;
        }
    }

    public function execute_periodic_maintenance()
    {
        if (!empty($this->data->next_run) && $this->data->next_run > time('Y-m-d H:i:s'))
            return;

        $this->update_data(array("step" => "recalculating post count"));
        Post::recalculate_row_count();
        $this->update_data(array("step" => "recalculating tag post counts"));
        Tag::recalculate_post_count();
        $this->update_data(array("step" => "purging old tags"));
        Tag::purge_tags();

        $next_run = strtotime('+6 hours');
        $this->update_data(array("next_run" => date('Y-m-d H:i:s', $next_run), "step" => null));
    }

    public function execute_external_data_search()
    {
        # current_user will be needed to save post history.
        # Set the first admin as current user.
        User::set_current_user(User::where('level = ?', CONFIG()->user_levels['Admin'])->first());

        if (empty($this->data->last_post_id))
            $this->data->last_post_id = 0;

        $post_id = $this->data->last_post_id + 1;

        $config = array_merge([
            'servers'    => [],
            'interval'   => 3,
            'source'     => true,
            'merge_tags' => true,
            'limit'      => 100,
            'set_rating' => false,
            'exclude_tags' => [],
            'similarity' => 90
        ], CONFIG()->external_data_search_config);

        $limit          = $config['limit'];
        $interval       = $config['interval'];
        $search_options = [
            'type'         => 'post',
            'data_search'  => true,
            'services'     => $config['servers'],
            'threshold'    => $config['similarity']
        ];

        $post_count = !$limit ? -1 : 0;

        while ($post_count < $limit) {
            if (!$post = Post::where('id >= ? AND status != "deleted"', $post_id)->order('id ASC')->first()) {
                break;
            }

            $search_options['source'] = $post;
            $new_tags = [];
            $source = null;

            $external_posts = SimilarImages::similar_images($search_options)['posts_external'];

            $rating_set = false;
            foreach ($external_posts as $ep) {
                if (!$rating_set && $config['set_rating'] && $ep->rating) {
                    $post->rating = $ep->rating;
                    $rating_set = true;
                }

                if ($config['source'] && !$source && $ep->source) {
                    $source = $ep->source;
                }
                $new_tags = array_merge($new_tags, explode(' ', $ep->tags));
            }

            # Exclude tags.
            $new_tags = array_diff($new_tags, $config['exclude_tags']);

            if ($config['merge_tags']) {
                $new_tags = array_merge($new_tags, $post->tags);
            }

            $new_tags = array_filter(array_unique($new_tags));
            $post->new_tags = $new_tags;

            if ($source); {
                $post->source = $source;
            }

            $post->save();

            if ($limit) {
                $post_count++;
            }

            $this->update_data(['last_post_id' => $post->id]);
            $post_id = $post->id + 1;

            if ($config['interval']) {
                sleep($config['interval']);
            }
        }
    }

    public function execute_upload_batch_posts()
    {
        $upload = BatchUpload::where("status = 'pending'")->order("id ASC")->first();
        if (!$upload)
            return;

        $this->updateAttributes(['data' => ['id' => $upload->id, 'user_id' => $upload->user_id, 'url' => $upload->url]]);
        $upload->run();
    }

    public function execute_approve_tag_alias()
    {
        $ta = TagAlias::find($this->data->id);
        $updater_id = $this->data->updater_id;
        $updater_ip_addr = $this->data->updater_ip_addr;
        $ta->approve($updater_id, $updater_ip_addr);
    }

    public function execute_approve_tag_implication()
    {
        $ti = TagImplication::find($this->data->id);
        $updater_id = $this->data->updater_id;
        $updater_ip_addr = $this->data->updater_ip_addr;
        $ti->approve($updater_id, $updater_ip_addr);
    }

    public function execute_calculate_tag_subscriptions()
    {
        if (CONFIG()->tag_subscription_delay) {
            if (Rails::cache()->read("delay-tag-sub-calc")) {
                return;
            }

            Rails::cache()->write("delay-tag-sub-calc", 1, ['expires_in' => CONFIG()->tag_subscription_delay]);
        }

        TagSubscription::process_all();
        
        $this->updateAttributes(['data' => ['last_run' => date('Y-m-d H:i:s')]]);
    }

    protected function init()
    {
        $this->setData($this->data_as_json ? json_decode($this->data_as_json) : new stdClass());
    }

    public function setData($data)
    {
        $this->data_as_json = json_encode($data);
        $this->data = (object)$data;
    }

    private function update_data($data)
    {
        $data = array_merge((array)$this->data, $data);
        $this->updateAttributes(array('data' => $data));
    }
}
