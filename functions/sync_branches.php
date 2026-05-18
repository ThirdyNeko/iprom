<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$pdo = qa_db();

try {

    // Call stored procedure
    $stmt = $pdo->query("EXEC ImperialBranchDetails_Complete");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $inserted = 0;
    $updated = 0;

    // 🔥 prevent duplicate processing in SAME result set
    $seen = [];

    // prepared statements (reuse = faster)
    $checkStmt = $pdo->prepare("
        SELECT TOP 1 1 
        FROM branches 
        WHERE branch_code = :code
    ");

    $updateStmt = $pdo->prepare("
        UPDATE branches
        SET branch = :branch,
            region = :region,
            corpo  = :corpo,
            area   = :area
        WHERE branch_code = :code
    ");

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

        $branchCode = $row['BranchCode'] ?? null;
        if (!$branchCode) continue;

        // 🔥 skip duplicates from SP output
        if (isset($seen[$branchCode])) continue;
        $seen[$branchCode] = true;

        $checkStmt->execute([':code' => $branchCode]);
        $exists = (bool) $checkStmt->fetchColumn();

        $data = [
            ':code'   => $branchCode,
            ':branch' => $row['Branch'] ?? null,
            ':region' => $row['Location'] ?? null,
            ':corpo'  => $row['Company'] ?? null,
            ':area'   => $row['DM'] ?? null
        ];

        if ($exists) {

            $updateStmt->execute($data);
            $updated++;

        } else {

            $insertStmt->execute($data);
            $inserted++;
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Sync completed. Inserted: $inserted | Updated: $updated"
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}