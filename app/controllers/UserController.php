<?php

class UserController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'blocked_only'        => ['only' => ['authenticate', 'update', 'edit', 'modifyBlacklist']],
                'invites_only'        => ['only' => ['invites']],
                'mod_only'            => ['only' => ['block', 'unblock', 'showBlockedUsers']],
                'post_member_only'    => ['only' => ['setAvatar']],
                'no_anonymous'        => ['only' => ['changePassword', 'changeEmail']],
                'member_only'         => ['only' => ['deleteAccount', 'executeDeleteAccount', 'error']],
                'set_settings_layout' => ['only' => ['changePassword', 'changeEmail', 'edit']],
            ],
        ];
    }

    protected function invites_only()
    {
        if (current_user()->is_janitor_or_higher()) {
            return;
        }

        if (current_user()->is_anonymous()) {
            $this->notice('Access denied');
            $this->redirectTo(['controller' => 'user', 'action' => 'login', 'url' => $this->request()->fullPath()]);
            return;
        }

        $this->notice('Access denied');
        $this->redirectTo(['controller' => 'user', 'action' => 'home']);
    }

    protected function set_settings_layout()
    {
        $this->setLayout('settings');
    }

    public function autocompleteName()
    {
        $keyword = $this->params()->term;
        if (strlen($keyword) >= 2) {
            $this->users = User::where('name LIKE ?', '%' . $keyword . '%')->pluck('name');
            if (!$this->users) {
                $this->users = [];
            }
        } else {
            $this->users = [];
        }

        $this->respondTo([
            'json' => function () {
                $this->render(['json' => ($this->users)]);
            },
        ]);
    }

    # FIXME: this method is crap and only function as temporary workaround
    #                until I convert the controllers to resourceful version which is
    #                planned for 3.2 branch (at least 3.2.1).
    public function removeAvatar()
    {
        # When removing other user's avatar, ensure current user is mod or higher.
        if (current_user()->id != $this->params()->id and !current_user()->is_mod_or_higher()) {
            $this->access_denied();
            return;
        }
        $this->user = User::find($this->params()->id);
        $this->user->avatar_post_id = null;
        if ($this->user->save()) {
            $this->notice('Avatar removed');
        } else {
            $this->notice('Failed removing avatar');
        }
        $this->redirectTo(['#show', 'id' => $this->params()->id]);
    }

    public function changePassword()
    {
        $this->title = 'Change Password';
        $this->setLayout('settings');
    }

    public function changeEmail()
    {
        $this->title = 'Change Email';
        current_user()->current_email = current_user()->email;
        $this->user = current_user();
        $this->setLayout('settings');
    }

    public function show()
    {
        if ($this->params()->name) {
            $this->user = User::where(['name' => $this->params()->name])->first();
        } else {
            $this->user = User::find($this->params()->id);
        }

        if (!$this->user) {
            $this->redirectTo("/404");
        } else {
            if ($this->user->id == current_user()->id) {
                $this->set_title('My profile');
            } else {
                $this->set_title($this->user->name . "'s profile");
            }
        }

        if (current_user()->is_mod_or_higher()) {
            # RP: Missing feature.
            // $this->user_ips = $this->user->user_logs->order('created_at DESC').pluck('ip_addr').uniq
            $this->user_ips = array_unique(UserLog::where(['user_id' => $this->user->id])->order('created_at DESC')->take()->getAttributes('ip_addr'));
        }

        $tag_types = CONFIG()->tag_types;
        foreach (array_keys($tag_types) as $k) {
            if (!preg_match('/^[A-Z]/', $k) || $k == 'General' || $k == 'Faults') {
                unset($tag_types[$k]);
            }
        }
        $this->tag_types = $tag_types;

        $this->respondTo([
            'html',
        ]);
    }

    public function invites()
    {
        if ($this->request()->isPost()) {
            if ($this->params()->member) {
                try {
                    current_user()->invite($this->params()->member['name'], $this->params()->member['level']);
                    $this->notice("User was invited");

                } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
                    $this->notice("Account not found");

                } catch (User_NoInvites $e) {
                    $this->notice("You have no invites for use");

                } catch (User_HasNegativeRecord $e) {
                    $this->notice("This use has a negative record and must be invited by an admin");
                }
            }

            $this->redirectTo('#invites');
        } else {
            $this->invited_users = User::where("invited_by = ?", current_user()->id)->order("lower(name)")->take();
        }
    }

    public function home()
    {
        $this->set_title('My Account');
    }

    public function index()
    {
        $this->set_title('Users');

        $this->users = User::generate_sql($this->params()->all())->paginate($this->page_number(), 20);
        $this->respond_to_list("users");
    }

    public function authenticate()
    {
        $this->_save_cookies(current_user());
        $path = $this->params()->url ?: '#home';
        $this->respond_to_success("You are now logged in", $path);
    }

    public function check()
    {
        if (!$this->request()->isPost()) {
            $this->redirectTo('root');
            return;
        }

        $ip = $this->request()->remoteIp();

        // AC-6: IP-based rate limit (10 per 15 minutes)
        if (\MyImouto\RateLimiter::isLimited('login_ip:' . $ip, 10, 900)) {
            $retry = \MyImouto\RateLimiter::retryAfter(900);
            $this->response()->headers()->add('Retry-After', (string) $retry);
            $this->respond_to_error(
                'Too many login attempts. Please try again later.',
                ['#login'],
                ['status' => 429, 'api' => ['retry_after' => $retry]],
            );
            return;
        }

        $user = User::where(['name' => $this->params()->username])->first();

        $ret['exists'] = false;
        $ret['name'] = $this->params()->username;

        if (!$user) {
            \MyImouto\RateLimiter::hit('login_ip:' . $ip, 900);
            $ret['response'] = "unknown-user";
            $this->respond_to_success("User does not exist", [], ['api' => $ret]);
            return;
        }

        // AC-10: Account-based lockout (20 per 30 minutes)
        if (\MyImouto\RateLimiter::isLimited('login_account:' . $user->id, 20, 1800)) {
            $retry = \MyImouto\RateLimiter::retryAfter(1800);
            $this->response()->headers()->add('Retry-After', (string) $retry);
            $this->respond_to_error(
                'Too many login attempts. Please try again later.',
                ['#login'],
                ['status' => 429, 'api' => ['retry_after' => $retry]],
            );
            return;
        }

        # Return some basic information about the user even if the password isn't given, for
        # UI cosmetics.
        $ret['exists']   = true;
        $ret['id']       = $user->id;
        $ret['name']     = $user->name;
        $ret['no_email'] = !((bool) $user->email);

        $pass = $this->params()->password ?: "";

        $authenticated_user = User::authenticate($this->params()->username, $pass);

        if (!$authenticated_user) {
            \MyImouto\RateLimiter::hit('login_ip:' . $ip, 900);
            \MyImouto\RateLimiter::hit('login_account:' . $user->id, 1800);
            $ret['response'] = "wrong-password";
            $this->respond_to_success("Wrong password", [], ['api' => $ret]);
            return;
        }

        // Set login cookies server-side (remember_token is HttpOnly)
        $this->_save_cookies($authenticated_user);

        $ret['user_info'] = $authenticated_user->user_info_cookie();
        $ret['response']  = 'success';

        $this->respond_to_success("Successful", [], ['api' => $ret]);
    }

    public function login()
    {
        $this->set_title('Login');
    }

    public function create()
    {
        // AC-7: Registration rate limit (3 per IP per hour)
        $ip = $this->request()->remoteIp();
        if (\MyImouto\RateLimiter::isLimited('signup_ip:' . $ip, 3, 3600)) {
            $retry = \MyImouto\RateLimiter::retryAfter(3600);
            $this->response()->headers()->add('Retry-After', (string) $retry);
            $this->respond_to_error(
                'Too many registration attempts. Please try again later.',
                ['#signup'],
                ['status' => 429, 'api' => ['retry_after' => $retry]],
            );
            return;
        }

        \MyImouto\RateLimiter::hit('signup_ip:' . $ip, 3600);
        $user = User::create($this->params()->user);

        if ($user->errors()->blank()) {
            $this->_save_cookies($user);

            $ret = [
                'exists'    => false,
                'name'      => $user->name,
                'id'        => $user->id,
                'user_info' => $user->user_info_cookie(),
            ];

            $this->respond_to_success("New account created", '#home', ['api' => array_merge(['response' => "success"], $ret)]);
        } else {
            $error = $user->errors()->fullMessages(", ");
            $this->respond_to_success("Error: " . $error, '#signup', ['api' => ['response' => "error", 'errors' => $user->errors()->fullMessages()]]);
        }
    }

    public function signup()
    {
        $this->set_title('Signup');
        $this->user = new User();
    }

    public function logout()
    {
        $this->set_title('Logout');

        // Invalidate remember token in DB
        if (!current_user()->is_anonymous()) {
            current_user()->updateAttribute('remember_token', null);
        }

        $this->session()->delete('user_id');
        $this->cookies()->delete('login');
        $this->cookies()->delete('pass_hash');
        $this->cookies()->delete('remember_token');

        $dest = $this->params()->from ?: '#home';
        $this->respond_to_success("You are now logged out", $dest);
    }

    public function update()
    {
        if ($this->params()->commit == "Cancel") {
            $this->redirectTo('#home');
            return;
        }

        $update_params = $this->sanitized_user_update_params();

        if (current_user()->updateAttributes($update_params)) {
            $this->respond_to_success("Account settings saved", '#edit');
        } else {
            if ($this->params()->render and $this->params()->render['view']) {
                $this->render(['action' => $this->_get_view_name_for_edit($this->params()->render['view'])]);
            } else {
                $this->respond_to_error(current_user(), '#edit');
            }
        }
    }

    public function modifyBlacklist()
    {
        $added_tags = $this->params()->add ?: [];
        $removed_tags = $this->params()->remove ?: [];

        $tags = current_user()->blacklisted_tags_array();
        foreach ($added_tags as $tag) {
            if (!in_array($tag, $tags)) {
                $tags[] = $tag;
            }
        }

        $tags = array_diff($tags, $removed_tags);

        if (current_user()->user_blacklisted_tag->updateAttribute('tags', implode("\n", $tags))) {
            $this->respond_to_success("Tag blacklist updated", '#home', ['api' => ['result' => current_user()->blacklisted_tags_array()]]);
        } else {
            $this->respond_to_error(current_user(), '#edit');
        }
    }

    public function removeFromBlacklist() {}

    public function edit()
    {
        $this->set_title('Edit Account');
        $this->user = current_user();
    }

    public function resetPassword()
    {
        $this->set_title('Reset Password');

        // Handle token-based password reset (user clicked link in email)
        if ($this->params()->token && $this->request()->isGet()) {
            $this->reset_token = $this->params()->token;
            return;
        }

        // Handle new password submission with token
        if ($this->params()->token && $this->request()->isPost() && isset($this->params()->user['password'])) {
            $raw_token = $this->params()->token;
            // Find user by reset token hash
            $hashed = hash('sha256', $raw_token);
            $user = User::where(['reset_token' => $hashed])->first();

            if (!$user || !$user->validate_reset_token($raw_token)) {
                $this->respond_to_error("Invalid or expired reset token", '#reset_password', ['api' => ['result' => "invalid-token"]]);
                return;
            }

            $new_password = $this->params()->user['password'];
            if (strlen($new_password) < 5) {
                $this->respond_to_error("Password must be at least 5 characters", '#reset_password', ['api' => ['result' => "password-too-short"]]);
                return;
            }

            $user->apply_new_password($new_password);
            $this->respond_to_success("Password has been reset. You can now log in.", '#login', ['api' => ['result' => "success"]]);
            return;
        }

        // Handle reset request (generate token + send email)
        if ($this->request()->isPost()) {
            // AC-8: Rate limit reset requests (3 per IP per hour)
            // Token-based password submissions are not rate-limited here.
            $ip = $this->request()->remoteIp();
            if (\MyImouto\RateLimiter::isLimited('reset_ip:' . $ip, 3, 3600)) {
                $retry = \MyImouto\RateLimiter::retryAfter(3600);
                $this->response()->headers()->add('Retry-After', (string) $retry);
                $this->respond_to_error(
                    'Too many password reset attempts. Please try again later.',
                    ['#reset_password'],
                    ['status' => 429, 'api' => ['retry_after' => $retry]],
                );
                return;
            }

            \MyImouto\RateLimiter::hit('reset_ip:' . $ip, 3600);
            $this->user = User::where(['name' => $this->params()->user['name']])->first();

            if (!$this->user) {
                $this->respond_to_error("That account does not exist", '#reset_password', ['api' => ['result' => "unknown-user"]]);
                return;
            }

            if (!$this->user->email) {
                $this->respond_to_error(
                    "You never supplied an email address, therefore you cannot have your password automatically reset",
                    '#login',
                    ['api' => ['result' => "no-email"]],
                );
                return;
            }

            if ($this->user->email != $this->params()->user['email']) {
                $this->respond_to_error(
                    "That is not the email address you supplied",
                    '#login',
                    ['api' => ['result' => "wrong-email"]],
                );
                return;
            }

            try {
                $reset_token = $this->user->reset_password();
                UserMailer::mail('password_reset', [$this->user, $reset_token])->deliver();
                $this->respond_to_success(
                    "Password reset link sent. Check your email in a few minutes.",
                    '#login',
                    ['api' => ['result' => "success"]],
                );
                return;
            } catch (\Throwable $e) {
                Rails::log()->exception($e);
                $this->respond_to_success(
                    "Your email address was invalid",
                    '#login',
                    ['api' => ['result' => "invalid-email"]],
                );
                return;
            }
        } else {
            $this->user = new User();
            if ($this->params()->format and $this->params()->format != 'html') {
                $this->redirectTo('root');
            }
        }
    }

    public function block()
    {
        $this->user = User::find($this->params()->id);

        if ($this->request()->isPost()) {
            if ($this->user->is_mod_or_higher()) {
                $this->notice("You can not ban other moderators or administrators");
                $this->redirectTo(['#block', 'id' => $this->params()->id]);
                return;
            }
            !is_array($this->params()->ban) && $this->params()->ban = [];

            $attrs = array_merge($this->params()->ban, ['banned_by' => current_user()->id, 'user_id' => $this->params()->id]);

            Ban::create($attrs);
            $this->redirectTo('#show_blocked_users');
        } else {
            $this->ban = new Ban(['user_id' => $this->user->id, 'duration' => "1"]);
        }
    }

    public function unblock()
    {
        foreach (array_keys($this->params()->user) as $user_id) {
            Ban::destroyAll("user_id = ?", $user_id);
        }

        $this->redirectTo('#show_blocked_users');
    }

    public function showBlockedUsers()
    {
        $this->set_title('Blocked Users');

        #$this->users = User.find(:all, 'select' => "users.*", 'joins' => "JOIN bans ON bans.user_id = users.id", 'conditions' => ["bans.banned_by = ?", current_user()->id])
        $this->users = User::order("expires_at ASC")->select("users.*")->joins("JOIN bans ON bans.user_id = users.id")->take();
        $this->ip_bans = IpBans::all();
    }

    /**
     * MyImouto:
     * MyImouto:
     * Moebooru doesn't use email activation,
     * so these 2 following methods aren't used.
     * Also, User::confirmation_hash() method is missing.
     */
    // public function resendConfirmation()
    // {
    // if (!CONFIG()->enable_account_email_activation) {
    // $this->access_denied();
    // return;
    // }

    // if ($this->request()->isPost()) {
    // $user = User::find_by_email($this->params()->email);

    // if (!$user) {
    // $this->notice("No account exists with that email");
    // $this->redirectTo('#home')
    // return;
    // }

    // if ($user->is_blocked_or_higher()) {
    // $this->notice("Your account is already activated");
    // $this->redirectTo('#home');
    // return;
    // }

    // UserMailer::deliver_confirmation_email($user);
    // $this->notice("Confirmation email sent");
    // $this->redirectTo('#home');
    // }
    // }

    // public function activateUser()
    // {
    // if (!CONFIG()->enable_account_email_activation) {
    // $this->access_denied();
    // return;
    // }

    // $this->notice("Invalid confirmation code");

    // $users = User::find_all(['conditions' => ["level = ?", CONFIG()->user_levels["Unactivated"]]]);
    // foreach ($users as $user) {
    // if (User::confirmation_hash($user->name) == $this->params()->hash) {
    // $user->updateAttribute('level', CONFIG()->starting_level);
    // $this->notice("Account has been activated");
    // break;
    // }
    // }

    // $this->redirectTo('#home');
    // }

    public function setAvatar()
    {
        $this->user = current_user();
        if ($this->params()->user_id) {
            $this->user = User::find($this->params()->user_id);
            if (!$this->user) {
                $this->respond_to_error("Not found", '#index', ['status' => 404]);
            }
        }

        if (!$this->user->is_anonymous() && !current_user()->has_permission($this->user, 'id')) {
            $this->access_denied();
            return;
        }

        if ($this->request()->isPost()) {
            if ($this->user->set_avatar($this->params()->all())) {
                $this->redirectTo(['#show', 'id' => $this->user->id]);
            } else {
                $this->respond_to_error($this->user, '#home');
            }
        }

        if (!$this->user->is_anonymous() && $this->params()->id == $this->user->avatar_post_id) {
            $this->old = $this->params();
        }

        $this->params = $this->params();
        $this->post = Post::find($this->params()->id);
    }

    /**
     * GET: Show self-deletion confirmation form.
     */
    public function deleteAccount()
    {
        $this->set_title('Delete Account');

        // Staff accounts cannot be self-deleted
        if (current_user()->is_mod_or_higher()) {
            $this->respond_to_error(
                "Staff accounts cannot be self-deleted. Contact an administrator.",
                '#home',
            );
            return;
        }

        // Blocked users cannot self-delete
        if (current_user()->level <= CONFIG()->user_levels['Blocked']) {
            $this->respond_to_error(
                "Banned accounts cannot be self-deleted",
                '#home',
            );
            return;
        }

        // Account must be at least 1 week old
        if (strtotime(current_user()->created_at) > strtotime('-1 week')) {
            $this->respond_to_error(
                "Account must be at least 1 week old to be deleted",
                '#home',
            );
            return;
        }
    }

    /**
     * POST: Execute self-service account deletion.
     */
    public function executeDeleteAccount()
    {
        $password = trim((string) ($this->params()->password ?: ''));

        if ($password === '') {
            $this->respond_to_error("Password is required", '#delete_account');
            return;
        }

        if (!$this->params()->confirm_deletion) {
            $this->respond_to_error("You must confirm the deletion", '#delete_account');
            return;
        }

        try {
            \MyImouto\UserDeletion\DeletionService::selfDelete(current_user(), $password);

            // Log the user out after successful deletion
            $this->session()->delete('user_id');
            $this->cookies()->delete('login');
            $this->cookies()->delete('pass_hash');
            $this->cookies()->delete('remember_token');

            $this->respond_to_success(
                "Your account has been deleted",
                '#home',
            );
        } catch (\RuntimeException $e) {
            $this->respond_to_error($e->getMessage(), '#delete_account');
        }
    }

    public function error()
    {
        $report = (string) ($this->params()->report ?? '');
        $report = substr($report, 0, 2048);
        $report = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $report);

        $file = Rails::root() . "/log/user_errors.log";
        if (!is_file($file)) {
            $fh = fopen($file, 'a');
            fclose($fh);
        }
        file_put_contents($file, $report . "\n\n\n-------------------------------------------\n\n\n", FILE_APPEND);

        $this->render(['json' => ['success' => true]]);
    }

    protected function init()
    {
        $this->helper('Post', 'TagSubscription', 'Avatar');
    }

    protected function _save_cookies($user)
    {
        $is_https = str_starts_with(CONFIG()->url_base, 'https://');
        $cookie_flags = [
            'expires'  => strtotime('+1 year'),
            'httponly'  => true,
            'samesite' => 'Lax',
            'secure'   => $is_https,
        ];

        // Generate remember token
        $raw_token = bin2hex(random_bytes(32));
        $hashed_token = hash('sha256', $raw_token);
        $user->updateAttribute('remember_token', $hashed_token);

        $this->cookies()->login = array_merge($cookie_flags, ['value' => $user->name]);
        $this->cookies()->remember_token = array_merge($cookie_flags, ['value' => $raw_token]);
        $this->cookies()->user_id = ['value' => $user->id, 'expires' => strtotime('+1 year')];
        $this->session()->user_id = $user->id;

        // AC-9: Store password hash token for session invalidation on password change.
        if ($user->bcrypt_password_hash) {
            $this->session()->ph = substr(hash('sha256', $user->bcrypt_password_hash), 0, 16);
        }
    }

    protected function sanitized_user_update_params()
    {
        $user_params = $this->params()->user;
        if (!is_array($user_params)) {
            return [];
        }

        $allowed_fields = [
            'blacklisted_tags',
            'my_tags',
            'always_resize_images',
            'receive_dmails',
            'show_samples',
            'use_browser',
            'show_advanced_editing',
            'pool_browse_mode',
            'language',
            'secondary_languages',
        ];

        $boolean_fields = [
            'always_resize_images',
            'receive_dmails',
            'show_samples',
            'use_browser',
            'show_advanced_editing',
            'pool_browse_mode',
        ];

        $sanitized = [];
        foreach ($allowed_fields as $field) {
            if (!array_key_exists($field, $user_params)) {
                continue;
            }
            $sanitized[$field] = $user_params[$field];
        }

        foreach ($boolean_fields as $field) {
            if (!array_key_exists($field, $sanitized)) {
                continue;
            }
            $sanitized[$field] = $this->to_boolean_int($sanitized[$field]);
        }

        return $sanitized;
    }

    protected function to_boolean_int($value)
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
    }

    protected function _get_view_name_for_edit($param)
    {
        switch ($param) {
            case 'change_email':
                return 'change_email';
            case 'change_password':
                return 'change_password';
            default:
                return 'edit';
        }
    }
}
