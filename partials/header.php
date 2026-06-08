<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../auth/require_login.php';

// Refresh session values from DB on every page load
if (isset($_SESSION['user_id'])) {
    $pdo = qa_db();
    $stmt = $pdo->prepare("
        SELECT role, branch, brand, position, department, status, first_login
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $fresh = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fresh) {
        $_SESSION['role']        = $fresh['role'];
        $_SESSION['branch']      = $fresh['branch']      ?? null;
        $_SESSION['brand']       = $fresh['brand']       ?? null;
        $_SESSION['position']    = $fresh['position']    ?? null;
        $_SESSION['department']  = $fresh['department']  ?? null;
        $_SESSION['status']      = $fresh['status']      ?? null;
        $_SESSION['first_login'] = $fresh['first_login'] ?? null;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>iProm</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/icons/LOGO ONLY RED.png">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/bootstrap-icons/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/datatables.min.css">
    <script src ="http://localhost/branch_logger/hooks/qa_hook.js"></script>
    <!-- Custom CSS -->
    <style>
body {
    overflow-x: hidden;
    background: #f8fafc;
    font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

/* LOGO */
.sidebar-logo {
    width: 50px;
    height: 50px;
    object-fit: contain;
}

/* SIDEBAR */
.sidebar {
    width: 240px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background: #c7dcff; 
    display: flex;
    flex-direction: column;
    padding: 1.5rem 1rem;
    transition: all 0.25s ease;
    border-right: 1px solid #dbeafe;
}

/* TITLE */
.sidebar h5 {
    font-size: 35px;
    font-weight: 600;
    color: #1e3a8a;
    letter-spacing: 0.3px;
}

/* LINKS */
.sidebar a {
    color: #1f2937;
    text-decoration: none;
}

/* NAV ITEMS */
.sidebar .nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    font-size: 15px;
    font-weight: 500;
    border-radius: 10px;
    transition: all 0.2s ease;
}

/* ICONS */
.sidebar .nav-link i {
    font-size: 16px;
    color: inherit;
}

/* HOVER */
.sidebar .nav-link:hover {
    background: rgba(37, 99, 235, 0.15);
    color: #1e3a8a;
    transform: translateX(2px);
}

/* ACTIVE */
.sidebar .nav-link.active {
    background: #2563eb;
    color: #fff;
}

/* ACTIVE ICON */
.sidebar .nav-link.active i {
    color: #fff;
}

/* CONTENT */
.content {
    margin-left: 240px;
    padding: 24px;
    transition: all 0.25s ease;
}

/* HEADER */
.header {
    height: 60px;
    margin-left: 240px;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    transition: all 0.25s ease;
}

/* BUTTON */
.header .btn {
    border-radius: 8px;
}

/* BOTTOM USER SECTION */
.sidebar .border-top {
    border-color: #d1d5db !important;
}

.sidebar .sidebar-text {
    color: #334155;
}

/* LOGOUT BUTTON */
.sidebar .btn-danger {
    border-radius: 10px;
    transition: all 0.2s ease;
}

.sidebar .btn-danger:hover {
    background-color: #b91c1c;
    transform: translateX(2px);
    color: #fff;
}

/* COLLAPSED MODE */
.collapsed .sidebar {
    width: 70px;
}

.collapsed .content,
.collapsed .header {
    margin-left: 70px;
}

/* HIDE TEXT */
.collapsed .sidebar span,
.collapsed .sidebar-text,
.collapsed .sidebar h5 {
    display: none;
}

.sidebar .btn-danger:hover .sidebar-text {
    color: #fff !important;
}

.sidebar .sidebar-text {
    transition: color 0.2s ease;
}

/* CENTER ICONS */
.collapsed .sidebar .nav-link {
    justify-content: center;
}

/* CENTER LOGO */
.collapsed .sidebar-logo {
    margin: 0 auto;
    display: block;
}

/* CENTER BOTTOM */
.collapsed .btn,
.collapsed .d-flex.align-items-center.gap-2 {
    justify-content: center !important;
}
</style>
</head>
<body>

<!-- Header -->
<nav class="navbar navbar-light bg-light border-bottom header px-3">
</nav>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;

    // Get stored sidebar state
    const collapsed = localStorage.getItem('sidebarCollapsed');

    if (collapsed === '1') {
        // User previously collapsed sidebar → restore collapsed
        body.classList.add('collapsed');
    } else if (collapsed === '0' || collapsed === null) {
        // Default to expanded
        body.classList.remove('collapsed');
        // Optional: explicitly store 0 if first login
        localStorage.setItem('sidebarCollapsed', '0');
    }

    // Attach toggle event
    const toggleBtn = document.querySelector('.btn-outline-secondary');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            body.classList.toggle('collapsed');

            // Save new state
            localStorage.setItem(
                'sidebarCollapsed',
                body.classList.contains('collapsed') ? '1' : '0'
            );
        });
    }
});
</script>