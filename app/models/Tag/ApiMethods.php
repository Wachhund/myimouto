<?php

trait TagApiMethods
{
    public function api_attributes()
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'count' => $this->post_count,
            'type'  => $this->tag_type,
            'ambiguous' => $this->is_ambiguous,
        ];
    }

    public function toXml(array $options = [])
    {
        $options['root'] = 'tag';
        $options['attributes'] = $this->api_attributes();
        return parent::toXml($options);
    }
}
