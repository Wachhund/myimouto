<?php

class BannedController extends ApplicationController
{
    public function index()
    {
        $this->setLayout('bare');

        $this->ban = $this->get_ip_ban();
        if (!$this->ban) {
            $this->redirectTo('root');
        }
    }
}
