<?php
header('Content-Type: application/json');
include '../config/db.php';
$pdo = qa_db();

$branchMap = [];

$stmt = $pdo->query("
    SELECT branch_code, branch
    FROM IPROM.dbo.branches
    WHERE status = 1
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branchMap[$row['branch_code']] = $row['branch'];
}

// DataTables params
$draw   = $_POST['draw'] ?? 1;
$start  = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 50;

// Filters
$branch = $_POST['branch'] ?? null;
$brand  = $_POST['brand'] ?? null;
$status = $_POST['status'] ?? null;
$from   = $_POST['from_date'] ?? null;
$to     = $_POST['to_date'] ?? null;

function clean($v) {
    return ($v === "" || $v === null) ? null : $v;
}

$branch = clean($branch);
$brand  = clean($brand);
$from   = clean($from);
$to     = clean($to);

// CALL STORED PROCEDURE
$stmt = $pdo->prepare("
    EXEC get_assignments 
        @branch_name = ?, 
        @brand_name = ?, 
        @from_date = ?, 
        @to_date = ?
");

$stmt->execute([$branch, $brand, $from, $to]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// TOTAL
$recordsTotal = count($rows);

// STATUS FILTER
if ($status) {
    $rows = array_filter($rows, function ($a) use ($status) {

        $required = (int)$a['required_count'];
        $assigned = (int)$a['assigned_count'];
        $shortage = $required - $assigned;

        if ($status === 'zero') {
            return $assigned === 0 && $required > 0;
        }

        if ($status === 'complete') {
            return $shortage === 0 && $required > 0;
        }

        if ($status === 'lacking') {
            return $assigned > 0 && $shortage > 0;
        }

        return true;
    });
}

$rows = array_values($rows);
$recordsFiltered = count($rows);

// ======================================================
// ✅ ORDER: BRAND FIRST, THEN DATE
// ======================================================
$orderDir = strtolower($_POST['order'][0]['dir'] ?? 'asc');

usort($rows, function ($a, $b) use ($orderDir) {

    $brandA = $a['brand_name'] ?? '';
    $brandB = $b['brand_name'] ?? '';

    $dateA = strtotime($a['updated_at'] ?? '1970-01-01');
    $dateB = strtotime($b['updated_at'] ?? '1970-01-01');

    // PRIMARY SORT: brand_name
    if ($brandA !== $brandB) {
        $cmp = strcmp($brandA, $brandB);
        return $orderDir === 'asc' ? $cmp : -$cmp;
    }

    // SECONDARY SORT: updated_at
    if ($dateA === $dateB) return 0;

    $cmp = $dateA <=> $dateB;

    return $orderDir === 'desc' ? $cmp : -$cmp;
});

// PAGINATION
$paged = array_slice($rows, $start, $length);

// FORMAT OUTPUT
$result = [];

foreach ($paged as $a) {

    $required = (int)$a['required_count'];
    $assigned = (int)$a['assigned_count'];
    $shortage = $required - $assigned;

    if ($required === 0) {
        $statusLabel = "<span class='badge bg-secondary'>INACTIVE</span>";
    } elseif ($assigned === 0) {
        $statusLabel = "<span class='badge bg-danger'>VACANT</span>";
    } elseif ($shortage > 0) {
        $statusLabel = "<span class='badge bg-orange'>PARTIAL: $shortage</span>";
    } else {
        $statusLabel = "<span class='badge bg-success'>COMPLETE</span>";
    }

    $result[] = [
        $branchMap[$a['branch_name']] ?? $a['branch_name'], // 🔥 FIXED
        $a['brand_name'],
        '<span class="required-cell">'.$a['required_count'].'</span>',
        $a['assigned_count'],
        $statusLabel,
        $a['updated_at'] ? date('m/d/Y', strtotime($a['updated_at'])) : '-',
        $a['updated_by'] ?? '-'
    ];
}

// RESPONSE
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => $recordsTotal,
    "recordsFiltered" => $recordsFiltered,
    "data" => $result
]);

exit;