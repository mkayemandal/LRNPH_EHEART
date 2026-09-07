<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

$user = Auth::requireLogin();
$input = requestBody();
$missing = Validation::requireFields($input, ['notification_id']);
if ($missing) {
    Response::error('Missing fields: ' . implode(', ', $missing), 422);
}
NotificationService::markRead((int) $input['notification_id'], $user['biometric_id']);
Response::success(null, 'Marked as read');
