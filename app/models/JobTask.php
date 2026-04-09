<?php
class JobTask extends Rails\ActiveRecord\Base
{
    protected $data;

    static public function execute_once()
    {
        // Enqueue any due scheduled jobs before processing.
        self::enqueue_scheduled();

        $conn = self::connection();
        $taskTypes = CONFIG()->active_job_tasks;

        // Snapshot: collect all currently pending task IDs once.
        // This prevents infinite re-processing of repeat_count=-1 tasks
        // that reset to 'pending' after execution.
        $placeholders = implode(',', array_fill(0, count($taskTypes), '?'));
        $params = array_merge(
            ["SELECT id FROM job_tasks WHERE status = 'pending' AND task_type IN ({$placeholders}) ORDER BY id DESC"],
            $taskTypes
        );
        $rows = call_user_func_array([$conn, 'select'], $params);

        foreach ($rows as $row) {
            // Atomically claim each task using FOR UPDATE SKIP LOCKED
            // to prevent double execution across multiple processor instances.
            $conn->executeSql("BEGIN");
            $locked = $conn->selectRow(
                "SELECT id FROM job_tasks WHERE id = ? AND status = 'pending' FOR UPDATE SKIP LOCKED",
                $row['id']
            );

            if (!$locked) {
                $conn->executeSql("COMMIT");
                continue;
            }

            $conn->executeSql(
                "UPDATE job_tasks SET status = 'processing' WHERE id = ? AND status = 'pending'",
                $row['id']
            );
            $conn->executeSql("COMMIT");

            $task = self::find($row['id']);
            if ($task->status !== 'processing') {
                continue;
            }

            $task->execute();
            sleep(1);
        }
    }

