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

    /**
     * Bulk-load vote scores for multiple forum posts.
     *
     * @param int[] $post_ids
     * @return array<int, int>  post_id => total score
     */
    static public function bulk_post_scores(array $post_ids)
    {
        $scores = [];
        if (empty($post_ids)) {
            return $scores;
        }

        $placeholders = implode(',', array_fill(0, count($post_ids), '?'));
        $rows = self::connection()->select(
            "SELECT forum_post_id, COALESCE(SUM(score), 0) AS total_score FROM forum_post_votes WHERE forum_post_id IN ($placeholders) GROUP BY forum_post_id",
            ...$post_ids
        );
        foreach ($rows as $row) {
            $scores[(int)$row->forum_post_id] = (int)$row->total_score;
        }
        return $scores;
    }

    /**
     * Bulk-load a user's votes for multiple forum posts.
     *
     * @param int   $user_id
     * @param int[] $post_ids
     * @return array<int, int>  post_id => score
     */
    static public function bulk_user_votes($user_id, array $post_ids)
    {
        $votes = [];
        if (empty($post_ids)) {
            return $votes;
        }

        $placeholders = implode(',', array_fill(0, count($post_ids), '?'));
        $rows = self::connection()->select(
            "SELECT forum_post_id, score FROM forum_post_votes WHERE forum_post_id IN ($placeholders) AND user_id = ?",
            ...[...$post_ids, (int)$user_id]
        );
        foreach ($rows as $row) {
            $votes[(int)$row->forum_post_id] = (int)$row->score;
        }
        return $votes;
    }
}
