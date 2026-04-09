<?php
class ApplicationController extends Rails\ActionController\Base
{
    protected $authenticated_with_api_key = false;

    public function __call($method, $params)
    {
        if (preg_match("/^(\w+)_only$/", $method, $m)) {
            if (current_user()->{'is_' . $m[1] . '_or_higher'}()) {
                return true;
            } else {
                $this->access_denied();
                return false;
            }
        }
        
        # For many actions, GET invokes the HTML UI, and a POST actually invokes
        # the action, so we often want to require higher access for POST (so the UI
        # can invoke the login dialog).
        elseif (preg_match("/^post_(\w+)_only$/", $method, $m)) {
            if (!$this->request()->isPost())
                return true;
            elseif (current_user()->{'is_' . $m[1] . '_or_higher'}())
                return true;
            else {
                $this->access_denied();
                return false;
            }
        }
        
        return parent::__call($method, $params);
    }
    
    /**
     * This is found in SessionHelper in Moebooru
     */
    public function page_number()
    {
        if (!isset($this->page_number))
            $this->page_number = $this->params()->page ?: 1;
        return $this->page_number;
    }
    
    # LoginSystem {
    protected function access_denied()
    {
        $previous_url = $this->params()->url || $this->request()->fullPath();
        
        $this->respondTo([
            'html' => function()use($previous_url) {
                $this->notice('Access denied');
                $this->redirectTo("user#login", array('url' => $previous_url));
            },
            'xml'  => function() {
                $this->render(array('xml' => array('success' => false, 'reason' => "access denied"), 'root' => "response", 'status' => 403));
            },
            'json' => function() {
                $this->render(array('json' => array('success' => false, 'reason' => "access denied"), 'status' => 403));
            }
        ]);
    }
    
    public function user_can_see_posts()
    {
        if (!current_user()->can_see_posts()) {
            $this->access_denied();
        }
    }

    protected function set_current_user()
    {
        $user = null;
        $AnonymousUser = array(
            'id'                       => 0,
            'level'                    => 0,
            'name'                     => "Anonymous",
            'show_samples'             => true,
            'language'                 => '',
            'secondary_languages'      => '',
            'pool_browse_mode'         => 1,
            'always_resize_images'     => true,
            'ip_addr'                  => $this->request()->remoteIp()
        );
        
        $api_auth_attempted = false;
        $this->authenticated_with_api_key = false;

        if (!current_user() && $this->session()->user_id) {
            $user = User::where(['id' => $this->session()->user_id])->first();
            // AC-9: Session invalidation on password change.
            // Compare stored password hash token with current hash.
            if ($user && $this->session()->ph) {
                $current_ph = $user->bcrypt_password_hash
                    ? crc32($user->bcrypt_password_hash)
                    : 0;
                if ((int)$this->session()->ph !== $current_ph) {
                    // Password changed since session was created -- invalidate.
                    $this->session()->delete('user_id');
                    $this->session()->delete('ph');
                    $user = null;
                }
            }
        } else {
            if (isset($this->params()->api_key) && isset($this->params()->username)) {
                $api_auth_attempted = true;
                // Try new api_keys table first (PROJ-22), then legacy users.api_key fallback.
                $apiKey = ApiKey::authenticate($this->params()->api_key);
                if ($apiKey) {
                    $user = User::where(['id' => $apiKey->user_id, 'name' => $this->params()->username])->first();
                    if ($user) {
                        $apiKey->touch_usage(
                            $this->request()->remoteIp(),
                            $this->request()->userAgent() ?? ''
                        );
                        $this->authenticated_with_api_key = true;
                    } else {
                        $apiKey = null; // username mismatch
                    }
                }
                if (!$user) {
                    // Legacy single-key fallback (users.api_key column)
                    $user = User::authenticate_with_api_key($this->params()->username, $this->params()->api_key);
                    if ($user) {
                        $this->authenticated_with_api_key = true;
                    }
                }
            }

            if (!$user && $this->cookies()->login && $this->cookies()->remember_token) {
                $user = User::authenticate_remember_token($this->cookies()->login, $this->cookies()->remember_token);
            } elseif (!$user && isset($this->params()->user['name']) && isset($this->params()->user['password'])) {
                $ip = $this->request()->remoteIp();
                if (!\MyImouto\RateLimiter::isLimited('login_ip:' . $ip, 10, 900)) {
                    $user = User::authenticate($this->params()->user['name'], $this->params()->user['password']);
                    if (!$user) {
                        \MyImouto\RateLimiter::hit('login_ip:' . $ip, 900);
                        $target = User::where(['name' => $this->params()->user['name']])->first();
                        if ($target) {
                            \MyImouto\RateLimiter::hit('login_account:' . $target->id, 1800);
                        }
                    }
                }
            }

            if ($api_auth_attempted && !$user) {
                $this->access_denied();
                return;
            }

            $user && $user->updateAttribute('last_logged_in_at', date('Y-m-d H:i:s'));
        }
        if ($user) {
            if ($user->is_blocked() && $user->ban && $user->ban->expires_at < date('Y-m-d H:i:s')) {
                $user->updateAttribute('level', CONFIG()->starting_level);
                Ban::destroyAll("user_id = ?", $user->id);
            }
            $this->session()->user_id = $user->id;
            // AC-9: Store password hash token for session invalidation check.
            if (!$this->session()->ph && $user->bcrypt_password_hash) {
                $this->session()->ph = crc32($user->bcrypt_password_hash);
            }
        } else {
            $user = new User();
            $user->assignAttributes($AnonymousUser, ['without_protection' => true]);
        }
        
        User::set_current_user($user);
        $this->current_user = $user;
        
        # For convenient access in activerecord models
        $user->ip_addr = $this->request()->remoteIp();
        
        Moebooru\Versioning\Versioning::init_history();
        
        if (!current_user()->is_anonymous())
            current_user()->log($this->request()->remoteIp());
    }

