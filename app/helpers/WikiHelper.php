<?php

class WikiHelper extends Rails\ActionView\Helper
{
    // public function linked_from($to)
    // {
    // links = to.find_pages_that_link_to_this.map do |page|
    // linkTo(h(page.pretty_title), :controller => "wiki", :action => "show", :title => page.title)
    // end.join(", ")

    // links.empty? ? "None" : links
    // }

    # Generates content for "Changes" column on history page.
    # Not filtered, must return HTML-safe string.
    # a is current
    # b is previous (or what's a compared to)
    public function page_change($a, $b)
    {
        $changes = $a->diff($b);
        if (!$changes) {
            return 'no change';
        } else {
            return implode(', ', array_map(function ($change) use ($a, $b) {
                switch ($change) {
                    case 'initial':
                        return 'first version';
                    case 'body':
                        return 'content update';
                    case 'title':
                        return "page rename (" . $this->h($a->title) . " ← " . $this->h($b->title) . ")";
                    case 'is_locked':
                        return $a->is_locked ? 'page lock' : 'page unlock';
                };
            }, $changes));
        }
    }
}
