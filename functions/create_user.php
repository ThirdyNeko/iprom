<?php
session_start();
include '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'super_admin' &&
     $_SESSION['role'] !== 'admin' &&
     $_SESSION['role'] !== 'supervisor')) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit;
}

$pdo = qa_db();

try {

    $username   = strtoupper(trim($_POST['username'] ?? ''));
    $role       = $_POST['role'] ?? null;

    $branches = $_POST['branches'] ?? [];
    $branch   = !empty($branches) ? implode(',', $branches) : null;
    $brand      = !empty($_POST['brand']) ? $_POST['brand'] : null;

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

    // duplicate check
    $check = $pdo->prepare("
        SELECT COUNT(*) 
        FROM users 
        WHERE username = ?
    ");

    $check->execute([$username]);

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
            first_name,
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
            :first_name,
            :last_name,
            :position,
            :department,
            :status,
            1
        )
    ");

    $stmt->execute([
        ':username'   => $username,
        ':password'   => $hashedPassword,
        ':role'       => $role,
        ':branch'     => $branch,
        ':brand'      => $brand,
        ':first_name' => $first_name,
        ':last_name'  => $last_name,
        ':position'   => $position,
        ':department' => $department,
        ':status'     => $status
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