<?php
class ApiKeyController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'member_only',
                'set_settings_layout'
            ]
        ];
    }

    protected function set_settings_layout()
    {
        $this->setLayout('settings');
    }

    public function index()
    {
        $this->set_title('API Keys');
        $this->api_keys = ApiKey::where(['user_id' => current_user()->id])
            ->order('created_at DESC')
            ->take();
        $this->max_keys = ApiKey::max_keys_for_level((int)current_user()->level);

        // Check if we have a newly created key to display
        if ($this->session()->new_api_key_raw) {
            $this->new_raw_key = $this->session()->new_api_key_raw;
            $this->session()->delete('new_api_key_raw');
        }

        $this->respondTo([
            'html',
            'json' => function() {
                $payload = [];
                foreach ($this->api_keys as $key) {
                    $payload[] = $key->asJson();
                }
                $this->render(['json' => $payload]);
            }
        ]);
    }

    public function create()
    {
        $existing_count = ApiKey::where(['user_id' => current_user()->id])->count();
        $max = ApiKey::max_keys_for_level((int)current_user()->level);

        if ($existing_count >= $max) {
            $this->respond_to_error(
                'You have reached the maximum number of API keys (' . $max . ')',
                '#index',
                ['status' => 420]
            );
            return;
        }

        $params = is_array($this->params()->api_key) ? $this->params()->api_key : [];
        $name = trim((string)($params['name'] ?? ''));
        if ($name === '') {
            $this->respond_to_error('Name is required', '#index', ['status' => 424]);
            return;
        }

        $expires_at = null;
        if (!empty($params['expires_at'])) {
            $parsed = strtotime($params['expires_at']);
            if ($parsed && $parsed > time()) {
                $expires_at = date('Y-m-d H:i:s', $parsed);
            }
        }

        $pair = ApiKey::generate();
        $now = date('Y-m-d H:i:s');

        $api_key = new ApiKey();
        $api_key->user_id = current_user()->id;
        $api_key->name = $name;
        $api_key->hashed_key = $pair['hashed_key'];
        $api_key->expires_at = $expires_at;
        $api_key->created_at = $now;

        if ($api_key->save()) {
            // Store raw key in session so it can be shown once
            $this->session()->new_api_key_raw = $pair['raw_key'];

            $this->respondTo([
                'html' => function() {
                    $this->notice('API key created. Copy the key now -- it will not be shown again.');
                    $this->redirectTo('#index');
                },
                'json' => function() use ($pair, $api_key) {
                    $attrs = $api_key->api_attributes();
                    $attrs['raw_key'] = $pair['raw_key'];
                    $this->render(['json' => array_merge(['success' => true], $attrs)]);
                }
            ]);
        } else {
            $this->respond_to_error($api_key, '#index');
        }
    }

    public function destroy()
    {
        $key = $this->find_own_key();
        if (!$key) {
            return;
        }

        $key->destroy();

        $this->respond_to_success('API key deleted', '#index');
    }

    public function regenerate()
    {
        $key = $this->find_own_key();
        if (!$key) {
            return;
        }

        $raw_key = $key->regenerate();

        // Store raw key in session so it can be shown once
        $this->session()->new_api_key_raw = $raw_key;

        $this->respondTo([
            'html' => function() {
                $this->notice('API key regenerated. Copy the new key now -- it will not be shown again.');
                $this->redirectTo('#index');
            },
            'json' => function() use ($raw_key, $key) {
                $attrs = $key->api_attributes();
                $attrs['raw_key'] = $raw_key;
                $this->render(['json' => array_merge(['success' => true], $attrs)]);
            }
        ]);
    }

    protected function find_own_key()
    {
        $key_id = (int)$this->params()->id;
        if ($key_id <= 0) {
            $this->respond_to_error('Key not found', '#index', ['status' => 404]);
            return null;
        }

        $key = ApiKey::where(['id' => $key_id, 'user_id' => current_user()->id])->first();
        if (!$key) {
            $this->respond_to_error('Key not found', '#index', ['status' => 404]);
            return null;
        }

        return $key;
    }
}
