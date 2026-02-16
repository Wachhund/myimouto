<?php
class DmailController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => ['blocked_only']
        ];
    }

    public function preview()
    {
        $this->setLayout(false);
    }

    public function showPreviousMessages()
    {
        $this->dmails = Dmail::where("(to_id = ? or from_id = ?) and parent_id = ? and id < ?",
                            $this->current_user->id, $this->current_user->id, $this->params()->parent_id, $this->params()->id)
                            ->order("id asc")->take();
        $this->setLayout(false);
    }

    public function compose()
    {
        $this->dmail = new Dmail();
    }

    public function create()
    {
        if (Dmail::where('from_id = ? AND created_at > ?', $this->current_user->id, date('Y-m-d H:i:s', time()-3600))->count() >= CONFIG()->max_dmails_per_hour) {
            $this->notice("You can't send more than " . CONFIG()->max_dmails_per_hour . " dmails per hour.");
            $this->redirectTo('#inbox');
            return;
        }
        $dmail = $this->params()->dmail;
        if (empty($dmail['parent_id']))
            $dmail['parent_id'] = null;
        $this->dmail = Dmail::create(array_merge($dmail, ['from_id' => $this->current_user->id, 'ip_addr' => $this->request()->remoteIp()]));

        if ($this->dmail->errors()->none()) {
            $this->notice("Message sent to ".$dmail['to_name']);
            $this->redirectTo("#inbox");
        } else {
            $this->notice("Error: " . $this->dmail->errors()->fullMessages(", "));
            $this->render('compose');
        }
    }

    public function inbox()
    {
        $this->dmails = Dmail::where("to_id = ? or from_id = ?", $this->current_user->id, $this->current_user->id)->order("created_at desc")->paginate($this->page_number(), 25);
    }

    public function show()
    {
        $this->dmail = Dmail::find($this->params()->id);

        if ($this->dmail->to_id != $this->current_user->id && $this->dmail->from_id != $this->current_user->id) {
            $this->notice("Access denied");
            $this->redirectTo("user#login");
            return;
        }

        if ($this->dmail->to_id == $this->current_user->id) {
            $this->dmail->mark_as_read($this->current_user);
        }
    }

    public function confirmMarkAllRead()
    {
    }

    public function markAllRead()
    {
        foreach (Dmail::where("to_id = ? and has_seen = false", $this->current_user->id)->take() as $dmail)
            $dmail->updateAttribute('has_seen', true);

        $this->current_user->updateAttribute('has_mail', false);
        $this->respond_to_success("All messages marked as read", ['action' => "inbox"]);
    }
}
