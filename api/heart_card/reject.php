<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    $user = Auth::requireRole(['HR_ADMIN', 'SYSTEM_ADMIN']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    $input = requestBody();
    $missing = Validation::requireFields($input, ['heart_card_id', 'reason']);
    if ($missing) {
        Response::error('Missing fields: ' . implode(', ', $missing), 422);
    }
    $id = HeartCardService::reject((int) $input['heart_card_id'], $input['reason'], $user['biometric_id']);
    Response::success(['heart_card_id' => $id], 'Heart Card rejected', 200);
} catch (Throwable $e) {
    error_log('Reject Heart Card failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    Response::error($e->getMessage(), 500);
}