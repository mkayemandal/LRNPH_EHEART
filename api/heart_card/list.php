<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    $user = Auth::requireLogin();

    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $department = isset($_GET['department']) ? trim($_GET['department']) : null;
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;

    if ($dateFrom !== null && $dateFrom !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $dateFrom);

        if (!$date || $date->format('Y-m-d') !== $dateFrom) {
            Response::error('Invalid date_from format. Use YYYY-MM-DD.', 422);
        }
    } else {
        $dateFrom = null;
    }

    if ($dateTo !== null && $dateTo !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $dateTo);

        if (!$date || $date->format('Y-m-d') !== $dateTo) {
            Response::error('Invalid date_to format. Use YYYY-MM-DD.', 422);
        }
    } else {
        $dateTo = null;
    }

    if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
        Response::error('The From date cannot be later than the To date.', 422);
    }

    $rows = HeartCardService::list(
        $status ?: null,
        $department ?: null,
        $dateFrom,
        $dateTo,
        $user['role_code'],
        $user['biometric_id']
    );

    Response::success($rows);

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