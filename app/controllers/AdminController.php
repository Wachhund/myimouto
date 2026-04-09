<?php
class AdminController extends ApplicationController
{
    protected function init()
    {
        $this->setLayout('admin');
    }
    
    protected function filters()
    {
        return [
            'before' => [
                'admin_only'
            ]
        ];
    }
    
    public function index()
    {
    }
    
    public function editUser()
    {
        if ($this->request()->isPost()) {
            $this->user = User::find_by_name($this->params()->user['name']);
            if (!$this->user) {
                $this->notice('User not found');
                $this->redirectTo('#edit_user');
                return;
            }
            $this->user->level = $this->params()->user['level'];
            
            if ($this->user->save()) {
                $this->notice('User updated');
                $this->redirectTo('#edit_user');
            } else {
                $this->render_error($this->user);
            }
        }
    }
    
    public function resetPassword()
    {
        if ($this->request()->isPost()) {
            $user = User::find_by_name($this->params()->user['name']);

            if ($user) {
                $reset_token = $user->reset_password();

                if ($user->email) {
                    try {
                        UserMailer::mail('password_reset', [$user, $reset_token])->deliver();
                        $this->notice('Password reset email sent to ' . $user->email);
                    } catch (\Throwable $e) {
                        Rails::log()->exception($e);
                        $this->notice('Password reset initiated but email delivery failed; check mail transport settings');
                    }
                } else {
                    $this->notice('Password reset initiated but user has no email address on file');
                }
            } else {
                $this->notice('That account does not exist');
                $this->redirectTo('#reset_password');
            }
        } else {
            $this->user = new User();
        }
    }
    
    public function cacheStats()
    {
        $keys = [];
        foreach([0, 20, 30, 35, 40, 50] as $level) {
            $keys[] = "stats/count/level=" . $level;
            
            foreach([0, 1, 2, 3, 4, 5] as $tag_count) {
                $keys[] = "stats/tags/level=" . $level . "&tags=" . $tag_count;
            }
            
            $keys[] = "stats/page/level=${level}&page=0-10";
            $keys[] = "stats/page/level=${level}&page=10-20";
            $keys[] = "stats/page/level=${level}&page=20+";
        }
        
        $h = [];
        foreach ($keys as $k) {
            $h[$k] = Rails::cache()->reach($k);
        }
        
        $this->post_stats = $h;
    }
    
    public function resetPostStats()
    {
        $keys = [];
        foreach([0, 20, 30, 35, 40, 50] as $level) {
            $keys[] = "stats/count/level=" . $level;
            
            foreach([0, 1, 2, 3, 4, 5] as $tag_count) {
                $keys[] = "stats/tags/level=" . $level . "&tags=" . $tag_count;
            }
            
            $keys[] = "stats/page/level=${level}&page=0-10";
            $keys[] = "stats/page/level=${level}&page=10-20";
            $keys[] = "stats/page/level=${level}&page=20+";
        }
        
        foreach ($keys as $key) {
            Rails::cache()->write($key, 0);
        }
        
        $this->redirectTo('#cache_stats');
    }
    
    public function recalculateTagCount()
    {
        Tag::recalculate_post_count();
        $this->notice('Tags count recalculated');
        $this->redirectTo('#index');
    }
    
    public function purgeTags()
    {
        Tag::purge_tags();
        $this->notice('Tags purged');
        $this->redirectTo('#index');
    }
}
