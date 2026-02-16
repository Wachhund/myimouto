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
        $this->job_task = JobTask::find($this->params()->id);

        if ($this->job_task->task_type == "upload_post" && $this->job_task->status == "finished") {
            $this->redirectTo(['controller' => "post", 'action' => "show", 'id' => $this->job_task->status_message]);
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
