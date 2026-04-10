<?php

class UserRecordController extends ApplicationController
{
    public function index()
    {
        $query = UserRecord::where('true');

        if ($this->params()->user_id) {
            // Use .where to ignore error when invalid user_id entered.
            // .first because .where returns array.
            $this->user = User::where('id = ?', $this->params()->user_id)->first();
            $query->where("user_id = ?", $this->params()->user_id);
        } else {
            $this->user = false;
        }

        // Category filter
        if ($this->params()->category && in_array($this->params()->category, ['positive', 'negative', 'neutral'])) {
            $query->where("category = ?", $this->params()->category);
        }

        // Non-admin users only see active (non-deleted) records
        if (!current_user()->is_admin()) {
            $query->where("is_deleted = 0");
        }

        $this->user_records = $query->order("created_at desc")->paginate($this->page_number(), 20);
    }

    public function create()
    {
        $this->user = User::find($this->params()->user_id);

        if ($this->request()->isPost()) {
            if ($this->user->id == $this->current_user->id) {
                $this->respond_to_error("You cannot create a record for yourself", ["#index", 'user_id' => $this->user->id]);
                return;
            }

            $category = 'negative';
            if ($this->params()->user_record && isset($this->params()->user_record['category'])
                && in_array($this->params()->user_record['category'], ['positive', 'negative', 'neutral'])) {
                $category = $this->params()->user_record['category'];
            }

            $attrs = [
                'user_id'     => $this->params()->user_id,
                'reported_by' => $this->current_user->id,
                'category'    => $category,
            ];
            if ($this->params()->user_record && isset($this->params()->user_record['body'])) {
                $attrs['body'] = $this->params()->user_record['body'];
            }

            $this->user_record = UserRecord::create($attrs);

            if ($this->user_record->errors()->blank()) {
                // Send dmail notification if requested
                if ($this->params()->send_dmail) {
                    $body = $this->current_user->name . " created a " . $category . " record for your account. View your records at /user_record?user_id=" . $this->user->id;
                    Dmail::create([
                        'from_id' => $this->current_user->id,
                        'to_id'   => $this->user->id,
                        'title'   => "Your user record has been updated",
                        'body'    => $body,
                    ]);
                }

                $this->notice("Record updated");
            }
            $this->redirectTo(["#index", 'user_id' => $this->user->id]);
        }
    }

    public function destroy()
    {
        $user_record = UserRecord::find($this->params()->id);

        if (current_user()->is_admin() || (current_user()->id == $user_record->reported_by)) {
            $user_record->soft_delete(current_user()->id);

            $this->respond_to_success("Record updated", ["#index", 'user_id' => $user_record->user_id]);
        } else {
            $this->access_denied();
        }
    }

    protected function filters()
    {
        return [
            'before' => [
                'mod_only' => ['only' => ['index', 'create', 'destroy']],
            ],
        ];
    }
}
