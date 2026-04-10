<?php

class TagAliasController extends ApplicationController
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
        $ta = new TagAlias($this->params()->tag_alias);
        $ta->is_pending = true;

        if ($ta->save()) {
            $this->notice("Tag alias created");
        } else {
            $this->notice("Error: " . $ta->errors()->fullMessages(', '));
        }

        $this->redirectTo("#index");
    }

    public function index()
    {
        $this->set_title("Tag Aliases");

        if ($this->params()->commit == "Search Implications") {
            $this->redirectTo(['tag_implication#index', 'query' => $this->params()->query]);
            return;
        }

        if ($this->params()->query) {
            $name = "%" . $this->params()->query . "%";
            $this->aliases = TagAlias::where("name LIKE ? OR alias_id IN (SELECT id FROM tags WHERE name LIKE ?)", $name, $name)
                                ->order("is_pending DESC, name")
                                ->paginate($this->page_number(), 20);
        } else {
            $this->aliases = TagAlias::order("is_pending DESC")->paginate($this->page_number(), 20);
        }

        $this->respond_to_list('aliases');
    }

    public function update()
    {
        !is_array($this->params()->aliases) && $this->params()->aliases = [];

        $ids = array_keys($this->params()->aliases);

        switch ($this->params()->commit) {
            case "Delete":
                $validate_all = true;

                foreach ($ids as $id) {
                    $ta = TagAlias::find($id);
                    if (!$ta->is_pending || $ta->creator_id != current_user()->id) {
                        $validate_all = false;
                        break;
                    }
                }

                if (current_user()->is_mod_or_higher() || $validate_all) {
                    foreach ($ids as $x) {
                        if ($ta = TagAlias::find($x)) {
                            $ta->destroy_and_notify(current_user(), $this->params()->reason);
                        }
                    }

                    $this->notice("Tag aliases deleted");
                    $this->redirectTo("#index");
                } else {
                    $this->access_denied();
                }
                break;

            case "Approve":
                if (current_user()->is_mod_or_higher()) {
                    foreach ($ids as $x) {
                        if (CONFIG()->is_job_task_active('approve_tag_alias')) {
                            JobTask::create(['task_type' => "approve_tag_alias", 'status' => "pending", 'data' => ["id" => $x, "updater_id" => current_user()->id, "updater_ip_addr" => $this->request()->remoteIp()]]);
                        } else {
                            $ta = TagAlias::find($x);
                            $ta->approve(current_user()->id, $this->request()->remoteIp());
                        }
                    }

                    $this->notice("Tag alias approval jobs created");
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
