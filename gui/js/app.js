let createModal, editModal, passwordModal, updateModal;

// Version check - if you see this in console, the new JS is loaded
console.log("Webbadeploy JS v5.0 loaded - Async stats loading for performance!");

document.addEventListener("DOMContentLoaded", function() {
    createModal = new bootstrap.Modal(document.getElementById("createModal"));
    editModal = new bootstrap.Modal(document.getElementById("editModal"));
    passwordModal = new bootstrap.Modal(document.getElementById("passwordModal"));
    updateModal = new bootstrap.Modal(document.getElementById("updateModal"));
    
    // Check for updates on page load
    checkForUpdatesBackground();
    
    // Auto-generate domain from name
    document.querySelector("input[name=\"name\"]").addEventListener("input", function(e) {
        const domain = e.target.value.toLowerCase()
            .replace(/[^a-z0-9\s]/g, "")
            .replace(/\s+/g, "-")
            .substring(0, 20);
        document.querySelector("input[name=\"domain\"]").value = domain;
    });

    // Handle domain suffix changes
    document.querySelector("select[name=\"domain_suffix\"]").addEventListener("change", function(e) {
        const customField = document.getElementById("customDomainField");
        const sslCheck = document.getElementById("sslCheck");

        if (e.target.value === "custom") {
            customField.style.display = "block";
            sslCheck.disabled = false;
        } else {
            customField.style.display = "none";
            sslCheck.disabled = true;
            sslCheck.checked = false;
            // Hide SSL challenge options when SSL is disabled
            document.getElementById("sslChallengeOptions").style.display = "none";
        }
    });

    // Load stats asynchronously for all sites
    loadAllDashboardStats();
    
    // Update site statuses
    updateAllSiteStatuses();
    setInterval(updateAllSiteStatuses, 30000); // Check every 30 seconds
    
    // Refresh stats every 10 seconds
    setInterval(loadAllDashboardStats, 10000);
});

function showCreateModal() {
    createModal.show();
}

function toggleSSLOptions(domainSuffix) {
    const sslCheck = document.getElementById("sslCheck");
    const sslChallengeOptions = document.getElementById("sslChallengeOptions");
    
    if (domainSuffix !== "custom") {
        sslCheck.disabled = true;
        sslCheck.checked = false;
        sslChallengeOptions.style.display = "none";
    } else {
        sslCheck.disabled = false;
    }
}

function toggleSSLChallengeOptions() {
    const sslCheck = document.getElementById("sslCheck");
    const sslChallengeOptions = document.getElementById("sslChallengeOptions");
    
    if (sslCheck.checked) {
        sslChallengeOptions.style.display = "block";
    } else {
        sslChallengeOptions.style.display = "none";
        document.getElementById("dnsProviderOptions").style.display = "none";
    }
}

function toggleDNSProviderOptions(challengeMethod) {
    const dnsProviderOptions = document.getElementById("dnsProviderOptions");
    
    if (challengeMethod === "dns") {
        dnsProviderOptions.style.display = "block";
    } else {
        dnsProviderOptions.style.display = "none";
        // Hide all DNS provider fields
        document.querySelectorAll(".dns-provider-fields").forEach(el => {
            el.style.display = "none";
        });
    }
}

function showDNSProviderFields(provider) {
    // Hide all DNS provider fields first
    document.querySelectorAll(".dns-provider-fields").forEach(el => {
        el.style.display = "none";
    });
    
    // Show the selected provider's fields
    if (provider) {
        const fieldId = provider + "Fields";
        const field = document.getElementById(fieldId);
        if (field) {
            field.style.display = "block";
        }
    }
}

function toggleTypeOptions(type) {
    const wpOptions = document.getElementById("wordpressOptions");

    if (type === "wordpress") {
        wpOptions.style.display = "block";

        // Generate strong password
        const passwordField = document.querySelector("input[name=\"wp_password\"]");
        if (!passwordField.value) {
            passwordField.value = generatePassword(16);
        }
    } else {
        wpOptions.style.display = "none";
    }
}

