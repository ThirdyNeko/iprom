<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

$pdo = qa_db();

ini_set('display_errors', 0);
error_reporting(E_ALL);

set_exception_handler(function($e) {
    echo json_encode([
        'status' => 'danger',
        'message' => $e->getMessage()
    ]);
    exit;
});

function isComboAvailable($pdo, $branch, $brand) {
    if (!$branch || !$brand) return false;

    $stmt = $pdo->prepare("
        SELECT required_count, assigned_count
        FROM assignment
        WHERE branch_name = ?
        AND brand_name = ?
    ");
    
    $stmt->execute([$branch, $brand]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) return false;

    return $row['assigned_count'] < $row['required_count'];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request method']);
    exit;
}

// =========================
// POST VALUES
// =========================
$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid employee ID']);
    exit;
}

// =========================
// FETCH CURRENT VALUES (IMPORTANT FIX)
// =========================
$stmt = $pdo->prepare("
    SELECT roving_group_id, multi_brand_group_id
    FROM employee_info
    WHERE id = ?
");
$stmt->execute([$id]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current) {
    echo json_encode(['status' => 'danger', 'message' => 'Employee not found']);
    exit;
}

// =========================
// ALWAYS CONTROLLED
// =========================
$status = 'ACTIVE';

$employment_status = strtoupper(trim($_POST['employment_status'] ?? ''));
$reason_for_update = strtoupper(trim($_POST['reason_update'] ?? ''));

$raw_start_date = $_POST['start_date'] ?? null;
$raw_end_date   = $_POST['end_date'] ?? null;
$agency         = $_POST['agency'] ?? null;

if ($reason_for_update === 'ADD BRANCH/BRAND' && empty($raw_start_date)) {
    echo json_encode([
        'status' => 'danger',
        'message' => 'Start date is required for ADD BRANCH/BRAND'
    ]);
    exit;
}

$skipSlotValidation = in_array($reason_for_update, [
    'REMOVE BRANCH/BRAND',
    'ADD BRANCH/BRAND',
    'CHANGE AGENCY',
    'RESIGNED',
    'PULL-OUT / END OF CONTRACT',
    'BLACKLISTED / AWOL / TERMINATED'
]);

$remarks = trim($_POST['remarks'] ?? '');

$last_updated_by  = $_SESSION['username'] ?? 'System';
$last_assigned_by = $_SESSION['username'] ?? null;

// =========================
// SAFE GROUP INPUT HANDLING (FIXED)
// =========================
$roving_group_id = $current['roving_group_id'];
$multi_brand_group_id = $current['multi_brand_group_id'];

$rovingBranches = $_POST['roving_branches'] ?? [];
$multiBrands    = $_POST['multi_brands'] ?? [];
$branch = $_POST['branch'] ?? null;
$brand  = $_POST['brand'] ?? null;

if (!is_array($rovingBranches)) $rovingBranches = [$rovingBranches];
if (!is_array($multiBrands)) $multiBrands = [$multiBrands];

// =========================
// ROVING BRANCHES (FIXED)
// =========================

$rovingBranches = array_filter($rovingBranches);

$skipGroupValidation = in_array($reason_for_update, [
    'CHANGE SUB STATUS',
    'ADD BRANCH/BRAND'
]);
$hasRovingBranches = !empty($rovingBranches);

// Only validate group ID if:
// - there are branches involved AND
// - it's NOT a fresh CHANGE SUB STATUS flow
if ($hasRovingBranches && !$skipGroupValidation) {

    $roving_group_id = $current['roving_group_id'];

    if (empty($roving_group_id)) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'Invalid branch combination'
        ]);
        exit;
    }
}

// =========================
// MULTI BRANDS (FIXED)
// =========================
$multiBrands = array_filter($multiBrands);

$hasMultiBrands = !empty($multiBrands);

// Only validate group ID if:
// - there are brands involved AND
// - it's NOT a fresh sub status change that may not yet have a group
if ($hasMultiBrands && !$skipGroupValidation) {

    if (empty($multi_brand_group_id)) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'Invalid brand combination'
        ]);
        exit;
    }
}

// =========================
// DATE VALUES (SAFE)
// =========================
$start_date = !empty($raw_start_date) ? $raw_start_date : null;
$end_date   = !empty($raw_end_date) ? $raw_end_date : null;

