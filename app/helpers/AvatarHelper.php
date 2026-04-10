<?php

class AvatarHelper extends Rails\ActionView\Helper
{
    protected $avatar_posts_registry = [];

    # id is an identifier for the object referencing this avatar; it's passed down
    # to the javascripts to implement blacklisting "click again to open".
    public function avatar(User $user, $id, array $html_options = [])
    {
        static $shown_avatars = [];
        static $posts_to_send = [];

        #if not @shown_avatars[user] then
        $shown_avatars[$user->id] = true;
        $posts_to_send[] = $user->avatar_post;
        $this->avatar_posts_registry[] = $user->avatar_post;
        $img = $this->imageTag(
            $user->avatar_url() . "?" . strtotime($user->avatar_timestamp),
            array_merge(['class' => "avatar", 'width' => $user->avatar_width, 'height' => $user->avatar_height, 'loading' => 'lazy'], $html_options),
        );
        return $this->linkTo(
            $img,
            ["post#show", 'id' => $user->avatar_post->id],
            ['class' => "ca" . $user->avatar_post->id,
                'onclick' => "return Post.check_avatar_blacklist(" . $user->avatar_post->id . ", " . $id . ")"],
        );
        #end
    }

    public function avatar_init(Post $post = null)
    {
        if (!$this->avatar_posts_registry) {
            return '';
        }

        $ret = '';
        foreach ($this->avatar_posts_registry as $post) {
            $ret .= 'Post.register(' . $post->toJson() . ");\n";
        }
        $ret .= "Post.init_blacklisted();\n";

        return $ret;
    }
}
