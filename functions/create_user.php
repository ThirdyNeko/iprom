<?php
session_start();
include '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'super_admin' &&
     $_SESSION['role'] !== 'admin' &&
     $_SESSION['role'] !== 'supervisor' &&
     $_SESSION['role'] !== 'audit_manager' &&
     $_SESSION['role'] !== 'audit_supervisor')) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit;
}

$pdo = qa_db();

try {

    $username    = strtoupper(trim($_POST['username'] ?? ''));
    $middle_name = strtoupper(trim($_POST['middle_name'] ?? ''));
    $role        = $_POST['role'] ?? null;

    $branches = $_POST['branches'] ?? [];
    $branch   = !empty($branches) ? implode(',', $branches) : null;
    $brand      = !empty($_POST['brand']) ? $_POST['brand'] : null;

    $regions = $_POST['regions'] ?? [];
    $region  = !empty($regions) ? implode(',', $regions) : null;

    $first_name = strtoupper(trim($_POST['first_name'] ?? ''));
    $last_name  = strtoupper(trim($_POST['last_name'] ?? ''));
    $position   = trim($_POST['position'] ?? '');
    $department = strtoupper(trim($_POST['department'] ?? ''));
    $status = "ACTIVE";

    if (!$username || !$role || !$first_name || !$last_name || !$position) {

        echo json_encode([
            'status' => 'error',
            'message' => 'Please fill in required fields'
        ]);

        exit;
    }

    // BRANCH MANAGER = exactly one branch. Enforced client-side already,
    // but the client can't be trusted — check it again here.
    if ($role === 'branch_manager' && count($branches) !== 1) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please select exactly one branch for a Branch Manager'
        ]);
        exit;
    }

    // REGIONAL MANAGER = exactly one region, same rule as branch_manager.
    if ($role === 'regional_manager' && count($regions) !== 1) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please select exactly one region for a Regional Manager'
        ]);
        exit;
    }

    // duplicate check — same username is allowed if middle_name differs;
    // inactive users are excluded from the check entirely
    $check = $pdo->prepare("
        SELECT COUNT(*) 
        FROM users 
        WHERE username = ?
          AND middle_name = ?
          AND status != 'INACTIVE'
    ");

    $check->execute([$username, $middle_name]);

    if ($check->fetchColumn() > 0) {

        echo json_encode([
            'status' => 'error',
            'message' => 'Username already exists'
        ]);

        exit;
    }

    $defaultPassword = 'Password123';

    $hashedPassword = password_hash(
        $defaultPassword,
        PASSWORD_DEFAULT
    );

    $stmt = $pdo->prepare("
        INSERT INTO users (
            username,
            password,
            role,
            branch,
            brand,
            region,
            first_name,
            middle_name,
            last_name,
            position,
            department,
            status,
            first_login
        )
        VALUES (
            :username,
            :password,
            :role,
            :branch,
            :brand,
            :region,
            :first_name,
            :middle_name,
            :last_name,
            :position,
            :department,
            :status,
            1
        )
    ");

    $stmt->execute([
        ':username'    => $username,
        ':password'    => $hashedPassword,
        ':role'        => $role,
        ':branch'      => $branch,
        ':brand'       => $brand,
        ':region'      => $region,
        ':first_name'  => $first_name,
        ':middle_name' => $middle_name,
        ':last_name'   => $last_name,
        ':position'    => $position,
        ':department'  => $department,
        ':status'      => $status
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'User created successfully'
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}