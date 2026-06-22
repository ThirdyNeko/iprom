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
$agency     = $_POST['agency'] ?: null;
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

$stmt = $pdo->prepare("
    SELECT TOP 1 corpo
    FROM branches
    WHERE branch_code = :branch
    ORDER BY corpo
");

$stmt->execute([
    ':branch' => $branch
]);

$corpo = $stmt->fetchColumn();

// ✅ Only generate NEW ID if NOT reassigning
if ($reassign !== '1') {
    $employee_id = 'EMP-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
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

if ($sub_status === 'MULTI BRANCH' || $sub_status === 'HYBRID') { // ✅ FIXED
    $roving_group_id = 'ROV-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
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

function validateAssignmentSlot($pdo, $branch, $brand) {

    if (!$branch || !$brand) {
        return [
            'valid' => false,
            'message' => 'Invalid branch/brand combination.'
        ];
    }

    $stmt = $pdo->prepare("
        SELECT required_count, assigned_count
        FROM assignment
        WHERE branch_name = ?
        AND brand_name = ?
    ");

    $stmt->execute([$branch, $brand]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // assignment setup missing
    if (!$row) {
        return [
            'valid' => false,
            'message' => "No assignment setup found for {$branch} - {$brand}."
        ];
    }

    // slot full
    if ((int)$row['assigned_count'] > (int)$row['required_count']) {
        return [
            'valid' => false,
            'message' => "Slot is already full for {$branch} - {$brand}."
        ];
    }

    return [
        'valid' => true,
        'message' => null
    ];
}

if ($sub_status === 'MULTI BRAND' || $sub_status === 'HYBRID') {
    $multi_brand_group_id = 'MBR-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
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

// =========================
// ASSIGNMENT VALIDATION
// =========================

// MAIN RECORD
if ($branch && $brand) {

    $validation = validateAssignmentSlot(
        $pdo,
        $branch,
        $brand
    );

    if (!$validation['valid']) {

        echo json_encode([
            'status' => 'error',
            'message' => $validation['message']
        ]);

        exit;
    }
}

// ROVING BRANCHES
foreach ($roving_branches as $rBranch) {

    $validation = validateAssignmentSlot(
        $pdo,
        $rBranch,
        $brand
    );

    if (!$validation['valid']) {

        echo json_encode([
            'status' => 'error',
            'message' => $validation['message']
        ]);

        exit;
    }
}

// MULTI BRANDS
foreach ($multi_brands as $mBrand) {

    $validation = validateAssignmentSlot(
        $pdo,
        $branch,
        $mBrand
    );

    if (!$validation['valid']) {

        echo json_encode([
            'status' => 'error',
            'message' => $validation['message']
        ]);

        exit;
    }
}

// HYBRID COMBINATIONS
if (
    strtoupper(trim($sub_status)) === 'HYBRID' &&
    !empty($roving_branches) &&
    !empty($multi_brands)
) {

    foreach ($roving_branches as $rBranch) {
        foreach ($multi_brands as $mBrand) {

            $validation = validateAssignmentSlot(
                $pdo,
                $rBranch,
                $mBrand
            );

            if (!$validation['valid']) {

                echo json_encode([
                    'status' => 'error',
                    'message' => $validation['message']
                ]);

                exit;
            }
        }
    }
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
                @corpo = :corpo,
                @brand = :brand,
                @agency = :agency,
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
                @employee_id = :employee_id,
                @roving_branches = :roving_branches,
                @multi_brands = :multi_brands
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':first_name'           => $first_name,
            ':last_name'            => $last_name,
            ':middle_name'          => $middle_name,
            ':suffix'               => $suffix,
            ':branch'               => $branch,
            ':corpo'                => $corpo,
            ':brand'                => $brand,
            ':agency'               => $agency,
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
            ':employee_id'          => $employee_id,
            ':roving_branches'      => !empty($roving_branches) ? implode(',', $roving_branches) : null,
            ':multi_brands'         => !empty($multi_brands) ? implode(',', $multi_brands) : null
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
                @corpo = :corpo,
                @brand = :brand,
                @agency = :agency,
                @status = :status,
                @assigned_by = :assigned_by,
                @employment_status = :employment_status,
                @sub_status = :sub_status,
                @multi_brand_group_id = :multi_brand_group_id,
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
            ':corpo'             => $corpo,
            ':brand'             => $brand,
            ':agency'            => $agency,
            ':status'            => $status,
            ':assigned_by'       => $assigned_by,
            ':employment_status' => $employment_status,
            ':sub_status'        => $sub_status,
            ':multi_brand_group_id'  => $multi_brand_group_id,
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
                @corpo = :corpo,
                @brand = :brand,
                @agency = :agency,
                @status = :status,
                @assigned_by = :assigned_by,
                @employment_status = :employment_status,
                @sub_status = :sub_status,
                @multi_brand_group_id = :multi_brand_group_id,
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
            ':first_name'            => $first_name,
            ':last_name'             => $last_name,
            ':middle_name'           => $middle_name,
            ':suffix'                => $suffix,
            ':branch'                => $branch,
            ':corpo'                 => $corpo,
            ':brand'                 => $mBrand,
            ':agency'                => $agency,
            ':status'                => $status,
            ':assigned_by'           => $assigned_by,
            ':employment_status'     => $employment_status,
            ':sub_status'            => $sub_status,
            ':multi_brand_group_id'  => $multi_brand_group_id,
            ':roving_group_id'       => $roving_group_id,
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
    
    // =========================
    // HYBRID INSERTS
    // (ROVING BRANCH × MULTI BRAND)
    // =========================
    $isHybrid = strtoupper(trim($sub_status)) === 'HYBRID';

    if (
        $isHybrid &&
        !empty($roving_branches) &&
        !empty($multi_brands)
    ) {

        $roving_branches = array_unique(array_filter($roving_branches));
        $multi_brands    = array_unique(array_filter($multi_brands));

        foreach ($roving_branches as $rBranch) {
            foreach ($multi_brands as $mBrand) {

                // skip if same as main record
                if (
                    strtoupper(trim($rBranch)) === strtoupper(trim($branch)) &&
                    strtoupper(trim($mBrand)) === strtoupper(trim($brand))
                ) {
                    continue;
                }

                $stmt = $pdo->prepare("
                    EXEC add_employee
                        @first_name = :first_name,
                        @last_name = :last_name,
                        @middle_name = :middle_name,
                        @suffix = :suffix,
                        @branch = :branch,
                        @corpo = :corpo,
                        @brand = :brand,
                        @agency = :agency,
                        @status = :status,
                        @assigned_by = :assigned_by,
                        @employment_status = :employment_status,
                        @sub_status = :sub_status,
                        @multi_brand_group_id = :multi_brand_group_id,
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
                    ':first_name'            => $first_name,
                    ':last_name'             => $last_name,
                    ':middle_name'           => $middle_name,
                    ':suffix'                => $suffix,
                    ':branch'                => $rBranch,
                    ':corpo'                 => $corpo,
                    ':brand'                 => $mBrand,
                    ':agency'                => $agency,
                    ':status'                => $status,
                    ':assigned_by'           => $assigned_by,
                    ':employment_status'     => $employment_status,
                    ':sub_status'            => $sub_status,
                    ':multi_brand_group_id'  => $multi_brand_group_id,
                    ':roving_group_id'       => $roving_group_id,
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
        }
    }

    echo json_encode([
        'status' => 'success',
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}