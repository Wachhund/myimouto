<?php
class TagSubscriptionController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'member_only' => ['except' => ['index']],
                'no_anonymous'
            ]
        ];
    }

    public function create()
    {
        $this->response()->headers()->setContentType('text/javascript');
        $this->setLayout(false);

        if ($this->request()->isPost()) {
            if (current_user()->tag_subscriptions->size() >= CONFIG()->max_tag_subscriptions) {
                $this->tag_subscription = null;
            } else {
                $this->tag_subscription = TagSubscription::create(['user_id' => current_user()->id, 'tag_query' => '', 'name' => 'new tagsub']);
            }
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
        $this->tag_subscriptions = current_user()->tag_subscriptions;
    }

    public function destroy()
    {
        $this->response()->headers()->setContentType('text/javascript');
        $this->setLayout(false);

        if ($this->request()->isPost()) {
            $this->tag_subscription = TagSubscription::find($this->params()->id);

            if (current_user()->has_permission($this->tag_subscription)) {
                $this->tag_subscription->destroy();
            }
        }
    }
}
