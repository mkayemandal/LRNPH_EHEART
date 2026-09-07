<?php

require_once __DIR__ . '/../../utils/bootstrap.php';

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error(
            'Method not allowed',
            405
        );
    }

    $input = requestBody();

    $email = trim(
        (string) ($input['email'] ?? '')
    );

    if ($email === '') {
        Response::error(
            'Email is required.',
            422
        );
    }

    $employee =
        UserService::findEmployeeByEmail($email);

    if (!$employee) {
        Response::success([
            'employee_found' => false,
            'employee' => null
        ]);

        return;
    }

    Response::success([
        'employee_found' => true,
        'employee' => $employee
    ]);
} catch (Throwable $e) {

    error_log(
        'PN user identification failed: ' .
            $e->getMessage()
    );

    Response::error(
        'Unable to identify employee.',
        500
    );
}
