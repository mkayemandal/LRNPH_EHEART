<?php

require_once __DIR__ . '/../../utils/bootstrap.php';

try {

    $user = Auth::requireRole(['MANAGER']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }

    $input = requestBody();

    $missing = Validation::requireFields(
        $input,
        [
            'receiver_biometric_id',
            'receiver_department',
            'receiver_full_name',
            'core_values',
            'action_performed',
            'business_impact',
            'why_beyond_normal'
        ]
    );

    if ($missing) {
        Response::error(
            'Missing fields: ' . implode(', ', $missing),
            422
        );
    }

    if (
        !is_array($input['core_values']) ||
        count($input['core_values']) === 0
    ) {
        Response::error(
            'At least one Core Value must be selected.',
            422
        );
    }

    $heartCardId = HeartCardService::createRequest(
        $input,
        $user['biometric_id'],
        $user['employee_name'] ?? $user['biometric_id']
    );

    Response::success(
        [
            'heart_card_id' => $heartCardId,
            'control_number' =>
                DateHelper::controlNumber($heartCardId)
        ],
        'Heart Card request submitted successfully.',
        201
    );

} catch (\Throwable $e) {

    /*
     * Response::error() may already have terminated
     * the request. This catch is for unexpected errors.
     */

    http_response_code(500);

    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
}