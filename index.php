<?php
require_once __DIR__ . '/connection/database.php';
require_once __DIR__ . '/utils/auth.php';
Auth::start();

if (Auth::check()) {
    header('Location: /eheart/pages/manager/dashboard.php');
} else {
    header('Location: /eheart/auth/login.php');
}
exit;
