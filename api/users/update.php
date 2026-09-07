<?php

require_once __DIR__ . '/../../utils/bootstrap.php';

try {

    $user = Auth::requireRole(['SYSTEM_ADMIN']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }

    $input = requestBody();

    $missing = Validation::requireFields(
        $input,
        ['user_id', 'full_name', 'role_id']
    );

    if ($missing) {
        Response::error('Missing fields: ' . implode(', ', $missing), 422);
    }

    $userId  = (int) $input['user_id'];
    $fullName = trim((string) $input['full_name']);
    $roleId   = (int) $input['role_id'];
    $email    = isset($input['email']) ? trim((string) $input['email']) : null;

    if ($userId <= 0 || $fullName === '' || $roleId <= 0) {
        Response::error('Invalid user data.', 422);
    }

    UserService::updateUser($userId, $fullName, $roleId, $email);

    AuditService::log(
        $user['biometric_id'],
        'UPDATE_USER',
        'users',
        $userId,
        'Updated user ' . $fullName
    );

    Response::success(null, 'User updated successfully.');
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
