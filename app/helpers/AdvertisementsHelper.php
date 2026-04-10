<?php

class AdvertisementsHelper extends Rails\ActionView\Helper
{
    public function print_advertisement($ad_type, $position = null, $center = false)
    {
        $ad = Advertisement::random($ad_type, substr($position, 0, 1));

        if ($ad) {
            if ($ad->html) {
                $contents = $ad->html;
            } else {
                $contents = $this->linkTo(
                    $this->imageTag(
                        $ad->image_url,
                        ['alt' => "Advertisement", 'width' => $ad->width, 'height' => $ad->height],
                    ),
                    $this->redirectAdvertisementPath($ad),
                    ['target' => '_blank'],
                );
            }

            if ($center) {
                return $this->contentTag('div', $contents, ['style' => 'margin:0 auto;width:' . $ad->width . 'px;height:' . $ad->height . 'px;']);
            } else {
                return $contents;
            }
        }
    }
}
