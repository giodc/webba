<!-- Change Password Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-key me-2"></i>Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="passwordForm" onsubmit="changePassword(event)">
                <div class="modal-body">
                    <!-- Hidden username field for browser accessibility -->
                    <input type="text" name="username" autocomplete="username" style="display:none;" value="<?= htmlspecialchars($currentUser['username'] ?? '') ?>" readonly>
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('current_password')">
                                <i class="bi bi-eye" id="current_password_icon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('new_password')">
                                <i class="bi bi-eye" id="new_password_icon"></i>
                            </button>
                            <button class="btn btn-outline-primary" type="button" onclick="generateRandomPassword()" title="Generate random password">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <div class="form-text">Minimum 8 characters. Click <i class="bi bi-arrow-clockwise"></i> to generate a secure password.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('confirm_password')">
                                <i class="bi bi-eye" id="confirm_password_icon"></i>
                            </button>
                        </div>
                    </div>
                    <div id="password_strength" class="mb-3" style="display: none;">
                        <div class="progress" style="height: 5px;">
                            <div id="password_strength_bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <small id="password_strength_text" class="text-muted"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Two-Factor Authentication Modal -->
<div class="modal fade" id="twoFactorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-shield-check me-2"></i>Two-Factor Authentication</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="twoFactorContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading 2FA settings...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Update Modal -->
<div class="modal fade" id="updateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-arrow-up-circle me-2"></i>System Update</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="updateContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Checking for updates...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="performUpdateBtn" style="display: none;" onclick="performUpdate()">
                    <i class="bi bi-download me-2"></i>Install Update
                </button>
            </div>
        </div>
    </div>
</div>
