<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    $user = Auth::requireRole(['HR_ADMIN']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }

    $input = requestBody();
    $missing = Validation::requireFields($input, ['redemption_id']);
    if ($missing) {
        Response::error('Missing fields: ' . implode(', ', $missing), 422);
    }

    $result = RedemptionService::cancel(
        (int) $input['redemption_id'],
        $user['biometric_id']
    );

    Response::success($result, 'Temporary redemption removed');
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}