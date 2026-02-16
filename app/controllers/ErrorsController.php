<?php
class ErrorsController extends ApplicationController
{
    protected function init()
    {
        $this->setLayout('bare');
    }

    // RailsPHP camelizes route actions (not_found -> notFound).
    public function notFound()
    {
        return $this->not_found();
    }

    public function not_found()
    {
        $this->render(['text' => 'Not Found', 'status' => 404]);
    }
}
