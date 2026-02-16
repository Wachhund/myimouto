<?php
class UserController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'blocked_only'        => ['only' => ['authenticate', 'update', 'edit', 'modifyBlacklist']],
                'janitor_only'        => ['only' => ['invites']],
                'mod_only'            => ['only' => ['block', 'unblock', 'showBlockedUsers']],
                'post_member_only'    => ['only' => ['setAvatar']],
                'no_anonymous'        => ['only' => ['changePassword', 'changeEmail']],
                'set_settings_layout' => ['only' => ['changePassword', 'changeEmail', 'edit']]
            ]
        ];
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
            if (!$this->users)
                $this->users = [];
        } else
            $this->users = [];
        
        $this->respondTo([
            'json' => function() {
                $this->render(['json' => ($this->users)]);
            }
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
            if ($this->user->id == current_user()->id)
                $this->set_title('My profile');
            else
                $this->set_title($this->user->name . "'s profile");
        }
        
        if (current_user()->is_mod_or_higher()) {
            # RP: Missing feature.
            // $this->user_ips = $this->user->user_logs->order('created_at DESC').pluck('ip_addr').uniq
            $this->user_ips = array_unique(UserLog::where(['user_id' => $this->user->id])->order('created_at DESC')->take()->getAttributes('ip_addr'));
        }
        
        $tag_types = CONFIG()->tag_types;
        foreach (array_keys($tag_types) as $k) {
            if (!preg_match('/^[A-Z]/', $k) || $k == 'General' || $k == 'Faults')
                unset($tag_types[$k]);
        }
        $this->tag_types = $tag_types;
        
        $this->respondTo(array(
            'html'
        ));
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
        
        $user = User::where(['name' => $this->params()->username])->first();
        
        $ret['exists'] = false;
        $ret['name'] = $this->params()->username;

        if (!$user) {
            $ret['response'] = "unknown-user";
            $this->respond_to_success("User does not exist", array(), array('api' => $ret));
            return;
        }

        # Return some basic information about the user even if the password isn't given, for
        # UI cosmetics.
        $ret['exists']   = true;
        $ret['id']       = $user->id;
        $ret['name']     = $user->name;
        $ret['no_email'] = !((bool)$user->email);

        $pass = $this->params()->password ?: "";

        $user = User::authenticate($this->params()->username, $pass);

        if (!$user) {
            $ret['response'] = "wrong-password";
            $this->respond_to_success("Wrong password", array(), array('api' => $ret));
            return;
        }

        $ret['pass_hash'] = $user->password_hash;
        $ret['user_info'] = $user->user_info_cookie();
        $ret['response']  = 'success';
        
        $this->respond_to_success("Successful", array(), array('api' => $ret));
    }

    public function login()
    {
        $this->set_title('Login');
    }

    public function create()
    {
        $user = User::create($this->params()->user);
        
        if ($user->errors()->blank()) {
            $this->_save_cookies($user);

            $ret = [
                'exists'    => false,
                'name'      => $user->name,
                'id'        => $user->id,
                'pass_hash' => $user->password_hash,
                'user_info' => $user->user_info_cookie()
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
        $this->session()->delete('user_id');
        $this->cookies()->delete('login');
        $this->cookies()->delete('pass_hash');

        $dest = $this->params()->from ?: '#home';
        $this->respond_to_success("You are now logged out", $dest);
    }

    public function update()
    {
        if ($this->params()->commit == "Cancel") {
            $this->redirectTo('#home');
            return;
        }

        if (current_user()->updateAttributes($this->params()->user)) {
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
            if (!in_array($tag, $tags))
                $tags[] = $tag;
        }
        
        $tags = array_diff($tags, $removed_tags);
        
        if (current_user()->user_blacklisted_tag->updateAttribute('tags', implode("\n", $tags))) {
            $this->respond_to_success("Tag blacklist updated", '#home', ['api' => ['result' => current_user()->blacklisted_tags_array()]]);
        } else {
            $this->respond_to_error(current_user(), '#edit');
        }
    }

    public function removeFromBlacklist()
    {
    }

    public function edit()
    {
        $this->set_title('Edit Account');
        $this->user = current_user();
    }

    public function resetPassword()
    {
        $this->set_title('Reset Password');
        
        if ($this->request()->isPost()) {
            $this->user = User::where(['name' => $this->params()->user['name']])->first();

            if (!$this->user) {
                $this->respond_to_error("That account does not exist", '#reset_password', ['api' => ['result' => "unknown-user"]]);
                return;
            }

            if (!$this->user->email) {
                $this->respond_to_error("You never supplied an email address, therefore you cannot have your password automatically reset",
                                                 '#login', ['api' => ['result' => "no-email"]]);
                return;
            }

            if ($this->user->email != $this->params()->user['email']) {
                $this->respond_to_error("That is not the email address you supplied",
                                                 '#login', ['api' => ['result' => "wrong-email"]]);
                return;
            }
            
            # iTODO:
            try {
                // User.transaction do
                    # If the email is invalid, abort the password reset
                    $new_password = $this->user->reset_password();
                    UserMailer::mail('new_password', [$this->user, $new_password])->deliver();
                    $this->respond_to_success("Password reset. Check your email in a few minutes.",
                                                     '#login', ['api' => ['result' => "success"]]);
                    return;
                // end
            } catch (\Throwable $e) { // rescue Net::SMTPSyntaxError, Net::SMTPFatalError
                Rails::log()->exception($e);
                $this->respond_to_success("Your email address was invalid",
                                                 '#login', ['api' => ['result' => "invalid-email"]]);
                return;
            }
        } else {
            $this->user = new User();
            if ($this->params()->format and $this->params()->format != 'html')
                $this->redirectTo('root');
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
        foreach (array_keys($this->params()->user) as $user_id)
            Ban::destroyAll("user_id = ?", $user_id);

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
            if (!$this->user)
                $this->respond_to_error("Not found", '#index', ['status' => 404]);
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

    public function error()
    {
        $report = $this->params()->report;

        $file = Rails::root() . "/log/user_errors.log";
        if (!is_file($file)) {
            $fh = fopen($file, 'a');
            fclose($fh);
        }
        file_put_contents($file, $report . "\n\n\n-------------------------------------------\n\n\n", FILE_APPEND);

        $this->render(array('json' => array('success' => true)));
    }
    
    protected function init()
    {
        $this->helper('Post', 'TagSubscription', 'Avatar');
    }
    
    protected function _save_cookies($user)
    {
        $this->cookies()->login = ['value' => $user->name, 'expires' => strtotime('+1 year')];
        $this->cookies()->pass_hash = ['value' => $user->password_hash, 'expires' => strtotime('+1 year')];
        $this->cookies()->user_id = ['value' => $user->id, 'expires' => strtotime('+1 year')];
        $this->session()->user_id = $user->id;
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
