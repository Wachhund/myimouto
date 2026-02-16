<?php
class ReportController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'mod_only' => ['only' => ['tagUpdates', 'noteUpdates', 'wikiUpdates', 'postUploads', 'votes']]
            ]
        ];
    }

    public function tagUpdates()
    {
        $this->not_implemented();
    }

    public function noteUpdates()
    {
        $this->not_implemented();
    }

    public function wikiUpdates()
    {
        $this->not_implemented();
    }

    public function postUploads()
    {
        $this->not_implemented();
    }

    public function votes()
    {
        $this->not_implemented();
    }

    public function setDates()
    {
        $this->not_implemented();
    }

    protected function not_implemented()
    {
        $this->respond_to_error('Report module is not ported yet', ['static#more'], ['status' => 501]);
    }
}
