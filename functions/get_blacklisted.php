<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

function nullIfEmpty($val) {
    $val = trim($val ?? '');
    return $val === '' ? null : $val;
}

$pdo = qa_db();

$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
$length = $length > 0 ? $length : 10;

$searchValue = nullIfEmpty($_POST['search']['value'] ?? '');

// DataTable column index -> actual sortable column name
$columns = ['full_name', 'branch', 'brand', 'employment_status'];

$orderColIndex = (int)($_POST['order'][0]['column'] ?? 0);
$orderDir      = strtoupper($_POST['order'][0]['dir'] ?? 'ASC');
$orderDir      = in_array($orderDir, ['ASC', 'DESC'], true) ? $orderDir : 'ASC';
$sortColumn    = $columns[$orderColIndex] ?? 'full_name';

try {
    $stmt = $pdo->prepare("
        EXEC dbo.get_blacklisted
            @Search     = :search,
            @SortColumn = :sortColumn,
            @SortDir    = :sortDir,
            @Skip       = :skip,
            @Take       = :take
    ");

    $stmt->bindValue(':search', $searchValue, PDO::PARAM_STR);
    $stmt->bindValue(':sortColumn', $sortColumn, PDO::PARAM_STR);
    $stmt->bindValue(':sortDir', $orderDir, PDO::PARAM_STR);
    $stmt->bindValue(':skip', $start, PDO::PARAM_INT);
    $stmt->bindValue(':take', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalRecords = $rows[0]['TotalCount'] ?? 0;

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            'id'                 => $r['id'],
            'full_name'          => htmlspecialchars($r['full_name']),
            'branch'             => htmlspecialchars($r['branch'] ?? ''),
            'brand'              => htmlspecialchars($r['brand'] ?? ''),
            'employment_status'  => htmlspecialchars($r['employment_status'] ?? ''),
        ];
    }

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => (int)$totalRecords,
        'recordsFiltered' => (int)$totalRecords,
        'data'            => $data,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Database error',
        'message' => $e->getMessage(),
    ]);
}