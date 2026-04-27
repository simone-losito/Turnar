<?php
require_once __DIR__ . '/../../core/settings.php';

require_login();
require_permission('dashboard.view');

redirect('');