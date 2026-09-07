<?php

class EmployeeService
{
    public static function findByBiometricId(string $biometricId): ?array
    {
        $pdo = DB::get_connection();
        $stmt = $pdo->prepare("
            SELECT TOP 1
                BiometricsID,
                FirstName,
                MiddleName,
                LastName,
                Department,
                Email
            FROM [LRNPH_E].[dbo].[lrn_master_list]
            WHERE BiometricsID = :bid
        ");
        $stmt->execute([':bid' => $biometricId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return self::shape($row);
    }

    public static function searchByName(string $q): array
    {
        $pdo = DB::get_connection();
        $stmt = $pdo->prepare("
            SELECT TOP 10
                BiometricsID,
                FirstName,
                MiddleName,
                LastName,
                Department,
                Email
            FROM [LRNPH_E].[dbo].[lrn_master_list]
            WHERE (FirstName + ' ' + LastName) LIKE :q
               OR (LastName + ' ' + FirstName) LIKE :q
        ");
        $stmt->execute([':q' => '%' . $q . '%']);
        return array_map([self::class, 'shape'], $stmt->fetchAll());
    }

    private static function shape(array $row): array
    {
        $middle = trim((string)($row['MiddleName'] ?? ''));
        $fullName = trim(
            $row['FirstName'] . ' ' .
                ($middle !== '' ? $middle . ' ' : '') .
                $row['LastName']
        );
        return [
            'biometric_id' => $row['BiometricsID'],
            'full_name'    => $fullName,
            'department'   => $row['Department'],
            'email'        => $row['Email'],
        ];
    }
}
