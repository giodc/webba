let createModal, editModal;

// Version check - if you see this in console, the new JS is loaded
console.log("WebBadeploy JS v2.0 loaded - Settings page is active!");

document.addEventListener("DOMContentLoaded", function() {
    createModal = new bootstrap.Modal(document.getElementById("createModal"));
    editModal = new bootstrap.Modal(document.getElementById("editModal"));
    
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

    // Update site statuses
    updateAllSiteStatuses();
    setInterval(updateAllSiteStatuses, 30000); // Check every 30 seconds
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
