<?php

require_once __DIR__ . '/../../utils/bootstrap.php';

try {

    Auth::requireRole([
        'HR_ADMIN',
        'SYSTEM_ADMIN'
    ]);

    $department =
        trim($_GET['department'] ?? '');

    if ($department === '') {
        Response::error(
            'Department is required.',
            422
        );
    }

    $summary =
        HeartCardService::getDepartmentQuotaSummary(
            $department
        );

    Response::success($summary);

} catch (\Throwable $e) {

    http_response_code(500);

    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}