<?php

class TagAlias extends Rails\ActiveRecord\Base
{
    public static function tableName()
    {
        return 'tag_aliases';
    }

    protected $alias_name;

    protected function callbacks()
    {
        return [
            'before_create' => [
                'normalize',
                'validate_uniqueness',
                'validate_tag',
                'validate_alias',
            ],
            'after_destroy' => [
                'expire_tag_cache_after_deletion',
            ],
        ];
    }

    public static function to_aliased($tags)
    {
        !is_array($tags) && $tags = [$tags];
        $aliased_tags = [];
        foreach ($tags as $tag_name) {
            $aliased_tags[] = self::to_aliased_helper($tag_name);
        }

        return $aliased_tags;
    }

    public static function to_aliased_helper($tag_name)
    {
        # TODO: add memcached support
        $tag = self::where("tag_aliases.name = ? AND tag_aliases.is_pending = FALSE", $tag_name)
                    ->select("tags.name AS name")
                    ->joins("JOIN tags ON tags.id = tag_aliases.alias_id")
                    ->first();
        return $tag ? $tag->name : $tag_name;
    }

    # Strips out any illegal characters and makes sure the name is lowercase.
    public function normalize()
    {
        $this->name = trim(str_replace(' ', '_', strtolower($this->name)), '-~');
    }

    # Makes sure the alias does not conflict with any other aliases.
    public function validate_uniqueness()
    {
        if (self::where("name = ?", $this->name)->exists()) {
            $this->errors()->addToBase("{$this->name} is already aliased to something");
            return false;
        }

        if (self::where("alias_id = (select id from tags where name = ?)", $this->name)->exists()) {
            $this->errors()->addToBase($this->name . " is already aliased to something");
            return false;
        }

        if (self::where("name = ?", $this->alias_name())->exists()) {
            $this->errors()->addToBase($this->alias_name() . " is already aliased to something");
            return false;
        }
    }

    protected function validate_tag()
    {
        if (!Tag::where(['name' => $this->name])->first()) {
            $this->errors()->add('tag', "couldn't be created");
            return false;
        }
    }

    protected function validate_alias()
    {
        if (!$this->alias_id) {
            $this->errors()->add('alias', "couldn't be created");
            return false;
        }
    }

    public function setAlias($name)
    {
        $tag = Tag::find_or_create_by_name($this->name);
        $alias_tag = Tag::find_or_create_by_name($name);

        if ($tag->tag_type != $alias_tag->tag_type) {
            $tag->updateAttribute('tag_type', $alias_tag->tag_type);
        }

        $this->alias_id = $alias_tag->id;
    }

    public function alias_name()
    {
        if ($this->alias_name === null) {
            $this->alias_name = Tag::find($this->alias_id)->name;
        }
        return $this->alias_name;
    }

    public function alias_tag()
    {
        return Tag::find_or_create_by_name($this->name);
    }

    # Destroys the alias and sends a message to the alias's creator.
    #TODO:
    public function destroy_and_notify($current_user, $reason)
    {
        if ($this->creator_id && $this->creator_id != $current_user->id) {
            $msg = "A tag alias you submitted (" . $this->name . " &rarr; " . $this->alias_name() . ") was deleted for the following reason: " . $reason;
            Dmail::create(['from_id' => current_user()->id, 'to_id' => $this->creator_id, 'title' => "One of your tag aliases was deleted", 'body' => $msg]);
        }

        $this->destroy();
    }

    public function approve($user_id, $ip_addr)
    {
        self::connection()->executeSql("UPDATE tag_aliases SET is_pending = FALSE WHERE id = ?", $this->id);

        Post::select('posts.*')
                ->joins('JOIN posts_tags pt ON posts.id = pt.post_id JOIN tags ON pt.tag_id = tags.id')
                ->where("tags.name LIKE ?", $this->name)
                ->take()->each(function ($post) use ($user_id, $ip_addr) {
                    $post->reload();
                    $post->updateAttributes(['tags' => $post->cached_tags, 'updater_user_id' => $user_id, 'updater_ip_addr' => $ip_addr]);
                });

        Moebooru\CacheHelper::expire_tag_version();
    }

    public function expire_tag_cache_after_deletion()
    {
        Moebooru\CacheHelper::expire_tag_version();
    }
}
