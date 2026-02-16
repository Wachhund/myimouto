<?php
class HistoryHelper extends Rails\ActionView\Helper
{
    protected $att_options;

    # :all: By default, some changes are not displayed.  When displaying details
    # for a single change, set :all=>true to display all changes.
    #
    # :show_all_tags: Show unchanged tags.
    public function get_default_field_options()
    {
        return [ 'suppress_fields' => [] ];
    }

    public function get_attribute_options()
    {
        if ($this->att_options)
            return $this->att_options;

        $att_options = [
            # :suppress_fields => If this attribute was changed, don't display changes to specified
            # fields to the same object in the same change.
            #
            # :force_show_initial => For initial changes, created when the object itself is created,
            # attributes that are set to an explicit :default are omitted from the display.  This
            # prevents things like "parent:none" being shown for every new post.  Set :force_show_initial
            # to override this behavior.
            #
            # :primary_order => Changes are sorted alphabetically by field name.  :primary_order
            # overrides this sorting with a top-level sort (default 1).
            #
            # :never_obsolete => Changes that are no longer current or have been reverted are
            # given the class "obsolete".  Changes in fields named by :never_obsolete are not
            # tested.
            #
            # Some cases:
            #
            # - When viewing a single object (eg. "post:123"), the display is always changed to
            # the appropriate type, so if we're viewing a single object, :specific_table will
            # always be true.
            #
            # - Changes to pool descriptions can be large, and are reduced to "description changed"
            # in the "All" view.  The diff is displayed if viewing the Pool view or a specific object.
            #
            # - Adding a post to a pool usually causes the sequence number to change, too, but
            # this isn't very interesting and clutters the display.  :suppress_fields is used
            # to hide these unless viewing the specific change.
            'Post' => [
                'fields' => [
                    'cached_tags' => [ 'primary_order' => 2 ], # show tag changes after other things
                    'source' => [ 'primary_order' => 3 ],
                ],
                'never_obsolete' => ['cached_tags' => true] # tags handle obsolete themselves per-tag
            ],

            'Pool' => [
                'primary_order' => 0,

                'fields' => [
                    'description' => [ 'primary_order' => 5 ] # we don't handle commas correctly if this isn't last
                ],
                'never_obsolete' => [ 'description' => true ] # changes to description aren't obsolete just because the text has changed again
            ],

            'PoolPost' => [
                'fields' => [
                    'sequence' => [ 'max_to_display' => 5],
                    'active' => [
                        'max_to_display' => 10,
                        'suppress_fields' => ['sequence'], # changing active usually changes sequence; this isn't interesting
                        'primary_order' => 2, # show pool post changes after other things
                    ]
                ],
                'cached_tags' => [  ],
            ],

            'Tag' => [
            ],

            'Note' => [
            ],
        ];

        foreach (array_keys($att_options) as $classname) {
            $att_options[$classname] = array_merge([
                'fields' => [],
                'primary_order' => 1,
                'never_obsolete' => [],
                'force_show_initial' => []
            ], $att_options[$classname]);

            $c = $att_options[$classname]['fields'];
            foreach (array_keys($c) as $field) {
                $c[$field] = array_merge($this->get_default_field_options(), $c[$field]);
            }
        }
    }

