<?php
class Inline extends Rails\ActiveRecord\Base
{
    static protected $module_tables_available = null;

    protected function associations()
    {
        return [
            'belongs_to' => [
                'user'
            ],
            'has_many' => [
                'inline_images' => [function() { $this->order('sequence'); } /*Not yet supported: 'dependent' => 'destroy'*/, 'class_name' => 'InlineImage']
            ]
        ];
    }

    static public function module_tables_available()
    {
        if (self::$module_tables_available !== null) {
            return self::$module_tables_available;
        }

        try {
            $connection = self::connection();
            self::$module_tables_available =
                $connection->tableExists('inlines') &&
                $connection->tableExists('inline_images');
        } catch (\Throwable $e) {
            Rails::log()->exception($e);
            self::$module_tables_available = false;
        }

        return self::$module_tables_available;
    }

    static public function mark_module_unavailable()
    {
        self::$module_tables_available = false;
    }

    static public function missing_table_exception(\Throwable $e)
    {
        $message = strtolower($e->getMessage());
        return strpos($message, 'base table or view not found') !== false ||
               strpos($message, 'no such table') !== false;
    }
    
    protected function callbacks()
    {
        return [
            'before_destroy' => [
                'destroy_inline_images'
            ]
        ];
    }
    
    protected function destroy_inline_images()
    {
        foreach ($this->inline_images as $i) {
            $i->destroy();
        }
    }
    
    # Sequence numbers must start at 1 and increase monotonically, to keep the UI simple.
    # If we've been given sequences with gaps or duplicates, sanitize them.
    public function renumber_sequences()
    {
        $first = 1;
        foreach ($this->inline_images as $image) {
            $image->sequence = $first;
            $image->save();
            $first++;
        }
    }
    
    public function pretty_name()
    {
        return "Inline #" . $this->id;
    }
    
    public function crop(array $params = [])
    {
        # MI: set default params
        $params = array_merge([
            'top'    => 0,
            'bottom' => 0,
            'left'   => 0,
            'right'  => 0,
        ], $params);
        
        if ($params['top']    < 0 or $params['top']    > 1 or
            $params['bottom'] < 0 or $params['bottom'] > 1 or
            $params['left']   < 0 or $params['left']   > 1 or
            $params['right']  < 0 or $params['right']  > 1 or
            $params['top']    >= $params['bottom'] or
            $params['left']   >= $params['right']
        ) {
            $this->errors()->add('parameter', 'error');
            return false;
        }
        
        $images = $this->inline_images;
        foreach ($images as $image) {
            # Create a new image with the same properties, crop this image into the new one,
            # and delete the old one.
            $new_image = new InlineImage([
                'description' => $image->description,
                'sequence'    => $image->sequence,
                'inline_id'   => $this->id,
                'file_ext'    => 'jpg'
            ]);
            $size = $this->reduce_and_crop($image->width, $image->height, $params);
            
            try {
                # Create one crop for the image, and InlineImage will create the sample and preview from that.
                Moebooru\Resizer::resize($image->file_ext, $image->file_path(), $new_image->tempfile_image_path(), $size, 95);
                chmod($new_image->tempfile_image_path(), 0775);
            } catch (Exception $e) {
                if (is_file($new_image->tempfile_image_path())) {
                    unlink($new_image->tempfile_image_path());
                }
                
                $this->errors()->add('crop', "couldn't be generated (" . $e->getMessage() . ")");
                return false;
            }
            
            $new_image->got_file();
            $new_image->save();
            $image->destroy();
        }
    }
    
    public function api_attributes()
    {
        return [
            'id'          => (int)$this->id,
            'description' => (string)$this->description,
            'user_id'     => (int)$this->user_id,
            'images'      => $this->inline_images->asJson()
        ];
    }
    
    public function asJson(array $params = [])
    {
        return $this->api_attributes();
    }
    
    protected function reduce_and_crop($image_width, $image_height, array $params = [])
    {
        $cropped_image_width  = $image_width  * ($params['right']  - $params['left']);
        $cropped_image_height = $image_height * ($params['bottom'] - $params['top']);
        
        $size = [];
        $size['width']  = $cropped_image_width;
        $size['height'] = $cropped_image_height;
        $size['crop_top'] = $image_height * $params['top'];
        $size['crop_bottom'] = $image_height * $params['bottom'];
        $size['crop_left'] = $image_width * $params['left'];
        $size['crop_right'] = $image_width * $params['right'];
        
        return $size;
    }
}
