<?php
include '../config/db.php';

$pdo = qa_db();

// -------------------------
// DATA TABLES INPUT
// -------------------------
$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');

// -------------------------
// BASE QUERY (USED IN BOTH COUNT + DATA)
// -------------------------
$baseQuery = "
FROM employee_reason_history h
LEFT JOIN employee_info i 
    ON h.employee_id = i.employee_id
";

// -------------------------
// SEARCH FILTER
// -------------------------
$where = "";
$params = [];

if ($search !== '') {
    $where = " WHERE 
        h.reason_for_update LIKE :search
        OR h.remarks LIKE :search
        OR i.first_name LIKE :search
        OR i.last_name LIKE :search
        OR h.updated_by LIKE :search
    ";
    $params[':search'] = "%$search%";
}

// -------------------------
// TOTAL RECORDS (NO FILTER)
// -------------------------
$totalStmt = $pdo->query("SELECT COUNT(*) FROM employee_reason_history");
$recordsTotal = (int)$totalStmt->fetchColumn();

// -------------------------
// FILTERED COUNT
// -------------------------
$countSql = "
SELECT COUNT(*)
$baseQuery
$where
";

$countStmt = $pdo->prepare($countSql);

foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}

$countStmt->execute();
$recordsFiltered = (int)$countStmt->fetchColumn();

// -------------------------
// PAGINATION (SQL SERVER 2012 SAFE)
// -------------------------
$startRow = $start + 1;
$endRow   = $start + $length;

// -------------------------
// MAIN DATA QUERY (ROW_NUMBER FIX)
// -------------------------
$sql = "
SELECT *
FROM (
    SELECT 
        h.id,
        h.reason_for_update,
        h.update_date,
        h.remarks,
        h.employee_id,
        h.updated_by,
        i.first_name,
        i.last_name,
        ROW_NUMBER() OVER (ORDER BY h.update_date DESC) AS rn
    $baseQuery
    $where
) t
WHERE t.rn BETWEEN :startRow AND :endRow
";

$stmt = $pdo->prepare($sql);

// bind search params
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}

// bind paging
$stmt->bindValue(':startRow', $startRow, PDO::PARAM_INT);
$stmt->bindValue(':endRow', $endRow, PDO::PARAM_INT);

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------------
// FORMAT OUTPUT FOR DATATABLES
// -------------------------
$rows = [];

foreach ($data as $row) {

    $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    if ($fullName === '') {
        $fullName = '-';
    }

    $rows[] = [
        $row['updated_by'] ?: 'SYSTEM',
        $row['reason_for_update'] ?? '-',
        $row['remarks'] ?? '-',
        $fullName,
        !empty($row['update_date'])
            ? date('Y-m-d', strtotime($row['update_date']))
            : '-'
    ];
}

// -------------------------
// RESPONSE
// -------------------------
echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $recordsTotal,
    "recordsFiltered" => $recordsFiltered,
    "data" => $rows
]);