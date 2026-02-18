<?php

namespace MyImouto\Mime;

class Part
{
    public $type = 'application/octet-stream';
    public $encoding = Mime::ENCODING_BASE64;
    public $disposition = '';
    public $filename = '';
    public $id = '';

    private $rawContent;

    public function __construct($content = '')
    {
        $this->rawContent = $content;
    }

    public function getRawContent()
    {
        return $this->rawContent;
    }

    public function setRawContent($content)
    {
        $this->rawContent = $content;
        return $this;
    }

    public function getContent()
    {
        return $this->rawContent;
    }

    public function __toString()
    {
        return $this->contentToString($this->rawContent);
    }

    private function contentToString($content)
    {
        if (is_resource($content)) {
            $data = stream_get_contents($content);
            $meta = stream_get_meta_data($content);
            if (!empty($meta['seekable'])) {
                rewind($content);
            }
            return (string)$data;
        }

        return (string)$content;
    }
}
