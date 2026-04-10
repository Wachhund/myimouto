<?php

class TagImplicationController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'member_only' => ['only' => ['create']],
            ],
        ];
    }

    public function create()
    {
        $tag_implication = $this->params()->tag_implication;
        $tag_implication['is_pending'] = true;
        $ti = new TagImplication($tag_implication);

        if ($ti->save()) {
            $this->notice("Tag implication created");
        } else {
            $this->notice("Error: " . $ti->errors()->fullMessages(', '));
        }

        $this->redirectTo("#index");
    }

    public function index()
    {
        $this->set_title("Tag Implications");

        if ($this->params()->commit == "Search Aliases") {
            $this->redirectTo(['tag_alias#index', 'query' => $this->params()->query]);
        }

        if ($this->params()->query) {
            $name = "%" . $this->params()->query . "%";
            $this->implications = TagImplication::where("predicate_id IN (SELECT id FROM tags WHERE name LIKE ?) OR consequent_id IN (SELECT id FROM tags WHERE name LIKE ?)", $name, $name)
                                    ->order("is_pending DESC, (SELECT name FROM tags WHERE id = tag_implications.predicate_id), (SELECT name FROM tags WHERE id = tag_implications.consequent_id)")
                                    ->paginate($this->page_number(), 20);
        } else {
            $this->implications = TagImplication::order("is_pending DESC, (SELECT name FROM tags WHERE id = tag_implications.predicate_id), (SELECT name FROM tags WHERE id = tag_implications.consequent_id)")
                                    ->paginate($this->page_number(), 20);
        }

        $this->respond_to_list("implications");
    }

    public function update()
    {
        !is_array($this->params()->implications) && $this->params()->implications = [];
        $ids = array_keys($this->params()->implications);

        switch ($this->params()->commit) {
            case "Delete":
                $can_delete = true;

                # iTODO:
                # 'creator_id' column isn't, apparently, filled when creating implications or aliases.
                foreach ($ids as $x) {
                    $ti = TagImplication::find($x);
                    $can_delete = ($ti->is_pending && $ti->creator_id == current_user()->id);
                    $tis[] = $ti;
                }

                if (current_user()->is_mod_or_higher() || $can_delete) {
                    foreach ($tis as $ti) {
                        $ti->destroy_and_notify(current_user(), $this->params()->reason);
                    }

                    $this->notice("Tag implications deleted");
                    $this->redirectTo("#index");
                } else {
                    $this->access_denied();
                }
                break;

            case "Approve":
                if (current_user()->is_mod_or_higher()) {
                    foreach ($ids as $x) {
                        if (CONFIG()->is_job_task_active('approve_tag_implication')) {
                            JobTask::create(['task_type' => "approve_tag_implication", 'status' => "pending", 'data' => ["id" => $x, "updater_id" => current_user()->id, "updater_ip_addr" => $this->request()->remoteIp()]]);
                        } else {
                            $ti = TagImplication::find($x);
                            $ti->approve(current_user(), $this->request()->remoteIp());
                        }
                    }

                    $this->notice("Tag implication approval jobs created");
                    $this->redirectTo('job_task#index');
                } else {
                    $this->access_denied();
                }
                break;

            default:
                $this->access_denied();
                break;
        }
    }
}
