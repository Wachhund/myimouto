<?php

class Dmail extends Rails\ActiveRecord\Base
{
    public function to_name()
    {
        if (!$this->to_id) {
            return;
        }
        return $this->to->name;
    }

    public function from_name()
    {
        return $this->from->name;
    }

    public function mark_as_read()
    {
        $this->updateAttribute('has_seen', true);

        if (!Dmail::where("to_id = ? AND has_seen = false", current_user()->id)->exists()) {
            current_user()->updateAttribute('has_mail', false);
        }
    }

    # iTODO:
    public function send_dmail()
    {
        // if ($this->to->receive_dmails && is_int(strpos($this->to->email, '@')))
        // UserMailer::deliver_dmail($this->to, $this->from, $this->title(), $this->body);
    }

    public function update_recipient()
    {
        $this->to->updateAttribute('has_mail', true);
    }

    protected function validations()
    {
        return [
            'to_id'   => ['presence' => true],
            'from_id' => ['presence' => true],
            'title'   => ['format' => ['with' => '/\S/']],
            'body'    => ['format' => ['with' => '/\S/']],
        ];
    }

    protected function associations()
    {
        return [
            'belongs_to' => [
                'to'   => ['class_name' => 'User', 'foreign_key' => 'to_id'],
                'from' => ['class_name' => 'User', 'foreign_key' => 'from_id'],
            ],
        ];
    }

    protected function callbacks()
    {
        return [
            'after_create' => ['update_recipient', 'send_dmail'],
        ];
    }

    public function title()
    {
        $title = $this->getAttribute('title');

        if ($this->parent_id && strpos($title, 'Re: ') !== 0) {
            return "Re: " . $title;
        } else {
            return $title;
        }
    }

    public function setToName($name)
    {
        if (!$user = User::where(['name' => $name])->first()) {
            return;
        }
        $this->to_id = $user->id;
    }

    public function setFromName($name)
    {
        if (!$user = User::where(['name' => $name])->first()) {
            return;
        }
        $this->from_id = $user->id;
    }
}
