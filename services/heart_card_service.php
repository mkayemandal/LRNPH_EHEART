<?php
class HeartCardService
{
    private static function normDept(string $d): string
    {
        $d = preg_replace('/[^A-Za-z0-9]+/', ' ', $d);
        $d = preg_replace('/\s+/', ' ', $d);
        return strtoupper(trim($d));
    }

    // outside world need this to bust dept quota cache key. same norm rule.
    public static function normDeptPublic(string $d): string
    {
        return self::normDept($d);
    }

    public static function createRequest(
        array $input,
        string $requesterBiometricId,
        ?string $requesterFullName = null
    ): int {

        $pdo = DB::get_connection();

        // use cached setting. no raw query, no round trip most the time.
        $monthlyLimit = (int) self::settingValue('MANAGER_MONTHLY_REQUEST_LIMIT', 10);

        $pdo->beginTransaction();

        try {

            $receiverBiometricId = trim((string) ($input['receiver_biometric_id'] ?? ''));
            $receiverDepartment = trim((string) ($input['receiver_department'] ?? ''));
            // $managerDepartment was only used by dead/commented internal-external
            // quota logic below. cross-server query for nothing, cut it.
            // if that logic come back, un-comment and call self::getEmployeeDepartment() again.

            $lockStmt = $pdo->prepare(
                "DECLARE @lockResult INT;

             EXEC @lockResult = sp_getapplock
                 @Resource = :resource,
                 @LockMode = 'Exclusive',
                 @LockOwner = 'Transaction',
                 @LockTimeout = 5000;

             IF @lockResult < 0
                 THROW 50001,
                       'Unable to secure quota lock.',
                       1;"
            );

            $lockStmt->execute([
                ':resource' => 'EHEART_MANAGER_QUOTA_' . $requesterBiometricId
            ]);

            if ($receiverDepartment !== '') {
                $lockStmt->execute([
                    ':resource' => 'EHEART_DEPARTMENT_QUOTA_' . $receiverDepartment
                ]);
            }

            if ($receiverBiometricId !== '') {
                $lockStmt->execute([
                    ':resource' => 'EHEART_EMPLOYEE_QUOTA_' . $receiverBiometricId
                ]);
            }

            /*
         * =====================================================
         * COUNT THIS MANAGER'S REQUESTS THIS MONTH
         * =====================================================
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
                ':requester' => $requesterBiometricId
            ]);

            $currentUsage = (int) $usageStmt->fetchColumn();

            /*
         * =====================================================
         * ENFORCE MONTHLY LIMIT
         * =====================================================
         */

            if ($currentUsage >= $monthlyLimit) {

                $pdo->rollBack();

                throw new RuntimeException(
                    'You have reached your monthly EHEART request limit of '
                        . $monthlyLimit
                        . ' requests. Please try again next month.'
                );
            }

            /*
         * =====================================================
         * ENFORCE DEPARTMENT QUOTA
         * =====================================================
         */

            if ($receiverDepartment !== '') {
                $departmentLimit = self::getDepartmentQuota($receiverDepartment);

                if ($departmentLimit !== null) {
                    $departmentUsage = self::getDepartmentMonthlyUsage($receiverDepartment);

                    if ($departmentUsage >= $departmentLimit) {
                        $pdo->rollBack();

                        throw new RuntimeException(
                            'This department has reached its monthly EHEART quota of '
                                . $departmentLimit
                                . ' requests. Please try again next month.'
                        );
                    }

                    // $halfLimit = $departmentLimit * 0.5;

                    // if ($managerDepartment !== '' && $managerDepartment === $receiverDepartment) {
                    //     $internalUsage = self::getDepartmentInternalUsage($receiverDepartment);

                    //     if ($internalUsage >= $halfLimit) {
                    //         $pdo->rollBack();

                    //         throw new RuntimeException(
                    //             'This department has reached its internal-request cap of 50% of its monthly EHEART quota.'
                    //         );
                    //     }
                    // } elseif ($managerDepartment !== '' && $managerDepartment !== $receiverDepartment) {
                    //     $externalLimit = $halfLimit;
                    //     $externalUsage = self::getDepartmentExternalUsage($receiverDepartment);

                    //     if ($externalUsage >= $externalLimit) {
                    //         $pdo->rollBack();

                    //         throw new RuntimeException(
                    //             'This department has reached its external-request cap of 50% of its monthly EHEART quota.'
                    //         );
                    //     }
                    // }
                }
            }

            /*
         * =====================================================
         * ENFORCE EMPLOYEE MONTHLY EHEART LIMIT
         * =====================================================
         */

            if ($receiverBiometricId !== '') {
                $employeeLimit = (int) self::settingValue(
                    'EMPLOYEE_MONTHLY_EHEART_LIMIT',
                    1
                );

                $employeeUsage = self::getEmployeeMonthlyUsage($receiverBiometricId);

                if ($employeeUsage >= $employeeLimit) {
                    $pdo->rollBack();

                    throw new RuntimeException(
                        'This employee has reached the monthly EHEART limit of '
                            . $employeeLimit
                            . ' card(s).'
                    );
                }
            }

            /*
         * =====================================================
         * INSERT REQUEST
         * =====================================================
         */

            $stmt = $pdo->prepare(
                "INSERT INTO eheart_heart_card
                (
                    requester_biometric_id,
                    requester_full_name,
                    receiver_biometric_id,
                    receiver_full_name,
                    receiver_department,
                    core_values,
                    action_performed,
                    business_impact,
                    why_beyond_normal,
                    status
                )
             VALUES
                (
                    :requester,
                    :requester_full_name,
                    :receiver,
                    :receiver_full_name,
                    :receiver_department,
                    :core_values,
                    :action,
                    :impact,
                    :why,
                    'PENDING'
                )"
            );

            $stmt->execute([
                ':requester'           => $requesterBiometricId,
                ':requester_full_name' => $requesterFullName ?? $requesterBiometricId,
                ':receiver'            => $input['receiver_biometric_id'],
                ':receiver_full_name'  => $input['receiver_full_name'],
                ':receiver_department' => $input['receiver_department'],
                ':core_values'         => Json::encodeIds($input['core_values']),
                ':action'              => $input['action_performed'],
                ':impact'              => $input['business_impact'],
                ':why'                 => $input['why_beyond_normal'],
            ]);

            $heartCardId = (int) $pdo->lastInsertId();

            /*
         * =====================================================
         * COMMIT
         * =====================================================
         */

            $pdo->commit();

            /*
         * =====================================================
         * AUDIT LOG
         * =====================================================
         */

            AuditService::log(
                $requesterBiometricId,
                'SUBMIT_REQUEST',
                'heart_card',
                $heartCardId,
                'Recognition request submitted'
            );

            /*
         * =====================================================
         * NOTIFICATION
         * =====================================================
         */

            NotificationService::sendToRoles(
                ['HR_ADMIN', 'SYSTEM_ADMIN'],
                'REQUEST_SUBMITTED',
                'New Recognition Request',
                ($requesterFullName ?? $requesterBiometricId) .
                    ' submitted a new Heart Card request (' .
                    DateHelper::controlNumber($heartCardId) .
                    ') for ' .
                    ($input['receiver_full_name'] ?? 'an employee') .
                    '. It is pending validation.',
                'heart_card',
                $heartCardId
            );

            return $heartCardId;
        } catch (\Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function approve(
        int $heartCardId,
        string $approvedBy
    ): int {
        $pdo = DB::get_connection();
        $card = self::find($heartCardId);
        if (!$card) {
            Response::error('Heart Card not found.', 404);
        }
        if ($card['status'] !== 'PENDING') {
            Response::error('Only PENDING requests may be approved.', 409);
        }
        $stmt = $pdo->prepare(
            "UPDATE [LRNPH_HR].[dbo].[eheart_heart_card]
             SET
                status = 'APPROVED',
                reviewed_by_biometric_id = :reviewed_by,
                reviewed_at = SYSUTCDATETIME(),
                review_feedback = NULL,
                updated_at = SYSUTCDATETIME()
             WHERE heart_card_id = :id"
        );
        $stmt->execute([
            ':reviewed_by' => $approvedBy,
            ':id' => $heartCardId
        ]);
        $controlNumber = DateHelper::controlNumber($heartCardId);
        AuditService::log(
            $approvedBy,
            'APPROVE_HEART_CARD',
            'heart_card',
            $heartCardId,
            'Heart Card approved by HR and returned to Manager for Short Inspiring Note'
        );
        NotificationService::send(
            $card['requester_biometric_id'],
            'REQUEST_APPROVED',
            'Heart Card Approved',
            'Your Heart Card request ' . $controlNumber . ' has been approved by HR. Please open the approved request and add the Short Inspiring Note before sending it to the employee.',
            'heart_card',
            $heartCardId
        );
        return $heartCardId;
    }
    public static function reject(
        int $heartCardId,
        string $reason,
        string $rejectedBy
    ): int {
        $pdo = DB::get_connection();
        $card = self::find($heartCardId);
        if (!$card) {
            Response::error('Heart Card not found.', 404);
        }
        if ($card['status'] !== 'PENDING') {
            Response::error('Only PENDING requests may be rejected.', 409);
        }
        $reason = trim($reason);
        if ($reason === '') {
            Response::error('Reason for rejection is required.', 422);
        }
        $stmt = $pdo->prepare(
            "UPDATE [LRNPH_HR].[dbo].[eheart_heart_card]
             SET
                status = 'REJECTED',
                reviewed_by_biometric_id = :reviewed_by,
                reviewed_at = SYSUTCDATETIME(),
                review_feedback = :reason,
                updated_at = SYSUTCDATETIME()
             WHERE heart_card_id = :id"
        );
        $stmt->execute([
            ':reviewed_by' => $rejectedBy,
            ':reason' => $reason,
            ':id' => $heartCardId
        ]);
        $controlNumber = DateHelper::controlNumber($heartCardId);
        AuditService::log(
            $rejectedBy,
            'REJECT_HEART_CARD',
            'heart_card',
            $heartCardId,
            'Heart Card rejected: ' . $reason
        );
        NotificationService::send(
            $card['requester_biometric_id'],
            'REQUEST_REJECTED',
            'Heart Card Rejected',
            $controlNumber . ' was rejected by HR: ' . $reason,
            'heart_card',
            $heartCardId
        );
        return $heartCardId;
    }
    public static function requestRevision(
        int $heartCardId,
        string $reason,
        string $requestedBy
    ): int {
        $pdo = DB::get_connection();
        $card = self::find($heartCardId);
        if (!$card) {
            Response::error('Heart Card not found.', 404);
        }
        if ($card['status'] !== 'PENDING') {
            Response::error('Only PENDING requests may be sent for revision.', 409);
        }
        $reason = trim($reason);
        if ($reason === '') {
            Response::error('Revision notes are required.', 422);
        }
        $stmt = $pdo->prepare(
            "UPDATE [LRNPH_HR].[dbo].[eheart_heart_card]
             SET
                status = 'FOR_REVISION',
                reviewed_by_biometric_id = :reviewed_by,
                reviewed_at = SYSUTCDATETIME(),
                review_feedback = :reason,
                updated_at = SYSUTCDATETIME()
             WHERE heart_card_id = :id"
        );
        $stmt->execute([
            ':reviewed_by' => $requestedBy,
            ':reason' => $reason,
            ':id' => $heartCardId
        ]);
        $controlNumber = DateHelper::controlNumber($heartCardId);
        AuditService::log(
            $requestedBy,
            'REQUEST_REVISION',
            'heart_card',
            $heartCardId,
            'Revision requested: ' . $reason
        );
        NotificationService::send(
            $card['requester_biometric_id'],
            'REVISION_REQUESTED',
            'Revision Requested',
            $controlNumber . ' needs revision: ' . $reason,
            'heart_card',
            $heartCardId
        );
        return $heartCardId;
    }

    public static function resubmit(
        int $heartCardId,
        array $input,
        string $requesterBiometricId,
        string $requesterFullName
    ): int {
        $pdo = DB::get_connection();
        $card = self::find($heartCardId);
        if (!$card) {
            Response::error('Heart Card not found.', 404);
        }
        if ($card['status'] !== 'FOR_REVISION') {
            Response::error('Only requests marked FOR_REVISION may be resubmitted.', 409);
        }
        if ((string) $card['requester_biometric_id'] !== (string) $requesterBiometricId) {
            Response::error('Only the original requester may resubmit this Heart Card.', 403);
        }
        $stmt = $pdo->prepare(
            "UPDATE [LRNPH_HR].[dbo].[eheart_heart_card]
             SET
                receiver_biometric_id = :receiver,
                requester_full_name = :requester_name,
                receiver_department = :department,
                receiver_full_name = :full_name,
                core_values = :core_values,
                action_performed = :action,
                business_impact = :impact,
                why_beyond_normal = :why,
                short_inspiring_note = NULL,
                qr_code_path = NULL,
                expiry_date = NULL,
                status = 'PENDING',
                review_feedback = NULL,
                updated_at = SYSUTCDATETIME()
             WHERE heart_card_id = :id"
        );
        $stmt->execute([
            ':receiver' => $input['receiver_biometric_id'],
            ':requester_name' => $requesterFullName,
            ':department' => $input['receiver_department'],
            ':full_name' => $input['receiver_full_name'],
            ':core_values' => Json::encodeIds($input['core_values']),
            ':action' => $input['action_performed'],
            ':impact' => $input['business_impact'],
            ':why' => $input['why_beyond_normal'],
            ':id' => $heartCardId
        ]);
        AuditService::log(
            $requesterBiometricId,
            'RESUBMIT_REQUEST',
            'heart_card',
            $heartCardId,
            'Heart Card resubmitted after revision'
        );
        NotificationService::sendToRoles(
            ['HR_ADMIN', 'SYSTEM_ADMIN'],
            'REQUEST_RESUBMITTED',
            'Heart Card Resubmitted',
            DateHelper::controlNumber($heartCardId) . ' has been resubmitted and is waiting for HR approval.',
            'heart_card',
            $heartCardId
        );
        return $heartCardId;
    }
    public static function managerGive(
        int $heartCardId,
        string $note,
        string $managerBiometricId
    ): void {
        $pdo = DB::get_connection();
        $card = self::find($heartCardId);
        if (!$card) {
            Response::error('Heart Card not found.', 404);
        }
        if ((string) $card['requester_biometric_id'] !== (string) $managerBiometricId) {
            Response::error('Only the Manager who created this request can send the Heart Card to the employee.', 403);
        }
        if ($card['status'] !== 'APPROVED') {
            Response::error('Only HR-approved Heart Cards may be sent to the employee.', 409);
        }
        $note = trim($note);
        if ($note === '') {
            Response::error('Short Inspiring Note is required.', 422);
        }
        if (mb_strlen($note) > 140) {
            Response::error('Short Inspiring Note must not exceed 140 characters.', 422);
        }
        $validityDays = 30;
        $expiry = DateHelper::addDays(date('Y-m-d'), $validityDays);
        $controlNumber = DateHelper::controlNumber($heartCardId);
        $qrPublicPath = self::generateAndStoreQrCode($heartCardId, $controlNumber);
        $stmt = $pdo->prepare(
            "UPDATE [LRNPH_HR].[dbo].[eheart_heart_card]
             SET
                short_inspiring_note = :note,
                status = 'FOR_REDEMPTION',
                expiry_date = :expiry,
                qr_code_path = :qr_path,
                updated_at = SYSUTCDATETIME()
             WHERE heart_card_id = :id"
        );
        $stmt->execute([
            ':note' => $note,
            ':expiry' => $expiry,
            ':qr_path' => $qrPublicPath,
            ':id' => $heartCardId
        ]);
        AuditService::log(
            $managerBiometricId,
            'MANAGER_GIVE_HEART_CARD',
            'heart_card',
            $heartCardId,
            'Manager added Short Inspiring Note and sent Heart Card to employee for redemption'
        );
        NotificationService::send(
            $card['receiver_biometric_id'],
            'CARD_RECEIVED',
            'You Received a Heart Card!',
            "Congratulations! You have received a Heart Card from " .
                ($card['requester_full_name'] ?: 'your manager') .
                " in recognition of your outstanding performance and valuable contribution.\n\n" .
                "Short Inspiring Note:\n" .
                $note,
            'heart_card',
            $heartCardId
        );
    }

    private static function generateAndStoreQrCode(
        int $heartCardId,
        string $controlNumber
    ): string {
        $qrCode = new \Endroid\QrCode\QrCode(
            data: $controlNumber,
            size: 300,
            margin: 10
        );
        $writer = new \Endroid\QrCode\Writer\PngWriter();
        $result = $writer->write($qrCode);
        $qrDir = __DIR__ . '/../uploads/qr/';
        if (!is_dir($qrDir)) {
            mkdir($qrDir, 0755, true);
        }
        $qrFileName = $heartCardId . '.png';
        $qrFilePath = $qrDir . $qrFileName;
        $result->saveToFile($qrFilePath);
        $qrPublicPath = '/eheart/uploads/qr/' . $qrFileName;
        return $qrPublicPath;
    }

    public static function find(int $heartCardId): ?array
    {
        $pdo = DB::get_connection();
        $stmt = $pdo->prepare(
            "SELECT hc.*,
            rev.full_name AS reviewed_by_full_name,
            red.processed_by_biometric_id,
            red.processed_by_name,
            red.redeemed_at,
            red.amount
         FROM [LRNPH_HR].[dbo].[eheart_heart_card] hc
         LEFT JOIN [LRNPH_HR].[dbo].[eheart_user] rev
             ON rev.biometric_id = hc.reviewed_by_biometric_id
         OUTER APPLY (
             SELECT TOP 1
                r.processed_by_biometric_id,
                r.processed_by_name,
                r.redeemed_at,
                r.amount
             FROM [LRNPH_HR].[dbo].[eheart_redemption] r
             WHERE r.heart_card_id = hc.heart_card_id
               AND r.status IN ('VALIDATED', 'REDEEMED')
             ORDER BY r.redemption_id DESC
         ) red
         WHERE hc.heart_card_id = :id"
        );
        $stmt->execute([':id' => $heartCardId]);
        $row = $stmt->fetch();

        if ($row) {
            $row['core_values'] = Json::decodeIds($row['core_values']);
            $row['control_number'] = DateHelper::controlNumber((int) $row['heart_card_id']);
            self::autoExpire($row);

            $employeeIds = self::batchGetEmployeeIds([
                $row['requester_biometric_id'],
                $row['receiver_biometric_id'],
                $row['reviewed_by_biometric_id'],
                $row['processed_by_biometric_id']
            ]);

            $row['requester_employee_id'] = $employeeIds[$row['requester_biometric_id']] ?? null;
            $row['receiver_employee_id']  = $employeeIds[$row['receiver_biometric_id']] ?? null;
            $row['reviewed_by_employee_id'] = $employeeIds[$row['reviewed_by_biometric_id']] ?? null;
            $row['processed_by_employee_id'] = $employeeIds[$row['processed_by_biometric_id']] ?? null;
        }

        return $row ?: null;
    }

    public static function findAvailableForRedemption(string $employeeId): ?array
    {
        $pdo = DB::get_connection();
        $stmt = $pdo->prepare(
            "SELECT *
             FROM [LRNPH_HR].[dbo].[eheart_heart_card]
             WHERE receiver_biometric_id = :employeeId
               AND status = 'FOR_REDEMPTION'
             ORDER BY heart_card_id ASC"
        );
        $stmt->execute([':employeeId' => $employeeId]);
        foreach ($stmt->fetchAll() as $card) {
            self::autoExpire($card);
            if ($card['status'] !== 'FOR_REDEMPTION') {
                continue;
            }
            return [
                'employee' => [
                    'biometric_id' => $card['receiver_biometric_id'],
                    'name' => $card['receiver_full_name'],
                    'department' => $card['receiver_department']
                ],
                'heart_card' => [
                    'heart_card_id' => (int) $card['heart_card_id'],
                    'code' => DateHelper::controlNumber((int) $card['heart_card_id']),
                    'brand' => null,
                    'reward' => 'Gift Certificate',
                    'amount' => 300.00,
                    'issue_date' => $card['updated_at'],
                    'status' => 'READY FOR REDEMPTION'
                ]
            ];
        }
        return null;
    }

    public static function findAvailableForRedemptionByControlNumber(string $controlNumber): ?array
    {
        $digits = preg_replace('/[^0-9]/', '', $controlNumber);
        if ($digits === '') {
            return null;
        }
        $heartCardId = (int) ltrim($digits, '0');
        if ($heartCardId <= 0) {
            return null;
        }
        $pdo = DB::get_connection();
        $stmt = $pdo->prepare(
            "SELECT *
             FROM [LRNPH_HR].[dbo].[eheart_heart_card]
             WHERE heart_card_id = :id"
        );
        $stmt->execute([':id' => $heartCardId]);
        $card = $stmt->fetch();
        if (!$card) {
            return null;
        }
        self::autoExpire($card);
        if ($card['status'] !== 'FOR_REDEMPTION') {
            return null;
        }
        return [
            'employee' => [
                'biometric_id' => $card['receiver_biometric_id'],
                'name' => $card['receiver_full_name'],
                'department' => $card['receiver_department']
            ],
            'heart_card' => [
                'heart_card_id' => (int) $card['heart_card_id'],
                'code' => DateHelper::controlNumber((int) $card['heart_card_id']),
                'brand' => null,
                'reward' => 'Gift Certificate',
                'amount' => 300.00,
                'issue_date' => $card['updated_at'],
                'status' => 'READY FOR REDEMPTION'
            ]
        ];
    }

    public static function list(
        ?string $status = null,
        ?string $department = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $userRole = null,
        ?string $userBiometricId = null
    ): array {
        $pdo = DB::get_connection();
        $sql = "
    SELECT hc.*,
        COALESCE(hc.requester_full_name, req.full_name) AS requester_full_name,
        rev.full_name AS reviewed_by_full_name,
        red.processed_by_biometric_id,
        red.processed_by_name,
        red.redeemed_at,
        red.amount
    FROM [LRNPH_HR].[dbo].[eheart_heart_card] hc
    LEFT JOIN [LRNPH_HR].[dbo].[eheart_user] req
        ON req.biometric_id = hc.requester_biometric_id
    LEFT JOIN [LRNPH_HR].[dbo].[eheart_user] rev
        ON rev.biometric_id = hc.reviewed_by_biometric_id
    OUTER APPLY (
        SELECT TOP 1
            r.processed_by_biometric_id,
            r.processed_by_name,
            r.redeemed_at,
            r.amount
        FROM [LRNPH_HR].[dbo].[eheart_redemption] r
        WHERE r.heart_card_id = hc.heart_card_id
          AND r.status IN ('VALIDATED', 'REDEEMED')
        ORDER BY r.redemption_id DESC
    ) red
    WHERE 1=1
    ";
        $params = [];

        if ($userRole === 'MANAGER' && $userBiometricId) {
            $sql .= " AND hc.requester_biometric_id = :userBiometricId";
            $params[':userBiometricId'] = $userBiometricId;
        }

        if ($status) {
            $sql .= " AND hc.status = :status";
            $params[':status'] = $status;
        }
        if ($department) {
            $sql .= " AND hc.receiver_department = :department";
            $params[':department'] = $department;
        }
        if ($dateFrom) {
            $sql .= " AND hc.created_at >= :dateFrom";
            $params[':dateFrom'] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= " AND hc.created_at < DATEADD(day, 1, CAST(:dateTo AS date))";
            $params[':dateTo'] = $dateTo;
        } elseif ($dateFrom) {
            $sql .= " AND hc.created_at < DATEADD(day, 1, CAST(:dateFromEnd AS date))";
            $params[':dateFromEnd'] = $dateFrom;
        }
        $sql .= " ORDER BY hc.heart_card_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Batch-fetch employee_id for all requester + receiver biometric IDs in one shot
        $allBiometricIds = [];
        foreach ($rows as $row) {
            if (!empty($row['requester_biometric_id'])) $allBiometricIds[] = $row['requester_biometric_id'];
            if (!empty($row['receiver_biometric_id'])) $allBiometricIds[] = $row['receiver_biometric_id'];
        }
        $employeeIds = self::batchGetEmployeeIds($allBiometricIds);

        foreach ($rows as &$row) {
            $row['core_values'] = Json::decodeIds($row['core_values']);
            $row['control_number'] = DateHelper::controlNumber((int) $row['heart_card_id']);
            self::autoExpire($row);

            $row['requester_employee_id'] = $employeeIds[$row['requester_biometric_id']] ?? null;
            $row['receiver_employee_id'] = $employeeIds[$row['receiver_biometric_id']] ?? null;
        }
        unset($row);

        return $rows;
    }

    private static function autoExpire(array &$row): void
    {
        if (
            $row['status'] === 'FOR_REDEMPTION' &&
            !empty($row['expiry_date']) &&
            DateHelper::isExpired($row['expiry_date'])
        ) {
            $pdo = DB::get_connection();
            $stmt = $pdo->prepare(
                "UPDATE [LRNPH_HR].[dbo].[eheart_heart_card]
                 SET
                    status = 'EXPIRED',
                    updated_at = SYSUTCDATETIME()
                 WHERE heart_card_id = :id"
            );
            $stmt->execute([':id' => $row['heart_card_id']]);
            $row['status'] = 'EXPIRED';
        }
    }

    public static function settingValue(
        string $key,
        $default = null
    ) {
        // settings barely change. cache 60s. no DB hit each call.
        $value = CacheService::remember('eh_setting_' . $key, 60, function () use ($key) {
            $pdo = DB::get_connection();
            $stmt = $pdo->prepare(
                "SELECT setting_value
                 FROM [LRNPH_HR].[dbo].[eheart_system_setting]
                 WHERE setting_key = :key"
            );
            $stmt->execute([':key' => $key]);
            $row = $stmt->fetch();
            return $row ? $row['setting_value'] : null;
        });

        return $value !== null ? $value : $default;
    }

    public static function getManagerMonthlyUsage(
        string $managerBiometricId
    ): int {
        $pdo = DB::get_connection();

        $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM [LRNPH_HR].[dbo].[eheart_heart_card]
        WHERE requester_biometric_id = :biometric_id
          AND created_at >= DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1)
          AND created_at < DATEADD(
                MONTH,
                1,
                DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1)
          )
          AND status NOT IN ('REJECTED', 'CANCELLED')
    ");

        $stmt->execute([
            ':biometric_id' => $managerBiometricId
        ]);

        return (int) $stmt->fetchColumn();
    }

