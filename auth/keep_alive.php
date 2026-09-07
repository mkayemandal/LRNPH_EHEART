<?php
require_once __DIR__ . '/../connection/database.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/response.php';

Auth::start();

if (!Auth::check()) {
    Response::error('Session expired.', 401);
    exit;
}

Response::success(['alive' => true]);