function generatePassword(length) {
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
    let password = "";
    for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    return password;
}

async function createSite(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData.entries());

    // Convert checkbox values
    data.ssl = formData.has("ssl");
    data.wp_optimize = formData.has("wp_optimize");

    const submitBtn = event.target.querySelector("button[type=\"submit\"]");
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2\"></span>Deploying...";
    submitBtn.disabled = true;

    try {
        const response = await fetch("api.php?action=create_site", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            if (result.warning) {
                showAlert("warning", result.message + (result.error_details ? "<br><small>Error: " + result.error_details + "</small>" : ""));
                createModal.hide();
                setTimeout(() => location.reload(), 3000);
            } else {
                showAlert("success", result.message || "Application deployed successfully!");
                createModal.hide();
                setTimeout(() => location.reload(), 1500);
            }
        } else {
            showAlert("danger", result.error || "Failed to create application");
        }
    } catch (error) {
        showAlert("danger", "Network error: " + error.message);
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

async function editSite(id) {
    try {
        const response = await fetch("api.php?action=get_site&id=" + id);
        const result = await response.json();

        if (result.success) {
            const site = result.site;
            
            // Basic fields
            document.getElementById("editSiteId").value = site.id;
            document.getElementById("editName").value = site.name;
            document.getElementById("editDomain").value = site.domain;
            document.getElementById("editSsl").checked = site.ssl == 1;
            document.getElementById("editStatus").value = site.status;
            
            // Type fields
            document.getElementById("editType").value = site.type;
            document.getElementById("editTypeDisplay").value = site.type.charAt(0).toUpperCase() + site.type.slice(1);
            
            // Container info
            document.getElementById("editContainerName").value = site.container_name;
            document.getElementById("editContainerNameDisplay").textContent = site.container_name;
            
            // Created date
            const createdDate = new Date(site.created_at);
            document.getElementById("editCreatedAt").textContent = createdDate.toLocaleString();
            
            editModal.show();
        } else {
            showAlert("danger", result.error || "Failed to load site data");
        }
    } catch (error) {
        showAlert("danger", "Network error: " + error.message);
    }
}

async function updateSite(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData.entries());
    data.ssl = formData.has("ssl");

    const submitBtn = event.target.querySelector("button[type=\"submit\"]");
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2\"></span>Updating...";
    submitBtn.disabled = true;

    try {
        const response = await fetch("api.php?action=update_site", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            if (result.needs_restart || result.domain_changed) {
                showAlert("warning", result.message);
            } else {
                showAlert("success", result.message || "Application updated successfully!");
            }
            editModal.hide();
            setTimeout(() => location.reload(), 2000);
        } else {
            showAlert("danger", result.error || "Failed to update application");
        }
    } catch (error) {
        showAlert("danger", "Network error: " + error.message);
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

function viewSite(domain, ssl) {
    const protocol = ssl ? "https" : "http";
    let url;
    
    // Handle different domain formats
    if (domain.includes(":")) {
        // Port-based domain
        url = protocol + "://" + window.location.hostname + domain;
    } else if (domain.includes(".test.local") || domain.includes(".localhost")) {
        // Local domain
        url = protocol + "://" + domain;
    } else {
        // Custom domain
        url = protocol + "://" + domain;
    }
    
    window.open(url, "_blank");
}

async function deleteSite(id) {
    if (!confirm("Are you sure you want to delete this application? This action cannot be undone.")) {
        return;
    }

    try {
        const response = await fetch("api.php?action=delete_site&id=" + id, {
            method: "GET"
        });

        const result = await response.json();

        if (result.success) {
            showAlert("success", "Application deleted successfully");
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert("danger", result.error || "Failed to delete application");
        }
    } catch (error) {
        showAlert("danger", "Network error: " + error.message);
    }
}

async function loadAllDashboardStats() {
    const siteCards = document.querySelectorAll("[data-site-id]");
    
    for (let card of siteCards) {
        const siteId = card.getAttribute("data-site-id");
        const statsSection = card.querySelector(`#stats-${siteId}`);
        
        if (statsSection) {
            try {
                const response = await fetch(`api.php?action=get_dashboard_stats&id=${siteId}`);
                const result = await response.json();
                
                if (result.success && result.stats) {
                    const stats = result.stats;
                    
                    // Update CPU
                    const cpuText = statsSection.querySelector(".stats-cpu");
                    const cpuBar = statsSection.querySelector(".stats-cpu-bar");
                    if (cpuText) cpuText.textContent = stats.cpu;
                    if (cpuBar) cpuBar.style.width = Math.min(stats.cpu_percent, 100) + "%";
                    
                    // Update Memory
                    const memText = statsSection.querySelector(".stats-memory");
                    const memBar = statsSection.querySelector(".stats-memory-bar");
                    if (memText) memText.textContent = stats.memory;
                    if (memBar) memBar.style.width = Math.min(stats.mem_percent, 100) + "%";
                }
            } catch (error) {
                console.error("Failed to load stats for site", siteId, error);
            }
        }
    }
}

async function updateAllSiteStatuses() {
    const statusBadges = document.querySelectorAll(".status-badge");

    for (let badge of statusBadges) {
        const card = badge.closest(".card");
        const siteId = getSiteIdFromCard(card);

        if (siteId) {
            try {
                const response = await fetch("api.php?action=site_status&id=" + siteId);
                const result = await response.json();

                if (result.status) {
                    updateStatusBadge(badge, result.status);
                    updateStatusIndicator(card, result.status);
                }
            } catch (error) {
                console.error("Failed to update status for site", siteId, error);
            }
        }
    }
}

function getSiteIdFromCard(card) {
    const editBtn = card.querySelector("button[onclick*=\"editSite\"]");
    if (editBtn) {
        const match = editBtn.getAttribute("onclick").match(/editSite\((\d+)\)/);
        return match ? match[1] : null;
    }
    return null;
}

function updateStatusBadge(badge, status) {
    const statusClass = status === "running" ? "bg-success" : (status === "starting" ? "bg-warning" : "bg-danger");
    badge.className = "badge status-badge " + statusClass;
    badge.innerHTML = "<i class=\"bi bi-circle-fill me-1\"></i>" + status.charAt(0).toUpperCase() + status.slice(1);
}

function updateStatusIndicator(card, status) {
    const indicator = card.querySelector(".status-indicator");
    if (indicator) {
        indicator.className = "status-indicator status-" + status;
    }
}

function showAlert(type, message) {
    const alertHtml = "<div class=\"alert alert-" + type + " alert-dismissible fade show position-fixed\" style=\"top: 20px; right: 20px; z-index: 9999; min-width: 300px;\">" + message + "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button></div>";

    document.body.insertAdjacentHTML("beforeend", alertHtml);

    setTimeout(() => {
        const alert = document.querySelector(".alert:last-of-type");
        if (alert) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }
    }, 5000);
}

function showPasswordModal() {
    document.getElementById("passwordForm").reset();
    passwordModal.show();
}

async function changePassword(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const currentPassword = formData.get("current_password");
    const newPassword = formData.get("new_password");
    const confirmPassword = formData.get("confirm_password");

    // Validate passwords match
    if (newPassword !== confirmPassword) {
        showAlert("danger", "New passwords do not match");
        return;
    }

    // Validate password length
    if (newPassword.length < 6) {
        showAlert("danger", "Password must be at least 6 characters long");
        return;
    }

    const submitBtn = event.target.querySelector("button[type=\"submit\"]");
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2\"></span>Changing...";
    submitBtn.disabled = true;

    try {
        const response = await fetch("api.php?action=change_password", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                current_password: currentPassword,
                new_password: newPassword
            })
        });

        const result = await response.json();

        if (result.success) {
            showAlert("success", "Password changed successfully!");
            passwordModal.hide();
            event.target.reset();
        } else {
            showAlert("danger", result.error || "Failed to change password");
        }
    } catch (error) {
        showAlert("danger", "Network error: " + error.message);
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

// ============================================
// UPDATE SYSTEM FUNCTIONS
// ============================================

async function checkForUpdatesBackground() {
    try {
        const response = await fetch("api.php?action=check_updates");
        const result = await response.json();
        
        if (result.success && result.data.update_available) {
            document.getElementById("updateLink").style.display = "block";
        }
    } catch (error) {
        console.error("Failed to check for updates:", error);
    }
}

async function showUpdateModal() {
    updateModal.show();
    
    try {
        const response = await fetch("api.php?action=get_update_info");
        const result = await response.json();
        
        if (result.success) {
            displayUpdateInfo(result.info, result.changelog);
        } else {
            document.getElementById("updateContent").innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Failed to load update information: ${result.error}
                </div>
            `;
        }
    } catch (error) {
        document.getElementById("updateContent").innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Network error: ${error.message}
            </div>
        `;
    }
}

function displayUpdateInfo(info, changelog) {
    const updateBtn = document.getElementById("performUpdateBtn");
    
    let html = `
        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="text-muted">Current Version</h6>
                <h4>${info.current_version}</h4>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Latest Version</h6>
                <h4>${info.remote_version || "Unknown"}</h4>
            </div>
        </div>
    `;
    
    if (info.update_available) {
        html += `
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                <strong>Update Available!</strong> A new version is ready to install.
            </div>
        `;
        updateBtn.style.display = "block";
    } else {
        html += `
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                You are running the latest version.
            </div>
        `;
        updateBtn.style.display = "none";
    }
    
    if (info.has_local_changes) {
        html += `
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Warning:</strong> Local changes detected. They will be stashed before updating.
            </div>
        `;
    }
    
    if (changelog && changelog.length > 0) {
        html += `
            <h6 class="mt-4 mb-3">Recent Changes</h6>
            <div class="bg-light p-3 rounded" style="max-height: 200px; overflow-y: auto;">
                <ul class="list-unstyled mb-0">
        `;
        changelog.forEach(line => {
            html += `<li class="mb-1"><code class="text-dark">${line}</code></li>`;
        });
        html += `
                </ul>
            </div>
        `;
    }
    
    html += `
        <div class="mt-3">
            <small class="text-muted">
                <i class="bi bi-clock me-1"></i>
                Last checked: ${new Date(info.last_check * 1000).toLocaleString()}
            </small>
        </div>
    `;
    
    document.getElementById("updateContent").innerHTML = html;
}

async function performUpdate() {
    const updateBtn = document.getElementById("performUpdateBtn");
    const originalText = updateBtn.innerHTML;
    
    if (!confirm("Are you sure you want to update? This will pull the latest changes from Git and may restart services.")) {
        return;
    }
    
    updateBtn.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2\"></span>Updating...";
    updateBtn.disabled = true;
    
    document.getElementById("updateContent").innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <h5>Installing Update...</h5>
            <p class="text-muted">Please wait, this may take a moment.</p>
        </div>
    `;
    
    try {
        const response = await fetch("api.php?action=perform_update", {
            method: "POST"
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById("updateContent").innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Update Successful!</strong><br>
                    ${result.message}
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    The page will reload in 3 seconds...
                </div>
            `;
            
            setTimeout(() => {
                location.reload();
            }, 3000);
        } else {
            document.getElementById("updateContent").innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Update Failed!</strong><br>
                    ${result.error}
                </div>
            `;
            updateBtn.innerHTML = originalText;
            updateBtn.disabled = false;
        }
    } catch (error) {
        document.getElementById("updateContent").innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Network Error!</strong><br>
                ${error.message}
            </div>
        `;
        updateBtn.innerHTML = originalText;
        updateBtn.disabled = false;
    }
}