$date_separated = (!empty($_POST['date_separated'])) ? $_POST['date_separated'] : null;
$date_of_return = (!empty($_POST['date_returned'])) ? $_POST['date_returned'] : null;

$sub_status = $_POST['sub_status'] ?? null;

if (in_array($reason_for_update, [
    'RESIGNED',
    'PULL-OUT / END OF CONTRACT',
    'BLACKLISTED / AWOL / TERMINATED'
])) {
    $start_date = null;
}

// =========================
// VALIDATION
// =========================
$today = strtotime(date('Y-m-d'));
// =========================
// START/END DATE VALIDATION
// =========================
if ($start_date && $end_date) {
    if (strtotime($end_date) < strtotime($start_date)) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'End date cannot be earlier than start date'
        ]);
        exit;
    }
}


// =========================
// EMPLOYMENT RULES
// =========================
$empStatusUpper = $employment_status;

$isReliever = in_array($empStatusUpper, ['RELIEVER', 'SEASONAL']);
$isTransferOrSubStatus = in_array($reason_for_update, ['TRANSFER BRANCH', 'CHANGE SUB STATUS', 'CHANGE EMPLOYMENT STATUS', 'ADD BRANCH/BRAND']);

if ($isReliever) {

    // MUST have both dates
    if ((!$start_date || !$end_date) && !$skipSlotValidation) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'Start and End date are required for Reliever/Seasonal'
        ]);
        exit;
    }

} elseif ($isTransferOrSubStatus) {

    // start date NOT required, end date ignored
    if (!$start_date) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'Start date is required for changing'
        ]);
        exit;
    }
    $end_date = null;

} else {

    // ALL OTHER CASES
    $start_date = null;
    $end_date = null;
}

// =========================
// STATUS LOGIC
// =========================
$inactiveReasons = [
    'RESIGNED',
    'PULL-OUT / END OF CONTRACT',
    'BLACKLISTED / AWOL / TERMINATED',
    'REMOVE BRANCH/BRAND',
];

$isInactiveReason = in_array($reason_for_update, $inactiveReasons);

$dateSeparatedValue = $date_separated ? strtotime($date_separated) : null;
$today = strtotime(date('Y-m-d'));
$hidden = false;

if ($isInactiveReason) {

    $status = (!$dateSeparatedValue || $dateSeparatedValue < $today)
        ? 'INACTIVE'
        : 'ACTIVE';

    $hidden = ($status === 'INACTIVE');

} else if (in_array($reason_for_update, ['MATERNITY LEAVE', 'EMERGENCY LEAVE'])) {

    $status = (!$dateSeparatedValue || $dateSeparatedValue <= $today)
        ? 'INACTIVE'
        : 'ACTIVE';

    $hidden = ($status === 'INACTIVE');

} else if (
    (
        in_array($empStatusUpper, ['RELIEVER', 'SEASONAL']) ||
        in_array($reason_for_update, [
            'CHANGE SUB STATUS',
            'CHANGE EMPLOYMENT STATUS',
            'TRANSFER BRANCH'
        ])
    )
    && $start_date && $end_date
) {

    $start = strtotime($start_date);
    $end   = strtotime($end_date);

    $hidden = ($start > $today); // future start = hidden

} else if (
    in_array(strtoupper(trim($sub_status)), ['MULTI BRANCH', 'MULTI BRAND', 'HYBRID', 'STATIONARY']) &&
    $start_date
) {

    $start = strtotime($start_date);


    $hidden = ($start > $today); // future start = hidden
}

if ($reason_for_update === 'MATERNITY LEAVE' && $date_of_return) {
    if (strtotime($date_of_return) <= $today) {
        $status = 'ACTIVE';
    }
}

if ($reason_for_update === 'EMERGENCY LEAVE' && $date_of_return) {
    if (strtotime($date_of_return) <= $today) {
        $status = 'ACTIVE';
    }
}

// =========================
// ASSIGNMENT SLOT VALIDATION
// =========================

