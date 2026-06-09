<div class="sidebar d-flex flex-column p-3">

    <!-- Logo / Title -->
    <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
        <img src="assets/icons/LOGO ONLY RED.png" alt="iProm Logo" class="sidebar-logo">
        <h5 class="m-0" style="transform: translateY(2px);">iProm</h5>
    </div>

    <!-- Menu -->
    <ul class="nav nav-pills flex-column mb-3">

        <li class="nav-item">
            <a href="index.php" class="nav-link d-flex align-items-center gap-2 <?= $current_page == 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li>
            <a href="assignments.php" class="nav-link d-flex align-items-center gap-2 <?= $current_page == 'assignments.php' ? 'active' : '' ?>">
                <i class="bi bi-diagram-3"></i>
                <span>Assignments</span>
            </a>
        </li>

        <li>
            <a href="promodizers.php" class="nav-link d-flex align-items-center gap-2 <?= $current_page == 'promodizers.php' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Promodisers</span>
            </a>
        </li>        

        <li>
            <a href="logs.php" class="nav-link d-flex align-items-center gap-2 <?= $current_page == 'logs.php' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i>
                <span>Logs</span>
            </a>
        </li>

        <!-- ✅ ADMIN ONLY: Users -->
        <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin') || ($_SESSION['role'] === 'super_admin')): ?>
        <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2 <?= in_array($current_page, ['branches.php', 'agencies.php']) ? '' : 'collapsed' ?>"
            data-bs-toggle="collapse"
            href="#settingsSubmenu"
            role="button"
            aria-expanded="<?= in_array($current_page, ['branches.php', 'agencies.php']) ? 'true' : 'false' ?>"
            aria-controls="settingsSubmenu">

                <i class="bi bi-gear"></i>
                <span>Settings</span>
                <i class="bi bi-chevron-down ms-auto"></i>
            </a>

            <div class="collapse <?= in_array($current_page, ['branches.php', 'agencies.php']) ? 'show' : '' ?>"
                id="settingsSubmenu">

                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ms-4">

                    <li>
                        <a href="branches.php"
                        class="nav-link <?= $current_page == 'branches.php' ? 'active' : '' ?>">
                            Branches
                        </a>
                    </li>

                    <li>
                        <a href="agencies.php"
                        class="nav-link <?= $current_page == 'agencies.php' ? 'active' : '' ?>">
                            Agencies
                        </a>
                    </li>

                </ul>
            </div>
        </li>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
        <li>
            <a href="merge.php" class="nav-link d-flex align-items-center gap-2 <?= $current_page == 'merge.php' ? 'active' : '' ?>">
                <i class="bi bi-arrow-left-right"></i>
                <span>Merge Employees</span>
            </a>
        </li>
        <?php endif; ?>

        <li>
            <a href="reports.php" class="nav-link d-flex align-items-center gap-2 <?= $current_page == 'reports.php' ? 'active' : '' ?>">
                <i class="bi bi-clipboard-data"></i>
                <span>Reports</span>
            </a>
        </li>
        <?php endif; ?> 

        <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin') || ($_SESSION['role'] === 'super_admin') ||($_SESSION['role'] === 'supervisor') ): ?>

        <li>
            <a href="users.php" class="nav-link d-flex align-items-center gap-2 <?= $current_page == 'users.php' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i>
                <span>Users</span>
            </a>
        </li>        
        <?php endif; ?>

        <!-- Change Password Sidebar Link (Modal Trigger) -->
        <li>
            <a href="#" 
            class="nav-link d-flex align-items-center gap-2 "
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
        <?php
        $roleLabels = [
            'admin' => 'ADMIN',
            'hr'    => 'HUMAN RESOURCES',
            'manager' => 'MANAGER', // optional if you have manager too
        ];
        $currentRole = $_SESSION['username'] ?? 'Guest';
        $roleDisplay = $roleLabels[$currentRole] ?? $currentRole;
        ?>
        <div class="small mb-2 d-flex align-items-center gap-2">
            <i class="bi bi-person-circle"></i>
            <span class="sidebar-text"><?= htmlspecialchars($roleDisplay) ?></span>
        </div>

        <!-- Logout Button -->
        <a href="auth/logout.php" onclick="localStorage.removeItem('sidebarCollapsed')" class="btn btn-danger w-100 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-box-arrow-right"></i>
            <span class="sidebar-text">Logout</span>
        </a>
    </div>

</div>