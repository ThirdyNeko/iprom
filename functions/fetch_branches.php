<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$pdo = qa_db();

$draw   = $_POST['draw'] ?? 0;
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 25);
$name   = trim($_POST['name'] ?? '');  // <-- custom filter from JS

$columns = [
    0 => 'branch',
    1 => 'corpo',
    2 => 'region',
    3 => 'area',
    4 => 'status'
];

$orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
$orderDir = ($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$orderColumn = $columns[$orderColumnIndex] ?? 'branch';

/* SEARCH */
$where = "WHERE 1=1";
$params = [];

if (!empty($name)) {
    $where .= " AND (
        branch LIKE :name1 OR
        corpo  LIKE :name2 OR
        region LIKE :name3 OR
        area   LIKE :name4
    )";
    $params[':name1'] = "%$name%";
    $params[':name2'] = "%$name%";
    $params[':name3'] = "%$name%";
    $params[':name4'] = "%$name%";
}

/* TOTAL */
$totalStmt = $pdo->query("SELECT COUNT(*) FROM branches");
$recordsTotal = $totalStmt->fetchColumn();

/* FILTERED COUNT */
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM branches $where");
$countStmt->execute($params);
$recordsFiltered = $countStmt->fetchColumn();

/* DATA (SQL Server 2012 ROW_NUMBER pagination) */
$sql = "
SELECT *
FROM (
    SELECT
        branch,
        corpo,
        region,
        area,
        status,
        branch_code,
        ROW_NUMBER() OVER (ORDER BY $orderColumn $orderDir) AS rownum
    FROM branches
    $where
) AS t
WHERE t.rownum > :start
  AND t.rownum <= :end
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':end', $start + $length, PDO::PARAM_INT);

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($data as &$row) {
    $row['status'] = ($row['status'] == 1) ? 'Active' : 'Inactive';
    unset($row['rownum']);
}

echo json_encode([
    "draw"            => intval($draw),
    "recordsTotal"    => intval($recordsTotal),
    "recordsFiltered" => intval($recordsFiltered),
    "data"            => $data
]);