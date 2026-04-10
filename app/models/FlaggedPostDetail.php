<?php

class FlaggedPostDetail extends Rails\ActiveRecord\Base
{
    # If this is set, the user who owns this record won't be included in the API.
    public $hide_user;

    protected function associations()
    {
        return [
            'belongs_to' => [
                'post',
                'user',
            ],
        ];
    }

    public function author()
    {
        return $this->flagged_by();
    }

    public static function new_deleted_posts($user)
    {
        if ($user->is_anonymous()) {
            return 0;
        }

        return Rails::cache()->fetch(
            'deleted_posts:' . $user->id . ':' . $user->last_deleted_post_seen_at,
            ['expires_in' => '1 minute'],
            function () use ($user) {
                return self::connection()->selectValue(
                    "SELECT COUNT(*) FROM flagged_post_details fpd JOIN posts p ON (p.id = fpd.post_id) " .
                    "WHERE p.status = 'deleted' AND p.user_id = ? AND fpd.user_id <> ? AND fpd.created_at > ?",
                    $user->id,
                    $user->id,
                    $user->last_deleted_post_seen_at,
                );
            },
        );
    }

    # XXX: author and flagged_by are redundant
    public function flagged_by()
    {
        if (!$this->user_id) {
            return "system";
        } else {
            return $this->user->name;
        }
    }

    public function resolve($resolver_id)
    {
        $this->updateAttributes(['is_resolved' => true, 'resolved_by' => $resolver_id]);
    }

    /**
     * Count total flags created by a user.
     */
    public static function count_by_user($user_id)
    {
        return (int) self::where('user_id = ?', $user_id)->count();
    }

    public static function can_flag_again($user_id, $post_id)
    {
        $count = self::connection()->selectValue(
            "SELECT COUNT(*) FROM flagged_post_details WHERE user_id = ? AND post_id = ? AND created_at > ?",
            $user_id,
            $post_id,
            date('Y-m-d H:i:s', strtotime('-24 hours')),
        );
        return (int) $count === 0;
    }

    public function api_attributes()
    {
        $ret = [
            'post_id'    => $this->post_id,
            'reason'     => $this->reason,
            'created_at' => $this->created_at,
        ];

        if ($this->reason_category) {
            $ret['reason_category'] = $this->reason_category;
        }

        if ($this->parent_post_id) {
            $ret['parent_post_id'] = $this->parent_post_id;
        }

        if (!$this->hide_user) {
            $ret['user_id']    = $this->user_id;
            $ret['flagged_by'] = $this->flagged_by();
        }

        if ($this->resolved_by) {
            $ret['resolved_by'] = $this->resolved_by;
        }

        return $ret;
    }

    // public function asJson()
    // {(*args)
    // return; api_attributes.asJson(*args)
    // }

    // public function to_xml()
    // {(options = array())
    // return; api_attributes.to_xml(options.reverse_merge('root' => "flagged_post_detail"))
    // }
}
