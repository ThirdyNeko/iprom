<?php
session_start();
require_once '../config/db.php';
$pdo = qa_db();

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode([]);
    exit;
}

// =========================
// GET MAIN EMPLOYEE
// =========================
$stmt = $pdo->prepare("
    SELECT *
    FROM employee_info
    WHERE id = :id
");
$stmt->execute([':id' => $id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$isBranchManager = (isset($_SESSION['role']) && $_SESSION['role'] === 'branch_manager');

$canPrintLOA = $isAdmin && !empty($employee['print_loa']) && $employee['print_loa'] == 1;

if (!$employee) {
    echo json_encode([]);
    exit;
}

// =========================
// NEW: LETTER OF ADVICE RECORD CHECK
// If this employee_id exists in letters_of_advice, the edit page
// locks itself (and update_promodizer.php rejects the save too).
// =========================
$hasLoaRecord = 0;

if (!empty($employee['employee_id'])) {
    $stmt = $pdo->prepare("
        SELECT TOP 1 1
        FROM letters_of_advice
        WHERE employee_id = :eid
    ");
    $stmt->execute([':eid' => $employee['employee_id']]);
    $hasLoaRecord = $stmt->fetchColumn() ? 1 : 0;
}

// =========================
// GET MULTI BRANCH (ROVING)
// =========================
$rovingBranches = [];

if (!empty($employee['roving_group_id'])) {
    $stmt = $pdo->prepare("
        SELECT branch
        FROM employee_info
        WHERE roving_group_id = :gid
          AND id != :id
    ");
    $stmt->execute([
        ':gid' => $employee['roving_group_id'],
        ':id'  => $employee['id']
    ]);

    $rovingBranches = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// =========================
// GET MULTI BRAND
// =========================
$multiBrands = [];

if (!empty($employee['multi_brand_group_id'])) {
    $stmt = $pdo->prepare("
        SELECT brand
        FROM employee_info
        WHERE multi_brand_group_id = :gid
          AND id != :id
    ");
    $stmt->execute([
        ':gid' => $employee['multi_brand_group_id'],
        ':id'  => $employee['id']
    ]);

    $multiBrands = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// =========================
// ATTACH TO RESPONSE
// =========================
$employee['roving_branches'] = $rovingBranches;
$employee['multi_brands'] = $multiBrands;
$employee['employee_id'] = $employee['employee_id'] ?? null;
$employee['loa_code'] = $employee['loa_code'] ?? null;

// NEW: consumed by edit JS (isLoaLocked / lockPageForLoaRecord)
$employee['has_loa_record'] = $hasLoaRecord;

// personal / address fields (explicit fallback for newly added columns)
$employee['marital_status']      = $employee['marital_status'] ?? null;
$employee['contact_number']      = $employee['contact_number'] ?? null;
$employee['biometric_number']    = $employee['biometric_number'] ?? null;
$employee['categories']          = $employee['categories'] ?? null;
$employee['province']            = $employee['province'] ?? null;
$employee['province_name']       = $employee['province_name'] ?? null;
$employee['municipality']        = $employee['municipality'] ?? null;
$employee['municipality_name']   = $employee['municipality_name'] ?? null;
$employee['barangay']            = $employee['barangay'] ?? null;
$employee['barangay_name']       = $employee['barangay_name'] ?? null;
$employee['street']              = $employee['street'] ?? null;

// =========================
// MASK SENSITIVE FIELDS FOR BRANCH MANAGER
// =========================
if ($isBranchManager) {
    $sensitiveFields = [
        'birthday',
        'street',
        'barangay_name',
        'province',
        'municipality',
        'barangay',
        'municipality_name',
        'province_name',
    ];

    foreach ($sensitiveFields as $field) {
        if (!empty($employee[$field])) {
            $employee[$field] = '********';
        }
    }
}

// =========================
// RETURN JSON
// =========================
$employee['can_print_loa'] = $canPrintLOA;
echo json_encode($employee);