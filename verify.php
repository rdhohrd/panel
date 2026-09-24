<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ===== HMAC SECRET - SAMA dengan LIC_HMAC_SECRET di license.h =====
define('HMAC_SECRET', 'G7x!kP#2qZmRvL9nYwD4sTbA6uJcEiOh');

// ===== TIMESTAMP TOLERANCE (detik) =====
define('TIMESTAMP_TOLERANCE', 30);

$key   = trim($_POST['key']   ?? '');
$hwid  = trim($_POST['hwid']  ?? '');
$nonce = trim($_POST['nonce'] ?? '');
$ts    = intval($_POST['ts']  ?? 0);
$sig   = trim($_POST['sig']   ?? '');
$ip    = $_SERVER['REMOTE_ADDR'] ?? '';

function out(string $status, string $message, string $expiry = '', string $hwid = '', string $plan = ''): void {
    $ts      = time();
    $payload = $status . $message . $expiry . $ts . $hwid;
    $sig     = hash_hmac('sha256', $payload, HMAC_SECRET);

    echo json_encode([
        'status'  => $status,
        'message' => $message,
        'expiry'  => $expiry,
        'hwid'    => $hwid,
        'plan'    => $plan,
        'ts'      => $ts,
        'sig'     => $sig,
    ]);
    exit;
}

function vlog(string $key, string $hwid, string $result): void {
    try {
        db()->prepare('INSERT INTO verify_logs (license_key, hwid, ip, result) VALUES (?,?,?,?)')
            ->execute([$key, $hwid, $_SERVER['REMOTE_ADDR'] ?? '', $result]);
    } catch (Throwable $t) {}
}

// ===== VALIDASI REQUEST =====
if ($key === '' || $hwid === '') {
    out('invalid', 'Parameter tidak lengkap');
}

// Cek timestamp request - tolak request yang terlalu lama/basi
if ($ts <= 0 || abs(time() - $ts) > TIMESTAMP_TOLERANCE) {
    out('invalid', 'Request expired atau timestamp tidak valid');
}

// Verifikasi HMAC signature dari client
if ($nonce !== '' && $sig !== '') {
    $body        = "key=" . rawurlencode($key) . "&hwid=" . rawurlencode($hwid) . "&ver=2.0";
    $signData    = $body . $nonce . $ts;
    $expected    = hash_hmac('sha256', $signData, HMAC_SECRET);
    if (!hash_equals($expected, $sig)) {
        out('invalid', 'Signature tidak valid');
    }
}

// ===== DATABASE =====
try {
    $pdo = db();
} catch (Throwable $t) {
    out('invalid', 'Server error (database)');
}

$stmt = $pdo->prepare('SELECT * FROM licenses WHERE license_key = ? LIMIT 1');
$stmt->execute([$key]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    vlog($key, $hwid, 'not_found');
    out('invalid', 'Key tidak ditemukan', '', $hwid);
}

if ($row['status'] === 'banned') {
    vlog($key, $hwid, 'banned');
    out('invalid', 'Key di-banned. Hubungi seller.', '', $hwid);
}

if ($row['status'] !== 'active') {
    vlog($key, $hwid, 'disabled');
    out('invalid', 'Key tidak aktif', '', $hwid);
}

if (!empty($row['expiry']) && strtotime($row['expiry']) < time()) {
    vlog($key, $hwid, 'expired');
    out('invalid', 'Key sudah expired', $row['expiry'], $hwid);
}

// ===== CEK LIMIT DEVICE =====
$dev = $pdo->prepare('SELECT id FROM license_devices WHERE license_id = ? AND hwid = ? LIMIT 1');
$dev->execute([$row['id'], $hwid]);

if ($dev->fetch()) {
    $pdo->prepare('UPDATE license_devices SET last_used = NOW() WHERE license_id = ? AND hwid = ?')
        ->execute([$row['id'], $hwid]);
} else {
    $cnt = $pdo->prepare('SELECT COUNT(*) c FROM license_devices WHERE license_id = ?');
    $cnt->execute([$row['id']]);
    $used = (int)$cnt->fetch(PDO::FETCH_ASSOC)['c'];
    $max  = (int)$row['max_devices'];

    if ($used >= $max) {
        vlog($key, $hwid, 'device_limit');
        out('invalid', "Limit device tercapai (max $max). Minta reset device ke seller.", '', $hwid);
    }

    $pdo->prepare('INSERT INTO license_devices (license_id, hwid) VALUES (?, ?)')
        ->execute([$row['id'], $hwid]);
}

$pdo->prepare('UPDATE licenses SET last_used = NOW(), last_ip = ? WHERE id = ?')
    ->execute([$ip, $row['id']]);

vlog($key, $hwid, 'valid');

$expiry = $row['expiry'] ?? 'lifetime';
$plan   = $row['plan']   ?? 'standard';

out('valid', 'OK', $expiry, $hwid, $plan);
