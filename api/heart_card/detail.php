<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

Auth::requireLogin();
$id = (int) ($_GET['id'] ?? 0);
$card = HeartCardService::find($id);
if (!$card) {
    Response::error('Heart Card not found.', 404);
}
Response::success($card);
