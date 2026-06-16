<?php
require_once '../config/db.php';
header('Content-Type: application/json');

$pdo = qa_db();

$sql = "
SELECT 
    p.id,
    p.first_name,
    p.middle_name,
    p.last_name,
    p.suffix,
    p.branch,
    b.branch AS branch_name,   
    p.brand,
    p.status,
    p.employment_status,
    p.sub_status,
    p.agency,
    p.corpo,
    p.gender,
    p.birthday,
    p.date_hired,
    p.assignment_date,
    p.last_assigned_by,
    b.area,
    b.region
FROM employee_info p
LEFT JOIN IPROM.dbo.branches b
    ON p.branch = b.branch_code
WHERE 1=1
";

$params = [];

/* =========================
   FILTERS
========================= */

if (!empty($_GET['region'])) {
    $sql .= " AND b.region = :region";
    $params[':region'] = $_GET['region'];
}

if (!empty($_GET['area'])) {
    $sql .= " AND b.area = :area";
    $params[':area'] = $_GET['area'];
}

if (!empty($_GET['corpo'])) {
    $sql .= " AND p.corpo = :corpo";
    $params[':corpo'] = $_GET['corpo'];
}

if (!empty($_GET['branch'])) {
    $sql .= " AND p.branch = :branch";
    $params[':branch'] = $_GET['branch'];
}

if (!empty($_GET['brand'])) {
    $sql .= " AND p.brand = :brand";
    $params[':brand'] = $_GET['brand'];
}

if (!empty($_GET['status'])) {
    $sql .= " AND p.status = :status";
    $params[':status'] = $_GET['status'];
}

if (!empty($_GET['employment_status'])) {
    $sql .= " AND p.employment_status = :employment_status";
    $params[':employment_status'] = $_GET['employment_status'];
}

if (!empty($_GET['sub_status'])) {
    $sql .= " AND p.sub_status = :sub_status";
    $params[':sub_status'] = $_GET['sub_status'];
}

if (!empty($_GET['agency'])) {
    $sql .= " AND p.agency = :agency";
    $params[':agency'] = $_GET['agency'];
}

/* =========================
   SEARCH (IMPROVED)
========================= */
if (!empty($_GET['search'])) {
    $sql .= " AND (
        p.first_name LIKE :search1 OR
        p.last_name LIKE :search2 OR
        p.branch LIKE :search3 OR
        p.brand LIKE :search4 OR
        p.agency LIKE :search5 OR
        p.corpo LIKE :search6
    )";

    $search = "%" . $_GET['search'] . "%";

    $params[':search1'] = $search;
    $params[':search2'] = $search;
    $params[':search3'] = $search;
    $params[':search4'] = $search;
    $params[':search5'] = $search;
    $params[':search6'] = $search;
}

/* =========================
   DATE FILTERS
========================= */
if (!empty($_GET['from_date'])) {
    $sql .= " AND p.assignment_date >= :from_date";
    $params[':from_date'] = $_GET['from_date'];
}

if (!empty($_GET['to_date'])) {
    $sql .= " AND p.assignment_date <= :to_date";
    $params[':to_date'] = $_GET['to_date'];
}

/* =========================
   ASSIGNED BY (MISSING FIX)
========================= */
if (!empty($_GET['assigned_by'])) {
    $sql .= " AND p.last_assigned_by = :assigned_by";
    $params[':assigned_by'] = $_GET['assigned_by'];
}

$sql .= " ORDER BY p.last_name ASC";

$stmt = $pdo->prepare($sql);

foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   OUTPUT
========================= */
$result = [];

foreach ($data as $p) {
    $result[] = [
        "name" => trim($p['first_name'] . ' ' . $p['last_name']),
        "first_name" => $p['first_name'],
        "middle_name" => $p['middle_name'],
        "last_name" => $p['last_name'],
        "suffix" => $p['suffix'],
        "branch" => $p['branch_name'] ?? $p['branch'],
        "brand" => $p['brand'],
        "status" => $p['status'],
        "employment_status" => $p['employment_status'],
        "sub_status" => $p['sub_status'],
        "agency" => $p['agency'],
        "corpo" => $p['corpo'],
        "gender" => $p['gender'],
        "birthday" => $p['birthday'],
        "date_hired" => $p['date_hired'],
        "assignment_date" => $p['assignment_date'],
        "last_assigned_by" => $p['last_assigned_by']
    ];
}

echo json_encode($result);