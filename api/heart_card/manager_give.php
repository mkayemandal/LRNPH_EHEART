<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    $user = Auth::requireRole(['MANAGER']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    $input = requestBody();
    $missing = Validation::requireFields($input, ['heart_card_id', 'short_inspiring_note']);
    if ($missing) {
        Response::error('Missing fields: ' . implode(', ', $missing), 422);
    }
    HeartCardService::managerGive(
        (int) $input['heart_card_id'],
        $input['short_inspiring_note'],
        $user['biometric_id']
    );
    Response::success(['heart_card_id' => (int) $input['heart_card_id']], 'Heart Card updated', 200);
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
}