<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$pdo = qa_db();

$draw   = $_POST['draw'] ?? 0;
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 25);
$search = $_POST['search']['value'] ?? '';

/* =========================
   ORDERING
========================= */
$columns = [
    0 => 'brand',
    1 => 'agency',
    2 => 'status'
];

$orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
$orderDir = ($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$orderColumn = $columns[$orderColumnIndex] ?? 'brand';

/* =========================
   SEARCH
========================= */
$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (
        brand LIKE :search OR
        agency LIKE :search OR
        status LIKE :search
    )";
    $params[':search'] = "%$search%";
}

/* =========================
   TOTAL
========================= */
$totalStmt = $pdo->query("SELECT COUNT(*) FROM brands");
$recordsTotal = $totalStmt->fetchColumn();

/* =========================
   FILTERED COUNT
========================= */
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM brands $where");
$countStmt->execute($params);
$recordsFiltered = $countStmt->fetchColumn();

/* =========================
   DATA (SQL SERVER 2012)
========================= */
$sql = "
SELECT *
FROM (
    SELECT
        id,
        brand,
        agency,
        status,
        ROW_NUMBER() OVER (ORDER BY $orderColumn $orderDir) AS rownum
    FROM brands
    $where
) AS t
WHERE t.rownum > :start
  AND t.rownum <= :end
";

$stmt = $pdo->prepare($sql);

/* bind search */
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

/* pagination */
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':end', $start + $length, PDO::PARAM_INT);

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FORMAT STATUS
========================= */
foreach ($data as &$row) {
    $row['status'] = ($row['status'] == 'active' || $row['status'] == 1)
        ? 'Active'
        : 'Inactive';

    unset($row['rownum']);
}

/* =========================
   OUTPUT
========================= */
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($recordsTotal),
    "recordsFiltered" => intval($recordsFiltered),
    "data" => $data
]);