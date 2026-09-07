<?php
/**
 * ============================================================
 * HEART CARD REDEMPTION REMINDER (SQL AGENT / CLI)
 * ============================================================
 *
 * Fires day-15 and day-25 reminder notifications (DB row +
 * email) for Heart Cards that are approved and waiting to be
 * redeemed but haven't been redeemed yet.
 *
 * "Waiting to be redeemed" = eheart_heart_card.status = 'FOR_REDEMPTION'
 * "Redeemed"               = eheart_heart_card.status = 'REDEEMED'
 * (PENDING / REJECTED cards are excluded - they were never issued
 *  to the receiver in a redeemable state.)
 *
 * Day count is measured from eheart_heart_card.created_at,
 * matching the "DATE ISSUED" field already shown on the Heart
 * Card email template.
 */

require_once __DIR__ . '/../utils/bootstrap.php';

const REMINDER_MILESTONES = [
    15 => 'CARD_REDEMPTION_REMINDER_15',
    25 => 'CARD_REDEMPTION_REMINDER_25',
];

try {
    $pdo = DB::get_connection();

    foreach (REMINDER_MILESTONES as $days => $notificationType) {
        $stmt = $pdo->prepare("
            SELECT
                hc.heart_card_id,
                hc.receiver_biometric_id,
                hc.created_at,
                hc.expiry_date
            FROM [LRNPH_HR].[dbo].[eheart_heart_card] hc
            WHERE
                hc.status = 'FOR_REDEMPTION'
                AND hc.receiver_biometric_id IS NOT NULL
                AND DATEDIFF(day, hc.created_at, SYSUTCDATETIME()) = :days
                AND NOT EXISTS (
                    SELECT 1
                    FROM [LRNPH_HR].[dbo].[eheart_notification] n
                    WHERE n.recipient_biometric_id = hc.receiver_biometric_id
                      AND n.notification_type = :notifType
                      AND n.reference_type = 'heart_card'
                      AND n.reference_id = hc.heart_card_id
                )
        ");

        $stmt->execute([
            ':days' => $days,
            ':notifType' => $notificationType,
        ]);

        $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cards as $card) {
            // Prefer the real expiry_date if set, otherwise fall back
            // to a flat 30-day assumption from created_at.
            $daysLeft = null;

            if (!empty($card['expiry_date'])) {
                try {
                    $expiry = new DateTime((string) $card['expiry_date']);
                    $today = new DateTime('now', new DateTimeZone('UTC'));
                    $daysLeft = max(0, (int) $today->diff($expiry)->format('%r%a'));
                } catch (Throwable $e) {
                    $daysLeft = 30 - $days;
                }
            } else {
                $daysLeft = 30 - $days;
            }

            $title = $days === 25
                ? 'Last Call: Redeem Your Heart Card'
                : 'Reminder: Redeem Your Heart Card';

            $message = "You still have an unredeemed Heart Card. "
                . "It expires in {$daysLeft} day(s). "
                . "Please proceed to HR and bring your Employee ID / Biometrics ID to redeem it.";

            NotificationService::send(
                (string) $card['receiver_biometric_id'],
                $notificationType,
                $title,
                $message,
                'heart_card',
                (int) $card['heart_card_id']
            );

            error_log(
                "RedemptionReminder: sent {$notificationType} for heart_card_id="
                . $card['heart_card_id'] . " to biometric_id="
                . $card['receiver_biometric_id']
            );
        }
    }
} catch (Throwable $e) {
    error_log('RedemptionReminder: FAILED - ' . $e->getMessage());
}