<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

try {
    $user = Auth::requireRole(['MANAGER']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    $input = requestBody();
    $missing = Validation::requireFields(
        $input,
        ['heart_card_id', 'receiver_biometric_id', 'receiver_department', 'receiver_full_name', 'core_values', 'action_performed', 'business_impact', 'why_beyond_normal']
    );
    if ($missing) {
        Response::error('Missing fields: ' . implode(', ', $missing), 422);
    }
    if (!is_array($input['core_values']) || count($input['core_values']) === 0) {
        Response::error('At least one Core Value must be selected.', 422);
    }
    $id = HeartCardService::resubmit(
        (int) $input['heart_card_id'],
        $input,
        $user['biometric_id'],
        $user['employee_name']
    );
    Response::success(['heart_card_id' => $id], 'Heart Card resubmitted for approval', 200);
} catch (\Throwable $e) {
    error_log('Resubmit Heart Card failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    Response::error($e->getMessage(), 500);
}