<?php
class TagSubscriptionController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'member_only' => ['except' => ['index']],
                'no_anonymous',
                'verify_tag_subscription_csrf' => ['only' => ['create', 'update', 'destroy']]
            ]
        ];
    }

    public function create()
    {
        $this->csrf_token = $this->form_authenticity_token();
        $this->response()->headers()->setContentType('text/javascript');
        $this->setLayout(false);

        if (!$this->request()->isPost()) {
            $this->render(['nothing' => true]);
            return;
        }

        if (current_user()->tag_subscriptions->size() >= CONFIG()->max_tag_subscriptions) {
            $this->tag_subscription = null;
        } else {
            $this->tag_subscription = TagSubscription::create(['user_id' => current_user()->id, 'tag_query' => '', 'name' => 'new tagsub']);
        }

        if ($this->request()->format() !== 'js') {
            $this->render(['nothing' => true]);
        }
    }

    public function update()
    {
        if ($this->request()->isPost()) {
            if (is_array($this->params()->tag_subscription)) {
                foreach ($this->params()->tag_subscription as $tag_subscription_id => $ts) {
                    $tag_subscription = TagSubscription::find($tag_subscription_id);
                    if ($tag_subscription->user_id == current_user()->id) {
                        $tag_subscription->updateAttributes($ts);
                    }
                }
            }
        }

        $this->notice("Tag subscriptions updated");
        $this->redirectTo('user#edit');
    }

    public function index()
    {
        $this->csrf_token = $this->form_authenticity_token();
        $this->tag_subscriptions = current_user()->tag_subscriptions;
    }

    public function destroy()
    {
        $this->csrf_token = $this->form_authenticity_token();
        $this->response()->headers()->setContentType('text/javascript');
        $this->setLayout(false);

        if (!$this->request()->isPost()) {
            $this->render(['nothing' => true]);
            return;
        }

        $this->tag_subscription = TagSubscription::find($this->params()->id);

        if (!current_user()->has_permission($this->tag_subscription)) {
            $this->render_destroy_access_denied();
            return;
        }

        $this->tag_subscription->destroy();

        if ($this->request()->format() !== 'js') {
            $this->render(['nothing' => true]);
        }
    }

    protected function render_destroy_access_denied()
    {
        if ($this->request()->isXmlHttpRequest() || $this->request()->format() === 'js') {
            $this->render(['text' => "notice('Access denied');", 'status' => 403]);
            return;
        }

        $this->respond_to_error('Access denied', ['user#edit'], ['status' => 403]);
    }

    protected function verify_tag_subscription_csrf()
    {
        if (!$this->request()->isPost()) {
            return;
        }

        if ($this->authenticated_with_api_key_request()) {
            return;
        }

        if (!$this->valid_authenticity_token($this->params()->csrf_token)) {
            if ($this->request()->isXmlHttpRequest() || $this->request()->format() === 'js') {
                $this->render(['text' => 'invalid authenticity token', 'status' => 403]);
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
}
