<?php

abstract class Post_ImageStore_Base
{
    protected $_post;

    abstract public function file_path();

    abstract public function file_url();

    abstract public function preview_path();

    abstract public function sample_path();

    abstract public function preview_url();

    abstract public function jpeg_path();

    abstract public function store_jpeg_url();

    abstract public function store_sample_url();

    public static function create_instance(Post $post)
    {
        $image_store = Rails::services()->get('inflector')->camelize(CONFIG()->image_store);
        $file = dirname(__FILE__) . '/' . $image_store . '.php';

        if (!is_file($file)) {
            throw new Exception(
                sprintf("File not found for image store configuration '%s'.", CONFIG()->image_store ?: '[empty value]'),
            );
        }

        require_once $file;

        $class = 'Post_ImageStore_' . $image_store;

        $object = new $class();
        $object->_post = $post;

        return $object;
    }

    public function delete_file()
    {
        if (is_file($this->file_path())) {
            @unlink($this->file_path());
        }
        if ($this->_post->image()) {
            if (file_exists($this->preview_path())) {
                @unlink($this->preview_path());
            }
            if (file_exists($this->sample_path())) {
                @unlink($this->sample_path());
            }
            if (file_exists($this->jpeg_path())) {
                @unlink($this->jpeg_path());
            }
        }
    }

    public function move_file()
    {
        $this->_create_dirs($this->file_path());

        if ($this->_post->is_import) {
            rename($this->_post->tempfile_path(), $this->file_path());
        } else {
            move_uploaded_file($this->_post->tempfile_path(), $this->file_path());
        }

        // chmod($this->file_path(), 0777);

        if ($this->_post->image()) {
            $this->_create_dirs($this->preview_path());
            rename($this->_post->tempfile_preview_path(), $this->preview_path());
            // chmod($this->preview_path(), 0777);
        }

        if (file_exists($this->_post->tempfile_sample_path())) {
            $this->_create_dirs($this->sample_path());
            rename($this->_post->tempfile_sample_path(), $this->sample_path());
            // chmod($this->sample_path(), 0777);
        }

        if (file_exists($this->_post->tempfile_jpeg_path())) {
            $this->_create_dirs($this->jpeg_path());
            rename($this->_post->tempfile_jpeg_path(), $this->jpeg_path());
            // chmod($this->jpeg_path(), 0777);
        }
    }

    protected function _file_hierarchy()
    {
        return substr($this->_post->md5, 0, 2) . '/' . substr($this->_post->md5, 2, 2);
    }

    protected function _create_dirs($dir)
    {
        $dirs = array_filter(explode('/', str_replace(Rails::root(), '', pathinfo($dir, PATHINFO_DIRNAME))));
        $dir = Rails::root() . '/';
        foreach ($dirs as $d) {
            $dir .= $d . '/';
            !is_dir($dir) && mkdir($dir);
        }
    }
}
