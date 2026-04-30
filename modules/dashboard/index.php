<?php
// modules/dashboard/index.php
// Redirect dashboard protetto da modulo e permessi

require_once __DIR__ . '/../../core/settings.php';

require_login();
require_module('dashboard');
require_permission('dashboard.view');

redirect('');
