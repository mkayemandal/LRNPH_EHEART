<?php

require_once __DIR__ . '/../../utils/bootstrap.php';

error_log(
    'REQUEST_REVISION_RUNTIME: display_errors=' .
    ini_get('display_errors') .
    ' | display_startup_errors=' .
    ini_get('display_startup_errors') .
    ' | log_errors=' .
    ini_get('log_errors') .
    ' | error_log=' .
    ini_get('error_log')
);

try {
    $user = Auth::requireRole(['HR_ADMIN', 'SYSTEM_ADMIN']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }

    $input = requestBody();

    $missing = Validation::requireFields($input, ['heart_card_id', 'reason']);

    if ($missing) {
        Response::error('Missing fields: ' . implode(', ', $missing), 422);
    }

    $heartCardId = filter_var($input['heart_card_id'], FILTER_VALIDATE_INT);

    if ($heartCardId === false || $heartCardId <= 0) {
        Response::error('Invalid Heart Card ID.', 422);
    }

    $reason = trim((string) $input['reason']);

    if ($reason === '') {
        Response::error('Revision notes are required.', 422);
    }

    $id = HeartCardService::requestRevision(
        (int) $heartCardId,
        $reason,
        $user['biometric_id']
    );

    Response::success(
        ['heart_card_id' => $id],
        'Sent back to Manager for revision',
        200
    );
} catch (Throwable $e) {
    error_log(
        'Request revision failed: ' .
            $e->getMessage() .
            ' in ' .
            $e->getFile() .
            ':' .
            $e->getLine()
    );

    Response::error('Unable to request revision.', 500);
}
