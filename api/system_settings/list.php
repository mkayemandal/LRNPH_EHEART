<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

Auth::requireRole(['HR_ADMIN', 'SYSTEM_ADMIN']);
Response::success(SystemSettingService::listManaged());
