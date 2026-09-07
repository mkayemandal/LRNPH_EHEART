<?php
require_once __DIR__ . '/../connection/database.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../services/audit_service.php';

function clientIp(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

Auth::start();
$user = Auth::user();
if ($user) {
    AuditService::log($user['biometric_id'], 'LOGOUT', 'auth', null, 'User logged out');
}
Auth::logout();
$reason = $_GET['reason'] ?? null;
$target = '/eheart/auth/login.php';
if ($reason === 'idle_timeout') {
    $target .= '?reason=idle_timeout';
}
header('Location: ' . $target);
exit;
