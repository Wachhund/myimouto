<?php
class StaticController extends ApplicationController
{
    protected function init()
    {
        $this->setLayout('bare');
    }
    
    public function index()
    {
        if (CONFIG()->skip_homepage)
            $this->redirectTo('post#index');
        else
            $this->post_count = Post::fast_count();
    }

    public function opensearch()
    {
        $this->setLayout(false);
        $this->response()->headers()->setContentType('application/opensearchdescription+xml');
    }

    public function error500()
    {
        $this->render(['action' => '500', 'status' => 500]);
    }
}
