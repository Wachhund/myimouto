<?php
class ErrorsController extends ApplicationController
{
    protected function init()
    {
        $this->setLayout('bare');
    }

    public function not_found()
    {
        $this->render(['text' => 'Not Found', 'status' => 404]);
    }
}
