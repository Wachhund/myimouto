<?php
class ForumPostVote extends Rails\ActiveRecord\Base
{
    protected function associations()
    {
        return [
            'belongs_to' => [
                'user',
                'forum_post' => ['class_name' => 'ForumPost']
            ]
        ];
    }

    /**
     * Cast or update a vote on a forum post.
     *
     * @param int $user_id
     * @param int $post_id
     * @param int $score  -1, 0, or 1
     * @return bool
     */
    static public function vote($user_id, $post_id, $score)
    {
        $score = max(-1, min(1, (int)$score));

        $existing = self::where('user_id = ? AND forum_post_id = ?', (int)$user_id, (int)$post_id)->first();

        if ($existing) {
            $existing->updateAttributes([
                'score'      => $score,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            return true;
        }

        $vote = new self();
        $vote->user_id = (int)$user_id;
        $vote->forum_post_id = (int)$post_id;
        $vote->score = $score;
        $vote->created_at = date('Y-m-d H:i:s');
        return $vote->save();
    }

    /**
     * Remove a user's vote on a forum post.
     */
    static public function unvote($user_id, $post_id)
    {
        $vote = self::where('user_id = ? AND forum_post_id = ?', (int)$user_id, (int)$post_id)->first();
        if ($vote) {
            $vote->destroy();
            return true;
        }
        return false;
    }

    /**
     * Get the current vote score for a user on a post, or null if not voted.
     */
    static public function user_vote($user_id, $post_id)
    {
        $vote = self::where('user_id = ? AND forum_post_id = ?', (int)$user_id, (int)$post_id)
            ->select('score')
            ->first();
        return $vote ? (int)$vote->score : null;
    }

    /**
     * Get the total score (SUM) for a forum post.
     */
    static public function post_score($post_id)
    {
        $result = self::connection()->selectValue(
            "SELECT COALESCE(SUM(score), 0) FROM forum_post_votes WHERE forum_post_id = ?",
            (int)$post_id
        );
        return (int)$result;
    }
}
