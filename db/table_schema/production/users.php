<?php
return array (
  0 => 
  array (
    'id' => 
    array (
      'type' => 'int(11)',
      'default' => NULL,
    ),
    'name' => 
    array (
      'type' => 'varchar(32)',
      'default' => NULL,
    ),
    'password_hash' => 
    array (
      'type' => 'varchar(40)',
      'default' => NULL,
    ),
    'api_key' => 
    array (
      'type' => 'varchar(64)',
      'default' => NULL,
    ),
    'created_at' => 
    array (
      'type' => 'datetime',
      'default' => '0000-00-00 00:00:00',
    ),
    'level' => 
    array (
      'type' => 'int(11)',
      'default' => '20',
    ),
    'email' => 
    array (
      'type' => 'varchar(249)',
      'default' => NULL,
    ),
    'avatar_post_id' => 
    array (
      'type' => 'int(11)',
      'default' => NULL,
    ),
    'avatar_width' => 
    array (
      'type' => 'double',
      'default' => NULL,
    ),
    'avatar_height' => 
    array (
      'type' => 'double',
      'default' => NULL,
    ),
    'avatar_top' => 
    array (
      'type' => 'double',
      'default' => NULL,
    ),
    'avatar_bottom' => 
    array (
      'type' => 'double',
      'default' => NULL,
    ),
    'avatar_left' => 
    array (
      'type' => 'double',
      'default' => NULL,
    ),
    'avatar_right' => 
    array (
      'type' => 'double',
      'default' => NULL,
    ),
    'avatar_timestamp' => 
    array (
      'type' => 'datetime',
      'default' => '0000-00-00 00:00:00',
    ),
    'my_tags' => 
    array (
      'type' => 'text',
      'default' => NULL,
    ),
    'invite_count' => 
    array (
      'type' => 'int(11)',
      'default' => '0',
    ),
    'invited_by' => 
    array (
      'type' => 'int(11)',
      'default' => NULL,
    ),
    'show_samples' => 
    array (
      'type' => 'tinyint(1)',
      'default' => '1',
    ),
    'show_advanced_editing' => 
    array (
      'type' => 'tinyint(1)',
      'default' => '0',
    ),
    'pool_browse_mode' => 
    array (
      'type' => 'tinyint(1)',
      'default' => '0',
    ),
    'use_browser' => 
    array (
      'type' => 'tinyint(1)',
      'default' => '0',
    ),
    'always_resize_images' => 
    array (
      'type' => 'tinyint(1)',
      'default' => '0',
    ),
    'last_logged_in_at' => 
    array (
      'type' => 'datetime',
      'default' => '0000-00-00 00:00:00',
    ),
    'last_forum_topic_read_at' => 
    array (
      'type' => 'datetime',
      'default' => '0000-00-00 00:00:00',
    ),
    'last_comment_read_at' => 
    array (
      'type' => 'datetime',
      'default' => '0000-00-00 00:00:00',
    ),
    'last_deleted_post_seen_at' => 
    array (
      'type' => 'datetime',
      'default' => '0000-00-00 00:00:00',
    ),
    'language' => 
    array (
      'type' => 'text',
      'default' => NULL,
    ),
    'secondary_languages' => 
    array (
      'type' => 'text',
      'default' => NULL,
    ),
    'receive_dmails' => 
    array (
      'type' => 'tinyint(1)',
      'default' => '1',
    ),
    'has_mail' => 
    array (
      'type' => 'tinyint(1)',
      'default' => '0',
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
