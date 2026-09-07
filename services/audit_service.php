<?php

class AuditService
{
    public static function log(?string $userBiometricId, string $action, string $module, $referenceId, string $description): void
    {
        try {
            $pdo = DB::get_connection();
            $stmt = $pdo->prepare(
                "INSERT INTO [LRNPH_HR].[dbo].[eheart_audit_log](user_biometric_id,action,module,reference_id,description,ip_address)
                VALUES(:uid,:action,:module,:ref,:desc,:ip)"
            );
            $stmt->execute([
                ':uid' => $userBiometricId,
                ':action' => $action,
                ':module' => $module,
                ':ref' => $referenceId,
                ':desc' => $description,
                ':ip' => clientIp()
            ]);
        } catch (Throwable $e) {
            error_log('Audit log write failed: ' . $e->getMessage());
        }
    }

    public static function list(int $limit = 100, int $offset = 0, ?string $module = null): array
    {
        $pdo = DB::get_connection();

        $sql = "SELECT a.*,u.full_name AS user_full_name,r.role_name AS user_role
        FROM [LRNPH_HR].[dbo].[eheart_audit_log] a
        LEFT JOIN [LRNPH_HR].[dbo].[eheart_user] u
        ON u.biometric_id=a.user_biometric_id
        LEFT JOIN [LRNPH_HR].[dbo].[eheart_role] r
        ON r.role_id=u.role_id";

        $params = [];

        if ($module) {
            $sql .= " WHERE a.module=:module";
            $params[':module'] = $module;
        }

        $sql .= " ORDER BY a.audit_log_id DESC";

        if ($limit > 0) {
            $sql .= " OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
        }

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        if ($limit > 0) {
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }

        $stmt->execute();

        return self::attachEmployeeIds($stmt->fetchAll());
    }

    private static function attachEmployeeIds(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['user_employee_id'] = null;
        }
        unset($row);

        $bids = array_unique(array_filter(array_column($rows, 'user_biometric_id')));

        if (empty($bids)) {
            return $rows;
        }

        try {
            $mstPdo = DB::get_connection('lrnph_e');

            $placeholders = [];
            $params = [];

            foreach (array_values($bids) as $i => $bid) {
                $placeholders[] = ":bid{$i}";
                $params[":bid{$i}"] = $bid;
            }

            $stmt = $mstPdo->prepare(
                "SELECT BiometricsID,EmployeeID
                FROM [LRNPH_E].[dbo].[lrn_master_list]
                WHERE BiometricsID IN(" . implode(',', $placeholders) . ")"
            );

            $stmt->execute($params);

            $map = [];

            foreach ($stmt->fetchAll() as $row) {
                $map[$row['BiometricsID']] = $row['EmployeeID'];
            }

            foreach ($rows as &$row) {
                $row['user_employee_id'] = $map[$row['user_biometric_id']] ?? null;
            }

            unset($row);
        } catch (Throwable $e) {
            error_log('Audit employee lookup failed: ' . $e->getMessage());
        }

        return $rows;
    }

    public static function listByModule(string|array $module, array $excludeActions, ?string $status = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $pdo = DB::get_connection();

        $sql = "SELECT
        a.[audit_log_id],
        a.[user_biometric_id],
        a.[action],
        a.[module],
        a.[reference_id],
        a.[description],
        a.[ip_address],
        a.[created_at],
        hc.[heart_card_id],
        hc.[receiver_biometric_id],
        hc.[receiver_full_name],
        red.[processed_by_name],
        manager.[full_name] AS manager_full_name,
        manager_role.[role_name] AS performed_by_role
        FROM [LRNPH_HR].[dbo].[eheart_audit_log] a
        LEFT JOIN [LRNPH_HR].[dbo].[eheart_redemption] red
        ON red.redemption_id = a.reference_id
        LEFT JOIN [LRNPH_HR].[dbo].[eheart_heart_card] hc
        ON (a.module = 'heart_card' AND hc.heart_card_id = a.reference_id)
        OR (a.module = 'redemption' AND hc.heart_card_id = red.heart_card_id)
        LEFT JOIN [LRNPH_HR].[dbo].[eheart_user] manager
        ON manager.biometric_id=a.user_biometric_id
        LEFT JOIN [LRNPH_HR].[dbo].[eheart_role] manager_role
        ON manager_role.role_id=manager.role_id
        WHERE a.[module] IN (";

        $modules = is_array($module) ? array_values($module) : [$module];
        $modulePlaceholders = [];
        $params = [];
        foreach ($modules as $i => $moduleName) {
            $placeholder = ":module{$i}";
            $modulePlaceholders[] = $placeholder;
            $params[$placeholder] = $moduleName;
        }
        $sql .= implode(',', $modulePlaceholders) . ')';

        foreach ($excludeActions as $i => $act) {
            $sql .= " AND a.[action]!=:exc{$i}";
            $params[":exc{$i}"] = $act;
        }

        if ($status) {
            $sql .= " AND a.[action]=:status";
            $params[':status'] = $status;
        }

        if ($dateFrom) {
            $sql .= " AND CAST(a.[created_at] AS DATE)>=:date_from";
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo) {
            $sql .= " AND CAST(a.[created_at] AS DATE)<=:date_to";
            $params[':date_to'] = $dateTo;
        }

        $sql .= " ORDER BY a.[created_at] DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return self::attachEmployeeIds($stmt->fetchAll());
    }
}
