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
if (!$user) {
    Response::error('Invalid biometric ID or account inactive.', 401);
}

// If password_hash is set, verify it. Otherwise (biometric-only orgs), allow through.
if (!empty($user['password_hash']) && !password_verify($input['password'], $user['password_hash'])) {
    Response::error('Invalid credentials.', 401);
}

Auth::login($user);
UserService::touchLogin($user['biometric_id']);
AuditService::log($user['biometric_id'], 'LOGIN', 'auth', null, 'User logged in');

Response::success(Auth::user(), 'Login successful');
