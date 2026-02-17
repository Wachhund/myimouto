<?php
return array (
  0 =>
  array (
    'id' =>
    array (
      'type' => 'int',
      'default' => NULL,
    ),
    'post_id' =>
    array (
      'type' => 'int',
      'default' => NULL,
    ),
    'creator_id' =>
    array (
      'type' => 'int',
      'default' => NULL,
    ),
    'reviewed_by_id' =>
    array (
      'type' => 'int',
      'default' => NULL,
    ),
    'status' =>
    array (
      'type' => 'varchar(16)',
      'default' => 'pending',
    ),
    'reason' =>
    array (
      'type' => 'text',
      'default' => NULL,
    ),
    'moderation_reason' =>
    array (
      'type' => 'text',
      'default' => NULL,
    ),
    'source_url' =>
    array (
      'type' => 'varchar(1024)',
      'default' => NULL,
    ),
    'replacement_file_path' =>
    array (
      'type' => 'varchar(255)',
      'default' => NULL,
    ),
    'replacement_file_name' =>
    array (
      'type' => 'varchar(255)',
      'default' => NULL,
    ),
    'replacement_md5' =>
    array (
      'type' => 'varchar(32)',
      'default' => NULL,
    ),
    'reviewed_at' =>
    array (
      'type' => 'datetime',
      'default' => NULL,
    ),
    'created_at' =>
    array (
      'type' => 'datetime',
      'default' => NULL,
    ),
    'updated_at' =>
    array (
      'type' => 'datetime',
      'default' => NULL,
    ),
  ),
  1 =>
  array (
    'pri' =>
    array (
      0 => 'id',
    ),
  ),
)
;
