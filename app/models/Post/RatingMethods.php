<?php

trait PostRatingMethods
{
    public $old_rating;

    protected function setRating($r)
    {
        if (!$r && !$this->isNewRecord()) {
            return;
        }

        if ($this->is_rating_locked) {
            return;
        }

        $r = strtolower(substr($r, 0, 1));

        if (in_array($r, ['q', 'e', 's'])) {
            $new_rating = $r;
        } else {
            $new_rating = CONFIG()->default_rating_upload ?: 'q';
        }

        # Moved the order of the next line because
        # it would just return on new posts, without
        # setting the rating.
        $this->setAttribute('rating', $new_rating);

        if ($r == $new_rating) {
            return;
        }

        $this->old_rating = $r;
        $this->touch_change_seq();
    }

    public function pretty_rating()
    {
        if ($this->rating == 'e') {
            return 'Explicit';
        } elseif ($this->rating == 'q') {
            return 'Questionable';
        } elseif ($this->rating == 's') {
            return 'Safe';
        }
    }

    public function can_change_is_note_locked(User $user)
    {
        // return $user->has_permission(pool)
    }

    public function can_change_rating_locked(User $user)
    {
        // return $this->user->has_permission(pool)
    }

    public function can_change_rating(User $user)
    {
        return $user->is_member_or_higher() && (!$this->is_rating_locked() || $user->has_permission($this));
    }
}
