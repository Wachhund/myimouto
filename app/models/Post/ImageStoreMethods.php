<?php

trait PostImageStoreMethods
{
    private $image_store_class;

    public function file_path()
    {
        return $this->_call_store_method('file_path');
    }

    public function file_url()
    {
        return $this->_call_store_method('file_url');
    }

    public function preview_path()
    {
        return $this->_call_store_method('preview_path');
    }

    public function sample_path()
    {
        return $this->_call_store_method('sample_path');
    }

    public function preview_url()
    {
        return $this->_call_store_method('preview_url');
    }

    public function jpeg_path()
    {
        return $this->_call_store_method('jpeg_path');
    }

    public function store_jpeg_url()
    {
        return $this->_call_store_method('store_jpeg_url');
    }

    public function store_sample_url()
    {
        return $this->_call_store_method('store_sample_url');
    }

    public function delete_file()
    {
        return $this->_call_store_method('delete_file');
    }

    public function move_file()
    {
        return $this->_call_store_method('move_file');
    }

    private function _call_store_method($method)
    {
        if (!$this->image_store_class) {
            require_once dirname(__FILE__) . '/ImageStore/Base.php';
            $this->image_store_class = Post_ImageStore_Base::create_instance($this);
        }
        return $this->image_store_class->$method();
    }
}
