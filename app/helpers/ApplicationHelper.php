<?php

class ApplicationHelper extends Rails\ActionView\Helper
{
    private $_top_menu_items = [];

    public function html_title()
    {
        $base_title = CONFIG()->app_name;
        if ($this->contentFor('title')) {
            return $this->content('title') . " | " . $base_title;
        } else {
            return $base_title;
        }
    }

    public function tag_header($tags = null)
    {
        if (!$tags) {
            return;
        }
        $tags = array_filter(explode(' ', $tags));
        foreach ($tags as $k => $tag) {
            $tags[$k] = $this->linkTo(str_replace('_', ' ', $tag), ['/post', 'tags' => $tag]);
        }
        return '/' . implode('+', $tags);
    }

    # Return true if the user can access the given level, or if creating an
    # account would.  This is only actually used for actions that require
    # privileged or higher; it's assumed that starting_level is no lower
    # than member.
    public function can_access($level)
    {
        $needed_level = User::get_user_level($level);
        $starting_level = CONFIG()->starting_level;
        $user_level = current_user()->level;
        if ($user_level >= $needed_level || $starting_level >= $needed_level) {
            return true;
        }
        return false;
    }

    # Return true if the starting level is high enough to execute
    # this action.    This is used by User.js.
    public function need_signup($level)
    {
        $needed_level = User::get_user_level($level);
        $starting_level = CONFIG()->starting_level;
        return $starting_level >= $needed_level;
    }

    public function get_help_action_for_controller($controller)
    {
        $singular = ["forum", "wiki"];
        $help_action = $controller;
        if (in_array($help_action, $singular)) {
            return $help_action;
        } else {
            return $help_action . 's';
        }
    }

    public function navigation_links($post)
    {
        $html = [];

        if ($post instanceof Post) {
            $html[] = $this->tag("link", ['rel' => "prev", 'title' => "Previous Post", 'href' => $this->urlFor(['post#show', 'id' => $post->id - 1])]);
            $html[] = $this->tag("link", ['rel' => "next", 'title' => "Next Post", 'href' => $this->urlFor(['post#show', 'id' => $post->id + 1])]);
        } elseif ($post instanceof Rails\ActiveRecord\Collection) {
            $posts = $post;

            $url_for = $this->request()->controller() . '#' . $this->request()->action();

            if ($posts->previousPage()) {
                $html[] = $this->tag("link", ['href' => $this->urlFor(array_merge([$url_for], $this->params()->merge(['page' => 1]))), 'rel' => "first", 'title' => "First Page"]);
                $html[] = $this->tag("link", ['href' => $this->urlFor(array_merge([$url_for], $this->params()->merge(['page' => $posts->previousPage()]))), 'rel' => "prev", 'title' => "Previous Page"]);
            }

            if ($posts->nextPage()) {
                $html[] = $this->tag("link", ['href' => $this->urlFor(array_merge([$url_for], $this->params()->merge(['page' => $posts->nextPage()]))), 'rel' => "next", 'title' => "Next Page"]);
                $html[] = $this->tag("link", ['href' => $this->urlFor(array_merge([$url_for], $this->params()->merge(['page' => $posts->totalPages()]))), 'rel' => "last", 'title' => "Last Page"]);
            }
        }

        return implode("\n", $html);
    }

    public function format_text($text, array $options = [])
    {
        return DText::parse($text);
    }

    public function format_inline($inline, $num, $id, $preview_html = null)
    {
        if (!$inline->inline_images) {
            return "";
        }

        if ($inline->inline_images->any()) {
            $url = $inline->inline_images[0]->preview_url();
        } else {
            $url = '';
        }

        if (!$preview_html) {
            $preview_html = '<img src="' . $url . '">';
        }

        $id_text = "inline-$id-$num";
        $block = '
            <div class="inline-image" id="' . $id_text . '">
                <div class="inline-thumb" style="display: inline;">
                ' . $preview_html . '
                </div>
                <div class="expanded-image" style="display: none;">
                    <div class="expanded-image-ui"></div>
                    <span class="main-inline-image"></span>
                </div>
            </div>
        ';
        $inline_id = "inline-$id-$num";
        $script = 'InlineImage.register("' . $inline_id . '", ' . $inline->toJson() . ');';
        return [$block, $script, $inline_id];
    }

    public function format_inlines($text, $id)
    {
        $num  = 0;
        $list = [];

        $text = preg_replace_callback('/image #(\d+)/i', function ($m) use (&$list, &$num, $id) {
            $i = Inline::where(['id' => (int) $m[1]])->first();
            if ($i) {
                list($block, $script) = $this->format_inline($i, $num, $id);
                $list[] = $script;
                $num++;
                return $block;
            } else {
                return $m[0];
            }
        }, $text);

        if ($num > 0) {
            $text .= '<script type="text/javascript">' . implode("\n", $list) . '</script>';
        }

        return $text;
    }

    public function id_to_color($id)
    {
        $r = $id % 255;
        $g = ($id >> 8) % 255;
        $b = ($id >> 16) % 255;
        return "rgb(" . $r . ", " . $g . ", " . $b . ")";
    }

    public function compact_time($datetime)
    {
        $datetime = new DateTime($datetime);

        if ($datetime->format('Y') == date('Y')) {
            if ($datetime->format('M d, Y') == date('M d, Y')) {
                $format = 'H:i';
            } else {
                $format = 'M d';
            }
        } else {
            $format = 'M d, Y';
        }
        return $datetime->format($format);
    }

    /**
     * Test:
     * To change attribute ['level' => 'member'] for ['class' => "need-signup"]
     */
    public function formTag($action_url = null, $attrs = [], Closure $block = null)
    {
        /* Took from parent { */
        if (func_num_args() == 1 && $action_url instanceof Closure) {
            $block = $action_url;
            $action_url = null;
        } elseif ($attrs instanceof Closure) {
            $block = $attrs;
            $attrs = [];
        }
        /* } */

        if (isset($attrs['level']) && $attrs['level'] == 'member') {
            $class = "need-signup";
            if (isset($attrs['class'])) {
                $attrs['class'] .= ' ' . $class;
            } else {
                $attrs['class'] = $class;
            }
            unset($attrs['level']);
        }

        return $this->base()->formTag($action_url, $attrs, $block);
    }

    public function tag_completion_box($box_id, array $form = [], $wrap_tags = false)
    {
        $script = '';

        if (CONFIG()->enable_tag_completion) {
            $script = 'new TagCompletionBox(' . $box_id . ');';

            if ($form) {
                $script .= "\n";
                $script .= "if(TagCompletion)\n";
                $script .= '  TagCompletion.observe_tag_changes_on_submit('
                    . ($form[0] ?: 'null') . ', '
                    . ($form[1] ?: 'null') . ', '
                    . ($form[2] ?: 'null') . ');';
            }

            if ($wrap_tags) {
                $script = $this->contentTag('script', "\n" . $script . "\n", ['type' => 'text/javascript']);
            }
        }

        return $script;
    }
}
