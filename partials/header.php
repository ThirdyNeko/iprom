<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>iProm</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/icons/CROWN.png">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/bootstrap-icons/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/datatables.min.css">
    <script src ="http://192.168.40.14/logger/hooks/qa_hook.js"></script>
    <!-- Custom CSS -->
    <style>
body {
    overflow-x: hidden;
    background: #f8fafc; /* softer background */
    font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}
.sidebar-logo {
    width: 28px;
    height: 28px;
    object-fit: contain;
}

/* When collapsed → hide text, keep logo centered */
.collapsed .sidebar h5 {
    display: none;
}

.collapsed .sidebar-logo {
    margin: 0 auto;
    display: block;
}
/* SIDEBAR */
.sidebar {
    width: 240px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background: #111827; /* modern dark */
    display: flex;
    flex-direction: column;
    padding: 1.5rem 1rem;
    transition: width 0.25s ease;
}

.sidebar h5 {
    font-size: 22px;
    font-weight: 600;
    color: #fff;
    letter-spacing: 0.5px;
}

/* LINKS */
.sidebar a {
    color: #9ca3af;
    text-decoration: none;
}

.sidebar .nav-link {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 12px;
    font-size: 16px;
    font-weight: 500;
    letter-spacing: 0.2;
    border-radius: 10px;
    transition: all 0.2s ease;
}

.sidebar .nav-link i {
    font-size: 16px;
}

/* HOVER */
.sidebar .nav-link:hover {
    background: rgba(255,255,255,0.08);
    color: #fff;
    transform: translateX(3px);
}

/* ACTIVE */
.sidebar .nav-link.active {
    background: #2563eb;
    color: #fff;
}

.sidebar .nav-link.active span {
    font-weight: 500;
}

/* CONTENT */
.content {
    margin-left: 240px;
    padding: 24px;
    transition: margin-left 0.25s ease;
}

/* HEADER */
.header {
    height: 60px;
    margin-left: 240px;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    transition: margin-left 0.25s ease;
}

/* TOGGLE BUTTON */
.header .btn {
    border-radius: 8px;
}

/* COLLAPSED MODE */
.collapsed .sidebar {
    width: 70px;
}

.collapsed .content,
.collapsed .header {
    margin-left: 70px;
}

/* Hide text */
.collapsed .sidebar span,
.collapsed .sidebar-text {
    display: none;
}

/* Center icons */
.collapsed .sidebar .nav-link {
    justify-content: center;
}

/* Center bottom section */
.collapsed .btn,
.collapsed .d-flex.align-items-center.gap-2 {
    justify-content: center !important;
}

/* Logo shrink */
.collapsed .sidebar h5 {
    font-size: 16px;
    text-align: center;
}

/* SMOOTH TRANSITIONS */
.sidebar,
.content,
.header {
    transition: all 0.25s ease;
}
</style>
</head>
<body>

<!-- Header -->
<nav class="navbar navbar-light bg-light border-bottom header px-3">
    <button class="btn btn-outline-secondary" onclick="toggleSidebar()">☰</button>
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