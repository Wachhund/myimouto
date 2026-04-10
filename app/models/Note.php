<?php

class Note extends Rails\ActiveRecord\Base
{
    use Moebooru\Versioning\VersioningTrait;
    use Rails\ActsAsVersioned\Versioning;

    public static function init_versioning($v)
    {
        $v->versioned_attributes([
            'is_active' => ['default' => true, 'allow_reverting_to_default' => true],
            'x',
            'y',
            'width',
            'height',
            'body',
        ])
            ->versioning_group_by(['class' => 'Post'])
            # When any change is made, save the current body to the history record, so we can
            # display it along with the change to identify what was being changed at the time.
            # Otherwise, we'd have to look back through history for each change to figure out
            # what the body was at the time.
            ->versioning_aux_callback('aux_callback');
    }

    # TODO: move this to a helper
    public function formatted_body()
    {
        $parser = new Michelf\Markdown();
        $parser->no_markup = true;
        $html = $parser->transform($this->body);

        if (preg_match_all('~(<p>&lt;tn>.+?&lt;/tn></p>)~s', $html, $ms)) {
            foreach ($ms[1] as $m) {
                $html = str_replace(
                    $m,
                    nl2br('<p class="tn">' . substr($m, 10, -12) . '</p>'),
                    $html,
                );
            }
        }

        return $html;
    }

    protected function update_post()
    {
        $active_notes = self::connection()->selectValue("SELECT 1 FROM notes WHERE is_active = ? AND post_id = ? LIMIT 1", true, $this->post_id);

        if ($active_notes) {
            self::connection()->executeSql("UPDATE posts SET last_noted_at = ? WHERE id = ?", $this->updated_at, $this->post_id);
        } else {
            self::connection()->executeSql("UPDATE posts SET last_noted_at = ? WHERE id = ?", null, $this->post_id);
        }
    }

    protected function associations()
    {
        return [
            'belongs_to' => ['post'],
        ];
    }

    protected function callbacks()
    {
        return [
            'after_save' => [
                'update_post',
            ],
        ];
    }

    protected function validations()
    {
        return [
            'post_must_not_be_note_locked',
        ];
    }

    protected function post_must_not_be_note_locked()
    {
        if ($this->is_locked()) {
            $this->errors()->addToBase("Post is note locked");
            return false;
        }
    }

    public function is_locked()
    {
        if ($this->connection()->selectValue("SELECT 1 FROM posts WHERE id = ? AND is_note_locked = ?", $this->post_id, true)) {
            return true;
        } else {
            return false;
        }
    }

    public function aux_callback()
    {
        # If our body has been changed and we have an old one, record it as the body;
        # otherwise if we're a new note and have no old body, record the current one.
        return ['note_body' => $this->bodyWas() ?: $this->body];
    }

    protected function actsAsVersionedConfig()
    {
        return [
            'table_name' => 'note_versions',
            'foreign_key' => 'note_id',
        ];
    }

    protected function versioningRelation($relation)
    {
        return $relation->order("updated_at DESC");
    }
}
