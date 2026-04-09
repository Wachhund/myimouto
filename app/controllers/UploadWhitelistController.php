<?php
class UploadWhitelistController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'admin_only' => ['only' => ['index', 'create', 'update', 'destroy']],
                'mod_only' => ['only' => ['is_allowed']]
            ]
        ];
    }

    public function index()
    {
        $this->set_title('Upload Whitelist');

        $query = UploadWhitelist::order('allowed ASC, pattern ASC');

        if ($this->params()->query) {
            $name = "%" . $this->params()->query . "%";
            $query->where("pattern LIKE ?", $name);
        }

        $this->rules = $query->paginate($this->page_number(), 50);

        $this->respondTo([
            'html',
            'json' => function() {
                $payload = [];
                foreach ($this->rules as $rule) {
                    $payload[] = $rule->api_attributes();
                }
                $this->render(['json' => $payload]);
            },
            'xml' => function() {
                $this->render(['xml' => $this->rules, 'root' => 'upload_whitelists']);
            }
        ]);
    }

    public function create()
    {
        $attrs = is_array($this->params()->upload_whitelist) ? $this->params()->upload_whitelist : [];

        $rule = new UploadWhitelist();
        $rule->pattern = isset($attrs['pattern']) ? trim((string)$attrs['pattern']) : '';
        $rule->allowed = isset($attrs['allowed']) ? (int)$attrs['allowed'] : 1;
        $rule->reason = isset($attrs['reason']) ? trim((string)$attrs['reason']) : null;
        $rule->note = isset($attrs['note']) ? trim((string)$attrs['note']) : null;
        $rule->hidden = isset($attrs['hidden']) ? (int)$attrs['hidden'] : 0;

        if ($rule->save()) {
            $this->respond_to_success('Whitelist rule created', '#index', ['api' => $rule->api_attributes()]);
        } else {
            $this->respond_to_error($rule, '#index');
        }
    }

    public function update()
    {
        $id = (int)$this->params()->id;
        if ($id <= 0) {
            $this->respond_to_error('Rule not found', '#index', ['status' => 404]);
            return;
        }

        try {
            $rule = UploadWhitelist::find($id);
        } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
            $this->respond_to_error('Rule not found', '#index', ['status' => 404]);
            return;
        }

        $attrs = is_array($this->params()->upload_whitelist) ? $this->params()->upload_whitelist : [];

        if (isset($attrs['pattern'])) {
            $rule->pattern = trim((string)$attrs['pattern']);
        }
        if (array_key_exists('allowed', $attrs)) {
            $rule->allowed = (int)$attrs['allowed'];
        }
        if (array_key_exists('reason', $attrs)) {
            $rule->reason = trim((string)$attrs['reason']) ?: null;
        }
        if (array_key_exists('note', $attrs)) {
            $rule->note = trim((string)$attrs['note']) ?: null;
        }
        if (array_key_exists('hidden', $attrs)) {
            $rule->hidden = (int)$attrs['hidden'];
        }

        if ($rule->save()) {
            $this->respond_to_success('Whitelist rule updated', '#index', ['api' => $rule->api_attributes()]);
        } else {
            $this->respond_to_error($rule, '#index');
        }
    }

    public function destroy()
    {
        $id = (int)$this->params()->id;
        if ($id <= 0) {
            $this->respond_to_error('Rule not found', '#index', ['status' => 404]);
            return;
        }

        try {
            $rule = UploadWhitelist::find($id);
        } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
            $this->respond_to_error('Rule not found', '#index', ['status' => 404]);
            return;
        }

        $rule->destroy();
        $this->respond_to_success('Whitelist rule deleted', '#index');
    }

    public function is_allowed()
    {
        $url = trim((string)$this->params()->url);

        if ($url === '') {
            $this->respondTo([
                'html' => function() {
                    $this->notice('No URL specified');
                    $this->redirectTo('#index');
                },
                'json' => function() {
                    $this->render(['json' => ['allowed' => false, 'reason' => 'No URL specified'], 'status' => 424]);
                },
                'xml' => function() {
                    $this->render(['xml' => ['allowed' => false, 'reason' => 'No URL specified'], 'root' => 'response', 'status' => 424]);
                }
            ]);
            return;
        }

        $result = UploadWhitelist::is_allowed($url);

        $this->respondTo([
            'html' => function() use ($result, $url) {
                $this->notice(($result['allowed'] ? 'Allowed' : 'Denied') . ': ' . $result['reason']);
                $this->redirectTo('#index');
            },
            'json' => function() use ($result) {
                $this->render(['json' => $result]);
            },
            'xml' => function() use ($result) {
                $this->render(['xml' => $result, 'root' => 'response']);
            }
        ]);
    }
}
