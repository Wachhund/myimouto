<?php

class PostSetController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'member_only' => ['only' => ['create', 'update', 'destroy', 'postList', 'updatePosts', 'addPost', 'removePost']],
            ],
        ];
    }

    public function index()
    {
        $params = $this->params()->all();
        $limit = isset($params['limit']) ? (int) $params['limit'] : 0;
        if ($limit <= 0) {
            $limit = (int) CONFIG()->post_set_index_default_limit;
        }
        $limit = max(1, min($limit, 100));

        $query = PostSet::search_query($params, current_user());
        $this->post_sets = $query
            ->page($this->page_number())
            ->perPage($limit)
            ->paginate();

        $this->respond_to_list('post_sets');
    }

    public function show()
    {
        $this->post_set = $this->find_post_set_from_params();
        if (!$this->post_set) {
            return;
        }

        if (!$this->post_set->can_be_seen_by(current_user())) {
            $this->access_denied();
            return;
        }

        $all_post_ids = $this->post_set->post_ids();
        $visible_post_ids = [];
        $visible_posts = [];
        if (!empty($all_post_ids)) {
            $posts = Post::where('id IN (?)', $all_post_ids)->take();
            $posts_by_id = [];
            foreach ($posts as $post) {
                $posts_by_id[(int) $post->id] = $post;
            }

            foreach ($all_post_ids as $post_id) {
                $post_id = (int) $post_id;
                if (!isset($posts_by_id[$post_id])) {
                    continue;
                }

                $post = $posts_by_id[$post_id];
                if (!$post->can_be_seen_by(current_user())) {
                    continue;
                }

                $visible_post_ids[] = $post_id;
                $visible_posts[] = $post;
            }
        }

        $this->post_ids = $visible_post_ids;
        $this->posts = new Rails\ActiveRecord\Collection($visible_posts);

        $this->set_title($this->post_set->name . ' - Sets');

        $this->respondTo([
            'html',
            'json' => function () {
                $payload = $this->post_set->api_attributes();
                $payload['post_ids'] = $this->post_ids;
                $this->render(['json' => $payload]);
            },
            'xml' => function () {
                $payload = $this->post_set->api_attributes();
                $payload['post_ids'] = implode(',', $this->post_ids);
                $this->render(['xml' => $payload, 'root' => 'post_set']);
            },
        ]);
    }

    public function create()
    {

        if (!$this->request()->isPost()) {
            $this->post_set = new PostSet();
            return;
        }

        $attrs = $this->extract_post_set_attributes();
        $attrs['creator_id'] = current_user()->id;

        $this->post_set = PostSet::create($attrs);
        if ($this->post_set->errors()->blank()) {
            $this->respond_to_success('Set created', ['#show', 'id' => $this->post_set->id], [
                'api' => ['post_set' => $this->post_set->api_attributes()],
            ]);
            return;
        }

        $this->respond_to_error($this->post_set, ['#index']);
    }

    public function update()
    {
        $this->post_set = $this->find_post_set_from_params();
        if (!$this->post_set) {
            return;
        }

        if (!$this->post_set->can_edit_settings_by(current_user())) {
            $this->access_denied();
            return;
        }

        if (!$this->request()->isPost()) {
            return;
        }

        $attrs = $this->extract_post_set_attributes();
        $this->post_set->updateAttributes($attrs);

        if ($this->post_set->errors()->blank()) {
            $this->respond_to_success('Set updated', ['#show', 'id' => $this->post_set->id], [
                'api' => ['post_set' => $this->post_set->api_attributes()],
            ]);
            return;
        }

        $this->respond_to_error($this->post_set, ['#show', 'id' => $this->post_set->id]);
    }

    public function destroy()
    {
        $this->post_set = $this->find_post_set_from_params();
        if (!$this->post_set) {
            return;
        }

        if (!$this->post_set->can_edit_settings_by(current_user())) {
            $this->access_denied();
            return;
        }

        if ($this->request()->isPost()) {
            $this->post_set->destroy();
            $this->respond_to_success('Set deleted', '#index');
        }
    }

    public function postList()
    {
        $this->post_set = $this->find_post_set_from_params();
        if (!$this->post_set) {
            return;
        }

        if (!$this->post_set->can_edit_posts_by(current_user())) {
            $this->access_denied();
            return;
        }

        $this->post_ids_text = implode(' ', $this->post_set->post_ids());
    }

    public function updatePosts()
    {
        $this->post_set = $this->find_post_set_from_params();
        if (!$this->post_set) {
            return;
        }

        if (!$this->post_set->can_edit_posts_by(current_user())) {
            $this->access_denied();
            return;
        }

        $post_ids = PostSet::parse_post_ids_string((string) $this->params()->post_ids);
        if (count($post_ids) > PostSet::post_limit()) {
            $this->respond_to_error(
                'Too many posts in set update',
                ['#post_list', 'id' => $this->post_set->id],
                ['status' => 424],
            );
            return;
        }

        $result = $this->post_set->sync_posts($post_ids);
        $summary = sprintf(
            'Set posts updated (added: %d, removed: %d, invalid: %d)',
            count($result['added']),
            count($result['removed']),
            count($result['invalid']),
        );

        $this->respond_to_success($summary, ['#post_list', 'id' => $this->post_set->id], ['api' => $result]);
    }

    public function addPost()
    {
        $this->post_set = $this->find_post_set_from_params('post_set_id');
        if (!$this->post_set) {
            return;
        }

        if (!$this->post_set->can_edit_posts_by(current_user())) {
            $this->access_denied();
            return;
        }

        $post_ids = $this->extract_post_ids();
        if (empty($post_ids)) {
            $this->respond_to_error('No valid post IDs provided', ['#show', 'id' => $this->post_set->id], ['status' => 424]);
            return;
        }

        if (($this->post_set->post_count + count($post_ids)) > PostSet::post_limit()) {
            $this->respond_to_error('Set post limit exceeded', ['#show', 'id' => $this->post_set->id], ['status' => 424]);
            return;
        }

        $result = $this->post_set->add_posts($post_ids);
        $summary = sprintf(
            'Posts added to set (added: %d, invalid: %d)',
            count($result['added']),
            count($result['invalid']),
        );

        $redirect = ['#show', 'id' => $this->post_set->id];
        if (!empty($result['added'])) {
            $redirect = ['post#show', 'id' => $result['added'][0]];
        }

        $this->respond_to_success($summary, $redirect, ['api' => $result]);
    }

    public function removePost()
    {
        $this->post_set = $this->find_post_set_from_params('post_set_id');
        if (!$this->post_set) {
            return;
        }

        if (!$this->post_set->can_edit_posts_by(current_user())) {
            $this->access_denied();
            return;
        }

        $post_ids = $this->extract_post_ids();
        if (empty($post_ids)) {
            $this->respond_to_error('No valid post IDs provided', ['#show', 'id' => $this->post_set->id], ['status' => 424]);
            return;
        }

        $result = $this->post_set->remove_posts($post_ids);
        $summary = sprintf('Posts removed from set (removed: %d)', count($result['removed']));
        $this->respond_to_success($summary, ['#show', 'id' => $this->post_set->id], ['api' => $result]);
    }

    public function maintainers()
    {
        $this->post_set = $this->find_post_set_from_params();
        if (!$this->post_set) {
            return;
        }

        if (!$this->post_set->can_be_seen_by(current_user())) {
            $this->access_denied();
            return;
        }

        $this->pending_maintainers = $this->post_set->pending_maintainers();
        $this->approved_maintainers = $this->post_set->approved_maintainers();

        $this->respondTo([
            'html',
            'json' => function () {
                $payload = [
                    'post_set_id' => (int) $this->post_set->id,
                    'pending' => [],
                    'approved' => [],
                ];

                foreach ($this->pending_maintainers as $m) {
                    $payload['pending'][] = $m->asJson();
                }
                foreach ($this->approved_maintainers as $m) {
                    $payload['approved'][] = $m->asJson();
                }

                $this->render(['json' => $payload]);
            },
            'xml' => function () {
                $payload = [
                    'post_set_id' => (int) $this->post_set->id,
                    'pending_count' => $this->pending_maintainers->size(),
                    'approved_count' => $this->approved_maintainers->size(),
                ];
                $this->render(['xml' => $payload, 'root' => 'post_set_maintainers']);
            },
        ]);
    }

    protected function find_post_set_from_params($param_key = 'id')
    {
        $id = (int) $this->params()->$param_key;
        if ($id <= 0) {
            $this->respond_to_error('Set not found', ['#index'], ['status' => 404]);
            return null;
        }

        try {
            return PostSet::find($id);
        } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
            $this->respond_to_error('Set not found', ['#index'], ['status' => 404]);
            return null;
        }
    }

    protected function extract_post_set_attributes()
    {
        $source = is_array($this->params()->post_set) ? $this->params()->post_set : [];

        $attrs = [];
        if (array_key_exists('name', $source)) {
            $attrs['name'] = trim((string) $source['name']);
        }
        if (array_key_exists('shortname', $source)) {
            $attrs['shortname'] = trim((string) $source['shortname']);
        }
        if (array_key_exists('description', $source)) {
            $attrs['description'] = trim((string) $source['description']);
        }
        if (array_key_exists('is_public', $source)) {
            $attrs['is_public'] = $this->to_bool($source['is_public']) ? 1 : 0;
        }

        return $attrs;
    }

    protected function extract_post_ids()
    {
        if (!empty($this->params()->post_ids)) {
            $post_ids = $this->params()->post_ids;
            if (is_array($post_ids)) {
                return PostSet::parse_post_ids_string(implode(' ', $post_ids));
            }
            return PostSet::parse_post_ids_string((string) $post_ids);
        }

        if (!empty($this->params()->post_id)) {
            $post_id = $this->params()->post_id;
            if (is_array($post_id)) {
                return PostSet::parse_post_ids_string(implode(' ', $post_id));
            }
            return PostSet::parse_post_ids_string((string) $post_id);
        }

        return [];
    }

    protected function to_bool($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

}
