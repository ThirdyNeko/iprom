<?php
require_once '../config/db.php';

$pdo = qa_db();

// =========================
// INPUTS
// =========================
$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
$search = trim($_POST['name'] ?? '');

$startRow = $start + 1;
$endRow   = $start + $length;

// =========================
// SEARCH CONDITION
// =========================
$where = "";
$hasSearch = false;

if ($search !== '') {
    $where = "
        WHERE 
            agencies       LIKE :s1 OR
            contact_person LIKE :s2 OR
            email          LIKE :s3
    ";
    $hasSearch = true;
}

// =========================
// TOTAL
// =========================
$totalStmt = $pdo->query("SELECT COUNT(*) FROM agencies");
$recordsTotal = $totalStmt->fetchColumn();

// FILTERED COUNT
if ($hasSearch) {
    $filteredStmt = $pdo->prepare("SELECT COUNT(*) FROM agencies $where");
    $filteredStmt->bindValue(':s1', "%$search%", PDO::PARAM_STR);
    $filteredStmt->bindValue(':s2', "%$search%", PDO::PARAM_STR);
    $filteredStmt->bindValue(':s3', "%$search%", PDO::PARAM_STR);
    $filteredStmt->execute();
    $recordsFiltered = $filteredStmt->fetchColumn();
} else {
    $recordsFiltered = $recordsTotal;
}

// =========================
// DATA QUERY
// =========================
$sql = "
    SELECT *
    FROM (
        SELECT 
            id,
            agencies,
            contact_person,
            contact_number,
            tel_number,
            email,
            status,
            ROW_NUMBER() OVER (ORDER BY agencies ASC, id ASC) AS rn
        FROM agencies
        $where
    ) t
    WHERE t.rn BETWEEN :startRow AND :endRow
    ORDER BY t.rn
";

$stmt = $pdo->prepare($sql);

$stmt->bindValue(':startRow', $startRow, PDO::PARAM_INT);
$stmt->bindValue(':endRow',   $endRow,   PDO::PARAM_INT);

// DATA QUERY binds
if ($hasSearch) {
    $stmt->bindValue(':s1', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(':s2', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(':s3', "%$search%", PDO::PARAM_STR);
}

$stmt->execute();

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =========================
// RESPONSE
// =========================
echo json_encode([
    "draw"            => $draw,
    "recordsTotal"    => (int)$recordsTotal,
    "recordsFiltered" => (int)$recordsFiltered,
    "data"            => $data
]);