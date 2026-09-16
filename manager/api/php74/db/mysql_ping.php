<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/mysql_lib.php';

$conn = mysql_conn_from_req();
$serverInfo = $conn->server_info;
$threadId = $conn->thread_id;
$conn->close();

jres([
    'ok' => true,
    'message' => 'mysql connected',
    'serverInfo' => $serverInfo,
    'threadId' => $threadId
]);
