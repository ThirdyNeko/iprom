<?php
require_once '../config/db.php';

$pdo = qa_db();

// =========================
// INPUTS
// =========================
$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');

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
            agencies LIKE :search OR
            contact_person LIKE :search OR
            contact_number LIKE :search OR
            tel_number LIKE :search OR
            email LIKE :search OR
            CAST(status AS VARCHAR(10)) LIKE :search
    ";
    $hasSearch = true;
}

// =========================
// TOTAL
// =========================
$totalStmt = $pdo->query("SELECT COUNT(*) FROM agencies");
$recordsTotal = $totalStmt->fetchColumn();

// =========================
// FILTERED COUNT
// =========================
if ($hasSearch) {
    $filteredStmt = $pdo->prepare("
        SELECT COUNT(*) FROM agencies $where
    ");
    $filteredStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
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

// bind pagination ONLY
$stmt->bindValue(':startRow', $startRow, PDO::PARAM_INT);
$stmt->bindValue(':endRow', $endRow, PDO::PARAM_INT);

// bind search ONLY if needed
if ($hasSearch) {
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}

$stmt->execute();

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =========================
// RESPONSE
// =========================
echo json_encode([
    "draw" => $draw,
    "recordsTotal" => (int)$recordsTotal,
    "recordsFiltered" => (int)$recordsFiltered,
    "data" => $data
]);