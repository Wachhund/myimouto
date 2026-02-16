<?php
class FavoriteController extends ApplicationController
{
    public function listUsers()
    {
        if ($this->favorite_list_users_rate_limited()) {
            $retry_after = $this->favorite_list_users_rate_limit_retry_after_seconds();
            $this->response()->headers()->add('Retry-After', (string)$retry_after);
            $this->respond_to_error('Rate limit exceeded', ['post#index'], ['status' => 429, 'api' => ['retry_after' => $retry_after]]);
            return;
        }

        $post_id = (int)$this->params()->id;
        if ($post_id <= 0) {
            $this->respond_to_error('Post not found', ['post#index'], ['status' => 404]);
            return;
        }

        $post = Post::where(['id' => $post_id])->first();
        if (!$post) {
            $this->respond_to_error('Post not found', ['post#index'], ['status' => 404]);
            return;
        }

        $favorited_users = $this->favorited_users_for_post($post);

        $this->respondTo([
            'json' => function () use ($favorited_users) {
                $this->render(['json' => ['favorited_users' => $favorited_users]]);
            }
        ]);
    }

    protected function favorited_users_for_post(Post $post)
    {
        $names = [];
        foreach ($post->favorited_by() as $user) {
            if (is_array($user) && isset($user['name'])) {
                $names[] = $user['name'];
            } elseif (is_object($user) && isset($user->name)) {
                $names[] = $user->name;
            }
        }
        $names = array_unique($names);
        return implode(',', $names);
    }

    protected function favorite_list_users_rate_limited()
    {
        $limit = $this->favorite_list_users_rate_limit();
        if ($limit <= 0) {
            return false;
        }

        $window_seconds = $this->favorite_list_users_rate_limit_window_seconds();
        $bucket = (int)floor(time() / $window_seconds);
        $cache_key = $this->favorite_list_users_rate_limit_cache_key($bucket);
        $hits = (int)Rails::cache()->read($cache_key);

        if ($hits >= $limit) {
            return true;
        }

        Rails::cache()->write($cache_key, $hits + 1, ['expires_in' => $window_seconds . ' seconds']);
        return false;
    }

    protected function favorite_list_users_rate_limit_retry_after_seconds()
    {
        $window_seconds = $this->favorite_list_users_rate_limit_window_seconds();
        $next_window = (((int)floor(time() / $window_seconds)) + 1) * $window_seconds;
        return max(1, $next_window - time());
    }

    protected function favorite_list_users_rate_limit_cache_key($bucket)
    {
        return 'rate-limit:favorite-list-users:' . $this->favorite_list_users_rate_limit_identity() . ':' . $bucket;
    }

    protected function favorite_list_users_rate_limit_identity()
    {
        if (!current_user()->is_anonymous()) {
            return 'user-' . current_user()->id;
        }

        return 'ip-' . $this->request()->remoteIp();
    }

    protected function favorite_list_users_rate_limit()
    {
        $configured = (int)CONFIG()->favorite_list_users_rate_limit;
        return $configured > 0 ? $configured : 60;
    }

    protected function favorite_list_users_rate_limit_window_seconds()
    {
        $configured = (int)CONFIG()->favorite_list_users_rate_limit_window_seconds;
        return $configured > 0 ? $configured : 60;
    }
}
