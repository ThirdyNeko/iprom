<div class="sidebar d-flex flex-column p-3">

    <!-- Logo / Title -->
    <h5 class="text-white text-center mb-4">PM</h5>

    <!-- Menu -->
    <ul class="nav nav-pills flex-column mb-3">

        <li class="nav-item">
            <a href="index.php" class="nav-link d-flex align-items-center gap-2 text-light <?= $current_page == 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li>
            <a href="promodizers.php" class="nav-link d-flex align-items-center gap-2 text-light <?= $current_page == 'promodizers.php' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Promodizers</span>
            </a>
        </li>

        <li>
            <a href="assignments.php" class="nav-link d-flex align-items-center gap-2 text-light <?= $current_page == 'assignments.php' ? 'active' : '' ?>">
                <i class="bi bi-diagram-3"></i>
                <span>Assignments</span>
            </a>
        </li>

        <!-- Change Password Sidebar Link (Modal Trigger) -->
        <li>
            <a href="#" 
            class="nav-link d-flex align-items-center gap-2 text-light"
            data-bs-toggle="modal" 
            data-bs-target="#changePasswordModal">
                <i class="bi bi-key"></i>
                <span>Change Password</span>
            </a>
        </li>
    </ul>

    <!-- Spacer pushes bottom down -->
    <div class="flex-grow-1"></div>

    <!-- Bottom Section -->
    <div class="mt-auto pt-3 border-top border-secondary">
        <div class="text-light small mb-2 d-flex align-items-center gap-2">
            <i class="bi bi-person-circle"></i>
            <span class="sidebar-text"><?= $_SESSION['username'] ?? 'Admin' ?></span>
        </div>

        <!-- Logout Button -->
        <a href="auth/logout.php" onclick="localStorage.removeItem('sidebarCollapsed')" class="btn btn-danger w-100 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-box-arrow-right"></i>
            <span class="sidebar-text">Logout</span>
        </a>
    </div>

</div>