<?php
class Ban extends Rails\ActiveRecord\Base
{
    protected $duration;
    
    protected function callbacks()
    {
        return [
            'before_create' => ['_save_level'],
            'after_create'  => ['_save_to_record', '_update_level'],
            'after_destroy' => ['_restore_level']
        ];
    }

    protected function _restore_level()
    {
        User::find($this->user_id)->updateAttribute('level', $this->old_level);
    }
    
    protected function _save_level()
    {
        $this->old_level = User::find($this->user_id)->level;
    }
    
    protected function _update_level()
    {
        $user = User::find($this->user_id);
        $user->level = CONFIG()->user_levels['Blocked'];
        $user->save();
    }
    
    protected function _save_to_record()
    {
        UserRecord::create(['user_id' => $this->user_id, 'reported_by' => $this->banned_by, 'category' => 'negative', 'body' => "Blocked: ".$this->reason]);
    }
    
    public function setDuration($dur)
    {
        $seconds = $dur * 60*60*24;
        $this->expires_at = date('Y-m-d H:i:s', time() + $seconds);
        $this->duration = $dur;
    }
    
    public function duration()
    {
        return $this->duration;
    }
}
