<?php
class ModActionController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'mod_only' => ['only' => ['index']]
            ]
        ];
    }

    public function index()
    {
        $query = ModAction::order('created_at DESC');

        if ($this->params()->action_type) {
            $query = $query->where('action = ?', $this->params()->action_type);
        }
        if ($this->params()->creator_id) {
            $query = $query->where('creator_id = ?', (int)$this->params()->creator_id);
        }
        if ($this->params()->creator_name) {
            $user = User::where('name = ?', $this->params()->creator_name)->first();
            if ($user) {
                $query = $query->where('creator_id = ?', $user->id);
            }
        }
        if ($this->params()->start_date) {
            $query = $query->where('created_at >= ?', $this->params()->start_date);
        }
        if ($this->params()->end_date) {
            $query = $query->where('created_at <= ?', $this->params()->end_date . ' 23:59:59');
        }

        $this->mod_actions = $query->paginate($this->page_number(), 50);
        $this->action_types = array_keys(ModAction::ACTION_REGISTRY);

        $this->respondTo([
            'html',
            'json' => function () {
                $data = [];
                foreach ($this->mod_actions as $ma) {
                    $data[] = $ma->api_attributes();
                }
                $this->render(['json' => $data]);
            }
        ]);
    }
}
