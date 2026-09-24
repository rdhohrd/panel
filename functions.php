<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function is_logged_in(): bool {
    return !empty($_SESSION['admin']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function gen_key(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $k = KEY_PREFIX;
        for ($g = 0; $g < KEY_GROUPS; $g++) {
            $k .= '-';
            for ($i = 0; $i < KEY_GROUP_LEN; $i++) {
                $k .= $chars[random_int(0, strlen($chars) - 1)];
            }
        }
        $st = db()->prepare('SELECT 1 FROM licenses WHERE license_key = ?');
        $st->execute([$k]);
    } while ($st->fetch());
    return $k;
}