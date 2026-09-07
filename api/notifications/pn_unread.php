<?php

require_once __DIR__ . '/../../utils/bootstrap.php';

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Response::error(
            'Method not allowed',
            405
        );
    }

    $email = trim(
        (string) (
            $_GET['email'] ?? ''
        )
    );

    if ($email === '') {
        Response::error(
            'Email is required.',
            422
        );
    }

    /*
     * IMPORTANT:
     * This endpoint is intended for the PN
     * identity bridge, not normal eHeart users.
     */

    $employee = UserService::findEmployeeByEmail($email);

    if (!$employee || empty($employee['biometric_id'])) {
        Response::success([
            'count' => 0,
            'notifications' => []
        ]);

        return;
    }

    $pdo = DB::get_connection();

    $stmt = $pdo->prepare("
        SELECT
            n.notification_id,
            n.recipient_biometric_id,
            n.notification_type,
            n.title,
            n.message,
            n.reference_type,
            n.reference_id,
            n.is_read,
            n.read_at,
            n.created_at,

            CASE
                WHEN n.reference_type IN
                    ('redemption', 'gift_certificate')
                THEN r.heart_card_id
                ELSE n.reference_id
            END AS resolved_reference_id,

            CASE
                WHEN n.notification_type IN
                    ('CARD_RECEIVED', 'CARD_REDEMPTION_REMINDER_15', 'CARD_REDEMPTION_REMINDER_25')
                THEN hc.qr_code_path
                ELSE NULL
            END AS qr_code_path

        FROM [LRNPH_HR].[dbo].[eheart_notification] n

        LEFT JOIN
            [LRNPH_HR].[dbo].[eheart_redemption] r
            ON r.redemption_id = n.reference_id
            AND n.reference_type IN
                ('redemption', 'gift_certificate')

        LEFT JOIN
            [LRNPH_HR].[dbo].[eheart_heart_card] hc
            ON hc.heart_card_id = n.reference_id
            AND n.notification_type IN
                ('CARD_RECEIVED', 'CARD_REDEMPTION_REMINDER_15', 'CARD_REDEMPTION_REMINDER_25')

        WHERE
            n.recipient_biometric_id = :bid
            AND n.is_read = 0
            AND n.notification_type IN
                ('CARD_RECEIVED', 'CARD_REDEMPTION_REMINDER_15', 'CARD_REDEMPTION_REMINDER_25')

        ORDER BY
            n.notification_id DESC
    ");

    $stmt->execute([
        ':bid' => $employee['biometric_id']
    ]);

    $notifications =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($notifications as &$notification) {
        $qrPath = trim((string) ($notification['qr_code_path'] ?? ''));

        // GRUNT: log why QR path empty. Tell you if HC row missing qr_code_path.
        if ($qrPath === '') {
            error_log(
                'PN unread: notification ' .
                    $notification['notification_id'] .
                    ' has empty qr_code_path (reference_id=' .
                    $notification['reference_id'] .
                    ', resolved_reference_id=' .
                    $notification['resolved_reference_id'] .
                    ')'
            );
            continue;
        }

        $qrFile = __DIR__ . '/../../uploads/qr/' . basename($qrPath);

        if (is_file($qrFile)) {
            $notification['qr_code_data'] =
                'data:image/png;base64,' . base64_encode(file_get_contents($qrFile));
        } else {
            // GRUNT: log missing file so you see exact path checked.
            error_log(
                'PN unread: QR file missing on disk at ' .
                    $qrFile .
                    ' for notification ' .
                    $notification['notification_id']
            );
        }
    }
    unset($notification);

    Response::success([
        'count' => count($notifications),
        'notifications' => $notifications
    ]);
} catch (Throwable $e) {

    error_log(
        'PN unread notification lookup failed: ' .
            $e->getMessage()
    );

    Response::error(
        'Unable to retrieve notifications.',
        500
    );
}
