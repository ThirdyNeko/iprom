<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Promodizer Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/bootstrap-icons/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/datatables.min.css">

    <!-- Custom CSS -->
    <style>
        body {
            overflow-x: hidden;
        }

        .sidebar {
            width: 240px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #343a40;
            display: flex;
            flex-direction: column;
            padding: 1.5rem 1rem; /* bigger padding */
        }

        .sidebar h5 {
            font-size: 24px; /* bigger logo */
        }

        .sidebar a {
            color: #adb5bd;
            text-decoration: none;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background-color: #495057;
            color: #fff;
        }

        .content {
            margin-left: 240px;
            padding: 20px;
        }

        .header {
            height: 60px;
            margin-left: 240px;
        }

        /* Collapsed sidebar (icon mode) */
        .collapsed .sidebar {
            width: 70px;
        }

        .collapsed .content,
        .collapsed .header {
            margin-left: 70px;
        }

        /* Hide text when collapsed */
        .collapsed .sidebar span {
            display: none;
        }

        /* Center icons */
        .collapsed .sidebar .nav-link {
            justify-content: center;
        }
        /* Hide text when sidebar is collapsed */
        .collapsed .sidebar-text {
            display: none;
        }

        /* Center icons when collapsed */
        .collapsed .btn, 
        .collapsed .d-flex.align-items-center.gap-2 {
            justify-content: center !important;
        }

        /* Optional: center logo */
        .collapsed .sidebar h5 {
            font-size: 16px;
        }
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 15px; /* more spacing between icon & text */
            padding: 0.75rem 1rem; /* bigger clickable area */
            font-size: 1.1rem; /* bigger text */
        }

        .sidebar .nav-link i {
            font-size: 1.4rem; /* bigger icons */
        }

        .sidebar .nav-link:hover {
            background-color: #495057;
            color: #fff;
        }

        .sidebar .nav-link.active {
            background-color: #0d6efd; /* Bootstrap primary color */
            color: #fff;
        }
        .sidebar .nav-link.active i {
            color: #fff; /* icon stays white */
        }

        .sidebar .nav-link.active span {
            font-weight: 600; /* bold text */
        }
    </style>
</head>
<body>

<!-- Header -->
<nav class="navbar navbar-light bg-light border-bottom header px-3">
    <button class="btn btn-outline-secondary" onclick="toggleSidebar()">☰</button>
    <span class="ms-3 fw-semibold">Promodizer Manager</span>
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