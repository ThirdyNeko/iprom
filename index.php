<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<div class="content">

    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col">
                <h4 class="fw-bold">Dashboard</h4>
            </div>
        </div>

        <div class="row g-3">

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Total Promodizers</h6>
                        <h3>120</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Assigned</h6>
                        <h3>95</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Unassigned</h6>
                        <h3>25</h3>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

<!-- Bootstrap JS -->
<script src="assets/js/bootstrap.bundle.min.js"></script>

<script>
function toggleSidebar() {
    document.body.classList.toggle('collapsed');
}
</script>

</body>
</html>