    /**
     * Check CONFIG()->scheduled_jobs and enqueue due tasks.
     * Prevents duplicates by checking for pending/processing instances.
     * Uses DB timestamps to determine when a task last ran.
     */
    public static function enqueue_scheduled()
    {
        $scheduled = CONFIG()->scheduled_jobs ?? [];

        foreach ($scheduled as $job) {
            if (empty($job['enabled'])) {
                continue;
            }

            $taskType = $job['task_type'];
            $intervalSeconds = (int)($job['interval_seconds'] ?? 86400);

            // Skip if a pending or processing task of this type already exists.
            $pendingCount = (int)self::connection()->selectValue(
                "SELECT COUNT(*) FROM job_tasks WHERE task_type = ? AND status IN ('pending', 'processing')",
                $taskType
            );
            if ($pendingCount > 0) {
                continue;
            }

            // Check when the last finished or error task of this type ran.
            // Use DB TIMESTAMPDIFF to avoid PHP/DB clock skew.
            $elapsed = self::connection()->selectValue(
                "SELECT TIMESTAMPDIFF(SECOND, MAX(updated_at), NOW()) FROM job_tasks WHERE task_type = ? AND status IN ('finished', 'error')",
                $taskType
            );

            // $elapsed is NULL if no previous runs exist (first-time enqueue).
            if ($elapsed !== null && (int)$elapsed < $intervalSeconds) {
                continue;
            }

            // Enqueue a single-shot task.
            $task = new self();
            $task->task_type = $taskType;
            $task->status = 'pending';
            $task->repeat_count = 0;
            $task->data_as_json = json_encode($job['data'] ?? new \stdClass());
            $task->save();

            Rails::log()->info(sprintf('Scheduled job enqueued: %s', $taskType));
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

            case "exception_log_prune":
                return $this->status_message ?: 'idle';

            case "user_deletion_cleanup":
                return $this->status_message ?: 'idle';

            case "forum_digest_send":
                return $this->status_message ?: 'idle';

            case "api_key_expiration_check":
                return $this->status_message ?: 'idle';
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

    /**
     * AC-2: Prune exception logs older than configured retention period.
     */
    public function execute_exception_log_prune()
    {
        $days = CONFIG()->exception_log_retention_days ?? 90;
        $totalDeleted = 0;

        // Delete in batches (prune() uses LIMIT 1000 internally).
        // Loop until no more rows are deleted.
        do {
            $stmt = ExceptionLog::prune($days);
            $deleted = $stmt->rowCount();
            $totalDeleted += $deleted;
        } while ($deleted > 0);

        $message = sprintf('Pruned %d exception logs older than %d days', $totalDeleted, $days);
        $this->updateAttribute('status_message', $message);
        Rails::log()->info($message);
    }

    /**
     * AC-3: Process pending user deletion cleanup (votes, favorites, subscriptions).
     * Runs bulk DELETEs in batches to avoid long locks.
     */
    public function execute_user_deletion_cleanup()
    {
        $conn = self::connection();
        $batchLimit = 10000;
        $processedUsers = 0;
        $totalDeleted = 0;

        // Find pending and failed (retry) cleanup events. Failed events are retried
        // up to 3 times — after that they stay 'failed' and require manual intervention.
        $maxRetries = 3;
        $events = $conn->select(
            "SELECT id, target_user_id, cleanup_status, cleanup_retries FROM user_deletion_events " .
            "WHERE cleanup_status IN ('pending', 'failed') AND cleanup_retries < ? ORDER BY id ASC LIMIT 50",
            $maxRetries
        );

        foreach ($events as $event) {
            $userId = (int)$event['target_user_id'];
            $eventId = (int)$event['id'];
            $userDeleted = 0;

            try {
                // Delete post_votes in batches.
                do {
                    $stmt = $conn->executeSql(
                        "DELETE FROM post_votes WHERE user_id = ? LIMIT {$batchLimit}",
                        $userId
                    );
                    $deleted = $stmt->rowCount();
                    $userDeleted += $deleted;
                } while ($deleted >= $batchLimit);

                // Delete favorites in batches.
                do {
                    $stmt = $conn->executeSql(
                        "DELETE FROM favorites WHERE user_id = ? LIMIT {$batchLimit}",
                        $userId
                    );
                    $deleted = $stmt->rowCount();
                    $userDeleted += $deleted;
                } while ($deleted >= $batchLimit);

                // Delete tag_subscriptions in batches.
                do {
                    $stmt = $conn->executeSql(
                        "DELETE FROM tag_subscriptions WHERE user_id = ? LIMIT {$batchLimit}",
                        $userId
                    );
                    $deleted = $stmt->rowCount();
                    $userDeleted += $deleted;
                } while ($deleted >= $batchLimit);

                // Mark event as completed.
                $conn->executeSql(
                    "UPDATE user_deletion_events SET cleanup_status = 'completed' WHERE id = ?",
                    $eventId
                );

                $processedUsers++;
                $totalDeleted += $userDeleted;
            } catch (\Exception $e) {
                // Increment retry counter and mark as failed.
                // Events exceeding max retries are excluded from future queries.
                $conn->executeSql(
                    "UPDATE user_deletion_events SET cleanup_status = 'failed', cleanup_retries = cleanup_retries + 1 WHERE id = ?",
                    $eventId
                );
                Rails::log()->warning(sprintf(
                    'User deletion cleanup failed for event #%d (user #%d, retry %d/%d): %s',
                    $eventId,
                    $userId,
                    (int)($event['cleanup_retries'] ?? 0) + 1,
                    $maxRetries,
                    $e->getMessage()
                ));
            }
        }

        $message = sprintf(
            'Cleanup complete: %d users processed, %d records deleted',
            $processedUsers,
            $totalDeleted
        );
        $this->updateAttribute('status_message', $message);
        Rails::log()->info($message);
    }

    /**
     * AC-4: Forum digest sending hook (implementation in PROJ-41).
     */
    public function execute_forum_digest_send()
    {
        // Stub: call digest service when PROJ-41 is implemented.
        if (class_exists('MyImouto\\Forum\\DigestService')) {
            \MyImouto\Forum\DigestService::sendPendingDigests();
            $this->updateAttribute('status_message', 'Digest send completed');
        } else {
            $this->updateAttribute('status_message', 'DigestService not available (PROJ-41 pending)');
        }
    }

    /**
     * AC-5: API key expiration warning hook (implementation in PROJ-43).
     */
    public function execute_api_key_expiration_check()
    {
        // Stub: call expiration check service when PROJ-43 is implemented.
        if (class_exists('MyImouto\\Auth\\ApiKeyExpirationService')) {
            \MyImouto\Auth\ApiKeyExpirationService::checkAndNotify();
            $this->updateAttribute('status_message', 'Expiration check completed');
        } else {
            $this->updateAttribute('status_message', 'ApiKeyExpirationService not available (PROJ-43 pending)');
        }
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
