<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    $user = Auth::requireRole(['HR_ADMIN', 'SYSTEM_ADMIN']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }

    $input = requestBody();
    $missing = Validation::requireFields($input, ['department', 'monthly_quota', 'headcount']);
    if ($missing) {
        Response::error('Missing fields: ' . implode(', ', $missing), 422);
    }

    $department = trim((string) $input['department']);
    $quota = (int) $input['monthly_quota'];
    $headcount = (int) $input['headcount'];

    if ($department === '') {
        Response::error('Department is required.', 422);
    }
    if ($quota <= 0) {
        Response::error('Monthly quota must be greater than 0.', 422);
    }
    if ($headcount < 0) {
        Response::error('Headcount cannot be negative.', 422);
    }

    $pdo = DB::get_connection();

    $exists = $pdo->prepare("
        SELECT COUNT(*) FROM eheart_department_quota
        WHERE UPPER(LTRIM(RTRIM(REPLACE(department, '-', ' ')))) =
              UPPER(LTRIM(RTRIM(REPLACE(:dept, '-', ' '))))
    ");
    $exists->execute([':dept' => $department]);

    if ((int) $exists->fetchColumn() > 0) {
        $stmt = $pdo->prepare("
            UPDATE eheart_department_quota
            SET monthly_quota = :quota, headcount = :headcount, is_active = 1, updated_at = SYSDATETIME()
            WHERE UPPER(LTRIM(RTRIM(REPLACE(department, '-', ' ')))) =
                  UPPER(LTRIM(RTRIM(REPLACE(:dept, '-', ' '))))
        ");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO eheart_department_quota (department, monthly_quota, headcount, is_active)
            VALUES (:dept, :quota, :headcount, 1)
        ");
    }

    $stmt->execute([':dept' => $department, ':quota' => $quota, ':headcount' => $headcount]);

    AuditService::log(
        $user['biometric_id'],
        'UPDATE_DEPARTMENT_QUOTA',
        'department_quota',
        0,
        $department . ' set to ' . $quota
    );

    Response::success(null, 'Department quota saved.');
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
