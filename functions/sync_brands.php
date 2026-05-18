<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$pdo = qa_db();

try {

    // =========================
    // GET BRANDS FROM SOURCE
    // =========================
    $stmt = $pdo->query("
        SELECT DISTINCT
            b.FirmName AS brand_name
        FROM VIAC_PROD_NEW.dbo.OMRC b
        WHERE b.FirmName IS NOT NULL
          AND LTRIM(RTRIM(b.FirmName)) <> ''
        ORDER BY b.FirmName
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $inserted = 0;
    $skipped  = 0;

    // prevent duplicates in same result set
    $seen = [];

    // =========================
    // PREPARED STATEMENTS
    // =========================
    $checkStmt = $pdo->prepare("
        SELECT TOP 1 1
        FROM brands
        WHERE UPPER(LTRIM(RTRIM(brand))) = UPPER(LTRIM(RTRIM(:brand)))
    ");

    $insertStmt = $pdo->prepare("
        INSERT INTO brands (
            brand,
            agency,
            status
        )
        VALUES (
            :brand,
            '',
            1
        )
    ");

    // =========================
    // LOOP BRANDS
    // =========================
    foreach ($rows as $row) {

        $brand = $row['brand_name'] ?? null;
        if (!$brand) continue;

        $brand = trim($brand);

        // skip duplicates from source
        $key = strtoupper($brand);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        // check if already exists in local table
        $checkStmt->execute([
            ':brand' => $brand
        ]);

        $exists = (bool) $checkStmt->fetchColumn();

        if ($exists) {
            $skipped++;
            continue;
        }

        // insert new brand
        $insertStmt->execute([
            ':brand' => $brand
        ]);

        $inserted++;
    }

    echo json_encode([
        "success" => true,
        "message" => "Sync completed. Inserted: $inserted | Skipped: $skipped"
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}