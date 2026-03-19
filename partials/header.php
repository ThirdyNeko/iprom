<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Promodizer Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <!-- Custom CSS -->
    <style>
        body {
            overflow-x: hidden;
        }

        .sidebar {
            width: 240px;
            height: 100vh;
            position: fixed;
            background-color: #343a40; /* muted dark grey */
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

        .collapsed .sidebar {
            margin-left: -240px;
        }

        .collapsed .content,
        .collapsed .header {
            margin-left: 0;
        }
    </style>
</head>
<body>

<!-- Header -->
<nav class="navbar navbar-light bg-light border-bottom header px-3">
    <button class="btn btn-outline-secondary" onclick="toggleSidebar()">☰</button>
    <span class="ms-3 fw-semibold">Promodizer Manager</span>
</nav>