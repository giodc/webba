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
        <a class="navbar-brand fw-bold d-flex align-items-center" href="/">
           <svg id="wharftales-logo" data-name="Logo" 
          viewBox="0 0 1024 1024" width="50" height="50" >
  <defs>
    <style>
      .cls-1 {
        fill: none;
      }

      .cls-2 {
        clip-path: url(#clippath);
      }
    </style>
    <clipPath id="clippath">
      <circle class="cls-1" cx="-606.18" cy="512" r="456.38"/>
    </clipPath>
  </defs>
  <g class="cls-2">
    <path d="M-199.86,346.86H-54.31c5.59,5.23,6.3,13.08,6.91,20.3,5.38,64.03,1.23,149.36.05,215.19-.07,3.82-.81,7.41-2.5,10.81l3.75,3.95c9.65-5.37,17.56-.21,27.05.16,16.74.65,33.72-.73,50.45-.14l-2.56,23.31-406.58,1.36-7.1-24.65H-77.44v-223.08h-380.86l-8.07-23.16,3.99-4.05h232.6V118.34h-527.77v228.52l21.81,4.13-5.32,21.88-16.49,1.2v148.26c0,1.17-10.35,9.96-12.7,11.78-4.77,3.68-11.55,8.54-17.22,8.62v-253h-334.62c-.72,1.99,2.72,3.25,2.72,4.08v285.65c0,.83-3.44,2.09-2.72,4.08h306.05l3.62,3.98-11.78,23.22h-403.99l.15-21.61,78.74-2.87v-316.93c0-.76,2.79-6.34,4.58-6.9,16.35,1.42,32.18-2.14,48.3-2.7,102.8-3.53,206.92,1.78,308.93,2.8V95.22c0-.47,4.2-4.8,5.48-5.4l576.69-.04c1.22.74,5.44,7.39,5.44,8.16v248.92Z"/>
  </g>
  <g>
    <path d="M606.7,657.49v129.47c0,5.52-16.05,15.09-21.12,17.24-28.73,12.15-122.49,13.99-146.41-6.98-4.55-3.99-8.25-9.96-8.97-15.96-4.08-34.03,2.77-76.23.13-111.38-.32-4.26-1.63-8.19-2.02-12.39-8.17,4.52-9.56,17.72-10.97,26.41-2.89,17.91-2.39,37.29-4.38,55.08-1.3,11.63-1.31,21.06-14.81,23.54-21.42,3.94-34.8-13.23-35.18-33.08-.49-26.26,25.96-107.82,47.13-123.73,43.83-32.94,182.33-35.6,221.61,4.71,11.27,11.57,20.36,34.08,25.36,49.45,4.03,12.38,16.54,53.15,16.82,63.87.75,28.18-39.61,52.61-47.15,27.83-2.71-8.91-1.57-30.04-3.01-41.1-1.06-8.11-6.71-47.32-10.31-51.07-2.28-2.37-3.66-2.16-6.72-1.91Z"/>
    <path d="M800.42,458.01h102.61c3.94,3.68,4.44,9.22,4.87,14.31,3.79,45.14.87,105.31.04,151.72-.05,2.69-.57,5.22-1.76,7.62l2.64,2.78c6.8-3.79,12.38-.15,19.07.11,11.81.46,23.77-.51,35.57-.1l-1.81,16.43-286.66.96-5-17.38h216.74v-157.28h-268.52l-5.69-16.33,2.82-2.85h163.99v-161.11h-372.1v161.11l15.38,2.91-3.75,15.42-11.63.85v104.53c0,.82-7.3,7.02-8.96,8.31-3.36,2.6-8.14,6.02-12.14,6.08v-178.38h-235.92c-.51,1.4,1.92,2.29,1.92,2.88v201.39c0,.58-2.43,1.47-1.92,2.88h215.78l2.55,2.81-8.3,16.37H75.41l.11-15.24,55.52-2.03v-223.45c0-.53,1.97-4.47,3.23-4.86,11.53,1,22.69-1.51,34.06-1.9,72.48-2.49,145.89,1.26,217.81,1.97v-117.96c0-.33,2.96-3.38,3.87-3.81l406.59-.03c.86.52,3.84,5.21,3.84,5.75v175.5Z"/>
    <path d="M184.74,434.04c9.37-9.22,9.33,12.04,9.58,17.13,2.21,43.63,3.09,104.7.1,147.91-.26,3.72-1.17,6.94-2.04,10.47l-7.65-1.93v-173.58Z"/>
    <path d="M315.16,608.58c-.78,3.15-6.39,2.29-7.41.66-.53-.84-2.06-17.16-2.11-19.79-.72-33.04-.01-68.17-.17-101.62-.08-16.97-.15-34.1,1.03-50.94l5.77-1.88,2.9,2.86v170.7Z"/>
    <polygon points="225.02 433.08 232.68 433.07 233.9 524.5 230.86 608.56 225.02 609.54 225.02 433.08"/>
    <polygon points="272.97 433.08 272.97 607.62 265.29 607.62 265.29 431.16 272.97 433.08"/>
    <polygon points="355.44 607.62 347.77 607.62 347.77 435 355.44 433.08 355.44 607.62"/>
    <path d="M673.83,494.46v124.67c-7.15-.02-8.96-8.76-9.59-14.39-2.45-21.78-2.69-82.11-.09-103.66.85-7.06,2.62-7.04,9.68-6.62Z"/>
    <polygon points="735.21 312.24 735.21 442.67 727.54 442.67 727.54 310.33 735.21 312.24"/>
    <path d="M693.01,312.24v130.43h-7.67v-127.55c0-2.03,5.44-3.65,7.67-2.88Z"/>
    <path d="M652.73,440.75h-7.67v-128.51c2.23-.77,7.67.85,7.67,2.88v125.63Z"/>
    <path d="M836.86,619.13l-7.67-1.92v-123.71l2.88-2.84c.9,1.23,4.8,4.16,4.8,4.76v123.71Z"/>
    <path d="M796.59,619.13l-7.67-1.92v-124.67l5.73-1.92c-.6,1.94,1.94,4.11,1.94,4.79v123.71Z"/>
    <path d="M610.54,308.41v126.59h-7.67v-119.88c0-1.03,2.65-4.29,2.07-6.59l5.6-.12Z"/>
    <path d="M754.39,619.13c-3.64,1.31-7.67-1.24-7.67-4.8v-118.92l2.91-2.87,4.77,2.87v123.71Z"/>
    <path d="M714.11,619.13l-7.8-3.08-.03-118.78c.05-3.49,4.99-4.65,7.82-6.65v128.51Z"/>
    <polygon points="447.51 423.49 439.83 425.41 439.83 313.2 442.74 310.34 447.51 313.2 447.51 423.49"/>
    <path d="M633.55,579.81c-1.36,4.63-9.59,3.86-9.59-1.92v-80.56c0-3.61,9.59-3.61,9.59,0v82.47Z"/>
    <path d="M480.11,313.2c1.2-4.97,8.89-1.48,9.7,5.64,1.51,13.22,1.89,60.55-.18,72.92-.37,2.21-.96,3.54-2.81,4.86l-2.85-3.83-1.92,3.82c.74-2.39-1.94-5.55-1.94-6.7v-76.72Z"/>
    <path d="M570.26,402.39h-7.67v-92.07c2.23-.77,7.67.85,7.67,2.88v89.19Z"/>
    <path d="M522.31,310.33l7.8,3.08.03,72.74c-.05,3.49-4.99,4.65-7.82,6.65v-82.47Z"/>
  </g>
  <path d="M547.09,554.68c-4.59-20.37,11.23-25.88,18.62-41.81,22.3-48.08-9.52-110.97-65.97-90.9-39.06,13.88-44.54,62.77-25.85,95.62,7.68,13.5,19.92,18.32,15.66,37.09"/>
</svg>
            Wharftales
            <span class="badge bg-secondary ms-2 fw-normal" style="font-size: 0.7rem;">
                v<?php 
                    $versionFile = '/var/www/html/../VERSION';
                    echo file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.0.0';
                ?>
            </span>
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
