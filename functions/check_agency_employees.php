<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$pdo = qa_db();

$agency = $_POST['agency'] ?? '';

try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT TOP 5 first_name, last_name
        FROM employee_info
        WHERE LTRIM(RTRIM(agency)) = LTRIM(RTRIM(:agency))
        AND ISNULL(status, '') = 'ACTIVE'
    ");

    $stmt->execute(['agency' => $agency]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        $names = [];
        foreach ($rows as $r) {
            $full = trim($r['first_name'] . ' ' . $r['last_name']);
            if ($full !== '') $names[] = $full;
        }

        echo json_encode([
            "blocked" => true,
            "employees" => $names
        ]);
    } else {
        echo json_encode([
            "blocked" => false
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        "blocked" => true,
        "error" => $e->getMessage()
    ]);
}