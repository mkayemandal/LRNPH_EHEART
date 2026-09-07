<?php

class UserService
{
    private static function roleCodeFromName(
        string $roleName
    ): string {

        $map = [
            'Manager' => 'MANAGER',
            'HR Admin' => 'HR_ADMIN',
            'System Administrator' => 'SYSTEM_ADMIN',
        ];

        return $map[$roleName]
            ?? strtoupper(
                preg_replace(
                    '/[^A-Za-z0-9]+/',
                    '_',
                    $roleName
                )
            );
    }


    public static function findByBiometricId(
        string $biometricId
    ): ?array {

        $pdo = DB::get_connection();

        $stmt = $pdo->prepare("
            SELECT
                u.user_id,
                u.biometric_id,
                u.full_name,
                u.password_hash,
                u.role_id,
                u.is_active,
                u.last_login_at,
                r.role_name
            FROM [LRNPH_HR].[dbo].[eheart_user] u
            INNER JOIN [LRNPH_HR].[dbo].[eheart_role] r
                ON r.role_id = u.role_id
            WHERE u.biometric_id = :bid
              AND u.is_active = 1
        ");

        $stmt->execute([
            ':bid' => $biometricId
        ]);

        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        $user['role_code'] =
            self::roleCodeFromName(
                $user['role_name']
            );

        $user['employee_name'] =
            $user['full_name'];

        $employee = self::findEmployeeByBiometricId(
            $user['biometric_id']
        );

        $user['department'] = $employee['department'] ?? null;

        $user['employee_id'] = $employee['employee_id'] ?? null;

        $user['position'] = null;

        return $user;
    }


    public static function touchLogin(
        string $biometricId
    ): void {

        $pdo = DB::get_connection();

        $stmt = $pdo->prepare("
            UPDATE [LRNPH_HR].[dbo].[eheart_user]
            SET
                last_login_at = SYSDATETIME(),
                updated_at = SYSDATETIME()
            WHERE biometric_id = :bid
        ");

        $stmt->execute([
            ':bid' => $biometricId
        ]);
    }


    public static function listUsers(): array
    {
        $pdo = DB::get_connection('main_db');

        $stmt = $pdo->query("
        SELECT
            u.user_id,
            u.biometric_id,
            u.full_name,
            u.is_active,
            u.last_login_at,
            u.email,
            r.role_name
        FROM [LRNPH_HR].[dbo].[eheart_user] u
        INNER JOIN [LRNPH_HR].[dbo].[eheart_role] r
            ON r.role_id = u.role_id
        ORDER BY
            CASE r.role_name
                WHEN 'System Administrator' THEN 1
                WHEN 'HR Admin' THEN 2
                WHEN 'Manager' THEN 3
                ELSE 4
            END,
            u.full_name ASC
    ");

        $users = $stmt->fetchAll();

        /*
     * Manager monthly request limit
     */
        $managerLimit = (int) self::getSystemSetting(
            'MANAGER_MONTHLY_REQUEST_LIMIT',
            10
        );

        /*
     * Connect to masterlist
     */
        $masterPdo = DB::get_connection('lrnph_e');

        $departmentStmt = $masterPdo->prepare("
        SELECT TOP 1
            Department,
            EmployeeID
        FROM [LRNPH_E].[dbo].[lrn_master_list]
        WHERE BiometricsID = :bid
    ");

        /*
     * Monthly Manager usage
     */
        $usagePdo = DB::get_connection('main_db');

        $managerUsageStmt = $usagePdo->prepare("
        SELECT COUNT(*)
        FROM [LRNPH_HR].[dbo].[eheart_heart_card]
        WHERE requester_biometric_id = :bid
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
          AND status IN (
              'PENDING',
              'APPROVED',
              'FOR_REDEMPTION',
              'REDEEMED',
              'FOR_REVISION'
          )
    ");

        foreach ($users as &$user) {

            $user['role_code'] =
                self::roleCodeFromName(
                    $user['role_name']
                );

            $user['department'] = '';

            $departmentStmt->execute([
                ':bid' => $user['biometric_id']
            ]);

            $employee = $departmentStmt->fetch();

            $user['employee_id'] = '';

            if ($employee) {
                $user['department'] =
                    $employee['Department'] ?? '';

                $user['employee_id'] =
                    trim((string) ($employee['EmployeeID'] ?? ''));
            }

            /*
         * Only Managers have a request quota.
         */
            if ($user['role_code'] === 'MANAGER') {

                $managerUsageStmt->execute([
                    ':bid' => $user['biometric_id']
                ]);

                $used = (int)
                $managerUsageStmt->fetchColumn();

                $user['request_quota'] = [
                    'limit' => $managerLimit,
                    'used' => $used,
                    'remaining' => max(
                        0,
                        $managerLimit - $used
                    )
                ];
            } else {

                $user['request_quota'] = null;
            }
        }

        unset($user);

        return $users;
    }


    public static function createUser(
        string $biometricId,
        string $fullName,
        int $roleId,
        ?string $email = null,
        ?string $employeeId = null
    ): int {

        $pdo = DB::get_connection();

        $passwordHash = password_hash(
            $biometricId,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare("
            INSERT INTO [LRNPH_HR].[dbo].[eheart_user]
            (
                biometric_id,
                full_name,
                role_id,
                password_hash,
                email
            )
            VALUES
            (
                :bid,
                :full_name,
                :role_id,
                :password_hash,
                :email
            )
        ");

        $stmt->execute([
            ':bid' => $biometricId,
            ':full_name' => $fullName,
            ':role_id' => $roleId,
            ':password_hash' => $passwordHash,
            ':email' => $email
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function updateUser(
        int $userId,
        string $fullName,
        int $roleId,
        ?string $email = null
    ): void {

        $pdo = DB::get_connection();

        $stmt = $pdo->prepare("
        UPDATE [LRNPH_HR].[dbo].[eheart_user]
        SET
            full_name = :full_name,
            role_id = :role_id,
            email = :email,
            updated_at = SYSDATETIME()
        WHERE user_id = :id
    ");

        $stmt->execute([
            ':full_name' => $fullName,
            ':role_id' => $roleId,
            ':email' => $email,
            ':id' => $userId
        ]);
    }


    public static function setActive(
        int $userId,
        bool $active
    ): void {

        $pdo = DB::get_connection();

        $stmt = $pdo->prepare("
            UPDATE [LRNPH_HR].[dbo].[eheart_user]
            SET
                is_active = :active,
                updated_at = SYSDATETIME()
            WHERE user_id = :id
        ");

        $stmt->execute([
            ':active' => $active ? 1 : 0,
            ':id' => $userId
        ]);
    }


    /* =========================================
       MASTERLIST LOOKUP BY BIOMETRIC ID
    ========================================= */

    public static function findEmployeeByBiometricId(
        string $biometricId
    ): ?array {

        $biometricId = trim($biometricId);

        if ($biometricId === '') {
            return null;
        }

        $pdo = DB::get_connection('lrnph_e');

        $stmt = $pdo->prepare("
            SELECT TOP 1
                BiometricsID,
                EmployeeID,
                FirstName,
                MiddleName,
                LastName,
                Department,
                Email
            FROM [LRNPH_E].[dbo].[lrn_master_list]
            WHERE BiometricsID = :bid
        ");

        $stmt->execute([
            ':bid' => $biometricId
        ]);

        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return self::shapeEmployee($row);
    }


    /* =========================================
       MASTERLIST LOOKUP BY EMAIL
       FOR PN IDENTITY
    ========================================= */

    public static function findEmployeeByEmail(
        string $email
    ): ?array {

        $email = trim($email);

        if ($email === '') {
            return null;
        }

        $pdo = DB::get_connection('lrnph_e');

        $stmt = $pdo->prepare("
            SELECT TOP 1
                BiometricsID,
                EmployeeID,
                FirstName,
                MiddleName,
                LastName,
                Department,
                Email
            FROM [LRNPH_E].[dbo].[lrn_master_list]
            WHERE LOWER(LTRIM(RTRIM(Email))) =
                  LOWER(LTRIM(RTRIM(:email)))
        ");

        $stmt->execute([
            ':email' => $email
        ]);

        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return self::shapeEmployee($row);
    }


    /* =========================================
       SEARCH MASTERLIST BY NAME
    ========================================= */

    public static function searchEmployeesByName(
        string $q
    ): array {

        $q = trim($q);

        if ($q === '') {
            return [];
        }

        $pdo = DB::get_connection('lrnph_e');

        $stmt = $pdo->prepare("
            SELECT TOP 10
                BiometricsID,
                EmployeeID,
                FirstName,
                MiddleName,
                LastName,
                Department,
                Email
            FROM [LRNPH_E].[dbo].[lrn_master_list]
            WHERE
                (FirstName + ' ' + LastName) LIKE :search1
                OR
                (LastName + ' ' + FirstName) LIKE :search2
                OR
                (FirstName + ' ' + MiddleName + ' ' + LastName) LIKE :search3
                OR
                CAST(BiometricsID AS VARCHAR(50)) LIKE :bidsearch
            ORDER BY LastName, FirstName
        ");

        $stmt->execute([
            ':search1' => '%' . $q . '%',
            ':search2' => '%' . $q . '%',
            ':search3' => '%' . $q . '%',
            ':bidsearch' => $q . '%'
        ]);

        return array_map(
            [self::class, 'shapeEmployee'],
            $stmt->fetchAll()
        );
    }


    /* =========================================
       FORMAT MASTERLIST EMPLOYEE DATA
    ========================================= */

    private static function shapeEmployee(
        array $row
    ): array {

        $firstName = trim(
            (string) (
                $row['FirstName'] ?? ''
            )
        );

        $middleName = trim(
            (string) (
                $row['MiddleName'] ?? ''
            )
        );

        $lastName = trim(
            (string) (
                $row['LastName'] ?? ''
            )
        );

        $nameParts = array_filter([
            $firstName,
            $middleName,
            $lastName
        ]);

        $fullName = implode(
            ' ',
            $nameParts
        );

        return [
            'biometric_id' => trim(
                (string) (
                    $row['BiometricsID'] ?? ''
                )
            ),

            'employee_id' => trim(
                (string) (
                    $row['EmployeeID'] ?? ''
                )
            ),

            'full_name' => $fullName,

            'department' => trim(
                (string) (
                    $row['Department'] ?? ''
                )
            ),

            'email' => trim(
                (string) (
                    $row['Email'] ?? ''
                )
            )
        ];
    }

    private static function getSystemSetting(
        string $key,
        $default = null
    ) {
        $pdo = DB::get_connection('main_db');

        $stmt = $pdo->prepare("
        SELECT setting_value
        FROM [LRNPH_HR].[dbo].[eheart_system_setting]
        WHERE setting_key = :key
    ");

        $stmt->execute([
            ':key' => $key
        ]);

        $row = $stmt->fetch();

        return $row
            ? $row['setting_value']
            : $default;
    }
}
