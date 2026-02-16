<?php
class JobTaskController extends ApplicationController
{
    public function index()
    {
        $activeTypes = "'" . implode("', '", CONFIG()->active_job_tasks) . "'";
        $this->job_tasks = JobTask::order("id DESC")->where("task_type IN ($activeTypes)")->paginate($this->page_number(), 25);
    }

    public function show()
    {
        $id = (int)$this->params()->id;
        if ($id <= 0) {
            $this->respond_to_error('That page does not exist.', ['#index'], ['status' => 404]);
            return;
        }

        $this->job_task = JobTask::where(['id' => $id])->first();
        if (!$this->job_task) {
            $this->respond_to_error('That page does not exist.', ['#index'], ['status' => 404]);
            return;
        }

        if ($this->job_task->task_type == "upload_post" && $this->job_task->status == "finished") {
            $post_id = (int)$this->job_task->status_message;
            if ($post_id > 0 && Post::exists($post_id)) {
                $this->redirectTo(['controller' => "post", 'action' => "show", 'id' => $post_id]);
            }
        }
    }

    public function destroy()
    {
        $this->job_task = JobTask::find($this->params()->id);

        if ($this->request()->isPost()) {
            $this->job_task->destroy();
            $this->redirectTo(['action' => "index"]);
        }
    }

    public function restart()
    {
        $this->job_task = JobTask::find($this->params()->id);

        if ($this->request()->isPost()) {
            $this->job_task->updateAttributes(['status' => "pending", 'status_message' => ""]);
            $this->redirectTo(['action' => "show", 'id' => $this->job_task->id]);
        }
    }

    protected function filters()
    {
        return [
            'before' => [
                'admin_only' => ['only' => ['destroy', 'restart']]
            ]
        ];
    }
}
