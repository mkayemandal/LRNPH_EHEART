<?php

/**
 * Included at the top of every protected page. Ensures a logged-in session
 * and exposes $currentUser for the page + layout partials.
 *
 * Usage:
 *   require_once __DIR__ . '/../_guard.php';
 *   $allowedRoles = ['MANAGER']; // optional, omit to allow any logged-in role
 */
require_once __DIR__ . '/../connection/database.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/permission.php';

Auth::start();

if (!Auth::check()) {
    header('Location: /eheart/auth/login.php');
    exit;
}

$currentUser = Auth::user();

if (!empty($allowedRoles) && !in_array($currentUser['role_code'], $allowedRoles, true)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}