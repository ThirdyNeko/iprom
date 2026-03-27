<?php
header('Content-Type: application/json');
include '../config/db.php';
$pdo = qa_db();

$data = json_decode(file_get_contents("php://input"), true);

$branch = $data['branch'] ?? null;
$brand  = $data['brand'] ?? null;

$stmt = $pdo->prepare("EXEC get_promodizers 
    @branch = :branch,
    @brand = :brand,
    @status = :status,
    @assigned_by = :assigned_by,
    @from_date = :from_date,
    @to_date = :to_date
");

$stmt->execute([
    ':branch' => $branch ?: null,
    ':brand'  => $brand ?: null,
    ':status' => 'Active', // only assigned ones
    ':assigned_by' => null,
    ':from_date' => null,
    ':to_date' => null
]);

$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "status" => "success",
    "data" => $employees
]);