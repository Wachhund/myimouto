<?php

trait PostVoteMethods
{
    public $voted_by = [];

    public static function static_recalculate_score($id = null)
    {
        $conds = $cond_params = [];

        $sql = "UPDATE posts AS p SET score = " .
            "(SELECT COALESCE(SUM(GREATEST(?, LEAST(?, score))), 0) FROM post_votes v WHERE v.post_id = p.id) ";
        $cond_params[] = CONFIG()->vote_sum_min;
        $cond_params[] = CONFIG()->vote_sum_max;

        if ($id) {
            $conds[] = "WHERE p.id = ?";
            $cond_params[] = $id;
        }

        $sql = implode(" ", [$sql, $conds]);
        array_unshift($cond_params, $sql);
        self::connection()->executeSql($cond_params);
    }

    public function recalculate_score()
    {
        $this->save();
        self::connection()->executeSql(['UPDATE posts SET score = (SELECT COUNT(*) FROM post_votes WHERE post_id = :post_id AND score > 0) WHERE id = :post_id', 'post_id' => $this->id]);
        $this->reload();
    }

    public function vote($score, $user, array $options = [])
    {
        $score < CONFIG()->vote_record_min && $score = CONFIG()->vote_record_min;
        $score > CONFIG()->vote_record_max && $score = CONFIG()->vote_record_max;

        if ($user->is_anonymous()) {
            return false;
        }

        $vote = PostVote::where(['user_id' => $user->id, 'post_id' => $this->id])->first();

        if (!$vote) {
            $vote = PostVote::create(['post_id' => $this->id, 'user_id' => $user->id, 'score' => $score]);
        }

        $vote->updateAttributes(['score' => $score]);

        $this->recalculate_score();

        return true;
    }
}
