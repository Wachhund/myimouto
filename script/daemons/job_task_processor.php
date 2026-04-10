<?php

define('RAILS_ENV', 'development');
require dirname(__DIR__) . '/../config/boot.php';

set_time_limit(0);
JobTask::execute_once();
