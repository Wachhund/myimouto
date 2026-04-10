<?php

class UserNameChangeRequestController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'member_only' => ['only' => ['blank', 'create', 'show', 'cancel']],
                'mod_only' => ['only' => ['index', 'approve', 'reject']],
            ],
        ];
    }

    /**
     * Staff queue: paginated list of username change requests.
     */
    public function index()
    {
        $query = UserNameChangeRequest::order('created_at DESC');

        $status = $this->normalize_status($this->params()->status);
        if ($status) {
            $query = $query->where('status = ?', $status);
        }

        $this->requests = $query->paginate($this->page_number(), 25);

        $this->respondTo([
            'html',
            'json' => function () {
                $data = [];
                foreach ($this->requests as $req) {
                    $data[] = $req->api_attributes();
                }
                $this->render(['json' => $data]);
            },
        ]);
    }

    /**
     * Show a single request. Users can view their own, mods can view all.
     */
    public function show()
    {
        $this->change_request = $this->find_request_from_params();
        if (!$this->change_request) {
            return;
        }

        # Users can only see their own requests; mods can see all.
        if (!current_user()->is_mod_or_higher()
            && (int) $this->change_request->user_id !== (int) current_user()->id
        ) {
            $this->access_denied();
            return;
        }

        $this->respondTo([
            'html',
            'json' => function () {
                $this->render(['json' => $this->change_request->api_attributes()]);
            },
        ]);
    }

    /**
     * New request form (mapped as 'new' in routes, named 'blank' here because 'new' is reserved).
     */
    public function blank()
    {
        $this->eligibility = UserNameChangeRequest::can_request(current_user());
        $this->change_request = new UserNameChangeRequest();
    }

    /**
     * Create a new username change request.
     */
    public function create()
    {
        $eligibility = UserNameChangeRequest::can_request(current_user());
        if (!$eligibility['allowed']) {
            $this->respond_to_error($eligibility['reason'], ['user#show', 'id' => current_user()->id]);
            return;
        }

        $this->change_request = new UserNameChangeRequest();
        $this->change_request->user_id = current_user()->id;
        $this->change_request->old_name = current_user()->name;
        $this->change_request->desired_name = $this->params()->desired_name ?: ($this->params()->user_name_change_request ? $this->params()->user_name_change_request['desired_name'] : '');
        $this->change_request->reason = $this->params()->reason ?: ($this->params()->user_name_change_request ? $this->params()->user_name_change_request['reason'] : null);
        $this->change_request->status = UserNameChangeRequest::STATUS_PENDING;
        $this->change_request->created_at = date('Y-m-d H:i:s');

        if ($this->change_request->save()) {
            $this->respond_to_success(
                'Username change request submitted',
                ['user_name_change_request#show', 'id' => $this->change_request->id],
                ['api' => ['request' => $this->change_request->api_attributes()]],
            );
        } else {
            $this->respond_to_error($this->change_request, ['user_name_change_request#blank']);
        }
    }

    /**
     * Cancel own pending request.
     */
    public function cancel()
    {
        $this->change_request = $this->find_request_from_params();
        if (!$this->change_request) {
            return;
        }

        if ((int) $this->change_request->user_id !== (int) current_user()->id) {
            $this->access_denied();
            return;
        }

        if ($this->change_request->cancel()) {
            $this->respond_to_success(
                'Username change request cancelled',
                ['user#show', 'id' => current_user()->id],
            );
        } else {
            $this->respond_to_error(
                $this->change_request->errors()->fullMessages(', '),
                ['user_name_change_request#show', 'id' => $this->change_request->id],
            );
        }
    }

    /**
     * Approve a pending request. Mod+ only.
     */
    public function approve()
    {
        $this->change_request = $this->find_request_from_params();
        if (!$this->change_request) {
            return;
        }

        if ($this->change_request->approve(current_user())) {
            $this->respond_to_success(
                'Username change approved',
                ['user_name_change_request#index'],
            );
        } else {
            $this->respond_to_error(
                $this->change_request->errors()->fullMessages(', '),
                ['user_name_change_request#index'],
            );
        }
    }

    /**
     * Reject a pending request. Mod+ only.
     */
    public function reject()
    {
        $this->change_request = $this->find_request_from_params();
        if (!$this->change_request) {
            return;
        }

        $reason = trim((string) $this->params()->staff_reason);
        if ($reason === '') {
            $reason = null;
        }

        if ($this->change_request->reject(current_user(), $reason)) {
            $this->respond_to_success(
                'Username change rejected',
                ['user_name_change_request#index'],
            );
        } else {
            $this->respond_to_error(
                $this->change_request->errors()->fullMessages(', '),
                ['user_name_change_request#index'],
            );
        }
    }

    protected function find_request_from_params()
    {
        $id = (int) $this->params()->id;
        if ($id <= 0) {
            $this->respond_to_error('Request not found', ['user#show', 'id' => current_user()->id], ['status' => 404]);
            return null;
        }

        try {
            return UserNameChangeRequest::find($id);
        } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
            $this->respond_to_error('Request not found', ['user#show', 'id' => current_user()->id], ['status' => 404]);
            return null;
        }
    }

    protected function normalize_status($status)
    {
        $status = strtolower(trim((string) $status));
        if (in_array($status, [
            UserNameChangeRequest::STATUS_PENDING,
            UserNameChangeRequest::STATUS_APPROVED,
            UserNameChangeRequest::STATUS_REJECTED,
            UserNameChangeRequest::STATUS_CANCELLED,
        ], true)) {
            return $status;
        }
        return null;
    }
}
