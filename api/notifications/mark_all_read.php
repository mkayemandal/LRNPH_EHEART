<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

$user = Auth::requireLogin();
$biometricId = $user['biometric_id'];
$roleCode = $user['role_code'];
NotificationService::markAllRead($biometricId, $roleCode);
Response::success(null, 'All marked as read');