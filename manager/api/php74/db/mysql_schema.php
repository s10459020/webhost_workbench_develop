<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/mysql_lib.php';

$conn = mysql_conn_from_req();
$database = trim((string)req('database', ''));

$dbRows = [];
$dbRes = $conn->query('SHOW DATABASES');
if ($dbRes !== false) {
    while ($row = $dbRes->fetch_row()) $dbRows[] = (string)$row[0];
    $dbRes->free();
}

$tables = [];
$dbNameRes = $conn->query('SELECT DATABASE() as db');
$activeDb = $database;
if ($activeDb === '' && $dbNameRes !== false) {
    $dbNameRow = $dbNameRes->fetch_assoc();
    $activeDb = (string)($dbNameRow['db'] ?? '');
}
if ($dbNameRes) $dbNameRes->free();
if ($activeDb !== '') {
    $sql = 'SHOW TABLES FROM ' . mysql_quote_ident($activeDb);
    $tbRes = $conn->query($sql);
    if ($tbRes !== false) {
        while ($row = $tbRes->fetch_row()) $tables[] = (string)$row[0];
        $tbRes->free();
    }
}

$conn->close();

jres([
    'ok' => true,
    'database' => $activeDb,
    'databases' => $dbRows,
    'tables' => $tables
]);
