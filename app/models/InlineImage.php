<?php
/*
 * InlineImages can be uploaded, copied directly from posts, or cropped from other InlineImages.
 * To create an image by cropping a post, the post must be copied to an InlineImage of its own,
 * and cropped from there; the only UI for cropping is InlineImage->InlineImage.
 *
 * InlineImages can be posted directly in the forum and wiki (and possibly comments).
 *
 * An inline image can have three versions, like a post.  For consistency, they use the
 * same names: image, sample, preview.  As with posts, sample and previews are always JPEG,
 * and the dimensions of preview is derived from image rather than stored.
 *
 * Image files are effectively garbage collected: InlineImages can share files, and the file
 * is deleted when the last one using it is deleted.  This allows any user to copy another user's
 * InlineImage, to crop it or to include it in an Inline.
 *
 * Example use cases:
 *
 * - Plain inlining, eg. for tutorials.  Thumbs and larger images can be shown inline, allowing
 * a click to expand.
 * - Showing edits.  Each user can upload his edit as an InlineImage and post it directly
 * into the forum.
 * - Comparing edits.  A user can upload his own edit, pair it with another version (using
 * Inline), crop to a region of interest, and post that inline.  The images can then be
 * compared in-place.  This can be used to clearly show editing problems and differences.
 */
class InlineImage extends Rails\ActiveRecord\Base
{
    use Moebooru\TempfilePrefix;
    
    public $source;
    
    public $received_file;
    
    public $file_needs_move;
    
    protected function associations()
    {
        return [
            'belongs_to' => [
                'inline'
            ]
        ];
    }
    
    protected function callbacks()
    {
        return [
            'before_validation_on_create' => [
                'download_source',
                'determine_content_type',
                'set_image_dimensions',
                'generate_sample',
                'generate_preview',
                'move_file',
                'set_default_sequence'
            ],
            'after_destroy' => [
                'delete_file'
            ],
            'before_create' => [
                'validate_uniqueness'
            ]
        ];
    }
    
    public function tempfile_image_path()
    {
        return $this->tempfile_prefix() . '.upload';
    }
    
    public function tempfile_sample_path()
    {
        return $this->tempfile_prefix() . '-sample.upload';
    }
    
    public function tempfile_preview_path()
    {
        return $this->tempfile_prefix() . '-preview.upload';
    }
    
    /**
     * MI: Warning for Windows:
     * The PHP function symlink only works on Windows Vista, Server 2008 or greater.
     */
    public function setPostId($id)
    {
        $post = Post::find($id);
        $file = $post->file_path();

        if (!@symlink($file, $this->tempfile_image_path())) {
            copy($file, $this->tempfile_image_path());
        }
        
        $this->received_file = true;
        $this->md5 = $post->md5;
    }
    
    # Call once a file is available in tempfile_image_path.
    public function got_file()
    {
        $this->generate_hash($this->tempfile_image_path());
        chmod($this->tempfile_image_path(), 0775);
        $this->file_needs_move = true;
        $this->received_file = true;
    }
    
    /**
     * @param Rails\ActionDispatch\Http\UploadedFile $f
     */
    public function setFile($f)
    {
        if (!$f->size()) {
            return;
        }
        
        copy($f->tempName(), $this->tempfile_image_path());
        
        $this->got_file();
    }
    
    public function download_source()
    {
        if (!preg_match('/^https?:\/\//', $this->source) || $this->file_ext
            || $this->received_file
        ) {
            return;
        }
        
        try {
            $file = Danbooru::http_get_streaming($this->source);
            file_put_contents($this->tempfile_image_path(), $file);
            unset($file);
            
            $this->got_file();
        } catch (Exception $e) {
            $this->delete_tempfile();
            $this->errors()->add('source', "couldn't be opened: " . $e->getMessage());
            return false;
        }
    }

