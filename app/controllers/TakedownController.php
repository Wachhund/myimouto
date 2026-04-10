<?php
class TakedownController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'mod_only' => ['only' => ['index', 'show', 'create', 'update', 'destroy', 'add_posts', 'remove_posts', 'add_posts_by_tags']]
            ]
        ];
    }

    public function index()
    {
        $query = Takedown::where('true');

        // Status filter
        if ($this->params()->status && in_array($this->params()->status, Takedown::VALID_STATUSES, true)) {
            $query->where('status = ?', $this->params()->status);
        }

        $this->takedowns = $query->order('created_at DESC')->paginate($this->page_number(), 25);

        $this->respondTo([
            'html',
            'json' => function () {
                $data = [];
                foreach ($this->takedowns as $takedown) {
                    $data[] = $takedown->api_attributes();
                }
                $this->render(['json' => $data]);
            }
        ]);
    }

    public function show()
    {
        $this->takedown = $this->find_takedown_from_params();
        if (!$this->takedown) {
            return;
        }

        $this->takedown_posts = TakedownPost::where('takedown_id = ?', $this->takedown->id)
            ->order('id ASC')
            ->take();

        $this->set_title('Takedown #' . $this->takedown->id);

        $this->respondTo([
            'html',
            'json' => function () {
                $payload = $this->takedown->api_attributes();
                $payload['posts'] = [];
                foreach ($this->takedown_posts as $tp) {
                    $payload['posts'][] = $tp->api_attributes();
                }
                $this->render(['json' => $payload]);
            }
        ]);
    }

    public function create()
    {
        if (!$this->request()->isPost()) {
            $this->takedown = new Takedown();
            return;
        }

        $takedown_params = is_array($this->params()->takedown) ? $this->params()->takedown : [];

        $attrs = [
            'creator_id' => current_user()->id,
            'reason' => $takedown_params['reason'] ?? ($this->params()->reason ?: ''),
            'email' => $takedown_params['email'] ?? ($this->params()->email ?: null),
            'source' => $takedown_params['source'] ?? ($this->params()->source ?: null),
            'instructions' => $takedown_params['instructions'] ?? ($this->params()->instructions ?: null),
            'notes' => $takedown_params['notes'] ?? ($this->params()->notes ?: null),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->takedown = Takedown::create($attrs);

        if ($this->takedown->errors()->blank()) {
            // If post_ids were supplied alongside creation, add them
            $post_ids_raw = $takedown_params['post_ids'] ?? ($this->params()->post_ids ?: '');
            if (!empty($post_ids_raw)) {
                $post_ids = $this->parse_post_ids($post_ids_raw);
                if (!empty($post_ids)) {
                    $this->takedown->add_posts($post_ids);
                }
            }

            $this->respond_to_success('Takedown created', ['#show', 'id' => $this->takedown->id], [
                'api' => ['takedown' => $this->takedown->api_attributes()]
            ]);
            return;
        }

        $this->respond_to_error($this->takedown, ['#create']);
    }

    public function update()
    {
        $this->takedown = $this->find_takedown_from_params();
        if (!$this->takedown) {
            return;
        }

        $takedown_params = is_array($this->params()->takedown) ? $this->params()->takedown : [];
        $status = $takedown_params['status'] ?? ($this->params()->status ?: null);
        $instructions = $takedown_params['instructions'] ?? $this->params()->instructions;
        $notes = $takedown_params['notes'] ?? $this->params()->notes;

        // Update optional fields
        if ($instructions !== null) {
            $this->takedown->instructions = trim((string)$instructions);
        }
        if ($notes !== null) {
            $this->takedown->notes = trim((string)$notes);
        }

        if ($status !== null && in_array($status, [Takedown::STATUS_APPROVED, Takedown::STATUS_DENIED, Takedown::STATUS_PARTIAL], true)) {
            $result = $this->takedown->process(current_user(), $status);
        } else {
            $this->takedown->updated_at = date('Y-m-d H:i:s');
            $result = $this->takedown->save();
        }

        if ($result) {
            $this->respond_to_success('Takedown updated', ['#show', 'id' => $this->takedown->id], [
                'api' => ['takedown' => $this->takedown->api_attributes()]
            ]);
        } else {
            $this->respond_to_error('Failed to update takedown', ['#show', 'id' => $this->takedown->id]);
        }
    }

    public function destroy()
    {
        if (!current_user()->is_admin()) {
            $this->access_denied();
            return;
        }

        $this->takedown = $this->find_takedown_from_params();
        if (!$this->takedown) {
            return;
        }

        $this->takedown->destroy();
        $this->respond_to_success('Takedown deleted', '#index');
    }

    public function add_posts()
    {
        $this->takedown = $this->find_takedown_from_params();
        if (!$this->takedown) {
            return;
        }

        $post_ids_raw = $this->params()->post_ids ?: '';
        $post_ids = $this->parse_post_ids($post_ids_raw);

        if (empty($post_ids)) {
            $this->respond_to_error('No valid post IDs provided', ['#show', 'id' => $this->takedown->id], ['status' => 424]);
            return;
        }

        $result = $this->takedown->add_posts($post_ids);
        $summary = sprintf(
            'Posts added to takedown (added: %d, invalid: %d)',
            count($result['added']),
            count($result['invalid'])
        );

        $this->respond_to_success($summary, ['#show', 'id' => $this->takedown->id], ['api' => $result]);
    }

    public function remove_posts()
    {
        $this->takedown = $this->find_takedown_from_params();
        if (!$this->takedown) {
            return;
        }

        $post_ids_raw = $this->params()->post_ids ?: '';
        $post_ids = $this->parse_post_ids($post_ids_raw);

        if (empty($post_ids)) {
            $this->respond_to_error('No valid post IDs provided', ['#show', 'id' => $this->takedown->id], ['status' => 424]);
            return;
        }

        $result = $this->takedown->remove_posts($post_ids);
        $summary = sprintf('Posts removed from takedown (removed: %d)', count($result['removed']));

        $this->respond_to_success($summary, ['#show', 'id' => $this->takedown->id], ['api' => $result]);
    }

    /**
     * Public status check by vericode. No auth required.
     */
    public function status()
    {
        $vericode = trim((string)$this->params()->vericode);

        if ($vericode === '') {
            // Show the form if no vericode provided
            $this->takedown = null;
            return;
        }

        if (!preg_match('/\A[a-f0-9]{32}\Z/', $vericode)) {
            $this->takedown = null;
            $this->notice_message = 'Invalid verification code format';
            return;
        }

        $this->takedown = Takedown::where('vericode = ?', $vericode)->first();

        if (!$this->takedown) {
            $this->notice_message = 'No takedown found with that verification code';
        }

        $this->respondTo([
            'html',
            'json' => function () {
                if ($this->takedown) {
                    $this->render(['json' => $this->takedown->public_attributes()]);
                } else {
                    $this->render(['json' => ['success' => false, 'reason' => 'not found'], 'status' => 404]);
                }
            }
        ]);
    }

    public function add_posts_by_tags()
    {
        $this->takedown = $this->find_takedown_from_params();
        if (!$this->takedown) {
            return;
        }

        $tags = trim((string)($this->params()->tags ?: ''));

        if ($tags === '') {
            $this->respond_to_error('No tags provided', ['#show', 'id' => $this->takedown->id], ['status' => 424]);
            return;
        }

        try {
            $q = Tag::parse_query($tags);
        } catch (\Throwable $e) {
            $this->respond_to_error('Invalid tag query: ' . $e->getMessage(), ['#show', 'id' => $this->takedown->id], ['status' => 424]);
            return;
        }

        $cap = 1000;

        try {
            list($sql, $params) = Post::generate_sql($q, [
                'original_query' => $tags,
                'from_api' => true,
                'order' => 'p.id DESC',
                'limit' => $cap + 1,
                'select' => 'p.id'
            ]);
        } catch (\Throwable $e) {
            $this->respond_to_error('Tag query error: ' . $e->getMessage(), ['#show', 'id' => $this->takedown->id], ['status' => 424]);
            return;
        }

        $results = Post::findBySql($sql, $params);
        $post_ids = [];
        foreach ($results as $post) {
            $post_ids[] = (int)$post->id;
        }

        if (empty($post_ids)) {
            $this->respond_to_error('No posts found matching the given tags', ['#show', 'id' => $this->takedown->id], ['status' => 424]);
            return;
        }

        $over_cap = false;
        if (count($post_ids) > $cap) {
            $over_cap = true;
            $post_ids = array_slice($post_ids, 0, $cap);
        }

        $add_result = $this->takedown->add_posts($post_ids);
        $added_count = count($add_result['added']);

        $message = sprintf('%d posts added to takedown by tag search', $added_count);
        if ($over_cap) {
            $message .= sprintf(' (results capped at %d posts)', $cap);
        }

        $this->respond_to_success($message, ['#show', 'id' => $this->takedown->id], ['api' => $add_result]);
    }

    protected function find_takedown_from_params()
    {
        $id = (int)$this->params()->id;
        if ($id <= 0) {
            $this->respond_to_error('Takedown not found', ['#index'], ['status' => 404]);
            return null;
        }

        try {
            return Takedown::find($id);
        } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
            $this->respond_to_error('Takedown not found', ['#index'], ['status' => 404]);
            return null;
        }
    }

    protected function parse_post_ids($value)
    {
        if (is_array($value)) {
            $value = implode(' ', $value);
        }

        $parts = preg_split('/[\s,]+/', (string)$value);
        $normalized = [];
        foreach ($parts as $part) {
            $id = (int)$part;
            if ($id > 0) {
                $normalized[] = $id;
            }
        }

        return array_values(array_unique($normalized));
    }
}
