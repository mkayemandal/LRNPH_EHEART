<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

$user = Auth::requireRole(['SYSTEM_ADMIN']);
$input = requestBody();
$missing = Validation::requireFields($input, ['user_id', 'is_active']);
if ($missing) {
    Response::error('Missing fields: ' . implode(', ', $missing), 422);
}
UserService::setActive((int) $input['user_id'], (bool) $input['is_active']);
AuditService::log($user['biometric_id'], $input['is_active'] ? 'ACTIVATE_USER' : 'DEACTIVATE_USER', 'users', (int) $input['user_id'], 'User status toggled');
Response::success(null, 'User status updated');
