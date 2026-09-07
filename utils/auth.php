<?php
require_once __DIR__ . '/response.php';

class Auth
{
    const IDLE_LIMIT = 900; // 15 min, in seconds. Change number, change time.

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('eheart_session');
            session_start();
        }
    }

    public static function login(array $user): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'user_id'       => $user['user_id'],
            'biometric_id'  => $user['biometric_id'],
            'role_id'       => $user['role_id'],
            'role_code'     => $user['role_code'],
            'employee_name' => $user['employee_name'] ?? $user['biometric_id'],
            'department'    => $user['department'] ?? null,
            'employee_id'   => $user['employee_id'] ?? null,
        ];
        $_SESSION['last_activity'] = time(); // clock start now
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function user(): ?array
    {
        self::start();
        self::checkIdle(); // kill session if too idle, before return user
        return $_SESSION['user'] ?? null;
    }

    public static function checkIdle(): void
    {
        if (isset($_SESSION['user'], $_SESSION['last_activity'])) {
            $idleFor = time() - $_SESSION['last_activity'];
            if ($idleFor > self::IDLE_LIMIT) {
                $_SESSION = [];
                session_destroy();
                return;
            }
        }
        if (isset($_SESSION['user'])) {
            $_SESSION['last_activity'] = time(); // touch clock, still alive
        }
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            Response::error('Session expired. Please log in again.', 401);
        }
        return $user;
    }

    public static function requireRole(array $allowedRoleCodes): array
    {
        $user = self::requireLogin();
        if (
            $user['role_code'] !== 'SYSTEM_ADMIN'
            && !in_array($user['role_code'], $allowedRoleCodes, true)
        ) {
            Response::error(
                'Forbidden. You do not have access to this resource.',
                403
            );
        }
        return $user;
    }
}
