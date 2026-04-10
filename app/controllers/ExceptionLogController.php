<?php

class ExceptionLogController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'mod_only' => ['only' => ['index', 'show', 'prune']],
            ],
        ];
    }

    public function index()
    {
        $query = ExceptionLog::order('created_at DESC');

        if ($this->params()->code) {
            $query = $query->where('code = ?', $this->params()->code);
        }
        if ($this->params()->exception_class) {
            $query = $query->where('exception_class LIKE ?', '%' . $this->params()->exception_class . '%');
        }
        if ($this->params()->message) {
            $query = $query->where('message LIKE ?', '%' . $this->params()->message . '%');
        }
        if ($this->params()->start_date) {
            $query = $query->where('created_at >= ?', $this->params()->start_date);
        }
        if ($this->params()->end_date) {
            $query = $query->where('created_at <= ?', $this->params()->end_date . ' 23:59:59');
        }
        if ($this->params()->version) {
            $query = $query->where('version = ?', $this->params()->version);
        }

        $this->exception_logs = $query->paginate($this->page_number(), 50);

        $this->respondTo([
            'html' => function () {},
            'json' => function () {
                $data = [];
                foreach ($this->exception_logs as $log) {
                    $data[] = $log->api_attributes();
                }
                $this->render(['json' => $data]);
            },
        ]);
    }

    public function show()
    {
        $this->exception_log = ExceptionLog::find($this->params()->id);
    }

    public function prune()
    {
        if (!$this->current_user->is_admin()) {
            $this->access_denied();
            return;
        }

        if ($this->request()->isPost()) {
            $days = (int) ($this->params()->days ?: 365);
            ExceptionLog::prune($days);
            $this->notice("Exception logs older than {$days} days pruned.");
        }
        $this->redirectTo(['#index']);
    }
}
