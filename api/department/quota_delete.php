<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    $user = Auth::requireRole(['HR_ADMIN', 'SYSTEM_ADMIN']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }

    $input = requestBody();
    $missing = Validation::requireFields($input, ['department']);
    if ($missing) {
        Response::error('Missing fields: ' . implode(', ', $missing), 422);
    }

    $department = trim((string) $input['department']);
    if ($department === '') {
        Response::error('Department is required.', 422);
    }

    $pdo = DB::get_connection();

    $stmt = $pdo->prepare("
        DELETE FROM eheart_department_quota
        WHERE UPPER(LTRIM(RTRIM(REPLACE(department, '-', ' ')))) =
              UPPER(LTRIM(RTRIM(REPLACE(:dept, '-', ' '))))
    ");
    $stmt->execute([':dept' => $department]);

    if ($stmt->rowCount() === 0) {
        Response::error('Department quota not found.', 404);
    }

    AuditService::log(
        $user['biometric_id'],
        'DELETE_DEPARTMENT_QUOTA',
        'department_quota',
        0,
        'Deleted quota for ' . $department
    );

    Response::success(null, 'Department quota deleted.');
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
