<?php
// app/config.php

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/auth.php';

// Blocco globale app mobile: Master sempre ok, Manager/User secondo matrice moduli.
if (function_exists('auth_check') && auth_check()) {
    if (function_exists('module_app_enabled') && !module_app_enabled('mobile')) {
        http_response_code(403);
        exit('App mobile non attiva per questo ruolo.');
    }
}

// URL base app mobile
if (!function_exists('app_mobile_url')) {
    function app_mobile_url(string $path = ''): string
    {
        return app_url('app/' . ltrim($path, '/'));
    }
}
