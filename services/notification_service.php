<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class NotificationService
{
    /**
     * ============================================================
     * SEND NOTIFICATION
     * ============================================================
     */
    public static function send(
        string $recipientBiometricId,
        string $type,
        string $title,
        string $message,
        ?string $refType = null,
        $refId = null,
        bool $skipEmail = false     // NEW
    ): void {
        try {
            $pdo = DB::get_connection();

            $stmt = $pdo->prepare(
                "INSERT INTO [LRNPH_HR].[dbo].[eheart_notification]
            (
                recipient_biometric_id,
                notification_type,
                title,
                message,
                reference_type,
                reference_id
            )
            VALUES
            (
                :recipient,
                :type,
                :title,
                :message,
                :refType,
                :refId
            )"
            );

            $stmt->execute([
                ':recipient' => $recipientBiometricId,
                ':type' => $type,
                ':title' => $title,
                ':message' => $message,
                ':refType' => $refType,
                ':refId' => $refId,
            ]);

            if (!$skipEmail) {       // NEW guard
                self::sendEmail(
                    $recipientBiometricId,
                    $type,
                    $title,
                    $message,
                    $refType,
                    $refId
                );
            }
        } catch (Throwable $e) {
            error_log('Notification send failed: ' . $e->getMessage());
        }
    }

    /**
     * ============================================================
     * SEND NOTIFICATION TO ROLES
     * ============================================================
     */
    public static function sendToRoles(
        array $roleNames,
        string $type,
        string $title,
        string $message,
        ?string $refType = null,
        $refId = null
    ): void {
        if (empty($roleNames)) {
            return;
        }

        try {
            $pdo = DB::get_connection();

            $roleCodes = array_map(
                static fn(string $roleName): string => str_replace(
                    ' ',
                    '_',
                    strtoupper(trim($roleName))
                ),
                $roleNames
            );

            $roleCodes = array_values(array_unique(array_merge(
                $roleCodes,
                in_array('SYSTEM_ADMIN', $roleCodes, true)
                    ? ['SYSTEM_ADMINISTRATOR']
                    : []
            )));

            $placeholders = implode(
                ',',
                array_fill(0, count($roleCodes), '?')
            );

            // CHANGED: also grab u.email now, need it for dedup below
            $stmt = $pdo->prepare(
                "SELECT u.biometric_id, u.email
             FROM [LRNPH_HR].[dbo].[eheart_user] u
             INNER JOIN [LRNPH_HR].[dbo].[eheart_role] r
                ON r.role_id = u.role_id
             WHERE u.is_active = 1
               AND r.is_active = 1
               AND REPLACE(UPPER(LTRIM(RTRIM(r.role_name))), ' ', '_')
                   IN ($placeholders)"
            );

            $stmt->execute($roleCodes);

            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $emailedAddresses = [];   // NEW: track email already sent this batch

            foreach ($recipients as $recipient) {
                $recipientBiometricId = $recipient['biometric_id'];

                $normalizedEmail = strtolower(trim((string) ($recipient['email'] ?? '')));

                $skipEmail = $normalizedEmail !== ''
                    && isset($emailedAddresses[$normalizedEmail]);

                self::send(
                    $recipientBiometricId,
                    $type,
                    $title,
                    $message,
                    $refType,
                    $refId,
                    $skipEmail          // NEW arg
                );

                if ($normalizedEmail !== '') {
                    $emailedAddresses[$normalizedEmail] = true;
                }
            }
        } catch (Throwable $e) {
            error_log('Role notification send failed: ' . $e->getMessage());
        }
    }

    /**
     * ============================================================
     * LIST NOTIFICATIONS
     * ============================================================
     */
    public static function listForUser(
        string $biometricId,
        string $roleCode,
        bool $unreadOnly = false
    ): array {
        $pdo = DB::get_connection();

        $sql = "
            SELECT n.*,
                   CASE
                       WHEN n.reference_type IN ('redemption', 'gift_certificate')
                           THEN r.heart_card_id
                       ELSE n.reference_id
                   END AS resolved_reference_id
            FROM [LRNPH_HR].[dbo].[eheart_notification] n
            LEFT JOIN [LRNPH_HR].[dbo].[eheart_redemption] r
                ON r.redemption_id = n.reference_id
               AND n.reference_type IN ('redemption', 'gift_certificate')
            WHERE n.recipient_biometric_id = :bid
        ";

        if ($unreadOnly) {
            $sql .= " AND n.is_read = 0";
        }

        $sql .= "
            ORDER BY n.notification_id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bid' => $biometricId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ============================================================
     * MARK ONE NOTIFICATION AS READ
     * ============================================================
     */
    public static function markRead(
        int $notificationId,
        string $biometricId
    ): void {
        $pdo = DB::get_connection();

        $stmt = $pdo->prepare(
            "UPDATE [LRNPH_HR].[dbo].[eheart_notification]
             SET
                is_read = 1,
                read_at = SYSUTCDATETIME()
             WHERE notification_id = :id
               AND recipient_biometric_id = :bid"
        );

        $stmt->execute([
            ':id' => $notificationId,
            ':bid' => $biometricId
        ]);
    }

    /**
     * ============================================================
     * MARK ALL NOTIFICATIONS AS READ
     * ============================================================
     */
    public static function markAllRead(
        string $biometricId,
        string $roleCode = ''
    ): void {
        $pdo = DB::get_connection();

        $stmt = $pdo->prepare(
            "UPDATE [LRNPH_HR].[dbo].[eheart_notification]
             SET
                is_read = 1,
                read_at = SYSUTCDATETIME()
             WHERE recipient_biometric_id = :bid"
        );

        $stmt->execute([
            ':bid' => $biometricId
        ]);
    }

    /**
     * ============================================================
     * SEND EMAIL (PHPMailer / SMTP)
     * ============================================================
     *
     * Config constants expected (define in bootstrap/config):
     *   EH_ENABLE_EMAIL_NOTIFICATIONS  (bool)
     *   EH_SMTP_HOST                   (string)  e.g. 'smtp-relay.brevo.com'
     *   EH_SMTP_PORT                   (int)     e.g. 587
     *   EH_SMTP_USERNAME               (string)
     *   EH_SMTP_PASSWORD               (string)
     *   EH_SMTP_SECURE                 (string)  'tls' or 'ssl'
     *   EH_EMAIL_FROM                  (string)
     *   EH_EMAIL_FROM_NAME             (string)
     *   EH_EMAIL_SUBJECT_PREFIX        (string)
     */
    private static function sendEmail(
        string $recipientBiometricId,
        string $type,
        string $title,
        string $message,
        ?string $refType = null,
        $refId = null
    ): void {
        if (
            !defined('EH_ENABLE_EMAIL_NOTIFICATIONS') ||
            !EH_ENABLE_EMAIL_NOTIFICATIONS
        ) {
            error_log('NotificationService: Email notifications are disabled.');
            return;
        }

        try {
            $pdo = DB::get_connection();
            $email = '';

            if (strtoupper(trim($type)) === 'CARD_RECEIVED') {
                // Heart Card recipients must use the masterlist email exactly.
                try {
                    $masterlistPdo = DB::get_connection('lrnph_e');
                    $stmt = $masterlistPdo->prepare(
                        "SELECT TOP 1 Email AS email
             FROM [dbo].[lrn_master_list]
             WHERE LTRIM(RTRIM(BiometricsID)) = LTRIM(RTRIM(:bid))"
                    );
                    $stmt->execute([
                        ':bid' => $recipientBiometricId
                    ]);

                    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($employee) {
                        $masterlistEmail = trim((string)($employee['email'] ?? ''));

                        if ($masterlistEmail !== '' && filter_var($masterlistEmail, FILTER_VALIDATE_EMAIL)) {
                            $email = $masterlistEmail;
                        }
                    }
                } catch (Throwable $e) {
                    error_log(
                        'NotificationService: Masterlist lookup failed for ' .
                            $recipientBiometricId .
                            ': ' .
                            $e->getMessage()
                    );
                }
            } else {
                $roleStmt = $pdo->prepare(
                    "SELECT TOP 1 u.email, r.role_name
         FROM [LRNPH_HR].[dbo].[eheart_user] u
         LEFT JOIN [LRNPH_HR].[dbo].[eheart_role] r
            ON r.role_id = u.role_id
         WHERE LTRIM(RTRIM(u.biometric_id)) = LTRIM(RTRIM(:bid))
           AND u.is_active = 1"
                );

                $roleStmt->execute([
                    ':bid' => $recipientBiometricId
                ]);

                $userRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
                $email = trim((string)($userRow['email'] ?? ''));
            }

            if (
                $email === '' ||
                !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                error_log(
                    'NotificationService: No valid email found for biometric ID ' .
                        $recipientBiometricId
                );
                return;
            }

            error_log(
                'NotificationService: Preparing email for ' .
                    $recipientBiometricId .
                    ' -> ' .
                    $email .
                    ' | Type: ' .
                    $type
            );

            $heartCard = null;

            $normalizedRefType = strtolower(
                trim((string)$refType)
            );

            if (
                $refId !== null &&
                in_array(
                    $normalizedRefType,
                    ['heart_card', 'request'],
                    true
                )
            ) {
                try {
                    $cardStmt = $pdo->prepare(
                        "SELECT TOP 1
                        hc.*,
                        COALESCE(
                            NULLIF(hc.requester_full_name, ''),
                            requester.full_name
                        ) AS requester_name,
                        COALESCE(
                            NULLIF(hc.receiver_full_name, ''),
                            receiver.full_name
                        ) AS receiver_name
                     FROM [LRNPH_HR].[dbo].[eheart_heart_card] hc
                     LEFT JOIN [LRNPH_HR].[dbo].[eheart_user] requester
                        ON requester.biometric_id =
                           hc.requester_biometric_id
                     LEFT JOIN [LRNPH_HR].[dbo].[eheart_user] receiver
                        ON receiver.biometric_id =
                           hc.receiver_biometric_id
                     WHERE hc.heart_card_id = :id"
                    );

                    $cardStmt->execute([
                        ':id' => $refId
                    ]);

                    $heartCard = $cardStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$heartCard) {
                        error_log(
                            'NotificationService: Heart Card not found for ID ' .
                                $refId
                        );
                    }
                } catch (Throwable $e) {
                    error_log(
                        'NotificationService: Unable to load Heart Card ' .
                            $refId .
                            ': ' .
                            $e->getMessage()
                    );
                }
            }

            $isHeartCardReceiver = false;

            $redemptionQrTypes = [
                'CARD_RECEIVED',
                'CARD_REDEMPTION_REMINDER_15',
                'CARD_REDEMPTION_REMINDER_25',
            ];

            if (
                in_array($type, $redemptionQrTypes, true) &&
                is_array($heartCard) &&
                !empty($heartCard['receiver_biometric_id'])
            ) {
                $isHeartCardReceiver =
                    (string)$heartCard['receiver_biometric_id'] ===
                    (string)$recipientBiometricId;
            }

            error_log(
                'NotificationService: receiver/QR check for type ' .
                    $type . ': ' .
                    ($isHeartCardReceiver ? 'YES' : 'NO')
            );

            $qrCid = 'heartcardqr';
            $qrFilesystemPath = null;

            if (
                $isHeartCardReceiver &&
                !empty($heartCard['qr_code_path'])
            ) {
                $candidatePath = __DIR__ .
                    '/../uploads/qr/' .
                    basename((string)$heartCard['qr_code_path']);

                if (is_file($candidatePath)) {
                    $qrFilesystemPath = $candidatePath;

                    error_log(
                        'NotificationService: QR file found: ' .
                            $candidatePath
                    );
                } else {
                    error_log(
                        'NotificationService: QR file NOT found: ' .
                            $candidatePath
                    );
                }
            }

            $smtpHost = defined('EH_SMTP_HOST')
                ? trim((string)EH_SMTP_HOST)
                : '';

            $smtpUsername = defined('EH_SMTP_USERNAME')
                ? trim((string)EH_SMTP_USERNAME)
                : '';

            $smtpPassword = defined('EH_SMTP_PASSWORD')
                ? (string)EH_SMTP_PASSWORD
                : '';

            $smtpPort = defined('EH_SMTP_PORT')
                ? (int)EH_SMTP_PORT
                : 587;

            $smtpSecure = defined('EH_SMTP_SECURE')
                ? trim((string)EH_SMTP_SECURE)
                : PHPMailer::ENCRYPTION_STARTTLS;

            if (
                $smtpHost === '' ||
                $smtpUsername === '' ||
                $smtpPassword === ''
            ) {
                error_log(
                    'NotificationService: SMTP configuration is incomplete.'
                );
                return;
            }

            $fromEmail = defined('EH_EMAIL_FROM')
                ? trim((string)EH_EMAIL_FROM)
                : $smtpUsername;

            $fromName = defined('EH_EMAIL_FROM_NAME')
                ? trim((string)EH_EMAIL_FROM_NAME)
                : 'eHeart';

            $subjectPrefix = defined('EH_EMAIL_SUBJECT_PREFIX')
                ? (string)EH_EMAIL_SUBJECT_PREFIX
                : '[eHeart] ';

            $subject = $subjectPrefix . $title;

            $html = self::buildHtml(
                $title,
                $message,
                $heartCard,
                $isHeartCardReceiver,
                $qrFilesystemPath !== null
                    ? $qrCid
                    : null
            );

            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;

            if (
                strtolower($smtpSecure) === 'ssl' ||
                strtolower($smtpSecure) === 'smtps'
            ) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->Port = $smtpPort;

            if (
                defined('EH_SMTP_VERIFY_PEER') &&
                !EH_SMTP_VERIFY_PEER
            ) {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }

            $mail->setFrom(
                $fromEmail,
                $fromName
            );

            $mail->addAddress($email);

            if ($qrFilesystemPath !== null) {
                $mail->addEmbeddedImage(
                    $qrFilesystemPath,
                    $qrCid,
                    'heart-card-qr.png'
                );
            }

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $message;

            $mail->send();

            error_log(
                'NotificationService: Email SENT successfully to ' .
                    $email .
                    ' | Type: ' .
                    $type .
                    ' | Ref ID: ' .
                    ($refId ?? 'N/A')
            );
        } catch (PHPMailerException $e) {
            error_log(
                'NotificationService: PHPMailer ERROR: ' .
                    $e->getMessage()
            );

            if (isset($mail) && $mail instanceof PHPMailer) {
                error_log(
                    'NotificationService: PHPMailer ErrorInfo: ' .
                        $mail->ErrorInfo
                );
            }
        } catch (Throwable $e) {
            error_log(
                'NotificationService: EMAIL ERROR: ' .
                    $e->getMessage() .
                    ' in ' .
                    $e->getFile() .
                    ':' .
                    $e->getLine()
            );
        }
    }

    /**
     * ============================================================
     * BUILD EMAIL HTML
     * ============================================================
     */
    private static function buildHtml(
        string $title,
        string $message,
        ?array $heartCard = null,
        bool $isHeartCardReceiver = false,
        ?string $qrCid = null
    ): string {
        $escape = static function ($value): string {
            return htmlspecialchars(
                (string) ($value ?? ''),
                ENT_QUOTES,
                'UTF-8'
            );
        };

        /**
         * DEFAULT VALUES
         */
        $giverName = 'Manager / Leader';
        $receiverName = 'Employee';
        $department = '';
        $dateIssued = date('M d, Y');
        $actionPerformed = $message;
        $controlNumber = 'N/A';

        /**
         * ALWAYS 30 DAYS
         */
        $validityText = 'VALID FOR 30 DAYS';
        $coreValues = [];

        /**
         * LOAD HEART CARD DATA
         */
        if (is_array($heartCard)) {
            $giverName = trim(
                (string) ($heartCard['requester_name'] ?? '')
            );

            if ($giverName === '') {
                $giverName = 'Manager / Leader';
            }

            $receiverName = trim(
                (string) (
                    $heartCard['receiver_name']
                    ?? $heartCard['receiver_full_name']
                    ?? 'Employee'
                )
            );

            if ($receiverName === '') {
                $receiverName = 'Employee';
            }

            $department = trim(
                (string) (
                    $heartCard['receiver_department'] ?? ''
                )
            );

            $rawDate = $heartCard['created_at'] ?? null;

            if (!empty($rawDate)) {
                try {
                    $dateIssued = (
                        new DateTime((string) $rawDate)
                    )->format('M d, Y');
                } catch (Throwable $e) {
                    $dateIssued = (string) $rawDate;
                }
            }

            $actionPerformed = trim(
                (string) (
                    $heartCard['action_performed'] ?? ''
                )
            );

            if ($actionPerformed === '') {
                $actionPerformed = $message;
            }

            if (!empty($heartCard['heart_card_id'])) {
                try {
                    $controlNumber = DateHelper::controlNumber(
                        (int) $heartCard['heart_card_id']
                    );
                } catch (Throwable $e) {
                    $controlNumber =
                        'HC-' .
                        str_pad(
                            (string) $heartCard['heart_card_id'],
                            4,
                            '0',
                            STR_PAD_LEFT
                        );
                }
            }

            $rawCoreValues =
                $heartCard['core_values'] ?? '';

            if (is_string($rawCoreValues)) {
                $decoded = json_decode(
                    $rawCoreValues,
                    true
                );

                if (is_array($decoded)) {
                    $coreValues = $decoded;
                } else {
                    $coreValues = array_filter(
                        array_map(
                            'trim',
                            explode(',', $rawCoreValues)
                        )
                    );
                }
            } elseif (is_array($rawCoreValues)) {
                $coreValues = $rawCoreValues;
            }
        }

        $coreValueIds = array_values(
            array_filter(
                array_map(
                    static fn($value) => is_numeric($value)
                        ? (int) $value
                        : null,
                    $coreValues
                ),
                static fn($value) => $value !== null
            )
        );

        if (!empty($coreValueIds)) {
            try {
                $placeholders = implode(',', array_fill(0, count($coreValueIds), '?'));
                $valueStmt = DB::get_connection()->prepare(
                    "SELECT core_value_id, core_value_name
                     FROM [LRNPH_HR].[dbo].[eheart_core_value]
                     WHERE core_value_id IN ({$placeholders})"
                );
                $valueStmt->execute($coreValueIds);

                $valueNames = [];
                foreach ($valueStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $valueNames[(int) $row['core_value_id']] = $row['core_value_name'];
                }

                $coreValues = array_map(
                    static fn($value) => $valueNames[(int) $value] ?? $value,
                    $coreValues
                );
            } catch (Throwable $e) {
                error_log('NotificationService: Unable to resolve Heart Card core values. ' . $e->getMessage());
            }
        }

        /**
         * CORE VALUE DEFINITIONS
         */
        $valueDefinitions = [
            'excellence' => [
                'name' => 'EXCELLENCE',
                'icon' => '&#9734;',
                'description' =>
                'I deliver high quality and pursue continuous improvement.'
            ],
            'efficiency' => [
                'name' => 'EFFICIENCY',
                'icon' => '&#9889;',
                'description' =>
                'I work smart and remove delays to achieve more.'
            ],
            'teamwork' => [
                'name' => 'TEAMWORK',
                'icon' => '&#128101;',
                'description' =>
                'I support others and achieve together.'
            ],
            'professionalism' => [
                'name' => 'PROFESSIONALISM',
                'icon' => '&#128737;',
                'description' =>
                'I act with discipline, integrity, and respect.'
            ],
            'passion' => [
                'name' => 'PASSION',
                'icon' => '&#128293;',
                'description' =>
                'I show initiative, energy, and pride in my work.'
            ]
        ];

        /**
         * NORMALIZE CORE VALUES
         */
        $selectedValues = [];

        foreach ($coreValues as $value) {
            if (is_array($value)) {
                $value =
                    $value['name']
                    ?? $value['value']
                    ?? $value['core_value']
                    ?? '';
            }

            $normalized = strtolower(
                trim((string) $value)
            );

            if ($normalized !== '') {
                $selectedValues[] = $normalized;
            }
        }

        $selectedValues = array_values(
            array_unique($selectedValues)
        );

        /**
         * BUILD CORE VALUES HTML
         */
        $valuesHtml = '';

        foreach ($valueDefinitions as $key => $value) {
            $isSelected = false;

            foreach ($selectedValues as $selectedValue) {
                if (
                    $selectedValue === $key ||
                    str_contains($selectedValue, $key)
                ) {
                    $isSelected = true;
                    break;
                }
            }

            $checkMark = $isSelected
                ? '&#10003;'
                : '';

            $checkboxBorder = $isSelected
                ? '#ec1876'
                : '#cfcfcf';

            $checkboxBackground = $isSelected
                ? '#fff1f7'
                : '#ffffff';

            $iconColor = $isSelected
                ? '#ec1876'
                : '#a8a8a8';

            $valuesHtml .= '
                <tr>
                    <td
                        width="28"
                        style="
                            width:28px;
                            padding:7px 5px 7px 0;
                            vertical-align:middle;
                        "
                    >
                        <div
                            style="
                                width:16px;
                                height:16px;
                                line-height:16px;
                                text-align:center;
                                box-sizing:border-box;
                                border:1.5px solid ' . $checkboxBorder . ';
                                background:' . $checkboxBackground . ';
                                color:#ec1876;
                                font-family:Arial,Helvetica,sans-serif;
                                font-size:11px;
                                font-weight:900;
                            "
                        >
                            ' . $checkMark . '
                        </div>
                    </td>

                    <td
                        width="38"
                        style="
                            width:38px;
                            padding:4px 6px 4px 0;
                            vertical-align:middle;
                            text-align:center;
                            color:' . $iconColor . ';
                            font-size:22px;
                            line-height:1;
                        "
                    >
                        ' . $value['icon'] . '
                    </td>

                    <td
                        style="
                            padding:6px 0;
                            vertical-align:middle;
                            border-bottom:1px solid #eeeeee;
                        "
                    >
                        <div
                            style="
                                color:#333333;
                                font-size:10px;
                                font-weight:900;
                                letter-spacing:.4px;
                            "
                        >
                            ' . $escape($value['name']) . '
                        </div>

                        <div
                            style="
                                margin-top:2px;
                                color:#777777;
                                font-size:8px;
                                line-height:1.35;
                            "
                        >
                            ' . $escape($value['description']) . '
                        </div>
                    </td>
                </tr>
            ';
        }

        /**
         * ESCAPE DATA
         */
        $safeTitle = $escape($title);
        $safeMessage = nl2br($escape($message));
        $safeGiverName = $escape($giverName);
        $safeReceiverName = $escape($receiverName);
        $safeDepartment = $escape($department);
        $safeDateIssued = $escape($dateIssued);
        $safeActionPerformed = nl2br(
            $escape($actionPerformed)
        );
        $safeControlNumber = $escape($controlNumber);
        $safeValidityText = $escape($validityText);

        /**
         * DEPARTMENT HTML
         */
        $departmentHtml = '';

        if ($department !== '') {
            $departmentHtml = '
                <div
                    style="
                        margin-top:4px;
                        color:#8a8a8a;
                        font-size:10px;
                    "
                >
                    ' . $safeDepartment . '
                </div>
            ';
        }

        /**
         * EMPLOYEE REDEMPTION MESSAGE
         */
        $employeeRedemptionMessage = '';

        if ($isHeartCardReceiver) {
            $qrImageHtml = '';

            if ($qrCid !== null) {
                $qrImageHtml = '
                    <div style="margin-top:14px; text-align:center;">
                        <img
                            src="cid:' . $escape($qrCid) . '"
                            alt="Heart Card QR Code"
                            width="160"
                            height="160"
                            style="
                                display:inline-block;
                                width:160px;
                                height:160px;
                                border:1px solid #f0bfd3;
                                border-radius:8px;
                                background:#ffffff;
                                padding:8px;
                            "
                        >
                        <div
                            style="
                                margin-top:8px;
                                color:#8a8a8a;
                                font-size:10px;
                                font-weight:700;
                            "
                        >
                            Show this QR code to HR to redeem
                        </div>
                    </div>
                ';
            }

            $employeeRedemptionMessage = '
                <table
                    role="presentation"
                    width="760"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="
                        width:100%;
                        max-width:760px;
                        margin-top:18px;
                        background:#fff5f9;
                        border:1px solid #f0bfd3;
                        border-left:5px solid #ec1876;
                        border-radius:10px;
                    "
                >
                    <tr>
                        <td style="padding:18px 22px;">
                            <div
                                style="
                                    color:#ec1876;
                                    font-size:12px;
                                    font-weight:900;
                                    letter-spacing:.7px;
                                "
                            >
                                REDEMPTION INSTRUCTION
                            </div>

                            <div
                                style="
                                    margin-top:8px;
                                    color:#444444;
                                    font-size:13px;
                                    line-height:1.65;
                                "
                            >
                                Congratulations! Please proceed to
                                <strong>HR</strong> and bring your
                                <strong>Biometrics ID</strong> to redeem
                                your Heart Card.
                            </div>

                            ' . $qrImageHtml . '
                        </td>
                    </tr>
                </table>
            ';
        }

        /**
         * EMAIL HTML
         */
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>{$safeTitle} | eHeart</title>
</head>

<body
    style="
        margin:0;
        padding:0;
        background:#f2f2f2;
        font-family:Arial,Helvetica,sans-serif;
        color:#222222;
    "
>

<div
    style="
        width:100%;
        padding:28px 10px;
        box-sizing:border-box;
    "
>

<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
>
<tr>
<td align="center">

<!-- HEART CARD -->
<table
    role="presentation"
    width="760"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="
        width:100%;
        max-width:760px;
        background:#ffffff;
        border:2px solid #111111;
        border-radius:22px;
        overflow:hidden;
        box-shadow:0 8px 28px rgba(0,0,0,.12);
    "
>

<!-- HEADER -->
<tr>
<td
    style="
        padding:0;
        background:#090909;
        border-bottom:4px solid #ec1876;
    "
>
<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
>
<tr>

<td
    width="62%"
    style="
        padding:20px 22px;
        vertical-align:middle;
    "
>
<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
>
<tr>

<td
    width="125"
    style="
        padding-right:18px;
        vertical-align:middle;
        border-right:1px solid #777777;
    "
>
<div
    style="
        color:#ffffff;
        font-size:22px;
        font-weight:900;
    "
>
    e<span style="color:#ec1876;">Heart</span>
</div>

<div
    style="
        margin-top:5px;
        color:#d6d6d6;
        font-size:8px;
        font-weight:bold;
        letter-spacing:1.4px;
    "
>
    LA ROSE NOIRE
</div>
</td>

<td
    style="
        padding-left:18px;
        vertical-align:middle;
    "
>
<div
    style="
        color:#ffffff;
        font-size:27px;
        line-height:1;
        font-weight:900;
    "
>
    CARES
</div>

<div
    style="
        margin-top:2px;
        color:#ffffff;
        font-size:27px;
        line-height:1;
        font-weight:900;
    "
>
    IN
    <span style="color:#ec1876;">
        ACTION
    </span>
</div>

<div
    style="
        margin-top:7px;
        color:#d0d0d0;
        font-size:10px;
        font-weight:bold;
    "
>
    Care. Act. Make an Impact.
</div>
</td>

</tr>
</table>
</td>

<td
    width="38%"
    style="
        padding:20px 22px;
        vertical-align:middle;
    "
>
<div
    style="
        color:#ec1876;
        font-size:23px;
        font-weight:900;
    "
>
    HEART CARD
</div>

<div
    style="
        margin-top:7px;
        color:#ffffff;
        font-size:11px;
        font-weight:bold;
        line-height:1.5;
    "
>
    RECOGNIZING ACTIONS<br>
    THAT MAKE A DIFFERENCE
</div>
</td>

</tr>
</table>
</td>
</tr>

<!-- MAIN CONTENT -->
<tr>
<td>

<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
>
<tr>

<!-- LEFT SIDE -->
<td
    width="62%"
    style="
        padding:25px;
        vertical-align:top;
        border-right:1px solid #e7e7e7;
    "
>

<!-- GIVER -->
<div style="margin-bottom:20px;">
    <div
        style="
            color:#777777;
            font-size:10px;
            font-weight:bold;
        "
    >
        GIVER
        <span
            style="
                color:#999999;
                font-weight:normal;
            "
        >
            (Manager / Leader)
        </span>
    </div>

    <div
        style="
            margin-top:7px;
            padding-bottom:7px;
            border-bottom:1px solid #c9c9c9;
            font-size:13px;
            font-weight:700;
        "
    >
        {$safeGiverName}
    </div>
</div>

<!-- RECEIVER -->
<div style="margin-bottom:20px;">
    <div
        style="
            color:#777777;
            font-size:10px;
            font-weight:bold;
        "
    >
        RECEIVER
        <span
            style="
                color:#999999;
                font-weight:normal;
            "
        >
            (Employee)
        </span>
    </div>

    <div
        style="
            margin-top:7px;
            padding-bottom:7px;
            border-bottom:1px solid #c9c9c9;
            font-size:13px;
            font-weight:700;
        "
    >
        {$safeReceiverName}
    </div>

    {$departmentHtml}
</div>

<!-- DATE ISSUED -->
<div style="margin-bottom:20px;">
    <div
        style="
            color:#777777;
            font-size:10px;
            font-weight:bold;
        "
    >
        DATE ISSUED
    </div>

    <div
        style="
            margin-top:7px;
            padding-bottom:7px;
            border-bottom:1px solid #c9c9c9;
            font-size:13px;
            font-weight:700;
        "
    >
        {$safeDateIssued}
    </div>
</div>

<!-- ACTION PERFORMED -->
<div>
    <div
        style="
            color:#777777;
            font-size:10px;
            font-weight:bold;
        "
    >
        ACTION PERFORMED
    </div>

    <div
        style="
            margin-top:8px;
            padding:12px 14px;
            background:#fffafd;
            border:1px solid #f1e0e8;
            border-left:4px solid #ec1876;
            color:#4d4d4d;
            font-size:11px;
            line-height:1.65;
        "
    >
        {$safeActionPerformed}
    </div>
</div>

</td>

<!-- RIGHT SIDE -->
<td
    width="38%"
    style="
        padding:20px;
        vertical-align:top;
    "
>

<div
    style="
        margin-bottom:10px;
        padding:10px 12px;
        background:#f4d6e3;
        color:#8b2a50;
        font-size:11px;
        font-weight:900;
        letter-spacing:.7px;
    "
>
    VALUE DEMONSTRATED
</div>

<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
>
    {$valuesHtml}
</table>

</td>

</tr>
</table>

</td>
</tr>

<!-- QUOTE -->
<tr>
<td
    style="
        padding:12px 20px;
        background:#f9dce8;
        border-top:1px solid #efc0d4;
        border-bottom:1px solid #e9b6cc;
        text-align:center;
        color:#4d4d4d;
        font-family:Georgia,serif;
        font-size:15px;
        font-style:italic;
    "
>
    " I demonstrated La Rose standards through action. "
</td>
</tr>

<!-- CONTROL NUMBER -->
<tr>
<td
    style="
        padding:16px 22px;
        background:#090909;
    "
>

<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
>
<tr>

<td
    width="62%"
    style="vertical-align:middle;"
>
<div
    style="
        color:#ec1876;
        font-size:9px;
        font-weight:bold;
    "
>
    CONTROL NUMBER
</div>

<div
    style="
        margin-top:6px;
        padding:8px 14px;
        background:#ffffff;
        border-radius:5px;
        text-align:center;
        color:#222222;
        font-size:21px;
        font-weight:900;
        letter-spacing:3px;
    "
>
    {$safeControlNumber}
</div>
</td>

<!-- VALIDITY -->
<td
    width="38%"
    style="
        padding-left:18px;
        vertical-align:middle;
        text-align:center;
    "
>
<div
    style="
        color:#ec1876;
        font-size:20px;
    "
>
    &#128197;
</div>

<div
    style="
        margin-top:4px;
        color:#ffffff;
        font-size:10px;
        font-weight:900;
    "
>
    {$safeValidityText}
</div>

<div
    style="
        margin-top:4px;
        color:#aaaaaa;
        font-size:8px;
    "
>
    from date issued
</div>
</td>

</tr>
</table>

</td>
</tr>

</table>

<!-- EMPLOYEE REDEMPTION MESSAGE -->
{$employeeRedemptionMessage}

<!-- NOTIFICATION MESSAGE -->
<table
    role="presentation"
    width="760"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="
        width:100%;
        max-width:760px;
        margin-top:18px;
        background:#ffffff;
        border:1px solid #e5e5e5;
        border-radius:10px;
    "
>
<tr>
<td style="padding:18px 22px;">

<div
    style="
        margin-bottom:7px;
        color:#ec1876;
        font-size:9px;
        font-weight:900;
        letter-spacing:1px;
    "
>
    NOTIFICATION
</div>

<div
    style="
        color:#252525;
        font-size:17px;
        font-weight:800;
    "
>
    {$safeTitle}
</div>

<div
    style="
        margin-top:8px;
        color:#666666;
        font-size:12px;
        line-height:1.65;
    "
>
    {$safeMessage}
</div>

</td>
</tr>
</table>

<!-- FOOTER -->
<div
    style="
        width:100%;
        max-width:760px;
        margin:15px auto 0;
        color:#999999;
        text-align:center;
        font-size:9px;
        line-height:1.5;
    "
>
    This is an automated notification from eHeart.<br>
    Please do not reply directly to this email.
</div>

</td>
</tr>
</table>

</div>

</body>
</html>
HTML;
    }
}
