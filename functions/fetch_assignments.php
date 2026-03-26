<?php
header('Content-Type: application/json');
include '../config/db.php';
$pdo = qa_db();

// DataTables params
$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 50;

// Filters
$branch = $_POST['branch'] ?: null;
$brand  = $_POST['brand'] ?: null;
$status = $_POST['status'] ?: null;
$from   = $_POST['from_date'] ?: null;
$to     = $_POST['to_date'] ?: null;

// Call SP safely
$stmt = $pdo->prepare("EXEC get_assignments @branch_name=?, @brand_name=?, @from_date=?, @to_date=?");
$stmt->execute([$branch, $brand, $from, $to]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Status filter in PHP
if($status){
    $data = array_filter($data, function($a) use($status){
        $shortage = $a['required_count'] - $a['assigned_count'];
        return ($status==='complete' && $shortage===0)
            || ($status==='lacking' && $shortage>0)
            || ($status==='excess' && $shortage<0);
    });
}

// Total records
$total = count($data);

// Pagination
$pagedData = array_slice($data, $start, $length);

// Format rows for DataTables
$result = [];
foreach($pagedData as $i => $a){
    $shortage = $a['required_count'] - $a['assigned_count'];
    $statusLabel = $shortage > 0
        ? "<span class='badge bg-danger'>Needs $shortage</span>"
        : ($shortage < 0
            ? "<span class='badge bg-warning'>Excess ".abs($shortage)."</span>"
            : "<span class='badge bg-success'>Complete</span>");

    $result[] = [
        "DT_RowAttr" => [
            "class" => "clickable-row",
            "data-branch" => $a['branch_name'],
            "data-brand"  => $a['brand_name'],
            "data-required" => $a['required_count'],
            "data-assigned" => $a['assigned_count'],
            "data-updated"  => $a['updated_at'] ? date('Y-m-d', strtotime($a['updated_at'])) : '-'
        ],
        $a['branch_name'],
        $a['brand_name'],
        $a['required_count'],
        $a['assigned_count'],
        $statusLabel,
        $a['updated_at'] ? date('Y-m-d', strtotime($a['updated_at'])) : '-'
    ];
}

// Return JSON
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => $total,
    "recordsFiltered" => $total,
    "data" => $result
]);
exit;