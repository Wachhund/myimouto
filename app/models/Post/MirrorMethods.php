<?php

// class MirrorError extends Exception
// {}

trait PostMirrorMethods
{
    # On :normal, upload all files to all mirrors except :previews_only ones.
    # On :previews_only, upload previews to previews_only mirrors.
    // public function upload_to_mirrors_internal(mode=:normal)
    // {
    // files_to_copy = array()        if ((mode != :previews_only then) {) {
    // files_to_copy << self.file_path
    // files_to_copy << self.sample_path if self.has_sample?
    // files_to_copy << self.jpeg_path if self.has_jpeg?
    // }
    // files_to_copy << self.preview_path if self.image?
    // files_to_copy = files_to_copy.uniq

    // # CONFIG[:data_dir] is equivalent to our local_base.
    // local_base = "#{Rails.root}/public/data/"

    // dirs = array()
    // files_to_copy.each { |file|
    // dirs << File.dirname(file[local_base.length, file.length])
    // }

    // options = array()
    // if (mode == :previews_only then) {
    // options[:previews_only] = true
    // }

    // Mirrors.create_mirror_paths(dirs, options)
    // files_to_copy.each { |file|
    // Mirrors.copy_file_to_mirrors(file, options)
    // }
    // }

    // public function upload_to_mirrors()
    // {
    // return; if is_warehoused
    // return; if self.status == "deleted"

    // begin
    // upload_to_mirrors_internal(:normal)
    // upload_to_mirrors_internal(:previews_only)
    // rescue MirrorError => e
    // # The post might be deleted while it's uploading.    Check the post status after
    // # an error.
    // self.reload
    // raise if self.status != "deleted"
    // return;        }

    // # This might take a while.    Rather than hold a transaction, just reload the post
    // # after uploading.
    // self.reload
    // self.updateAttributes('is_warehoused' => true)
    // }
}
