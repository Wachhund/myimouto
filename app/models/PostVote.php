<?php

class PostVote extends Rails\ActiveRecord\Base
{
    protected function associations()
    {
        return [
            'belongs_to' => [
                'post',
                'user',
            ],
        ];
    }
}
