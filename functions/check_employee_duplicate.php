<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

$pdo = qa_db();

// read JSON input
$input = json_decode(file_get_contents('php://input'), true);

$first_name     = strtoupper(trim($input['first_name'] ?? ''));
$last_name      = strtoupper(trim($input['last_name'] ?? ''));
$middle_name    = strtoupper(trim($input['middle_name'] ?? ''));
$birthday       = $input['birthday'] ?? '';
$gender         = strtoupper(trim($input['gender'] ?? ''));
$marital_status = strtoupper(trim($input['marital_status'] ?? ''));

if (!$first_name || !$last_name || !$birthday) {
    echo json_encode([
        'exists' => false,
        'error' => 'Missing required fields'
    ]);
    exit;
}

try {

    // =========================
    // 1) Exact match - blacklisted (checked FIRST — a blacklist hit
    // always takes priority over any employee_info duplicate/status)
    // =========================
    $sqlBl = "
        SELECT TOP 1
            id,
            employee_id,
            first_name,
            last_name,
            middle_name,
            birthday,
            employment_status,
            remarks
        FROM dbo.blacklisted
        WHERE
            UPPER(first_name) = :first_name
            AND UPPER(last_name) = :last_name
            AND ISNULL(UPPER(LTRIM(RTRIM(middle_name))), '') = :middle_name
            AND birthday = :birthday
    ";

    $stmtBl = $pdo->prepare($sqlBl);
    $stmtBl->execute([
        ':first_name'  => $first_name,
        ':last_name'   => $last_name,
        ':middle_name' => $middle_name,
        ':birthday'    => $birthday
    ]);

    $blRow = $stmtBl->fetch(PDO::FETCH_ASSOC);

    if ($blRow) {
        echo json_encode([
            'exists'            => true,
            'source'            => 'blacklisted',
            'id'                => $blRow['id'],
            'employee_id'       => $blRow['employee_id'],
            'employment_status' => $blRow['employment_status'],
            'remarks'           => $blRow['remarks']
        ]);
        exit;
    }

    // =========================
    // 2) Exact match - employee_info
    // =========================
    $sql = "
        SELECT TOP 1
            id,
            employee_id,
            first_name,
            last_name,
            middle_name,
            birthday,
            status,
            reason_for_update
        FROM employee_info
        WHERE 
            UPPER(first_name) = :first_name
            AND UPPER(last_name) = :last_name
            AND ISNULL(UPPER(LTRIM(RTRIM(middle_name))), '') = :middle_name
            AND birthday = :birthday
            AND reason_for_update != 'Clerical Error'
        ORDER BY id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':first_name'  => $first_name,
        ':last_name'   => $last_name,
        ':middle_name' => $middle_name,
        ':birthday'    => $birthday
    ]);

    $empRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($empRow) {
        echo json_encode([
            'exists'            => true,
            'source'            => 'employee_info',
            'id'                => $empRow['id'],
            'employee_id'       => $empRow['employee_id'],
            'status'            => $empRow['status'],
            'reason_for_update' => $empRow['reason_for_update']
        ]);
        exit;
    }

    // =========================
    // 3) Maiden-name cross match (MARRIED + FEMALE only)
    // Assumption: after marriage, a woman's maiden surname commonly
    // becomes her middle name, and her husband's surname becomes her
    // last name. We check both directions since we don't know which
    // record (the existing one, or the one being typed now) reflects
    // the married name vs the maiden name:
    //   A) existing.last_name   = new.middle_name  (existing record is
    //      the OLDER one, still under maiden name)
    //   B) existing.middle_name = new.last_name     (existing record
    //      is the NEWER one, already updated to married name, and the
    //      new entry being typed still uses the maiden surname)
    // Both checks require first_name + birthday to match.
    // =========================
    if ($marital_status === 'MARRIED' && $gender === 'FEMALE' && $middle_name !== '') {

        // blacklisted cross match (both directions) — checked first,
        // same priority ordering as the exact-match section above
        $sqlCrossBl = "
            SELECT TOP 1
                id,
                employee_id,
                first_name,
                last_name,
                middle_name,
                birthday,
                employment_status,
                remarks
            FROM dbo.blacklisted
            WHERE
                UPPER(first_name) = :first_name
                AND birthday = :birthday
                AND (
                    UPPER(last_name) = :middle_name
                    OR UPPER(ISNULL(LTRIM(RTRIM(middle_name)), '')) = :last_name
                )
        ";
        $stmtCrossBl = $pdo->prepare($sqlCrossBl);
        $stmtCrossBl->execute([
            ':first_name'  => $first_name,
            ':middle_name' => $middle_name,
            ':last_name'   => $last_name,
            ':birthday'    => $birthday
        ]);
        $crossBlRow = $stmtCrossBl->fetch(PDO::FETCH_ASSOC);

        if ($crossBlRow) {
            echo json_encode([
                'exists'              => false,
                'possible_match'      => true,
                'needs_confirmation'  => true,
                'source'              => 'blacklisted',
                'id'                  => $crossBlRow['id'],
                'employee_id'         => $crossBlRow['employee_id'],
                'employment_status'   => $crossBlRow['employment_status'],
                'remarks'             => $crossBlRow['remarks'],
                'matched_first_name'  => $crossBlRow['first_name'],
                'matched_last_name'   => $crossBlRow['last_name'],
                'matched_middle_name' => $crossBlRow['middle_name'],
                'note' => 'Possible blacklist match under maiden name: existing and entered last/middle names cross-match.'
            ]);
            exit;
        }

        // employee_info cross match (both directions)
        $sqlCross = "
            SELECT TOP 1
                id,
                employee_id,
                first_name,
                last_name,
                middle_name,
                birthday,
                status,
                reason_for_update
            FROM employee_info
            WHERE
                UPPER(first_name) = :first_name
                AND birthday = :birthday
                AND reason_for_update != 'Clerical Error'
                AND (
                    UPPER(last_name) = :middle_name
                    OR UPPER(ISNULL(LTRIM(RTRIM(middle_name)), '')) = :last_name
                )
            ORDER BY id DESC
        ";
        $stmtCross = $pdo->prepare($sqlCross);
        $stmtCross->execute([
            ':first_name'  => $first_name,
            ':middle_name' => $middle_name,
            ':last_name'   => $last_name,
            ':birthday'    => $birthday
        ]);
        $crossEmpRow = $stmtCross->fetch(PDO::FETCH_ASSOC);

        if ($crossEmpRow) {
            echo json_encode([
                'exists'              => false,
                'possible_match'      => true,
                'needs_confirmation'  => true,
                'source'              => 'employee_info',
                'id'                  => $crossEmpRow['id'],
                'employee_id'         => $crossEmpRow['employee_id'],
                'status'              => $crossEmpRow['status'],
                'reason_for_update'   => $crossEmpRow['reason_for_update'],
                'matched_first_name'  => $crossEmpRow['first_name'],
                'matched_last_name'   => $crossEmpRow['last_name'],
                'matched_middle_name' => $crossEmpRow['middle_name'],
                'note' => 'Possible match under maiden name: existing and entered last/middle names cross-match.'
            ]);
            exit;
        }
    }

    // No match at all
    echo json_encode(['exists' => false]);

} catch (Exception $e) {
    echo json_encode([
        'exists' => false,
        'error' => $e->getMessage()
    ]);
}