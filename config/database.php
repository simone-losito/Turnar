<?php
// config/database.php
// Connessione database centralizzata per Turnar

require_once __DIR__ . '/app.php';

// Evita inclusioni multiple
if (defined('TURNAR_DATABASE_LOADED')) {
    return;
}
define('TURNAR_DATABASE_LOADED', true);

// --------------------------------------------------
// CONFIGURAZIONE DATABASE
// MODIFICA QUI I DATI SE NECESSARIO
// --------------------------------------------------
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}

if (!defined('DB_NAME')) {
    define('DB_NAME', 'turnar');
}

if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}

if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// --------------------------------------------------
// CONNESSIONE CENTRALIZZATA
// --------------------------------------------------
if (!function_exists('db_connect')) {
    function db_connect(): mysqli
    {
        static $conn = null;

        if ($conn instanceof mysqli) {
            return $conn;
        }

        mysqli_report(MYSQLI_REPORT_OFF);

        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_errno) {
            die('Errore connessione database Turnar: ' . htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8'));
        }

        if (!$conn->set_charset(DB_CHARSET)) {
            die('Errore impostazione charset database Turnar.');
        }

        return $conn;
    }
}

// --------------------------------------------------
// CONNESSIONE GLOBALE PER COMPATIBILITÀ PRATICA
// --------------------------------------------------
$mysqli = db_connect();

// --------------------------------------------------
// HELPER DATABASE
// --------------------------------------------------
if (!function_exists('db')) {
    function db(): mysqli
    {
        return db_connect();
    }
}

if (!function_exists('db_escape')) {
    function db_escape(string $value): string
    {
        return db()->real_escape_string($value);
    }
}

if (!function_exists('db_table_exists')) {
    function db_table_exists(string $tableName): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return false;
        }

        $safe = db_escape($tableName);
        $sql  = "SHOW TABLES LIKE '{$safe}'";

        $res = db()->query($sql);
        if ($res instanceof mysqli_result) {
            $exists = ($res->num_rows > 0);
            $res->free();
            return $exists;
        }

        return false;
    }
}

if (!function_exists('db_one')) {
    function db_one(string $sql)
    {
        $res = db()->query($sql);
        if ($res instanceof mysqli_result) {
            $row = $res->fetch_row();
            $res->free();
            return $row[0] ?? null;
        }
        return null;
    }
}