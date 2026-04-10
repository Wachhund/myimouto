<?php

class HistoryChange extends Rails\ActiveRecord\Base
{
    protected $obj;

    public function options()
    {
        $master_class = $this->master_class();
        return $master_class::versioning()->get_versioned_attribute_options($this->column_name) ?: [];
    }

    public function master_class()
    {
        if ($this->table_name == 'pools_posts') {
            $class_name = 'PoolPost';
        } else {
            $class_name = Rails::services()->get('inflector')->classify($this->table_name);
        }
        return $class_name;
    }

    # Return true if this changes the value to the default value.
    public function changes_to_default()
    {
        if (!$this->has_default()) {
            return false;
        }

        // $master_class = $this->master_class();
        // $column = $master_class::columns
        return $this->value == $this->get_default();
    }

    public function is_obsolete()
    {
        $latest_change = $this->latest();
        return $this->value != $latest_change->value;
    }

    public function has_default()
    {
        return isset($this->options()['default']);
    }

    public function get_default()
    {
        if ($this->has_default()) {
            return $this->options()['default'];
        }
    }

    # Return the default value for the field recorded by this change.
    public function default_history()
    {
        if (!$this->has_default()) {
            return null;
        }

        return new History([
            'table_name'  => $this->table_name,
            'remote_id'   => $this->remote_id,
            'column_name' => $this->column_name,
            'value'       => $this->get_default(),
        ]);
    }

    # Return the object this change modifies.
    public function obj()
    {
        if (!$this->obj) {
            $master_class = $this->master_class();
            $this->obj = $master_class::find($this->remote_id);
        }
        return $this->obj;
    }

    public function latest()
    {
        return self::order('id DESC')
              ->where(
                  "table_name = ? AND remote_id = ? AND column_name = ?",
                  $this->table_name,
                  $this->remote_id,
                  $this->column_name,
              )->first();
    }

    public function next()
    {
        return self::order('id ASC')
              ->where(
                  "table_name = ? AND remote_id = ? AND id > ? AND column_name = ?",
                  $this->table_name,
                  $this->remote_id,
                  $this->id,
                  $this->column_name,
              )->first();
    }

    protected function set_previous()
    {
        $obj = self::order('id DESC')
                     ->where(
                         "table_name = ? AND remote_id = ? AND id < ? AND column_name = ?",
                         $this->table_name,
                         $this->remote_id,
                         $this->id,
                         $this->column_name,
                     )->first();

        if ($obj) {
            $this->setAssociation('previous', $obj);
            $this->previous_id = $obj->id;
            $this->save();
        }
    }

    protected function associations()
    {
        return [
            'belongs_to' => [
                'history',
                'previous' => ['class_name' => 'HistoryChange', 'foreign_key' => 'previous_id'],
            ],
        ];
    }

    protected function callbacks()
    {
        return [
            'after_create' => [
                'set_previous',
            ],
        ];
    }
}