    public function format_changes($history, array $options = [])
    {
        $html = '';

        $changes = $history->history_changes;

        # Group the changes by class and field.
        $change_groups = [];
        foreach ($changes as $c) {
            if (!isset($change_groups[$c->table_name]))
                $change_groups[$c->table_name] = [];
            if (!isset($change_groups[$c->table_name][$c->column_name]))
                $change_groups[$c->table_name][$c->column_name] = [];
            $change_groups[$c->table_name][$c->column_name][] = $c;
        }

        $att_options = $this->get_attribute_options();

        # Number of changes hidden (not including suppressions):
        $hidden = 0;
        $parts = [];
        foreach ($change_groups as $table_name => $fields) {
            # Apply supressions.
            $to_suppress = [];
            foreach ($fields as $field => $group) {
                $class_name = $group[0]->master_class();
                $table_options = !empty($att_options[$class_name]) ? $att_options[$class_name] : [];
                $field_options = isset($table_options['fields']['field']) ? $table_options['fields']['field'] : $this->get_default_field_options();
                $to_suppress = array_merge($to_suppress, $field_options['suppress_fields']);
            }

            foreach ($to_suppress as $suppress)
                unset($fields[$suppress]);

            foreach ($fields as $field => $group) {
                $class_name = $group[0]->master_class();
                $field_options = isset($table_options['fields']['field']) ? $table_options['fields']['field'] : $this->get_default_field_options();

                # Check for entry limits.
                if (empty($options['specific_history'])) {
                    $max = isset($field_options['max_to_display']) ? $field_options['max_to_display'] : null;
                    if ($max && count($group) > $max) {
                        $hidden += count($group) - $max;
                        $group = array_slice($group, $max);
                    }
                }

                # Format the rest.
                foreach ($group as $c) {
                    if (!$c->previous && $c->changes_to_default() && empty($table_options['force_show_initial']['field']))
                        continue;

                    $part = $this->format_change($history, $c, $options, $table_options);
                    if (!$part)
                        continue;

                    if (!empty($field_options['primary_order']))
                        $primary_order = $field_options['primary_order'];
                    elseif (!empty($table_options['primary_order']))
                        $primary_order = $table_options['primary_order'];
                    else
                        $primary_order = null;

                    $part = array_merge($part, ['primary_order' =>  $primary_order]);
                    $parts[] = $part;
                }
            }
        }

        usort($parts, function($a, $b) {
            $comp = 0;
            foreach (['primary_order', 'field', 'sort_key'] as $field) {
                if ($a[$field] < $b[$field])
                    $comp = -1;
                elseif ($a[$field] == $b[$field])
                    $comp = 0;
                else
                    $comp = 1;

                if ($comp != 0)
                    break;
            }
            return $comp;
        });

        foreach (array_keys($parts) as $idx) {
            if (!$idx || $parts[$idx]['field'] == $parts[$idx - 1]['field'])
                continue;
            $parts[$idx-1]['html'] .= ', ';
        }

        $html = '';

        if (empty($options['show_name']) && $history->group_by_table == 'tags') {
            $tag = $history->history_changes[0]->obj();
            $html .= $this->tag_link($tag->name);
            $html .= ': ';
        }

        if (!empty($history->aux()->note_body)) {
            $body = $history->aux()->note_body;
            if (strlen($body) > 20)
                $body = substr($body, 0, 20) . '...';
            $html .= 'note ' . $this->h($body) . ' ';
        }

        $html .= implode(' ', array_map(function($part) { return $part['html']; }, $parts));

        if ($hidden > 0) {
            $html .= ' (' . $this->linkTo($hidden . ' more...', ['search' => 'change:' . $history->id]) . ')';
        }

        return $html;
    }

