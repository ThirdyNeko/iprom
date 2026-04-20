<?php
header('Content-Type: application/json');
include '../config/db.php';
$pdo = qa_db();

// DataTables params
$draw   = $_POST['draw'] ?? 1;
$start  = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 50;

// Filters
$branch = $_POST['branch'] ?: null;
$brand  = $_POST['brand'] ?: null;
$status = $_POST['status'] ?: null;
$from   = $_POST['from_date'] ?: null;
$to     = $_POST['to_date'] ?: null;

// Clean nulls properly
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

// -------------------------
// STATUS FILTER (POST DB)
// -------------------------
if ($status) {
    $rows = array_filter($rows, function ($a) use ($status) {
        $shortage = (int)$a['required_count'] - (int)$a['assigned_count'];

        if ($status === 'zero') {
            return (int)$a['assigned_count'] === 0;
        }
        if ($status === 'complete') {
            return $shortage === 0;
        }
        if ($status === 'lacking') {
            return (int)$a['assigned_count'] > 0 && $shortage > 0;
        }
        return true;
    });
}

// Reindex array after filter
$rows = array_values($rows);

// TOTALS (IMPORTANT FIX)
$recordsFiltered = count($rows);

// PAGINATION AFTER FILTER (correct now)
$paged = array_slice($rows, $start, $length);

// FORMAT OUTPUT
$result = [];

foreach ($paged as $a) {

    $shortage = (int)$a['required_count'] - (int)$a['assigned_count'];

    if ((int)$a['assigned_count'] === 0) {
        $statusLabel = "<span class='badge bg-danger'>INACTIVE</span>";
    } elseif ($shortage > 0) {
        $statusLabel = "<span class='badge bg-warning'>VACANT: $shortage</span>";
    } else {
        $statusLabel = "<span class='badge bg-success'>ACTIVE</span>";
    }

    $result[] = [
        $a['branch_name'],
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
    "recordsTotal" => $recordsFiltered,
    "recordsFiltered" => $recordsFiltered,
    "data" => $result
]);
exit;