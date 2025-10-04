<?php
// Navigation component - requires $currentUser to be set
if (!isset($currentUser)) {
    $currentUser = getCurrentUser();
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-primary" href="/">
            <i class="bi bi-cloud-arrow-up me-2"></i>Webbadeploy
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index.php' ? 'active fw-semibold' : '' ?>" href="/">
                        <i class="bi bi-grid me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'traefik-logs.php' ? 'active fw-semibold' : '' ?>" href="/traefik-logs.php">
                        <i class="bi bi-file-text me-1"></i>SSL Logs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'ssl-debug.php' ? 'active fw-semibold' : '' ?>" href="/ssl-debug.php">
                        <i class="bi bi-shield-lock me-1"></i>SSL Debug
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'debug.php' ? 'active fw-semibold' : '' ?>" href="/debug.php">
                        <i class="bi bi-bug me-1"></i>Debug
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'settings.php' ? 'active fw-semibold' : '' ?>" href="/settings.php">
                        <i class="bi bi-gear me-1"></i>Settings
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link position-relative text-success fw-semibold" href="#" onclick="showUpdateModal(); return false;" id="updateLink" style="display: none;">
                        <i class="bi bi-arrow-up-circle me-1"></i>Update Available
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-success border border-light rounded-circle">
                            <span class="visually-hidden">Update available</span>
                        </span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($currentUser['username']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                        <li>
                            <a class="dropdown-item" href="#" onclick="showPasswordModal(); return false;">
                                <i class="bi bi-key me-2"></i>Change Password
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
