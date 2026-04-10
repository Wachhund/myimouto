<?php

class ArtistUrl extends Rails\ActiveRecord\Base
{
    public static function tableName()
    {
        return 'artists_urls';
    }

    protected function callbacks()
    {
        return [
            'before_save' => ['normalize_url'],
        ];
    }

    protected function validations()
    {
        return [
            'url' => [
                'presence' => true,
            ],
        ];
    }

    public static function normalize($url)
    {
        if ($url) {
            $url = preg_replace(
                ['/^http:\/\/blog\d+\.fc2/', '/^http:\/\/blog-imgs-\d+\.fc2/', '/^http:\/\/img\d+\.pixiv\.net/'],
                ["http://blog.fc2", "http://blog.fc2", "http://img.pixiv.net"],
                $url,
            );
            return $url;
        }
    }

    public static function normalize_for_search($url)
    {
        if (preg_match('/\.\w+$/', $url) && preg_match('/\w\/\w/', $url)) {
            $url = dirname($url);
        }

        $url = preg_replace(
            ['/^http:\/\/blog\d+\.fc2/', '/^http:\/\/blog-imgs-\d+\.fc2/', '/^http:\/\/img\d+\.pixiv\.net/'],
            ["http://blog*.fc2", "http://blog*.fc2", "http://img*.pixiv.net"],
            $url,
        );
    }

    public function normalize_url()
    {
        $this->normalized_url = self::normalize($this->url);
    }
}
