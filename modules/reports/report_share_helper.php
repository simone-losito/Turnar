<?php
// modules/reports/report_share_helper.php

if (!function_exists('report_share_detect_scheme')) {
    function report_share_detect_scheme(): string
    {
        if (
            (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        ) {
            return 'https';
        }

        return 'http';
    }
}

if (!function_exists('report_share_detect_host')) {
    function report_share_detect_host(): string
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            return $host;
        }

        $serverName = trim((string)($_SERVER['SERVER_NAME'] ?? ''));
        if ($serverName !== '') {
            return $serverName;
        }

        return 'localhost';
    }
}

if (!function_exists('report_share_normalize_path')) {
    function report_share_normalize_path(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        // Se è già un URL assoluto lo restituisco così com'è
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        // Mantengo sempre slash iniziale
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        // Evito slash doppi nel path
        $path = preg_replace('#/+#', '/', $path);

        return $path ?: '/';
    }
}

if (!function_exists('report_share_absolute_url')) {
    function report_share_absolute_url(string $relativeOrAbsoluteUrl): string
    {
        $url = trim($relativeOrAbsoluteUrl);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $scheme = report_share_detect_scheme();
        $host   = report_share_detect_host();
        $path   = report_share_normalize_path($url);

        return $scheme . '://' . $host . $path;
    }
}

if (!function_exists('report_share_clean_text')) {
    function report_share_clean_text(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/u", ' ', $text);
        $text = preg_replace("/\n{3,}/u", "\n\n", $text);
        $text = trim($text);

        return $text;
    }
}

if (!function_exists('report_share_build_message')) {
    function report_share_build_message(string $title, string $text, string $absoluteUrl): string
    {
        $parts = [];

        $title = trim($title);
        $text  = report_share_clean_text($text);
        $absoluteUrl = trim($absoluteUrl);

        if ($title !== '') {
            $parts[] = $title;
        }

        if ($text !== '') {
            $parts[] = $text;
        }

        if ($absoluteUrl !== '') {
            $parts[] = $absoluteUrl;
        }

        return implode("\n\n", $parts);
    }
}

if (!function_exists('build_report_share_data')) {
    function build_report_share_data(string $relativePrintUrl, string $title, string $text): array
    {
        $title = trim($title);
        $text  = report_share_clean_text($text);

        $absoluteUrl = report_share_absolute_url($relativePrintUrl);
        $message     = report_share_build_message($title, $text, $absoluteUrl);

        return [
            'title'            => $title,
            'text'             => $text,
            'message'          => $message,
            'absolute_url'     => $absoluteUrl,
            'whatsapp_url'     => 'https://wa.me/?text=' . rawurlencode($message),
            'email_url'        => 'mailto:?subject=' . rawurlencode($title !== '' ? $title : 'Report')
                                  . '&body=' . rawurlencode($message),
            'native_share_url' => $absoluteUrl,
        ];
    }
}

if (!function_exists('build_report_share_data_from_current_url')) {
    function build_report_share_data_from_current_url(string $title, string $text): array
    {
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        return build_report_share_data($requestUri, $title, $text);
    }
}

if (!function_exists('build_report_share_data_with_fallback')) {
    function build_report_share_data_with_fallback(string $relativePrintUrl, string $fallbackRelativeUrl, string $title, string $text): array
    {
        $relativePrintUrl  = trim($relativePrintUrl);
        $fallbackRelativeUrl = trim($fallbackRelativeUrl);

        $chosen = $relativePrintUrl !== '' ? $relativePrintUrl : $fallbackRelativeUrl;

        return build_report_share_data($chosen, $title, $text);
    }
}