    # iTODO:
    protected function set_country()
    {
        current_user()->country = '--';
        // current_user()->country = Rails::cache()->fetch(['type' => 'geoip', 'ip' => $this->request()->remote_ip()], ['expires_in' => '+1 month']) do
            // begin
                // GeoIP->new(Rails.root.join('db', 'GeoIP.dat').to_s).country($this->request()->remote_ip()).country_code2
            // rescue
                // '--'
            // end
        // end
    }
    
    # } RespondToHelpers {
    
    protected function respond_to_success($notice, $redirect_to_params, array $options = array())
    {
        $extra_api_params = isset($options['api']) ? $options['api'] : array();

        $this->respondTo(array(
            'html' => function() use ($notice, $redirect_to_params) {
                $this->notice($notice);
                $this->redirectTo($redirect_to_params);
            },
            'json' => function() use ($extra_api_params) {
                $this->render(array('json' => array_merge($extra_api_params, array('success' => true))));
            },
            'xml' => function() use ($extra_api_params) {
                $this->render(array('xml' => array_merge($extra_api_params, array('success' => true)), 'root' => "response"));
            }
        ));
    }

    protected function respond_to_error($obj, $redirect_to_params, $options = array())
    {
        !is_array($redirect_to_params) && $redirect_to_params = array($redirect_to_params);
        $extra_api_params = isset($options['api']) ? $options['api'] : array();
        $status = isset($options['status']) ? $options['status'] : 500;

        if ($obj instanceof Rails\ActiveRecord\Base) {
            $obj = $obj->errors()->fullMessages(", ");
            $status = 420;
        }
        
        if ($status == 420)
            $status = "420 Invalid Record";
        elseif ($status == 421)
            $status = "421 User Throttled";
        elseif ($status == 422)
            $status = "422 Locked";
        elseif ($status == 423)
            $status = "423 Already Exists";
        elseif ($status == 424)
            $status = "424 Invalid Parameters";

        $this->respondTo(array(
            'html' => function()use($obj, $redirect_to_params) {
                $this->notice("Error: " . $obj);
                $this->redirectTo($redirect_to_params);
            },
            
            'json' => function()use($obj, $extra_api_params, $status) {
                $this->render(array('json' => array_merge($extra_api_params, array('success' => false, 'reason' => $obj)), 'status' => $status));
            },
            
            'xml' => function()use($obj, $extra_api_params, $status) {
                $this->render(array('xml' => array_merge($extra_api_params, array('success' => false, 'reason' => $obj)), 'root' => "response", 'status' => $status));
            }
        ));
    }

