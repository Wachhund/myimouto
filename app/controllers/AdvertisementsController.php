<?php

class AdvertisementsController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'admin_only' => ['except' => ['redirect']],
            ],
        ];
    }

    public function index()
    {
        $this->ads = Advertisement::paginate($this->page_number(), 100);
    }

    public function show()
    {
        $this->ad = Advertisement::find($this->params()->id);
    }

    public function blank()
    {
        $this->ad = new Advertisement();
    }

    public function create()
    {
        $this->ad = new Advertisement($this->params()->advertisement);
        if ($this->ad->save()) {
            $this->notice('Advertisement added');
            $this->redirectTo('#index');
        } else {
            $this->render('blank');
        }
    }

    public function edit()
    {
        $this->ad = Advertisement::find($this->params()->id);
    }

    public function update()
    {
        $this->ad = Advertisement::find($this->params()->id);
        if ($this->ad->updateAttributes($this->params()->advertisement)) {
            $this->notice('Advertisement updated');
            $this->redirectTo('#index');
        } else {
            $this->render('blank');
        }
    }

    public function updateMultiple()
    {
        if ($this->params()->advertisement_ids) {
            $ids = array_map(function ($a) {
                return (int) $a;
            }, $this->params()->advertisement_ids);
        } else {
            $this->notice('No advertisement selected');
            $this->redirectTo($this->advertisementsPath());
            return;
        }
        if ($this->params()->do_delete) {
            Advertisement::destroyAll(['id' => $ids]);
        } elseif ($this->params()->do_reset_hit_count) {
            Advertisement::reset_hit_count($ids);
        }
        $this->notice('Advertisements updated');
        $this->redirectTo($this->advertisementsPath());
    }

    public function destroy()
    {
        $ad = Advertisement::find($this->params()->id);
        $ad->destroy();
        $this->notice('Deleted advertisement ' . $ad->id);
        $this->redirectTo($this->advertisementsPath());
    }

    public function redirect()
    {
        $ad = Advertisement::find($this->params()->id);
        $ad->increment('hit_count');
        $this->redirectTo($ad->referral_url);
    }
}
