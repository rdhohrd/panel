<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

echo "<pre style='background:#111;color:#0f0;padding:12px;font-family:monospace'>";
echo "=== CEK PANEL LICENSE ===\n";
echo "PHP version   : " . PHP_VERSION . " (minimal butuh 7.1)\n";
echo "pdo_mysql     : " . (extension_loaded('pdo_mysql') ? "ADA" : "TIDAK ADA") . "\n";
echo "config.php    : " . (is_file(__DIR__ . '/config.php') ? "ADA" : "HILANG!") . "\n";
echo "functions.php : " . (is_file(__DIR__ . '/functions.php') ? "ADA" : "HILANG!") . "\n";
echo "login.php     : " . (is_file(__DIR__ . '/login.php') ? "ADA" : "HILANG!") . "\n";
echo "index.php     : " . (is_file(__DIR__ . '/index.php') ? "ADA" : "HILANG!") . "\n";
echo "api/verify.php: " . (is_file(__DIR__ . '/api/verify.php') ? "ADA" : "HILANG!") . "\n\n";

if (version_compare(PHP_VERSION, '7.1.0', '<')) {
    echo "[ERROR] PHP terlalu lama. Ganti ke PHP 7.4 / 8.x di panel hosting\n";
    echo "(cPanel: MultiPHP Manager | Hostinger: hPanel > PHP Configuration).\n";
    echo "</pre>";
    exit;
}

try {
    require_once __DIR__ . '/config.php';
    echo "config.php    : dimuat OK\n";
} catch (Throwable $e) {
    echo "[ERROR] config.php : " . $e->getMessage() . "\n";
    echo "</pre>";
    exit;
}

try {
    db();
    echo "Koneksi DB    : OK\n";
    $r1 = db()->query("SHOW TABLES LIKE 'licenses'")->fetch();
    $r2 = db()->query("SHOW TABLES LIKE 'verify_logs'")->fetch();
    echo "Tabel licenses    : " . ($r1 ? "ADA" : "BELUM ADA -> import schema.sql") . "\n";
    echo "Tabel verify_logs : " . ($r2 ? "ADA" : "BELUM ADA -> import schema.sql") . "\n";
} catch (Throwable $e) {
    echo "[ERROR] DB : " . $e->getMessage() . "\n";
}

echo "\nSelesai. HAPUS file cek.php ini setelah selesai diagnosa.\n";
echo "</pre>";