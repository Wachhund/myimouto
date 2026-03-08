<?php
class DText
{
    static public function parse($str)
    {
        $state = ['newline'];
        $result = '';
        
        # Normalize newlines.
        $str = trim($str);
        $str = preg_replace([
            '/(\r\n?)/',
            '/\n{3,}/',
            # Nuke spaces between newlines.
            '/ *\n */',
        ], [
            "\n",
            "\n\n",
            "\n",
        ], $str);
        
        $str = htmlentities($str);
        
        # Keep newline, use carriage return for split.
        $str = str_replace("\n", "\n\r", $str);
        
        $data = explode("\r", $str);
        
        # Parse header and list first, line by line.
        foreach ($data as $d) {
            $result .= self::parseline($d, $state);
        }
        
        # Parse inline tags as a whole.
        $result = self::parseinline($result);
        
        # htmLawed ensures valid html output.
        require_once Rails::root() . '/vendor/htmLawed/htmLawed.php';
        return htmLawed($result, ['safe' => 1], 'span=onclick');
    }
    
    static public function parseinline($str)
    {
        # Short links subtitution:
        $str = preg_replace_callback('/\[\[(.+?)\]\]/', function($m) { # [[title]] or [[title|label]] ;link to wiki
            $data = explode('|', $m[1], 2);
            $title = $data[0];
            $label = isset($data[1]) ? $data[1] : $title;
            return "<a href=\"/wiki/show?title=" . urlencode(html_entity_decode(str_replace(" ", "_", $title))) . "\">" . $label . "</a>";
        }, $str);
        $str = preg_replace_callback('/\{\{(.+?)\}\}/', function($m) { # {{post tags here}} ;search post with tags
            return "<a href=\"/post?tags=" . urlencode(html_entity_decode($m[1])) . "\">" . $m[1] . "</a>";
        }, $str);
        
        # Miscellaneous single line tags subtitution.
        $str = preg_replace([
            '/\[b\](.+?)\[\/b\]/',
            '/\[i\](.+?)\[\/i\]/',
            '/(post #(\d+))/i',
            '/(forum #(\d+))/i',
            '/(comment #(\d+))/i',
            '/(pool #(\d+))/i',
            
            # Single line spoiler tags.
            '/\[spoilers?\](.+?)\[\/spoilers?\]/',
            '/\[spoilers?=(.+?)\](.+?)\[\/spoilers?\]/',
            
            # Multi line spoiler tags.
            '/\[spoilers?\]/',
            '/\[spoilers?=(.+?)\]/',
            '/\[\/spoilers?\]/',
            
            # Quote.
            '/\[quote\]/',
            '/\[\/quote\]/',
        ], [
            '<strong>\1</strong>',
            '<em>\1</em>',
            '<a href="/post/show/\2">\1</a>',
            '<a href="/forum/show/\2">\1</a>',
            '<a href="/comment/show/\2">\1</a>',
            '<a href="/pool/show/\2">\1</a>',
            
            '<span class="spoiler" onclick="Comment.spoiler(this); return false;"><span class="spoilerwarning">spoiler</span></span><span class="spoilertext" style="display: none">\1</span>',
            '<span class="spoiler" onclick="Comment.spoiler(this); return false;"><span class="spoilerwarning">\1</span></span><span class="spoilertext" style="display: none">\2</span>',
            
            '<span class="spoiler" onclick="Comment.spoiler(this); return false;"><span class="spoilerwarning">spoiler</span></span><div class="spoilertext" style="display: none">',
            '<span class="spoiler" onclick="Comment.spoiler(this); return false;"><span class="spoilerwarning">\1</span></span><div class="spoilertext" style="display: none">',
            '</div>',
            
            '<blockquote><div>',
            '</div></blockquote>',
        ], $str);
        
        $str = self::parseurl($str);
        
        return preg_replace([
            # Extraneous newlines before closing div are unnecessary.
            '/\n+(<\/div>)/',
            # So are after headers, lists, and blockquotes.
            '/(<\/(ul|h\d+|blockquote)>)\n+/',
            # And after opening blockquote.
            '/(<blockquote><div>)\n+/',
            '/\n/',
        ], [
            '\1',
            '\1',
            '\1',
            '<br>',
        ], $str);
    }
    
    static public function parseline($str, &$state)
    {
        if (preg_match('/\d/', end($state)) || preg_match('/^\*+\s+/', $str)) {
            return self::parselist($str, $state);
        } elseif (preg_match('/^(h[1-6])\.\s*(.+)\n*/', $str, $m)) {
            return "<${m[1]}>${m[2]}</${m[1]}>";
        } else {
            return $str;
        }
    }
    
    static public function parselist($str, &$state)
    {
        $html = '';
        if (!preg_match('/\d/', end($state))) {
            $state[] = "1";
            $html .= '<ul>';
        } else {
            $n = substr_count((preg_match('/^\*+\s+/', $str, $m) ? $m[0] : ''), '*');
            $last = (int)end($state);
            if ($n < $last) {
                $html .= str_repeat('</ul>', $last - $n);
                array_pop($state);
                $state[] = (string)$n;
            } elseif ($n > $last) {
                $html .= '<ul>';
                array_pop($state);
                $state[] = (string)($last + 1);
            }
            if (!preg_match('/^\*+\s+/', $str)) {
                array_pop($state);
                return $html . self::parseline($str, $state);
            }
        }
        $html .= preg_replace('/\*+\s+(.+)\n*/', '<li>\1', $str);
        return $html;
    }
    
    static public function parseurl($str)
    {
        # Basic URL pattern
        $url = '(h?ttps?:\/\/\[?(:{0,2}[\w\-]+)((:{1,2}|\.)[\w\-]+)*\]?(:\d+)*(\/[^\s\n<]*)*)';
        
        # Substitute url tag in this form:
        return preg_replace([
            '/(^|[\s\(>])' . $url . '/',                            # url
            '/&lt;&lt;\s*' . $url . '\s*\|\s*(.+?)\s*&gt;&gt;/',    # <<url|label>>
            '/(^|[\s>])&quot;(.+?)&quot;:' . $url . '/',            # "label":url
            '/&lt;&lt;\s*' . $url . '\s*&gt;&gt;/',                 # <<url>>
            '/<a href="ttp/',                                       # Fix ttp(s) scheme
        ], [
            '\1<a href="\2">\2</a>',
            '<a href="\1">\7</a>',
            '\1<a href="\3">\2</a>',
            '<a href="\1">\1</a>',
            '<a href="http',
        ], $str);
    }
}
