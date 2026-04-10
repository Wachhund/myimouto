<?php

class PostTagHistory extends Rails\ActiveRecord\Base
{
    public static function undo_user_changes($user_id)
    {
        $posts = Post::joins("join post_tag_histories pth on pth.post_id = posts.id")
                       ->select("distinct posts.id")
                       ->where("pth.user_id = ?", $user_id)
                       ->take();
        // puts posts.size
        // p posts.map {|x| x.id}
        #        destroy_all(["user_id = ?", user_id])
        #        posts.each do |post|
        #            post.tags = post.tag_history.first.tags
        #            post.updater_user_id = post.tag_history.first.user_id
        #            post.updater_ip_addr = post.tag_history.first.ip_addr
        #            post.save!
        #        end
    }

    public static function generate_sql($options = [])
    {
        $sql = self::none();
        if (!empty($options['post_id'])) {
            $sql->where("post_tag_histories.post_id = ?", $options['post_id']);
        }

        if (!empty($options['user_id'])) {
            $sql->where("post_tag_histories.user_id = ?", $options['user_id']);
        }

        if (!empty($options['user_name'])) {
            $sql->joins("users ON users.id = post_tag_histories.user_id");
            $sql->where("users.name = ?", $options['user_name']);
        }

        return $sql;
    }

    public static function undo_changes_by_user($user_id)
    {
        $this->transaction(function () {
            $posts = Post::joins("join post_tag_histories pth on pth.post_id = posts.id")
                           ->select("distinct posts.*")
                           ->where("pth.user_id = ?", $user_id)
                           ->take();
            PostTagHistory::destroyAll("user_id = ?", $user_id);
            foreach ($posts as $post) {
                if ($post->tag_history->any()) {
                    $first = $post->tag_history[0];
                    $post->tags = $first->tags;
                    $post->updater_ip_addr = $first->ip_addr;
                    $post->updater_user_id = $first->user_id;
                    $post->save();
                }
            }
        });
    }

    # The contents of options['posts'] must be saved by the caller.    This allows
    # undoing many tag changes across many posts; all †changes to a particular
    # post will be condensed into one change.
    public function undo(array $options = [])
    {
        # TODO: refactor. modifying parameters is a bad habit.
        if (!isset($options['posts'])) {
            $options['posts'] = [];
        }
        if (!isset($options['posts'][$this->post_id])) {
            $options['post'] = $options['posts'][$this->post_id] = Post::find($this->post_id);
        }
        $post = $options['posts'][$this->post_id];

        $current_tags = $post->tags();

        $prev = $this->previous();
        if (!$prev) {
            return;
        }

        $changes = $this->tag_changes($prev);

        $new_tags = array_merge(array_diff($current_tags, $changes['added_tags']), $changes['removed_tags']);
        if (!isset($options['update_options'])) {
            $options['update_options'] = [];
        }
        $post->assignAttributes(array_merge(['tags' => implode(" ", $new_tags)], $options['update_options']));
    }

    public function author()
    {
        return $this->user->name;
    }

    public function tag_changes($prev)
    {
        $new_tags = explode(' ', $this->tags);
        $old_tags = explode(' ', $prev->tags);
        $latest = Post::find($this->post_id)->cached_tags/*_versioned*/;
        $latest_tags = explode(' ', $latest);

        return [
            'added_tags'            => array_diff($new_tags, $old_tags),
            'removed_tags'          => array_diff($old_tags, $new_tags),
            'unchanged_tags'        => array_intersect($array_new_tags, $old_tags),
            'obsolete_added_tags'   => array_diff(array_diff($new_tags, $old_tags), $latest_tags),
            'obsolete_removed_tags' => array_intersect(array_diff($old_tags, $new_tags), $latest_tags),
        ];
    }

    public function next()
    {
        return PostTagHistory::where("post_id = ? AND id > ?", $this->post_id, $this->id)
                               ->order("id ASC")
                               ->first();
    }

    public function previous()
    {
        return PostTagHistory::where("post_id = ? AND id < ?", $this->post_id, $this->id)
                               ->order("id ASC")
                               ->first();
    }

    public function toXml(array $options = [])
    {
        return parent::toXml(['attributes' => [
            'id'      => $this->id,
            'post_id' => $this->post_id,
            'tags'    => $this->tags,
        ], 'root' => "tag_history"]);
    }

    public function asJson()
    {
        return [
            'id'      => $this->id,
            'post_id' => $this->post_id,
            'tags'    => $this->tags,
        ];
    }

    protected function associations()
    {
        return [
            'belongs_to' => [
                'user',
                'post',
            ],
        ];
    }
}
