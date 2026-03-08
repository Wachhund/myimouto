<?php
class NoteController extends ApplicationController
{
    // layout 'default', 'only' => [:index, :history, :search]
    // helper :post
    protected function init()
    {
        $this->helper('Post');
    }
    
    protected function filters()
    {
        return [
            'before' => [
                'post_member_only' => ['only' => ['destroy', 'update', 'revert']]
            ]
        ];
    }
    
    public function search()
    {
         if ($this->params()->query) {
            $query = '%' . implode('%', array_filter(explode(' ', $this->params()->query))) . '%';
            $this->notes = Note::where("body LIKE ?", $query)->order("id asc")->paginate($this->page_number(), 25);
            $this->respond_to_list("notes");
        } else
            $this->notes = new Rails\ActiveRecord\Collection();
    }

    public function index()
    {
        $this->set_title('Notes');
        
        if ($this->params()->post_id) {
            $this->posts = Post::where("id = ?", $this->params()->post_id)->order("last_noted_at DESC")->paginate($this->page_number(), 100);
        } else {
            $this->posts = Post::where("last_noted_at IS NOT NULL")->order("last_noted_at DESC")->paginate($this->page_number(), 16);
        }
        # iTODO:
        $this->respondTo([
            'html',
            'xml' => function() {
                $notes = new Rails\ActiveRecord\Collection();
                foreach ($this->posts as $post)
                    $notes->merge($post->notes);
                $this->render(['xml' => $notes, 'root' => "notes"]);
            },
            'json' => function() {
                 // {render :json => @posts.map {|x| x.notes}.flatten.to_json}
            }
        ]);
    }

    public function history()
    {
        $this->set_title('Note History');
        
        if ($this->params()->id) {
            $this->notes = NoteVersion::where("note_id = ?", (int)$this->params()->id)->order("id DESC")->paginate($this->page_number(), 25);
        } elseif ($this->params()->post_id) {
            $this->notes = NoteVersion::where("post_id = ?", (int)$this->params()->post_id)->order("id DESC")->paginate($this->page_number(), 50);
        } elseif ($this->params()->user_id) {
            $this->notes = NoteVersion::where("user_id = ?", (int)$this->params()->user_id)->order("id DESC")->paginate($this->page_number(), 50);
        } else {
            $this->notes = NoteVersion::order("id DESC")->paginate($this->page_number(), 25);
        }

        $this->respond_to_list("notes");
    }

    // public function revert()
    // {
        // $note = Note::find($this->params()->id);

        // if ($note->is_locked()) {
            // $this->respond_to_error("Post is locked", ['#history', 'id' => $note->id], 'status' => 422);
            // return;
        // }

        // $note->revert_to($this->params()->version):
        // $note->ip_addr = $this->request()->remote_ip():
        // $note->user_id = current_user()->id:

        // if ($note->save()) {
            // $this->respond_to_success("Note reverted", ['#history', 'id' => $note->id]);
        // } else {
            // $this->render_error($note);
        // }
    // }

    public function update()
    {
        if (isset($this->params()->note['post_id'])) {
            $note = new Note(['post_id' => $this->params()->note['post_id']]);
        } else {
            $note = Note::find($this->params()->id);
        }

         if ($note->is_locked()) {
            $this->respond_to_error("Post is locked", array('post#show', 'id' => $note->post_id), ['status' => 422]);
            return;
        }

        $permitted = array_intersect_key($this->params()->note ?: [], array_flip(['body', 'x', 'y', 'width', 'height']));
        $note->assignAttributes($permitted);
        $note->user_id = current_user()->id;
        $note->ip_addr = $this->request()->remoteIp();
        # iTODO:
        if ($note->save()) {
            $this->respond_to_success("Note updated", '#index', ['api' => ['new_id' => $note->id, 'old_id' => (int)$this->params()->id, 'formatted_body' => $note->formatted_body()]]);
            // ActionController::Base.helpers.sanitize(note.formatted_body)]]);
        } else {
            $this->respond_to_error($note, ['post#show', 'id' => $note->post_id]);
        }
    }
}