    public static function getDepartmentMonthlyUsage(
        string $department
    ): int {
        $pdo = DB::get_connection();

        $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM [LRNPH_HR].[dbo].[eheart_heart_card]
        WHERE UPPER(LTRIM(RTRIM(REPLACE(receiver_department, '-', ' ')))) =
              UPPER(LTRIM(RTRIM(REPLACE(:department, '-', ' '))))
          AND created_at >= DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1)
          AND created_at < DATEADD(
                MONTH,
                1,
                DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1)
          )
          AND status NOT IN ('REJECTED', 'CANCELLED')
    ");

        $stmt->execute([
            ':department' => $department
        ]);

        return (int) $stmt->fetchColumn();
    }

    public static function getDepartmentExternalUsage(
        string $department
    ): int {
        return self::getDepartmentUsageBySide($department, false);
    }

    public static function getDepartmentInternalUsage(
        string $department
    ): int {
        return self::getDepartmentUsageBySide($department, true);
    }

    private static function batchGetEmployeeIds(array $biometricIds): array
    {
        $uniqueIds = array_values(array_unique(array_filter($biometricIds)));

        if (!$uniqueIds) {
            return [];
        }

        // check cache first. only ask DB for id we don't got yet.
        $map = [];
        $missingIds = [];

        foreach ($uniqueIds as $id) {
            $cacheKey = 'eh_emp_id_' . $id;
            $hit = false;

            if (function_exists('apcu_fetch')) {
                $val = apcu_fetch($cacheKey, $hit);
                if ($hit) {
                    $map[$id] = $val;
                }
            }

            if (!$hit) {
                $missingIds[] = $id;
            }
        }

        if ($missingIds) {
            $mePdo = DB::get_connection('lrnph_e');

            $placeholders = [];
            $params = [];

            foreach ($missingIds as $i => $id) {
                $ph = ':bid' . $i;
                $placeholders[] = $ph;
                $params[$ph] = $id;
            }

            $sql = "SELECT BiometricsID, EmployeeID
                FROM [LRNPH_E].[dbo].[lrn_master_list]
                WHERE BiometricsID IN (" . implode(',', $placeholders) . ")";

            $stmt = $mePdo->prepare($sql);
            $stmt->execute($params);

            foreach ($stmt->fetchAll() as $row) {
                $eid = trim((string) $row['EmployeeID']);
                $map[$row['BiometricsID']] = $eid;

                if (function_exists('apcu_store')) {
                    apcu_store('eh_emp_id_' . $row['BiometricsID'], $eid, 300);
                }
            }
        }

        return $map;
    }

    private static function getDepartmentUsageBySide(
        string $department,
        bool $internal
    ): int {
        $pdo = DB::get_connection();

        $stmt = $pdo->prepare("
        SELECT hc.requester_biometric_id
        FROM [LRNPH_HR].[dbo].[eheart_heart_card] hc
        WHERE UPPER(LTRIM(RTRIM(REPLACE(hc.receiver_department, '-', ' ')))) =
              UPPER(LTRIM(RTRIM(REPLACE(:department, '-', ' '))))
          AND hc.created_at >= DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1)
          AND hc.created_at < DATEADD(
                MONTH,
                1,
                DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1)
          )
          AND hc.status NOT IN ('REJECTED', 'CANCELLED')
    ");

        $stmt->execute([
            ':department' => $department
        ]);

        $requesterIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!$requesterIds) {
            return 0;
        }

        $uniqueIds = array_values(array_unique($requesterIds));
        $mePdo = DB::get_connection('lrnph_e');

        $placeholders = [];
        $params = [];

        foreach ($uniqueIds as $i => $id) {
            $ph = ':bid' . $i;
            $placeholders[] = $ph;
            $params[$ph] = $id;
        }

        $sql = "SELECT BiometricsID, Department
                FROM [LRNPH_E].[dbo].[lrn_master_list]
                WHERE BiometricsID IN (" . implode(',', $placeholders) . ")";

        $mstmt = $mePdo->prepare($sql);
        $mstmt->execute($params);

        $deptMap = [];
        foreach ($mstmt->fetchAll() as $row) {
            $deptMap[$row['BiometricsID']] = trim((string) $row['Department']);
        }

        $targetDept = self::normDept($department);
        $count = 0;

        foreach ($requesterIds as $rid) {
            $reqDept = $deptMap[$rid] ?? null;

            if ($reqDept === null || $reqDept === '') {
                continue;
            }

            $isSame = self::normDept($reqDept) === $targetDept;

            if ($internal && $isSame) {
                $count++;
            } elseif (!$internal && !$isSame) {
                $count++;
            }
        }

        return $count;
    }

    public static function getEmployeeDepartment(
        string $employeeBiometricId
    ): ?string {
        // cross-server call. slow. dept change rare, 5 min cache fine.
        return CacheService::remember(
            'eh_emp_dept_' . $employeeBiometricId,
            300,
            function () use ($employeeBiometricId) {
                $pdo = DB::get_connection('lrnph_e');

                $stmt = $pdo->prepare("
                    SELECT TOP 1 Department
                    FROM [LRNPH_E].[dbo].[lrn_master_list]
                    WHERE BiometricsID = :bid
                ");

                $stmt->execute([
                    ':bid' => $employeeBiometricId
                ]);

                $department = trim((string) $stmt->fetchColumn());

                return $department !== '' ? $department : null;
            }
        );
    }

    public static function getEmployeeMonthlyUsage(
        string $employeeBiometricId
    ): int {
        $pdo = DB::get_connection();

        $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM [LRNPH_HR].[dbo].[eheart_heart_card]
        WHERE receiver_biometric_id = :biometric_id
          AND created_at >= DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1)
          AND created_at < DATEADD(
                MONTH,
                1,
                DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1)
          )
          AND status NOT IN ('REJECTED', 'CANCELLED')
    ");

        $stmt->execute([
            ':biometric_id' => $employeeBiometricId
        ]);

        return (int) $stmt->fetchColumn();
    }

    public static function getDepartmentQuota(
        string $department
    ): ?int {
        // quota row change rare. cache per dept, 60s.
        $cacheKey = 'eh_dept_quota_' . self::normDept($department);

        return CacheService::remember($cacheKey, 60, function () use ($department) {
            $pdo = DB::get_connection();

            $stmt = $pdo->prepare("
            SELECT monthly_quota
            FROM [LRNPH_HR].[dbo].[eheart_department_quota]
            WHERE UPPER(LTRIM(RTRIM(REPLACE(department, '-', ' ')))) =
                  UPPER(LTRIM(RTRIM(REPLACE(:department, '-', ' '))))
              AND is_active = 1
        ");

            $stmt->execute([
                ':department' => $department
            ]);

            $value = $stmt->fetchColumn();

            return $value === false ? null : (int) $value;
        });
    }

    public static function getManagerQuotaSummary(
        string $managerBiometricId
    ): array {
        $limit = (int) self::settingValue(
            'MANAGER_MONTHLY_REQUEST_LIMIT',
            10
        );

        $used = self::getManagerMonthlyUsage(
            $managerBiometricId
        );

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used)
        ];
    }


    /**
     * ============================================================
     * DEPARTMENT QUOTA SUMMARY
     * ============================================================
     */
    public static function getDepartmentQuotaSummary(
        string $department
    ): array {
        $limit = self::getDepartmentQuota($department);

        if ($limit === null) {
            return [
                'department' => $department,
                'limit' => null,
                'used' => self::getDepartmentMonthlyUsage($department),
                'remaining' => null
            ];
        }

        $used = self::getDepartmentMonthlyUsage($department);

        return [
            'department' => $department,
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used)
        ];
    }


    /**
     * ============================================================
     * EMPLOYEE QUOTA SUMMARY
     * ============================================================
     */
    public static function getEmployeeQuotaSummary(
        string $employeeBiometricId
    ): array {
        $limit = (int) self::settingValue(
            'EMPLOYEE_MONTHLY_EHEART_LIMIT',
            1
        );

        $used = self::getEmployeeMonthlyUsage(
            $employeeBiometricId
        );

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used)
        ];
    }
}