    public function format_change($history, $change, $options, $table_options)
    {
        $html = '';

        $classes = [];
        if (empty($table_options['never_obsolete'][$change->column_name]) && $change->is_obsolete()) {
            $classes[] = 'obsolete';
        }

        $added = '<span class="added">+</span>';
        $removed = '<span class="removed">-</span>';

        $sort_key = $change->remote_id;
        $primary_order = 1;
        switch ($change->table_name) {
            case 'posts':
                switch ($change->column_name) {
                    case 'rating':
                        $html .= '<span class="changed-post-rating">rating:';
                        $html .= $change->value;
                        if ($change->previous) {
                            $html .= '←';
                            $html .= $change->previous->value;
                        }
                        $html .= '</span>';
                        break;

                    case 'parent_id':
                        $html .= 'parent:';
                        if ($change->value) {
                            $new = Post::where('id = ?', $change->value)->first();
                            if ($new) {
                                $html .= $this->linkTo($new->id, ['post#show', 'id' => $new->id]);
                            } else {
                                $html .= $change->value;
                            }
                        } else {
                            $html .= 'none';
                        }

                        if ($change->previous) {
                            $html .= '←';
                            if ($change->previous->value) {
                                $old = Post::where('id = ?', $change->previous->value)->first();
                                if ($old) {
                                    $html .= $this->linkTo($old->id, ['post#show', 'id' => $old->id]);
                                } else {
                                    $html .= $change->previous->value;
                                }
                            } else {
                                $html .= 'none';
                            }
                        }
                        break;

                    case 'source':
                        if ($change->previous)  {
                            $html .= sprintf("source changed from <span class='name-change'>%s</span> to <span class='name-change'>%s</span>", $this->source_link($change->previous->value, false), $this->source_link($change->value, false));
                        } else {
                            $html .= sprintf("source: <span class='name-change'>%s</span>", $this->source_link($change->value, false));
                        }
                        break;

                    case 'frames_pending':
                        $html .= 'frames changed: ' . $this->h($change->value ?: '(none)');
                        break;

                    case 'is_rating_locked':
                        # Trueish: if a value equals true or 't'
                        $html .= $change->value || $change->value == 't' ? $added : $removed;
                        $html .= 'rating-locked';
                        break;

                    case 'is_note_locked':
                        # Trueish
                        $html .= $change->value || $change->value == 't' ? $added : $removed;
                        $html .= 'note-locked';
                        break;

                    case 'is_shown_in_index':
                        # Trueish
                        $html .= $change->value || $change->value == 't' ? $added : $removed;
                        $html .= 'shown';
                        break;

                    case 'cached_tags':
                        $previous = $change->previous;

                        $changes = Post::tag_changes($change, $previous, $change->latest());

                        $list = [];
                        $list[] = $this->tag_list($changes['added_tags'], ['obsolete' => $changes['obsolete_added_tags'], 'prefix' => '+', 'class' => 'added']);
                        $list[] = $this->tag_list($changes['removed_tags'], ['obsolete' => $changes['obsolete_removed_tags'], 'prefix' => '-', 'class' => 'removed']);

                        if (!empty($options['show_all_tags']))
                            $list[] = $this->tag_list($changes['unchanged_tags'], ['prefix' => '', 'class' => 'unchanged']);
                        $html .= trim(implode(' ', $list));
                        break;
                }
                break;

            case 'pools':
                $primary_order = 0;

                switch ($change->column_name) {
                    case 'name':
                        if ($change->previous) {
                            $html .= sprintf("name changed from <span class='name-change'>%s</span> to <span class='name-change'>%s</span>", $this->h($change->previous->value), $this->h($change->value));
                        } else {
                            $html .= sprintf("name: <span class='name-change'>%s</span>", $this->h($change->value));
                        }
                        break;

                    case 'description':
                        if ($change->value === '') {
                            $html .= 'description removed';
                        } else {
                            if (!$change->previous)
                                $html .= 'description: ';
                            elseif ($change->previous->value === '')
                                $html .= 'description added: ';
                            else
                                $html .= 'description changed: ';

                            # Show a diff if there's a previous description and it's not blank.  Otherwise,
                            # just show the new text.
                            $show_diff = $change->previous && $change->previous->value !== '';
                            if ($show_diff)
                                $text = Moebooru\Diff::generate($change->previous->value, $change->value);
                            else
                                $text = $this->h($change->value);

                            # If there's only one line in the output, just show it inline.  Otherwise, show it
                            # as a separate block.
                            $multiple_lines = is_int(strpos($text, '<br>')) || is_int(strpos($text, '<br />'));

                            $show_in_detail = !empty($options['specific_history']) || !empty($options['specific_object']);
                            if (!$multiple_lines)
                                $display = $text;
                            elseif ($show_diff)
                                $display = "<div class='diff text-block'>${text}</div>";
                            else
                                $display = "<div class='initial-diff text-block'>${text}</div>";

                            if ($multiple_lines && !$show_in_detail)
                                $html .= "<a onclick='$(this).hide(); $(this).next().show()' href='#'>(show changes)</a><div style='display: none;'>${display}</div>";
                            else
                                $html .= $display;
                        }
                        break;

                    case 'is_public':
                        # Trueish
                        $html .= $change->value || $change->value == 't' ? $added : $removed;
                        $html .= 'public';
                        break;

                    case 'is_active':
                        # Trueish
                        $html .= $change->value || $change->value == 't' ? $added : $removed;
                        $html .= 'active';
                        break;
                }
                break;

            case 'pools_posts':
                # Sort the output by the post id.
                $sort_key = $change->obj()->post->id;
                switch ($change->column_name) {
                    case 'active':
                        # Trueish
                        $html .= $change->value || $change->value == 't' ? $added : $removed;

                        $html .= $this->linkTo('post #' . $change->obj()->post_id, ['post#show', 'id' => $change->obj()->post_id]);
                        break;

                    case 'sequence':
                        /**
                         * MI: For some reason the sequence is shown in the first HistoryChange created,
                         * while in Moebooru it doesn't. We will hide it here.
                         */
                        if (!$change->previous)
                            return null;

                        $seq = 'order:' . $change->obj()->post_id . ':' . $change->value;
                        $seq .= '←' . $change->previous->value;
                        $html .= $this->linkTo($seq, ['post#show', 'id' => $change->obj()->post_id]);
                        break;
                }
                break;

            case 'tags':
                switch ($change->column_name) {
                    case 'tag_type':
                        $html .= 'type:';
                        $tag_type = Tag::type_name_from_value($change->value);
                        $html .= '<span class="tag-type-' . $tag_type . '">' . $tag_type . '</span>';
                        if ($change->previous) {
                            $tag_type = Tag::type_name_from_value($change->previous->value);
                            $html .= '←<span class="tag-type-' . $tag_type . '">' . $tag_type . '</span>';
                        }
                        break;

                    case 'is_ambiguous':
                        # Trueish
                        $html .= $change->value || $change->value == 't' ? $added : $removed;
                        $html .= 'ambiguous';
                        break;
                }
                break;

            case 'notes':
                switch($change->column_name) {
                    case 'body':
                        if ($change->previous) {
                            $html .= sprintf("body changed from <span class='name-change'>%s</span> to <span class='name-change'>%s</span>", $this->h($change->previous->value), $this->h($change->value));
                        } else {
                            $html .= sprintf("body: <span class='name-change'>%s</span>", $this->h($change->value));
                        }
                        break;

                    case 'x':
                    case 'y':
                    case 'width':
                    case 'height':
                        $html .= $change->column_name . ':' . $this->h($change->value);
                        break;

                    case 'is_active':
                        # Trueish
                        if ($change->value || $change->value == 't') {
                            # Don't show the note initially being set to active.
                            if (!$change->previous) {
                                return null;
                            }
                            $html .= 'undeleted';
                        } else {
                            $html .= 'deleted';
                        }
                }
                break;
        }

        $span = '<span class="' . implode(' ', $classes) . '">' . $html . '</span>';

        return [
            'html' => $span,
            'field' => $change->column_name,
            'sort_key' => $sort_key
        ];
    }

    public function tag_list($tags, array $options = [])
    {
        if (!$tags)
            return '';

        $html = '<span class="' . (!empty($options['class']) ? $options['class'] : '') . '">';

        $tags_html = [];
        foreach ($tags as $name) {
            $tags_html[] = $this->tag_link($name, $options);
        }

        if (!$tags_html)
            return '';

        $html .= implode(' ', $tags_html);
        $html .= '</span>';
        return $html;
    }
}
