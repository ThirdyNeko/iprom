<?php
header('Content-Type: application/json');
include '../config/db.php';
$pdo = qa_db();

// DataTables params (SAFE)
$draw   = $_POST['draw'] ?? 1;
$start  = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 50;

// Filters (SAFE FIX - avoid undefined index warnings)
$branch = $_POST['branch'] ?? null;
$brand  = $_POST['brand'] ?? null;
$status = $_POST['status'] ?? null;
$from   = $_POST['from_date'] ?? null;
$to     = $_POST['to_date'] ?? null;

// Clean nulls
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

// TOTAL BEFORE FILTER (IMPORTANT FIX)
$recordsTotal = count($rows);

// -------------------------
// STATUS FILTER
// -------------------------

if ($status) {
    $rows = array_filter($rows, function ($a) use ($status) {
        $shortage = (int)$a['required_count'] - (int)$a['assigned_count'];

        if ($status === 'zero') {
            return (int)$a['assigned_count'] === 0 && (int)$a['required_count'] > 0;
        }
        if ($status === 'complete') {
            return $shortage === 0 && (int)$a['required_count'] > 0;
        }
        if ($status === 'lacking') {
            return (int)$a['assigned_count'] > 0 && $shortage > 0 ;
        }
        return true;
    });
}

// Reindex
$rows = array_values($rows);

// FILTERED COUNT (IMPORTANT FIX)
$recordsFiltered = count($rows);

// PAGINATION
$paged = array_slice($rows, $start, $length);

// FORMAT OUTPUT (UNCHANGED STRUCTURE — SAFE)
$result = [];

foreach ($paged as $a) {

    $shortage = (int)$a['required_count'] - (int)$a['assigned_count'];
    if ((int)$a['required_count'] === 0) {
        $statusLabel = "<span class='badge bg-secondary'>INACTIVE</span>";
    }elseif ((int)$a['assigned_count'] === 0) {
        $statusLabel = "<span class='badge bg-danger'>VACANT</span>";
    } elseif ($shortage > 0) {
        $statusLabel = "<span class='badge bg-orange'>PARTIAL: $shortage</span>";
    } else {
        $statusLabel = "<span class='badge bg-success'>COMPLETE</span>";
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

// RESPONSE (FIXED TOTALS ONLY)
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => $recordsTotal,
    "recordsFiltered" => $recordsFiltered,
    "data" => $result
]);

exit;