<?php

class NoteVersion extends Rails\ActiveRecord\Base
{
    public function toXml(array $options = [])
    {
        // {:created_at => created_at, :updated_at => updated_at, :creator_id => user_id, :x => x, :y => y, :width => width, :height => height, :is_active => is_active, :post_id => post_id, :body => body, :version => version}.to_xml(options.reverse_merge(:root => "note_version"))
    }

    public function asJson(array $args = [])
    {
        return json_encode([
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'creator_id' => $this->user_id,
            'x'         => $this->x,
            'y'         => $this->y,
            'width'     => $this->width,
            'height'    => $this->height,
            'is_active' => $this->is_active,
            'post_id'   => $this->post_id,
            'body'      => $this->body,
            'version'   => $this->version,
        ]);
    }

    public function author()
    {
        return User::find_name($this->user_id);
    }
}
