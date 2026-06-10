<?php
require_once '../config/db.php';
header('Content-Type: application/json');

$pdo = qa_db();

$sql = "
SELECT 
    h.id,
    h.updated_by,
    h.reason_for_update,
    h.remarks,
    h.update_date,
    h.employee_id,
    h.original_employee_id,
    LTRIM(RTRIM(ISNULL(e.first_name, '') + ' ' + ISNULL(e.last_name, ''))) AS employee_name
FROM employee_reason_history h
LEFT JOIN employee_info e ON h.employee_id = e.employee_id
WHERE 1=1
";

$params = [];

/* =========================
   FILTERS
========================= */

// USER FILTER ✅ "system" → search for NULL updated_by
$user = trim($_GET['user'] ?? '');
if ($user !== '') {
    if (strtolower($user) === 'system') {
        $sql .= " AND h.updated_by IS NULL";
    } else {
        $sql .= " AND h.updated_by LIKE :user";
        $params[':user'] = "%$user%";
    }
}

if (!empty($_GET['reason'])) {
    $sql .= " AND h.reason_for_update LIKE :reason";
    $params[':reason'] = "%{$_GET['reason']}%";
}

if (isset($_GET['remarks_empty']) && $_GET['remarks_empty'] == '1') {
    $sql .= " AND (h.remarks IS NULL OR LTRIM(RTRIM(h.remarks)) = '')";
} elseif (!empty($_GET['remarks'])) {
    $sql .= " AND h.remarks LIKE :remarks";
    $params[':remarks'] = '%' . $_GET['remarks'] . '%';
}

if (!empty($_GET['from_date'])) {
    $sql .= " AND CAST(h.update_date AS DATE) >= :from_date";
    $params[':from_date'] = $_GET['from_date'];
}

if (!empty($_GET['to_date'])) {
    $sql .= " AND CAST(h.update_date AS DATE) <= :to_date";
    $params[':to_date'] = $_GET['to_date'];
}

$sql .= " ORDER BY h.update_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params); // ✅ cleaner than foreach bindValue

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   OUTPUT
========================= */
$result = [];

foreach ($data as $row) {
    $result[] = [
        "updated_by"           => $row['updated_by'] ?: 'SYSTEM',
        "reason_for_update"    => $row['reason_for_update'],
        "remarks"              => $row['remarks'],
        "employee_name"        => $row['employee_name'],
        "employee_id"          => $row['employee_id'],
        "original_employee_id" => $row['original_employee_id'],
        "update_date"          => $row['update_date'],
    ];
}

echo json_encode($result);