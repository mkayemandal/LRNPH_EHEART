<?php

require_once __DIR__ . '/../../utils/bootstrap.php';

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error(
            'Method not allowed',
            405
        );
    }

    $input = requestBody();

    $notificationId = (int) (
        $input['notification_id'] ?? 0
    );

    $email = trim(
        (string) (
            $input['email'] ?? ''
        )
    );

    if ($notificationId <= 0) {
        Response::error(
            'Invalid notification ID.',
            422
        );
    }

    if ($email === '') {
        Response::error(
            'Email is required.',
            422
        );
    }

    $employee = UserService::findEmployeeByEmail($email);

    if (!$employee || empty($employee['biometric_id'])) {
        Response::success([
            'notification_id' => $notificationId,
            'marked_read' => false
        ]);

        return;
    }

    $pdo = DB::get_connection();

    $stmt = $pdo->prepare("
        UPDATE
            [LRNPH_HR].[dbo].[eheart_notification]

        SET
            is_read = 1,
            read_at = SYSUTCDATETIME()

        WHERE
            notification_id = :notification_id

            AND recipient_biometric_id = :bid
    ");

    $stmt->execute([
        ':notification_id' => $notificationId,
        ':bid' => $employee['biometric_id']
    ]);

    Response::success([
        'notification_id' => $notificationId,
        'marked_read' => $stmt->rowCount() > 0
    ]);
} catch (Throwable $e) {

    error_log(
        'PN notification mark-read failed: ' .
            $e->getMessage()
    );

    Response::error(
        'Unable to mark notification as read.',
        500
    );
}
