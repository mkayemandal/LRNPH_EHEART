<?php
class GiftCertificateService
{
    public static function release(int $redemptionId, string $gcSerialNumber, string $issuedByBiometricId): int
    {
        $pdo = DB::get_connection();
        $redemption = RedemptionService::find($redemptionId);
        if (!$redemption) {
            Response::error('Redemption record not found.', 404);
        }
        if ($redemption['status'] !== 'VALIDATED') {
            Response::error('Redemption must be VALIDATED before GC release.', 409);
        }
        if (!empty($redemption['gc_serial_number'])) {
            Response::error('Gift Certificate already released for this redemption.', 409);
        }
        $amount = 300.00;
        $stmt = $pdo->prepare(
            "UPDATE [LRNPH_HR].[dbo].[eheart_redemption]
             SET gc_serial_number = :serial,
                 amount = :amount,
                 status = 'REDEEMED',
                 processed_by_biometric_id = :issuedBy,
                 processed_by_name = :issuedByName,
                 processed_at = SYSUTCDATETIME(),
                 updated_at = SYSUTCDATETIME()
             WHERE redemption_id = :redemption
               AND status = 'VALIDATED'"
        );
        $stmt->execute([
            ':redemption' => $redemptionId,
            ':serial'     => $gcSerialNumber,
            ':amount'     => $amount,
            ':issuedBy'   => $issuedByBiometricId,
            ':issuedByName' => Auth::user()['employee_name'] ?? $issuedByBiometricId,
        ]);

        $pdo->prepare(
            "UPDATE [LRNPH_HR].[dbo].[eheart_heart_card]
        SET status = 'REDEEMED', updated_at = SYSUTCDATETIME()
        WHERE heart_card_id = :id"
        )->execute([':id' => $redemption['heart_card_id']]);

        AuditService::log($issuedByBiometricId, 'RELEASE_GIFT_CERTIFICATE', 'redemption', $redemptionId, "GC {$gcSerialNumber} released, amount {$amount}");
        NotificationService::send(
            $redemption['employee_biometric_id'],
            'GC_RELEASED',
            'Gift Certificate Released',
            "Your PHP {$amount} Gift Certificate ({$gcSerialNumber}) has been released.",
            'heart_card',
            $redemption['heart_card_id']
        );
        return $redemptionId;
    }

    public static function totals(): array
    {
        $pdo = DB::get_connection();
        $stmt = $pdo->query("SELECT COUNT(*) AS total_count, SUM(amount) AS total_amount FROM [LRNPH_HR].[dbo].[eheart_redemption] WHERE status = 'REDEEMED'");
        return $stmt->fetch();
    }
}
