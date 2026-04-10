<?php

class History extends Rails\ActiveRecord\Base
{
    public function aux()
    {
        if (!$this->aux_as_json) {
            return [];
        }
        return json_decode($this->aux_as_json);
    }

    public function setAux($o)
    {
        if (!$o) {
            $this->aux_as_json = null;
        } else {
            $this->aux_as_json = json_encode($o);
        }
    }

    public function group_by_table_class()
    {
        return Rails::services()->get('inflector')->classify($this->group_by_table);
    }

    public function get_group_by_controller()
    {
        $class_name = $this->group_by_table_class();
        return $class_name::versioning()->get_versioning_group_by()['controller'];
    }

    public function get_group_by_action()
    {
        $class_name = $this->group_by_table_class();
        return $class_name::versioning()->get_versioning_group_by()['action'];
    }

    public function group_by_obj()
    {
        $class_name = $this->group_by_table_class();
        return $class_name::find($this->group_by_id);
    }

    public function user()
    {
        return User::find($this->user_id);
    }

    public function author()
    {
        return User::find($this->user_id)->name;
    }

    # Undo all changes in the array changes.
    public static function undo($changes, $user, $redo_change = false, array &$errors)
    {
        # Save parent objects after child objects, so changes to the children are
        # committed when we save the parents.
        $objects = new ArrayObject();

        foreach ($changes as $change) {
            # If we have no previous change, this was the first change to this property
            # and we have no default, so this change can't be undone.
            $previous_change = $change->previous;

            if (!$previous_change && empty($change->options()['allow_reverting_to_default'])) {
                continue;
            }

            if (!$user->can_change($change->obj(), $change->column_name)) {
                $errors[spl_object_hash($change)] = 'denied';
                continue;
            }

            # Add this node and its parent objects to objects.
            $node = self::cache_object_recurse($objects, $change->table_name, $change->remote_id, $change->obj());
            if (!isset($node['changes'])) {
                $node['changes'] = [];
            }
            $node['changes'][] = $change;
        }

        if (empty($objects['objects'])) {
            return;
        }
        # objects contains one or more trees of objects.  Flatten this to an ordered
        # list, so we can always save child nodes before parent nodes.
        $done = new ArrayObject();
        $stack = new ArrayObject();
        foreach ($objects['objects'] as $table_name => $rhs) {
            foreach ($rhs as $id => $node) {
                # Start adding from the node at the top of the tree.
                while (!empty($node['parent'])) {
                    $node = $node['parent'];
                }
                self::stack_object_recurse($node, $stack, $done);
            }
        }
        $stack = $stack->getArrayCopy();

        foreach (array_reverse($stack) as $node) {
            $object = $node['o'];

            $object->runCallbacks('undo', function () use ($node, $redo_change, $object) {
                $changes = !empty($node['changes']) ? $node['changes'] : [];

                if ($changes) {
                    foreach ($changes as $change) {
                        if ($redo_change) {
                            $redo_func = $change->column_name . '_redo';
                            if (method_exists($object, $redo_func)) {
                                $object->$redo_func($change);
                            } else {
                                $object->{$change->column_name} = $change->value;
                            }
                        } else {
                            $undo_func = $change->column_name . '_undo';
                            if (method_exists($object, $undo_func)) {
                                $object->$undo_func($change);
                            } else {
                                if ($change->previous) {
                                    $previous = $change->previous->value;
                                } else {
                                    $previous = $change->options()['default']; # when :allow_reverting_to_default
                                }

                                $object->{$change->column_name} = $previous;
                            }
                        }
                    }
                }
            });

            $object->save();
        }
    }

    public static function generate_sql($options = [])
    {
        $sql = self::none();
        if (isset($options['remote_id'])) {
            $sql->where("histories.remote_id = ?", $options['remote_id']);
        }
        if (isset($options['remote_id'])) {
            $sql->where("histories.user_id = ?", $options['user_id']);
        }

        if (isset($options['user_name'])) {
            $sql->joins("JOIN users ON users.id = histories.user_id")
                ->where("users.name = ?", $options['user_name']);
        }
        return $sql;
    }

    # Find and return the node for table_name/id in objects.  If the node doesn't
    # exist, create it and point it at object.
    protected static function cache_object($objects, $table_name, $id, $object)
    {
        if (empty($objects['objects'])) {
            $objects['objects'] = [];
        }
        if (empty($objects['objects'][$table_name])) {
            $objects['objects'][$table_name] = [];
        }
        if (empty($objects['objects'][$table_name][$id])) {
            $objects['objects'][$table_name][$id] = new ArrayObject([
                'o' => $object,
            ]);
        }
        return $objects['objects'][$table_name][$id];
    }

    # Find and return the node for table_name/id in objects.  Recursively create
    # nodes for parent objects.
    protected static function cache_object_recurse($objects, $table_name, $id, $object)
    {
        $node = self::cache_object($objects, $table_name, $id, $object);

        # If this class has a master class, register the master object for update callbacks too.
        $master = $object->versioned_master_object();
        if ($master) {
            $master_node = self::cache_object_recurse($objects, get_class($master), $master->id, $master);

            if (empty($master_node['children'])) {
                $master_node['children'] = [];
            }
            $master_node['children'][] = $node;
            $node['parent'] = $master_node;
        }

        return $node;
    }

    protected static function stack_object_recurse($node, $stack, $done = [])
    {
        $nodeHash = spl_object_hash($node);
        if (!empty($done[$nodeHash])) {
            return;
        }
        $done[$nodeHash] = true;

        $stack[] = $node;

        if (!empty($node['children'])) {
            foreach ($node['children'] as $child) {
                self::stack_object_recurse($child, $stack, $done);
            }
        }
    }

    protected function associations()
    {
        return [
            'belongs_to' => [
                'user',
            ],
            'has_many' => [
                'history_changes' => [function () {
                    $this->order('id');
                }],
            ],
        ];
    }
}
