<?php

trait PostCommentMethods
{
    public function recent_comments()
    {
        $recent = new Rails\ActiveRecord\Collection();
        # reverse_order to fetch last 6 comments
        # reversed in the last to return from lowest id
        if ($this->comments) {
            $recent->merge(array_slice($this->comments->members(), -6));
        }
        return $recent;
    }
}
