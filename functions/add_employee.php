<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

$assigned_by = $_SESSION['username'] ?? 'System';

// Basic fields
$first_name = $_POST['first_name'] ?? null;
$last_name  = $_POST['last_name'] ?? null;
$branch     = $_POST['branch'] ?: null;
$brand      = $_POST['brand'] ?: null;
$status     = $_POST['status'] ?: null;
$employment_status = $_POST['employment_status'] ?? null;
$remarks    = $_POST['remarks'] ?? null;
$date_hired = $_POST['date_hired'] ?? null;
$start_date = $_POST['start_date'] ?? null;
$end_date   = $_POST['end_date'] ?? null;

// Roving branches array
$roving_branches = $_POST['roving_branches'] ?? [];

// Remove duplicates and exclude main branch if present
$roving_branches = array_unique(array_filter($roving_branches, fn($b) => $b !== $branch));

try {
    // --- Insert main branch ---
    if ($branch) {
        $stmt = $pdo->prepare("
            EXEC add_employee
                @first_name = :first_name,
                @last_name = :last_name,
                @branch = :branch,
                @brand = :brand,
                @status = :status,
                @assigned_by = :assigned_by,
                @employment_status = :employment_status,
                @remarks = :remarks,
                @date_hired = :date_hired,
                @start_date = :start_date,
                @end_date = :end_date
        ");
        $stmt->execute([
            ':first_name' => $first_name,
            ':last_name'  => $last_name,
            ':branch'     => $branch,
            ':brand'      => $brand,
            ':status'     => $status,
            ':assigned_by'=> $assigned_by,
            ':employment_status' => $employment_status,
            ':remarks'    => $remarks,
            ':date_hired' => $date_hired,
            ':start_date' => $start_date,
            ':end_date'   => $end_date
        ]);
    }

    // --- Insert roving branches ---
    foreach ($roving_branches as $rBranch) {
        $stmt = $pdo->prepare("
            EXEC add_employee
                @first_name = :first_name,
                @last_name = :last_name,
                @branch = :branch,
                @brand = :brand,
                @status = :status,
                @assigned_by = :assigned_by,
                @employment_status = :employment_status,
                @remarks = :remarks,
                @date_hired = :date_hired,
                @start_date = :start_date,
                @end_date = :end_date
        ");
        $stmt->execute([
            ':first_name' => $first_name,
            ':last_name'  => $last_name,
            ':branch'     => $rBranch,
            ':brand'      => $brand,
            ':status'     => $status,
            ':assigned_by'=> $assigned_by,
            ':employment_status' => $employment_status,
            ':remarks'    => $remarks,
            ':date_hired' => $date_hired,
            ':start_date' => $start_date,
            ':end_date'   => $end_date
        ]);
    }

    echo json_encode(['status' => 'success', 'message' => 'Employee added successfully!']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}