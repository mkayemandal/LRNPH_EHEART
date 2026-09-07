<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

$user = Auth::requireLogin();
$pdo  = DB::get_connection('main_db');

$coreValueId = (isset($_GET['core_value_id']) && $_GET['core_value_id'] !== '')
    ? (int) $_GET['core_value_id']
    : null;

/*
|--------------------------------------------------------------------------
| Core Value Filter
|--------------------------------------------------------------------------
| eheart_heart_card.core_values stores a JSON array:
| ["1","3"]
|
| This reusable EXISTS condition checks whether a Heart Card contains
| the selected Core Value.
*/
$cvExists = "ISJSON(core_values) = 1 AND EXISTS (
    SELECT 1
    FROM OPENJSON(core_values) j
    WHERE TRY_CAST(j.value AS INT) = :cv_id
)";

try {

    /* MANAGER DASHBOAR */
    if ($user['role_code'] === 'MANAGER') {

        $sql = "
            SELECT
                COUNT(*) AS total_requests,
                SUM(CASE WHEN status IN ('APPROVED', 'FOR_REDEMPTION', 'REDEEMED') THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status IN ('REJECTED', 'NOT_QUALIFIED') THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN status = 'FOR_REDEMPTION' THEN 1 ELSE 0 END) AS for_redemption,
                SUM(CASE WHEN status = 'REDEEMED' THEN 1 ELSE 0 END) AS redeemed
            FROM [LRNPH_HR].[dbo].[eheart_heart_card]
            WHERE requester_biometric_id = :bid
        ";

        $params = [':bid' => $user['biometric_id']];

        if ($coreValueId) {
            $sql .= " AND $cvExists";
            $params[':cv_id'] = $coreValueId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetch());
        return;
    }

    /* HR ADMIN / SYSTEM ADMIN DASHBOARD */

    $sql = "
        SELECT
            COUNT(*) AS heart_cards_issued,
            SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending_cards,
            SUM(CASE WHEN status IN ('APPROVED', 'FOR_REDEMPTION', 'REDEEMED') THEN 1 ELSE 0 END) AS approved_cards,
            SUM(CASE WHEN status IN ('REJECTED', 'NOT_QUALIFIED') THEN 1 ELSE 0 END) AS rejected_cards,
            SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) AS active_hearts,
            SUM(CASE WHEN status = 'REDEEMED' THEN 1 ELSE 0 END) AS redeemed_hearts,
            SUM(CASE WHEN status = 'EXPIRED' THEN 1 ELSE 0 END) AS expired_hearts,
            SUM(CASE WHEN status = 'FOR_REDEMPTION' THEN 1 ELSE 0 END) AS for_redemption,
            SUM(
                CASE
                    WHEN expiry_date BETWEEN CAST(GETDATE() AS DATE) AND DATEADD(DAY, 3, CAST(GETDATE() AS DATE))
                    THEN 1
                    ELSE 0
                END
            ) AS expiring_soon,

            /*
            |--------------------------------------------------------------------------
            | TOTAL GC AMOUNT
            |--------------------------------------------------------------------------
            | Gift Certificate amount is now stored directly in eheart_redemption.amount.
            | Only actual redemption records are included.
            |--------------------------------------------------------------------------
            */
            (
                SELECT ISNULL(SUM(r.amount), 0)
                FROM [LRNPH_HR].[dbo].[eheart_redemption] r
                INNER JOIN [LRNPH_HR].[dbo].[eheart_heart_card] hc2
                    ON hc2.heart_card_id = r.heart_card_id
                WHERE r.status IN ('VALIDATED', 'REDEEMED')
                AND r.amount IS NOT NULL
                " . ($coreValueId
                    ? " AND ISJSON(hc2.core_values) = 1
                        AND EXISTS (
                            SELECT 1
                            FROM OPENJSON(hc2.core_values) j2
                            WHERE TRY_CAST(j2.value AS INT) = :cv_id_gc
                        )"
                    : "") . "
            ) AS total_gc_amount

        FROM [LRNPH_HR].[dbo].[eheart_heart_card]
    ";

    $params = [];

    if ($coreValueId) {
        $sql .= " WHERE $cvExists";
        $params[':cv_id'] = $coreValueId;
        $params[':cv_id_gc'] = $coreValueId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    Response::success($stmt->fetch());

} catch (Throwable $e) {
    error_log('SUMMARY ERROR: ' . $e->getMessage());
    Response::error($e->getMessage(), 500);
}