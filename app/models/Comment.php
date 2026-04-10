<?php

// require 'translate'
# FIXME: god, why I need this. Anyway, the required helper functions should be
#        moved to library instead. It's not really "view" helper anymore.
// include ApplicationHelper

class Comment extends Rails\ActiveRecord\Base
{
    public static function generate_sql(array $params)
    {
        $query = self::none();

        if (isset($params['post_id'])) {
            $query->where('post_id = ?', $params['post_id']);
            // unset($params['post_id']);
        }

        return $query;
        // return array_merge($params, $query);
    }

    public static function updated(User $user)
    {
        if (!$user->is_anonymous()) {
            $query = Comment::where("user_id <> ?", $user->id);
        } else {
            $query = Comment::none();
        }

        if (!$newest_comment = $query->order("id desc")->limit(1)->select("created_at")->first()) {
            return false;
        }

        !$user->last_comment_read_at && $user->last_comment_read_at = '0000-00-00 00:00:00';
        return $newest_comment->created_at > $user->last_comment_read_at;
    }

    public function update_last_commented_at()
    {
        # return if self.do_not_bump_post

        $comment_count = self::connection()->selectValue("SELECT COUNT(*) FROM comments WHERE post_id = ?", $this->post_id);
        if ($comment_count <= CONFIG()->comment_threshold) {
            self::connection()->executeSql(["UPDATE posts SET last_commented_at = (SELECT created_at FROM comments WHERE post_id = :post_id ORDER BY created_at DESC LIMIT 1) WHERE posts.id = :post_id", 'post_id' => $this->post_id]);
        }
    }

    public function get_formatted_body()
    {
        return $this->format_inlines($this->format_text($this->body, ['mode' => 'comment']), $this->id);
    }

    public function update_fragments()
    {
        return;
    }

    # Get the comment translated into the requested language.    Languages in source_langs
    # will be left untranslated.
    public function get_translated_formatted_body_uncached($target_lang, $source_langs)
    {
        return $this->get_formatted_body();
        // return $this->get_formatted_body, array();
    }

    public function get_translated_formatted_body($target_lang, array $source_langs)
    {
        $source_lang_list = implode(',', $source_langs);
        $key = "comment:" . $this->id . ":" . strtotime($this->updated_at) . ":" . $target_lang . ":" . $source_lang_list;

        return Rails::cache()->fetch($key, function () use ($target_lang, $source_langs) {
            return $this->get_translated_formatted_body_uncached($target_lang, $source_langs);
        });
    }

    public function author()
    {
        return $this->user->name;
    }

    public function pretty_author()
    {
        return str_replace("_", " ", $this->author());
    }

    public function author2()
    {
        return $this->user->name;
    }

    public function pretty_author2()
    {
        return str_replace("_", " ", $this->author2());
    }

    public function api_attributes()
    {
        return [
            'id'            => $this->id,
            'created_at'    => $this->created_at,
            'post_id'       => $this->post_id,
            'creator'       => $this->author(),
            'creator_id'    => $this->user_id,
            'body'          => $this->body,
        ];
    }

    public function toXml(array $options = [])
    {
        return parent::toXml(array_merge($options, ['attributes' => $this->api_attributes()]));
    }

    public function asJson($args = [])
    {
        return $this->api_attributes();
    }

    protected function validations()
    {
        return [
            'body' => [
                'format' => ['with' => '/\S/', 'message' => 'has no content'],
            ],
            'validate_utf8_body',
        ];
    }

    protected function validate_utf8_body()
    {
        if ($this->body === null || $this->body === '') {
            return;
        }

        if (!$this->is_valid_utf8($this->body)) {
            $this->errors()->add('body', 'contains invalid UTF-8');
        }
    }

    protected function is_valid_utf8($value)
    {
        if (!is_string($value)) {
            if (is_int($value) || is_float($value)) {
                $value = (string) $value;
            } else {
                return false;
            }
        }

        if (function_exists('mb_check_encoding')) {
            return mb_check_encoding($value, 'UTF-8');
        }

        return preg_match('//u', $value) === 1;
    }

    protected function associations()
    {
        return [
            'belongs_to' => [
                'post',
                'user',
            ],
        ];
    }

    protected function callbacks()
    {
        return [
            'after_save' => ['update_last_commented_at', 'update_fragments'],
            'after_destroy' => ['update_last_commented_at'],
        ];
    }
}
