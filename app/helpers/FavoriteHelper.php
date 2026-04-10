<?php

class FavoriteHelper extends Rails\ActionView\Helper
{
    public function favorite_list(Post $post)
    {
        if (!$users = $post->favorited_by()) {
            return "no one";
        }

        $html = [];

        foreach (range(0, 5) as $i) {
            if (!isset($users[$i])) {
                break;
            }
            $html[] = '<a href="/user/show/' . array_shift($users[$i]) . '">' . array_shift($users[$i]) . '</a>';
        }

        $output = implode(', ', $html);
        $html = [];

        if (count($users) > 6) {
            foreach (range(6, count($users) - 1) as $i) {
                $html[] = '<a href="/user/show/' . $users[$i]['id'] . '">' . $users[$i]['name'] . '</a>';
            }
            $html = '<span id="remaining-favs" style="display: none;">, ' . implode(', ', $html) . '</span>';
            $output .= $html . ' <span id="remaining-favs-link">(<a href="#" onclick="$(\'remaining-favs\').show(); $(\'remaining-favs-link\').hide(); return false;">' . (count($users) - 6) . ' more</a>)</span>';
        }
        return $output;
    }
}
