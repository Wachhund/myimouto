<?php
class PostSetPost extends Rails\ActiveRecord\Base
{
    protected function associations()
    {
        return [
            'belongs_to' => [
                'post_set' => ['class_name' => 'PostSet'],
                'post'
            ]
        ];
    }
}

