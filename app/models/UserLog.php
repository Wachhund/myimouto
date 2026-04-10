<?php

class UserLog extends Rails\ActiveRecord\Base
{
    protected function associations()
    {
        return [
            'belongs_to' => [
                'user',
            ],
        ];
    }
}