    protected function respond_to_list($inst_var_name, array $formats = array())
    {
        $inst_var = $this->$inst_var_name;
        
        $this->respondTo(array(
            'html',
            isset($formats['atom']) ? 'atom' : null,
            'json' => function() use ($inst_var) {
                $this->render(array('json' => $inst_var->toJson()));
            },
            'xml'  => function() use ($inst_var, $inst_var_name) {
                $this->render(array('xml' => $inst_var, 'root' => $inst_var_name));
            }
        ));
    }

    protected function _render_error($record)
    {
        $this->record = $record;
        $this->render(['inline' => '<?= $this->record->errors()->fullMessages("<br />") ?>', 'layout' => "bare", 'status' => 500]);
    }
    # }
    
  // protected :build_cache_key
  // protected :get_cache_key
    
    public function get_ip_ban()
    {
        $ban = IpBans::where("ip_addr = ?", $this->request()->remoteIp())->first();
        return $ban ?: null;
    }
    
    protected function check_ip_ban()
    {
         if ($this->request()->controller() == "banned" and $this->request()->action() == "index") {
            return;
        }
        
        $ban = $this->get_ip_ban();
        if (!$ban) {
            return;
        }

        if ($ban->expires_at && $ban->expires_at < date('Y-m-d H:i:s')) {
            IpBans::destroyAll("ip_addr = ?", $this->request()->remoteIp());
            return;
        }

        $this->redirectTo('banned#index');
    }

    protected function check_tos_acceptance()
    {
        if (!CONFIG()->tos_require_acceptance) {
            return;
        }

        // Anonymous users are exempt.
        if (!$this->current_user || current_user()->is_anonymous()) {
            return;
        }

        // Exempt controllers/actions that must remain accessible.
        $controller = $this->request()->controller();
        $action = $this->request()->action();

        $exempt = [
            'tos'    => ['show', 'accept'],
            'user'   => ['login', 'logout', 'signup', 'authenticate', 'check'],
            'static' => true, // all actions
            'banned' => true,
        ];

        if (isset($exempt[$controller])) {
            if ($exempt[$controller] === true || in_array($action, $exempt[$controller])) {
                return;
            }
        }

        $tos_version = TosController::effective_tos_version();
        $accepted = $this->current_user->tos_accepted_version ?? 0;

        if ((int)$accepted >= $tos_version) {
            return;
        }

        $return_to = $this->request()->fullPath();
        $tos_url = $this->urlFor(['controller' => 'tos', 'action' => 'show']);

        $this->respondTo([
            'html' => function() use ($return_to) {
                $this->notice('You must accept the current Terms of Service to continue.');
                $this->redirectTo(['controller' => 'tos', 'action' => 'show', 'return_to' => $return_to]);
            },
            'xml' => function() use ($tos_url) {
                $this->render([
                    'xml' => ['success' => false, 'reason' => 'Terms of Service acceptance required', 'tos_url' => $tos_url],
                    'root' => 'response',
                    'status' => 451,
                ]);
            },
            'json' => function() use ($tos_url) {
                $this->render([
                    'json' => ['success' => false, 'reason' => 'Terms of Service acceptance required', 'tos_url' => $tos_url],
                    'status' => 451,
                ]);
            },
        ]);
    }

    protected function save_tags_to_cookie()
    {
        if ($this->params()->tags || (is_array($this->params()->post) && isset($this->params()->post['tags']))) {
            $post_tags = isset($this->params()->post['tags']) ? (string)$this->params()->post['tags'] : '';
            $tags = TagAlias::to_aliased(explode(' ', (strtolower($this->params()->tags ?: $post_tags))));
            if ($recent_tags = trim($this->cookies()->recent_tags))
                $tags = array_merge($tags, explode(' ', $recent_tags));
            $this->cookies()->recent_tags = implode(" ", array_slice($tags, 0, 20));
        }
    }

