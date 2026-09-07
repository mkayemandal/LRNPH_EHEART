<?php

class RedemptionService
{
    /** HR validates an employee's original Heart Card for redemption. */
    public static function validate(int $heartCardId, string $hrBiometricId, string $decision, ?string $notes): array
    {
        $pdo = DB::get_connection();
        $card = HeartCardService::find($heartCardId);

        if (!$card) {
            Response::error('Heart Card not found. Verify Control Number.', 404);
        }

        // Check receiver against masterlist instead of eheart_user.
        // No local account needed for redemption anymore.
        $employee = UserService::findEmployeeByBiometricId($card['receiver_biometric_id']);
        if (!$employee) {
            Response::error('Employee not found in masterlist. Cannot validate redemption.', 404);
        }

        if ($card['status'] === 'EXPIRED') {
            Response::error('Heart Card has expired and cannot be redeemed.', 409);
        }
        if ($card['status'] === 'REDEEMED') {
            Response::error('Heart Card has already been redeemed. Duplicate redemption is not allowed.', 409);
        }
        if ($card['status'] !== 'FOR_REDEMPTION') {
            Response::error('Heart Card must be given by the Manager (status FOR_REDEMPTION) before redemption.', 409);
        }

        $existing = $pdo->prepare("SELECT * FROM [LRNPH_HR].[dbo].[eheart_redemption] WHERE heart_card_id = :id");
        $existing->execute([':id' => $heartCardId]);
        $redemption = $existing->fetch();

        $status = $decision === 'APPROVE' ? 'VALIDATED' : 'REJECTED';

        if ($redemption) {
            $stmt = $pdo->prepare(
                "UPDATE [LRNPH_HR].[dbo].[eheart_redemption]
                 SET status = :status, processed_by_biometric_id = :hr, processed_by_name = :hr_name,
                     redeemed_at = SYSUTCDATETIME(), processed_at = SYSUTCDATETIME(),
                     validation_notes = :notes, updated_at = SYSUTCDATETIME()
                 WHERE redemption_id = :id"
            );
            $stmt->execute([
                ':status'   => $status,
                ':hr'       => $hrBiometricId,
                ':hr_name'  => Auth::user()['employee_name'] ?? $hrBiometricId,
                ':notes'    => $notes,
                ':id'       => $redemption['redemption_id'],
            ]);
            $redemptionId = (int) $redemption['redemption_id'];
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO [LRNPH_HR].[dbo].[eheart_redemption]
                    (heart_card_id, heart_card_number, employee_biometric_id, employee_name,
                     processed_by_biometric_id, processed_by_name, redeemed_at, processed_at,
                     status, validation_notes)
                 VALUES
                    (:card, :card_number, :employee, :employee_name,
                     :hr, :hr_name, SYSUTCDATETIME(), SYSUTCDATETIME(), :status, :notes)"
            );
            $stmt->execute([
                ':card'         => $heartCardId,
                ':card_number'  => DateHelper::controlNumber($heartCardId),
                ':employee'     => $card['receiver_biometric_id'],
                ':employee_name' => $card['receiver_full_name'],
                ':hr'           => $hrBiometricId,
                ':hr_name'      => Auth::user()['employee_name'] ?? $hrBiometricId,
                ':status'       => $status,
                ':notes'        => $notes,
            ]);
            $redemptionId = (int) $pdo->lastInsertId();
        }

        // NOTE: Heart Card status is NO LONGER flipped to REDEEMED here.
        // It stays FOR_REDEMPTION until the Gift Certificate is actually
        // released in GiftCertificateService::release(). This is the fix:
        // previously the card was marked REDEEMED right after APPROVE,
        // before the GC was ever released, so a cancelled/abandoned
        // flow left the card permanently stuck as REDEEMED with no GC.

        AuditService::log($hrBiometricId, 'REDEEM_HEART_CARD', 'redemption', $redemptionId, "Redemption {$status} for " . DateHelper::controlNumber($heartCardId));

        if ($status === 'VALIDATED') {
            NotificationService::send(
                $card['receiver_biometric_id'],
                'REDEMPTION_APPROVED',
                'Heart Card Redemption Approved',
                'Your Heart Card redemption for ' . DateHelper::controlNumber($heartCardId) . ' has been approved.',
                'redemption',
                $redemptionId
            );
        }

        return ['redemption_id' => $redemptionId, 'status' => $status];
    }

    /** Remove a temporary VALIDATED redemption before the GC is released. */
    public static function cancel(int $redemptionId, string $hrBiometricId): array
    {
        $pdo = DB::get_connection();

        $existing = $pdo->prepare("SELECT * FROM [LRNPH_HR].[dbo].[eheart_redemption] WHERE redemption_id = :id");
        $existing->execute([':id' => $redemptionId]);
        $redemption = $existing->fetch();

        if (!$redemption) {
            Response::error('Redemption not found.', 404);
        }
        if ($redemption['status'] !== 'VALIDATED') {
            Response::error('Only a validated redemption pending GC release can be cancelled.', 409);
        }

        $stmt = $pdo->prepare(
            "DELETE FROM [LRNPH_HR].[dbo].[eheart_redemption]
             WHERE redemption_id = :id AND status = 'VALIDATED'"
        );
        $stmt->execute([':id' => $redemptionId]);

        AuditService::log($hrBiometricId, 'CANCEL_REDEMPTION', 'redemption', $redemptionId, 'Temporary redemption removed before GC release');

        return ['redemption_id' => $redemptionId, 'status' => 'REMOVED'];
    }

    public static function find(int $redemptionId): ?array
    {
        $pdo = DB::get_connection();
        $stmt = $pdo->prepare("SELECT * FROM [LRNPH_HR].[dbo].[eheart_redemption] WHERE redemption_id = :id");
        $stmt->execute([':id' => $redemptionId]);
        return $stmt->fetch() ?: null;
    }

    public static function list(?string $status = null): array
    {
        $pdo = DB::get_connection();
        $sql = "SELECT * FROM [LRNPH_HR].[dbo].[eheart_redemption] WHERE 1=1";
        $params = [];
        if ($status) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        $sql .= " ORDER BY redemption_id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
