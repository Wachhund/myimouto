<?php
# These are methods dealing with getting the image and generating the thumbnail.
# It works in conjunction with the image_store methods. Since these methods have
# to be called in a specific order, they've been bundled into one module.
trait PostFileMethods
{
    /**
     * Allowed mime types.
     */
    static protected $MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'video/webm' => 'webm',
        'video/mp4'  => 'mp4',
        'application/x-shockwave-flash' => 'swf'
    ];
    
    /**
     * @see MyImouto\DefaultBooruConfig::$fake_sample_url
     * @see PostApiMethods::batch_api_data()
     */
    static protected $create_fake_sample_url = false;
    
    public $file;
    
    public $is_import = false;
    
    public $tempfile_path;
    
    /**
     * Used only to parse filename into tags and source, which is done in the CONFIG class.
     */
    public $tempfile_name;
    
    public $mime_type;

    public $received_file;
    
    protected $upload;
    
    /**
     * For Import
     */
    static public function get_import_files($dir)
    {
        # [0] files; [1] invalid_files; [2] invalid_folders;
        $data = array(array(), array(), array());
        
        if ($fh = opendir($dir)) {
            while (false !== ($file = readdir($fh))) {
                if ($file == '.' || $file == '..')
                    continue;
                
                if (is_int(strpos($file, '?'))) {
                    $e = addslashes(str_replace(Rails::root().'/public/data/import/', '', utf8_encode($dir.$file)));
                    if (preg_match('/\.\w+$/', $e))
                        $data[1][] = $e;
                    else
                        $data[2][] = $e;
                    continue;
                }
                
                if (is_dir($dir.$file)) {
                    list($files, $invalid_files, $invalid_folders) = Post::get_import_files($dir.$file.'/');
                    $data[0] = array_merge($data[0], $files);
                    $data[1] = array_merge($data[1], $invalid_files);
                    $data[2] = array_merge($data[2], $invalid_folders);
                } else
                    $data[0][] = addslashes(str_replace(Rails::root().'/public/data/import/', '', utf8_encode($dir.$file)));
            }
            closedir($fh);
        }
        sort($data[0]);
        return $data;
    }
    
    public function strip_exif()
    {
         // if (file_ext.downcase == 'jpg' then) {
            // # FIXME: awesome way to strip EXIF.
            // #                This will silently fail on systems without jhead in their PATH
            // #                and may cause confusion for some bored ones.
            // system('jhead', '-purejpg', tempfile_path)
        // }
        // return true
    }

    protected function ensure_tempfile_exists()
    {
        // if ($this->is_upload) {
            // if (!empty($_FILES['post']['name']['file']) && $_FILES['post']['error']['file'] === UPLOAD_ERR_OK)
                // return;
        // } else {
            // vde($_FILES['post']['name']['file']);
            // vde(filesize($this->tempfile_path()));
            if (is_file($this->tempfile_path()) && filesize($this->tempfile_path()))
                return;
        // }
        $this->errors()->add('file', "not found, try uploading again");
        return false;
    }

    protected function validate_content_type()
    {
        if (!array_key_exists($this->mime_type, self::$MIME_TYPES)) {
            $this->errors()->add('file', 'is an invalid content type: ' . $this->mime_type);
            return false;
        }
        
        $this->file_ext = self::$MIME_TYPES[$this->mime_type];
    }
    
    public function pretty_file_name($options = array())
    {
        # Include the post number and tags.    Don't include too many tags for posts that have too
        # many of them.
        empty($options['type']) && $options['type'] = 'image';
        $tags = null;
        # If the filename is too long, it might fail to save or lose the extension when saving.
        # Cut it down as needed.    Most tags on moe with lots of tags have lots of characters,
        # and those tags are the least important (compared to tags like artists, circles, "fixme",
        # etc).
        #
        # Prioritize tags:
        # - remove artist and circle tags last; these are the most important
        # - general tags can either be important ("fixme") or useless ("red hair")
        # - remove character tags first; 

     
        if ($options['type'] == 'sample')
            $tags = "sample";
        else
            $tags = Tag::compact_tags($this->cached_tags, 150);
        
        # Filter characters.
        $tags = str_replace(array('/', '?'), array('_', ''), $tags);

        $name = "{$this->id} $tags";
        if (CONFIG()->download_filename_prefix)
            $name = CONFIG()->download_filename_prefix . " " . $name;
        
        return $name;
    }
    
    public function file_name()
    {
        return $this->md5 . "." . $this->file_ext;
    }
    
    public function delete_tempfile()
    {
        if (is_file($this->tempfile_path()))
            unlink($this->tempfile_path());
        if (is_file($this->tempfile_preview_path()))
            unlink($this->tempfile_preview_path());
        if (is_file($this->tempfile_sample_path()))
            unlink($this->tempfile_sample_path());
        if (is_file($this->tempfile_jpeg_path()))
            unlink($this->tempfile_jpeg_path());
    }
    
    public function tempfile_path()
    {
        if (!$this->tempfile_path)
            $this->tempfile_path = tempnam(Rails::root()  . "/tmp", "upload");
        return $this->tempfile_path;
    }

    public function fake_sample_url()
    {
        if (CONFIG()->use_pretty_image_urls) {
            $path = "/data/image/".$this->md5."/".$this->pretty_file_name(array('type' => 'sample')).'.'.$this->file_ext;
        } else
            $path = "/data/image/" . CONFIG()->sample_filename_prefix . $this->md5 . '.' . $this->file_ext;
        
        return CONFIG()->url_base . $path;
    }
    
    public function tempfile_preview_path()
    {
        return Rails::root() . "/public/data/{$this->md5}-preview.jpg";
    }

    public function tempfile_sample_path()
    {
        return Rails::root() . "/public/data/{$this->md5}-sample.jpg";
    }
    
    public function tempfile_jpeg_path()
    {
        return Rails::root() . "/public/data/".$this->md5."-jpeg.jpg";
    }

    # Generate MD5 and CRC32 hashes for the file.    Do this before generating samples, so if this
    # is a duplicate we'll notice before we spend time resizing the image.
    public function regenerate_hash()
    {
        $path = $this->tempfile_path ?: $this->file_path();
        
        if (!file_exists($path)) {
            
            $this->errors()->add('file', "not found");
            return false;
        }
        
        $this->md5 = md5_file($path);
        # iTODO
        // $this->crc32 = ...............
        return true;
    }

    public function regenerate_jpeg_hash()
    {
        if (!$this->has_jpeg())
            return false;

        // crc32_accum = 0
        // File.open(jpeg_path, 'rb') { |fp|
            // buf = ""
            // while fp.read(1024*64, buf) do
                // crc32_accum = Zlib.crc32(buf, crc32_accum)
            // end
        // }
        // return; false if self.jpeg_crc32 == crc32_accum

        // self.jpeg_crc32 = crc32_accum
        return true;
    }

    public function generate_hash()
    {
        if (!$this->regenerate_hash())
            return false;
        
        if (Post::where("md5 = ?", $this->md5)->exists()) {
            $this->delete_tempfile();
            $this->errors()->add('md5', "already exists");
            return false;
        } else
            return true;
    }

    # Generate the specified image type.    If options[:force_regen] is set, generate the file even
    # IF it already exists
    
    public function regenerate_images($type, array $options = array())
    {
        if (!$this->image())
            return true;

        $force_regen = !empty($options['force_regen']);
        
        switch ($type) {
            case 'sample':
                if (!$this->generate_sample($force_regen)) {
                    return false;
                }
                $temp_path = $this->tempfile_sample_path();
                $dest_path = $this->sample_path();
                break;
            
            case 'jpeg':
                if (!$this->generate_jpeg($force_regen)) {
                    return false;
                }
                $temp_path = $this->tempfile_jpeg_path();
                $dest_path = $this->jpeg_path();
                break;
            
            case 'preview':
                if (!$this->generate_preview($force_regen)) {
                    return false;
                }
                $temp_path = $this->tempfile_preview_path();
                $dest_path = $this->preview_path();
                break;
            
            default:
                throw new Exception(sprintf("unknown type: %s", $type));
        }

        # Only move in the changed files on success.    When we return; false, the caller won't
        # save us to the database; we need to only move the new files in if we're going to be
        # saved.    This is normally handled by move_file.
         if (is_file($temp_path)) {
            $dest_dir = dirname($dest_path);
            if (!is_dir($dest_dir)) {
                mkdir($dest_dir, 0775, true);
            }
            rename($temp_path, $dest_path);
            chmod($dest_path, 0775);
        }

        return true;
    }

    # Automatically download from the source if it's a URL.
    public function download_source()
    {
        if (!preg_match('/^https?:\/\//', $this->source) || $this->file_ext || $this->tempfile_path)
            return;
        
        try {
            $file = Danbooru::http_get_streaming($this->source);
            
            if ($file) {
                file_put_contents($this->tempfile_path(), $file);
                # This flag will cause Post\ImageStore\Base\move_file() to rename() the file
                # instead of move_uploaded_file().
                $this->is_import = true;
            }
            if (preg_match('/^http/', $this->source) && !preg_match('/pixiv\.net/', $this->source)) {
                # $this->source = "Image board";
                $this->source = "";
            }
            
            return true;
        } catch (Danbooru\Exception\RuntimeException $e) {
            $this->delete_tempfile();
            $this->errors()->add('source', "couldn't be opened: " . $e->getMessage());
            return false;
        }
    }

    public function determine_content_type()
    {
        if (!file_exists($this->tempfile_path())) {
            $this->errors()->addToBase("No file received");
            return false;
        }
        
        $this->tempfile_name = pathinfo($this->tempfile_name, PATHINFO_FILENAME);
        
        list ($x, $y, $type) = getimagesize($this->tempfile_path());
        
        $this->mime_type = image_type_to_mime_type($type);
    }
    
    # Assigns a CGI file to the post. This writes the file to disk and generates a unique file name.
    // protected function file_setter($f)
    // {
        // return; if f.nil? || count(f) == 0

        // if (f.tempfile.path) {
            // # Large files are stored in the temp directory, so instead of
            // # reading/rewriting through Ruby, just rely on system calls to
            // # copy the file to danbooru's directory.
            // FileUtils.cp(f.tempfile.path, tempfile_path)
        // } else {
            // File.open(tempfile_path, 'wb') {|nf| nf.write(f.read)}
        // }
        
        // $this->received_file = true;
    // }

    protected function set_image_dimensions()
    {
        if ($this->image() or $this->flash()) {
            list($this->width, $this->height) = getimagesize($this->tempfile_path());
        }
        $this->file_size = filesize($this->tempfile_path());
    }

    # If the image resolution is too low and the user is privileged or below, force the
    # image to pending.    If the user has too many pending posts, raise an error.
    #
    # We have to do this here, so on creation it's done after set_image_dimensions so
    # we know the size.    If we do it in another module the order of operations is unclear.
    protected function image_is_too_small()
    {
        if (!CONFIG()->min_mpixels) return false;
        if (empty($this->width)) return false;
        if ($this->width * $this->height >= CONFIG()->min_mpixels) return false;
        return true;
    }

    protected function set_image_status()
    {
        if (!$this->image_is_too_small())
            return true;
        
        if ($this->user->level >= 33)
            return;

        $this->status = "pending";
        $this->status_reason = "low-res";
        return true;
    }

    # If this post is pending, and the user has too many pending posts, reject the upload.
    # This must be done after set_image_status.
    public function check_pending_count()
    {
        if (!CONFIG()->max_pending_images) return;
        if ($this->status != "pending") return;
        if ($this->user->level >= 33) return;

        $pending_posts = Post::where("user_id = ? AND status = 'pending'", $this->user_id)->count();
        if ($pending_posts < CONFIG()->max_pending_images) return;

        $this->errors()->addToBase("You have too many posts pending moderation");
        return false;
    }

    # Returns true if the post is an image format that GD can handle.
    public function image()
    {
        return in_array($this->file_ext, array('jpg', 'jpeg', 'gif', 'png'));
    }

    # Returns true if the post is a Flash movie.
    public function flash()
    {
        return $this->file_ext == "swf";
    }
    
    public function gif()
    {
        return $this->file_ext == 'gif';
    }

    // public function find_ext(file_path)
    // {
        // ext = File.extname(file_path)
        // if (ext.blank?) {
            // return; "txt"
        // } else {
            // ext = ext[1..-1].downcase
            // ext = "jpg" if ext == "jpeg"
            // return; ext
        // }
    // }
    

    
    // public function content_type_to_file_ext(content_type)
    // {
        // case content_type.chomp
        // when "image/jpeg"
            // return; "jpg"

        // when "image/gif"
            // return; "gif"

        // when "image/png"
            // return; "png"

        // when "application/x-shockwave-flash"
            // return; "swf"

        // } else {
            // nil
        // end
    // }

    public function raw_preview_dimensions()
    {
        if ($this->image()) {
            $dim = Moebooru\Resizer::reduce_to(array('width' => $this->width, 'height' => $this->height), array('width' => 300, 'height' => 300));
            $dim = array($dim['width'], $dim['height']);
        } else
            $dim = array(300, 300);
        return $dim;
    }

    public function preview_dimensions()
    {
        if ($this->image()) {
            $dim = Moebooru\Resizer::reduce_to(array('width' => $this->width, 'height' => $this->height), array('width' => 150, 'height' => 150));
            $dim = array($dim['width'], $dim['height']);
        } else
            $dim = array(150, 150);
        return $dim;
    }

    public function generate_sample($force_regen = false)
    {
        if ($this->gif() || !$this->image()) return true;
        elseif (!CONFIG()->image_samples) return true;
        elseif (!$this->width && !$this->height) return true;
        elseif ($this->file_ext == "gif") return true;

        # Always create samples for PNGs.
        $ratio = $this->file_ext == 'png' ? 1 : CONFIG()->sample_ratio;

        $size = array('width' => $this->width, 'height' => $this->height);
        if (CONFIG()->sample_width)
            $size = Moebooru\Resizer::reduce_to($size, array('width' => CONFIG()->sample_width, 'height' => CONFIG()->sample_height), $ratio);
        
        $size = Moebooru\Resizer::reduce_to($size, array('width' => CONFIG()->sample_max, 'height' => CONFIG()->sample_min), $ratio, false, true);
        
        # We can generate the sample image during upload or offline.    Use tempfile_path
        #- if it exists, otherwise use file_path.
        $path = $this->tempfile_path();
        
        if (!file_exists($path)) {
            $this->errors()->add('file', "not found");
            return false;
        }

        # If we're not reducing the resolution for the sample image, only reencode if the
        # source image is above the reencode threshold.    Anything smaller won't be reduced
        # enough by the reencode to bother, so don't reencode it and save disk space.
        if ($size['width'] == $this->width && $size['height'] == $this->height && filesize($path) < CONFIG()->sample_always_generate_size) {
            $this->sample_width = null;
            $this->sample_height = null;
            return true;
        }
        
        # If we already have a sample image, and the parameters havn't changed,
        # don't regenerate it.
        if ($this->has_sample() && !$force_regen && ($size['width'] == $this->sample_width && $size['height'] == $this->sample_height))
            return true;
        
        try {
            Moebooru\Resizer::resize($this->file_ext, $path, $this->tempfile_sample_path(), $size, CONFIG()->sample_quality);
        } catch (Exception $e) {
            $this->errors()->add('sample', 'couldn\'t be created: '. $e->getMessage());
            return false;
        }
        
        $this->sample_width = $size['width'];
        $this->sample_height = $size['height'];
        $this->sample_size = filesize($this->tempfile_sample_path());
        
        # iTODO: enable crc32 for samples.
        $crc32_accum = 0;

        return true;
    }
    
    protected function generate_preview($force_regen = false)
    {
        if (!$this->image() || (!$this->width && !$this->height))
            return true;
        
        # If we already have a preview image, don't regenerate it.
        if (is_file($this->preview_path()) && !$force_regen) {
            return true;
        }
        
        $size = Moebooru\Resizer::reduce_to(array('width' => $this->width, 'height' => $this->height), array('width' => 300, 'height' => 300));

        # Generate the preview from the new sample if we have one to save CPU, otherwise from the image.
        if (is_file($this->tempfile_sample_path()))
            list($path, $ext) = array($this->tempfile_sample_path(), "jpg");
        elseif (is_file($this->sample_path()))
            list($path, $ext) = array($this->sample_path(), "jpg");
        elseif (is_file($this->tempfile_path))
            list($path, $ext) = array($this->tempfile_path, $this->file_ext);
        elseif (is_file($this->file_path()))
            list($path, $ext) = array($this->file_path(), $this->file_ext);
        else
            return false;
        
        try {
            Moebooru\Resizer::resize($ext, $path, $this->tempfile_preview_path(), $size, 85);
        } catch (Exception $e) {
            $this->errors()->add("preview", "couldn't be generated (".$e->getMessage().")");
            $this->delete_tempfile();
            return false;
        }
        
        $this->actual_preview_width = $this->raw_preview_dimensions()[0];
        $this->actual_preview_height = $this->raw_preview_dimensions()[1];
        $this->preview_width = $this->preview_dimensions()[0];
        $this->preview_height = $this->preview_dimensions()[1];
        
        return true;
    }

    # If the JPEG version needs to be generated (or regenerated), output it to tempfile_jpeg_path.    On
    # error, return; false; on success or no-op, return; true.
    protected function generate_jpeg($force_regen = false)
    {
        if ($this->gif() || !$this->image()) return true;
        elseif (!CONFIG()->jpeg_enable) return true;
        elseif (!$this->width && !$this->height) return true;
        
        # Only generate JPEGs for PNGs.    Don't do it for files that are already JPEGs; we'll just add
        # artifacts and/or make the file bigger.    Don't do it for GIFs; they're usually animated.
        if ($this->file_ext != "png") return true;

        # We can generate the image during upload or offline.    Use tempfile_path
        #- if it exists, otherwise use file_path.
        $path = $this->tempfile_path();
        // path = file_path unless File.exists?(path)
        // unless File.exists?(path)
            // record_errors.add(:file, "not found")
            // return false
        // end
        
        # If we already have the image, don't regenerate it.
        if (!$force_regen && ctype_digit((string)$this->jpeg_width))
            return true;
        
        $size = Moebooru\Resizer::reduce_to(array('width' => $this->width, 'height' => $this->height), array('width' => CONFIG()->jpeg_width, 'height' => CONFIG()->jpeg_height), CONFIG()->jpeg_ratio);
        try {
            Moebooru\Resizer::resize($this->file_ext, $path, $this->tempfile_jpeg_path(), $size, CONFIG()->jpeg_quality['max']);
        } catch (Moebooru\Exception\ResizeErrorException $e) {
            $this->errors()->add("jpeg", "couldn't be created: {$e->getMessage()}");
            return false;
        }
        
        $this->jpeg_width = $size['width'];
        $this->jpeg_height = $size['height'];
        $this->jpeg_size = filesize($this->tempfile_jpeg_path());
        
        # iTODO: enable crc32 for jpg.
        $crc32_accum = 0;

        return true;
    }

    # Returns true if the post has a sample image.
    public function has_sample()
    {
        return !empty($this->sample_size);
    }

    # Returns true if the post has a sample image, and we're going to use it.
    public function use_sample($user = null)
    {
        if (!$user)
            $user = current_user();
        
        if ($user && !$user->show_samples)
            return false;
        else
            return CONFIG()->image_samples && $this->has_sample();
    }

    public function get_file_image($user = null)
    {
        return array(
            'url'    => $this->file_url(),
            'ext'    => $this->file_ext,
            'size'   => $this->file_size,
            'width'  => $this->width,
            'height' => $this->height
        );
    }
    
    public function get_file_jpeg($user = null)
    {
        if ($this->status == "deleted" or !$this->use_jpeg($user))
            return $this->get_file_image($user);

        return array(
            'url'    => $this->store_jpeg_url(),
            'size'   => $this->jpeg_size,
            'ext'    => "jpg",
            'width'  => $this->jpeg_width,
            'height' => $this->jpeg_height
        );
    }
    
    public function get_file_sample($user = null)
    {
        if ($this->status == "deleted" or !$this->use_sample($user))
            return $this->get_file_jpeg($user);
        
        return array(
            'url'    => $this->store_sample_url(),
            'size'   => $this->sample_size,
            'ext'    => "jpg",
            'width'  => $this->sample_width,
            'height' => $this->sample_height
        );
    }

    public function sample_url($user = null)
    {
        return $this->get_file_sample($user)['url'];
    }

    public function get_sample_width($user = null)
    {
        return $this->get_file_sample($user)['width'];
    }
    
    public function get_sample_height($user = null)
    {
        return $this->get_file_sample($user)['height'];
    }
    
    public function has_jpeg()
    {
        return $this->jpeg_size;
    }
    
    public function use_jpeg($user = null)
    {
        return CONFIG()->jpeg_enable && $this->has_jpeg();
    }
    
    public function jpeg_url($user = null)
    {
        return $this->get_file_jpeg($user)['url'];
    }
    
    # Filename parsing methods
    protected function get_tags_from_filename()
    {
        if ($tags = CONFIG()->filename_to_tags($this->tempfile_name)) {
            if ($this->tags())
                $tags = array_unique(array_filter(array_merge($this->tags(), $tags)));
            $this->new_tags = array_unique(array_merge($tags, $this->new_tags));
        }
    }
    
    protected function get_source_from_filename()
    {
        if ($source = CONFIG()->filename_to_source($this->tempfile_name)) {
            $this->source = $source;
        }
    }
}
