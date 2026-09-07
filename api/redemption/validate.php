<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    $user = Auth::requireRole(['HR_ADMIN']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }

    $input = requestBody();
    $missing = Validation::requireFields($input, ['heart_card_id', 'decision']);
    if ($missing) {
        Response::error('Missing fields: ' . implode(', ', $missing), 422);
    }
    if (!in_array($input['decision'], ['APPROVE', 'REJECT'], true)) {
        Response::error('decision must be APPROVE or REJECT.', 422);
    }

    $result = RedemptionService::validate((int) $input['heart_card_id'], $user['biometric_id'], $input['decision'], $input['notes'] ?? null);
    Response::success($result, 'Redemption processed');
} catch (Throwable $e) {
    error_log('Redemption validation failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    Response::error($e->getMessage(), 500);
}
