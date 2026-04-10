<?php

trait PostParentMethods
{
    protected $parent;

    public function update_has_children($post_id)
    {
        if (!$post_id) {
            // vpe($post_id);
            return;
        }

        $has_children = Post::where('parent_id = ? AND status <> "deleted"', $post_id)->exists();
        // vpe($has_children);
        self::connection()->executeSql("UPDATE posts SET has_children = ? WHERE id = ?", $has_children, $post_id);
    }

    public function recalculate_has_children()
    {
        self::connection()->executeSql("UPDATE posts SET has_children = false WHERE has_children = true");
        self::connection()->executeSql("UPDATE posts SET has_children = true WHERE id IN (SELECT parent_id FROM posts WHERE parent_id IS NOT NULL AND status <> 'deleted')");
    }

    # $side_updates_only will save a query and let the parent be updated via save()
    # Option created for commit_tags()
    public function set_parent($post_id, $parent_id, $old_parent_id = null, $side_updates_only = false)
    {
        if (!CONFIG()->enable_parent_posts) {
            return;
        }

        if ($old_parent_id === null) {
            $old_parent_id = self::connection()->selectValue("SELECT parent_id FROM posts WHERE id = ?", $post_id);
        }

        if ($parent_id == $post_id || $parent_id == 0) {
            $parent_id = null;
        }

        if (!$side_updates_only) {
            self::connection()->executeSql("UPDATE posts SET parent_id = ? WHERE id = ?", $parent_id, $post_id);
        }

        $this->update_has_children($old_parent_id);
        $this->update_has_children($parent_id);
    }

    public function validate_parent()
    {
        if ($this->parent_id or !Post::exists($parent_id)) {
            $this->errors()->add('parent_id', '');
        }
    }

    protected function update_parent()
    {
        if (!$this->parentIdChanged() && !$this->statusChanged()) {
            return;
        }
        $this->set_parent($this->id, $this->parent_id, $this->parentIdWas());
    }

    public function give_favorites_to_parent()
    {
        if (!$this->parent_id) {
            return;
        }

        $parent = Post::find($this->parent_id);

        foreach (PostVote::where('post_id = ?', $this->id)->take() as $vote) {
            $parent->vote($vote->score, $vote->user);
            $this->vote(0, $vote->user);
        }
    }

    public function get_parent()
    {
        if (isset($this->parent)) {
            return $this->parent;
        }

        $this->parent = Post::where(['id' => $this->parent_id])->first();
        return $this->parent;
    }
}
