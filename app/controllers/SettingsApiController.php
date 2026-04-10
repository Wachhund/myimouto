<?php

class SettingsApiController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'no_anonymous',
                'set_settings_layout',
                'ensure_api_key',
            ],
        ];
    }

    protected function set_settings_layout()
    {
        $this->setLayout('settings');
    }

    protected function ensure_api_key()
    {
        $this->user = current_user();

        if (!$this->user->api_key) {
            $this->user->set_api_key();
            $this->user->save();
        }
    }

    public function show()
    {
        $this->set_title('API Key');
    }

    public function update()
    {
        $this->user = current_user();
        $this->user->set_api_key();

        if ($this->user->save()) {
            $this->respond_to_success('API key reset', '#show');
        } else {
            $this->respond_to_error($this->user, '#show');
        }
    }
}
