<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

Auth::requireRole(['HR_ADMIN', 'SYSTEM_ADMIN']);

$employeeId = $_GET['employee_id'] ?? null;
$controlNumber = $_GET['control_number'] ?? null;

if (!$employeeId && !$controlNumber) {
    Response::error('employee_id or control_number is required.', 422);
}

if ($controlNumber) {
    $result = HeartCardService::findAvailableForRedemptionByControlNumber($controlNumber);
    if (!$result) {
        Response::error('No available Heart Card found for this Control No.', 404);
    }
    Response::success($result);
}

$result = HeartCardService::findAvailableForRedemption($employeeId);
if (!$result) {
    Response::error('No available Heart Card found for this employee.', 404);
}

Response::success($result);
