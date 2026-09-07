<?php

/* LOAD .ENV */
$envFile = dirname(__DIR__) . '/.env';

if (file_exists($envFile)) {
    $lines = file(
        $envFile,
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value);

        if (
            strlen($value) >= 2 &&
            (
                ($value[0] === '"' && $value[-1] === '"') ||
                ($value[0] === "'" && $value[-1] === "'")
            )
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}

/* PHP ERROR LOGGING */
$eheartLogFile = dirname(__DIR__) . '/storage_app.log';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $eheartLogFile);

/* COMPOSER */
require_once __DIR__ . '/../vendor/autoload.php';

/* CORE UTILITIES */
require_once __DIR__ . '/../connection/database.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permission.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/json.php';
require_once __DIR__ . '/date_helper.php';
require_once __DIR__ . '/logger.php';

/* SERVICES */
require_once __DIR__ . '/../services/audit_service.php';
require_once __DIR__ . '/../services/notification_service.php';
require_once __DIR__ . '/../services/user_service.php';
require_once __DIR__ . '/../services/heart_card_service.php';
require_once __DIR__ . '/../services/redemption_service.php';
require_once __DIR__ . '/../services/gift_certificate_service.php';
require_once __DIR__ . '/../services/system_setting_service.php';

/* FATAL ERROR HANDLER */
register_shutdown_function(static function (): void {
    $error = error_get_last();

    if (
        !$error ||
        !in_array(
            $error['type'],
            [
                E_ERROR,
                E_PARSE,
                E_CORE_ERROR,
                E_COMPILE_ERROR
            ],
            true
        )
    ) {
        return;
    }

    error_log(sprintf(
        'Fatal eHeart error: %s in %s:%d',
        $error['message'],
        $error['file'],
        $error['line']
    ));

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');

        echo json_encode([
            'success' => false,
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});

/* EMAIL CONFIGURATION */
if (!defined('EH_ENABLE_EMAIL_NOTIFICATIONS')) {
    define(
        'EH_ENABLE_EMAIL_NOTIFICATIONS',
        filter_var(
            getenv('EH_ENABLE_EMAIL_NOTIFICATIONS') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        )
    );
}

if (!defined('EH_SMTP_HOST')) {
    define(
        'EH_SMTP_HOST',
        getenv('EH_SMTP_HOST') ?: ''
    );
}

if (!defined('EH_SMTP_PORT')) {
    define(
        'EH_SMTP_PORT',
        (int) (getenv('EH_SMTP_PORT') ?: 587)
    );
}

if (!defined('EH_SMTP_USERNAME')) {
    define(
        'EH_SMTP_USERNAME',
        getenv('EH_SMTP_USERNAME') ?: ''
    );
}

if (!defined('EH_SMTP_PASSWORD')) {
    define(
        'EH_SMTP_PASSWORD',
        getenv('EH_SMTP_PASSWORD') ?: ''
    );
}

if (!defined('EH_SMTP_SECURE')) {
    define(
        'EH_SMTP_SECURE',
        getenv('EH_SMTP_SECURE') ?: 'tls'
    );
}

if (!defined('EH_SMTP_VERIFY_PEER')) {
    define(
        'EH_SMTP_VERIFY_PEER',
        filter_var(
            getenv('EH_SMTP_VERIFY_PEER') ?: 'true',
            FILTER_VALIDATE_BOOLEAN
        )
    );
}

if (!defined('EH_EMAIL_FROM')) {
    define(
        'EH_EMAIL_FROM',
        getenv('EH_EMAIL_FROM') ?: ''
    );
}

if (!defined('EH_EMAIL_FROM_NAME')) {
    define(
        'EH_EMAIL_FROM_NAME',
        getenv('EH_EMAIL_FROM_NAME') ?: 'eHeart'
    );
}

if (!defined('EH_EMAIL_SUBJECT_PREFIX')) {
    define(
        'EH_EMAIL_SUBJECT_PREFIX',
        getenv('EH_EMAIL_SUBJECT_PREFIX') ?: '[eHeart] '
    );
}

/* CORS */
$allowedOrigin = getenv('EH_PN_ORIGIN') ?: 'http://10.2.0.16:89';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (
    $requestOrigin !== '' &&
    hash_equals($allowedOrigin, $requestOrigin)
) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* START AUTH SESSION */
Auth::start();

/* REQUEST BODY */
function requestBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    return is_array($data)
        ? $data
        : $_POST;
}

/* CLIENT IP */
function clientIp(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
}