    protected function delete_tempfile()
    {
        $paths = [
            $this->tempfile_image_path(),
            $this->tempfile_sample_path(),
            $this->tempfile_preview_path()
        ];
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
    
    public function determine_content_type()
    {
        if ($this->file_ext) {
            return true;
        }
        
        if (!file_exists($this->tempfile_image_path())) {
            $this->errors()->addToBase("No file received");
            return false;
        }
        
        $imgsize = getimagesize($this->tempfile_image_path());
        
        $this->file_ext = strtolower(image_type_to_extension($imgsize[2], false));
        
        if ($this->file_ext == 'jpeg') {
            $this->file_ext = 'jpg';
        }
        
        if (!in_array($this->file_ext, ['jpg', 'png', 'gif'])) {
            $this->errors()->add('file', 'is an invalid content type: ' . $this->file_ext ?: 'unknown');
        }
        
        return true;
    }
    
    public function set_image_dimensions()
    {
        if ($this->width and $this->height) {
            return true;
        }
        
        $imgsize = getimagesize($this->tempfile_image_path());
        $this->width  = $imgsize[0];
        $this->height = $imgsize[1];
        
        return true;
    }
    
    public function preview_dimensions()
    {
        return Moebooru\Resizer::reduce_to(['width' => $this->width, 'height' => $this->height], ['width' => 150, 'height' => 150]);
    }
    
    public function thumb_size()
    {
        return Moebooru\Resizer::reduce_to(['width' => $this->width, 'height' => $this->height], ['width' => 400, 'height' => 400]);
    }
    
    public function generate_sample()
    {
        if (is_file($this->sample_path())) {
            return true;
        }
        
        /**
         * We can generate the sample image during upload or offline.  Use tempfile_image_path
         * if it exists, otherwise use file_path.
         */
        $path = $this->tempfile_image_path();
        if (!is_file($path)) {
            $path = $this->file_path();
        }
        if (!is_file($path)) {
            $this->errors()->add('file', 'not found');
            return false;
        }
        
        # If we're not reducing the resolution for the sample image, only reencode if the
        # source image is above the reencode threshold.  Anything smaller won't be reduced
        # enough by the reencode to bother, so don't reencode it and save disk space.
        $sample_size = Moebooru\Resizer::reduce_to(['width' => $this->width, 'height' => $this->height], ['width' => CONFIG()->inline_sample_width, 'height' => CONFIG()->inline_sample_height]);
        if ($sample_size['width'] == $this->width && $sample_size['height'] == $this->height && filesize($path) < CONFIG()->sample_always_generate_size) {
            return true;
        }
        
        # If we already have a sample image, and the parameters havn't changed,
        # don't regenerate it.
        if ($sample_size['width'] == $this->sample_width && $sample_size['height'] == $this->sample_height) {
            return true;
        }
        
        try {
            Moebooru\Resizer::resize($this->file_ext, $path, $this->tempfile_sample_path(), $sample_size, 95);
        } catch (Exception $e) {
            $this->errors()->add('sample', "couldn't be created:" . $e->getMessage());
            return false;
        }
        
        $this->sample_width  = $sample_size['width'];
        $this->sample_height = $sample_size['height'];
        return true;
    }
    
    public function generate_preview()
    {
        if (is_file($this->preview_path())) {
            return true;
        }
        
        if (!is_file($this->tempfile_image_path())) {
            $this->errors()->add('file', 'not found');
            return false;
        }
        
        # Generate the preview from the new sample if we have one to save CPU, otherwise from the image.
        if (is_file($this->tempfile_sample_path())) {
            $path = $this->tempfile_sample_path();
            $ext  = 'jpg';
        } else {
            $path = $this->tempfile_image_path();
            $ext  = $this->file_ext;
        }
        
        try {
            Moebooru\Resizer::resize($ext, $path, $this->tempfile_preview_path(), $this->preview_dimensions(), 95);
        } catch (Exception $e) {
            $this->errors()->add('preview', "couldn't be generated: " . $e->getMessage());
            return false;
        }
        return true;
    }
    
    public function move_file()
    {
        if (!$this->file_needs_move) {
            return true;
        }
        
        if (!is_dir(dirname($this->file_path()))) {
            mkdir(dirname($this->file_path()), 0777, true);
        }
        rename($this->tempfile_image_path(), $this->file_path());
        
        if (is_file($this->tempfile_preview_path())) {
            if (!is_dir(dirname($this->preview_path()))) {
                mkdir(dirname($this->preview_path()), 0777, true);
            }
            rename($this->tempfile_preview_path(), $this->preview_path());
        }
        if (is_file($this->tempfile_sample_path())) {
            if (!is_dir(dirname($this->sample_path()))) {
                mkdir(dirname($this->sample_path()), 0777, true);
            }
            rename($this->tempfile_sample_path(), $this->sample_path());
        }
        $this->file_needs_move = false;
        return true;
    }
    
    public function set_default_sequence()
    {
        if ($this->sequence) {
            return;
        }
        if (!$this->inline) {
            $this->sequence = 1;
            return;
        }
        $siblings = $this->inline->inline_images;
        $max_sequence = ($siblings->getAttributes('sequence') && max($siblings->getAttributes('sequence'))) ?: 0;
        $this->sequence = $max_sequence + 1;
    }
    
    public function generate_hash($path)
    {
        $this->md5 = md5_file($path);
    }
    
    public function has_sample()
    {
        return (bool)$this->sample_height;
    }
    
    public function file_name()
    {
        return $this->md5 . '.' . $this->file_ext;
    }
    
    public function file_name_jpg()
    {
        return $this->md5 . '.jpg';
    }
    
    public function file_path()
    {
        return Rails::publicPath() . '/data/inline/image/' . $this->file_name();
    }
    
    public function preview_path()
    {
        return Rails::publicPath() . '/data/inline/preview/' . $this->file_name_jpg();
    }
    
    public function sample_path()
    {
        return Rails::publicPath() . '/data/inline/sample/' . $this->file_name_jpg();
    }
    
    public function file_url()
    {
        return CONFIG()->url_base . '/data/inline/image/' . $this->file_name();
    }
    
    public function sample_url()
    {
        if ($this->has_sample()) {
            return CONFIG()->url_base . '/data/inline/sample/' . $this->file_name_jpg();
        } else {
            return $this->file_url();
        }
    }
    
    public function preview_url()
    {
        return CONFIG()->url_base . '/data/inline/preview/' . $this->file_name_jpg();
    }
    
    public function delete_file()
    {
        # If several inlines use the same image, they'll share the same file via the MD5.  Only
        # delete the file if this is the last one using it.
        $exists = InlineImage::where('id <> ? AND md5 = ?', $this->id, $this->md5)->first();
        if ($exists) {
            return;
        }
        
        if (is_file($this->file_path())) {
            unlink($this->file_path());
        }
        if (is_file($this->preview_path())) {
            unlink($this->preview_path());
        }
        if (is_file($this->sample_path())) {
            unlink($this->sample_path());
        }
    }
    
    # We should be able to use validates_uniqueness_of for this, but Rails is completely
    # brain-damaged: it only lets you specify an error message that starts with the name
    # of the column, capitalized, so if we say "foo", the message is "Md5 foo".  This is
    # useless.
    public function validate_uniqueness()
    {
        $siblings = $this->inline->inline_images;
        foreach ($siblings as $s) {
            if ($s->id == $this->id) {
                continue;
            }
            if ($s->md5 == $this->md5) {
                $this->errors()->add('base', '#' . $s->sequence . ' already exists.');
                return false;
            }
        }
        return true;
    }
    
    public function api_attributes()
    {
        return [
            'id'             => (int)$this->id,
            'sequence'       => $this->sequence,
            'md5'            => $this->md5,
            'width'          => (int)$this->width,
            'height'         => (int)$this->height,
            'sample_width'   => $this->sample_width,
            'sample_height'  => $this->sample_height,
            'preview_width'  => $this->preview_dimensions()['width'],
            'preview_height' => $this->preview_dimensions()['height'],
            'description'    => (string)$this->description,
            'file_url'       => $this->file_url(),
            'sample_url'     => $this->sample_url(),
            'preview_url'    => $this->preview_url()
        ];
    }
    
    public function asJson()
    {
        return $this->api_attributes();
    }
}
