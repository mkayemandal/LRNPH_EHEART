<?php

require_once __DIR__ . '/../../utils/bootstrap.php';

try {

    /*
     * Only logged-in users can view quota.
     * The actual request limit is still enforced
     * server-side inside HeartCardService.
     */
    $user = Auth::requireRole(['MANAGER']);

    $pdo = DB::get_connection();

    /*
     * =========================================================
     * GET MONTHLY LIMIT
     * =========================================================
     */

    $settingStmt = $pdo->prepare(
        "SELECT setting_value
         FROM eheart_system_setting
         WHERE setting_key = 'MANAGER_MONTHLY_REQUEST_LIMIT'"
    );

    $settingStmt->execute();

    $monthlyLimit = (int) ($settingStmt->fetchColumn() ?: 10);

    /*
     * =========================================================
     * GET CURRENT MONTH USAGE
     * =========================================================
     */

    $usageStmt = $pdo->prepare(
        "SELECT COUNT(*)
     FROM eheart_heart_card
     WHERE requester_biometric_id = :requester
       AND created_at >= DATEFROMPARTS(
            YEAR(GETDATE()),
            MONTH(GETDATE()),
            1
       )
       AND created_at < DATEADD(
            MONTH,
            1,
            DATEFROMPARTS(
                YEAR(GETDATE()),
                MONTH(GETDATE()),
                1
            )
       )
       AND status NOT IN ('REJECTED', 'CANCELLED')"
    );

    $usageStmt->execute([
        ':requester' => $user['biometric_id']
    ]);

    $used = (int) $usageStmt->fetchColumn();

    $remaining = max(0, $monthlyLimit - $used);

    Response::success([
        'used'       => $used,
        'limit'      => $monthlyLimit,
        'remaining'  => $remaining,
        'is_exceeded' => $used >= $monthlyLimit,
        'month'      => date('Y-m')
    ]);
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
