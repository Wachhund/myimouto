<?php
class FavoriteController extends ApplicationController
{
    public function listUsers()
    {
        $post = Post::find($this->params()->id);
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
            }
        }
        $names = array_unique($names);
        return implode(',', $names);
    }
}
