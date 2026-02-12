<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// Session Timeout Logic (30 minutes)
$timeout_duration = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "login.php?error=timeout");
    exit();
}
$_SESSION['last_activity'] = time();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Sheet Online - Hana Project</title>
    <!-- Bootstrap 5 CSS -->
    <link href="<?php echo BASE_URL; ?>assets/libs/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/libs/fontawesome/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <!-- SweetAlert2 -->
    <script src="<?php echo BASE_URL; ?>assets/libs/sweetalert2/sweetalert2.all.min.js"></script>
</head>

<body>

    <div class="sidebar" id="sidebar">
        <div class="p-4 mb-4">
            <a href="<?php echo BASE_URL; ?>index.php" class="text-decoration-none">
                <h4 class="fw-bold text-primary mb-0"><i class="fas fa-microchip me-2"></i><span>Hana Check</span></h4>
            </a>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link active" href="<?php echo BASE_URL; ?>index.php">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
            <a class="nav-link" href="<?php echo BASE_URL; ?>pages/machines.php">
                <i class="fas fa-industry"></i> <span>Machines</span>
            </a>
            <a class="nav-link" href="<?php echo BASE_URL; ?>pages/products.php">
                <i class="fas fa-box"></i> <span>Products</span>
            </a>
            <a class="nav-link" href="<?php echo BASE_URL; ?>pages/families.php">
                <i class="fas fa-tags"></i> <span>Family</span>
            </a>
            <a class="nav-link" href="<?php echo BASE_URL; ?>pages/tooling.php">
                <i class="fas fa-tools"></i> <span>Tooling</span>
            </a>
            <a class="nav-link" href="<?php echo BASE_URL; ?>pages/check_master.php">
                <i class="fas fa-clipboard-list"></i> <span>Check Items</span>
            </a>
            <a class="nav-link" href="<?php echo BASE_URL; ?>pages/parameters.php">
                <i class="fas fa-sliders-h"></i> <span>Parameters</span>
            </a>
            <a class="nav-link" href="<?php echo BASE_URL; ?>pages/inspections.php">
                <i class="fas fa-microscope"></i> <span>Inspections</span>
            </a>
            <a class="nav-link" href="<?php echo BASE_URL; ?>pages/check_form.php">
                <i class="fas fa-clipboard-check"></i> <span>Fill Check Sheet</span>
            </a>
            <a class="nav-link" href="<?php echo BASE_URL; ?>pages/history.php">
                <i class="fas fa-history"></i> <span>History</span>
            </a>
            <a class="nav-link" href="<?php echo BASE_URL; ?>pages/downtime.php">
                <i class="fas fa-exclamation-triangle"></i> <span>Downtime / Repair</span>
            </a>
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <a class="nav-link" href="<?php echo BASE_URL; ?>pages/users.php">
                    <i class="fas fa-users-cog"></i> <span>User Management</span>
                </a>
            <?php endif; ?>
        </nav>
    </div>

    <main class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-4">
            <button class="btn btn-white shadow-sm border rounded-pill px-3 me-3" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h2 class="fw-bold mb-0" id="page-title">Dashboard</h2>
                <p class="text-muted small">ระบบตรวจเช็คเครื่องจักรและเครื่องมือออนไลน์</p>
            </div>
            <div class="d-flex align-items-center">
                <div class="me-3 text-end d-none d-md-block">
                    <div class="fw-bold"><?php echo $_SESSION['full_name']; ?></div>
                    <div class="text-muted small"><?php echo ucfirst($_SESSION['role']); ?> |
                        <?php echo $_SESSION['department']; ?>
                    </div>
                </div>
                <?php
                $initial = strtoupper(substr($_SESSION['full_name'], 0, 1));
                $bgColors = ['primary', 'success', 'danger', 'warning', 'info', 'dark'];
                $bgClass = $bgColors[ord($initial) % count($bgColors)];
                ?>
                <div class="rounded-circle shadow-sm d-flex align-items-center justify-content-center bg-<?php echo $bgClass; ?> text-white"
                    style="width: 45px; height: 45px; font-size: 1.2rem; font-weight: bold;">
                    <?php echo $initial; ?>
                </div>
                <a href="<?php echo BASE_URL; ?>actions/logout.php"
                    class="btn btn-white border rounded-pill shadow-sm ms-3 text-danger d-flex align-items-center px-3"
                    title="Logout">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    <span class="small fw-bold">Logout</span>
                </a>
            </div>
        </header>