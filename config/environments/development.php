<?php

MyImouto\Application::configure(function ($config) {
    $config->rails_panel_path = 'railspanel';

    $config->error->report_types = E_ALL;

    $config->consider_all_requests_local = true;

    $config->serve_static_assets = false;

    $config->assets->concat = true;

    $config->active_record->use_cached_schema = false;

    $config->action_mailer->delivery_method = 'file';
});
