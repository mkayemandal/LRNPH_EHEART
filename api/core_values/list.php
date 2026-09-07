<?php
require_once __DIR__ . '/../../utils/bootstrap.php';

Auth::requireLogin();
$pdo = DB::get_connection();
$stmt = $pdo->query("
    SELECT [core_value_id]
          ,[core_value_name]
      FROM [LRNPH_HR].[dbo].[eheart_core_value]
     WHERE [is_active] = 1
     ORDER BY [core_value_name]
");
Response::success($stmt->fetchAll());