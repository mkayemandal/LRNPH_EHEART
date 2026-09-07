<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    Auth::requireRole(['HR_ADMIN', 'SYSTEM_ADMIN']);

    $excludeQuota = isset($_GET['exclude_quota']) && $_GET['exclude_quota'] == '1';

    $pdo = DB::get_connection('lrnph_e');
    $stmt = $pdo->query("
        SELECT DISTINCT LTRIM(RTRIM(Department)) AS Department
        FROM [LRNPH_E].[dbo].[lrn_master_list]
        WHERE Department IS NOT NULL
          AND LTRIM(RTRIM(Department)) <> ''
        ORDER BY Department
    ");
    $rawDepartments = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $normDept = function (string $d): string {
        $d = preg_replace('/[^A-Za-z0-9]+/', ' ', $d);
        $d = preg_replace('/\s+/', ' ', $d);
        return strtoupper(trim($d));
    };

    $seen = [];
    $departments = [];
    foreach ($rawDepartments as $d) {
        $key = $normDept($d);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $departments[] = $d;
        }
    }

    if ($excludeQuota) {
        $mainPdo = DB::get_connection();
        $usedStmt = $mainPdo->query("SELECT LTRIM(RTRIM(department)) FROM eheart_department_quota");
        $used = $usedStmt->fetchAll(PDO::FETCH_COLUMN);
        $usedKeys = array_map($normDept, $used);

        $departments = array_values(array_filter($departments, function ($d) use ($usedKeys, $normDept) {
            return !in_array($normDept($d), $usedKeys, true);
        }));
    }

    Response::success($departments);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
