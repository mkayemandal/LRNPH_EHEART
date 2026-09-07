<?php

require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    Auth::requireRole(['MANAGER', 'SYSTEM_ADMIN']);

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Response::error('Method not allowed', 405);
    }

    $biometricId = trim($_GET['biometric_id'] ?? '');
    $query = trim($_GET['q'] ?? '');

    if ($query !== '') {
        Response::success(UserService::searchEmployeesByName($query));
        exit;
    }

    if ($biometricId === '') {
        Response::error('Biometric ID is required.', 422);
    }

    $employee = UserService::findEmployeeByBiometricId($biometricId);

    if (!$employee) {
        Response::error('No employee found with biometric ID: ' . $biometricId, 404);
    }

    $fullName = $employee['full_name']
        ?? $employee['name']
        ?? trim(
            ($employee['FirstName'] ?? '') . ' ' .
                ($employee['MiddleName'] ?? '') . ' ' .
                ($employee['LastName'] ?? '')
        );

    $department = $employee['department']
        ?? $employee['Department']
        ?? $employee['department_name']
        ?? '';

    Response::success([
        'biometric_id' => $employee['biometric_id'] ?? $employee['EmployeeID'] ?? $biometricId,
        'employee_id'  => $employee['EmployeeID'] ?? '',
        'full_name' => trim($fullName),
        'department' => trim($department),
        'email' => trim((string) ($employee['email'] ?? $employee['Email'] ?? '')),
    ]);
} catch (Throwable $e) {
    error_log('Heart Card employee lookup error: ' . $e->getMessage());
    Response::error('Unable to fetch employee information: ' . $e->getMessage(), 500);
}
