<?php
// app/config.php

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/auth.php';

// URL base app mobile
if (!function_exists('app_mobile_url')) {
    function app_mobile_url(string $path = ''): string
    {
        return app_url('app/' . ltrim($path, '/'));
    }
}