    public function set_cache_headers()
    {
        $this->response()->headers()->add("Cache-Control", "max-age=300");
    }

    # iTODO:
    public function cache_action()
    {
        // if ($this->request()->method() == 'get' && !preg_match('/Googlebot/', $this->request()->env()) && $this->params()->format != "xml" && $this->params()->format != "json") {
            // list($key, $expiry) = $this->get_cache_key($this->controller_name(), $this->action_name(), $this->params(), 'user' => current_user());

            // if ($key && count($key) < 200) {
                // $cached = Rails::cache()->read($key);

                // if ($cached) {
                    // $this->render(['text' => $cached, 'layout' => false]);
                    // return;
                // }
            // }

            // $this->yield();

            // if ($key && strpos($this->response->headers['Status'], '200') === 0) {
                // Rails::cache()->write($key, $this->response->body, ['expires_in' => $expiry]);
            // }
        // } else {
            // $this->yield();
        // }
    }

    protected function init_cookies()
    {
        if ($this->request()->format() == "xml" || $this->request()->format() == "json") {
            return;
        }

        $forum_posts = ForumPost::where("parent_id IS NULL")->order("updated_at DESC")->limit(10)->take();
        $this->cookies()->current_forum_posts = json_encode(array_map(function($fp) {
            if (current_user()->is_anonymous()) {
                $updated = false;
            } else {
                $updated = $fp->updated_at > current_user()->last_forum_topic_read_at;
            }
            return [$fp->title, $fp->id, $updated, ceil($fp->response_count / 30.0)];
        }, $forum_posts->members()));

        $this->cookies()->country = current_user()->country;

        if (!current_user()->is_anonymous()) {
            $this->cookies()->user_id = (string)current_user()->id;
            
            $this->cookies()->user_info = current_user()->user_info_cookie();

            $this->cookies()->has_mail = (current_user()->has_mail ? "1" : "0");
            
            $this->cookies()->forum_updated = (current_user()->is_privileged_or_higher() && ForumPost::updated(current_user()) ? "1" : "0");
            
            $this->cookies()->comments_updated = (current_user()->is_privileged_or_higher() && Comment::updated(current_user()) ? "1" : "0");
            
            if (current_user()->is_janitor_or_higher()) {
                $mod_pending = Post::where("status = 'flagged' OR status = 'pending'")->count();
                $this->cookies()->mod_pending = (string)$mod_pending;
            }

            if (current_user()->is_blocked()) {
                if (current_user()->ban)
                    $this->cookies()->block_reason = "You have been blocked. Reason: ".current_user()->ban->reason.". Expires: ".substr(current_user()->ban->expires_at, 0, 10);
                else
                    $this->cookies()->block_reason = "You have been blocked.";
            } else
                $this->cookies()->block_reason = "";
            
            $this->cookies()->resize_image = (current_user()->always_resize_images ? "1" : "0");

            $this->cookies()->show_advanced_editing = (current_user()->show_advanced_editing ? "1" : "0");
            $this->cookies()->my_tags = current_user()->my_tags;
            $this->cookies()->blacklisted_tags = json_encode(current_user()->blacklisted_tags_array());
            $this->cookies()->held_post_count = (string)current_user()->held_post_count();
        } else {
            $this->cookies()->delete('user_info');
            $this->cookies()->delete('login');
            $this->cookies()->blacklisted_tags = json_encode(CONFIG()->default_blacklists);
        }
        
        if ($this->session()->notice) {
            $this->cookies()->notice = $this->session()->notice;
            $this->session()->delete('notice');
        }
    }
    
    protected function set_title($title = null)
    {
        if (!$title)
            $title = CONFIG()->app_name;
        else
            $title .= ' | ' . CONFIG()->app_name;
        $this->page_title = $title;
    }
    
    protected function notice($str)
    {
        $this->session()->notice = $str;
    }
    
