<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

$user = Auth::requireLogin();
$unreadOnly = isset($_GET['unread']) && $_GET['unread'] === '1';
Response::success(NotificationService::listForUser($user['biometric_id'], $user['role_code'], $unreadOnly));
