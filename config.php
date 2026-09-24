<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// ================= DATABASE =================
define('DB_HOST', 'localhost');
define('DB_NAME', 'ridhopan_licdb');
define('DB_USER', 'ridhopan_licdb');
define('DB_PASS', 'FJRk8T^TGTv6;g9=');

// ================= ADMIN PANEL =================
// WAJIB GANTI sebelum dipakai!
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'ganti123');

// ================= FORMAT KEY =================
// Hasil default: MLBB-XXXX
// KEY_GROUPS = 2  ->  MLBB-XXXX-XXXX
// KEY_GROUPS = 3  ->  MLBB-XXXX-XXXX-XXXX
define('KEY_PREFIX', 'MLBB');
define('KEY_GROUPS', 1);
define('KEY_GROUP_LEN', 4);

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}