    protected function set_locale()
    {
        if ($this->params()->locale and in_array($this->params()->locale, CONFIG()->available_locales)) {
            $this->cookies()->locale = [ 'value' => $this->params()->locale, 'expires' => '+1 year' ];
            $this->I18n()->setLocale($this->params()->locale);
        } elseif ($this->cookies()->locale and in_array($this->cookies()->locale, CONFIG()->available_locales)) {
            $this->I18n()->setLocale($this->cookies()->locale);
        } else
            $this->I18n()->setLocale(CONFIG()->default_locale);
    }

    protected function sanitize_params()
    {
        if ($this->params()->page) {
            if ($this->params()->page < 1)
                $this->params()->page = 1;
        } else
            $this->params()->page = 1;
    }

    protected function admin_only()
    {
        if (!current_user()->is_admin())
            $this->access_denied();
    }
    
    protected function member_only()
    {
        if (!current_user()->is_member_or_higher())
            $this->access_denied();
    }
    
    protected function post_privileged_only()
    {
        if (!current_user()->is_privileged_or_higher())
            $this->access_denied();
    }
    
    protected function post_member_only()
    {
        if (!current_user()->is_member_or_higher())
            $this->access_denied();
    }
    
    protected function no_anonymous()
    {
        if (current_user()->is_anonymous())
            $this->access_denied();
    }

    protected function sanitize_id()
    {
        $this->params()->id = (int)$this->params()->id;
    }

    protected function form_authenticity_token()
    {
        if (empty($this->session()->csrf_token)) {
            $this->session()->csrf_token = $this->generate_csrf_token();
        }

        return (string)$this->session()->csrf_token;
    }

    protected function valid_authenticity_token($token)
    {
        $expected = (string)$this->session()->csrf_token;
        $provided = (string)$token;

        if ($expected === '' || $provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    protected function authenticated_with_api_key_request()
    {
        return !empty($this->authenticated_with_api_key);
    }

    /**
     * Makes the CSRF token available to all views as $this->csrf_token.
     */
    protected function set_csrf_token()
    {
        $this->csrf_token = $this->form_authenticity_token();
    }

    /**
     * Global CSRF verification before-filter.
     * Skips safe HTTP methods (GET, HEAD, OPTIONS) and API-key-authenticated requests.
     * Checks token from: params (csrf_token / authenticity_token) and X-CSRF-Token header.
     */
    protected function verify_authenticity_token()
    {
        $requestMethod = $this->request()->get('REQUEST_METHOD') ?: 'GET';
        if (in_array($requestMethod, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        if ($this->authenticated_with_api_key_request()) {
            return;
        }

        $token = $this->params()->csrf_token
            ?? $this->params()->authenticity_token
            ?? '';

        if ($token === '') {
            $headerToken = $this->request()->get('HTTP_X_CSRF_TOKEN');
            if ($headerToken) {
                $token = $headerToken;
            }
        }

        if ($this->valid_authenticity_token($token)) {
            return;
        }

        $this->respondTo([
            'html' => function () {
                $this->render(['text' => 'Invalid authenticity token', 'status' => 403]);
            },
            'json' => function () {
                $this->render(['json' => ['success' => false, 'reason' => 'invalid authenticity token'], 'status' => 403]);
            },
            'xml' => function () {
                $this->render(['xml' => ['success' => false, 'reason' => 'invalid authenticity token'], 'root' => 'response', 'status' => 403]);
            }
        ]);
    }

    private function generate_csrf_token()
    {
        return bin2hex(random_bytes(32));
    }

    protected function set_security_headers()
    {
        $headers = $this->response()->headers();

        $headers->add('X-Frame-Options', 'SAMEORIGIN');
        $headers->add('X-Content-Type-Options', 'nosniff');
        $headers->add('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (str_starts_with(CONFIG()->url_base, 'https://')) {
            $headers->add('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $headers->add(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'self'"
        );
    }

    protected function filters()
    {
        return [
            'before' => [
                'set_current_user',
                'set_country',
                'set_locale',
                'set_title',
                'sanitize_params',
                'check_ip_ban',
                'check_tos_acceptance',
                'set_csrf_token',
                'verify_authenticity_token'
            ],
            'after' => [
                'init_cookies',
                'set_security_headers'
            ]
        ];
    }
}
