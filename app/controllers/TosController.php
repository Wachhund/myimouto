<?php

class TosController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'member_only' => ['only' => ['accept']],
                'admin_only' => ['only' => ['bump_version']],
            ],
        ];
    }

    /**
     * Effective ToS version: DB override takes precedence over config default.
     */
    public static function effective_tos_version()
    {
        $dbVersion = SiteSetting::get('tos_version');
        return $dbVersion !== null ? (int) $dbVersion : (int) CONFIG()->tos_version;
    }

    public function show()
    {
        $this->tos_version = self::effective_tos_version();
        $this->needs_acceptance = false;
        if ($this->current_user && CONFIG()->tos_require_acceptance) {
            $this->needs_acceptance = ($this->current_user->tos_accepted_version ?? 0) < $this->tos_version;
        }

        // Sanitize return_to to prevent XSS (framework form helpers don't escape attribute values).
        $rt = $this->params()->return_to ?? '';
        $safe = $rt && strpos($rt, '/') === 0 && !str_starts_with($rt, '//');
        // Strip characters that could break out of HTML attributes.
        if ($safe && preg_match('/[<>"\']/', $rt)) {
            $safe = false;
        }
        $this->return_to = $safe ? $rt : '';
    }

    public function accept()
    {
        if (!$this->request()->isPost()) {
            $this->redirectTo(['#show']);
            return;
        }

        // AC-4: Validate checkbox is checked.
        if (empty($this->params()->accept)) {
            $rt = $this->params()->return_to ?? '';
            $safe_rt = ($rt && strpos($rt, '/') === 0 && !str_starts_with($rt, '//') && !preg_match('/[<>"\']/', $rt)) ? $rt : '';
            $this->respond_to_error('You must check the acceptance checkbox', ['#show', 'return_to' => $safe_rt]);
            return;
        }

        $tos_version = self::effective_tos_version();
        $this->current_user->updateAttributes([
            'tos_accepted_version' => $tos_version,
            'tos_accepted_at' => date('Y-m-d H:i:s'),
        ]);

        $this->notice('Terms of Service accepted.');
        $return_to = $this->params()->return_to;
        // Validate return_to: must start with /, reject //, reject HTML-breaking characters.
        if ($return_to && strpos($return_to, '/') === 0 && !str_starts_with($return_to, '//') && !preg_match('/[<>"\']/', $return_to)) {
            $this->redirectTo($return_to);
        } else {
            $this->redirectTo('/');
        }
    }

    public function bump_version()
    {
        $current = self::effective_tos_version();
        $new_version = $current + 1;

        SiteSetting::set('tos_version', (string) $new_version);

        ModAction::log('tos_version_bump', [
            'old_version' => $current,
            'new_version' => $new_version,
        ]);

        $this->notice('ToS version bumped from ' . $current . ' to ' . $new_version . '.');
        $this->redirectTo(['#show']);
    }
}
