<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    Auth::requireRole(['HR_ADMIN', 'SYSTEM_ADMIN']);

    $pdo = DB::get_connection();

    $stmt = $pdo->query("
        SELECT department, monthly_quota, headcount, is_active
        FROM eheart_department_quota
        ORDER BY department
    ");
    $quotas = $stmt->fetchAll();

    $result = [];

    foreach ($quotas as $q) {
        $used = HeartCardService::getDepartmentMonthlyUsage($q['department']);
        $limit = (int) $q['monthly_quota'];

        $result[] = [
            'department' => $q['department'],
            'monthly_quota' => $limit,
            'headcount' => (int) $q['headcount'],
            'is_active' => (bool) $q['is_active'],
            'used' => $used,
            'remaining' => max(0, $limit - $used)
        ];
    }

    Response::success($result);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
