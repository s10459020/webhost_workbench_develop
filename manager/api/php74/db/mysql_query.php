<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/mysql_lib.php';

$sql = trim((string)req('sql', ''));
if ($sql === '') tres('sql is required', 400);

$conn = mysql_conn_from_req();

if (!$conn->multi_query($sql)) tres('query failed: ' . $conn->error, 500);

$payload = null;
$affectedTotal = 0;

do {
    $res = $conn->store_result();
    if ($res instanceof mysqli_result) {
        $columns = [];
        foreach ($res->fetch_fields() as $field) $columns[] = $field->name;
        $rows = [];
        while ($row = $res->fetch_assoc()) $rows[] = $row;
        $payload = [
            'ok' => true,
            'kind' => 'resultset',
            'columns' => $columns,
            'rows' => $rows,
            'rowCount' => count($rows)
        ];
        $res->free();
    } else {
        $affected = $conn->affected_rows;
        if ($affected > 0) $affectedTotal += $affected;
        $payload = [
            'ok' => true,
            'kind' => 'execute',
            'affectedRows' => $affectedTotal,
            'insertId' => $conn->insert_id
        ];
    }
} while ($conn->more_results() && $conn->next_result());

if ($conn->errno) tres('query failed: ' . $conn->error, 500);

$conn->close();
jres($payload);
