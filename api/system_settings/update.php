<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    $user = Auth::requireRole(['HR_ADMIN', 'SYSTEM_ADMIN']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }

    $input = requestBody();
    $missing = Validation::requireFields($input, ['setting_key', 'setting_value']);
    if ($missing) {
        Response::error('Missing fields: ' . implode(', ', $missing), 422);
    }

    SystemSettingService::update(
        (string) $input['setting_key'],
        (int) $input['setting_value']
    );

    AuditService::log(
        $user['biometric_id'],
        'UPDATE_SYSTEM_SETTING',
        'system_setting',
        0,
        $input['setting_key'] . ' set to ' . (int) $input['setting_value']
    );

    Response::success(null, 'Setting updated.');
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), 422);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}