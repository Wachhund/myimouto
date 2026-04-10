<?php

class CanNotBanSelf extends Exception {}

class BlocksController extends ApplicationController
{
    public function blockIp()
    {
        try {
            IpBans::transaction(function () {
                $ban = IpBans::create(array_merge($this->params()->ban, ['banned_by' => current_user()->id]));
                if (IpBans::where("id = ? and ip_addr = ?", $ban->id, $this->request()->remoteIp())->first()) {
                    throw new CanNotBanSelf();
                }
            });
        } catch (CanNotBanSelf $e) {
            $this->notice("You can not ban yourself");
        }
        $this->redirectTo('user#show_blocked_users');
    }

    public function unblockIp()
    {
        foreach (array_keys($this->params()->ip_ban) as $ban_id) {
            IpBans::destroyAll("id = ?", $ban_id);
        }

        $this->redirectTo("user#show_blocked_users");
    }
}
