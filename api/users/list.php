<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

Auth::requireRole(['SYSTEM_ADMIN']);
Response::success(UserService::listUsers());
