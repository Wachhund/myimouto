<?php

class WikiPageVersion extends Rails\ActiveRecord\Base
{
    public function author()
    {
        return User::find_name($this->user_id);
    }

    public function diff($target)
    {
        $what = [];
        if ($target) {
            if ($this->body != $target->body) {
                $what[] = 'body';
            }
            if ($this->title != $target->title) {
                $what[] = 'title';
            }
            if ($this->is_locked != $target->is_locked) {
                $what[] = 'is_locked';
            }
        }
        if ($this->version == 1) {
            $what[] = 'initial';
        }
        return $what;
    }

    public function prev()
    {
        WikiPageVersion::where('wiki_page_id = ? AND version = ?', $this->wiki_page_id, $this->version - 1)->first();
    }

    public function pretty_title()
    {
        return str_replace('_', ' ', $this->title);
    }

    public function toXml(array $options = [])
    {
        $options = array_flip($options);
        $options['wiki_page_version'] = 'root';

        return toXml(['id' => $this->$this->id, 'created_at' => $this->$this->created_at, 'updated_at' => $this->updated_at, 'title' => $this->title, 'body' => $this->body, 'updater_id' => $this->user_id, 'locked' => $this->is_locked, 'version' => $this->version, 'post_id' => $this->post_id], $options);
    }

    public function asJson(array $args = [])
    {
        return ['id' => $this->id, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at, 'title' => $this->title, 'body' => $this->body, 'updater_id' => $this->user_id, 'locked' => $this->is_locked, 'version' => $this->version, 'post_id' => $this->post_id];
    }
}
