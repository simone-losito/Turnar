<?php
// config/push.php
// Configurazione Web Push VAPID per Turnar

if (!defined('TURNAR_PUSH_PUBLIC_KEY')) {
    define('TURNAR_PUSH_PUBLIC_KEY', 'BNOm1hPx6JjyYXPXayul-PtioyyOMyheGxsTWx245v0xuti7Wzv8cbwbMyX_ay8hOLyB5uipyvnvHYIJeOzz06Q');
}

if (!defined('TURNAR_PUSH_PRIVATE_KEY')) {
    define('TURNAR_PUSH_PRIVATE_KEY', 'WomC19hOuskgTlRn2vdwClLQQoDnmdG5AnRlaPqN0no');
}

if (!defined('TURNAR_PUSH_SUBJECT')) {
    define('TURNAR_PUSH_SUBJECT', 'mailto:info@example.com');
}