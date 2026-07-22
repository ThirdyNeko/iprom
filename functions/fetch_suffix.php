<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$draw   = intval($_POST['draw'] ?? 1);
$start  = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 25);
$name   = trim($_POST['name'] ?? '');

$conditions = [];
$params     = [];

if ($name !== '') {
    $conditions[] = "suffix LIKE :name";
    $params[':name'] = '%' . $name . '%';
}

$where = count($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// TOTAL RECORDS
$totalSql = "SELECT COUNT(*) FROM suffix";
$totalStmt = $pdo->query($totalSql);
$totalRecords = (int) $totalStmt->fetchColumn();

// FILTERED COUNT
$filteredSql  = "SELECT COUNT(*) FROM suffix $where";
$filteredStmt = $pdo->prepare($filteredSql);
$filteredStmt->execute($params);
$filteredRecords = (int) $filteredStmt->fetchColumn();

// PAGINATED DATA (ROW_NUMBER for SQL Server 2012)
$offset = $start + 1;
$end    = $start + $length;

$dataSql = "
    SELECT id, suffix
    FROM (
        SELECT
            id,
            suffix,
            ROW_NUMBER() OVER (ORDER BY suffix ASC) AS rn
        FROM suffix
        $where
    ) AS paged
    WHERE rn BETWEEN :offset AND :end
";

$dataParams = array_merge($params, [
    ':offset' => $offset,
    ':end'    => $end,
]);

$dataStmt = $pdo->prepare($dataSql);
$dataStmt->execute($dataParams);
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data'            => $rows,
]);