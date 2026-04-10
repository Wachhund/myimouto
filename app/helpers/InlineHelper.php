<?php

class InlineHelper extends Rails\ActionView\Helper
{
    public function inline_image_tag($image, array $options = [], array $tag_options = [])
    {
        if (!empty($options['use_sample']) && $image->has_sample()) {
            $url = $image->sample_url();
            $tag_options['width'] = $image->sample_width;
            $tag_options['height'] = $image->sample_height;
        } else {
            $url = $image->file_url();
            $tag_options['width'] = $image->width;
            $tag_options['height'] = $image->height;
        }

        return $this->imageTag($url, $tag_options);
    }
}
