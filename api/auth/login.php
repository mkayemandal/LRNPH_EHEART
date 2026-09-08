<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$input = requestBody();
$missing = Validation::requireFields($input, ['biometric_id', 'password']);
if ($missing) {
    Response::error('Missing fields: ' . implode(', ', $missing), 422);
}

$user = UserService::findByBiometricId($input['biometric_id']);
if (empty($user['is_active']) || (int) $user['is_active'] !== 1) {
    AuditService::log($user['biometric_id'], 'LOGIN_FAILED', 'auth', null, 'Account inactive');
    Response::error('This account is inactive. Contact an administrator.', 403);
}

if (!empty($user['status']) && $user['status'] !== 'active') {
    AuditService::log($user['biometric_id'], 'LOGIN_FAILED', 'auth', null, 'Account inactive');
    Response::error('This account is inactive. Contact an administrator.', 403);
}

// If password_hash is set, verify it. Otherwise (biometric-only orgs), allow through.
if (!empty($user['password_hash']) && !password_verify($input['password'], $user['password_hash'])) {
    AuditService::log($user['biometric_id'], 'LOGIN_FAILED', 'auth', null, 'Wrong password');
    Response::error('Wrong password.', 401);
}

Auth::login($user);
UserService::touchLogin($user['biometric_id']);
AuditService::log($user['biometric_id'], 'LOGIN', 'auth', null, 'User logged in');

Response::success(Auth::user(), 'Login successful');
