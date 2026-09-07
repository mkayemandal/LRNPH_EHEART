<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

$user = Auth::user();
if ($user) {
    AuditService::log($user['biometric_id'], 'LOGOUT', 'auth', null, 'User logged out');
}
Auth::logout();
Response::success(null, 'Logged out');
