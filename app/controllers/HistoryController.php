<?php
use Moebooru\Versioning as Versioned;

class HistoryController extends ApplicationController
{
    public function index()
    {
        $this->helper('Tag', 'Post');
        
        $search = trim($this->params()->search) ?: "";

        $q = [
            'keywords' => []
        ];

        if ($search) {
            foreach (explode(' ', $search) as $s) {
                if (preg_match('/^(.+?):(.*)/', $s, $m)) {
                    $search_type = $m[1];
                    $param = $m[2];

                    if ($search_type == "user") {
                        $q['user'] = $param;
                    } elseif ($search_type == "change") {
                        $q['change'] = (int)$param;
                    } elseif ($search_type == "type") {
                        $q['type'] = $param;
                    } elseif ($search_type == "id") {
                        $q['id'] = (int)$param;
                    } elseif ($search_type == "field") {
                        # 'type' must also be set for this to be used.
                        $q['field'] = $param;
                    } else {
                        # pool'123'
                        $q['type'] = $search_type;
                        $q['id'] = (int)$param;
                    }
                } else {
                    $q['keywords'][] = $s;
                }
            }
        }

        $inflector = Rails::services()->get('inflector');
        
        if (!empty($q['type'])) {
            $q['type'] = $inflector->pluralize($q['type']);
        }
        if (!empty($q['inner_type'])) {
            $q['inner_type'] = $inflector->pluralize($q['inner_type']);
        }

        # If notes'id' has been specified, search using the inner key in history_changes
        # rather than the grouping table in histories.    We don't expose this in general.
        # Searching based on hc.table_name without specifying an ID is slow, and the
        # details here shouldn't be visible anyway.
        if (array_key_exists('type', $q) and array_key_exists('id', $q) and $q['type'] == "notes") {
            $q['inner_type'] = $q['type'];
            $q['remote_id'] = $q['id'];

            unset($q['type']);
            unset($q['id']);
        }

        $query = History::none();

        $hc_conds = [];
        $hc_cond_params = [];

        if (!empty($q['user'])) {
            $user = User::where('name', $q['user'])->first();
            if ($user) {
                $query->where("histories.user_id = ?", $user->id);
            } else {
                $query->where("false");
            }
        }

        if (!empty($q['id'])) {
            $query->where("group_by_id = ?", $q['id']);
        }

        if (!empty($q['type'])) {
            $query->where("group_by_table = ?", $q['type']);
        }

        if (!empty($q['change'])) {
            $query->where("histories.id = ?", $q['change']);
        }

        if (!empty($q['inner_type'])) {
            $q['inner_type'] = $inflector->pluralize($q['inner_type']);

            $hc_conds[] = "hc.table_name = ?";
            $hc_cond_params[] = $q['inner_type'];
        }

        if (!empty($q['remote_id'])) {
            $hc_conds[] = "hc.remote_id = ?";
            $hc_cond_params[] = $q['remote_id'];
        }

        if ($q['keywords']) {
            $hc_conds[] = 'hc.value LIKE ?';
            $hc_cond_params[] = '%' . implode('%', $q['keywords']) . '%';
        }

        if (!empty($q['field']) and !empty($q['type'])) {
            # Look up a particular field change, eg. "type'posts' field'rating'".
            # XXX: The WHERE id IN (SELECT id...) used to implement this is slow when we don't have
            # anything } else { filtering the results.
            $field = $q['field'];
            $table = $q['type'];

            # For convenience:
            if ($field == "tags") {
                $field = "cached_tags";
            }

            # Look up the named class.
            if (!Versioned::is_versioned_class($cls)) {
                $query->where("false");
            } else {
                $hc_conds[] = "hc.column_name = ?";
                $hc_cond_params[] = $field;

                # A changes that has no previous value is the initial value for that object.    Don't show
                # these changes unless they're different from the default for that field.
                list ($default_value, $has_default) = $cls::versioning()->get_versioned_default($field);
                if ($has_default) {
                    $hc_conds[] = "(hc.previous_id IS NOT NULL OR value <> ?)";
                    $hc_cond_params[] = $default_value;
                }
            }
        }
        
        if ($hc_conds) {
            array_unshift($hc_cond_params, 'histories.id IN (SELECT history_id FROM history_changes hc JOIN histories h ON (hc.history_id = h.id) WHERE ' . implode(" AND ", $hc_conds) . ')');
            call_user_func_array([$query, 'where'], $hc_cond_params);
        }

        if (!empty($q['type']) and empty($q['change'])) {
            $this->type = $q['type'];
        } else {
            $this->type = "all";
        }

        # 'specific_history' => showing only one history
        # 'specific_table' => showing changes only for a particular table
        # 'show_all_tags' => don't omit post tags that didn't change
        $this->options = [
            'show_all_tags' => $this->params()->show_all_tags == "1",
            'specific_object' => (!empty($q['type']) and !empty($q['id'])),
            'specific_history' => !empty($q['change']),
        ];
        
        $this->options['show_name'] = false;
        if ($this->type != "all") {
            $cn = $inflector->classify($this->type);
            try {
                if (Versioned::is_versioned_class($cls) && class_exists($cn)) {
                    $obj = new $cn();
                    if (method_exists($obj, "pretty_name"))
                        $this->options['show_name'] = true;
                }
            } catch (Rails\Loader\Exception\ExceptionInterface $e) {
            }
        }

        $this->changes = $query->order("histories.id DESC")
                               ->select('*')
                               ->paginate($this->page_number(), 20);

        # If we're searching for a specific change, force the display to the
        # type of the change we found.
        if (!empty($q['change']) && $this->changes->any()) {
            $this->type = $inflector->pluralize($this->changes[0]->group_by_table);
        }

        $this->render(['action' => 'index']);
    }

    public function undo()
    {
        $ids = explode(',', $this->params()->id);

        $this->changes = HistoryChange::emptyCollection();
        foreach ($ids as $id)
            $this->changes[] = HistoryChange::where("id = ?", $id)->first();

        $histories = [];
        $total_histories = 0;
        foreach ($this->changes as $change) {
            if (isset($histories[$change->history_id]))
                continue;
            
            $histories[$change->history_id] = true;
            $total_histories += 1;
        }

        if ($total_histories > 1 && !$this->current_user->is_privileged_or_higher()) {
            $this->respond_to_error("Only privileged users can undo more than one change at once", ['status' => 403]);
            return;
        }

        $errors = [];
        History::undo($this->changes, $this->current_user, $this->params()->redo == "1", $errors);

        $error_texts = [];
        $successful = 0;
        $failed = 0;
        foreach ($this->changes as $change) {
            $objectHash = spl_object_hash($change);
            if (empty($errors[$objectHash])) {
                $successful += 1;
                continue;
            }
            $failed += 1;

            switch ($errors[$objectHash]) {
                case 'denied':
                    $error_texts[] = "Some changes were not made because you do not have access to make them.";
                    break;
            }
        }
        $error_texts = array_unique($error_texts);

        $this->respond_to_success("Changes made.", ['action' => "index"], ['api' => ['successful' => $successful, 'failed' => $failed, 'errors' => $error_texts]]);
    }

    protected function filters()
    {
        return ['before' => ['member_only' => ['only' => ['undo']]]];
    }
}
