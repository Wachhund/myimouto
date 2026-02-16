<?php
class InlineController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'ensure_inline_module_available',
                'member_only' => ['only' => ['create', 'copy']]
            ]
        ];
    }

    protected function init()
    {
        $this->helper('Inline');
    }
    
    public function create()
    {
        # If this user already has an inline with no images, use it.
        $inline = Inline::where("(SELECT count(*) FROM inline_images WHERE inline_images.inline_id = inlines.id) = 0 AND user_id = ?", current_user()->id)->first();
        
        if (!$inline) {
            $inline = Inline::create(['user_id' => current_user()->id]);
        }
        
        $this->redirectTo(['#edit', 'id' => $inline->id]);
    }
    
    public function index()
    {
        $query = Inline::none();
        if (!current_user()->is_anonymous()) {
            $query->order('user_id = ' . current_user()->id . ' DESC');
        }
        $query->order('created_at desc');
        
        $this->inlines = $query->paginate($this->page_number(), 20);
        
        $this->respond_to_list('inlines');
    }
    
    public function delete()
    {
        $inline = Inline::find($this->params()->id);
        
        if (!current_user()->has_permission($inline)) {
            $this->access_denied();
            return;
        }
        
        $inline->destroy();
        $this->respond_to_success('Image group deleted', '#index');
    }
    
    public function addImage()
    {
        $inline = Inline::find($this->params()->id);
        
        if (!current_user()->has_permission($inline)) {
            $this->access_denied();
            return;
        }
        
        if ($this->request()->isPost()) {
            $new_image = InlineImage::create(array_merge($this->params()->image ?: [], ['inline_id' => $inline->id, 'file' => $this->params()->files()->image['file']]));
            if ($new_image->errors()->any()) {
                $this->respond_to_error($new_image, ['#edit', 'id' => $inline->id]);
                return;
            }
        
            $this->redirectTo(['#edit', 'id' => $inline->id]);
        }
    }
    
    public function deleteImage()
    {
        $image = InlineImage::find($this->params()->id);
        
        $inline = $image->inline;
        
        if (!current_user()->has_permission($inline)) {
            $this->access_denied();
            return;
        }
        
        $image->destroy();
        $this->redirectTo(['#edit', 'id' => $inline->id]);
    }
    
    public function update()
    {
        $inline = Inline::find($this->params()->id);
        
        if (!current_user()->has_permission($inline)) {
            $this->access_denied();
            return;
        }
        
        $inline->updateAttributes($this->params()->inline);
        
        $images = $this->params()->image ?: [];
        foreach ($images as $id => $p) {
            $image = InlineImage::where('id = ? AND inline_id = ?', $id, $inline->id)->first();
            if (isset($p['description'])) {
                $image->description = $p['description'];
            }
            if (isset($p['sequence'])) {
                $image->sequence = $p['sequence'];
            }
            if ($image->changedAttributes()) {
                $image->save();
            }
        }
        
        $inline->reload();
        $inline->renumber_sequences();
        
        $this->notice('Image updated');
        $this->redirectTo(['#edit', 'id' => $inline->id]);
    }
    
    # Create a copy of an inline image and all of its images.  Allow copying from images
    # owned by someone else.
    public function copy()
    {
        $inline = Inline::find($this->params()->id);
        
        $new_inline = Inline::create([
            'user_id' => current_user()->id,
            'description' => $inline->description
        ]);
        
        foreach ($inline->inline_images as $image) {
            $new_attributes = array_merge($image->attributes(), ['inline_id' => $new_inline->id]);
            unset($new_attributes['id']);
            $new_image = InlineImage::create($new_attributes);
        }
        
        $this->respond_to_success('Image copied', ['#edit', 'id' => $new_inline->id]);
    }
    
    public function edit()
    {
        $this->inline = Inline::find($this->params()->id);
    }
    
    public function crop()
    {
        $this->inline = Inline::find($this->params()->id);
        $image        = $this->inline->inline_images->toArray();
        $this->image  = array_shift($image);
        if (!$this->image) {
            throw new Rails\ActiveRecord\Exception\RecordNotFoundException(
                "Inline images set #" . $this->inline->id . " doesn't have inline images"
            );
        }
        if (!current_user()->has_permission($this->inline)) {
            $this->access_denied();
            return;
        }
        
        if ($this->request()->isPost()) {
            if ($this->inline->crop($this->params()->toArray())) {
                $this->redirectTo(['#edit', 'id' => $this->inline->id]);
            } else {
                $this->respond_to_error($this->inline, ['#edit', 'id' => $this->inline->id]);
            }
        }
    }

    protected function ensure_inline_module_available()
    {
        if (Inline::module_tables_available()) {
            return true;
        }

        $this->respond_to_error(
            'Inline module unavailable: required database tables are missing. Run db:migrate.',
            'static#more',
            ['status' => 503]
        );
        return false;
    }
}
