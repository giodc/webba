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
        <a class="navbar-brand fw-bold" href="/">
            <i class="bi bi-cloud-arrow-up me-2"></i>Webbadeploy
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
            <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index.php' ? 'active fw-semibold' : '' ?>" href="/">
                        <i class="bi bi-grid me-1"></i>Dashboard
                    </a>
                </li>
                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'users.php' ? 'active fw-semibold' : '' ?>" href="/users.php">
                        <i class="bi bi-people me-1"></i>Users
                    </a>
                </li>
                <?php endif; ?>
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
                <li class="nav-item">
                    <span class="nav-link text-muted">
                        <i class="bi bi-tag me-1"></i>v<?php 
                                    $versionFile = '/var/www/html/../VERSION';
                                    echo file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.0.0';
                                ?>
                    </span>
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
                        <li>
                            <a class="dropdown-item" href="#" onclick="show2FAModal(); return false;">
                                <i class="bi bi-shield-check me-2"></i>Two-Factor Auth
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <span class="dropdown-item-text text-muted small">
                                <i class="bi bi-shield-fill me-1"></i>Admin Account
                            </span>
                        </li>
                        <?php endif; ?>
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
