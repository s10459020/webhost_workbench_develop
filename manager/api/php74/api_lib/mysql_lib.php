<?php
declare(strict_types=1);
require_once __DIR__ . '/_lib.php';

function mysql_conn_from_req(): mysqli {
    $host = trim((string)req('host', '127.0.0.1'));
    $port = (int)trim((string)req('port', '3306'));
    $user = (string)req('user', 'root');
    $pass = (string)req('password', '');
    $db = trim((string)req('database', ''));

    if ($host === '') tres('host is required', 400);
    if ($user === '') tres('user is required', 400);

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($host, $user, $pass, $db !== '' ? $db : null, $port);
    if ($conn->connect_errno) tres('mysql connect failed: ' . $conn->connect_error, 500);
    $conn->set_charset('utf8mb4');
    return $conn;
}

function mysql_quote_ident(string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
}
