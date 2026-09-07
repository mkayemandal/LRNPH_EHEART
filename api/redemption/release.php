<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    $user = Auth::requireRole(['HR_ADMIN']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    $input = requestBody();
    $missing = Validation::requireFields($input, ['redemption_id', 'gc_serial_number']);
    if ($missing) {
        Response::error('Missing fields: ' . implode(', ', $missing), 422);
    }
    $gcId = GiftCertificateService::release((int) $input['redemption_id'], $input['gc_serial_number'], $user['biometric_id']);
    Response::success(['gift_certificate_id' => $gcId], 'Gift Certificate released', 201);
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