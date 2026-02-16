<?php
class HelpController extends ApplicationController
{
    public function show()
    {
        $page = trim((string) $this->params()->page);
        if ($page === '') {
            $page = 'index';
        }

        if (!preg_match('/^[a-z0-9_]+$/', $page)) {
            $this->render(['text' => 'not found', 'status' => 404]);
            return;
        }

        $view = Rails::root() . '/app/views/help/' . $page . '.php';
        if (!is_file($view)) {
            $this->render(['text' => 'not found', 'status' => 404]);
            return;
        }

        $this->render(['action' => $page]);
    }
}
