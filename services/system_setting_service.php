<?php

class SystemSettingService
{
    /**
     * Keys manageable via System Settings page.
     */
    private const MANAGED_KEYS = [
        'MANAGER_MONTHLY_REQUEST_LIMIT' => [
            'label' => 'Manager Monthly Request Limit',
            'description' => 'Maximum number of EHEART requests a Manager can submit per month.',
            'default' => 10
        ],
        'EMPLOYEE_MONTHLY_EHEART_LIMIT' => [
            'label' => 'Employee Monthly EHEART Limit',
            'description' => 'Maximum number of EHEART Cards an employee can receive per month.',
            'default' => 1
        ]
    ];

    public static function listManaged(): array
    {
        $pdo = DB::get_connection();

        $stmt = $pdo->query("
            SELECT setting_key, setting_value
            FROM eheart_system_setting
        ");

        $rows = $stmt->fetchAll();
        $byKey = [];

        foreach ($rows as $row) {
            $byKey[$row['setting_key']] = $row['setting_value'];
        }

        $result = [];

        foreach (self::MANAGED_KEYS as $key => $meta) {
            $result[] = [
                'setting_key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'setting_value' => isset($byKey[$key])
                    ? (int) $byKey[$key]
                    : $meta['default']
            ];
        }

        return $result;
    }

    public static function update(string $key, int $value): void
    {
        if (!array_key_exists($key, self::MANAGED_KEYS)) {
            throw new InvalidArgumentException('Unknown setting key: ' . $key);
        }

        if ($value <= 0) {
            throw new InvalidArgumentException('Value must be greater than 0.');
        }

        $pdo = DB::get_connection();

        $exists = $pdo->prepare("
            SELECT COUNT(*) FROM eheart_system_setting WHERE setting_key = :key
        ");
        $exists->execute([':key' => $key]);

        if ((int) $exists->fetchColumn() > 0) {
            $stmt = $pdo->prepare("
                UPDATE eheart_system_setting
                SET setting_value = :value, updated_at = SYSDATETIME()
                WHERE setting_key = :key
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO eheart_system_setting (setting_key, setting_value)
                VALUES (:key, :value)
            ");
        }

        $stmt->execute([
            ':key' => $key,
            ':value' => (string) $value
        ]);

        // old cached limit dead now. next read get fresh number.
        CacheService::forget('eh_setting_' . $key);
    }
}
