<?php

require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    $user = Auth::requireRole(['MANAGER', 'HR', 'HR_ADMIN', 'SYSTEM_ADMIN']);
    $pdo = DB::get_connection();

    $sql = "
        SELECT
            cv.core_value_id,
            cv.core_value_name,
            cv.description,
            COUNT(DISTINCT hc.heart_card_id) AS recognition_count,
            COUNT(DISTINCT CASE WHEN hc.status = 'REDEEMED' THEN hc.heart_card_id END) AS redemption_count
        FROM [LRNPH_HR].[dbo].[eheart_core_value] cv
        LEFT JOIN [LRNPH_HR].[dbo].[eheart_heart_card] hc
            ON ISJSON(hc.core_values) = 1
            AND EXISTS (
                SELECT 1
                FROM OPENJSON(hc.core_values) j
                WHERE TRY_CAST(j.value AS INT) = cv.core_value_id
            )
    ";
    
    // Filter by role: MANAGER shows only their requested heart cards
    if ($user['role_code'] === 'MANAGER') {
        $sql .= " AND hc.requester_biometric_id = :biometric_id";
    }
    
    $sql .= "
        WHERE cv.is_active = 1
        GROUP BY cv.core_value_id, cv.core_value_name, cv.description
        ORDER BY cv.core_value_id ASC
    ";

    $stmt = $pdo->prepare($sql);
    
    if ($user['role_code'] === 'MANAGER') {
        $stmt->execute([':biometric_id' => $user['biometric_id']]);
    } else {
        $stmt->execute();
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(static function ($row) {
        return [
            'core_value_id'     => (int) $row['core_value_id'],
            'core_value_name'   => $row['core_value_name'],
            'description'       => $row['description'],
            'recognition_count' => (int) $row['recognition_count'],
            'redemption_count'  => (int) $row['redemption_count'],
        ];
    }, $rows);

    Response::success($data);
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);
}