// single transfer/reassign
if (!$skipSlotValidation && in_array($reason_for_update, ['TRANSFER BRANCH', 'CHANGE AGENCY'])) {

    if (!isComboAvailable($pdo, $branch, $brand)) {

        echo json_encode([
            "status" => "error",
            "message" => "Slot is already full for {$branch} - {$brand}."
        ]);
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT sub_status
    FROM employee_info
    WHERE id = ?
");

$stmt->execute([$id]);

$old_sub_status = $stmt->fetchColumn();

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

    // assignment does not exist
    if (!$row) {
        return [
            'valid' => false,
            'message' => "No assignment setup for {$branch} - {$brand}."
        ];
    }

    // slot full
    if ($row['assigned_count'] >= $row['required_count']) {
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
// HYBRID validation
if (
    !$skipSlotValidation &&
    strtoupper(trim($sub_status)) === 'HYBRID' &&
    !empty($rovingBranches) &&
    !empty($multiBrands)
) {

    foreach ($rovingBranches as $branchItem) {
        foreach ($multiBrands as $brandItem) {

            $validation = validateAssignmentSlot(
                $pdo,
                $branchItem,
                $brandItem
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

// MULTI BRANCH validation
if (
    !$skipSlotValidation &&
    strtoupper(trim($sub_status)) === 'MULTI BRANCH' &&
    $old_sub_status !== 'HYBRID' &&
    !empty($rovingBranches)
) {

    foreach ($rovingBranches as $branchItem) {

        if (!isComboAvailable($pdo, $branchItem, $brand)) {

            echo json_encode([
                "status" => "error",
                "message" => "Slot is already full for {$branchItem} - {$brand}."
            ]);
            exit;
        }
    }
}

// MULTI BRAND validation
if (
    !$skipSlotValidation &&
    strtoupper(trim($sub_status)) === 'MULTI BRAND' &&
    $old_sub_status !== 'HYBRID' &&
    !empty($multiBrands)
) {

    foreach ($multiBrands as $brandItem) {

        if (!isComboAvailable($pdo, $branch, $brandItem)) {

            echo json_encode([
                "status" => "error",
                "message" => "Slot is already full for {$branch} - {$brandItem}."
            ]);
            exit;
        }
    }
}



// =========================
// AUTO CREATE GROUP IDS (FIXED - STRING SAFE)
// =========================
$isHybrid = strtoupper(trim($sub_status)) === 'HYBRID';
$isMultiBranch = strtoupper(trim($sub_status)) === 'MULTI BRANCH';
$isMultiBrand  = strtoupper(trim($sub_status)) === 'MULTI BRAND';

if (
    $reason_for_update === 'ADD BRANCH/BRAND' ||
    $reason_for_update === 'CHANGE SUB STATUS'
) {

    // HYBRID = must have both groups
    if ($isHybrid) {

        if (empty($roving_group_id)) {
            $roving_group_id = 'ROV-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));

        }

        if (empty($multi_brand_group_id)) {
            $multi_brand_group_id = 'MBR-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
        }
    }

    // MULTI BRANCH only
    if ($isMultiBranch && empty($roving_group_id)) {
        $roving_group_id = 'ROV-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
    }

    // MULTI BRAND only
    if ($isMultiBrand && empty($multi_brand_group_id)) {
        $multi_brand_group_id = 'MBR-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
    }
}

try {
    $pdo->beginTransaction();

    $hasInserted = false;
    $insertedIds = []; // ✅ MUST be declared before use

    // =========================
    // FETCH BASE EMPLOYEE
    // =========================
    $stmtBase = $pdo->prepare("SELECT * FROM employee_info WHERE id = ?");
    $stmtBase->execute([$id]);
    $base = $stmtBase->fetch(PDO::FETCH_ASSOC);

    if (!$base) {
        throw new Exception("Employee not found");
    }

    $currentBranch = $base['branch'];
    $currentBrand  = $base['brand'];

    // =========================
    // UPDATE ORIGINAL ONLY HERE
    // =========================
    $sendTransferFields = in_array($reason_for_update, ['TRANSFER BRANCH', 'REASSIGNED']);

    $branchParam = $sendTransferFields ? $branch : null;
    $brandParam  = $sendTransferFields ? $brand : null;

    $stmt = $pdo->prepare("
        SELECT branch
        FROM employee_info
        WHERE roving_group_id = ?
    ");
    $stmt->execute([$base['roving_group_id']]);

    $existingBranches = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $newBranches = array_diff($rovingBranches, $existingBranches);

    $stmt = $pdo->prepare("
        SELECT brand
        FROM employee_info
        WHERE multi_brand_group_id = ?
    ");
    $stmt->execute([$base['multi_brand_group_id']]);

    $existingBrands = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $newBrands = array_diff($multiBrands, $existingBrands);
    
    $removedBranches = array_unique(
        array_diff($existingBranches ?? [], [$currentBranch])
    );

    $removedBrands = array_unique(
        array_diff($existingBrands ?? [], [$currentBrand])
    );

    $stmt = $pdo->prepare("
        EXEC update_employee
            @id = :id,
            @status = :status,
            @sub_status = :sub_status,
            @employment_status = :employment_status,
            @reason_for_update = :reason_for_update,
            @start_date = :start_date,
            @end_date = :end_date,
            @date_separated = :date_separated,
            @date_of_return = :date_of_return,
            @remarks = :remarks,
            @last_updated_by = :last_updated_by,
            @roving_group_id = :roving_group_id,
            @multi_brand_group_id = :multi_brand_group_id,
            @roving_branches = :roving_branches,
            @multi_brands = :multi_brands,
            @removedBranch = :removedBranch,
            @removedBrand = :removedBrand,
            @branch = :branch,
            @brand = :brand,
            @agency = :agency
    ");

    $stmt->execute([
        ':id' => $id,
        ':status' => $status,
        ':sub_status' => $sub_status,
        ':employment_status' => $employment_status,
        ':reason_for_update' => $reason_for_update,
        ':start_date' => $start_date,
        ':end_date' => $end_date,
        ':date_separated' => $date_separated,
        ':date_of_return' => $date_of_return,
        ':remarks' => $remarks,
        ':last_updated_by' => $last_updated_by,
        ':roving_group_id' => $roving_group_id,
        ':multi_brand_group_id' => $multi_brand_group_id,
        ':roving_branches' => !empty($newBranches) ? implode(',', $newBranches) : null,
        ':multi_brands'    => !empty($newBrands) ? implode(',', $newBrands) : null,
        ':removedBranch' => !empty($removedBranches)
            ? implode(',', $removedBranches)
            : null,

        ':removedBrand' => !empty($removedBrands)
            ? implode(',', $removedBrands)
            : null,
        ':branch' => $branchParam,
        ':brand' => $brandParam,
        ':agency'=> $agency
    ]);

    $filteredBranches = [];

    foreach ($rovingBranches as $branch) {

        $check = $pdo->prepare("
            SELECT 1
            FROM employee_info
            WHERE first_name = ?
            AND last_name = ?
            AND branch = ?
            AND roving_group_id = ?
            AND status = 'ACTIVE'
        ");

        $check->execute([
            $base['first_name'],
            $base['last_name'],
            $branch,
            $roving_group_id
        ]);

        if (!$check->fetch()) {
            $filteredBranches[] = $branch;
        }
    }

    $filteredBrands = [];
    
    foreach ($multiBrands as $brand) {

        $check = $pdo->prepare("
            SELECT 1
            FROM employee_info
            WHERE first_name = ?
            AND last_name = ?
            AND brand = ?
            AND multi_brand_group_id = ?
            AND status = 'ACTIVE'
        ");

        $check->execute([
            $base['first_name'],
            $base['last_name'],
            $brand,
            $multi_brand_group_id
        ]);

        if (!$check->fetch()) {
            $filteredBrands[] = $brand;
        }
    }

    // =========================
    // ONLY RUN IF ADD BRANCH/BRAND
    // =========================
    $startTimestamp = $start_date ? strtotime($start_date) : null;
    if (
        (
            $reason_for_update === 'ADD BRANCH/BRAND' ||
            ($reason_for_update === 'CHANGE SUB STATUS' && $sub_status !== 'STATIONARY')
        )
    ) {

        if ($startTimestamp <= $today) {
            $insertStatus = 'ACTIVE';
            $hidden = false;
        } else {
            $insertStatus = 'INACTIVE';
            $hidden = true;
        }

        // =========================
        // INSERT TEMPLATE (reuse both loops)
        // =========================
        $stmtInsert = $pdo->prepare("
            INSERT INTO employee_info (
                employee_id,
                first_name,
                last_name,
                middle_name,
                suffix,
                branch,
                brand,
                assignment_date,
                last_assigned_by,
                status,
                created_at,
                updated_at,
                date_of_return,
                date_separated,
                employment_status,
                remarks,
                last_updated_by,
                reason_for_update,
                date_hired,
                start_date,
                end_date,
                roving_group_id,
                sub_status,
                multi_brand_group_id,
                gender,
                birthday,
                hidden,
                agency,
                corpo
            )
            VALUES (
                :employee_id,
                :first_name,
                :last_name,
                :middle_name,
                :suffix,
                :branch,
                :brand,
                :assignment_date,
                :last_assigned_by,
                :status,
                GETDATE(),
                GETDATE(),
                :date_of_return,
                :date_separated,
                :employment_status,
                :remarks,
                :last_updated_by,
                :reason_for_update,
                :date_hired,
                :start_date,
                :end_date,
                :roving_group_id,
                :sub_status,
                :multi_brand_group_id,
                :gender,
                :birthday,
                :hidden,
                :agency,
                :corpo
            )
        ");

        // =========================
        // BRANCH DUPLICATION
        // =========================
        if (!empty($rovingBranches) && ($sub_status === 'MULTI BRANCH' || $sub_status === 'HYBRID') ) {

            $rovingBranches = array_filter($rovingBranches);

            foreach ($rovingBranches as $branch) {

                $check = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM employee_info
                    WHERE first_name = ?
                    AND last_name = ?
                    AND middle_name = ?
                    AND suffix = ?
                    AND branch = ?
                    AND brand = ?
                    AND status = 'ACTIVE'
                    AND roving_group_id = ?
                    AND corpo = ?
                ");

                $check->execute([
                    $base['first_name'],
                    $base['last_name'],
                    $base['middle_name'],
                    $base['suffix'],
                    $branch,
                    $currentBrand,
                    $base['roving_group_id'],
                    $base['corpo']
                ]);

                if ($check->fetchColumn() > 0) {
                    continue;
                }

                $stmtInsert->execute([
                    ':employee_id' => $_POST['employee_id'] ?? $id,
                    ':first_name' => $base['first_name'],
                    ':last_name'  => $base['last_name'],
                    ':middle_name' => $base['middle_name'],
                    ':suffix'      => $base['suffix'],
                    ':branch'     => $branch,
                    ':brand'      => $currentBrand,
                    ':assignment_date' => date('Y-m-d'),
                    ':last_assigned_by' => $last_assigned_by,
                    ':status' => $insertStatus,
                    ':date_of_return' => $date_of_return,
                    ':date_separated' => $date_separated,
                    ':employment_status' => $employment_status,
                    ':remarks' => $remarks,
                    ':last_updated_by' => $last_updated_by,
                    ':reason_for_update' => $reason_for_update,
                    ':date_hired' => $base['date_hired'],
                    ':start_date' => $start_date,
                    ':end_date' => $end_date,
                    ':roving_group_id' => $roving_group_id,
                    ':sub_status' => $sub_status,
                    ':multi_brand_group_id' => $multi_brand_group_id,
                    ':gender'     => $base['gender'],
                    ':birthday'   => $base['birthday'],
                    ':hidden' => $hidden,
                    ':agency' => $agency,
                    ':corpo'  => $base['corpo']
                ]);

                $newId = $pdo->lastInsertId();
                if ($newId) {
                    $insertedIds[] = $newId;
                }

                $hasInserted = true;
            }
        }

        // =========================
        // BRAND DUPLICATION
        // =========================
        if (!empty($multiBrands) && ($sub_status === 'MULTI BRAND' || $sub_status === 'HYBRID') ) {

            $multiBrands = array_filter($multiBrands);

            foreach ($multiBrands as $brand) {

                $check = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM employee_info
                    WHERE first_name = ?
                    AND last_name = ?
                    AND middle_name = ?
                    AND suffix = ?
                    AND branch = ?
                    AND brand = ?
                    AND status = 'ACTIVE'
                    AND multi_brand_group_id = ?
                    AND corpo = ?
                ");

                $check->execute([
                    $base['first_name'],
                    $base['last_name'],
                    $base['middle_name'],
                    $base['suffix'],
                    $currentBranch,
                    $brand,
                    $base['multi_brand_group_id'],
                    $base['corpo']
                ]);

                if ($check->fetchColumn() > 0) {
                    continue;
                }

                $stmtInsert->execute([
                    ':employee_id' => $_POST['employee_id'] ?? $id,
                    ':first_name' => $base['first_name'],
                    ':last_name'  => $base['last_name'],
                    ':middle_name' => $base['middle_name'],
                    ':suffix'      => $base['suffix'],
                    ':branch'     => $currentBranch,
                    ':brand'      => $brand,
                    ':assignment_date' => date('Y-m-d'),
                    ':last_assigned_by' => $last_assigned_by,
                    ':status' => $insertStatus,
                    ':date_of_return' => $date_of_return,
                    ':date_separated' => $date_separated,
                    ':employment_status' => $employment_status,
                    ':remarks' => $remarks,
                    ':last_updated_by' => $last_updated_by,
                    ':reason_for_update' => $reason_for_update,
                    ':date_hired' => $base['date_hired'],
                    ':start_date' => $start_date,
                    ':end_date' => $end_date,
                    ':roving_group_id' => $roving_group_id,
                    ':sub_status' => $sub_status,
                    ':multi_brand_group_id' => $multi_brand_group_id,
                    ':gender'     => $base['gender'],
                    ':birthday'   => $base['birthday'],
                    ':hidden' => $hidden,
                    ':agency' => $agency,
                    ':corpo' => $base['corpo']
                ]);

                $newId = $pdo->lastInsertId();
                if ($newId) {
                    $insertedIds[] = $newId;
                }

                $hasInserted = true;
            }
        }

        // =========================
        // HYBRID DUPLICATION (BRANCH × BRAND)
        // =========================

        if (
            !empty($rovingBranches) &&
            !empty($multiBrands) &&
            strtoupper(trim($sub_status)) === 'HYBRID'
        ) {

            $rovingBranches = array_filter($rovingBranches);
            $multiBrands    = array_filter($multiBrands);

            foreach ($rovingBranches as $branchItem) {
                foreach ($multiBrands as $brandItem) {

                    // Prevent duplicate active record
                    $check = $pdo->prepare("
                        SELECT COUNT(*) 
                        FROM employee_info
                        WHERE first_name = ?
                        AND last_name = ?
                        AND middle_name = ?
                        AND suffix = ?
                        AND branch = ?
                        AND brand = ?
                        AND status = 'ACTIVE'
                        AND roving_group_id = ?
                        AND multi_brand_group_id = ?
                        AND corpo = ?
                    ");

                    $check->execute([
                        $base['first_name'],
                        $base['last_name'],
                        $base['middle_name'],
                        $base['suffix'],
                        $branchItem,
                        $brandItem,
                        $roving_group_id,
                        $multi_brand_group_id,
                        $base['corpo']
                    ]);

                    if ($check->fetchColumn() > 0) {
                        continue;
                    }

                    $stmtInsert->execute([
                        ':employee_id' => $_POST['employee_id'] ?? $id,
                        ':first_name' => $base['first_name'],
                        ':last_name'  => $base['last_name'],
                        ':middle_name' => $base['middle_name'],
                        ':suffix'      => $base['suffix'],
                        ':branch'     => $branchItem,
                        ':brand'      => $brandItem,
                        ':assignment_date' => date('Y-m-d'),
                        ':last_assigned_by' => $last_assigned_by,
                        ':status' => $insertStatus,
                        ':date_of_return' => $date_of_return,
                        ':date_separated' => $date_separated,
                        ':employment_status' => $employment_status,
                        ':remarks' => $remarks,
                        ':last_updated_by' => $last_updated_by,
                        ':reason_for_update' => $reason_for_update,
                        ':date_hired' => $base['date_hired'],
                        ':start_date' => $start_date,
                        ':end_date' => $end_date,
                        ':roving_group_id' => $roving_group_id,
                        ':sub_status' => $sub_status,
                        ':multi_brand_group_id' => $multi_brand_group_id,
                        ':gender' => $base['gender'],
                        ':birthday' => $base['birthday'],
                        ':hidden' => $hidden,
                        ':agency' => $agency,
                        ':corpo' => $base['corpo']
                    ]);

                    $newId = $pdo->lastInsertId();
                    if ($newId) {
                        $insertedIds[] = $newId;
                    }

                    $hasInserted = true;
                }
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Employee record updated successfully!',
    ]);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'status' => 'danger',
        'message' => $e->getMessage()
    ]);
    exit;
}