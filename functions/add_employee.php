<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

$pdo = qa_db();

$assigned_by = $_SESSION['username'] ?? 'System';

// =========================
// BASIC FIELDS
// =========================
$first_name = $_POST['first_name'] ?? null;
$last_name  = $_POST['last_name'] ?? null;
$branch     = $_POST['branch'] ?: null;
$brand      = $_POST['brand'] ?: null;
$status     = $_POST['status'] ?: null;
$employment_status = $_POST['employment_status'] ?? null;
$sub_status = $_POST['sub_status'] ?? null; // ✅ NEW
$remarks    = $_POST['remarks'] ?? null;
$date_hired = $_POST['date_hired'] ?? null;
$start_date = $_POST['start_date'] ?? null;
$end_date   = $_POST['end_date'] ?? null;
$gender   = $_POST['gender'] ?? null;
$birthday = $_POST['birthday'] ?? null;
$middle_name = $_POST['middle_name'] ?? null;
$suffix      = $_POST['suffix'] ?? null;
$reassign = $_POST['reassign'] ?? null;
$employee_id = $_POST['employee_id'] ?? null;

// ✅ Only generate NEW ID if NOT reassigning
if ($reassign !== '1') {
    $employee_id = 'EMP-' . date('YmdHis') . '-' . rand(100, 999);
}
// =========================
// ROVING BRANCHES
// =========================
$roving_branches = $_POST['roving_branches'] ?? [];
$roving_branches = array_unique(array_filter($roving_branches, fn($b) => $b !== $branch));

// =========================
// ROVING GROUP ID
// =========================
$roving_group_id = null;

if ($sub_status === 'MULTI BRANCH') { // ✅ FIXED
    $roving_group_id = 'ROV-' . date('YmdHis') . '-' . rand(100, 999);
}

// =========================
// MULTI BRANDS
// =========================
$multi_brands = $_POST['multi_brands'] ?? [];
$multi_brands = array_unique(array_filter($multi_brands, fn($b) => $b !== $brand));

// =========================
// MULTI BRAND GROUP ID
// =========================
$multi_brand_group_id = null;

if ($sub_status === 'MULTI BRAND') {
    $multi_brand_group_id = 'MBR-' . date('YmdHis') . '-' . rand(100, 999);
}

if ($date_hired && $date_hired > date('Y-m-d')) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Date hired cannot be in the future.'
    ]);
    exit;
}

if ($birthday && $birthday > date('Y-m-d')) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Birthday cannot be in the future.'
    ]);
    exit;
}

try {

    // =========================
    // MAIN BRANCH INSERT
    // =========================
    if ($branch) {

        // ✅ decide which procedure to use
        $procedure = ($reassign === '1') ? 'reassign_employee' : 'add_employee';

        $sql = "
            EXEC {$procedure}
                @first_name = :first_name,
                @last_name = :last_name,
                @middle_name = :middle_name,
                @suffix = :suffix,
                @branch = :branch,
                @brand = :brand,
                @status = :status,
                @assigned_by = :assigned_by,
                @employment_status = :employment_status,
                @sub_status = :sub_status,
                @roving_group_id = :roving_group_id,
                @multi_brand_group_id = :multi_brand_group_id,
                @remarks = :remarks,
                @date_hired = :date_hired,
                @start_date = :start_date,
                @end_date = :end_date,
                @gender = :gender,
                @birthday = :birthday,
                @employee_id = :employee_id
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':first_name'           => $first_name,
            ':last_name'            => $last_name,
            ':middle_name'          => $middle_name,
            ':suffix'               => $suffix,
            ':branch'               => $branch,
            ':brand'                => $brand,
            ':status'               => $status,
            ':assigned_by'          => $assigned_by,
            ':employment_status'    => $employment_status,
            ':sub_status'           => $sub_status,
            ':roving_group_id'      => $roving_group_id,
            ':multi_brand_group_id' => $multi_brand_group_id,
            ':remarks'              => $remarks,
            ':date_hired'           => $date_hired,
            ':start_date'           => $start_date,
            ':end_date'             => $end_date,
            ':gender'               => $gender,
            ':birthday'             => $birthday,
            ':employee_id'          => $employee_id
        ]);
    }

    // =========================
    // ROVING BRANCH INSERTS
    // =========================

    $today = date('Y-m-d');
    $hidden = false;
    if (!empty($start_date)) {
        $hidden = strtotime($start_date) > strtotime($today);
    }

    foreach ($roving_branches as $rBranch) {

        $stmt = $pdo->prepare("
            EXEC add_employee
                @first_name = :first_name,
                @last_name = :last_name,
                @middle_name = :middle_name,
                @suffix = :suffix,
                @branch = :branch,
                @brand = :brand,
                @status = :status,
                @assigned_by = :assigned_by,
                @employment_status = :employment_status,
                @sub_status = :sub_status,
                @roving_group_id = :roving_group_id,
                @remarks = :remarks,
                @date_hired = :date_hired,
                @start_date = :start_date,
                @end_date = :end_date,
                @gender = :gender,
                @birthday = :birthday,
                @employee_id = :employee_id,
                @hidden = :hidden
        ");

        $stmt->execute([
            ':first_name'        => $first_name,
            ':last_name'         => $last_name,
            ':middle_name'       => $middle_name,
            ':suffix'            => $suffix,
            ':branch'            => $rBranch,
            ':brand'             => $brand,
            ':status'            => $status,
            ':assigned_by'       => $assigned_by,
            ':employment_status' => $employment_status,
            ':sub_status'        => $sub_status,
            ':roving_group_id'   => $roving_group_id,
            ':remarks'           => $remarks,
            ':date_hired'        => $date_hired,
            ':start_date'        => $start_date,
            ':end_date'          => $end_date,
            ':gender'            => $gender,
            ':birthday'          => $birthday,
            ':employee_id'       => $employee_id,
            ':hidden'            => $hidden
        ]);
    }

    // =========================
    // MULTI BRAND INSERTS
    // =========================
    foreach ($multi_brands as $mBrand) {

        $stmt = $pdo->prepare("
            EXEC add_employee
                @first_name = :first_name,
                @last_name = :last_name,
                @middle_name = :middle_name,
                @suffix = :suffix,
                @branch = :branch,
                @brand = :brand,
                @status = :status,
                @assigned_by = :assigned_by,
                @employment_status = :employment_status,
                @sub_status = :sub_status,
                @multi_brand_group_id = :multi_brand_group_id,
                @remarks = :remarks,
                @date_hired = :date_hired,
                @start_date = :start_date,
                @end_date = :end_date,
                @gender = :gender,
                @birthday = :birthday,
                @employee_id = :employee_id,
                @hidden = :hidden
        ");

        $stmt->execute([
            ':first_name'            => $first_name,
            ':last_name'             => $last_name,
            ':middle_name'           => $middle_name,
            ':suffix'                => $suffix,
            ':branch'                => $branch,
            ':brand'                 => $mBrand,
            ':status'                => $status,
            ':assigned_by'           => $assigned_by,
            ':employment_status'     => $employment_status,
            ':sub_status'            => $sub_status,
            ':multi_brand_group_id'  => $multi_brand_group_id,
            ':remarks'               => $remarks,
            ':date_hired'            => $date_hired,
            ':start_date'            => $start_date,
            ':end_date'              => $end_date,
            ':gender'                => $gender,
            ':birthday'              => $birthday,
            ':employee_id'           => $employee_id,
            ':hidden'                => $hidden
        ]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Employee added successfully!',
        'roving_group_id' => $roving_group_id,
        'multi_brand_group_id' => $multi_brand_group_id
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}