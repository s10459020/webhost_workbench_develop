<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/mysql_lib.php';

function sql_value(mysqli $conn, $value): string {
    if ($value === null) return 'NULL';
    if (is_int($value) || is_float($value)) return (string)$value;
    return "'" . $conn->real_escape_string((string)$value) . "'";
}

$conn = mysql_conn_from_req();
$filename = 'mysql_export_' . date('Ymd_His') . '.sql';

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "-- SHV MySQL export\n";
echo '-- exported at ' . date('c') . "\n\n";
echo "SET NAMES utf8mb4;\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

$dbRes = $conn->query('SHOW DATABASES');
while ($dbRow = $dbRes->fetch_row()) {
    $db = (string)$dbRow[0];
    if (in_array($db, ['information_schema', 'performance_schema', 'mysql', 'sys'], true)) continue;

    echo "--\n-- Database: " . $db . "\n--\n";
    echo 'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $db) . "`;\n";
    echo 'USE `' . str_replace('`', '``', $db) . "`;\n\n";

    $tbRes = $conn->query('SHOW TABLES FROM `' . str_replace('`', '``', $db) . '`');
    $tables = [];
    while ($tbRow = $tbRes->fetch_row()) $tables[] = (string)$tbRow[0];
    $tbRes->free();

    foreach ($tables as $table) {
        $qdb = '`' . str_replace('`', '``', $db) . '`';
        $qt = '`' . str_replace('`', '``', $table) . '`';
        $full = $qdb . '.' . $qt;

        $objType = 'BASE TABLE';
        $typeRes = $conn->query(
            "SELECT TABLE_TYPE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='"
            . $conn->real_escape_string($db)
            . "' AND TABLE_NAME='"
            . $conn->real_escape_string($table)
            . "' LIMIT 1"
        );
        if ($typeRes instanceof mysqli_result) {
            $typeRow = $typeRes->fetch_assoc();
            $objType = (string)($typeRow['TABLE_TYPE'] ?? 'BASE TABLE');
            $typeRes->free();
        }

        if (strcasecmp($objType, 'VIEW') === 0) {
            $createRes = $conn->query("SHOW CREATE VIEW {$full}");
            if (!($createRes instanceof mysqli_result)) tres('export failed on view ' . $db . '.' . $table . ': ' . $conn->error, 500);
            $createRow = $createRes->fetch_assoc();
            $createSql = (string)($createRow['Create View'] ?? '');
            $createRes->free();
            echo "DROP VIEW IF EXISTS {$qt};\n";
            if ($createSql !== '') echo $createSql . ";\n\n";
            continue;
        }

        $createRes = $conn->query("SHOW CREATE TABLE {$full}");
        if (!($createRes instanceof mysqli_result)) tres('export failed on table ' . $db . '.' . $table . ': ' . $conn->error, 500);
        $createRow = $createRes->fetch_assoc();
        $createSql = (string)($createRow['Create Table'] ?? '');
        $createRes->free();

        echo "DROP TABLE IF EXISTS {$qt};\n";
        if ($createSql !== '') echo $createSql . ";\n\n";

        $dataRes = $conn->query("SELECT * FROM {$full}");
        if ($dataRes instanceof mysqli_result) {
            $fields = [];
            foreach ($dataRes->fetch_fields() as $f) $fields[] = '`' . str_replace('`', '``', $f->name) . '`';
            $fieldSql = implode(', ', $fields);
            while ($row = $dataRes->fetch_assoc()) {
                $vals = [];
                foreach ($row as $v) $vals[] = sql_value($conn, $v);
                echo "INSERT INTO {$qt} ({$fieldSql}) VALUES (" . implode(', ', $vals) . ");\n";
            }
            $dataRes->free();
        }
        echo "\n";
    }
}
if ($dbRes) $dbRes->free();
echo "SET FOREIGN_KEY_CHECKS=1;\n";
$conn->close();
exit;
