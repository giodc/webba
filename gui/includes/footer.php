<footer class="bg-light border-top py-3 mt-5">
    <div class="container text-center">
        <small class="text-muted">
            <i class="bi bi-cloud-arrow-up me-1"></i>
            Webbadeploy v<?php 
                $versionFile = '/var/www/html/../VERSION';
                echo file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.0.0';
            ?>
        </small>
    </div>
</footer>
