<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    $user = Auth::requireRole(['HR_ADMIN', 'SYSTEM_ADMIN']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    $input = requestBody();
    $missing = Validation::requireFields($input, ['heart_card_id']);
    if ($missing) {
        Response::error('Missing fields: ' . implode(', ', $missing), 422);
    }
    $id = HeartCardService::approve((int) $input['heart_card_id'], $user['biometric_id']);
    Response::success(['heart_card_id' => $id], 'Heart Card approved', 200);
} catch (\Throwable $e) {
    error_log('Approve Heart Card failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    Response::error($e->getMessage(), 500);
}
