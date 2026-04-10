<?php

class PoolHelper extends Rails\ActionView\Helper
{
    public function pool_list(Post $post)
    {
        $html = "";
        $pools = Pool::where("pools_posts.post_id = {$post->id}")->joins("JOIN pools_posts ON pools_posts.pool_id = pools.id")->order("pools.name")->select("pools.name, pools.id")->take();

        if ($pools->blank()) {
            $html .= "none";
        } else {
            $html .= join(", ", array_map(function ($p) {
                return $this->linkTo($this->h($p->pretty_name()), ["pool#show", 'id' => $p->id]);
            }, $pools->members()));
        }

        return $html;
    }

    public function link_to_pool_zip($text, $pool, $zip_params, $options = [])
    {
        $text = sprintf(
            "%s%s (%s)",
            $text,
            !empty($options['has_jpeg']) ? " PNGs" : "",
            $this->numberToHumanSize($pool->get_zip_size($zip_params)),
        );

        $options = [ 'action' => "zip", 'id' => $pool->id, 'filename' => $pool->get_zip_filename($zip_params) ];
        if (!empty($zip_params['jpeg'])) {
            $options['jpeg'] = 1;
        }
        return $this->linkTo($text, $options, ['onclick' => "if(!User.run_login_onclick(event)) return false; return true;", 'class' => 'pool_zip_download']);
    }

    public function generate_zip_list($pool_zip)
    {
        if (!$pool_zip->blank()) {
            return join('', array_map(function ($data) {
                return sprintf("%s %s %s %s\n", $data->crc32, $data->file_size, $data->path, $data->filename);
            }, $pool_zip->members()));
        }
    }
}
