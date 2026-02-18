<?php
use MyImouto\PostReplacement\ApplyService;
use MyImouto\PostReplacement\NotificationService;
use MyImouto\PostReplacement\StagingService;

class PostReplacementController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'member_only',
                'verify_post_replacement_csrf' => ['only' => ['create', 'approve', 'reject', 'destroy']]
            ]
        ];
    }

    public function index()
    {
        $query = PostReplacement::order('id DESC');
        if (!$this->is_staff_user(current_user())) {
            $query->where('creator_id = ?', current_user()->id);
        }

        $post_id = (int)$this->params()->post_id;
        if ($post_id > 0) {
            $query->where('post_id = ?', $post_id);
        }

        $status = $this->normalize_status($this->params()->status);
        if ($status) {
            $query->where('status = ?', $status);
        }

        $this->post_replacements = $query->paginate($this->page_number(), 25);
        $this->csrf_token = $this->form_authenticity_token();

        $this->respondTo([
            'html',
            'json' => function() {
                $payload = [];
                foreach ($this->post_replacements as $replacement) {
                    $payload[] = $replacement->asJson();
                }
                $this->render(['json' => $payload]);
            },
            'xml' => function() {
                $this->render(['xml' => ['count' => $this->post_replacements->size()], 'root' => 'post_replacements']);
            }
        ]);
    }

    public function create()
    {
        if (!$this->can_submit_replacements(current_user())) {
            $this->access_denied();
            return;
        }

        $post = $this->find_post_from_params();
        if (!$post) {
            return;
        }

        $payload = $this->replacement_payload();
        $source_url = trim((string)$payload['source_url']);
        $reason = trim((string)$payload['reason']);
        if ($reason === '') {
            $reason = null;
        }

        try {
            $staged_upload = StagingService::stageUploadFromGlobals('post_replacement', 'file');
        } catch (RuntimeException $e) {
            $this->respond_to_error($e->getMessage(), ['post#show', 'id' => $post->id], ['status' => 424]);
            return;
        }

        if (!$staged_upload && $source_url === '') {
            $this->respond_to_error(
                'Replacement requires either an upload file or a source URL',
                ['post#show', 'id' => $post->id],
                ['status' => 424]
            );
            return;
        }

        $replacement = null;
        $duplicate_message = 'Post already has a pending replacement request';
        try {
            PostReplacement::transaction(function() use ($post, $reason, $source_url, $staged_upload, &$replacement, $duplicate_message) {
                $this->lock_post_for_update((int)$post->id);
                if (PostReplacement::where('post_id = ? AND status = ?', $post->id, PostReplacement::STATUS_PENDING)->exists()) {
                    throw new RuntimeException($duplicate_message);
                }

                $replacement = PostReplacement::create([
                    'post_id' => $post->id,
                    'creator_id' => current_user()->id,
                    'status' => PostReplacement::STATUS_PENDING,
                    'reason' => $reason,
                    'source_url' => $source_url ?: null,
                    'replacement_file_path' => $staged_upload ? $staged_upload['path'] : null,
                    'replacement_file_name' => $staged_upload ? $staged_upload['name'] : null
                ]);
            });
        } catch (RuntimeException $e) {
            if ($staged_upload) {
                StagingService::cleanup($staged_upload['path']);
            }

            $status_code = ((string)$e->getMessage() === $duplicate_message) ? 423 : 424;
            $this->respond_to_error(
                $e->getMessage(),
                ['post_replacement#index', 'post_id' => $post->id],
                ['status' => $status_code]
            );
            return;
        }

        if (!$replacement) {
            if ($staged_upload) {
                StagingService::cleanup($staged_upload['path']);
            }
            $this->respond_to_error('Unable to create replacement request', ['post#show', 'id' => $post->id], ['status' => 500]);
            return;
        }

        if (!$replacement->errors()->blank()) {
            if ($staged_upload) {
                StagingService::cleanup($staged_upload['path']);
            }
            $this->respond_to_error($replacement, ['post#show', 'id' => $post->id]);
            return;
        }

        NotificationService::emitCreated($replacement);

        $this->respond_to_success(
            'Replacement request submitted',
            ['post_replacement#index', 'post_id' => $post->id],
            ['api' => ['post_replacement' => $replacement->asJson()]]
        );
    }

    public function approve()
    {
        $replacement = $this->find_replacement_from_params();
        if (!$replacement) {
            return;
        }

        if (!$replacement->can_be_moderated_by(current_user())) {
            $this->access_denied();
            return;
        }

        if ((string)$replacement->status === PostReplacement::STATUS_APPROVED) {
            $this->respond_to_success(
                'Replacement already approved',
                ['post_replacement#index', 'post_id' => $replacement->post_id],
                ['api' => ['post_replacement' => $replacement->asJson()]]
            );
            return;
        }

        if ((string)$replacement->status !== PostReplacement::STATUS_PENDING) {
            $this->respond_to_error(
                'Replacement is not in pending state',
                ['post_replacement#index', 'post_id' => $replacement->post_id],
                ['status' => 424]
            );
            return;
        }

        $approved = null;
        $preloaded_source_path = null;
        $resolved_upload = null;
        if (empty($replacement->replacement_file_path) && !empty($replacement->source_url)) {
            try {
                $resolved_upload = StagingService::downloadFromSource($replacement->source_url);
                $preloaded_source_path = $resolved_upload['path'];
            } catch (RuntimeException $e) {
                $this->respond_to_error($e->getMessage(), ['post_replacement#index'], ['status' => 424]);
                return;
            } catch (Exception $e) {
                $this->respond_to_error($e->getMessage(), ['post_replacement#index']);
                return;
            }
        }

        try {
            PostReplacement::transaction(function() use ($replacement, &$approved, $resolved_upload) {
                $current = $this->lock_replacement_for_update((int)$replacement->id);
                if ((string)$current->status !== PostReplacement::STATUS_PENDING) {
                    throw new RuntimeException('Replacement is no longer pending');
                }

                $approved = ApplyService::approve(
                    $current,
                    current_user(),
                    $this->params()->moderation_reason,
                    $resolved_upload
                );
            });
        } catch (RuntimeException $e) {
            $this->respond_to_error($e->getMessage(), ['post_replacement#index'], ['status' => 424]);
            return;
        } catch (Exception $e) {
            $this->respond_to_error($e->getMessage(), ['post_replacement#index']);
            return;
        } finally {
            if ($preloaded_source_path) {
                StagingService::cleanup($preloaded_source_path);
            }
        }

        $this->respond_to_success(
            'Replacement approved',
            ['post#show', 'id' => $approved->post_id],
            ['api' => ['post_replacement' => $approved->asJson()]]
        );
    }

    public function reject()
    {
        $replacement = $this->find_replacement_from_params();
        if (!$replacement) {
            return;
        }

        if (!$replacement->can_be_moderated_by(current_user())) {
            $this->access_denied();
            return;
        }

        if ((string)$replacement->status === PostReplacement::STATUS_REJECTED) {
            $this->respond_to_success(
                'Replacement already rejected',
                ['post_replacement#index', 'post_id' => $replacement->post_id],
                ['api' => ['post_replacement' => $replacement->asJson()]]
            );
            return;
        }

        $rejected = null;
        $already_rejected = false;
        $did_transition = false;
        $staged_path = null;
        try {
            PostReplacement::transaction(function() use ($replacement, &$rejected, &$already_rejected, &$did_transition, &$staged_path) {
                $current = $this->lock_replacement_for_update((int)$replacement->id);

                if ((string)$current->status === PostReplacement::STATUS_REJECTED) {
                    $already_rejected = true;
                    $rejected = $current;
                    return;
                }

                if ((string)$current->status !== PostReplacement::STATUS_PENDING) {
                    throw new RuntimeException('Replacement is not in pending state');
                }

                $staged_path = !empty($current->replacement_file_path) ? (string)$current->replacement_file_path : null;
                $current->replacement_file_path = null;
                $current->replacement_file_name = null;
                $current->status = PostReplacement::STATUS_REJECTED;
                $current->reviewed_by_id = current_user()->id;
                $current->reviewed_at = date('Y-m-d H:i:s');
                $current->moderation_reason = $this->normalize_optional_text($this->params()->moderation_reason);

                if (!$current->save()) {
                    throw new RuntimeException($current->errors()->fullMessages(', '));
                }

                $did_transition = true;
                $rejected = $current;
            });
        } catch (RuntimeException $e) {
            $this->respond_to_error($e->getMessage(), ['post_replacement#index'], ['status' => 424]);
            return;
        } catch (Exception $e) {
            $this->respond_to_error($e->getMessage(), ['post_replacement#index']);
            return;
        }

        if ($already_rejected || !$did_transition) {
            $this->respond_to_success(
                'Replacement already rejected',
                ['post_replacement#index', 'post_id' => $rejected->post_id],
                ['api' => ['post_replacement' => $rejected->asJson()]]
            );
            return;
        }

        if ($staged_path) {
            StagingService::cleanup($staged_path);
        }

        NotificationService::emitModerationOutcome($rejected);

        $this->respond_to_success(
            'Replacement rejected',
            ['post_replacement#index', 'post_id' => $rejected->post_id],
            ['api' => ['post_replacement' => $rejected->asJson()]]
        );
    }

    public function destroy()
    {
        $replacement = $this->find_replacement_from_params();
        if (!$replacement) {
            return;
        }

        if (!$replacement->can_be_moderated_by(current_user())) {
            $this->access_denied();
            return;
        }

        if ((string)$replacement->status === PostReplacement::STATUS_DELETED) {
            $this->respond_to_success(
                'Replacement already deleted',
                ['post_replacement#index', 'post_id' => $replacement->post_id],
                ['api' => ['post_replacement' => $replacement->asJson()]]
            );
            return;
        }

        $deleted = null;
        $already_deleted = false;
        $did_transition = false;
        $staged_path = null;
        try {
            PostReplacement::transaction(function() use ($replacement, &$deleted, &$already_deleted, &$did_transition, &$staged_path) {
                $current = $this->lock_replacement_for_update((int)$replacement->id);

                if ((string)$current->status === PostReplacement::STATUS_DELETED) {
                    $already_deleted = true;
                    $deleted = $current;
                    return;
                }

                if ((string)$current->status !== PostReplacement::STATUS_PENDING) {
                    throw new RuntimeException('Replacement is not in pending state');
                }

                $staged_path = !empty($current->replacement_file_path) ? (string)$current->replacement_file_path : null;
                $current->replacement_file_path = null;
                $current->replacement_file_name = null;
                $current->status = PostReplacement::STATUS_DELETED;
                $current->reviewed_by_id = current_user()->id;
                $current->reviewed_at = date('Y-m-d H:i:s');
                $current->moderation_reason = $this->normalize_optional_text($this->params()->moderation_reason);

                if (!$current->save()) {
                    throw new RuntimeException($current->errors()->fullMessages(', '));
                }

                $did_transition = true;
                $deleted = $current;
            });
        } catch (RuntimeException $e) {
            $this->respond_to_error($e->getMessage(), ['post_replacement#index'], ['status' => 424]);
            return;
        } catch (Exception $e) {
            $this->respond_to_error($e->getMessage(), ['post_replacement#index']);
            return;
        }

        if ($already_deleted || !$did_transition) {
            $this->respond_to_success(
                'Replacement already deleted',
                ['post_replacement#index', 'post_id' => $deleted->post_id],
                ['api' => ['post_replacement' => $deleted->asJson()]]
            );
            return;
        }

        if ($staged_path) {
            StagingService::cleanup($staged_path);
        }

        NotificationService::emitModerationOutcome($deleted);

        $this->respond_to_success(
            'Replacement deleted',
            ['post_replacement#index', 'post_id' => $deleted->post_id],
            ['api' => ['post_replacement' => $deleted->asJson()]]
        );
    }

    protected function can_submit_replacements(User $user = null)
    {
        if (!$user || $user->is_anonymous()) {
            return false;
        }

        $min_level = isset(CONFIG()->post_replacement_min_level)
            ? (int)CONFIG()->post_replacement_min_level
            : (int)CONFIG()->user_levels['Contributor'];

        return (int)$user->level >= $min_level;
    }

    protected function is_staff_user(User $user = null)
    {
        return $user && !$user->is_anonymous() && $user->is_janitor_or_higher();
    }

    protected function normalize_status($status)
    {
        $status = strtolower(trim((string)$status));
        if (in_array($status, [
            PostReplacement::STATUS_PENDING,
            PostReplacement::STATUS_APPROVED,
            PostReplacement::STATUS_REJECTED,
            PostReplacement::STATUS_DELETED
        ], true)) {
            return $status;
        }

        return null;
    }

    protected function replacement_payload()
    {
        $payload = is_array($this->params()->post_replacement) ? $this->params()->post_replacement : [];
        if (!array_key_exists('reason', $payload)) {
            $payload['reason'] = $this->params()->reason;
        }
        if (!array_key_exists('source_url', $payload)) {
            $payload['source_url'] = $this->params()->source_url ?: $this->params()->source;
        }
        if (!array_key_exists('post_id', $payload)) {
            $payload['post_id'] = $this->params()->post_id;
        }

        return $payload;
    }

    protected function find_post_from_params()
    {
        $payload = $this->replacement_payload();
        $post_id = (int)$payload['post_id'];
        if ($post_id <= 0) {
            $this->respond_to_error('Post not found', ['post#index'], ['status' => 404]);
            return null;
        }

        try {
            return Post::find($post_id);
        } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
            $this->respond_to_error('Post not found', ['post#index'], ['status' => 404]);
            return null;
        }
    }

    protected function find_replacement_from_params()
    {
        $replacement_id = (int)$this->params()->id;
        if ($replacement_id <= 0) {
            $this->respond_to_error('Replacement not found', ['post_replacement#index'], ['status' => 404]);
            return null;
        }

        try {
            return PostReplacement::find($replacement_id);
        } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
            $this->respond_to_error('Replacement not found', ['post_replacement#index'], ['status' => 404]);
            return null;
        }
    }

    protected function lock_replacement_for_update($replacement_id)
    {
        $table = PostReplacement::tableName();
        $rows = PostReplacement::findBySql(
            sprintf('SELECT * FROM `%s` WHERE id = ? FOR UPDATE', $table),
            [(int)$replacement_id]
        );

        $members = is_object($rows) && method_exists($rows, 'members')
            ? $rows->members()
            : (is_array($rows) ? $rows : []);

        if (empty($members)) {
            throw new Rails\ActiveRecord\Exception\RecordNotFoundException(
                'Could not find PostReplacement with ID ' . (int)$replacement_id
            );
        }

        return $members[0];
    }

    protected function lock_post_for_update($post_id)
    {
        $table = Post::tableName();
        Post::connection()->executeSql(
            sprintf('SELECT id FROM `%s` WHERE id = ? FOR UPDATE', $table),
            (int)$post_id
        );
    }

    protected function normalize_optional_text($text)
    {
        $text = trim((string)$text);
        return $text === '' ? null : $text;
    }

    protected function verify_post_replacement_csrf()
    {
        if (!$this->request()->isPost()) {
            return;
        }

        if ($this->authenticated_with_api_key_request()) {
            return;
        }

        if ($this->valid_authenticity_token($this->params()->csrf_token)) {
            return;
        }

        $this->respondTo([
            'html' => function() {
                $this->render(['text' => 'invalid authenticity token', 'status' => 403]);
            },
            'json' => function() {
                $this->render(['json' => ['success' => false, 'reason' => 'invalid authenticity token'], 'status' => 403]);
            },
            'xml' => function() {
                $this->render(['xml' => ['success' => false, 'reason' => 'invalid authenticity token'], 'root' => 'response', 'status' => 403]);
            }
        ]);
    }
}
