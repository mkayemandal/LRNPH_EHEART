<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

$user = Auth::requireLogin();
$pdo  = DB::get_connection('main_db');
$role = $user['role_code'];

$charts = [];

/* Core Value Filter */

$coreValueId = (isset($_GET['core_value_id']) && $_GET['core_value_id'] !== '')
    ? (int) $_GET['core_value_id']
    : null;

/* Reusable Core Value EXISTS clause */

$cvExists = "ISJSON(core_values) = 1 AND EXISTS (
    SELECT 1
    FROM OPENJSON(core_values) j
    WHERE TRY_CAST(j.value AS INT) = :cv_id
)";

try {

    /* MANAGER */
    if ($role === 'MANAGER') {

        /* Monthly Requests */
        $sql = "
            SELECT
                FORMAT(created_at, 'yyyy-MM') AS ym,
                COUNT(*) AS cnt
            FROM [LRNPH_HR].[dbo].[eheart_heart_card]
            WHERE requester_biometric_id = :bid
        ";

        $params = [':bid' => $user['biometric_id']];

        if ($coreValueId) {
            $sql .= " AND $cvExists";
            $params[':cv_id'] = $coreValueId;
        }

        $sql .= "
            GROUP BY FORMAT(created_at, 'yyyy-MM')
            ORDER BY ym
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $charts['monthly'] = $stmt->fetchAll();

        /* My Core Value Recognition */
        $sql = "
            SELECT
                TRY_CAST(j.value AS INT) AS core_value,
                COUNT(DISTINCT hc.heart_card_id) AS cnt
            FROM [LRNPH_HR].[dbo].[eheart_heart_card] hc
            CROSS APPLY OPENJSON(hc.core_values) j
            WHERE hc.requester_biometric_id = :bid
              AND ISJSON(hc.core_values) = 1
        ";

        $params = [':bid' => $user['biometric_id']];

        if ($coreValueId) {
            $sql .= " AND TRY_CAST(j.value AS INT) = :cv_id";
            $params[':cv_id'] = $coreValueId;
        }

        $sql .= "
            GROUP BY TRY_CAST(j.value AS INT)
            ORDER BY cnt DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $charts['core_values'] = $stmt->fetchAll();

    }

    /* HR ADMIN */
    elseif ($role === 'HR_ADMIN') {

        /* Card Status */
        $sql = "
            SELECT
                status,
                COUNT(*) AS cnt
            FROM [LRNPH_HR].[dbo].[eheart_heart_card]
        ";

        $params = [];

        if ($coreValueId) {
            $sql .= " WHERE $cvExists";
            $params[':cv_id'] = $coreValueId;
        }

        $sql .= "
            GROUP BY status
            ORDER BY cnt DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $charts['status'] = $stmt->fetchAll();

        /* Monthly Activity */
        $sql = "
            SELECT
                FORMAT(created_at, 'yyyy-MM') AS ym,
                COUNT(*) AS cnt
            FROM [LRNPH_HR].[dbo].[eheart_heart_card]
        ";

        $params = [];

        if ($coreValueId) {
            $sql .= " WHERE $cvExists";
            $params[':cv_id'] = $coreValueId;
        }

        $sql .= "
            GROUP BY FORMAT(created_at, 'yyyy-MM')
            ORDER BY ym
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $charts['monthly'] = $stmt->fetchAll();

        /* Recognition by Core Value */
        $sql = "
            SELECT
                TRY_CAST(j.value AS INT) AS core_value,
                COUNT(DISTINCT hc.heart_card_id) AS cnt
            FROM [LRNPH_HR].[dbo].[eheart_heart_card] hc
            CROSS APPLY OPENJSON(hc.core_values) j
            WHERE ISJSON(hc.core_values) = 1
        ";

        $params = [];

        if ($coreValueId) {
            $sql .= " AND TRY_CAST(j.value AS INT) = :cv_id";
            $params[':cv_id'] = $coreValueId;
        }

        $sql .= "
            GROUP BY TRY_CAST(j.value AS INT)
            ORDER BY cnt DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $charts['core_values'] = $stmt->fetchAll();

        /*
        | Redemption Trend
        | Redemption data now comes directly from eheart_redemption.
        */
        $sql = "
            SELECT
                FORMAT(
                    COALESCE(r.redeemed_at, r.processed_at, r.created_at),
                    'yyyy-MM'
                ) AS ym,
                COUNT(*) AS cnt
            FROM [LRNPH_HR].[dbo].[eheart_redemption] r
            INNER JOIN [LRNPH_HR].[dbo].[eheart_heart_card] hc
                ON hc.heart_card_id = r.heart_card_id
            WHERE r.status IN ('VALIDATED', 'REDEEMED')
        ";

        $params = [];

        if ($coreValueId) {
            $sql .= " AND $cvExists";
            $params[':cv_id'] = $coreValueId;
        }

        $sql .= "
            GROUP BY FORMAT(
                COALESCE(r.redeemed_at, r.processed_at, r.created_at),
                'yyyy-MM'
            )
            ORDER BY ym
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $charts['redemption_trend'] = $stmt->fetchAll();

    }

    /* SYSTEM ADMIN */
    else {

        /* Card Status */
        $sql = "
            SELECT
                status,
                COUNT(*) AS cnt
            FROM [LRNPH_HR].[dbo].[eheart_heart_card]
        ";

        $params = [];

        if ($coreValueId) {
            $sql .= " WHERE $cvExists";
            $params[':cv_id'] = $coreValueId;
        }

        $sql .= "
            GROUP BY status
            ORDER BY cnt DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $charts['status'] = $stmt->fetchAll();

        /* Monthly Trend */
        $sql = "
            SELECT
                FORMAT(created_at, 'yyyy-MM') AS ym,
                COUNT(*) AS cnt
            FROM [LRNPH_HR].[dbo].[eheart_heart_card]
        ";

        $params = [];

        if ($coreValueId) {
            $sql .= " WHERE $cvExists";
            $params[':cv_id'] = $coreValueId;
        }

        $sql .= "
            GROUP BY FORMAT(created_at, 'yyyy-MM')
            ORDER BY ym
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $charts['monthly'] = $stmt->fetchAll();

        /* Recognition by Core Value */
        $sql = "
            SELECT
                TRY_CAST(j.value AS INT) AS core_value,
                COUNT(DISTINCT hc.heart_card_id) AS cnt
            FROM [LRNPH_HR].[dbo].[eheart_heart_card] hc
            CROSS APPLY OPENJSON(hc.core_values) j
            WHERE ISJSON(hc.core_values) = 1
        ";

        $params = [];

        if ($coreValueId) {
            $sql .= " AND TRY_CAST(j.value AS INT) = :cv_id";
            $params[':cv_id'] = $coreValueId;
        }

        $sql .= "
            GROUP BY TRY_CAST(j.value AS INT)
            ORDER BY cnt DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $charts['core_values'] = $stmt->fetchAll();

        /* Recognition by Department */
        $sql = "
            SELECT
                receiver_department AS dept,
                COUNT(*) AS cnt,
                SUM(CASE WHEN status = 'REDEEMED' THEN 1 ELSE 0 END) AS redeemed
            FROM [LRNPH_HR].[dbo].[eheart_heart_card]
            WHERE receiver_department IS NOT NULL
        ";

        $params = [];

        if ($coreValueId) {
            $sql .= " AND $cvExists";
            $params[':cv_id'] = $coreValueId;
        }

        $sql .= "
            GROUP BY receiver_department
            ORDER BY cnt DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $charts['by_department'] = $stmt->fetchAll();
    }

    Response::success($charts);

} catch (Throwable $e) {
    error_log('CHARTS ERROR: ' . $e->getMessage());
    Response::error($e->getMessage(), 500);
}