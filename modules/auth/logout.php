<?php
require_once __DIR__ . '/../../core/settings.php';

if (auth_check()) {
    auth_logout();
}

// redirect sempre alla login
redirect('modules/auth/login.php');