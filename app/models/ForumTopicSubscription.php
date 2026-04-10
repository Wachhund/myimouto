<?php

class ForumTopicSubscription extends Rails\ActiveRecord\Base
{
    protected function associations()
    {
        return [
            'belongs_to' => [
                'user',
                'forum_topic' => ['class_name' => 'ForumPost', 'foreign_key' => 'forum_topic_id'],
            ],
        ];
    }

    /**
     * Subscribe a user to a forum topic. Creates the subscription if it doesn't exist,
     * otherwise does nothing (idempotent).
     */
    public static function subscribe($user_id, $topic_id)
    {
        if (self::is_subscribed($user_id, $topic_id)) {
            return true;
        }

        $sub = new self();
        $sub->user_id = (int) $user_id;
        $sub->forum_topic_id = (int) $topic_id;
        $sub->created_at = date('Y-m-d H:i:s');
        return $sub->save();
    }

    /**
     * Remove a user's subscription to a forum topic.
     */
    public static function unsubscribe($user_id, $topic_id)
    {
        $sub = self::where('user_id = ? AND forum_topic_id = ?', (int) $user_id, (int) $topic_id)->first();
        if ($sub) {
            $sub->destroy();
            return true;
        }
        return false;
    }

    /**
     * Check whether a user is subscribed to a forum topic.
     */
    public static function is_subscribed($user_id, $topic_id)
    {
        return self::where('user_id = ? AND forum_topic_id = ?', (int) $user_id, (int) $topic_id)->exists();
    }

    /**
     * Update the last_read_at timestamp for a subscription.
     */
    public static function mark_read($user_id, $topic_id)
    {
        $sub = self::where('user_id = ? AND forum_topic_id = ?', (int) $user_id, (int) $topic_id)->first();
        if ($sub) {
            $sub->updateAttribute('last_read_at', date('Y-m-d H:i:s'));
            return true;
        }
        return false;
    }
}
