<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$pdo = qa_db();

function cleanValue($value, $fallback = null) {
    $value = trim((string)$value);

    if ($value === '' || strtolower($value) === 'null') {
        return $fallback;
    }

    return $value;
}

try {

    // Start transaction for safety
    $pdo->beginTransaction();

    // Call stored procedure
    $stmt = $pdo->query("EXEC ImperialBranchDetails_Complete");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $inserted = 0;
    $updated = 0;

    // prevent duplicates in same result set
    $seen = [];

    // check if exists
    $checkStmt = $pdo->prepare("
        SELECT branch, region, corpo, area
        FROM branches 
        WHERE branch_code = :code
    ");

    // update
    $updateStmt = $pdo->prepare("
        UPDATE branches
        SET branch = :branch,
            region = :region,
            corpo  = :corpo,
            area   = :area
        WHERE branch_code = :code
    ");

    // insert
    $insertStmt = $pdo->prepare("
        INSERT INTO branches (
            branch_code,
            branch,
            region,
            corpo,
            area,
            status
        )
        VALUES (
            :code,
            :branch,
            :region,
            :corpo,
            :area,
            1
        )
    ");

    foreach ($rows as $row) {

        $branchCode = cleanValue($row['BranchCode'] ?? null);

        if (!$branchCode) continue;

        // skip duplicates from SP output
        if (isset($seen[$branchCode])) continue;
        $seen[$branchCode] = true;

        $checkStmt->execute([':code' => $branchCode]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        $data = [
            ':code'   => $branchCode,
            ':branch' => cleanValue($row['Branch'] ?? null),
            ':region' => cleanValue($row['Location'] ?? null),
            ':corpo'  => cleanValue($row['Company'] ?? null, 'NO COMPANY'),
            ':area'   => cleanValue($row['DM'] ?? null)
        ];

        if ($existing) {
            $hasChanges = $existing['branch'] !== $data[':branch']
                    || $existing['region'] !== $data[':region']
                    || $existing['corpo']  !== $data[':corpo']
                    || $existing['area']   !== $data[':area'];

            if ($hasChanges) {
                $updateStmt->execute($data);
                $updated++;
            }
        } else {
            $insertStmt->execute($data);
            $inserted++;
        }

    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Sync completed. Inserted: $inserted | Updated: $updated"
    ]);

} catch (Exception $e) {

    $pdo->rollBack();

    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}