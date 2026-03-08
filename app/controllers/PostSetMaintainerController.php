<?php
class PostSetMaintainerController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'member_only'
            ]
        ];
    }

    public function index()
    {
        $this->maintainer_invites = PostSetMaintainer::where('user_id = ?', current_user()->id)
            ->order('updated_at DESC')
            ->take();

        $this->respondTo([
            'html',
            'json' => function() {
                $payload = [];
                foreach ($this->maintainer_invites as $invite) {
                    $payload[] = $invite->asJson();
                }
                $this->render(['json' => $payload]);
            },
            'xml' => function() {
                $this->render(['xml' => ['count' => $this->maintainer_invites->size()], 'root' => 'post_set_maintainers']);
            }
        ]);
    }

    public function create()
    {
        $post_set = $this->find_post_set_from_params('post_set_id');
        if (!$post_set) {
            return;
        }

        if (!$post_set->can_edit_settings_by(current_user())) {
            $this->access_denied();
            return;
        }

        $target_user = $this->find_target_user();
        if (!$target_user) {
            $this->respond_to_error('User not found', ['post_set#maintainers', 'id' => $post_set->id], ['status' => 404]);
            return;
        }

        if ($post_set->is_owner($target_user)) {
            $this->respond_to_error('Owner cannot be invited as maintainer', ['post_set#maintainers', 'id' => $post_set->id], ['status' => 424]);
            return;
        }

        $existing = PostSetMaintainer::find_for_set_and_user($post_set->id, $target_user->id);
        if ($existing) {
            if ($existing->status === PostSetMaintainer::STATUS_BLOCKED) {
                $this->respond_to_error('User has blocked maintainer invites for this set', ['post_set#maintainers', 'id' => $post_set->id], ['status' => 403]);
                return;
            }

            if ($existing->status === PostSetMaintainer::STATUS_APPROVED) {
                $this->respond_to_error('User is already a maintainer', ['post_set#maintainers', 'id' => $post_set->id], ['status' => 423]);
                return;
            }

            if ($existing->status === PostSetMaintainer::STATUS_PENDING) {
                $existing->approve();
                $this->respond_to_success(
                    $target_user->pretty_name() . ' added as maintainer',
                    ['post_set#maintainers', 'id' => $post_set->id],
                    ['api' => ['post_set_maintainer' => $existing->asJson()]]
                );
                return;
            }
        }

        $maintainer = PostSetMaintainer::create([
            'post_set_id' => $post_set->id,
            'user_id' => $target_user->id,
            'status' => PostSetMaintainer::STATUS_APPROVED
        ]);

        if ($maintainer->errors()->blank()) {
            $this->respond_to_success(
                $target_user->pretty_name() . ' added as maintainer',
                ['post_set#maintainers', 'id' => $post_set->id],
                ['api' => ['post_set_maintainer' => $maintainer->asJson()]]
            );
            return;
        }

        $this->respond_to_error($maintainer, ['post_set#maintainers', 'id' => $post_set->id]);
    }

    public function requestAccess()
    {
        $post_set = $this->find_post_set_from_params('post_set_id');
        if (!$post_set) {
            return;
        }

        if (!$post_set->can_be_seen_by(current_user())) {
            $this->access_denied();
            return;
        }

        if ($post_set->is_owner(current_user())) {
            $this->respond_to_error('Owner cannot request maintainer access', ['post_set#show', 'id' => $post_set->id], ['status' => 424]);
            return;
        }

        $existing = PostSetMaintainer::find_for_set_and_user($post_set->id, current_user()->id);
        if ($existing) {
            if ($existing->status === PostSetMaintainer::STATUS_PENDING) {
                $this->respond_to_error('Maintainer request already pending', ['post_set#show', 'id' => $post_set->id], ['status' => 423]);
                return;
            }
            if ($existing->status === PostSetMaintainer::STATUS_APPROVED) {
                $this->respond_to_error('You are already a maintainer', ['post_set#show', 'id' => $post_set->id], ['status' => 423]);
                return;
            }
            if ($existing->status === PostSetMaintainer::STATUS_BLOCKED) {
                $this->respond_to_error('You blocked maintainer invites for this set', ['post_set#show', 'id' => $post_set->id], ['status' => 403]);
                return;
            }
        }

        $maintainer = PostSetMaintainer::create([
            'post_set_id' => $post_set->id,
            'user_id' => current_user()->id,
            'status' => PostSetMaintainer::STATUS_PENDING
        ]);

        if ($maintainer->errors()->blank()) {
            $this->respond_to_success(
                'Maintainer request submitted',
                ['post_set#show', 'id' => $post_set->id],
                ['api' => ['post_set_maintainer' => $maintainer->asJson()]]
            );
            return;
        }

        $this->respond_to_error($maintainer, ['post_set#show', 'id' => $post_set->id]);
    }

    public function approve()
    {
        $maintainer = $this->find_maintainer_from_params();
        if (!$maintainer) {
            return;
        }

        if (!$this->can_approve($maintainer)) {
            $this->access_denied();
            return;
        }

        if ($maintainer->status === PostSetMaintainer::STATUS_BLOCKED) {
            $this->respond_to_error('Blocked maintainer invite cannot be approved', ['#index'], ['status' => 403]);
            return;
        }

        if ($maintainer->status === PostSetMaintainer::STATUS_APPROVED) {
            $this->respond_to_success('Maintainer already approved', ['post_set#maintainers', 'id' => $maintainer->post_set_id], [
                'api' => ['post_set_maintainer' => $maintainer->asJson()]
            ]);
            return;
        }

        if ($maintainer->status !== PostSetMaintainer::STATUS_PENDING) {
            $this->respond_to_error('Maintainer is not in pending state', ['post_set#maintainers', 'id' => $maintainer->post_set_id], ['status' => 424]);
            return;
        }

        if ($maintainer->status === PostSetMaintainer::STATUS_PENDING) {
            $maintainer->approve();
        }

        $this->respond_to_success('Maintainer approved', ['post_set#maintainers', 'id' => $maintainer->post_set_id], [
            'api' => ['post_set_maintainer' => $maintainer->asJson()]
        ]);
    }

    public function deny()
    {
        $maintainer = $this->find_maintainer_from_params();
        if (!$maintainer) {
            return;
        }

        if (!$this->can_deny($maintainer)) {
            $this->access_denied();
            return;
        }

        $post_set_id = (int)$maintainer->post_set_id;
        $maintainer->destroy();

        $this->respond_to_success('Maintainer invite denied', ['post_set#maintainers', 'id' => $post_set_id]);
    }

    public function block()
    {
        $maintainer = $this->find_maintainer_from_params();
        if (!$maintainer) {
            return;
        }

        if (!$this->can_block($maintainer)) {
            $this->access_denied();
            return;
        }

        $maintainer->block();

        $this->respond_to_success('Maintainer invite blocked', ['#index'], [
            'api' => ['post_set_maintainer' => $maintainer->asJson()]
        ]);
    }

    public function revoke()
    {
        $maintainer = $this->find_maintainer_from_params();
        if (!$maintainer) {
            return;
        }

        if (!$maintainer->post_set->can_edit_settings_by(current_user())) {
            $this->access_denied();
            return;
        }

        $post_set_id = (int)$maintainer->post_set_id;
        $maintainer->destroy();
        $this->respond_to_success('Maintainer removed', ['post_set#maintainers', 'id' => $post_set_id]);
    }

    public function destroy()
    {
        $this->revoke();
    }

    protected function can_approve(PostSetMaintainer $maintainer)
    {
        return $maintainer->post_set->can_edit_settings_by(current_user());
    }

    protected function can_deny(PostSetMaintainer $maintainer)
    {
        if ($maintainer->post_set->can_edit_settings_by(current_user())) {
            return true;
        }

        return (int)$maintainer->user_id === (int)current_user()->id;
    }

    protected function can_block(PostSetMaintainer $maintainer)
    {
        if ($maintainer->post_set->can_edit_settings_by(current_user())) {
            return true;
        }

        return (int)$maintainer->user_id === (int)current_user()->id;
    }

    protected function find_target_user()
    {
        if (!empty($this->params()->maintainer_id)) {
            return User::where(['id' => (int)$this->params()->maintainer_id])->first();
        }

        if (!empty($this->params()->maintainer_name)) {
            return User::find_by_name((string)$this->params()->maintainer_name);
        }

        return null;
    }

    protected function find_post_set_from_params($param_key = 'post_set_id')
    {
        $post_set_id = (int)$this->params()->$param_key;
        if ($post_set_id <= 0) {
            $this->respond_to_error('Set not found', ['post_set#index'], ['status' => 404]);
            return null;
        }

        try {
            return PostSet::find($post_set_id);
        } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
            $this->respond_to_error('Set not found', ['post_set#index'], ['status' => 404]);
            return null;
        }
    }

    protected function find_maintainer_from_params()
    {
        $id = (int)$this->params()->id;
        if ($id <= 0) {
            $this->respond_to_error('Maintainer record not found', ['#index'], ['status' => 404]);
            return null;
        }

        try {
            return PostSetMaintainer::find($id);
        } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
            $this->respond_to_error('Maintainer record not found', ['#index'], ['status' => 404]);
            return null;
        }
    }

}
