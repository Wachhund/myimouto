<?php

class TicketController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'member_only' => ['only' => ['index', 'create', 'show']],
                'mod_only' => ['only' => ['update', 'claim', 'unclaim']],
            ],
        ];
    }

    public function index()
    {
        $query = Ticket::where('true');

        // Apply per-type visibility filter (AC-4)
        $query = $this->apply_visibility_filter($query);

        // Status filter
        if ($this->params()->status && in_array($this->params()->status, Ticket::VALID_STATUSES, true)) {
            $query->where('status = ?', $this->params()->status);
        }

        // Type filter
        if ($this->params()->qtype && in_array($this->params()->qtype, Ticket::VALID_QTYPES, true)) {
            $query->where('qtype = ?', $this->params()->qtype);
        }

        // Creator filter (mod only)
        if (current_user()->is_mod_or_higher() && $this->params()->creator_id) {
            $query->where('creator_id = ?', (int) $this->params()->creator_id);
        }

        $this->tickets = $query->order('updated_at DESC, created_at DESC')->paginate($this->page_number(), 25);

        $this->respondTo([
            'html',
            'json' => function () {
                $data = [];
                foreach ($this->tickets as $ticket) {
                    $data[] = $ticket->api_attributes();
                }
                $this->render(['json' => $data]);
            },
        ]);
    }

    public function show()
    {
        $this->ticket = $this->find_ticket_from_params();
        if (!$this->ticket) {
            return;
        }

        // Per-type visibility check (AC-4) — return 404 to avoid existence leak
        if (!$this->can_view_ticket($this->ticket)) {
            $this->respond_to_error('Ticket not found', ['#index'], ['status' => 404]);
            return;
        }

        $this->set_title('Ticket #' . $this->ticket->id);

        $this->respondTo([
            'html',
            'json' => function () {
                $this->render(['json' => $this->ticket->api_attributes()]);
            },
        ]);
    }

    public function create()
    {
        if (!$this->request()->isPost()) {
            $this->ticket = new Ticket();
            $this->ticket->qtype = $this->params()->qtype ?: 'post';
            $this->ticket->model_type = $this->params()->model_type;
            $this->ticket->model_id = $this->params()->model_id;
            return;
        }

        if (!Ticket::can_create_ticket(current_user())) {
            $this->respond_to_error(
                'You have too many pending tickets (max ' . Ticket::MAX_PENDING_PER_USER . ')',
                ['#index'],
                ['status' => 421],
            );
            return;
        }

        $ticket_params = is_array($this->params()->ticket) ? $this->params()->ticket : [];

        $attrs = [
            'creator_id' => current_user()->id,
            'qtype' => $ticket_params['qtype'] ?? ($this->params()->qtype ?: 'post'),
            'model_type' => $ticket_params['model_type'] ?? $this->params()->model_type,
            'model_id' => $ticket_params['model_id'] ?? $this->params()->model_id,
            'reason' => $ticket_params['reason'] ?? $this->params()->reason,
            'accused_id' => $ticket_params['accused_id'] ?? $this->params()->accused_id,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Duplicate detection (AC-3) — Mod+ can bypass
        if (!current_user()->is_mod_or_higher()) {
            $existing = Ticket::find_duplicate(
                (int) current_user()->id,
                $attrs['model_type'],
                $attrs['model_id'],
            );
            if ($existing) {
                $this->respondTo([
                    'html' => function () use ($existing) {
                        $this->notice("You already have an open ticket for this item (Ticket #{$existing->id}).");
                        $this->redirectTo(['#create']);
                    },
                    'json' => function () use ($existing) {
                        $this->render([
                            'json' => [
                                'success' => false,
                                'reason' => 'duplicate',
                                'existing_ticket_id' => (int) $existing->id,
                            ],
                            'status' => 409,
                        ]);
                    },
                ]);
                return;
            }
        }

        $this->ticket = Ticket::create($attrs);

        if ($this->ticket->errors()->blank()) {
            $this->respond_to_success('Ticket created', ['#show', 'id' => $this->ticket->id], [
                'api' => ['ticket' => $this->ticket->api_attributes()],
            ]);
            return;
        }

        $this->respond_to_error($this->ticket, ['#create']);
    }

    public function update()
    {
        $this->ticket = $this->find_ticket_from_params();
        if (!$this->ticket) {
            return;
        }

        $ticket_params = is_array($this->params()->ticket) ? $this->params()->ticket : [];
        $response = $ticket_params['response'] ?? ($this->params()->response ?: '');
        $status = $ticket_params['status'] ?? ($this->params()->status ?: null);

        if ($status !== null && in_array($status, [Ticket::STATUS_APPROVED, Ticket::STATUS_REJECTED], true)) {
            $result = $this->ticket->resolve(current_user(), $response, $status);
        } else {
            $result = $this->ticket->update_response(current_user(), $response, $status);
        }

        if ($result) {
            $this->respond_to_success('Ticket updated', ['#show', 'id' => $this->ticket->id], [
                'api' => ['ticket' => $this->ticket->api_attributes()],
            ]);
        } else {
            $this->respond_to_error('Failed to update ticket', ['#show', 'id' => $this->ticket->id]);
        }
    }

    public function claim()
    {
        $this->ticket = $this->find_ticket_from_params();
        if (!$this->ticket) {
            return;
        }

        $result = $this->ticket->claim(current_user());

        if ($result['success']) {
            $this->respond_to_success('Ticket claimed', ['#show', 'id' => $this->ticket->id], [
                'api' => ['ticket' => $this->ticket->api_attributes()],
            ]);
        } elseif ($result['reason'] === 'already_claimed') {
            $claimant_name = htmlspecialchars($result['claimant'] ?: 'another staff member', ENT_QUOTES, 'UTF-8');
            $this->respondTo([
                'html' => function () use ($claimant_name) {
                    $this->notice("Error: This ticket has already been claimed by {$claimant_name}.");
                    $this->redirectTo(['#show', 'id' => $this->ticket->id]);
                },
                'json' => function () use ($result) {
                    $this->render([
                        'json' => [
                            'success' => false,
                            'reason' => 'already_claimed',
                            'claimant' => $result['claimant'],
                        ],
                        'status' => 409,
                    ]);
                },
            ]);
        } else {
            $this->respond_to_error('Cannot claim this ticket', ['#show', 'id' => $this->ticket->id]);
        }
    }

    public function unclaim()
    {
        $this->ticket = $this->find_ticket_from_params();
        if (!$this->ticket) {
            return;
        }

        if ($this->ticket->unclaim()) {
            $this->respond_to_success('Ticket unclaimed', ['#show', 'id' => $this->ticket->id], [
                'api' => ['ticket' => $this->ticket->api_attributes()],
            ]);
        } else {
            $this->respond_to_error('Cannot unclaim this ticket', ['#show', 'id' => $this->ticket->id]);
        }
    }

    protected function find_ticket_from_params()
    {
        $id = (int) $this->params()->id;
        if ($id <= 0) {
            $this->respond_to_error('Ticket not found', ['#index'], ['status' => 404]);
            return null;
        }

        try {
            return Ticket::find($id);
        } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
            $this->respond_to_error('Ticket not found', ['#index'], ['status' => 404]);
            return null;
        }
    }

    /**
     * Apply per-type visibility filter to a ticket query (AC-4).
     *
     * Visibility rules:
     *   - qtype='dmail': creator OR Mod+
     *   - qtype='post': creator OR Janitor+
     *   - all others: creator OR Mod+
     */
    protected function apply_visibility_filter($query)
    {
        $user = current_user();

        // Mod+ see everything
        if ($user->is_mod_or_higher()) {
            return $query;
        }

        // Janitor+ see own tickets + post-type tickets
        if (!$user->is_anonymous() && $user->is_janitor_or_higher()) {
            $query->where("(creator_id = ? OR qtype = 'post')", (int) $user->id);
            return $query;
        }

        // Logged-in members see only their own tickets
        if (!$user->is_anonymous()) {
            $query->where('creator_id = ?', (int) $user->id);
            return $query;
        }

        // Anonymous users — no tickets visible
        $query->where('FALSE');
        return $query;
    }

    /**
     * Check if the current user can view a specific ticket (AC-4).
     * Used in show() — returns false if access denied.
     */
    protected function can_view_ticket($ticket)
    {
        $user = current_user();

        // Mod+ see everything
        if ($user->is_mod_or_higher()) {
            return true;
        }

        // Creator can always see their own ticket
        if (!$user->is_anonymous() && (int) $ticket->creator_id === (int) $user->id) {
            return true;
        }

        // Janitor+ can see post-type tickets
        if (!$user->is_anonymous() && $user->is_janitor_or_higher() && (string) $ticket->qtype === 'post') {
            return true;
        }

        return false;
    }
}
